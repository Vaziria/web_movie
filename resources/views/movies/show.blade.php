@extends('layouts.app')

@section('content')
    <article>
        {{-- External player. Sandbox (blocks pop-up ads) is opt-in via EMBED_SANDBOX: many hosts refuse to play when sandboxed. --}}
        <div class="-mx-4 -mt-4 aspect-video bg-black md:mx-0 md:mt-0 md:overflow-hidden md:rounded-lg">
            <iframe src="{{ $movie->embed_url }}"
                    title="{{ $movie->title }}"
                    @if (config('movies.embed_sandbox')) sandbox="allow-scripts allow-same-origin allow-presentation" @endif
                    allow="fullscreen; encrypted-media; picture-in-picture"
                    allowfullscreen
                    referrerpolicy="no-referrer"
                    class="size-full border-0"></iframe>
        </div>

        <nav aria-label="Breadcrumb" class="mt-4 text-xs text-neutral-500">
            <a href="{{ route('movies.index') }}" class="hover:text-neutral-300">Home</a>
            @if ($first = $movie->categories->first())
                <span aria-hidden="true">/</span>
                <a href="{{ route('movies.category', $first) }}" class="hover:text-neutral-300">{{ $first->name }}</a>
            @endif
        </nav>

        <h1 class="mt-1 text-xl font-semibold leading-tight text-white md:text-3xl">{{ $movie->title }}</h1>

        <p class="mt-2 text-sm text-neutral-400">
            @if ($movie->release_year)<span>{{ $movie->release_year }}</span> · @endif
            @if ($movie->duration_minutes)<span>{{ $movie->duration_minutes }} min</span> · @endif
            <span>★ {{ number_format($movie->rating_avg, 1) }}/{{ \App\Models\Movie::MAX_SCORE }}
                ({{ $movie->rating_count }} {{ \Illuminate\Support\Str::plural('rating', $movie->rating_count) }})</span>
            · <span>{{ number_format($movie->views) }} views</span>
        </p>

        @if ($movie->categories->isNotEmpty())
            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach ($movie->categories as $c)
                    <li><a href="{{ route('movies.category', $c) }}" class="block rounded-full border border-ink-700 px-3 py-1.5 text-xs text-neutral-300 hover:border-neutral-500">{{ $c->name }}</a></li>
                @endforeach
            </ul>
        @endif

        <form method="post" action="{{ route('movies.rate', $movie) }}" class="mt-5 border-y border-ink-800 py-3">
            @csrf
            <p class="text-sm text-neutral-400">{{ $myScore ? 'Your rating' : 'Rate this movie' }}</p>
            {{-- Reversed DOM order so hovering a star lights it and every lower star (CSS peer, no JS). --}}
            <div class="mt-1 flex flex-row-reverse justify-end">
                @for ($i = \App\Models\Movie::MAX_SCORE; $i >= \App\Models\Movie::MIN_SCORE; $i--)
                    <button type="submit" name="score" value="{{ $i }}" aria-label="Rate {{ $i }} out of {{ \App\Models\Movie::MAX_SCORE }}"
                            @class(['peer size-11 text-3xl leading-none hover:text-accent peer-hover:text-accent', 'text-accent' => $myScore && $i <= $myScore, 'text-ink-700' => ! ($myScore && $i <= $myScore)])>★</button>
                @endfor
            </div>
            @error('score')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
        </form>

        @if ($movie->synopsis)
            <section class="mt-5 max-w-3xl">
                <h2 class="mb-2 text-sm font-medium uppercase tracking-wide text-neutral-500">Synopsis</h2>
                <p class="leading-relaxed text-neutral-300">{!! nl2br(e($movie->synopsis)) !!}</p>
            </section>
        @endif
    </article>
@endsection
