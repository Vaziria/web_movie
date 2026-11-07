@extends('admin.layout')

@section('title', 'Movies')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-white">Movies <span class="text-sm font-normal text-neutral-500">({{ $movies->total() }})</span></h1>
        <a href="{{ route('admin.movies.create') }}" class="inline-flex h-10 items-center rounded-lg bg-accent px-4 text-sm font-medium text-white hover:bg-accent-hover">+ New movie</a>
    </div>

    <form method="get" class="mb-4 flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ $q }}" placeholder="Search title or synopsis" aria-label="Search"
               class="h-10 min-w-0 flex-1 basis-48 rounded-lg border border-ink-700 bg-ink-900 px-3 text-base text-white focus:border-accent focus:outline-none">
        <select name="category" aria-label="Category" class="h-10 min-w-0 flex-1 basis-36 rounded-lg border border-ink-700 bg-ink-900 px-2 text-sm text-neutral-200 sm:flex-none">
            <option value="">All categories</option>
            @foreach ($categories as $c)
                <option value="{{ $c->slug }}" @selected($category === $c->slug)>{{ $c->name }} ({{ $c->movies_count }})</option>
            @endforeach
            <option value="none" @selected($category === 'none')>No category</option>
        </select>
        <select name="status" aria-label="Status" class="h-10 min-w-0 flex-1 basis-36 rounded-lg border border-ink-700 bg-ink-900 px-2 text-sm text-neutral-200 sm:flex-none">
            <option value="">All statuses</option>
            <option value="published" @selected($status === 'published')>Published</option>
            <option value="scheduled" @selected($status === 'scheduled')>Scheduled ({{ $statusCounts['scheduled'] }})</option>
            <option value="draft" @selected($status === 'draft')>Drafts ({{ $statusCounts['draft'] }})</option>
        </select>
        <button class="h-10 rounded-lg border border-ink-700 px-4 text-sm hover:border-neutral-500">Filter</button>
        @if ($q !== '' || $category !== '' || $status !== '')
            <a href="{{ route('admin.movies.index') }}" class="inline-flex h-10 items-center px-2 text-sm text-neutral-400 hover:text-white">Clear</a>
        @endif
    </form>

    @if ($movies->isEmpty())
        @if ($q !== '' || $category !== '' || $status !== '')
            <p class="py-12 text-center text-neutral-500">No movies match this filter. <a href="{{ route('admin.movies.index') }}" class="text-accent">Show all</a>.</p>
        @else
            <p class="py-12 text-center text-neutral-500">No movies yet. <a href="{{ route('admin.movies.create') }}" class="text-accent">Add the first one</a>.</p>
        @endif
    @else
        <ul class="divide-y divide-ink-800 border-y border-ink-800">
            @foreach ($movies as $movie)
                <li class="flex items-center gap-3 py-3">
                    <a href="{{ route('admin.movies.edit', $movie) }}" class="aspect-video w-24 shrink-0 overflow-hidden rounded bg-ink-800 sm:w-32">
                        @if ($src = $movie->thumbnailSrc())
                            <img src="{{ $src }}" alt="" class="size-full object-cover" loading="lazy" referrerpolicy="no-referrer">
                        @endif
                    </a>
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('admin.movies.edit', $movie) }}" class="line-clamp-1 font-medium text-white hover:underline">{{ $movie->title }}</a>
                        @php $st = $movie->status(); @endphp
                        @if ($st !== 'published')
                            <p @class(['text-xs font-medium', 'text-neutral-300' => $st === 'draft', 'text-amber-300' => $st === 'scheduled'])>
                                {{ $st === 'draft' ? '● Draft' : '◷ Scheduled · '.$movie->publishedAtLabel() }}
                            </p>
                        @endif
                        @if ($movie->open_reports_count > 0)
                            <a href="{{ route('admin.reports.index') }}" class="text-xs font-medium text-red-400 hover:underline">⚠ {{ $movie->open_reports_count }} broken-video {{ \Illuminate\Support\Str::plural('report', $movie->open_reports_count) }}</a>
                        @endif
                        <p class="line-clamp-1 text-xs text-neutral-500">
                            {{ $movie->categories->pluck('name')->join(', ') ?: 'No category' }}
                            · ★ {{ number_format($movie->rating_avg, 1) }} ({{ $movie->rating_count }})
                            · {{ number_format($movie->views) }} views
                            · {{ $movie->created_at?->format('Y-m-d') }}
                        </p>
                    </div>
                    <a href="{{ route('movies.show', $movie) }}" target="_blank" rel="noopener" class="hidden shrink-0 text-xs text-neutral-400 hover:text-white sm:inline">{{ $movie->isPublished() ? 'View' : 'Preview' }}</a>
                </li>
            @endforeach
        </ul>
        {{ $movies->links('pagination.simple') }}
    @endif
@endsection
