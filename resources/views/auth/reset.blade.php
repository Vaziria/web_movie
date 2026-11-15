@extends('layouts.app')

@php $input = 'h-11 w-full rounded-lg border border-ink-700 bg-ink-900 px-3 text-base text-white focus:border-accent focus:outline-none'; @endphp

@section('content')
    <div class="mx-auto mt-4 max-w-sm">
        <h1 class="mb-6 text-xl font-semibold text-white">{{ __('Choose a new password') }}</h1>
        @if ($errors->any())
            <div role="alert" class="mb-4 rounded-lg border border-red-900 bg-red-950 px-3 py-2 text-sm text-red-300">
                @foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach
            </div>
        @endif
        <form method="post" action="{{ lroute('auth.password.update') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label for="email" class="mb-1 block text-sm text-neutral-400">{{ __('Email') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username" class="{{ $input }}">
            </div>
            <div>
                <label for="password" class="mb-1 block text-sm text-neutral-400">{{ __('New password') }}</label>
                <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" class="{{ $input }}">
                <p class="mt-1 text-xs text-neutral-500">{{ __('At least 8 characters.') }}</p>
            </div>
            <div>
                <label for="password_confirmation" class="mb-1 block text-sm text-neutral-400">{{ __('Repeat password') }}</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="{{ $input }}">
            </div>
            <button type="submit" class="h-11 w-full rounded-lg bg-accent font-medium text-white hover:bg-accent-hover">{{ __('Save password and log in') }}</button>
        </form>
    </div>
@endsection
