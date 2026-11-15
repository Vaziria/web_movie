@extends('admin.layout')

@php
    $input = 'h-11 w-full rounded-lg border border-ink-700 bg-ink-900 px-3 text-base text-white focus:border-accent focus:outline-none';
    $label = 'mb-1 block text-sm text-neutral-400';
@endphp

@section('title', 'Settings')

@section('content')
    <h1 class="mb-6 text-xl font-semibold text-white">Settings</h1>

    <form method="post" action="{{ route('admin.settings.update') }}" class="max-w-xl space-y-8">
        @csrf @method('PUT')

        <fieldset class="space-y-4 rounded-lg border border-ink-800 p-4">
            <legend class="px-1 text-sm text-neutral-400">Google login</legend>
            <p class="text-sm {{ $s->googleEnabled() ? 'text-emerald-400' : 'text-amber-300' }}">
                {{ $s->googleEnabled() ? '● On: visitors see "Continue with Google".' : '● Off: add the client ID and secret, then tick "On".' }}
            </p>
            <div>
                <label for="google_client_id" class="{{ $label }}">Client ID</label>
                <input id="google_client_id" name="google_client_id" value="{{ old('google_client_id', $s->googleClientId()) }}" placeholder="…apps.googleusercontent.com" class="{{ $input }}" autocomplete="off">
            </div>
            <div>
                <label for="google_client_secret" class="{{ $label }}">Client secret</label>
                <input id="google_client_secret" type="password" name="google_client_secret" placeholder="{{ $hasSecret ? '•••••••• saved (leave empty to keep)' : 'GOCSPX-…' }}" class="{{ $input }}" autocomplete="new-password">
                <p class="mt-1 text-xs text-neutral-500">Stored encrypted and never shown again. Never share it in chat.</p>
                @if ($hasSecret)
                    <label class="mt-2 flex items-center gap-2 text-sm text-neutral-400">
                        <input type="checkbox" name="remove_google_secret" value="1" class="size-4 accent-accent"> Remove the saved secret
                    </label>
                @endif
            </div>
            <label class="flex min-h-11 items-center gap-2 text-sm text-neutral-300">
                <input type="hidden" name="google_enabled" value="0">
                <input type="checkbox" name="google_enabled" value="1" @checked(old('google_enabled', $s->get('google_enabled', '1')) === '1') class="size-4 accent-accent"> On
            </label>
            <div class="rounded-lg bg-ink-900 p-3 text-xs text-neutral-400">
                In Google Cloud (Google Auth Platform → Clients → your web client) the <strong class="text-neutral-200">Authorized redirect URI</strong> must be:
                <code class="mt-1 block select-all break-all text-neutral-200">{{ $redirectUri }}</code>
            </div>
        </fieldset>

        <fieldset class="space-y-4 rounded-lg border border-ink-800 p-4">
            <legend class="px-1 text-sm text-neutral-400">Free movies for visitors who are not logged in</legend>
            <div>
                <label for="guest_free_movies" class="{{ $label }}">Free movies</label>
                <input id="guest_free_movies" type="number" min="0" max="50" name="guest_free_movies" value="{{ old('guest_free_movies', $s->guestFreeMovies()) }}" class="{{ $input }} max-w-32">
                <p class="mt-1 text-xs text-neutral-500">After this many different movies the player asks them to log in. 0 = no limit (no login needed).</p>
            </div>
            <div class="space-y-1">
                @foreach (['total' => 'In total (once used up, always ask to log in)', 'day' => 'Per day (starts again every day, '.config('movies.admin_timezone_label').')'] as $value => $text)
                    <label class="flex min-h-11 items-center gap-2 text-sm text-neutral-300">
                        <input type="radio" name="guest_limit_period" value="{{ $value }}" @checked(old('guest_limit_period', $s->guestLimitPeriod()) === $value) class="size-4 accent-accent"> {{ $text }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <fieldset class="space-y-4 rounded-lg border border-ink-800 p-4">
            <legend class="px-1 text-sm text-neutral-400">Privacy page</legend>
            <div>
                <label for="contact_email" class="{{ $label }}">Contact email</label>
                <input id="contact_email" type="email" name="contact_email" value="{{ old('contact_email', $s->contactEmail()) }}" placeholder="privacy@moviemov.id" class="{{ $input }}">
                <p class="mt-1 text-xs text-neutral-500">Shown on the <a href="{{ lroute('pages.privacy', [], 'id') }}" target="_blank" rel="noopener" class="underline">privacy page</a> for data requests. Leave empty to hide it.</p>
            </div>
        </fieldset>

        <button type="submit" class="h-11 rounded-lg bg-accent px-6 font-medium text-white hover:bg-accent-hover">Save settings</button>
    </form>
@endsection
