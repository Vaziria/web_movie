@extends('layouts.app')

@php $max = \App\Models\Movie::MAX_SCORE; @endphp

@section('content')
    @if ($preview)
        <p role="status" class="-mt-1 mb-4 rounded-lg border border-amber-800 bg-amber-950 px-3 py-2 text-sm text-amber-200">
            Admin preview: this movie is a <strong>{{ $movie->status() }}</strong>@if ($movie->published_at) (goes live {{ $movie->publishedAtLabel() }})@endif.
            Visitors get a 404 until it is published.
            <a href="{{ route('admin.movies.edit', $movie) }}" class="underline">Edit</a>
        </p>
    @endif
    <article>
        {{-- External player. Sandbox (blocks pop-up ads) is opt-in via EMBED_SANDBOX: many hosts refuse to play when sandboxed. --}}
        <div class="-mx-4 -mt-4 aspect-video bg-black md:mx-0 md:mt-0 md:overflow-hidden md:rounded-lg">
            <iframe src="{{ $players[$server] }}"
                    title="{{ $movie->title }}"
                    @if (config('movies.embed_sandbox')) sandbox="allow-scripts allow-same-origin allow-presentation" @endif
                    allow="fullscreen; encrypted-media; picture-in-picture"
                    allowfullscreen
                    referrerpolicy="no-referrer"
                    class="size-full border-0"></iframe>
        </div>

        {{-- Server switch + broken-video report, right under the player. Links reload the page (no JS). --}}
        <div class="mt-3 flex flex-wrap items-center gap-2">
            @if (count($players) > 1)
                <span class="mr-1 text-xs text-neutral-500">{{ __('Server') }}</span>
                @foreach ($players as $n => $src)
                    <a href="{{ lroute('movies.show', $movie) }}{{ $n > 1 ? '?server='.$n : '' }}" rel="nofollow"
                       @if ($n === $server) aria-current="true" @endif
                       @class(['inline-flex h-9 min-w-11 items-center justify-center rounded-lg border px-3 text-sm', 'border-accent bg-accent text-white' => $n === $server, 'border-ink-700 text-neutral-300 hover:border-neutral-500' => $n !== $server])>{{ $n }}</a>
                @endforeach
            @endif

            <details class="group ml-auto">
                <summary class="inline-flex h-9 cursor-pointer list-none items-center rounded-lg px-2 text-xs text-neutral-400 hover:text-white [&::-webkit-details-marker]:hidden">
                    ⚠ {{ __('Video not working?') }}
                </summary>
                <form method="post" action="{{ lroute('movies.report', $movie) }}" class="mt-2 flex flex-wrap items-center gap-2 rounded-lg border border-ink-800 bg-ink-900 p-3">
                    @csrf
                    <input type="hidden" name="server" value="{{ $server }}">
                    <label for="reason" class="sr-only">{{ __('Problem') }}</label>
                    <select id="reason" name="reason" class="h-10 rounded-lg border border-ink-700 bg-ink-950 px-2 text-sm text-neutral-200">
                        <option value="not_loading">{{ __('Video does not load') }}</option>
                        <option value="wrong_video">{{ __('Wrong video') }}</option>
                        <option value="bad_quality">{{ __('Bad quality or no sound') }}</option>
                        <option value="other">{{ __('Other problem') }}</option>
                    </select>
                    <button type="submit" class="h-10 rounded-lg border border-ink-700 px-4 text-sm text-neutral-200 hover:border-neutral-500">
                        {{ count($players) > 1 ? __('Report server :n', ['n' => $server]) : __('Report') }}
                    </button>
                </form>
            </details>
        </div>

        <nav aria-label="{{ __('Breadcrumb') }}" class="mt-3 text-xs text-neutral-500">
            <a href="{{ lroute('movies.index') }}" class="hover:text-neutral-300">{{ __('Home') }}</a>
            @if ($first = $movie->categories->first())
                <span aria-hidden="true">/</span>
                <a href="{{ lroute('movies.category', $first) }}" class="hover:text-neutral-300">{{ $first->name }}</a>
            @endif
        </nav>

        <h1 class="mt-1 text-xl font-semibold leading-tight text-white md:text-3xl">{{ $movie->title }}</h1>

        <p class="mt-2 text-sm text-neutral-400">
            @if ($movie->release_year)<span>{{ $movie->release_year }}</span> · @endif
            @if ($d = $movie->durationLabel(true))<span>{{ $d }}</span> · @endif
            <span>★ {{ nf($movie->rating_avg, 1) }}/{{ $max }}
                ({{ trans_choice(':count rating|:count ratings', $movie->rating_count, ['count' => nf($movie->rating_count)]) }})</span>
            · <span>{{ trans_choice(':count view|:count views', $movie->views, ['count' => nf($movie->views)]) }}</span>
        </p>

        @if ($movie->categories->isNotEmpty())
            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach ($movie->categories as $c)
                    <li><a href="{{ lroute('movies.category', $c) }}" class="block rounded-full border border-ink-700 px-3 py-1.5 text-xs text-neutral-300 hover:border-neutral-500">{{ $c->name }}</a></li>
                @endforeach
            </ul>
        @endif

        @if ($movie->tags->isNotEmpty())
            <ul class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-sm" aria-label="{{ __('Tags') }}">
                @foreach ($movie->tags as $t)
                    <li><a href="{{ lroute('movies.tag', $t) }}" class="inline-block py-1 text-neutral-400 hover:text-white">#{{ $t->name }}</a></li>
                @endforeach
            </ul>
        @endif

        <form method="post" action="{{ lroute('movies.rate', $movie) }}" class="mt-5 border-y border-ink-800 py-3">
            @csrf
            <p class="text-sm text-neutral-400">{{ $myScore ? __('Your rating') : __('Rate this movie') }}</p>
            {{-- Reversed DOM order so hovering a star lights it and every lower star (CSS peer, no JS). --}}
            <div class="mt-1 flex flex-row-reverse justify-end">
                @for ($i = $max; $i >= \App\Models\Movie::MIN_SCORE; $i--)
                    <button type="submit" name="score" value="{{ $i }}" aria-label="{{ __('Rate :n out of :max', ['n' => $i, 'max' => $max]) }}"
                            @class(['peer size-11 text-3xl leading-none hover:text-accent peer-hover:text-accent', 'text-accent' => $myScore && $i <= $myScore, 'text-ink-700' => ! ($myScore && $i <= $myScore)])>★</button>
                @endfor
            </div>
            @error('score')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
        </form>

        @if ($movie->synopsis)
            <section class="mt-5 max-w-3xl">
                <h2 class="mb-2 text-sm font-medium uppercase tracking-wide text-neutral-500">{{ __('Synopsis') }}</h2>
                <p class="leading-relaxed text-neutral-300">{!! nl2br(e($movie->synopsis)) !!}</p>
            </section>
        @endif
    </article>

    @if ($related->isNotEmpty())
        <section class="mt-10" aria-labelledby="related-heading">
            <h2 id="related-heading" class="mb-4 text-lg font-semibold text-white">{{ __('More like this') }}</h2>
            <ul class="grid grid-cols-2 gap-x-3 gap-y-5 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($related as $r)
                    <li>@include('partials.movie-card', ['movie' => $r, 'tag' => 'h3', 'meta' => 'short'])</li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
