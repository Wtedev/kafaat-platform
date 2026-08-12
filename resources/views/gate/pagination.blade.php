@if ($paginator->hasPages())
    <nav role="navigation" aria-label="التنقل بين الصفحات" class="w-full max-w-full overflow-hidden">
        {{-- Mobile: compact previous / current of last / next --}}
        <div class="flex items-center justify-between gap-2 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="inline-flex items-center rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-[11px] font-medium text-gray-400" aria-disabled="true">
                    السابق
                </span>
            @else
                <a
                    href="{{ $paginator->previousPageUrl() }}"
                    rel="prev"
                    class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-gray-700 hover:bg-gray-50"
                >
                    السابق
                </a>
            @endif

            <span class="text-[11px] font-medium text-gray-600 tabular-nums">
                {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
            </span>

            @if ($paginator->hasMorePages())
                <a
                    href="{{ $paginator->nextPageUrl() }}"
                    rel="next"
                    class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-gray-700 hover:bg-gray-50"
                >
                    التالي
                </a>
            @else
                <span class="inline-flex items-center rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-[11px] font-medium text-gray-400" aria-disabled="true">
                    التالي
                </span>
            @endif
        </div>

        {{-- Desktop/tablet: page number buttons --}}
        <div class="hidden flex-wrap items-center justify-center gap-1.5 sm:flex">
            @if ($paginator->onFirstPage())
                <span class="inline-flex items-center rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-400" aria-disabled="true">
                    السابق
                </span>
            @else
                <a
                    href="{{ $paginator->previousPageUrl() }}"
                    rel="prev"
                    class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                >
                    السابق
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="inline-flex min-w-[1.75rem] items-center justify-center px-1 text-xs text-gray-400" aria-hidden="true">…</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span
                                aria-current="page"
                                class="inline-flex min-w-[2.1rem] items-center justify-center rounded-lg border border-[#335483] bg-[#335483] px-2 py-1.5 text-xs font-semibold text-white"
                            >
                                {{ $page }}
                            </span>
                        @else
                            <a
                                href="{{ $url }}"
                                class="inline-flex min-w-[2.1rem] items-center justify-center rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                aria-label="الصفحة {{ $page }}"
                            >
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a
                    href="{{ $paginator->nextPageUrl() }}"
                    rel="next"
                    class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                >
                    التالي
                </a>
            @else
                <span class="inline-flex items-center rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-400" aria-disabled="true">
                    التالي
                </span>
            @endif
        </div>
    </nav>
@endif
