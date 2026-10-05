@props([
    'title',
    'value',
    'delta',
    'positive' => true,
    'kind' => 'bar',
    'labels',
    'values',
])

<article {{ $attributes->class('sui-card sui-chart') }}>
    <div class="sui-chart__head">
        <div>
            <h3 class="sui-chart__title">{{ $title }}</h3>
            <p class="sui-chart__value">{{ $value }}</p>
            <p @class(['sui-delta', 'sui-delta--up' => $positive, 'sui-delta--down' => ! $positive])><bdi dir="ltr">{{ $delta }}</bdi></p>
        </div>
        <div style="margin-inline-start: auto;">
            <x-staff-ui.dropdown align="end">
                <x-slot:trigger>
                    <x-staff-ui.button variant="secondary" size="sm" icon="chevron-down" type="button">آخر 30 يوم</x-staff-ui.button>
                </x-slot:trigger>
                <button type="button" class="sui-menu-item">آخر 7 أيام</button>
                <button type="button" class="sui-menu-item">آخر 30 يوم</button>
                <button type="button" class="sui-menu-item">آخر 90 يوم</button>
            </x-staff-ui.dropdown>
        </div>
    </div>
    <div class="sui-chart__canvas">
        <canvas
            data-sui-chart="{{ $kind }}"
            data-labels='@json($labels)'
            data-values='@json($values)'
            role="img"
            aria-label="{{ $title }}"
        ></canvas>
    </div>
</article>
