@props([
    'primaryUrl',
    'primaryAlt' => '',
    'galleryUrls' => [],
])

@php
    $galleryUrls = array_values(array_filter($galleryUrls));
    $images = array_values(array_unique(array_filter(array_merge(
        filled($primaryUrl) ? [$primaryUrl] : [],
        $galleryUrls,
    ))));
    $imageCount = count($images);
    $hasMultiple = $imageCount > 1;
    $galleryId = 'news-gallery-'.substr(md5(implode('|', $images).$primaryAlt), 0, 10);
@endphp

<div
    id="{{ $galleryId }}"
    class="news-gallery mb-8 {{ $hasMultiple ? 'news-gallery--with-rail' : '' }}"
    @if ($hasMultiple) data-news-gallery-autoplay @endif
>
    <div class="news-gallery__layout {{ $hasMultiple ? 'grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_7.5rem] gap-4 items-stretch' : '' }}">
        <div class="news-gallery__primary relative overflow-hidden rounded-2xl ring-1 ring-black/5 shadow-sm">
            <img
                data-news-gallery-main
                src="{{ $images[0] }}"
                alt="{{ $primaryAlt }}"
                class="h-full w-full object-cover transition-opacity duration-500 {{ $hasMultiple ? 'min-h-[16rem] md:min-h-[22rem]' : '' }}"
                style="{{ $hasMultiple ? '' : 'max-height:420px' }}"
                loading="eager"
            >
        </div>

        @if ($hasMultiple)
            <div
                class="news-gallery__rail hidden flex-col gap-3 rounded-2xl bg-gradient-to-b from-[#e9eff6] to-white p-2 ring-1 ring-black/5 md:flex"
                aria-label="معاينة صور الخبر"
            >
                @foreach ($images as $index => $url)
                    <button
                        type="button"
                        data-news-gallery-thumb="{{ $index }}"
                        class="news-gallery__thumb shrink-0 overflow-hidden rounded-xl ring-2 ring-transparent shadow-sm transition {{ $index === 0 ? 'is-active' : '' }}"
                        aria-label="عرض الصورة {{ $index + 1 }}"
                        aria-pressed="{{ $index === 0 ? 'true' : 'false' }}"
                    >
                        <img src="{{ $url }}" alt="" class="h-24 w-full object-cover" loading="lazy">
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    @if ($hasMultiple)
        <div class="news-gallery__mobile-rail mt-4 flex gap-3 overflow-x-auto pb-1 md:hidden" aria-label="معاينة صور الخبر">
            @foreach ($images as $index => $url)
                <button
                    type="button"
                    data-news-gallery-thumb="{{ $index }}"
                    class="news-gallery__thumb shrink-0 overflow-hidden rounded-xl ring-2 ring-transparent shadow-sm transition {{ $index === 0 ? 'is-active' : '' }}"
                    aria-label="عرض الصورة {{ $index + 1 }}"
                    aria-pressed="{{ $index === 0 ? 'true' : 'false' }}"
                >
                    <img src="{{ $url }}" alt="" class="h-20 w-28 object-cover" loading="lazy">
                </button>
            @endforeach
        </div>
    @endif
</div>

@if ($hasMultiple)
    <style>
        .news-gallery__rail {
            height: clamp(16rem, 42vw, 22rem);
            overflow-y: auto;
            scrollbar-width: thin;
        }

        .news-gallery__thumb.is-active {
            box-shadow: 0 0 0 2px #335483;
        }

        .news-gallery__thumb:focus-visible {
            outline: 2px solid #335483;
            outline-offset: 2px;
        }
    </style>

    <script>
        (function () {
            var root = document.getElementById(@json($galleryId));
            if (!root) return;

            var images = @json($images);
            if (images.length <= 1) return;

            var mainImg = root.querySelector('[data-news-gallery-main]');
            var thumbGroups = root.querySelectorAll('[data-news-gallery-thumb]');
            var index = 0;
            var timer = null;
            var intervalMs = 5000;

            function setIndex(nextIndex) {
                index = ((nextIndex % images.length) + images.length) % images.length;
                mainImg.src = images[index];
                thumbGroups.forEach(function (btn) {
                    var isActive = Number(btn.getAttribute('data-news-gallery-thumb')) === index;
                    btn.classList.toggle('is-active', isActive);
                    btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });
            }

            function advance() {
                setIndex(index + 1);
            }

            function startAuto() {
                stopAuto();
                timer = setInterval(advance, intervalMs);
            }

            function stopAuto() {
                if (timer) {
                    clearInterval(timer);
                    timer = null;
                }
            }

            thumbGroups.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    setIndex(Number(btn.getAttribute('data-news-gallery-thumb')));
                    startAuto();
                });
            });

            root.addEventListener('mouseenter', stopAuto);
            root.addEventListener('mouseleave', startAuto);
            root.addEventListener('focusin', stopAuto);
            root.addEventListener('focusout', function (event) {
                if (!root.contains(event.relatedTarget)) {
                    startAuto();
                }
            });

            if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                startAuto();
            }
        })();
    </script>
@endif
