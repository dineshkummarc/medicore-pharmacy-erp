@if ($paginator->hasPages() || $paginator->total() > 0)
    <nav class="flex flex-col items-center justify-between gap-3 border-t border-slate-100 px-5 py-3 text-sm sm:flex-row" aria-label="Pagination">
        <p class="text-slate-500">
            @if ($paginator->total() > 0)
                Showing <span class="font-medium text-slate-700">{{ $paginator->firstItem() }}</span>–<span class="font-medium text-slate-700">{{ $paginator->lastItem() }}</span>
                of <span class="font-medium text-slate-700">{{ number_format($paginator->total()) }}</span>
            @endif
        </p>
        @if ($paginator->hasPages())
            <div class="flex items-center gap-1">
                @if ($paginator->onFirstPage())
                    <span class="btn-secondary btn-sm pointer-events-none opacity-50"><x-icon name="chevron-left" class="size-4" /></span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" class="btn-secondary btn-sm" rel="prev" aria-label="Previous page"><x-icon name="chevron-left" class="size-4" /></a>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="hidden px-2 text-slate-400 sm:inline">…</span>
                    @endif
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="hidden min-w-8 rounded-lg bg-brand-600 px-2.5 py-1.5 text-center text-xs font-semibold text-white sm:inline-block">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="hidden min-w-8 rounded-lg px-2.5 py-1.5 text-center text-xs font-medium text-slate-600 hover:bg-slate-100 sm:inline-block">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" class="btn-secondary btn-sm" rel="next" aria-label="Next page"><x-icon name="chevron-right" class="size-4" /></a>
                @else
                    <span class="btn-secondary btn-sm pointer-events-none opacity-50"><x-icon name="chevron-right" class="size-4" /></span>
                @endif
            </div>
        @endif
    </nav>
@endif
