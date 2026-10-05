@props([
    'icon',
    'label',
    'value',
    'delta',
    'compare' => 'عن الشهر الماضي',
    'positive' => true,
])

<article {{ $attributes->class('sui-card sui-kpi') }}>
    <div class="sui-kpi__top">
        <span class="sui-kpi__icon" aria-hidden="true">
            <i data-lucide="{{ $icon }}" class="sui-icon"></i>
        </span>
        <span class="sui-kpi__label">{{ $label }}</span>
        <i data-lucide="info" class="sui-icon sui-icon--sm sui-kpi__info" title="رقم تجريبي للمعاينة"></i>
    </div>
    <p class="sui-kpi__value">{{ $value }}</p>
    <p @class(['sui-delta', 'sui-delta--up' => $positive, 'sui-delta--down' => ! $positive])>
        <bdi dir="ltr">{{ $delta }}</bdi> {{ $compare }}
    </p>
</article>
