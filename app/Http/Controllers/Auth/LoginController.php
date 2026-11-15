<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Visitor login, registration and logout (email + password). Google login: GoogleController. */
class LoginController extends Controller
{
    public function __construct(private Settings $settings) {}

    /** Remember where to go back to: ?next=/movies/x (same-site paths only). */
    public static function rememberNext(Request $request): void
    {
        $next = (string) $request->query('next', '');
        if ($next !== '' && str_starts_with($next, '/') && ! str_starts_with($next, '//')) {
            $request->session()->put('url.intended', url($next));
        }
    }

    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->to(lroute('movies.index'));
        }
        self::rememberNext($request);

        return view('auth.login', ['google' => $this->settings->googleEnabled(), 'seo' => $this->seo(__('Log in'))]);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();
        if ($user?->isBlocked()) {
            throw ValidationException::withMessages(['email' => __('This account has been blocked.')]);
        }
        if (! Auth::attempt($data, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => $user && ! $user->hasPassword()
                    ? __('This account uses Google login. Tap "Continue with Google".')
                    : __('Wrong email or password.'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(lroute('movies.index'));
    }

    public function showRegister(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->to(lroute('movies.index'));
        }
        self::rememberNext($request);

        return view('auth.register', ['google' => $this->settings->googleEnabled(), 'seo' => $this->seo(__('Sign up'))]);
    }

    public function register(Request $request): RedirectResponse
    {
        // `website` is a honeypot: people never see it, simple bots fill it in.
        abort_if(filled($request->input('website')), 422);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:200', 'confirmed'],
        ], ['email.unique' => __('This email is already registered. Log in instead.')]);

        $user = User::create(['name' => trim($data['name']), 'email' => strtolower($data['email']), 'password' => $data['password']]);
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(lroute('movies.index'))->with('status', __('Welcome, :name! You can now watch without limits.', ['name' => $user->name]));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to(lroute('movies.index'));
    }

    /** Auth pages are not search results: noindex. */
    private function seo(string $title): array
    {
        return ['title' => $title.' | '.config('app.name'), 'robots' => 'noindex, follow'];
    }
}
