@extends('layouts.public')

@section('title', 'المركز الإعلامي — كفاءات')
@section('meta_description', 'تابع آخر أخبار جمعية كفاءات وتصفّح مكتبة الصور من فعالياتنا وبرامجنا.')

@section('head')
<style>
    .media-tab-btn {
        position: relative;
        transition: color 0.2s, background 0.2s;
    }
    .media-tab-btn.active {
        color: #253B5B;
        font-weight: 700;
    }
    .media-tab-btn.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        right: 0;
        left: 0;
        height: 3px;
        border-radius: 9999px;
        background: #253B5B;
    }
    .media-tab-panel { display: none; }
    .media-tab-panel.active { display: block; }
    /* Progressive enhancement: بدون جافاسكربت تظهر كل اللوحات */
    .no-js .media-tab-panel { display: block; }
    .no-js .media-tab-nav { display: none; }

    .news-card {
        transition: transform 0.25s cubic-bezier(.22,1,.36,1), box-shadow 0.25s cubic-bezier(.22,1,.36,1);
    }
    .news-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 16px 40px rgba(37,59,91,0.10);
    }
    .photo-thumb {
        transition: transform 0.3s ease;
        cursor: zoom-in;
    }
    .photo-thumb:hover { transform: scale(1.04); }

    /* Lightbox */
    #lb-overlay {
        position: fixed; inset: 0; z-index: 9999;
        background: rgba(0,0,0,0.9);
        display: flex; align-items: center; justify-content: center;
    }
    #lb-overlay.hidden { display: none; }
    #lb-img { max-width: 90vw; max-height: 88vh; border-radius: 12px; object-fit: contain; }
</style>
@endsection

@section('content')

{{-- Page Header --}}
<div class="text-right mb-8">
    <p class="text-sm font-semibold uppercase tracking-widest mb-2" style="color:#3CB878">أخبار وصور</p>
    <h1 class="text-3xl sm:text-4xl font-bold mb-3" style="color:#111827">المركز الإعلامي</h1>
    <p class="text-base leading-relaxed max-w-2xl" style="color:#6B7280">
        تابع آخر أخبار الجمعية وتصفّح مكتبة الصور من فعالياتنا وبرامجنا.
    </p>
</div>

{{-- Tabs Navigation --}}
<div class="mb-8 border-b border-gray-200 media-tab-nav">
    <div class="flex gap-1" role="tablist" aria-label="أقسام المركز الإعلامي">
        <button class="media-tab-btn active px-5 py-3 text-sm font-medium rounded-t-xl hover:bg-gray-50"
                style="color:#253B5B"
                role="tab"
                id="media-tabbtn-news"
                aria-selected="true"
                aria-controls="media-tab-news"
                data-tab="news">
            الأخبار
        </button>
        <button class="media-tab-btn px-5 py-3 text-sm font-medium rounded-t-xl hover:bg-gray-50"
                style="color:#6B7280"
                role="tab"
                id="media-tabbtn-photos"
                aria-selected="false"
                aria-controls="media-tab-photos"
                tabindex="-1"
                data-tab="photos">
            الصور
        </button>
    </div>
</div>

{{-- ── News Tab ── --}}
<div id="media-tab-news" class="media-tab-panel active" role="tabpanel" aria-labelledby="media-tabbtn-news">
    @if($news->isEmpty())
    <div class="text-center py-20">
        <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background:#EAF2FA">
            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="#253B5B"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
        </div>
        <h3 class="text-lg font-semibold mb-1" style="color:#374151">لا توجد أخبار منشورة حالياً</h3>
        <p class="text-sm" style="color:#9CA3AF">تابعنا قريباً لمزيد من الأخبار.</p>
    </div>
    @else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        @foreach($news as $item)
        <a href="{{ route('public.news.show', $item->slug) }}"
           class="news-card group bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden flex flex-col text-right">
            {{-- Image --}}
            <div class="h-44 overflow-hidden bg-gray-100">
                <img src="{{ $item->imagePublicUrl() }}"
                     alt="{{ $item->title }}"
                     loading="lazy"
                     class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" />
            </div>
            <div class="p-5 flex flex-col flex-1">
                {{-- Category badge --}}
                @if($item->category)
                <span class="text-xs font-semibold mb-2 inline-block" style="color:#3CB878">{{ $item->category }}</span>
                @endif

                <h3 class="text-base font-bold leading-snug mb-2 flex-1" style="color:#111827">{{ Str::limit($item->title, 80) }}</h3>

                @if($item->excerpt)
                <p class="text-sm leading-relaxed mb-3" style="color:#6B7280">{{ Str::limit($item->excerpt, 100) }}</p>
                @endif

                <div class="flex items-center justify-between mt-auto pt-2 border-t border-gray-100">
                    <span class="text-xs font-semibold flex items-center gap-1" style="color:#253B5B">
                        اقرأ المزيد
                        <svg class="w-3.5 h-3.5 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </span>
                    @if($item->published_at)
                    <span class="text-xs" style="color:#9CA3AF">{{ $item->published_at->translatedFormat('d M Y') }}</span>
                    @endif
                </div>
            </div>
        </a>
        @endforeach
    </div>

    {{-- Pagination --}}
    @if($news->hasPages())
    <div class="flex justify-center">
        {{ $news->links() }}
    </div>
    @endif
    @endif
