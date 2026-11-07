{{-- A titled row of movie cards that scrolls sideways on phones. $key, $title, $url, $movies, $ranked, $first --}}
<section class="mb-8" aria-labelledby="row-{{ $key }}">
    <div class="mb-3 flex items-baseline justify-between gap-3">
        <h2 id="row-{{ $key }}" class="text-lg font-semibold text-white">{{ $title }}</h2>
        <a href="{{ $url }}" rel="nofollow" class="inline-flex h-11 shrink-0 items-center text-sm text-neutral-400 hover:text-white">{{ __('See all') }} &rarr;</a>
    </div>
    <ul class="-mx-4 flex snap-x scroll-px-4 gap-3 overflow-x-auto px-4 pb-1 [scrollbar-width:none] md:mx-0 md:scroll-px-0 md:px-0">
        @foreach ($movies as $m)
            <li class="w-40 shrink-0 snap-start sm:w-52">
                @include('partials.movie-card', [
                    'movie' => $m, 'tag' => 'h3', 'eager' => $first && $loop->index < 3,
                    'rank' => $ranked ? $loop->iteration : null, 'meta' => $ranked ? 'week' : 'short',
                ])
            </li>
        @endforeach
    </ul>
</section>
