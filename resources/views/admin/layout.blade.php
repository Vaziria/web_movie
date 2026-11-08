<!DOCTYPE html>
<html lang="en" class="bg-ink-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Admin') · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite('resources/css/app.css')
</head>
<body class="min-h-dvh bg-ink-950 font-sans text-neutral-200 antialiased">
@auth
    <header class="border-b border-ink-800 bg-ink-900">
        <div class="mx-auto flex max-w-5xl items-center gap-4 overflow-x-auto px-4 text-sm [scrollbar-width:none]">
            <a href="{{ route('admin.movies.index') }}" class="flex shrink-0 items-center gap-2 py-3">
                <img src="{{ asset('favicon.svg') }}" alt="" width="24" height="24" class="size-6">
                <span class="font-semibold text-white">Admin</span>
            </a>
            <a href="{{ route('admin.movies.index') }}" @class(['shrink-0 py-3', 'text-white' => request()->routeIs('admin.movies.*'), 'text-neutral-400 hover:text-white' => ! request()->routeIs('admin.movies.*')])>Movies</a>
            <a href="{{ route('admin.categories.index') }}" @class(['shrink-0 py-3', 'text-white' => request()->routeIs('admin.categories.*'), 'text-neutral-400 hover:text-white' => ! request()->routeIs('admin.categories.*')])>Categories</a>
            <a href="{{ route('admin.tags.index') }}" @class(['shrink-0 py-3', 'text-white' => request()->routeIs('admin.tags.*'), 'text-neutral-400 hover:text-white' => ! request()->routeIs('admin.tags.*')])>Tags</a>
            @php $openReports = \App\Models\PlayerReport::openMovieCount(); @endphp
            <a href="{{ route('admin.reports.index') }}" @class(['inline-flex shrink-0 items-center gap-1.5 py-3', 'text-white' => request()->routeIs('admin.reports.*'), 'text-neutral-400 hover:text-white' => ! request()->routeIs('admin.reports.*')])>
                Reports
                @if ($openReports > 0)
                    <span class="rounded-full bg-accent px-1.5 text-xs font-medium leading-5 text-white" aria-label="{{ $openReports }} movies with open reports">{{ $openReports }}</span>
                @endif
            </a>
            <a href="{{ route('admin.account') }}" @class(['shrink-0 py-3', 'text-white' => request()->routeIs('admin.account'), 'text-neutral-400 hover:text-white' => ! request()->routeIs('admin.account')])>Account</a>
            <a href="{{ route('movies.index') }}" class="shrink-0 py-3 text-neutral-400 hover:text-white" target="_blank" rel="noopener">View site</a>
            <form method="post" action="{{ route('admin.logout') }}" class="ml-auto shrink-0">
                @csrf
                <button type="submit" class="py-3 text-neutral-400 hover:text-white">Log out</button>
            </form>
        </div>
    </header>
@endauth

<main class="mx-auto max-w-5xl px-4 py-6">
    @if (session('status'))
        <p role="status" class="mb-4 rounded-lg border border-emerald-900 bg-emerald-950 px-3 py-2 text-sm text-emerald-300">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-lg border border-red-900 bg-red-950 px-3 py-2 text-sm text-red-300">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif
    @yield('content')
</main>
</body>
</html>
