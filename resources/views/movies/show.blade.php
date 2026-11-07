@extends('layouts.app')

@section('title', $movie->title.' - '.config('app.name'))

@section('content')
    <article class="movie">
        <div class="player">
            {{-- External player. Sandbox (blocks pop-up ads) is opt-in via EMBED_SANDBOX: many hosts refuse to play when sandboxed. --}}
            <iframe src="{{ $movie->embed_url }}"
                    title="{{ $movie->title }}"
                    @if (config('movies.embed_sandbox')) sandbox="allow-scripts allow-same-origin allow-presentation" @endif
                    allow="fullscreen; encrypted-media; picture-in-picture"
                    allowfullscreen
                    referrerpolicy="no-referrer"
                    loading="lazy"></iframe>
        </div>

        <h1>{{ $movie->title }}</h1>
        <p class="meta">
            @if ($movie->release_year){{ $movie->release_year }} &middot; @endif
            @if ($movie->duration_minutes){{ $movie->duration_minutes }} min &middot; @endif
            &#9733; {{ number_format($movie->rating_avg, 1) }} / {{ \App\Models\Movie::MAX_SCORE }}
            ({{ $movie->rating_count }} {{ \Illuminate\Support\Str::plural('rating', $movie->rating_count) }})
            &middot; {{ number_format($movie->views) }} views
        </p>

        <form class="rate" method="post" action="{{ route('movies.rate', $movie) }}">
            @csrf
            <span>{{ $myScore ? 'Your rating:' : 'Rate this movie:' }}</span>
            @for ($i = \App\Models\Movie::MIN_SCORE; $i <= \App\Models\Movie::MAX_SCORE; $i++)
                <button type="submit" name="score" value="{{ $i }}"
                        class="star {{ $myScore && $i <= $myScore ? 'on' : '' }}"
                        title="{{ $i }} / {{ \App\Models\Movie::MAX_SCORE }}">&#9733;</button>
            @endfor
            @error('score')<span class="error">{{ $message }}</span>@enderror
        </form>

        @if ($movie->synopsis)
            <p class="synopsis">{!! nl2br(e($movie->synopsis)) !!}</p>
        @endif
    </article>
@endsection
