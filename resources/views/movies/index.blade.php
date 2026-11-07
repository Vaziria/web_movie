@extends('layouts.app')

@section('content')
    @php
        $sortLabels = App\Http\Controllers\MovieController::SORT_LABELS;
        $sorts = App\Models\Movie::SORTS;
        $listUrl = fn (array $params) => ($category ? lroute('movies.category', $category) : lroute('movies.index'))
            .(($qs = http_build_query(array_filter($params))) ? '?'.$qs : '');
        // Switching category keeps the current search and sort.
        $keep = array_filter(['q' => $q, 'sort' => $sort === 'created' ? null : $sort, 'dir' => $dir === $defaultDir ? null : $dir]);
        $chipUrl = fn ($c) => ($c ? lroute('movies.category', $c) : lroute('movies.index')).($keep ? '?'.http_build_query($keep) : '');
    @endphp

    @if ($sections)
        {{-- Home with rows: a hidden page title, the rows, then "Latest movies" as a section. --}}
        <h1 class="sr-only">{{ __('Watch movies online on :site', ['site' => config('app.name')]) }}</h1>
    @else
        <h1 class="mb-4 text-xl font-semibold text-white md:text-2xl">{{ $heading }}</h1>
    @endif
    @if ($category?->description)
        <p class="-mt-2 mb-4 max-w-3xl text-sm leading-relaxed text-neutral-400">{{ $category->description }}</p>
    @endif

    @if ($categories->isNotEmpty())
        <nav aria-label="{{ __('Categories') }}" class="-mx-4 mb-3 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] md:mx-0 md:flex-wrap md:px-0">
            <a href="{{ $chipUrl(null) }}" @if ($keep) rel="nofollow" @endif @if (! $category) aria-current="true" @endif
               @class(['shrink-0 rounded-full border px-3.5 py-2 text-sm', 'border-white bg-white text-ink-950' => ! $category, 'border-ink-700 text-neutral-300 hover:border-neutral-500' => $category])>{{ __('All') }}</a>
            @foreach ($categories->sortByDesc(fn ($c) => $category?->is($c)) as $c)
                <a href="{{ $chipUrl($c) }}" @if ($keep) rel="nofollow" @endif @if ($category?->is($c)) aria-current="true" @endif
                   @class(['shrink-0 rounded-full border px-3.5 py-2 text-sm', 'border-white bg-white text-ink-950' => $category?->is($c), 'border-ink-700 text-neutral-300 hover:border-neutral-500' => ! $category?->is($c)])>{{ $c->name }}</a>
            @endforeach
        </nav>
    @endif

    @foreach ($sections as $key => $section)
        @include('partials.movie-row', $section + ['key' => $key, 'first' => $loop->first])
    @endforeach

    @if ($sections)
        <h2 class="mb-2 text-lg font-semibold text-white">{{ $heading }}</h2>
    @endif

    {{-- Sort tabs scroll sideways on phones. Tapping the active tab flips the order (▼ high→low, ▲ low→high). --}}
    <nav aria-label="{{ __('Sort') }}" class="-mx-4 mb-5 flex gap-4 overflow-x-auto border-b border-ink-800 px-4 text-sm [scrollbar-width:none] md:mx-0 md:px-0">
        @foreach ($sortLabels as $key => $label)
            @php
                $active = $sort === $key;
                $keyDefault = $sorts[$key][1];
                $nextDir = $active ? ($dir === 'desc' ? 'asc' : 'desc') : $keyDefault;
                $tabUrl = $listUrl(['q' => $q, 'sort' => $key === 'created' ? null : $key, 'dir' => $nextDir === $keyDefault ? null : $nextDir]);
            @endphp
            <a href="{{ $tabUrl }}" rel="nofollow"
               @if ($active) aria-current="true" title="{{ $dir === 'desc' ? __('High to low, tap to reverse') : __('Low to high, tap to reverse') }}" @endif
               @class(['-mb-px inline-flex shrink-0 items-center gap-1 whitespace-nowrap border-b-2 py-2.5', 'border-accent font-medium text-white' => $active, 'border-transparent text-neutral-400 hover:text-neutral-200' => ! $active])>
                {{ __($label) }}@if ($active)<span aria-hidden="true" class="text-xs">{{ $dir === 'desc' ? '▼' : '▲' }}</span><span class="sr-only">({{ $dir === 'desc' ? __('high to low') : __('low to high') }})</span>@endif
            </a>
        @endforeach
    </nav>

    @if ($q !== '')
        <p class="mb-4 text-sm text-neutral-400">{{ trans_choice(':count result for “:q”|:count results for “:q”', $movies->total(), ['count' => nf($movies->total()), 'q' => $q]) }}</p>
    @endif

    @if ($movies->isEmpty())
        <div class="py-16 text-center text-neutral-500">
            <p>{{ __('No movies found.') }}</p>
            @if ($category && $q !== '')
                <a href="{{ $chipUrl(null) }}" rel="nofollow" class="mt-3 inline-flex h-11 items-center rounded-lg border border-ink-700 px-4 text-sm text-neutral-200 hover:border-neutral-500">{{ __('Search “:q” in all categories', ['q' => $q]) }}</a>
            @endif
        </div>
    @else
        <ul class="grid grid-cols-2 gap-x-3 gap-y-5 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($movies as $movie)
                <li>
                    @include('partials.movie-card', ['movie' => $movie, 'tag' => $sections ? 'h3' : 'h2', 'eager' => ! $sections && $loop->index < 4])
                </li>
            @endforeach
        </ul>
        {{ $movies->links('pagination.simple') }}
    @endif
@endsection
