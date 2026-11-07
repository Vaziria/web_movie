@extends('layouts.app')

@section('content')
    @php
        $listUrl = fn (array $params) => ($category ? lroute('movies.category', $category) : lroute('movies.index'))
            .(($qs = http_build_query(array_filter($params))) ? '?'.$qs : '');
    @endphp

    <h1 class="mb-4 text-xl font-semibold text-white md:text-2xl">{{ $heading }}</h1>
    @if ($category?->description)
        <p class="-mt-2 mb-4 max-w-3xl text-sm leading-relaxed text-neutral-400">{{ $category->description }}</p>
    @endif

    @if ($categories->isNotEmpty())
        <nav aria-label="{{ __('Categories') }}" class="-mx-4 mb-3 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] md:mx-0 md:flex-wrap md:px-0">
            <a href="{{ lroute('movies.index') }}"
               @class(['shrink-0 rounded-full border px-3.5 py-2 text-sm', 'border-white bg-white text-ink-950' => ! $category, 'border-ink-700 text-neutral-300 hover:border-neutral-500' => $category])>{{ __('All') }}</a>
            @foreach ($categories->sortByDesc(fn ($c) => $category?->is($c)) as $c)
                <a href="{{ lroute('movies.category', $c) }}"
                   @class(['shrink-0 rounded-full border px-3.5 py-2 text-sm', 'border-white bg-white text-ink-950' => $category?->is($c), 'border-ink-700 text-neutral-300 hover:border-neutral-500' => ! $category?->is($c)])>{{ $c->name }}</a>
            @endforeach
        </nav>
    @endif

    <nav aria-label="{{ __('Sort') }}" class="mb-5 flex gap-5 border-b border-ink-800 text-sm">
        @foreach (\App\Http\Controllers\MovieController::SORT_LABELS as $key => $label)
            <a href="{{ $listUrl(['q' => $q, 'sort' => $key === 'created' ? null : $key]) }}" rel="nofollow"
               @class(['-mb-px border-b-2 py-2.5', 'border-accent font-medium text-white' => $sort === $key, 'border-transparent text-neutral-400 hover:text-neutral-200' => $sort !== $key])>{{ __($label) }}</a>
        @endforeach
    </nav>

    @if ($q !== '')
        <p class="mb-4 text-sm text-neutral-400">{{ trans_choice(':count result for “:q”|:count results for “:q”', $movies->total(), ['count' => nf($movies->total()), 'q' => $q]) }}</p>
    @endif

    @if ($movies->isEmpty())
        <p class="py-16 text-center text-neutral-500">{{ __('No movies found.') }}</p>
    @else
        <ul class="grid grid-cols-2 gap-x-3 gap-y-5 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($movies as $movie)
                <li>
                    <a href="{{ lroute('movies.show', $movie) }}" class="group block">
                        <div class="aspect-video overflow-hidden rounded-lg bg-ink-800">
                            @if ($src = $movie->thumbnailSrc())
                                <img src="{{ $src }}" alt="{{ $movie->title }}" width="480" height="270"
                                     loading="{{ $loop->index < 4 ? 'eager' : 'lazy' }}" decoding="async" referrerpolicy="no-referrer"
                                     class="size-full object-cover transition-opacity group-hover:opacity-80">
                            @else
                                <div class="flex size-full items-center justify-center p-3 text-center text-xs text-neutral-500">{{ \Illuminate\Support\Str::limit($movie->title, 40) }}</div>
                            @endif
                        </div>
                        <h2 class="mt-2 line-clamp-2 text-sm font-medium leading-snug text-neutral-100 group-hover:text-white">{{ $movie->title }}</h2>
                        <p class="mt-0.5 text-xs text-neutral-500">
                            @if ($movie->release_year){{ $movie->release_year }} · @endif
                            ★ {{ nf($movie->rating_avg, 1) }} · {{ trans_choice(':count view|:count views', $movie->views, ['count' => nf($movie->views)]) }}
                        </p>
                    </a>
                </li>
            @endforeach
        </ul>
        {{ $movies->links('pagination.simple') }}
    @endif
@endsection
