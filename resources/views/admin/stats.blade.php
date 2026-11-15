@extends('admin.layout')

@php
    $maxDay = max(1, collect($daily)->max(fn ($d) => $d['users'] + $d['guests']));
    $maxMonth = max(1, collect($monthly)->max(fn ($m) => $m['users'] + $m['guests']));
    $maxSign = max(1, collect($daily)->max('signups'));
    $t = $totals;
    $split = fn (int $u, int $g) => $u.' '.\Illuminate\Support\Str::plural('user', $u).' · '.$g.' '.\Illuminate\Support\Str::plural('guest', $g);
@endphp

@section('title', 'Statistics')

@section('content')
    <h1 class="mb-4 text-xl font-semibold text-white">Statistics</h1>

    <div class="mb-8 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ([
            ['Active today', $t['active_today_users'] + $t['active_today_guests'], $split($t['active_today_users'], $t['active_today_guests'])],
            ['Active this month', $t['active_month_users'] + $t['active_month_guests'], $split($t['active_month_users'], $t['active_month_guests'])],
            ['Users', $t['users_total'], $t['google_users'].' via Google'.($t['users_blocked'] ? ' · '.$t['users_blocked'].' blocked' : '')],
            ['Sign-ups today', $t['signups_today'], 'new accounts'],
        ] as [$label, $value, $sub])
            <div class="rounded-lg border border-ink-800 p-3">
                <p class="text-2xl font-semibold text-white">{{ nf($value) }}</p>
                <p class="text-xs text-neutral-400">{{ $label }}</p>
                <p class="text-xs text-neutral-500">{{ $sub }}</p>
            </div>
        @endforeach
    </div>

    <p class="mb-2 flex items-center gap-4 text-xs text-neutral-400">
        <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-accent"></span>Logged-in users</span>
        <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-neutral-500"></span>Guests</span>
    </p>

    <section class="mb-10">
        <h2 class="mb-2 font-medium text-white">Daily active users <span class="text-sm font-normal text-neutral-500">· last 30 days</span></h2>
        <div class="flex h-40 items-end gap-[3px] border-b border-ink-700" role="img" aria-label="Daily active users, last 30 days">
            @foreach ($daily as $d)
                <div class="group relative flex h-full flex-1 flex-col justify-end" title="{{ \Illuminate\Support\Carbon::parse($d['day'])->format('D j M') }}: {{ $d['users'] }} users, {{ $d['guests'] }} guests">
                    <div class="bg-neutral-500" style="height: {{ round($d['guests'] / $maxDay * 100, 2) }}%"></div>
                    <div class="bg-accent" style="height: {{ round($d['users'] / $maxDay * 100, 2) }}%"></div>
                </div>
            @endforeach
        </div>
        <div class="mt-1 flex justify-between text-[10px] text-neutral-500">
            <span>{{ \Illuminate\Support\Carbon::parse($daily[0]['day'])->format('j M') }}</span><span>max {{ $maxDay }}</span><span>today</span>
        </div>
    </section>

    <section class="mb-10">
        <h2 class="mb-2 font-medium text-white">Monthly active users <span class="text-sm font-normal text-neutral-500">· last 12 months, distinct visitors</span></h2>
        <div class="flex h-40 items-end gap-1.5 border-b border-ink-700" role="img" aria-label="Monthly active users, last 12 months">
            @foreach ($monthly as $m)
                <div class="flex h-full flex-1 flex-col justify-end" title="{{ \Illuminate\Support\Carbon::parse($m['month'].'-01')->format('F Y') }}: {{ $m['users'] }} users, {{ $m['guests'] }} guests">
                    <div class="bg-neutral-500" style="height: {{ round($m['guests'] / $maxMonth * 100, 2) }}%"></div>
                    <div class="bg-accent" style="height: {{ round($m['users'] / $maxMonth * 100, 2) }}%"></div>
                </div>
            @endforeach
        </div>
        <div class="mt-1 flex justify-between gap-1.5 text-[10px] text-neutral-500">
            @foreach ($monthly as $m)<span class="flex-1 text-center">{{ \Illuminate\Support\Carbon::parse($m['month'].'-01')->format('M') }}</span>@endforeach
        </div>
    </section>

    <section class="mb-10">
        <h2 class="mb-2 font-medium text-white">Sign-ups per day <span class="text-sm font-normal text-neutral-500">· last 30 days</span></h2>
        <div class="flex h-24 items-end gap-[3px] border-b border-ink-700" role="img" aria-label="Sign-ups per day">
            @foreach ($daily as $d)
                <div class="flex-1 bg-emerald-500" style="height: {{ round($d['signups'] / $maxSign * 100, 2) }}%" title="{{ \Illuminate\Support\Carbon::parse($d['day'])->format('D j M') }}: {{ $d['signups'] }}"></div>
            @endforeach
        </div>
    </section>

    <details class="mb-6 text-sm">
        <summary class="cursor-pointer py-2 text-neutral-400 hover:text-white">Numbers per day</summary>
        <table class="mt-2 w-full text-left text-xs">
            <thead class="text-neutral-500"><tr><th class="py-1">Day</th><th>Users</th><th>Guests</th><th>Sign-ups</th></tr></thead>
            <tbody class="text-neutral-300">
                @foreach (array_reverse($daily) as $d)
                    <tr class="border-t border-ink-800"><td class="py-1">{{ \Illuminate\Support\Carbon::parse($d['day'])->format('D j M') }}</td><td>{{ $d['users'] }}</td><td>{{ $d['guests'] }}</td><td>{{ $d['signups'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </details>

    <p class="text-xs text-neutral-500">
        Days are {{ config('movies.admin_timezone_label') }}. A visitor counts once per day. Users are counted per account, guests per browser
        (a guest is counted from their second page view, so search-engine crawlers are left out). Monthly = distinct visitors in that month.
    </p>
@endsection
