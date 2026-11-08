@extends('admin.layout')

@php
    $input = 'h-11 w-full rounded-lg border border-ink-700 bg-ink-900 px-3 text-base text-white focus:border-accent focus:outline-none';
    $label = 'mb-1 block text-sm text-neutral-400';
@endphp

@section('title', 'Account')

@section('content')
    <h1 class="mb-6 text-xl font-semibold text-white">Account</h1>

    <div class="grid max-w-3xl gap-10 md:grid-cols-2">
        <form method="post" action="{{ route('admin.account.password') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <h2 class="font-medium text-white">Change password</h2>
            <div>
                <label for="pw_current" class="{{ $label }}">Current password</label>
                <input id="pw_current" type="password" name="current_password" required autocomplete="current-password" class="{{ $input }}">
            </div>
            <div>
                <label for="password" class="{{ $label }}">New password</label>
                <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" class="{{ $input }}">
                <p class="mt-1 text-xs text-neutral-500">At least 8 characters.</p>
            </div>
            <div>
                <label for="password_confirmation" class="{{ $label }}">Repeat new password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="{{ $input }}">
            </div>
            <button type="submit" class="h-11 rounded-lg bg-accent px-6 font-medium text-white hover:bg-accent-hover">Save password</button>
        </form>

        <form method="post" action="{{ route('admin.account.email') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <h2 class="font-medium text-white">Login email</h2>
            <div>
                <label for="email" class="{{ $label }}">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="username" class="{{ $input }}">
                <p class="mt-1 text-xs text-neutral-500">Use an inbox you read: "Forgot password?" links are sent here.</p>
            </div>
            <div>
                <label for="em_current" class="{{ $label }}">Current password</label>
                <input id="em_current" type="password" name="current_password" required autocomplete="current-password" class="{{ $input }}">
            </div>
            <button type="submit" class="h-11 rounded-lg border border-ink-700 px-6 font-medium text-white hover:border-neutral-500">Save email</button>
        </form>
    </div>
@endsection
