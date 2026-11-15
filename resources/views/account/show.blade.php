@extends('layouts.app')

@php $input = 'h-11 w-full rounded-lg border border-ink-700 bg-ink-900 px-3 text-base text-white focus:border-accent focus:outline-none'; @endphp

@section('content')
    <div class="mx-auto max-w-lg">
        <div class="mb-6 flex items-center gap-3">
            <span class="inline-flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-full border border-ink-700 bg-ink-800 font-semibold text-white">
                @if ($user->avatar_url)
                    <img src="{{ $user->avatar_url }}" alt="" width="48" height="48" class="size-full object-cover" referrerpolicy="no-referrer">
                @else
                    {{ $user->initials() }}
                @endif
            </span>
            <div class="min-w-0">
                <h1 class="truncate text-xl font-semibold text-white">{{ $user->name }}</h1>
                <p class="truncate text-sm text-neutral-400">{{ $user->email }}@if ($user->google_id) · {{ __('Google login') }}@endif</p>
            </div>
        </div>

        @if ($errors->any())
            <div role="alert" class="mb-4 rounded-lg border border-red-900 bg-red-950 px-3 py-2 text-sm text-red-300">
                @foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach
            </div>
        @endif

        <p class="mb-6 text-sm text-neutral-400">{{ trans_choice(':count movie watched|:count movies watched', $watched, ['count' => nf($watched)]) }} · {{ __('Member since :date', ['date' => $user->created_at->timezone(config('movies.admin_timezone'))->translatedFormat('j F Y')]) }}</p>

        <section class="mb-8 space-y-3 border-t border-ink-800 pt-6">
            <h2 class="font-medium text-white">{{ __('Name') }}</h2>
            <form method="post" action="{{ lroute('account.name') }}" class="flex gap-2">
                @csrf @method('PUT')
                <label for="name" class="sr-only">{{ __('Name') }}</label>
                <input id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="60" class="{{ $input }}">
                <button type="submit" class="h-11 shrink-0 rounded-lg border border-ink-700 px-4 text-sm text-white hover:border-neutral-500">{{ __('Save') }}</button>
            </form>
        </section>

        <section class="mb-8 space-y-3 border-t border-ink-800 pt-6">
            <h2 class="font-medium text-white">{{ $user->hasPassword() ? __('Change password') : __('Create a password') }}</h2>
            @unless ($user->hasPassword())
                <p class="text-sm text-neutral-400">{{ __('You log in with Google. Add a password to also log in with your email.') }}</p>
            @endunless
            <form method="post" action="{{ lroute('account.password') }}" class="space-y-3">
                @csrf @method('PUT')
                @if ($user->hasPassword())
                    <div>
                        <label for="current_password" class="mb-1 block text-sm text-neutral-400">{{ __('Current password') }}</label>
                        <input id="current_password" type="password" name="current_password" required autocomplete="current-password" class="{{ $input }}">
                    </div>
                @endif
                <div>
                    <label for="password" class="mb-1 block text-sm text-neutral-400">{{ __('New password') }}</label>
                    <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" class="{{ $input }}">
                </div>
                <div>
                    <label for="password_confirmation" class="mb-1 block text-sm text-neutral-400">{{ __('Repeat password') }}</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="{{ $input }}">
                </div>
                <button type="submit" class="h-11 rounded-lg border border-ink-700 px-5 text-sm text-white hover:border-neutral-500">{{ __('Save password') }}</button>
            </form>
        </section>

        <section class="mb-8 border-t border-ink-800 pt-6">
            <form method="post" action="{{ lroute('auth.logout') }}">
                @csrf
                <button type="submit" class="h-11 w-full rounded-lg bg-ink-800 px-5 text-sm font-medium text-white hover:bg-ink-700 sm:w-auto">{{ __('Log out') }}</button>
            </form>
        </section>

        @unless ($user->is_admin)
            <details class="border-t border-ink-800 pt-6">
                <summary class="cursor-pointer py-2 text-sm text-red-400 hover:text-red-300">{{ __('Delete my account') }}</summary>
                <form method="post" action="{{ lroute('account.destroy') }}" class="mt-3 space-y-3">
                    @csrf @method('DELETE')
                    <p class="text-sm text-neutral-400">{{ __('Your account and watch history are deleted for good. This cannot be undone.') }}</p>
                    @if ($user->hasPassword())
                        <div>
                            <label for="del_password" class="mb-1 block text-sm text-neutral-400">{{ __('Password') }}</label>
                            <input id="del_password" type="password" name="password" required autocomplete="current-password" class="{{ $input }}">
                        </div>
                    @endif
                    <div>
                        <label for="confirm" class="mb-1 block text-sm text-neutral-400">{{ __('Type :word to confirm', ['word' => __('DELETE')]) }}</label>
                        <input id="confirm" name="confirm" required autocomplete="off" class="{{ $input }}">
                    </div>
                    <button type="submit" class="h-11 rounded-lg border border-red-900 px-5 text-sm text-red-300 hover:bg-red-950">{{ __('Delete my account') }}</button>
                </form>
            </details>
        @endunless
    </div>
@endsection
