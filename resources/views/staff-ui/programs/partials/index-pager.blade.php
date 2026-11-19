<div class="sui-pager sui-programs-pager">
    <span class="sui-pager__label">{{ en_num($programs->firstItem() ?? 0) }}–{{ en_num($programs->lastItem() ?? 0) }} من {{ en_num($programs->total()) }}</span>
    @foreach ([
        ['url' => $programs->url(1), 'enabled' => ! $programs->onFirstPage(), 'icon' => 'chevrons-right', 'label' => 'الصفحة الأولى'],
        ['url' => $programs->previousPageUrl(), 'enabled' => ! $programs->onFirstPage(), 'icon' => 'chevron-right', 'label' => 'السابق'],
        ['url' => $programs->nextPageUrl(), 'enabled' => $programs->hasMorePages(), 'icon' => 'chevron-left', 'label' => 'التالي'],
        ['url' => $programs->url($programs->lastPage()), 'enabled' => $programs->hasMorePages(), 'icon' => 'chevrons-left', 'label' => 'الصفحة الأخيرة'],
    ] as $pager)
        @if ($pager['enabled'])
            <a class="sui-icon-btn" href="{{ $pager['url'] }}" aria-label="{{ $pager['label'] }}"><i data-lucide="{{ $pager['icon'] }}" class="sui-icon"></i></a>
        @else
            <span class="sui-icon-btn" aria-disabled="true" aria-label="{{ $pager['label'] }}"><i data-lucide="{{ $pager['icon'] }}" class="sui-icon"></i></span>
        @endif
    @endforeach
</div>
