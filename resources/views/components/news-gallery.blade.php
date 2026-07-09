@props([
    'primaryUrl',
    'primaryAlt' => '',
    'galleryUrls' => [],
])

@php
    $galleryUrls = array_values(array_filter($galleryUrls));
    $hasGallery = count($galleryUrls) > 0;
@endphp

<div class="news-gallery mb-8 {{ $hasGallery ? 'news-gallery--with-rail' : '' }}">
    <div class="news-gallery__layout {{ $hasGallery ? 'grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_7.5rem] gap-4 items-stretch' : '' }}">
        <div class="news-gallery__primary relative overflow-hidden rounded-2xl ring-1 ring-black/5 shadow-sm">
            <img
                src="{{ $primaryUrl }}"
                alt="{{ $primaryAlt }}"
                class="h-full w-full object-cover {{ $hasGallery ? 'min-h-[16rem] md:min-h-[22rem]' : '' }}"
                style="{{ $hasGallery ? '' : 'max-height:420px' }}"
                loading="eager"
            >
            @if ($hasGallery)
                <span class="absolute top-3 start-3 rounded-full bg-[#335483]/90 px-3 py-1 text-xs font-semibold text-white shadow">
                    الصورة الأساسية
                </span>
            @endif
        </div>

        @if ($hasGallery)
            <div
                class="news-gallery__rail relative hidden overflow-hidden rounded-2xl bg-gradient-to-b from-[#e9eff6] to-white ring-1 ring-black/5 md:block"
                aria-hidden="true"
            >
                <div class="news-gallery__fade news-gallery__fade--top pointer-events-none absolute inset-x-0 top-0 z-10 h-10 bg-gradient-to-b from-[#F7FAFC] to-transparent"></div>
                <div class="news-gallery__fade news-gallery__fade--bottom pointer-events-none absolute inset-x-0 bottom-0 z-10 h-10 bg-gradient-to-t from-[#F7FAFC] to-transparent"></div>

                <div class="news-gallery__track flex flex-col gap-3 p-2">
                    @foreach (array_merge($galleryUrls, $galleryUrls) as $url)
                        <div class="news-gallery__thumb shrink-0 overflow-hidden rounded-xl ring-1 ring-white/70 shadow-sm">
                            <img src="{{ $url }}" alt="" class="h-24 w-full object-cover" loading="lazy">
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @if ($hasGallery)
        <div class="news-gallery__mobile-rail mt-4 flex gap-3 overflow-x-auto pb-1 md:hidden">
            @foreach ($galleryUrls as $url)
                <div class="news-gallery__thumb shrink-0 overflow-hidden rounded-xl ring-1 ring-black/5 shadow-sm">
                    <img src="{{ $url }}" alt="" class="h-20 w-28 object-cover" loading="lazy">
                </div>
            @endforeach
        </div>
    @endif
</div>

@if ($hasGallery)
    <style>
        @keyframes news-gallery-scroll-up {
            0% { transform: translateY(0); }
            100% { transform: translateY(-50%); }
        }

        .news-gallery__rail {
            height: clamp(16rem, 42vw, 22rem);
        }

        .news-gallery__track {
            animation: news-gallery-scroll-up 18s linear infinite;
            will-change: transform;
        }

        .news-gallery__thumb img {
            transition: transform 0.6s cubic-bezier(0.22, 1, 0.36, 1), filter 0.6s ease;
        }

        .news-gallery__track:hover {
            animation-play-state: paused;
        }

        @media (prefers-reduced-motion: reduce) {
            .news-gallery__track {
                animation: none;
            }
        }
    </style>
@endif
