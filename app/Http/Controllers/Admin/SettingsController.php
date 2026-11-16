<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Controller;
use App\Services\Settings;
use App\Services\Stream\VidaraStream;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** Admin → Settings: Google login keys (secret encrypted, never shown), guest limit, contact email. */
class SettingsController extends Controller
{
    public function edit(Settings $settings): View
    {
        return view('admin.settings', [
            's' => $settings,
            'hasSecret' => $settings->has('google_client_secret'),
            'redirectUri' => GoogleController::callbackUrl(),
            'vidaraLast' => Cache::get(VidaraStream::LAST_RESULT_KEY),
        ]);
    }

    public function update(Request $request, Settings $settings): RedirectResponse
    {
        $data = $request->validate([
            'google_client_id' => ['nullable', 'string', 'max:200', 'regex:/^[0-9A-Za-z._-]+\.apps\.googleusercontent\.com$/'],
            'google_client_secret' => ['nullable', 'string', 'max:200'],
            'remove_google_secret' => ['sometimes', 'boolean'],
            'google_enabled' => ['sometimes', 'boolean'],
            'guest_free_movies' => ['required', 'integer', 'between:0,50'],
            'guest_limit_period' => ['required', 'in:total,day'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'premium_enabled' => ['sometimes', 'boolean'],
        ], ['google_client_id.regex' => 'The client ID ends with .apps.googleusercontent.com.']);

        $values = [
            'google_client_id' => $data['google_client_id'] ?? null,
            'google_enabled' => $request->boolean('google_enabled') ? '1' : '0',
            'guest_free_movies' => (string) $data['guest_free_movies'],
            'guest_limit_period' => $data['guest_limit_period'],
            'contact_email' => $data['contact_email'] ?? null,
            'premium_enabled' => $request->boolean('premium_enabled') ? '1' : '0',
        ];
        // An empty secret box keeps the saved secret; "remove" clears it.
        if ($request->boolean('remove_google_secret')) {
            $values['google_client_secret'] = null;
        } elseif (filled($data['google_client_secret'] ?? null)) {
            $values['google_client_secret'] = trim($data['google_client_secret']);
        }
        $settings->put($values);

        return redirect()->route('admin.settings')->with('status', 'Settings saved.'.($settings->googleEnabled() ? ' Google login is on.' : ''));
    }
}
