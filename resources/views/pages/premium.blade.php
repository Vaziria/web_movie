@extends('layouts.app')

@section('content')
    <div class="mx-auto mt-4 max-w-lg">
        <span class="rounded bg-amber-400 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-ink-950">Premium</span>
        <h1 class="mt-3 text-xl font-semibold text-white md:text-2xl">{{ __('Premium: watch without ads') }}</h1>

        <ul class="mt-4 space-y-2 text-sm text-neutral-300">
            <li class="flex gap-2"><span class="text-amber-400" aria-hidden="true">✓</span>{{ __('An ad-free player on every movie') }}</li>
            <li class="flex gap-2"><span class="text-amber-400" aria-hidden="true">✓</span>{{ __('No pop-ups or redirects while you watch') }}</li>
            <li class="flex gap-2"><span class="text-amber-400" aria-hidden="true">✓</span>{{ __('The free player stays available as a backup') }}</li>
        </ul>

        <div class="mt-6 rounded-lg border border-ink-800 p-4 text-sm">
            @if ($user?->isPremium())
                <p class="font-medium text-emerald-400">
                    @if ($user->is_admin && ! $user->premium_until?->isFuture())
                        {{ __('You are an admin: premium is always on.') }}
                    @else
                        {{ __('Premium active until :date', ['date' => $user->premium_until->timezone(config('movies.admin_timezone'))->translatedFormat('j F Y')]) }}
                    @endif
                </p>
            @elseif ($contact)
                <p class="text-neutral-300">{{ __('To subscribe, contact us:') }}</p>
                <a href="mailto:{{ $contact }}?subject={{ rawurlencode('Premium '.config('app.name').($user ? ' - '.$user->email : '')) }}"
                   class="mt-1 inline-block py-2 font-medium text-white underline underline-offset-4">{{ $contact }}</a>
                @guest
                    <p class="mt-2 text-neutral-400">{!! __('Premium is linked to your account, so :login or :register first.', [
                        'login' => '<a href="'.e(lroute('auth.login', ['next' => request()->getRequestUri()])).'" class="text-white underline underline-offset-4">'.e(__('log in')).'</a>',
                        'register' => '<a href="'.e(lroute('auth.register', ['next' => request()->getRequestUri()])).'" class="text-white underline underline-offset-4">'.e(__('sign up')).'</a>',
                    ]) !!}</p>
                @else
                    <p class="mt-2 text-neutral-400">{{ __('Mention your account email: :email', ['email' => $user->email]) }}</p>
                @endguest
            @else
                <p class="text-neutral-300">{{ __('Subscriptions open soon.') }}</p>
            @endif
        </div>

        <p class="mt-6 text-sm"><a href="{{ lroute('movies.index') }}" class="inline-block py-2 text-neutral-400 hover:text-white">← {{ __('Back to movies') }}</a></p>
    </div>
@endsection
