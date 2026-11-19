<section class="sui-card">
    <div class="sui-card__head sui-card__head--row">
        <h2>سجل النشاط</h2>
        <form method="GET" class="sui-activity-filter">
            <x-staff-ui.select name="group" label="النوع" :selected="$activityGroup" :options="['' => 'الكل'] + $activityGroups" onchange="this.form.submit()" />
            <noscript><x-staff-ui.button type="submit" variant="secondary" size="sm">تصفية</x-staff-ui.button></noscript>
        </form>
    </div>
    @include('staff-ui.programs.partials.activity-list', ['items' => $activities->getCollection()])
    @if ($activities->hasPages())
        <div class="sui-pager">
            <span class="sui-pager__label">{{ en_num($activities->firstItem() ?? 0) }}–{{ en_num($activities->lastItem() ?? 0) }} من {{ en_num($activities->total()) }}</span>
            @foreach ([
                ['url' => $activities->previousPageUrl(), 'enabled' => ! $activities->onFirstPage(), 'icon' => 'chevron-right', 'label' => 'الأحدث'],
                ['url' => $activities->nextPageUrl(), 'enabled' => $activities->hasMorePages(), 'icon' => 'chevron-left', 'label' => 'الأقدم'],
            ] as $pager)
                @if ($pager['enabled'])
                    <a class="sui-icon-btn" href="{{ $pager['url'] }}" aria-label="{{ $pager['label'] }}"><i data-lucide="{{ $pager['icon'] }}" class="sui-icon"></i></a>
                @else
                    <span class="sui-icon-btn" aria-disabled="true" aria-label="{{ $pager['label'] }}"><i data-lucide="{{ $pager['icon'] }}" class="sui-icon"></i></span>
                @endif
            @endforeach
        </div>
    @endif
</section>
