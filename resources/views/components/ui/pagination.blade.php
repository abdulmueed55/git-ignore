@if ($paginator->hasPages())
    <nav class="flex items-center justify-between gap-3 text-[12.5px]" aria-label="Pagination">
        <div class="hidden text-muted sm:block">
            Showing <span class="mono text-ink">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span>
            of <span class="mono text-ink">{{ $paginator->total() }}</span>
        </div>
        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="btn btn-ghost btn-sm opacity-40"><x-icon name="arrow-left" :size="14" /></span>
            @else
                <a class="btn btn-ghost btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev"><x-icon name="arrow-left" :size="14" /></a>
            @endif
            <span class="mono px-2 text-muted sm:hidden">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
            <span class="hidden items-center gap-1 sm:flex">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-2 text-muted">…</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="btn btn-sm mono bg-ink text-paper">{{ $page }}</span>
                        @else
                            <a class="btn btn-ghost btn-sm mono" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
            </span>
            @if ($paginator->hasMorePages())
                <a class="btn btn-ghost btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next"><x-icon name="arrow-right" :size="14" /></a>
            @else
                <span class="btn btn-ghost btn-sm opacity-40"><x-icon name="arrow-right" :size="14" /></span>
            @endif
        </div>
    </nav>
@endif
