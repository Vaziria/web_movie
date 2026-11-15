{{-- Header account link: "Masuk" for guests, initials/avatar for logged-in visitors. --}}
@auth
    <a href="{{ lroute('account') }}" class="inline-flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-full border border-ink-700 bg-ink-800 text-xs font-semibold text-white hover:border-neutral-500"
       aria-label="{{ __('My account') }}" title="{{ auth()->user()->name }}">
        @if (auth()->user()->avatar_url)
            <img src="{{ auth()->user()->avatar_url }}" alt="" width="36" height="36" class="size-full object-cover" referrerpolicy="no-referrer">
        @else
            {{ auth()->user()->initials() }}
        @endif
    </a>
@else
    {{-- No rel=nofollow: the login page itself is noindex, and the header stays a plain site-wide link. --}}
    <a href="{{ lroute('auth.login', request()->routeIs('movies.show', 'en.movies.show') ? ['next' => request()->getRequestUri()] : []) }}"
       class="inline-flex h-9 shrink-0 items-center rounded-lg border border-ink-700 px-3 text-xs font-medium text-neutral-200 hover:border-neutral-500">{{ __('Log in') }}</a>
@endauth
