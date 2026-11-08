@extends('admin.layout')

@section('title', 'Log in')

@section('content')
    <div class="mx-auto mt-10 max-w-sm">
        <div class="mb-6 flex items-center gap-2">
            <img src="{{ asset('favicon.svg') }}" alt="" width="32" height="32" class="size-8">
            <h1 class="text-xl font-semibold text-white">{{ config('app.name') }} admin</h1>
        </div>
        <form method="post" action="{{ route('admin.login.submit') }}" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="mb-1 block text-sm text-neutral-400">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                       class="h-11 w-full rounded-lg border border-ink-700 bg-ink-900 px-3 text-base text-white focus:border-accent focus:outline-none">
            </div>
            <div>
                <label for="password" class="mb-1 block text-sm text-neutral-400">Password</label>
                <input id="password" type="password" name="password" required autocomplete="current-password"
                       class="h-11 w-full rounded-lg border border-ink-700 bg-ink-900 px-3 text-base text-white focus:border-accent focus:outline-none">
            </div>
            <label class="flex items-center gap-2 text-sm text-neutral-400">
                <input type="checkbox" name="remember" value="1" class="size-4 accent-[var(--color-accent)]"> Remember me
            </label>
            <button type="submit" class="h-11 w-full rounded-lg bg-accent font-medium text-white hover:bg-accent-hover">Log in</button>
        </form>
        <p class="mt-6 text-center text-sm"><a href="{{ route('admin.password.request') }}" class="inline-block py-2 text-neutral-400 hover:text-white">Forgot password?</a></p>
    </div>
@endsection
