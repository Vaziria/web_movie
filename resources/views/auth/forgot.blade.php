@extends('layouts.app')

@section('content')
    <div class="mx-auto mt-4 max-w-sm">
        <h1 class="mb-2 text-xl font-semibold text-white">{{ __('Forgot password?') }}</h1>
        <p class="mb-6 text-sm text-neutral-400">{{ __('Enter your email. We will send you a link to choose a new password.') }}</p>
        @if ($errors->any())
            <p role="alert" class="mb-4 rounded-lg border border-red-900 bg-red-950 px-3 py-2 text-sm text-red-300">{{ $errors->first() }}</p>
        @endif
        <form method="post" action="{{ lroute('auth.password.email') }}" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="mb-1 block text-sm text-neutral-400">{{ __('Email') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                       class="h-11 w-full rounded-lg border border-ink-700 bg-ink-900 px-3 text-base text-white focus:border-accent focus:outline-none">
            </div>
            <button type="submit" class="h-11 w-full rounded-lg bg-accent font-medium text-white hover:bg-accent-hover">{{ __('Send reset link') }}</button>
        </form>
        <p class="mt-4 text-xs text-neutral-500">{{ __('Signed up with Google? Just tap "Continue with Google" on the login page.') }}</p>
        <p class="mt-4 text-center text-sm"><a href="{{ lroute('auth.login') }}" class="inline-block py-2 text-neutral-400 hover:text-white">{{ __('Back to log in') }}</a></p>
    </div>
@endsection
