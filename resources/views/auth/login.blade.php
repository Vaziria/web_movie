@extends('layouts.app')

@php $input = 'h-11 w-full rounded-lg border border-ink-700 bg-ink-900 px-3 text-base text-white placeholder:text-neutral-500 focus:border-accent focus:outline-none'; @endphp

@section('content')
    <div class="mx-auto mt-4 max-w-sm">
        <h1 class="mb-1 text-xl font-semibold text-white">{{ __('Log in') }}</h1>
        <p class="mb-6 text-sm text-neutral-400">{{ __('Log in to watch every movie without limits.') }}</p>

        @if ($errors->any())
            <p role="alert" class="mb-4 rounded-lg border border-red-900 bg-red-950 px-3 py-2 text-sm text-red-300">{{ $errors->first() }}</p>
        @endif

        @if ($google)
            @include('partials.google-button')
            <p class="my-4 flex items-center gap-3 text-xs text-neutral-500"><span class="h-px flex-1 bg-ink-800"></span>{{ __('or with email') }}<span class="h-px flex-1 bg-ink-800"></span></p>
        @endif

        <form method="post" action="{{ lroute('auth.login.submit') }}" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="mb-1 block text-sm text-neutral-400">{{ __('Email') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" class="{{ $input }}">
            </div>
            <div>
                <label for="password" class="mb-1 block text-sm text-neutral-400">{{ __('Password') }}</label>
                <input id="password" type="password" name="password" required autocomplete="current-password" class="{{ $input }}">
            </div>
            <label class="flex min-h-11 items-center gap-2 text-sm text-neutral-400">
                <input type="checkbox" name="remember" value="1" checked class="size-4 accent-[var(--color-accent)]"> {{ __('Remember me') }}
            </label>
            <button type="submit" class="h-11 w-full rounded-lg bg-accent font-medium text-white hover:bg-accent-hover">{{ __('Log in') }}</button>
        </form>

        <div class="mt-6 flex flex-col items-center gap-1 text-sm">
            <a href="{{ lroute('auth.password.request') }}" class="py-2 text-neutral-400 hover:text-white">{{ __('Forgot password?') }}</a>
            <p class="text-neutral-400">{{ __('No account yet?') }} <a href="{{ lroute('auth.register') }}" class="inline-block py-2 font-medium text-white underline-offset-4 hover:underline">{{ __('Sign up for free') }}</a></p>
        </div>
    </div>
@endsection
