@props([
    'columns' => [],
    'group' => 'main',
    'perPage' => 6,
    'server' => false,
])

<section
    {{ $attributes->class('sui-card sui-table-card') }}
    @unless ($server)
        data-sui-table
        data-tab-group="{{ $group }}"
        data-filter="all"
        data-per-page="{{ $perPage }}"
    @endunless
>
    <div class="sui-table__toolbar">
        @if ($server)
            {{ $filters ?? '' }}
        @else
        <label class="sui-search sui-table__search">
            <i data-lucide="search" class="sui-icon"></i>
            <input type="search" data-sui-table-search placeholder="ابحث في الجدول..." aria-label="بحث في الجدول">
        </label>
        <div class="sui-bulk" data-sui-bulk>
            <span data-sui-bulk-count>تم تحديد 0</span>
            <x-staff-ui.button variant="secondary" size="sm" type="button" data-sui-toast data-tone="info" data-title="إجراء جماعي" data-body="المعاينة لا تنفّذ الإجراء.">تصدير المحدد</x-staff-ui.button>
            <x-staff-ui.button variant="ghost" size="sm" type="button" data-sui-bulk-clear>إلغاء</x-staff-ui.button>
        </div>
        @endif
    </div>
    <div class="sui-table-wrap">
        <table class="sui-table">
            <thead>
                <tr>
                    @unless ($server)
                        <th style="width: 36px;"><input class="sui-check" type="checkbox" data-sui-check-all aria-label="تحديد الكل"></th>
                    @endunless
                    @foreach ($columns as $column)
                        <th>
                            @if ($column['sortable'] ?? false)
                                <button type="button" class="sui-sort" data-sui-sort="{{ $column['key'] }}">
                                    {{ $column['label'] }}
                                    <i data-lucide="chevrons-up-down" class="sui-icon"></i>
                                </button>
                            @else
                                {{ $column['label'] }}
                            @endif
                        </th>
                    @endforeach
                    @unless ($server)
                        <th style="width: 48px;"><span class="sui-sr">إجراءات</span></th>
                    @endunless
                </tr>
            </thead>
            <tbody>{{ $slot }}</tbody>
        </table>
    </div>
    <div class="sui-table__empty">
        @if (isset($emptyState))
            {{ $emptyState }}
        @else
            <i data-lucide="search-x" class="sui-icon"></i>
            <p>لا توجد نتائج مطابقة.</p>
        @endif
    </div>
    <div class="sui-table__loading" aria-hidden="true">
        <div class="sui-skel"></div>
        <div class="sui-skel"></div>
        <div class="sui-skel"></div>
    </div>
    @if ($server)
        {{ $footer ?? '' }}
    @else
    <div class="sui-pager">
        <span class="sui-pager__label" data-sui-page-label></span>
        <button type="button" class="sui-icon-btn" data-sui-page="first" aria-label="الصفحة الأولى"><i data-lucide="chevrons-right" class="sui-icon"></i></button>
        <button type="button" class="sui-icon-btn" data-sui-page="prev" aria-label="السابق"><i data-lucide="chevron-right" class="sui-icon"></i></button>
        <button type="button" class="sui-icon-btn" data-sui-page="next" aria-label="التالي"><i data-lucide="chevron-left" class="sui-icon"></i></button>
        <button type="button" class="sui-icon-btn" data-sui-page="last" aria-label="الصفحة الأخيرة"><i data-lucide="chevrons-left" class="sui-icon"></i></button>
    </div>
    @endif
</section>
