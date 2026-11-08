@extends('admin.layout')

@section('title', 'Choose a new password')

@section('content')
    <div class="mx-auto mt-10 max-w-sm">
        <h1 class="mb-6 text-xl font-semibold text-white">Choose a new password</h1>
        <form method="post" action="{{ route('admin.password.update') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label for="email" class="mb-1 block text-sm text-neutral-400">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username"
                       class="h-11 w-full rounded-lg border border-ink-700 bg-ink-900 px-3 text-base text-white focus:border-accent focus:outline-none">
            </div>
            <div>
                <label for="password" class="mb-1 block text-sm text-neutral-400">New password</label>
                <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" @if ($email) autofocus @endif
                       class="h-11 w-full rounded-lg border border-ink-700 bg-ink-900 px-3 text-base text-white focus:border-accent focus:outline-none">
                <p class="mt-1 text-xs text-neutral-500">At least 8 characters.</p>
            </div>
            <div>
                <label for="password_confirmation" class="mb-1 block text-sm text-neutral-400">Repeat new password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"
                       class="h-11 w-full rounded-lg border border-ink-700 bg-ink-900 px-3 text-base text-white focus:border-accent focus:outline-none">
            </div>
            <button type="submit" class="h-11 w-full rounded-lg bg-accent font-medium text-white hover:bg-accent-hover">Save password and log in</button>
        </form>
        <p class="mt-6 text-center text-sm"><a href="{{ route('admin.password.request') }}" class="inline-block py-2 text-neutral-400 hover:text-white">Link expired? Get a new one</a></p>
    </div>
@endsection
