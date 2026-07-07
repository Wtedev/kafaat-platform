{{--
    resources/views/components/public-navbar.blade.php
    Shared public navbar — used on the standalone homepage AND all public layout pages.
--}}
@php
use App\Support\CompetencyTrackCatalog;

$aboutHref = request()->routeIs('home') ? '#about' : route('home') . '#about';
$hasGovernance = Route::has('public.governance.index');
$hasRegulations = Route::has('public.regulations.index');
$hasMedia = Route::has('public.media.index');
$programTrackOrder = CompetencyTrackCatalog::order();
$programTrackMeta = config('competency_tracks.tracks', []);
$programsActive = request()->routeIs('public.programs.*') || request()->routeIs('public.tracks.*');
$brand = config('brand');
$govTabs = $hasGovernance
    ? array_merge([
        'board' => 'أعضاء مجلس الإدارة',
        'general_assembly' => 'أعضاء الجمعية العمومية',
        'standing_committees' => 'اللجان الدائمة',
    ], \App\Models\GovernanceDocument::TYPES)
    : [];
$govActive = request()->routeIs('public.governance.*');
@endphp

<style>
    .pub-nav-link {
        position: relative;
        display: inline-flex;
        align-items: center;
        transition: color 0.22s ease, transform 0.22s ease;
    }

    .pub-nav-link:hover {
        color: #335483 !important;
        transform: translateY(-2px);
    }

    .pub-nav-link::after {
        content: '';
        position: absolute;
        bottom: -6px;
        right: 0;
        left: 0;
        height: 2px;
        border-radius: 9999px;
        background: #335483;
        transform: scaleX(0);
        transform-origin: center;
        transition: transform 0.25s ease;
    }

    .pub-nav-link:hover::after,
    .pub-nav-link.is-active::after {
        transform: scaleX(1);
    }

    .pub-nav-link.is-active {
        color: #335483 !important;
        font-weight: 600;
    }

    .pub-nav-dropdown:hover .pub-nav-dropdown-panel,
    .pub-nav-dropdown:focus-within .pub-nav-dropdown-panel {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .pub-nav-dropdown-panel {
        opacity: 0;
        visibility: hidden;
        transform: translateY(8px);
        transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s;
    }

    .pub-nav-dropdown-item {
        transition: background 0.18s ease, color 0.18s ease, padding-inline-start 0.18s ease;
    }

    .pub-nav-dropdown-item:hover {
        background: #e9eff6;
        color: #335483;
        padding-inline-start: 1.25rem;
    }

    .pub-nav-dropdown-item--track {
        margin-inline: 0.5rem;
        padding: 0.55rem 0.85rem;
        border-radius: 0.55rem;
        border-inline-start: 3px solid var(--track-color);
        font-size: 0.875rem;
        font-weight: 500;
        color: #374151;
    }

    .pub-nav-dropdown-item--track:hover {
        color: var(--track-color) !important;
        background: color-mix(in srgb, var(--track-color) 8%, white);
        padding-inline-start: 0.85rem;
    }

    .pub-nav-dropdown-item--track.is-active {
        color: var(--track-color) !important;
        font-weight: 600;
        background: color-mix(in srgb, var(--track-color) 10%, white);
    }

    .pub-nav-programs-panel {
        min-width: 15rem;
    }

    .pub-nav-mobile-link {
        transition: background 0.18s ease, color 0.18s ease, transform 0.18s ease;
    }

    .pub-nav-mobile-link:hover {
        transform: translateX(-3px);
    }
</style>

<header id="pub-nav" class="sticky top-0 z-50 bg-white/95 backdrop-blur-sm border-b border-slate-100 shadow-sm transition-shadow duration-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 gap-6">

            {{-- Logo --}}
            <a href="{{ route('home') }}" class="flex-shrink-0 flex items-center transition-transform duration-200 hover:-translate-y-0.5" aria-label="كفاءات — الرئيسية">
                <img
                    src="{{ asset($brand['logos']['kafaat']) }}"
                    alt="كفاءات"
                    class="h-10 w-auto"
                    width="132"
                    height="40"
                />
            </a>

            {{-- Desktop Nav --}}
            <nav class="hidden lg:flex items-center gap-5 xl:gap-6 text-sm font-medium" style="color:#6B7280">

                <a href="{{ $aboutHref }}" class="pub-nav-link {{ request()->routeIs('home') && !request()->has('page') ? '' : '' }}">
                    عن كفاءات
                </a>

                <a href="{{ route('public.paths.index') }}" class="pub-nav-link {{ request()->routeIs('public.paths.*') ? 'is-active' : '' }}">
                    المسارات
                </a>

                <div class="pub-nav-dropdown group relative">
                    <button type="button" class="pub-nav-link gap-1 {{ $programsActive ? 'is-active' : '' }}" aria-haspopup="true">
                        البرامج
                        <svg class="w-3.5 h-3.5 opacity-60 transition-transform duration-200 group-hover:rotate-180 group-focus-within:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div class="pub-nav-dropdown-panel pub-nav-programs-panel absolute top-full start-0 z-50 mt-3 rounded-2xl border border-gray-100 bg-white py-2 shadow-xl">
                        <a href="{{ route('public.tracks.index') }}" class="pub-nav-dropdown-item block px-4 py-2.5 text-sm font-semibold text-[#335483]">
                            عن المسارات
                        </a>
                        <div class="my-1 border-t border-gray-100"></div>
                        @foreach ($programTrackOrder as $trackKey)
                            @php
                            $track = \App\Enums\CompetencyTrack::from($trackKey);
                            $tMeta = $programTrackMeta[$trackKey] ?? [];
                            $trackColor = $tMeta['color'] ?? '#335483';
                            $isActiveTrack = request()->routeIs('public.programs.track') && request()->route('track')?->value === $trackKey;
                            @endphp
                            <a href="{{ route('public.programs.track', $track) }}"
                               class="pub-nav-dropdown-item pub-nav-dropdown-item--track block text-right {{ $isActiveTrack ? 'is-active' : '' }}"
                               style="--track-color: {{ $trackColor }}">
                                {{ $track->shortLabel() }}
                            </a>
                        @endforeach
                    </div>
                </div>

                <a href="{{ route('public.volunteering.index') }}" class="pub-nav-link {{ request()->routeIs('public.volunteering.*') ? 'is-active' : '' }}">
                    الفرص التطوعية
                </a>

                @if($hasGovernance)
                <div class="pub-nav-dropdown group relative">
                    <button type="button" class="pub-nav-link gap-1 {{ $govActive ? 'is-active' : '' }}" aria-haspopup="true">
                        الحوكمة
                        <svg class="w-3.5 h-3.5 opacity-60 transition-transform duration-200 group-hover:rotate-180 group-focus-within:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div class="pub-nav-dropdown-panel absolute top-full start-0 z-50 mt-3 min-w-[15rem] rounded-2xl border border-gray-100 bg-white py-2 shadow-xl">
                        <a href="{{ route('public.governance.index') }}" class="pub-nav-dropdown-item block px-4 py-2.5 text-sm font-semibold text-[#335483]">
                            نظرة عامة
                        </a>
                        <div class="my-1 border-t border-gray-100"></div>
                        @foreach($govTabs as $key => $label)
                        <a href="{{ route('public.governance.index') }}#{{ $key }}" class="pub-nav-dropdown-item block px-4 py-2 text-sm text-gray-600">
                            {{ $label }}
                        </a>
                        @endforeach
                    </div>
                </div>
                @endif

                @if($hasRegulations)
                <a href="{{ route('public.regulations.index') }}" class="pub-nav-link {{ request()->routeIs('public.regulations.*') ? 'is-active' : '' }}">
                    اللوائح والأنظمة
                </a>
                @endif

                @if($hasMedia)
                <a href="{{ route('public.media.index') }}" class="pub-nav-link {{ request()->routeIs('public.media.*') ? 'is-active' : '' }}">
                    المركز الإعلامي
                </a>
                @endif

            </nav>

            {{-- Desktop Auth --}}
            <div class="hidden lg:flex items-center gap-3 flex-shrink-0">
                @auth
                @if(auth()->user()->canAccessFilamentAdmin())
                <a href="{{ url('/admin') }}" class="px-5 py-2 rounded-2xl text-sm font-semibold text-white shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200" style="background:#335483">لوحة الإدارة</a>
                @else
                <a href="{{ route('portal.dashboard') }}" class="px-5 py-2 rounded-2xl text-sm font-semibold text-white shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200" style="background:#335483">حسابي</a>
                @endif
                @else
                <a href="{{ route('login') }}" class="px-5 py-2 rounded-2xl text-sm font-medium transition-all duration-200 hover:bg-[#e9eff6] hover:-translate-y-0.5" style="color:#335483">تسجيل الدخول</a>
                <a href="{{ route('register') }}" class="px-5 py-2 rounded-2xl text-sm font-semibold text-white shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200" style="background:#335483">إنشاء حساب</a>
                @endauth
            </div>

            {{-- Mobile Hamburger --}}
            <button id="pub-hamburger" aria-label="قائمة التنقل" class="lg:hidden p-2 rounded-xl text-gray-500 hover:bg-gray-100 transition-colors flex-shrink-0">
                <svg id="pub-ham-open" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                <svg id="pub-ham-close" class="w-6 h-6 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>

        </div>
    </div>

    {{-- Mobile Menu --}}
    <div id="pub-mobile-nav" class="hidden lg:hidden border-t border-gray-100 bg-white shadow-lg">
        <nav class="max-w-7xl mx-auto px-4 py-4 flex flex-col gap-1">
            <a href="{{ $aboutHref }}" class="pub-nav-mobile-link px-4 py-2.5 rounded-xl text-sm font-medium text-gray-700 hover:bg-[#e9eff6] hover:text-[#335483] text-right">عن كفاءات</a>

            <a href="{{ route('public.paths.index') }}" class="pub-nav-mobile-link px-4 py-2.5 rounded-xl text-sm font-medium text-gray-700 hover:bg-[#e9eff6] hover:text-[#335483] text-right">المسارات</a>

            <details class="group rounded-xl">
                <summary class="pub-nav-mobile-link flex cursor-pointer list-none items-center justify-between px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-[#e9eff6] hover:text-[#335483] text-right [&::-webkit-details-marker]:hidden">
                    <span>البرامج</span>
                    <svg class="w-4 h-4 opacity-50 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </summary>
                <div class="mt-1 space-y-0.5 pe-2">
                    <a href="{{ route('public.tracks.index') }}" class="pub-nav-mobile-link block rounded-lg px-6 py-2 text-sm font-semibold text-[#335483] hover:bg-[#e9eff6] text-right">عن المسارات</a>
                    @foreach ($programTrackOrder as $trackKey)
                        @php
                            $track = \App\Enums\CompetencyTrack::from($trackKey);
                            $tMeta = $programTrackMeta[$trackKey] ?? [];
                            $trackColor = $tMeta['color'] ?? '#335483';
                            $isActiveTrack = request()->routeIs('public.programs.track') && request()->route('track')?->value === $trackKey;
                        @endphp
                        <a href="{{ route('public.programs.track', $track) }}"
                           class="pub-nav-mobile-link block rounded-lg border-s-[3px] px-6 py-2 text-sm hover:bg-[#e9eff6] text-right {{ $isActiveTrack ? 'font-semibold' : 'text-gray-600' }}"
                           style="border-color: {{ $trackColor }}; {{ $isActiveTrack ? 'color:'.$trackColor : '' }}">
                            {{ $track->shortLabel() }}
                        </a>
                    @endforeach
                </div>
            </details>
            <a href="{{ route('public.volunteering.index') }}" class="pub-nav-mobile-link px-4 py-2.5 rounded-xl text-sm font-medium text-gray-700 hover:bg-[#e9eff6] hover:text-[#335483] text-right">الفرص التطوعية</a>

            @if($hasGovernance)
            <details class="group rounded-xl">
                <summary class="pub-nav-mobile-link flex cursor-pointer list-none items-center justify-between px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-[#e9eff6] hover:text-[#335483] text-right [&::-webkit-details-marker]:hidden">
                    <span>الحوكمة</span>
                    <svg class="w-4 h-4 opacity-50 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </summary>
                <div class="mt-1 space-y-0.5 pe-2">
                    <a href="{{ route('public.governance.index') }}" class="pub-nav-mobile-link block rounded-lg px-6 py-2 text-sm font-semibold text-[#335483] hover:bg-[#e9eff6] text-right">نظرة عامة</a>
                    @foreach($govTabs as $key => $label)
                    <a href="{{ route('public.governance.index') }}#{{ $key }}" class="pub-nav-mobile-link block rounded-lg px-6 py-2 text-sm text-gray-600 hover:bg-[#e9eff6] hover:text-[#335483] text-right">{{ $label }}</a>
                    @endforeach
                </div>
            </details>
            @endif

            @if($hasRegulations)
            <a href="{{ route('public.regulations.index') }}" class="pub-nav-mobile-link px-4 py-2.5 rounded-xl text-sm font-medium text-gray-700 hover:bg-[#e9eff6] hover:text-[#335483] text-right">اللوائح والأنظمة</a>
            @endif

            @if($hasMedia)
            <a href="{{ route('public.media.index') }}" class="pub-nav-mobile-link px-4 py-2.5 rounded-xl text-sm font-medium text-gray-700 hover:bg-[#e9eff6] hover:text-[#335483] text-right">المركز الإعلامي</a>
            @endif

            @auth
            @if(auth()->user()->canAccessFilamentAdmin())
            <a href="{{ url('/admin') }}" class="mt-3 px-4 py-2.5 rounded-xl text-sm font-semibold text-white text-center" style="background:#335483">لوحة الإدارة</a>
            @else
            <a href="{{ route('portal.dashboard') }}" class="mt-3 px-4 py-2.5 rounded-xl text-sm font-semibold text-white text-center" style="background:#335483">حسابي</a>
            @endif
            @else
            <div class="mt-3 flex gap-2">
                <a href="{{ route('login') }}" class="flex-1 px-4 py-2.5 rounded-xl text-sm font-medium text-center border-2 transition-colors hover:bg-[#e9eff6]" style="color:#335483; border-color:#335483">تسجيل الدخول</a>
                <a href="{{ route('register') }}" class="flex-1 px-4 py-2.5 rounded-xl text-sm font-semibold text-white text-center" style="background:#335483">إنشاء حساب</a>
            </div>
            @endauth
        </nav>
    </div>
</header>

<script>
    (function() {
        var btn = document.getElementById('pub-hamburger');
        var menu = document.getElementById('pub-mobile-nav');
        var ico = document.getElementById('pub-ham-open');
        var icoc = document.getElementById('pub-ham-close');
        if (btn) {
            btn.addEventListener('click', function() {
                var open = !menu.classList.contains('hidden');
                menu.classList.toggle('hidden', open);
                ico.classList.toggle('hidden', !open);
                icoc.classList.toggle('hidden', open);
            });
        }
        var nav = document.getElementById('pub-nav');
        if (nav) {
            window.addEventListener('scroll', function() {
                nav.style.boxShadow = window.scrollY > 10 ?
                    '0 4px 24px rgba(51,84,131,0.08)' :
                    '0 1px 3px rgba(0,0,0,0.06)';
            }, {
                passive: true
            });
        }
    })();
</script>
