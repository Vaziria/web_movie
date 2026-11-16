@extends('admin.layout')

@php
    $tz = config('movies.admin_timezone');
    $filters = \App\Http\Controllers\Admin\UserController::FILTERS;
@endphp

@section('title', 'Users')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-white">Users <span class="text-sm font-normal text-neutral-500">({{ $total }})</span></h1>
        <a href="{{ route('admin.stats') }}" class="text-sm text-neutral-400 hover:text-white">Active users →</a>
    </div>

    <form method="get" class="mb-3 flex gap-2">
        <label for="q" class="sr-only">Search users</label>
        <input id="q" type="search" name="q" value="{{ $q }}" placeholder="Name or email" class="h-10 min-w-0 flex-1 rounded-lg border border-ink-700 bg-ink-900 px-3 text-base text-white focus:border-accent focus:outline-none">
        @if ($filter !== 'all')<input type="hidden" name="filter" value="{{ $filter }}">@endif
        <button type="submit" class="h-10 rounded-lg border border-ink-700 px-4 text-sm hover:border-neutral-500">Search</button>
    </form>
    <nav class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 text-sm [scrollbar-width:none]" aria-label="Filter">
        @foreach ($filters as $key => $label)
            <a href="{{ route('admin.users.index', array_filter(['q' => $q, 'filter' => $key === 'all' ? null : $key])) }}"
               @class(['shrink-0 rounded-full border px-3 py-1.5', 'border-white bg-white text-ink-950' => $filter === $key, 'border-ink-700 text-neutral-300 hover:border-neutral-500' => $filter !== $key])>{{ $label }}</a>
        @endforeach
    </nav>

    @if ($users->isEmpty())
        <p class="py-12 text-center text-neutral-500">No users {{ $q !== '' || $filter !== 'all' ? 'match this filter' : 'yet' }}.</p>
    @else
        <ul class="divide-y divide-ink-800 border-y border-ink-800">
            @foreach ($users as $u)
                <li>
                    <a href="{{ route('admin.users.show', $u) }}" class="flex items-center gap-3 py-3 hover:bg-ink-900">
                        <span class="inline-flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-ink-800 text-xs font-semibold text-white">
                            @if ($u->avatar_url)<img src="{{ $u->avatar_url }}" alt="" width="36" height="36" class="size-full object-cover" loading="lazy" referrerpolicy="no-referrer">@else{{ $u->initials() }}@endif
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2">
                                <span class="truncate text-sm font-medium text-white">{{ $u->name }}</span>
                                @if ($u->is_admin)<span class="rounded bg-accent px-1.5 text-[10px] font-medium leading-4 text-white">ADMIN</span>@endif
                                @if ($u->premium_until?->isFuture())<span class="rounded bg-amber-400 px-1.5 text-[10px] font-bold leading-4 text-ink-950">PREMIUM</span>@endif
                                @if ($u->isBlocked())<span class="rounded bg-red-950 px-1.5 text-[10px] font-medium leading-4 text-red-300">BLOCKED</span>@endif
                                @if ($u->google_id)<span class="text-[10px] text-neutral-500">Google</span>@endif
                            </span>
                            <span class="block truncate text-xs text-neutral-500">{{ $u->email }}</span>
                            <span class="block text-xs text-neutral-500">
                                joined {{ $u->created_at->timezone($tz)->format('j M Y') }}
                                · {{ $u->last_active_at ? 'active '.$u->last_active_at->diffForHumans() : 'never active' }}
                                · {{ $u->watches_count }} {{ \Illuminate\Support\Str::plural('movie', $u->watches_count) }}
                            </span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $users->links('pagination.simple') }}</div>
    @endif
@endsection
