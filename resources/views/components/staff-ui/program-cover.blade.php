@props([
    'program',
    'index' => 0,
    'variant' => 'card',
])

@php
    $hasImage = filled($program->image);
    $imageUrl = $program->imagePublicUrl();
    $contain = $hasImage && $program->imageUsesContainFit();
    $surface = $contain ? ($program->imageHeroSurfaceColor() ?: '#eef2f6') : null;
    $gradients = config('brand.image_gradients', [
        'linear-gradient(135deg, #e8f0f7, #d5e4f2)',
        'linear-gradient(135deg, #eef6f1, #d9ebe0)',
        'linear-gradient(135deg, #f7f0e8, #efe0d0)',
    ]);
    $placeholderBg = $gradients[$index % max(1, count($gradients))];
@endphp

@if ($variant === 'thumb')
    <div {{ $attributes->class('sui-program-thumb') }}>
        @if ($hasImage)
            <div class="sui-program-thumb__media" @if ($contain) style="background: {{ $surface }}" @endif>
                <img
                    src="{{ $imageUrl }}"
                    alt=""
                    class="{{ $contain ? 'sui-program-thumb__img sui-program-thumb__img--contain' : 'sui-program-thumb__img' }}"
                    loading="lazy"
                    decoding="async"
                >
            </div>
        @else
            <div class="sui-program-thumb__placeholder" style="background: {{ $placeholderBg }}" aria-hidden="true">
                <i data-lucide="graduation-cap" class="sui-icon"></i>
            </div>
        @endif
    </div>
@else
    <div {{ $attributes->class('sui-program-cover') }}>
        @if ($hasImage)
            <div class="sui-program-cover__media" @if ($contain) style="background: {{ $surface }}" @endif>
                <img
                    src="{{ $imageUrl }}"
                    alt=""
                    class="{{ $contain ? 'sui-program-cover__img sui-program-cover__img--contain' : 'sui-program-cover__img' }}"
                    loading="lazy"
                    decoding="async"
                >
            </div>
        @else
            <div class="sui-program-cover__placeholder" style="background: {{ $placeholderBg }}" aria-hidden="true">
                <i data-lucide="graduation-cap" class="sui-icon"></i>
            </div>
        @endif
    </div>
@endif
