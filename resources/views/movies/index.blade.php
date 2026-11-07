@extends('layouts.app')

@section('title', ($q !== '' ? "Search: $q - " : '').config('app.name'))

@section('content')
    <nav class="sorts" aria-label="Sort">
        @foreach (['created' => 'Newest', 'rating' => 'Top rated', 'views' => 'Most viewed'] as $key => $label)
            <a href="{{ route('movies.index', array_filter(['q' => $q, 'sort' => $key])) }}"
               class="{{ $sort === $key ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </nav>

    @if ($q !== '')
        <p class="muted">{{ $movies->total() }} result(s) for "{{ $q }}"</p>
    @endif

    @if ($movies->isEmpty())
        <p class="empty">No movies found.</p>
    @else
        <div class="grid">
            @foreach ($movies as $movie)
                <a class="card" href="{{ route('movies.show', $movie) }}">
                    <div class="poster">
                        @if ($movie->poster_url)
                            <img src="{{ $movie->poster_url }}" alt="{{ $movie->title }}" loading="lazy" referrerpolicy="no-referrer">
                        @else
                            <span>{{ \Illuminate\Support\Str::limit($movie->title, 40) }}</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <h2>{{ $movie->title }}</h2>
                        <p class="meta">
                            @if ($movie->release_year){{ $movie->release_year }} &middot; @endif
                            &#9733; {{ number_format($movie->rating_avg, 1) }} ({{ $movie->rating_count }})
                            &middot; {{ number_format($movie->views) }} views
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
        {{ $movies->links('pagination.simple') }}
    @endif
@endsection
