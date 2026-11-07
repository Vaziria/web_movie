@if ($paginator->hasPages())
    <nav aria-label="{{ __('Pagination') }}" class="mt-8 flex items-center justify-between gap-4 text-sm md:justify-center">
        @if ($paginator->onFirstPage())
            <span class="inline-flex h-11 items-center px-3 text-neutral-600">&larr; {{ __('Prev') }}</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex h-11 items-center rounded-lg border border-ink-700 px-4 text-neutral-200 hover:border-neutral-500">&larr; {{ __('Prev') }}</a>
        @endif
        <span class="text-neutral-500">{{ __('Page :current of :last', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex h-11 items-center rounded-lg border border-ink-700 px-4 text-neutral-200 hover:border-neutral-500">{{ __('Next') }} &rarr;</a>
        @else
            <span class="inline-flex h-11 items-center px-3 text-neutral-600">{{ __('Next') }} &rarr;</span>
        @endif
    </nav>
@endif
