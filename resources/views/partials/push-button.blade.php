{{-- Push notification switch. Hidden until resources/js/push.js knows what the browser supports.
     $variant: 'footer' (small, every page) or 'card' (home page; hidden once subscribed). --}}
@if (\App\Services\Push\PushDispatcher::enabled())
    @php $card = ($variant ?? 'footer') === 'card'; @endphp
    <div data-push-box hidden @if ($card) data-hide-when="on" @endif
         @class([
             'mb-8 flex flex-col gap-3 rounded-lg border border-ink-800 p-4 sm:flex-row sm:items-center sm:justify-between' => $card,
             'flex flex-col items-start gap-1' => ! $card,
         ])>
        @if ($card)
            <p class="text-sm text-neutral-300">{{ __('Get notified when a new movie is added.') }}</p>
        @endif
        <button type="button" data-push-action="subscribe" data-when="off error" hidden
                @class(['inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-lg px-4 text-sm font-medium', 'bg-accent text-white hover:bg-accent-hover' => $card, 'border border-ink-700 text-neutral-200 hover:border-neutral-500' => ! $card])>
            <span aria-hidden="true">🔔</span> {{ __('Turn on notifications') }}
        </button>
        <button type="button" data-push-action="unsubscribe" data-when="on" hidden
                class="inline-flex h-11 shrink-0 items-center gap-2 rounded-lg border border-ink-700 px-4 text-sm text-neutral-300 hover:border-neutral-500">
            <span aria-hidden="true">🔕</span> {{ __('Turn off notifications') }}
        </button>
        <p data-when="denied" hidden class="text-xs text-neutral-500">{{ __('Notifications are blocked. Allow them in your browser settings.') }}</p>
        <p data-when="ios" hidden class="text-xs text-neutral-500">{{ __('On iPhone: tap Share, then "Add to Home Screen", and open MovieMov from there to turn on notifications.') }}</p>
        <p data-when="error" hidden role="alert" class="text-xs text-red-400">{{ __('Something went wrong. Try again.') }}</p>
    </div>
@endif
