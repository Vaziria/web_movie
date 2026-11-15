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
