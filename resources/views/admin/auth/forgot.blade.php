@extends('admin.layout')

@section('title', 'Forgot password')

@section('content')
    <div class="mx-auto mt-10 max-w-sm">
        <h1 class="mb-2 text-xl font-semibold text-white">Forgot your password?</h1>
        <p class="mb-6 text-sm text-neutral-400">Enter your admin email. We'll email you a link to choose a new password.</p>
        <form method="post" action="{{ route('admin.password.email') }}" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="mb-1 block text-sm text-neutral-400">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                       class="h-11 w-full rounded-lg border border-ink-700 bg-ink-900 px-3 text-base text-white focus:border-accent focus:outline-none">
            </div>
            <button type="submit" class="h-11 w-full rounded-lg bg-accent font-medium text-white hover:bg-accent-hover">Email me a reset link</button>
        </form>
        <p class="mt-6 text-center text-sm"><a href="{{ route('admin.login') }}" class="inline-block py-2 text-neutral-400 hover:text-white">Back to log in</a></p>
    </div>
@endsection
