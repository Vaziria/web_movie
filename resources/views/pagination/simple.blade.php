@if ($paginator->hasPages())
    <nav aria-label="Pagination" class="mt-8 flex items-center justify-between gap-4 text-sm md:justify-center">
        @if ($paginator->onFirstPage())
            <span class="inline-flex h-11 items-center px-3 text-neutral-600">&larr; Prev</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex h-11 items-center rounded-lg border border-ink-700 px-4 text-neutral-200 hover:border-neutral-500">&larr; Prev</a>
        @endif
        <span class="text-neutral-500">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex h-11 items-center rounded-lg border border-ink-700 px-4 text-neutral-200 hover:border-neutral-500">Next &rarr;</a>
        @else
            <span class="inline-flex h-11 items-center px-3 text-neutral-600">Next &rarr;</span>
        @endif
    </nav>
@endif
