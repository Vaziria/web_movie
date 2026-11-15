@extends('layouts.app')

@php $input = 'h-11 w-full rounded-lg border border-ink-700 bg-ink-900 px-3 text-base text-white placeholder:text-neutral-500 focus:border-accent focus:outline-none'; @endphp

@section('content')
    <div class="mx-auto mt-4 max-w-sm">
        <h1 class="mb-1 text-xl font-semibold text-white">{{ __('Sign up for free') }}</h1>
        <p class="mb-6 text-sm text-neutral-400">{{ __('Free account: watch every movie without limits.') }}</p>

        @if ($errors->any())
            <div role="alert" class="mb-4 rounded-lg border border-red-900 bg-red-950 px-3 py-2 text-sm text-red-300">
                @foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach
            </div>
        @endif

        @if ($google)
            @include('partials.google-button')
            <p class="my-4 flex items-center gap-3 text-xs text-neutral-500"><span class="h-px flex-1 bg-ink-800"></span>{{ __('or with email') }}<span class="h-px flex-1 bg-ink-800"></span></p>
        @endif

        <form method="post" action="{{ lroute('auth.register.submit') }}" class="space-y-4">
            @csrf
            {{-- Honeypot: hidden from people, filled in by simple bots. --}}
            <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <div>
                <label for="name" class="mb-1 block text-sm text-neutral-400">{{ __('Name') }}</label>
                <input id="name" name="name" value="{{ old('name') }}" required maxlength="60" autocomplete="name" class="{{ $input }}">
            </div>
            <div>
                <label for="email" class="mb-1 block text-sm text-neutral-400">{{ __('Email') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" class="{{ $input }}">
            </div>
            <div>
                <label for="password" class="mb-1 block text-sm text-neutral-400">{{ __('Password') }}</label>
                <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" class="{{ $input }}">
                <p class="mt-1 text-xs text-neutral-500">{{ __('At least 8 characters.') }}</p>
            </div>
            <div>
                <label for="password_confirmation" class="mb-1 block text-sm text-neutral-400">{{ __('Repeat password') }}</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="{{ $input }}">
            </div>
            <p class="text-xs text-neutral-500">{!! __('By signing up you agree to our :privacy.', ['privacy' => '<a href="'.e(lroute('pages.privacy')).'" class="underline underline-offset-4 hover:text-neutral-300">'.e(__('Privacy Policy')).'</a>']) !!}</p>
            <button type="submit" class="h-11 w-full rounded-lg bg-accent font-medium text-white hover:bg-accent-hover">{{ __('Sign up') }}</button>
        </form>

        <p class="mt-6 text-center text-sm text-neutral-400">{{ __('Already have an account?') }} <a href="{{ lroute('auth.login') }}" class="inline-block py-2 font-medium text-white underline-offset-4 hover:underline">{{ __('Log in') }}</a></p>
    </div>
@endsection
