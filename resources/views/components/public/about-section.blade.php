{{--
    Homepage «من نحن» — intro, vision, and mission.
--}}
@php
    $about = config('about', []);
@endphp

<section id="about" class="scroll-mt-24 bg-white py-20 sm:py-24">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <header class="reveal-fade mb-10 max-w-3xl text-right">
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

        <div class="reveal-fade grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-5">
            <article class="rounded-2xl border border-gray-100 bg-[#F8FAFC] p-6 sm:p-7">
                <p class="mb-3 text-sm font-semibold" style="color:#335483">
                    {{ $about['vision']['label'] ?? 'الرؤية' }}
                </p>
                <p class="text-sm leading-relaxed sm:text-base" style="color:var(--brand-body)">
                    {{ $about['vision']['text'] ?? '' }}
                </p>
            </article>

            <article class="rounded-2xl border border-gray-100 bg-[#F8FAFC] p-6 sm:p-7">
                <p class="mb-3 text-sm font-semibold" style="color:#1a9399">
                    {{ $about['mission']['label'] ?? 'الرسالة' }}
                </p>
                <p class="text-sm leading-relaxed sm:text-base" style="color:var(--brand-body)">
                    {{ $about['mission']['text'] ?? '' }}
                </p>
            </article>
        </div>
    </div>
</section>
