@extends('layouts.public')

@section('title', 'مسارات الكفاءة — كفاءات')
@section('meta_description', 'تعرّف على مسارات الكفاءة الثلاثة في كفاءات: الذاتية، المهنية، والمجتمعية — إطار تنظيمي لبرامج ومبادرات كفاءات.')

@section('content')

@php
use App\Enums\CompetencyTrack;
use App\Support\CompetencyTrackCatalog;

$intro = config('competency_tracks.intro', []);
$about = config('competency_tracks.about', []);
$tracks = CompetencyTrackCatalog::tracks();
$order = CompetencyTrackCatalog::order();
@endphp

<header class="mb-8 text-right">
    <p class="mb-1 text-sm font-semibold" style="color:#1a9399">
        {{ $intro['badge'] ?? 'مسارات الكفاءة' }}
    </p>
    <h1 class="text-2xl font-bold">
        {{ $intro['title'] ?? 'مسارات الكفاءة' }}
    </h1>
    <p class="mt-3 max-w-3xl text-sm leading-relaxed sm:text-base" style="color:#6B7280">
        {{ $intro['subtitle'] ?? '' }}
    </p>
</header>

<div class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-2">
    <section class="rounded-2xl border border-gray-100 bg-white p-6 sm:p-7">
        <h2 class="mb-3 text-lg font-bold">
            {{ $about['heading'] ?? 'ما المقصود بمسارات الكفاءة؟' }}
        </h2>
        <p class="text-sm leading-relaxed sm:text-base" style="color:#6B7280">
            {{ $about['body'] ?? '' }}
        </p>
    </section>

    <section class="rounded-2xl border border-gray-100 bg-white p-6 sm:p-7">
        <h2 class="mb-3 text-lg font-bold">
            {{ $about['why_heading'] ?? 'لماذا نعتمد هذا التصنيف؟' }}
        </h2>
        <p class="text-sm leading-relaxed sm:text-base" style="color:#6B7280">
            {{ $about['why_body'] ?? '' }}
        </p>
    </section>
</div>

<section class="mb-10 rounded-2xl border border-gray-100 bg-[#F8FAFC] p-6 sm:p-7">
    <h2 class="mb-3 text-lg font-bold">
        {{ $about['relation_heading'] ?? 'كيف ترتبط بالبرامج؟' }}
    </h2>
    <p class="max-w-3xl text-sm leading-relaxed sm:text-base" style="color:#6B7280">
        {{ $about['relation_body'] ?? '' }}
    </p>
</section>

<section class="mb-10">
    <div class="mb-6 text-right">
        <h2 class="text-xl font-bold">
            {{ $about['tracks_heading'] ?? 'المسارات الثلاثة' }}
        </h2>
        <p class="mt-2 text-sm sm:text-base" style="color:#6B7280">
            {{ $about['tracks_subtitle'] ?? '' }}
        </p>
    </div>

    <div class="space-y-6">
        @foreach ($order as $trackKey)
            @php
                $track = CompetencyTrack::from($trackKey);
                $meta = $tracks[$trackKey] ?? [];
                $color = $meta['color'] ?? '#335483';
                $focus = $meta['focus'] ?? [];
            @endphp

            <article class="track-info-card group rounded-2xl border border-gray-100 bg-white transition-shadow duration-200 hover:shadow-md">
                <div class="border-b border-gray-100 px-5 py-4 sm:px-7 sm:py-5">
                    <div class="text-right">
                        <div class="mb-2 flex flex-wrap items-center justify-end gap-2">
                            <span
                                class="inline-flex items-center justify-center rounded-md px-2.5 py-1 text-xs font-semibold leading-none"
                                style="background:{{ $color }}14; color:{{ $color }}"
                            >
                                <span class="translate-y-0.5">{{ $meta['stat_label'] ?? '' }}</span>
                            </span>
                            <span
                                class="h-2 w-2 shrink-0 rounded-full"
                                style="background:{{ $color }}"
                                aria-hidden="true"
                            ></span>
                        </div>
                        <h3 class="text-lg font-bold sm:text-xl" style="color:{{ $color }}">
                            {{ $track->shortLabel() }}
                        </h3>
                    </div>
                </div>

                <div class="space-y-4 px-5 py-5 sm:px-7 sm:py-6">
                    <p class="text-sm font-medium leading-relaxed sm:text-base" style="color:var(--brand-body)">
                        {{ $meta['description'] ?? '' }}
                    </p>

                    @if (! empty($meta['detail']))
                        <p class="text-sm leading-relaxed sm:text-base" style="color:#6B7280">
                            {{ $meta['detail'] }}
                        </p>
                    @endif

                    @if (! empty($focus))
                        <div class="pt-1">
                            <p class="mb-3 text-sm font-semibold" style="color:var(--brand-body)">
                                {{ $meta['focus_heading'] ?? 'أبرز مجالات عمل كفاءات في هذا المسار' }}
                            </p>
                            <ul class="space-y-2.5" dir="rtl">
                                @foreach ($focus as $item)
                                    <li class="flex items-start gap-2 text-sm leading-relaxed" style="color:#4B5563">
                                        <svg
                                            class="mt-0.5 h-4 w-4 shrink-0 rotate-180"
                                            style="color:{{ $color }}"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            aria-hidden="true"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                        <span>{{ $item }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end border-t border-gray-50 px-5 py-3.5 sm:px-7">
                    <a
                        href="{{ route('public.programs.track', $track) }}"
                        class="inline-flex items-center gap-2.5 text-sm font-semibold leading-none transition-opacity group-hover:opacity-90 hover:opacity-80"
                        style="color:{{ $color }}"
                    >
                        <span class="translate-y-0.5">برامج ومبادرات هذا المسار</span>
                        <span
                            class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full"
                            style="background:{{ $color }}"
                            aria-hidden="true"
                        >
                            <svg class="h-3 w-3 rotate-180 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </span>
                    </a>
                </div>
            </article>
        @endforeach
    </div>
</section>

<section class="pt-2 text-right">
    <h2 class="text-lg font-bold sm:text-xl">
        {{ $about['cta_heading'] ?? 'من التعريف إلى المشاركة' }}
    </h2>
    <p class="mt-2 max-w-2xl text-sm leading-relaxed sm:text-base" style="color:#6B7280">
        {{ $about['cta_body'] ?? '' }}
    </p>
    <a
        href="{{ route('home') }}#programs"
        class="mt-4 inline-flex items-center gap-2 text-sm font-semibold transition-opacity hover:opacity-80"
        style="color:#335483"
    >
        {{ $about['cta_button'] ?? 'استكشف برامج كفاءات' }}
        <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
    </a>
</section>

@endsection
