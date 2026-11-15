<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Visitor "forgot password": emailed one-time link (60 minutes). Admins use /admin/forgot-password. */
class PasswordController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot', ['seo' => ['title' => __('Forgot password').' | '.config('app.name'), 'robots' => 'noindex, follow']]);
    }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        // Same answer whether or not the email exists. Google-only accounts have no password to reset.
        $user = User::query()->where('email', strtolower($data['email']))->first();
        if ($user && ! $user->isBlocked() && ! $user->is_admin) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return back()->with('status', __('If that email has an account, a reset link is on its way. Check your inbox and spam folder.'))->withInput();
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
            'seo' => ['title' => __('Choose a new password').' | '.config('app.name'), 'robots' => 'noindex, nofollow'],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'max:200', 'confirmed'],
        ]);

        $done = null;
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use (&$done) {
                if ($user->is_admin || $user->isBlocked()) {
                    return;   // admins reset in the admin panel
                }
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
                $done = $user;
            },
        );

        if ($status !== Password::PASSWORD_RESET || ! $done) {
            throw ValidationException::withMessages(['email' => __('This reset link is invalid or has expired. Request a new one.')]);
        }

        Auth::login($done, true);
        $request->session()->regenerate();

        return redirect()->to(lroute('movies.index'))->with('status', __('Password changed. You are logged in.'));
    }
}
