@extends('admin.layout')

@php
    $input = 'w-full rounded-lg border border-ink-700 bg-ink-900 px-3 text-base text-white focus:border-accent focus:outline-none';
    $label = 'mb-1 block text-sm text-neutral-400';
    $tz = config('movies.admin_timezone');
@endphp

@section('title', 'Push notifications')

@section('content')
    <h1 class="mb-4 text-xl font-semibold text-white">Push notifications</h1>

    @if (! $enabled)
        <p role="alert" class="mb-6 rounded-lg border border-amber-800 bg-amber-950 px-3 py-2 text-sm text-amber-200">
            Not set up on this server yet: run <code>php artisan app:push-keys</code>, then <code>php artisan config:cache</code>.
        </p>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
        <div class="rounded-lg border border-ink-800 p-3">
            <p class="text-2xl font-semibold text-white">{{ $total }}</p>
            <p class="text-xs text-neutral-500">subscribers @if ($total) ({{ collect($byLocale)->map(fn ($n, $l) => strtoupper($l).' '.$n)->join(', ') }}) @endif</p>
        </div>
        <div class="rounded-lg border border-ink-800 p-3">
            <p class="text-sm font-medium {{ $lastRun && $lastRun->gt(now()->subMinutes(5)) ? 'text-emerald-400' : 'text-amber-300' }}">
                {{ $lastRun ? 'Sender ran '.$lastRun->diffForHumans() : 'Sender has not run yet' }}
            </p>
            <p class="text-xs text-neutral-500">{{ $lastRun && $lastRun->gt(now()->subMinutes(5)) ? 'runs every minute' : 'check the server cron job (docs/DEPLOY.md)' }}</p>
        </div>
    </div>

    <p class="mb-6 max-w-2xl text-sm text-neutral-400">
        New movies are announced automatically when they go live (publish now, or at their scheduled time). Untick
        "Notify push subscribers" in a movie's Publish box to skip one. Several movies going live in the same minute
        become one notification.
    </p>

    <form method="post" action="{{ route('admin.push.store') }}" class="mb-10 max-w-xl space-y-4 rounded-lg border border-ink-800 p-4">
        @csrf
        <h2 class="font-medium text-white">Send a custom notification</h2>
        <div>
            <label for="title" class="{{ $label }}">Title *</label>
            <input id="title" name="title" value="{{ old('title') }}" required maxlength="100" placeholder="Film baru minggu ini" class="{{ $input }} h-11">
        </div>
        <div>
            <label for="body" class="{{ $label }}">Message</label>
            <input id="body" name="body" value="{{ old('body') }}" maxlength="200" placeholder="5 film baru sudah bisa ditonton." class="{{ $input }} h-11">
        </div>
        <div>
            <label for="url" class="{{ $label }}">Link</label>
            <input id="url" name="url" value="{{ old('url', '/') }}" maxlength="500" placeholder="/category/drama" class="{{ $input }} h-11">
            <p class="mt-1 text-xs text-neutral-500">A page on this site, e.g. / or /category/drama or a movie page.</p>
        </div>
        <button type="submit" @disabled(! $enabled || ! $total) class="h-11 rounded-lg bg-accent px-6 font-medium text-white hover:bg-accent-hover disabled:opacity-40">
            Send to {{ $total }} {{ \Illuminate\Support\Str::plural('subscriber', $total) }}
        </button>
    </form>

    <h2 class="mb-2 font-medium text-white">Latest notifications</h2>
    @if ($messages->isEmpty())
        <p class="py-8 text-center text-neutral-500">Nothing sent yet.</p>
    @else
        <ul class="divide-y divide-ink-800 border-y border-ink-800">
            @foreach ($messages as $m)
                <li class="py-3">
                    <p class="line-clamp-1 text-sm text-white">{{ $m->label() }}</p>
                    <p class="text-xs text-neutral-500">
                        <span @class(['text-emerald-400' => $m->status === 'sent', 'text-amber-300' => $m->status !== 'sent'])>{{ $m->status }}</span>
                        · {{ $m->sent }} delivered @if ($m->failed) · {{ $m->failed }} failed @endif @if ($m->expired) · {{ $m->expired }} unsubscribed @endif
                        · {{ ($m->sent_at ?? $m->created_at)->timezone($tz)->format('j M H:i') }} {{ config('movies.admin_timezone_label') }}
                    </p>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
