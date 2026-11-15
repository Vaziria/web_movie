<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** The visitor's own account: name, password, delete. */
class AccountController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('account.show', [
            'user' => $user,
            'watched' => $user->watches()->count(),
            'seo' => ['title' => __('My account').' | '.config('app.name'), 'robots' => 'noindex, nofollow'],
        ]);
    }

    public function updateName(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);
        $request->user()->forceFill(['name' => trim($data['name'])])->save();

        return back()->with('status', __('Saved.'));
    }

    /** Change the password, or set a first one for a Google-only account (no current password then). */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => [$user->hasPassword() ? 'required' : 'nullable', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:200', 'confirmed'],
        ]);
        if ($user->hasPassword() && ! Hash::check((string) $data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => __('The current password is wrong.')]);
        }

        $user->forceFill(['password' => $data['password']])->save();

        return back()->with('status', __('Password saved.'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->is_admin) {
            return back()->withErrors(['confirm' => __('Admin accounts cannot be deleted here.')]);
        }

        $word = __('DELETE');
        $request->validate([
            'confirm' => ['required', 'string', function ($attr, $value, $fail) use ($word) {
                if (mb_strtoupper(trim((string) $value)) !== $word) {
                    $fail(__('Type :word to confirm.', ['word' => $word]));
                }
            }],
            'password' => [$user->hasPassword() ? 'required' : 'nullable', 'string'],
        ]);
        if ($user->hasPassword() && ! Hash::check((string) $request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => __('The password is wrong.')]);
        }

        Auth::logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to(lroute('movies.index'))->with('status', __('Your account has been deleted.'));
    }
}
