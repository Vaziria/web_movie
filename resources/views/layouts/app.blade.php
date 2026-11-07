<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<header class="topbar">
    <div class="wrap topbar-inner">
        <a class="brand" href="{{ route('movies.index') }}">{{ config('app.name') }}</a>
        <form class="search" method="get" action="{{ route('movies.index') }}">
            <input type="search" name="q" value="{{ $q ?? '' }}" placeholder="Search movies..." maxlength="100" aria-label="Search">
            @isset($sort)<input type="hidden" name="sort" value="{{ $sort }}">@endisset
            <button type="submit">Search</button>
        </form>
    </div>
</header>
<main class="wrap">
    @if (session('status'))
        <p class="flash">{{ session('status') }}</p>
    @endif
    @yield('content')
</main>
<footer class="wrap footer">&copy; {{ date('Y') }} {{ config('app.name') }}</footer>
</body>
</html>
