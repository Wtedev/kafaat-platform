{{--
    Homepage «من نحن» — الجمعية intro only.
    Vision and mission copy live in the homepage hero.
--}}
@php
    $about = config('about', []);
@endphp

<section id="about" class="scroll-mt-24 bg-white py-20 sm:py-24">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <header class="reveal-fade max-w-3xl text-right">
            <p class="mb-1 text-sm font-semibold" style="color:#1a9399">
                {{ $about['badge'] ?? 'من نحن' }}
            </p>
            <h2 class="text-2xl font-bold text-brand">
                {{ $about['title'] ?? 'جمعية كفاءات' }}
            </h2>
            <p class="mt-3 text-sm leading-relaxed sm:text-base" style="color:#6B7280">
                {{ $about['intro'] ?? '' }}
            </p>
        </header>
    </div>
</section>
