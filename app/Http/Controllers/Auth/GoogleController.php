<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * "Continue with Google" (Socialite). The client ID/secret come from Admin → Settings (secret encrypted), applied
 * at runtime. One callback URL for both languages: /auth/google/callback.
 */
class GoogleController extends Controller
{
    public function __construct(private Settings $settings) {}

    public function redirect(Request $request): RedirectResponse
    {
        abort_unless($this->settings->googleEnabled(), 404);
        LoginController::rememberNext($request);
        $request->session()->put('auth.locale', in_array($request->query('lang'), array_keys(site_locales()), true) ? $request->query('lang') : 'id');

        return $this->driver()->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless($this->settings->googleEnabled(), 404);
        $locale = (string) $request->session()->get('auth.locale', 'id');
        app()->setLocale($locale);
        $login = lroute('auth.login', [], $locale);

        if ($request->has('error')) {   // the visitor pressed "Cancel" at Google
            return redirect()->to($login);
        }

        try {
            $google = $this->driver()->user();
        } catch (Throwable $e) {
            Log::warning('Google login failed', ['error' => $e->getMessage()]);

            return redirect()->to($login)->withErrors(['email' => __('Google login failed. Try again.')]);
        }

        $email = strtolower((string) $google->getEmail());
        if ($email === '' || ($google->getRaw()['email_verified'] ?? true) === false) {
            return redirect()->to($login)->withErrors(['email' => __('Your Google account has no verified email.')]);
        }

        // Same Google account, or an existing account with the same (Google-verified) email: log in and link it.
        $user = User::query()->where('google_id', $google->getId())->first()
            ?? User::query()->where('email', $email)->first();
        if ($user?->isBlocked()) {
            return redirect()->to($login)->withErrors(['email' => __('This account has been blocked.')]);
        }

        if ($user) {
            $user->forceFill([
                'google_id' => $google->getId(),
                'avatar_url' => $google->getAvatar() ?: $user->avatar_url,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
            $welcome = null;
        } else {
            $user = new User(['name' => $google->getName() ?: strstr($email, '@', true), 'email' => $email, 'google_id' => $google->getId(), 'avatar_url' => $google->getAvatar()]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $welcome = __('Welcome, :name! You can now watch without limits.', ['name' => $user->name]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(lroute('movies.index', [], $locale))->with('status', $welcome);
    }

    /** Built from APP_URL, so it is always https://moviemov.id/... even behind the host's proxy. */
    public static function callbackUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/auth/google/callback';
    }

    private function driver()
    {
        config(['services.google' => [
            'client_id' => $this->settings->googleClientId(),
            'client_secret' => $this->settings->get('google_client_secret'),
            'redirect' => self::callbackUrl(),
        ]]);

        return Socialite::driver('google');
    }
}
