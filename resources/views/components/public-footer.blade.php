{{--
    resources/views/components/public-footer.blade.php
    Public footer — dark theme; map: Leaflet + CARTO dark_matter tiles.
--}}
@php
    $site = config('site');
    $telHref = '+' . preg_replace('/\D+/', '', (string) ($site['contact_phone_e164'] ?? ''));
    $maps = $site['maps'] ?? [];
    $mapsLink = is_string($maps['link'] ?? null) ? $maps['link'] : '#';
    $mapLat = (float) ($maps['lat'] ?? 26.3676773);
    $mapLng = (float) ($maps['lng'] ?? 43.9288304);
    $mapZoom = (int) ($maps['zoom'] ?? 16);
@endphp
<footer class="relative min-w-0 overflow-x-hidden border-t border-white/10 bg-gradient-to-b from-[#111827] via-[#0f172a] to-[#0b1220] text-white antialiased">
    <div class="mx-auto max-w-7xl px-4 pb-[max(1.5rem,env(safe-area-inset-bottom,0px))] pt-12 sm:px-6 sm:pb-10 sm:pt-16 lg:px-8">

        <div class="grid grid-cols-1 gap-10 sm:gap-12 md:grid-cols-2 lg:grid-cols-12 lg:gap-x-10 lg:gap-y-12">

            {{-- Brand --}}
            <div class="text-center sm:text-right lg:col-span-4">
                <a href="{{ route('home') }}" class="inline-block text-2xl font-bold tracking-tight text-white transition-colors hover:text-emerald-300">كفاءات</a>
                <p class="mx-auto mt-3 max-w-sm text-sm leading-relaxed text-gray-400 sm:mx-0 sm:max-w-none">
                    منصة تدريب وتطوع متكاملة تسعى إلى بناء قدرات الشباب وتمكينهم من التميز في مساراتهم المهنية.
                </p>
                <div class="mt-5 flex flex-wrap items-center justify-center gap-2.5 sm:justify-end">
                    @foreach($site['social'] ?? [] as $social)
                        <a
                            href="{{ $social['url'] }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border border-white/10 bg-white/10 text-gray-300 transition-all duration-200 hover:-translate-y-0.5 hover:bg-emerald-500/20 hover:text-white hover:shadow-lg hover:shadow-emerald-900/20"
                            aria-label="{{ $social['label'] }}"
                        >
                            @switch($social['key'] ?? '')
                                @case('youtube')
                                    <svg class="h-[1.1rem] w-[1.1rem]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                                    @break
                                @case('linkedin')
                                    <svg class="h-[1.05rem] w-[1.05rem]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 4.127 0c0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                                    @break
                                @case('x')
                                    <svg class="h-[1rem] w-[1rem]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                                    @break
                                @case('tiktok')
                                    <svg class="h-[1.05rem] w-[0.92rem]" viewBox="0 0 448 512" fill="currentColor" aria-hidden="true" preserveAspectRatio="xMidYMid meet"><path d="M448,209.91a210.06,210.06,0,0,1-122.77-39.25V349.38A162.55,162.55,0,1,1,185,188.31V278.2a74.62,74.62,0,1,0,52.23,71.18V0l88,0a121.18,121.18,0,0,0,1.86,22.17h0A122.18,122.18,0,0,0,381,102.39a121.43,121.43,0,0,0,67,20.14Z"/></svg>
                                    @break
                                @case('instagram')
                                    <svg class="h-[1.1rem] w-[1.1rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18.5" height="18.5" x="2.75" y="2.75" rx="5" ry="5"/><circle cx="12" cy="12" r="3.35"/><circle cx="17.35" cy="6.65" r="0.85" fill="currentColor" stroke="none"/></svg>
                                    @break
                                @default
                                    <span class="text-xs font-semibold" aria-hidden="true">↗</span>
                            @endswitch
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Quick links --}}
            <div class="text-center sm:text-right lg:col-span-2">
                <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-400/90">روابط</h4>
                <ul class="mt-4 space-y-2.5 text-sm">
                    <li><a href="{{ route('home') }}" class="text-gray-400 transition-colors hover:text-white">الرئيسية</a></li>
                    <li><a href="{{ route('impact.index') }}" class="text-gray-400 transition-colors hover:text-white">عام الأثر</a></li>
                    <li><a href="{{ route('public.paths.index') }}" class="text-gray-400 transition-colors hover:text-white">المسارات التدريبية</a></li>
                    <li><a href="{{ route('public.programs.index') }}" class="text-gray-400 transition-colors hover:text-white">البرامج التدريبية</a></li>
                    <li><a href="{{ route('public.volunteering.index') }}" class="text-gray-400 transition-colors hover:text-white">الفرص التطوعية</a></li>
                </ul>
            </div>

            {{-- Platform --}}
            <div class="text-center sm:text-right lg:col-span-3">
                <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-400/90">المنصة</h4>
                <ul class="mt-4 space-y-2.5 text-sm">
                    @guest
                    <li><a href="{{ route('login') }}" class="text-gray-400 transition-colors hover:text-white">تسجيل الدخول</a></li>
                    <li><a href="{{ route('register') }}" class="text-gray-400 transition-colors hover:text-white">إنشاء حساب</a></li>
                    @else
                    @if(auth()->user()->canAccessFilamentAdmin())
                    <li><a href="{{ url('/admin') }}" class="text-gray-400 transition-colors hover:text-white">لوحة الإدارة</a></li>
                    @else
                    <li><a href="{{ route('portal.dashboard') }}" class="text-gray-400 transition-colors hover:text-white">بوابتي</a></li>
                    @endif
                    @endguest
                    <li><a href="{{ route('home') }}#faq" class="text-gray-400 transition-colors hover:text-white">الأسئلة الشائعة</a></li>
                    <li><a href="#" class="text-gray-400 transition-colors hover:text-white">سياسة الخصوصية</a></li>
                    <li><a href="#" class="text-gray-400 transition-colors hover:text-white">الشروط والأحكام</a></li>
                </ul>
            </div>

            {{-- Contact --}}
            <div class="text-center sm:text-right lg:col-span-3">
                <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-400/90">تواصل معنا</h4>
                <ul class="mt-4 space-y-3 text-sm">
                    <li class="flex flex-wrap items-center justify-center gap-2 sm:flex-nowrap sm:justify-end">
                        <a href="mailto:{{ $site['contact_email'] }}" class="min-w-0 break-all font-medium text-gray-200 transition-colors hover:text-white" dir="ltr">{{ $site['contact_email'] }}</a>
                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/5" aria-hidden="true">
                            <svg class="h-4 w-4 text-emerald-400/90" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                        </span>
                    </li>
                    <li class="flex flex-wrap items-center justify-center gap-2 sm:flex-nowrap sm:justify-end">
                        <a href="tel:{{ $telHref }}" class="min-w-0 font-medium text-gray-200 transition-colors hover:text-white" dir="ltr">{{ $site['contact_phone_display'] ?? $site['contact_phone_local'] }}</a>
                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/5" aria-hidden="true">
                            <svg class="h-4 w-4 text-emerald-400/90" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Location + map --}}
        <div class="mt-14 border-t border-white/10 pt-14 sm:mt-16 sm:pt-16">
            <div class="mb-8 text-center sm:mb-10 sm:text-right">
                <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400/90">موقع الجمعية</p>
                <h3 class="mt-2 text-2xl font-bold text-white sm:text-3xl">زيارة مقرّ كفاءات</h3>
                <p class="mx-auto mt-2 max-w-2xl text-sm leading-relaxed text-gray-400 sm:mx-0">العنوان وساعات الاستقبال، وخريطة تفاعلية لتحديد الموقع.</p>
            </div>

            <div class="grid items-stretch gap-8 lg:grid-cols-12 lg:gap-10">
                <div class="flex flex-col lg:col-span-7">
                    <div class="overflow-hidden rounded-3xl border border-white/10 bg-gray-950/40 shadow-inner ring-1 ring-white/5">
                        <div
                            id="kafaat-footer-map"
                            class="kafaat-footer-leaflet z-0 h-[min(58vw,320px)] w-full min-h-[240px] sm:h-72 lg:min-h-[280px]"
                            data-lat="{{ $mapLat }}"
                            data-lng="{{ $mapLng }}"
                            data-zoom="{{ $mapZoom }}"
                            role="region"
                            aria-label="خريطة موقع جمعية كفاءات"
                        ></div>
                    </div>
                    <a
                        href="{{ $mapsLink }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-4 inline-flex items-center justify-center gap-2 self-center rounded-2xl border border-emerald-500/30 bg-emerald-500/15 px-5 py-2.5 text-sm font-semibold text-emerald-100 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-emerald-400/50 hover:bg-emerald-500/25 sm:self-end"
                    >
                        <svg class="h-4 w-4 shrink-0 opacity-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                        </svg>
                        التوجيه عبر Google Maps
                    </a>
                </div>

                <div class="flex flex-col gap-6 lg:col-span-5">
                    <div class="rounded-3xl border border-white/10 bg-white/[0.04] p-6 backdrop-blur-sm sm:p-7">
                        <div class="flex items-start justify-center gap-3 sm:justify-end">
                            <div class="min-w-0 flex-1 text-center sm:text-right">
                                <p class="text-xs font-bold uppercase tracking-wider text-emerald-400/90">العنوان</p>
                                <p class="mt-2 text-base font-bold leading-snug text-white">جمعية كفاءات لبناء قدرات الشباب</p>
                                <ul class="mt-3 space-y-1.5 text-sm leading-relaxed text-gray-400">
                                    @foreach($site['address_lines'] ?? [] as $line)
                                        <li>{{ $line }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            <div class="hidden h-12 w-12 shrink-0 rounded-2xl border border-white/10 bg-white/5 sm:flex sm:items-center sm:justify-center" aria-hidden="true">
                                <svg class="h-6 w-6 text-emerald-400/90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-white/10 bg-gradient-to-br from-white/[0.07] to-white/[0.02] p-6 shadow-inner sm:p-7">
                        <p class="text-xs font-bold uppercase tracking-wider text-emerald-400/90">{{ $site['working_hours']['title'] ?? 'ساعات العمل' }}</p>
                        <p class="mt-2 text-sm font-semibold text-gray-200">{{ $site['working_hours']['days'] ?? '' }}</p>
                        <ul class="mt-4 divide-y divide-white/10">
                            @foreach($site['working_hours']['shifts'] ?? [] as $shift)
                                <li class="flex flex-col gap-0.5 py-3.5 first:pt-0 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                                    <span class="text-sm font-semibold text-white">{{ $shift['title'] ?? '' }}</span>
                                    <span class="text-sm tabular-nums text-gray-400" dir="auto">{{ $shift['hours'] ?? '' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        {{-- Bottom bar --}}
        <div class="mt-14 flex flex-col items-center gap-4 border-t border-white/10 pt-8 text-center text-sm text-gray-500 sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:text-right">
            <p class="leading-relaxed">© {{ date('Y') }} كفاءات. جميع الحقوق محفوظة.</p>
            <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 sm:justify-end">
                <a href="#" class="font-medium text-gray-400 transition-colors hover:text-white">سياسة الخصوصية</a>
                <a href="#" class="font-medium text-gray-400 transition-colors hover:text-white">الشروط والأحكام</a>
            </div>
        </div>
    </div>

    @once
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <style>
        .kafaat-footer-leaflet.leaflet-container { font-family: 'IBM Plex Sans Arabic', 'Tajawal', sans-serif; background: #0b1220; }
        .kafaat-footer-leaflet .leaflet-control-attribution {
            background: rgba(15, 23, 42, 0.92);
            color: #94a3b8;
            font-size: 10px;
            line-height: 1.35;
            padding: 4px 8px;
            border-radius: 10px 0 0 0;
            max-width: 100%;
        }
        .kafaat-footer-leaflet .leaflet-control-attribution a { color: #6ee7b7; }
        .kafaat-footer-leaflet .leaflet-bar a {
            background-color: rgba(15, 23, 42, 0.92);
            color: #e2e8f0;
            border-color: rgba(255, 255, 255, 0.12);
        }
        .kafaat-footer-leaflet .leaflet-bar a:hover { background-color: rgba(30, 41, 59, 0.95); color: #fff; }
        .kafaat-footer-leaflet .leaflet-bar a.leaflet-disabled { color: #64748b; }
    </style>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    @endonce
    <script>
        (function () {
            if (window.__kafaatFooterMapInit) return;
            window.__kafaatFooterMapInit = true;

            function startMap() {
                var el = document.getElementById('kafaat-footer-map');
                if (!el || el.getAttribute('data-map-ready') === '1') return;
                if (typeof L === 'undefined') return;

                var lat = parseFloat(el.getAttribute('data-lat') || '0');
                var lng = parseFloat(el.getAttribute('data-lng') || '0');
                var zoom = parseInt(el.getAttribute('data-zoom') || '16', 10);
                if (!lat || !lng) return;

                el.setAttribute('data-map-ready', '1');

                var map = L.map(el, {
                    scrollWheelZoom: false,
                    zoomControl: true,
                    attributionControl: true
                }).setView([lat, lng], zoom);

                L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
                    subdomains: 'abcd',
                    maxZoom: 20
                }).addTo(map);

                L.circleMarker([lat, lng], {
                    radius: 10,
                    fillColor: '#34d399',
                    color: '#ecfdf5',
                    weight: 2.5,
                    opacity: 1,
                    fillOpacity: 0.95
                }).addTo(map);

                requestAnimationFrame(function () { map.invalidateSize(); });
                window.addEventListener('load', function () { map.invalidateSize(); });
                window.addEventListener('resize', function () { map.invalidateSize(); });
            }

            if ('IntersectionObserver' in window) {
                var el = document.getElementById('kafaat-footer-map');
                if (!el) return;
                var io = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            startMap();
                            io.disconnect();
                        }
                    });
                }, { rootMargin: '80px', threshold: 0.05 });
                io.observe(el);
            } else {
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', startMap);
                } else {
                    startMap();
                }
            }
        })();
    </script>
</footer>
