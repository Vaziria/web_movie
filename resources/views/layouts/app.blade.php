@php
    $locale = app()->getLocale();
    $locales = site_locales();
    $seo = ($seo ?? []) + [
        'title' => config('app.name'),
        'description' => __('Watch movies online on :site. Browse the newest, top rated and most viewed movies by category.', ['site' => config('app.name')]),
        'canonical' => url()->current(),
        'alternates' => [],
        'robots' => 'index, follow',
        'type' => 'website',
        'image' => null,
    ];
    $ogImage = $seo['image'] ?: asset('og-default.png');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" class="bg-ink-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seo['title'] }}</title>
    <meta name="description" content="{{ $seo['description'] }}">
    <meta name="robots" content="{{ $seo['robots'] }}">
    <link rel="canonical" href="{{ $seo['canonical'] }}">
    @foreach ($seo['alternates'] as $hreflang => $href)
        <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}">
    @endforeach
    <meta name="theme-color" content="#0a0a0a">

    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:type" content="{{ $seo['type'] }}">
    <meta property="og:title" content="{{ $seo['title'] }}">
    <meta property="og:description" content="{{ $seo['description'] }}">
    <meta property="og:url" content="{{ $seo['canonical'] }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:locale" content="{{ $locales[$locale]['og'] }}">
    @foreach ($locales as $code => $info)
        @if ($code !== $locale)<meta property="og:locale:alternate" content="{{ $info['og'] }}">@endif
    @endforeach
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seo['title'] }}">
    <meta name="twitter:description" content="{{ $seo['description'] }}">
    <meta name="twitter:image" content="{{ $ogImage }}">

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://vidara.to">

    @foreach (['jsonld', 'jsonld_site'] as $k)
        @if (! empty($seo[$k]))
            <script type="application/ld+json">{!! json_encode($seo[$k], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
        @endif
    @endforeach

    @vite('resources/css/app.css')
</head>
<body class="min-h-dvh bg-ink-950 font-sans text-neutral-200 antialiased">
<header class="sticky top-0 z-20 border-b border-ink-800 bg-ink-950/95 backdrop-blur">
    <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-3 md:flex-row md:items-center md:justify-between">
        <div class="flex items-center justify-between gap-3">
            <a href="{{ lroute('movies.index') }}" class="flex items-center gap-2" aria-label="{{ __(':site home', ['site' => config('app.name')]) }}">
                <img src="{{ asset('favicon.svg') }}" alt="" width="28" height="28" class="size-7">
                <span class="text-lg font-semibold tracking-tight text-white">{{ config('app.name') }}</span>
            </a>
            <nav aria-label="{{ __('Language') }}" class="flex items-center gap-1 text-xs md:hidden">
                @include('partials.language-switch')
            </nav>
        </div>
        <div class="flex w-full items-center gap-3 md:w-auto">
            @php
                // List pages pass $searchForm (stay inside the category/tags, keep the sort); other pages search all.
                $searchForm ??= ['action' => lroute('movies.index'), 'hidden' => [], 'placeholder' => __('Search movies')];
            @endphp
            <form method="get" action="{{ $searchForm['action'] }}" role="search" class="flex w-full gap-2 md:w-96">
                @foreach ($searchForm['hidden'] as $field => $value)
                    <input type="hidden" name="{{ $field }}" value="{{ $value }}">
                @endforeach
                <label for="q" class="sr-only">{{ $searchForm['placeholder'] }}</label>
                <input id="q" type="search" name="q" value="{{ $q ?? '' }}" placeholder="{{ $searchForm['placeholder'] }}" maxlength="100"
                       class="h-11 min-w-0 flex-1 rounded-lg border border-ink-700 bg-ink-900 px-3 text-base text-white placeholder:text-neutral-500 focus:border-accent focus:outline-none">
                <button type="submit" class="h-11 rounded-lg bg-accent px-4 text-sm font-medium text-white hover:bg-accent-hover">{{ __('Search') }}</button>
            </form>
            <nav aria-label="{{ __('Language') }}" class="hidden items-center gap-1 text-xs md:flex">
                @include('partials.language-switch')
            </nav>
        </div>
    </div>
</header>

<main class="mx-auto max-w-6xl px-4 pt-4 pb-12 md:pt-6">
    @if (session('status'))
        <p role="status" class="mb-4 rounded-lg border border-emerald-900 bg-emerald-950 px-3 py-2 text-sm text-emerald-300">{{ session('status') }}</p>
    @endif
    @yield('content')
</main>

<footer class="border-t border-ink-800">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-6 text-xs text-neutral-500">
        <span>&copy; {{ date('Y') }} {{ config('app.name') }}</span>
        <span class="flex gap-3">
            @foreach ($locales as $code => $info)
                <a href="{{ current_url_in($code) }}" hreflang="{{ $code }}" lang="{{ $code }}"
                   @class(['hover:text-neutral-300', 'text-neutral-300' => $code === $locale])>{{ $info['name'] }}</a>
            @endforeach
        </span>
    </div>
</footer>
</body>
</html>
