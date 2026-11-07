@extends('admin.layout')

@section('title', 'Broken video reports')

@php
    $reasonLabels = ['not_loading' => 'does not load', 'wrong_video' => 'wrong video', 'bad_quality' => 'bad quality / no sound', 'other' => 'other'];
@endphp

@section('content')
    <h1 class="mb-1 text-xl font-semibold text-white">Broken video reports</h1>
    <p class="mb-4 text-sm text-neutral-500">Visitors' "Video not working?" reports that are still open. Fix the player (edit the movie), then mark it fixed.</p>

    @if ($rows->isEmpty())
        <p class="py-12 text-center text-neutral-500">No open reports. All players look fine.</p>
    @else
        <ul class="divide-y divide-ink-800 border-y border-ink-800">
            @foreach ($rows as $row)
                <li class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center">
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <div class="aspect-video w-24 shrink-0 overflow-hidden rounded bg-ink-800">
                            @if ($src = $row['movie']->thumbnailSrc())
                                <img src="{{ $src }}" alt="" class="size-full object-cover" loading="lazy" referrerpolicy="no-referrer">
                            @endif
                        </div>
                        <div class="min-w-0">
                            <a href="{{ route('admin.movies.edit', $row['movie']) }}" class="line-clamp-1 font-medium text-white hover:underline">{{ $row['movie']->title }}</a>
                            <p class="text-xs text-neutral-400">
                                <span class="font-medium text-red-400">{{ $row['count'] }} {{ \Illuminate\Support\Str::plural('report', $row['count']) }}</span>
                                · servers: {{ $row['servers']->map(fn ($n, $s) => "#{$s} ×{$n}")->join(', ') }}
                            </p>
                            <p class="text-xs text-neutral-500">
                                {{ $row['reasons']->map(fn ($n, $r) => ($reasonLabels[$r] ?? $r)." ×{$n}")->join(', ') }}
                                · last {{ $row['latest']?->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <a href="{{ route('movies.show', $row['movie']) }}" target="_blank" rel="noopener" class="inline-flex h-10 items-center rounded-lg border border-ink-700 px-3 text-sm hover:border-neutral-500">Test</a>
                        <a href="{{ route('admin.movies.edit', $row['movie']) }}" class="inline-flex h-10 items-center rounded-lg border border-ink-700 px-3 text-sm hover:border-neutral-500">Edit</a>
                        <form method="post" action="{{ route('admin.reports.resolve', $row['movie']) }}">
                            @csrf
                            <button type="submit" class="h-10 rounded-lg bg-accent px-4 text-sm font-medium text-white hover:bg-accent-hover">Mark fixed</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
