{{--
    resources/views/components/public-headquarters.blade.php
    Public headquarters / location section — light site background; map: Leaflet + CARTO Voyager.
--}}
@php
    $site = config('site');
    $maps = $site['maps'] ?? [];
    $mapsLink = is_string($maps['link'] ?? null) ? $maps['link'] : '#';
    $mapLat = (float) ($maps['lat'] ?? 26.3676773);
    $mapLng = (float) ($maps['lng'] ?? 43.9288304);
    $mapZoom = (int) ($maps['zoom'] ?? 16);
    $legalName = $site['legal_name'] ?? 'جمعية كفاءات لبناء قدرات الشباب';
    $location = $site['location'] ?? [];
@endphp
<section id="headquarters" class="relative min-w-0 overflow-x-hidden border-t border-gray-100 bg-[#F7FAFC] text-brand-body antialiased" aria-label="مقر الجمعية">
    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 sm:py-16 lg:px-8">
        {{-- Location + map --}}
        <div>
            <div class="mb-8 text-center sm:mb-10 sm:text-right">
                <p class="text-sm font-semibold uppercase tracking-widest text-[#1a9399]">{{ $location['section_label'] ?? 'موقع الجمعية' }}</p>
                <h3 class="mt-2 text-2xl font-bold text-[#335483] sm:text-3xl">{{ $location['heading'] ?? 'مقرّ جمعية كفاءات' }}</h3>
                <p class="mx-auto mt-2 max-w-2xl text-sm leading-relaxed text-[#4B5563] sm:mx-0">{{ $location['subtitle'] ?? '' }}</p>
            </div>

            <div class="grid items-stretch gap-8 lg:grid-cols-12 lg:gap-10">
                <div class="flex flex-col lg:col-span-7">
                    <div class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm ring-1 ring-gray-100/80">
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
                        class="mt-4 inline-flex items-center justify-center gap-2 self-center rounded-2xl border border-[#b8e0e2] bg-[#e6f5f6] px-5 py-2.5 text-sm font-semibold text-[#1a9399] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-[#1a9399]/40 hover:bg-[#d4f0f2] sm:self-end"
                    >
                        <svg class="h-4 w-4 shrink-0 opacity-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                        </svg>
                        {{ $location['directions_label'] ?? 'فتح الموقع في خرائط جوجل' }}
                    </a>
                </div>

                <div class="flex flex-col gap-6 lg:col-span-5">
                    <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm sm:p-7">
                        <div class="flex items-start justify-center gap-3 sm:justify-end">
                            <div class="min-w-0 flex-1 text-center sm:text-right">
                                <p class="text-xs font-bold uppercase tracking-wider text-[#1a9399]">العنوان</p>
                                <p class="mt-2 text-base font-bold leading-snug text-[#335483]">{{ $legalName }}</p>
                                <ul class="mt-3 space-y-1.5 text-sm leading-relaxed text-[#4B5563]">
                                    @foreach($site['address_lines'] ?? [] as $line)
                                        <li>{{ $line }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            <div class="hidden h-12 w-12 shrink-0 rounded-2xl border border-[#c5d4e4] bg-[#e9eff6] sm:flex sm:items-center sm:justify-center" aria-hidden="true">
                                <svg class="h-6 w-6 text-[#335483]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm sm:p-7">
                        <p class="text-xs font-bold uppercase tracking-wider text-[#1a9399]">{{ $site['working_hours']['title'] ?? 'ساعات العمل' }}</p>
                        <p class="mt-2 text-sm font-semibold text-[#335483]">{{ $site['working_hours']['days'] ?? '' }}</p>
                        @if(filled($site['working_hours']['note'] ?? null))
                        <p class="mt-2 text-xs leading-relaxed text-gray-500">{{ $site['working_hours']['note'] }}</p>
                        @endif
                        <ul class="mt-4 divide-y divide-gray-100">
                            @foreach($site['working_hours']['shifts'] ?? [] as $shift)
                                <li class="flex flex-col gap-0.5 py-3.5 first:pt-0 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                                    <span class="text-sm font-semibold text-[#335483]">{{ $shift['title'] ?? '' }}</span>
                                    <span class="text-sm tabular-nums text-[#4B5563]" dir="auto">{{ $shift['hours'] ?? '' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @once
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <style>
        .kafaat-footer-leaflet.leaflet-container { font-family: 'FF Shamel', sans-serif; background: #e9eff6; }
        .kafaat-footer-leaflet .leaflet-control-attribution {
            background: rgba(255, 255, 255, 0.92);
            color: #6B7280;
            font-size: 10px;
            line-height: 1.35;
            padding: 4px 8px;
            border-radius: 10px 0 0 0;
            max-width: 100%;
        }
        .kafaat-footer-leaflet .leaflet-control-attribution a { color: #335483; }
        .kafaat-footer-leaflet .leaflet-bar a {
            background-color: rgba(255, 255, 255, 0.95);
            color: #335483;
            border-color: #e5e7eb;
        }
        .kafaat-footer-leaflet .leaflet-bar a:hover { background-color: #e9eff6; color: #243a55; }
        .kafaat-footer-leaflet .leaflet-bar a.leaflet-disabled { color: #9CA3AF; }
    </style>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    @endonce
    <script>
        (function () {
            function startMap() {
                var el = document.getElementById('kafaat-footer-map');
                if (!el || el.getAttribute('data-map-ready') === '1') return;
                if (typeof L === 'undefined') {
                    window.setTimeout(startMap, 150);
                    return;
                }

                var lat = parseFloat(el.getAttribute('data-lat') || '0');
                var lng = parseFloat(el.getAttribute('data-lng') || '0');
                var zoom = parseInt(el.getAttribute('data-zoom') || '16', 10);
                if (Number.isNaN(lat) || Number.isNaN(lng)) return;

                el.setAttribute('data-map-ready', '1');

                var map = L.map(el, {
                    scrollWheelZoom: false,
                    zoomControl: true,
                    attributionControl: true
                }).setView([lat, lng], zoom);

                L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
                    subdomains: 'abcd',
                    maxZoom: 20
                }).addTo(map);

                L.circleMarker([lat, lng], {
                    radius: 10,
                    fillColor: '#1a9399',
                    color: '#335483',
                    weight: 2.5,
                    opacity: 1,
                    fillOpacity: 0.95
                }).addTo(map);

                requestAnimationFrame(function () { map.invalidateSize(); });
                window.addEventListener('load', function () { map.invalidateSize(); });
                window.addEventListener('resize', function () { map.invalidateSize(); });
            }

            function observeMap() {
                var el = document.getElementById('kafaat-footer-map');
                if (!el) return;

                if ('IntersectionObserver' in window) {
                    var io = new IntersectionObserver(function (entries) {
                        entries.forEach(function (entry) {
                            if (entry.isIntersecting) {
                                startMap();
                                io.disconnect();
                            }
                        });
                    }, { rootMargin: '80px', threshold: 0.05 });
                    io.observe(el);
                    return;
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', startMap);
                } else {
                    startMap();
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', observeMap);
            } else {
                observeMap();
            }
        })();
    </script>
</section>
