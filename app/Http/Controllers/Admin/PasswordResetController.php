<?php

namespace App\Http\Controllers\Admin;

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

/** "Forgot password?": email a one-time reset link (60 minutes) to an admin, then let them pick a new password. */
class PasswordResetController extends Controller
{
    public const SENT = 'If that email belongs to an admin, a reset link is on its way. Check your inbox and spam folder; the link works for 60 minutes.';

    public function request(): View
    {
        return view('admin.auth.forgot');
    }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        // Only admins get a link. The answer is the same either way, so the form does not reveal who is an admin.
        // Laravel also limits it to one link per account per minute.
        if (User::query()->where('email', $data['email'])->where('is_admin', true)->exists()) {
            Password::sendResetLink(['email' => $data['email']]);
        }

        return back()->with('status', self::SENT)->withInput();
    }

    public function edit(Request $request, string $token): View
    {
        return view('admin.auth.reset', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'max:200', 'confirmed'],
        ]);

        $admin = null;
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use (&$admin) {
                if (! $user->is_admin) {
                    return;
                }
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
                $admin = $user;
            },
        );

        if ($status !== Password::PASSWORD_RESET || ! $admin) {
            throw ValidationException::withMessages([
                'email' => $status === Password::INVALID_TOKEN || $status === Password::PASSWORD_RESET
                    ? 'This reset link is invalid or has expired. Request a new one.'
                    : __($status),
            ]);
        }

        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->route('admin.movies.index')->with('status', 'Password changed. You are logged in.');
    }
}
