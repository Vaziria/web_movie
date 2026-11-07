{{--
    One movie card (thumbnail, title, meta line).
    $movie, $tag ('h2'|'h3'), $eager (bool), $rank (int|null: "#1" badge), $meta ('full'|'short'|'week')
--}}
@php
    $tag ??= 'h2';
    $eager ??= false;
    $rank ??= null;
    $meta ??= 'full';
@endphp
<a href="{{ lroute('movies.show', $movie) }}" class="group block">
    <div class="relative aspect-video overflow-hidden rounded-lg bg-ink-800">
        @if ($src = $movie->thumbnailSrc())
            <img src="{{ $src }}" alt="{{ $movie->title }}" width="480" height="270"
                 loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" referrerpolicy="no-referrer"
                 class="size-full object-cover transition-opacity group-hover:opacity-80">
        @else
            <div class="flex size-full items-center justify-center p-3 text-center text-xs text-neutral-500">{{ \Illuminate\Support\Str::limit($movie->title, 40) }}</div>
        @endif
        @if ($rank)
            <span class="absolute left-1.5 top-1.5 rounded bg-ink-950/85 px-1.5 text-xs font-semibold leading-5 text-white">#{{ $rank }}</span>
        @endif
    </div>
    <{{ $tag }} class="mt-2 line-clamp-2 text-sm font-medium leading-snug text-neutral-100 group-hover:text-white">{{ $movie->title }}</{{ $tag }}>
    <p class="mt-0.5 line-clamp-1 text-xs text-neutral-500">
        @if ($meta === 'week')
            {{ trans_choice(':count view this week|:count views this week', $movie->week_views, ['count' => nf($movie->week_views)]) }}
        @else
            @if ($movie->release_year){{ $movie->release_year }} · @endif
            @if ($d = $movie->durationLabel()){{ $d }} · @endif
            ★ {{ nf($movie->rating_avg, 1) }}
            @if ($meta === 'full') · {{ trans_choice(':count view|:count views', $movie->views, ['count' => nf($movie->views)]) }}@endif
        @endif
    </p>
</a>
