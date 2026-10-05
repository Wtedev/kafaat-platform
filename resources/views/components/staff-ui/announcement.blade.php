@props([
    'pill' => 'جديد',
    'title',
])

<section {{ $attributes->class('sui-banner') }}>
    <div class="sui-banner__copy">
        <span class="sui-banner__pill">
            <i data-lucide="sparkles" class="sui-icon sui-icon--sm"></i>
            {{ $pill }}
        </span>
        <h2 class="sui-banner__title">{{ $title }}</h2>
    </div>
    {{ $action ?? '' }}
</section>