</div>

{{-- ── Photos Tab ── --}}
<div id="media-tab-photos" class="media-tab-panel" role="tabpanel" aria-labelledby="media-tabbtn-photos">
    @if($photos->isEmpty())
    <div class="text-center py-20">
        <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background:#EAF2FA">
            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="#253B5B"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </div>
        <h3 class="text-lg font-semibold mb-1" style="color:#374151">لا توجد صور منشورة حالياً</h3>
        <p class="text-sm" style="color:#9CA3AF">سيتم إضافة الصور قريباً.</p>
    </div>
    @else
    @foreach($photos as $album => $albumPhotos)
    <div class="mb-10">
        {{-- Album heading --}}
        <div class="flex items-center gap-3 mb-5">
            <div class="w-1 h-6 rounded-full" style="background:#253B5B"></div>
            <h2 class="text-lg font-bold" style="color:#111827">{{ $album }}</h2>
            <span class="text-xs px-2 py-0.5 rounded-full" style="background:#EAF2FA; color:#253B5B">{{ $albumPhotos->count() }} صورة</span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
            @foreach($albumPhotos as $photo)
            <button type="button"
                    class="relative rounded-xl overflow-hidden bg-gray-100 aspect-square block w-full text-right js-photo"
                    data-src="{{ $photo->imagePublicUrl() }}"
                    data-caption="{{ $photo->title }}"
                    aria-label="عرض الصورة: {{ $photo->title }}">
                <img src="{{ $photo->imagePublicUrl() }}"
                     alt="{{ $photo->title }}"
                     loading="lazy"
                     class="photo-thumb w-full h-full object-cover" />
                @if($photo->caption)
                <span class="absolute bottom-0 inset-x-0 bg-gradient-to-t from-black/60 to-transparent p-2 block">
                    <span class="text-white text-xs leading-tight truncate block">{{ $photo->caption }}</span>
                </span>
                @endif
            </button>
            @endforeach
        </div>
    </div>
    @endforeach
    @endif
</div>

{{-- Lightbox --}}
<div id="lb-overlay" class="hidden" role="dialog" aria-modal="true" aria-label="عارض الصور">
    <button type="button" id="lb-close" class="absolute top-4 left-4 text-white text-3xl font-bold leading-none hover:opacity-70 z-10" aria-label="إغلاق">×</button>
    <div class="flex flex-col items-center gap-3">
        <img id="lb-img" src="" alt="" />
        <p id="lb-caption" class="text-white text-sm opacity-80"></p>
    </div>
</div>

@endsection

@section('scripts')
<script>
(function () {
    var tabs = Array.prototype.slice.call(document.querySelectorAll('.media-tab-btn'));

    function switchMediaTab(key) {
        document.querySelectorAll('.media-tab-panel').forEach(function (el) {
            el.classList.remove('active');
        });
        tabs.forEach(function (el) {
            var isActive = el.getAttribute('data-tab') === key;
            el.classList.toggle('active', isActive);
            el.setAttribute('aria-selected', isActive ? 'true' : 'false');
            el.setAttribute('tabindex', isActive ? '0' : '-1');
            el.style.color = isActive ? '#253B5B' : '#6B7280';
        });
        var panel = document.getElementById('media-tab-' + key);
        if (panel) panel.classList.add('active');
    }

    tabs.forEach(function (btn) {
        btn.addEventListener('click', function () {
            switchMediaTab(btn.getAttribute('data-tab'));
        });
    });

    // Lightbox
    var overlay = document.getElementById('lb-overlay');
    var lbImg = document.getElementById('lb-img');
    var lbCaption = document.getElementById('lb-caption');
    var lbClose = document.getElementById('lb-close');
    var lastFocused = null;

    function openLightbox(src, caption) {
        lastFocused = document.activeElement;
        lbImg.src = src;
        lbImg.alt = caption || '';
        lbCaption.textContent = caption || '';
        overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        lbClose.focus();
    }

    function closeLightbox() {
        overlay.classList.add('hidden');
        lbImg.src = '';
        document.body.style.overflow = '';
        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }
    }

    document.querySelectorAll('.js-photo').forEach(function (el) {
        el.addEventListener('click', function () {
            openLightbox(el.getAttribute('data-src'), el.getAttribute('data-caption'));
        });
    });

    lbClose.addEventListener('click', closeLightbox);
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeLightbox();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !overlay.classList.contains('hidden')) closeLightbox();
    });

    // Support URL hash tab switching
    var hash = window.location.hash.replace('#', '');
    if (hash === 'photos' || hash === 'news') {
        switchMediaTab(hash);
    }
})();
</script>
@endsection
