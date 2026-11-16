@extends('admin.layout')

@php
    $tz = config('movies.admin_timezone');
    $tzl = config('movies.admin_timezone_label');
    $protected = $user->is_admin || $user->is(auth()->user());
@endphp

@section('title', $user->name)

@section('content')
    <a href="{{ route('admin.users.index') }}" class="mb-4 inline-block text-sm text-neutral-400 hover:text-white">← Users</a>

    <div class="mb-6 flex items-center gap-3">
        <span class="inline-flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-full bg-ink-800 font-semibold text-white">
            @if ($user->avatar_url)<img src="{{ $user->avatar_url }}" alt="" width="48" height="48" class="size-full object-cover" referrerpolicy="no-referrer">@else{{ $user->initials() }}@endif
        </span>
        <div class="min-w-0">
            <h1 class="truncate text-xl font-semibold text-white">{{ $user->name }}</h1>
            <p class="truncate text-sm text-neutral-400">{{ $user->email }}</p>
        </div>
    </div>

    <dl class="mb-6 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
        @foreach ([
            'Status' => $user->isBlocked() ? 'Blocked '.$user->blocked_at->timezone($tz)->format('j M Y') : ($user->is_admin ? 'Admin' : 'Active'),
            'Premium' => $user->premium_until?->isFuture() ? 'until '.$user->premium_until->timezone($tz)->format('j M Y H:i').' '.$tzl : ($user->is_admin ? 'always (admin)' : ($user->premium_until ? 'ended '.$user->premium_until->timezone($tz)->format('j M Y') : 'no')),
            'Login' => trim(($user->google_id ? 'Google' : '').($user->google_id && $user->hasPassword() ? ' + ' : '').($user->hasPassword() ? 'Email' : '')),
            'Joined' => $user->created_at->timezone($tz)->format('j M Y H:i').' '.$tzl,
            'Last active' => $user->last_active_at ? $user->last_active_at->timezone($tz)->format('j M Y H:i').' '.$tzl : 'never',
            'Movies watched' => $user->watches_count,
            'Active days' => $activeDays,
        ] as $label => $value)
            <div class="rounded-lg border border-ink-800 p-3">
                <dt class="text-xs text-neutral-500">{{ $label }}</dt>
                <dd class="mt-0.5 text-white">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    @if ($protected)
        <p class="mb-6 text-sm text-neutral-500">Admin accounts can't be blocked, deleted or reset here.</p>
    @else
        <div class="mb-8 flex flex-wrap gap-2">
            @if ($user->isBlocked())
                <form method="post" action="{{ route('admin.users.unblock', $user) }}">@csrf
                    <button class="h-10 rounded-lg border border-ink-700 px-4 text-sm text-white hover:border-neutral-500">Unblock</button>
                </form>
            @else
                <form method="post" action="{{ route('admin.users.block', $user) }}" onsubmit="return confirm('Block this user? They are logged out and cannot log in.')">@csrf
                    <button class="h-10 rounded-lg border border-amber-800 px-4 text-sm text-amber-200 hover:bg-amber-950">Block</button>
                </form>
            @endif
            @if ($user->hasPassword())
                <form method="post" action="{{ route('admin.users.reset', $user) }}">@csrf
                    <button class="h-10 rounded-lg border border-ink-700 px-4 text-sm text-white hover:border-neutral-500">Send password reset link</button>
                </form>
            @endif
            <form method="post" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Delete this account and its watch history for good?')">@csrf @method('DELETE')
                <button class="h-10 rounded-lg border border-red-900 px-4 text-sm text-red-300 hover:bg-red-950">Delete</button>
            </form>
        </div>
    @endif

    <section class="mb-8 rounded-lg border border-ink-800 p-4">
        <h2 class="mb-1 font-medium text-white">Premium (ad-free player)</h2>
        <p class="mb-3 text-xs text-neutral-500">Set by hand until payments are added. "Add" counts from today, or from the current end date if premium is still running.</p>
        <div class="flex flex-wrap items-center gap-2">
            @foreach ([30 => '+30 days', 90 => '+90 days', 365 => '+1 year'] as $days => $label)
                <form method="post" action="{{ route('admin.users.premium', $user) }}">@csrf
                    <input type="hidden" name="action" value="add"><input type="hidden" name="days" value="{{ $days }}">
                    <button class="h-10 rounded-lg border border-amber-700 px-4 text-sm text-amber-200 hover:bg-amber-950">{{ $label }}</button>
                </form>
            @endforeach
            <form method="post" action="{{ route('admin.users.premium', $user) }}" class="flex items-center gap-2">@csrf
                <input type="hidden" name="action" value="set">
                <label for="until" class="sr-only">Premium until</label>
                <input id="until" type="date" name="until" required min="{{ now()->timezone($tz)->addDay()->toDateString() }}"
                       value="{{ $user->premium_until?->isFuture() ? $user->premium_until->timezone($tz)->toDateString() : '' }}"
                       class="h-10 rounded-lg border border-ink-700 bg-ink-900 px-2 text-sm text-white">
                <button class="h-10 rounded-lg border border-ink-700 px-3 text-sm text-white hover:border-neutral-500">Set date</button>
            </form>
            @if ($user->premium_until?->isFuture())
                <form method="post" action="{{ route('admin.users.premium', $user) }}" onsubmit="return confirm('Remove premium for this user?')">@csrf
                    <input type="hidden" name="action" value="remove">
                    <button class="h-10 rounded-lg px-3 text-sm text-red-300 hover:bg-red-950">Remove</button>
                </form>
            @endif
        </div>
        @error('until')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
    </section>

    <h2 class="mb-2 font-medium text-white">Recently watched</h2>
    @if ($watched->isEmpty())
        <p class="py-6 text-sm text-neutral-500">Nothing yet.</p>
    @else
        <ul class="divide-y divide-ink-800 border-y border-ink-800 text-sm">
            @foreach ($watched as $w)
                <li class="flex items-center justify-between gap-3 py-2">
                    <a href="{{ route('movies.show', $w->slug) }}" target="_blank" rel="noopener" class="truncate text-white hover:underline">{{ $w->title }}</a>
                    <span class="shrink-0 text-xs text-neutral-500">{{ \Illuminate\Support\Carbon::parse($w->last_at)->timezone($tz)->format('j M H:i') }}</span>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
