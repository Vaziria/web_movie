<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The logged-in admin's own login email and password. Both changes need the current password. */
class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.account', ['user' => $request->user()]);
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['required', 'current_password'],
        ]);

        $user->forceFill(['email' => $data['email']])->save();

        return back()->with('status', "Login email changed to {$data['email']}. Password reset links go there too.");
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'max:200', 'confirmed'],
        ]);

        $request->user()->forceFill(['password' => $data['password']])->save();

        return back()->with('status', 'Password changed.');
    }
}
