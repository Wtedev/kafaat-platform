<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>@yield('title', 'بوابة المستفيد') — كفاءات</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        html {
            scroll-behavior: smooth;
        }

        button:focus-visible,
        a:focus-visible {
            outline: 2px solid #335483;
            outline-offset: 3px;
            border-radius: 8px;
        }

        #portal-sidebar {
            transition: transform 0.3s cubic-bezier(.22, 1, .36, 1);
        }

        .portal-nav-details > summary {
            list-style: none;
        }
        .portal-nav-details > summary::-webkit-details-marker {
            display: none;
        }
        .portal-nav-details[open] .portal-nav-chevron {
            transform: rotate(180deg);
        }

        nav[aria-label="قائمة بوابة المستفيد"] a[aria-current="page"] svg {
            color: #335483;
        }
    </style>
</head>
<body class="min-h-screen bg-[#F7FAFC] text-[#111827] antialiased font-sans">

    <header class="sticky top-0 z-40 border-b border-[#c5d4e4]/60 bg-white/90 shadow-[0_1px_0_rgba(51,84,131,0.06)] backdrop-blur-md">
        <div class="mx-auto flex h-14 max-w-7xl min-w-0 items-center gap-2 px-3 sm:h-16 sm:gap-3 sm:px-6 lg:px-8">
            <div class="flex min-w-0 shrink items-center gap-1.5 sm:gap-3">
                <button id="portal-sidebar-toggle" type="button" aria-controls="portal-sidebar" aria-expanded="false" aria-label="فتح القائمة" class="-me-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-slate-600 transition-colors hover:bg-slate-100/90 active:bg-slate-100 lg:hidden">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <a href="{{ route('home') }}" class="flex min-w-0 items-center" aria-label="كفاءات — الرئيسية">
                    <img src="{{ asset(config('brand.logos.kafaat')) }}" alt="كفاءات" class="h-9 w-auto" width="119" height="36" />
                </a>
            </div>

            <div class="flex min-w-0 flex-1 items-center justify-end gap-1 sm:gap-2 lg:gap-2.5">
                @php $portalHeaderNotifActive = (request()->route()?->getName() ?? '') === 'portal.notifications'; @endphp
                <a
                    href="{{ route('portal.notifications') }}"
                    class="inline-flex h-11 min-w-[2.75rem] shrink-0 items-center justify-center gap-1.5 rounded-xl px-2 text-sm font-medium transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-[#335483]/25 sm:h-auto sm:min-w-0 sm:justify-start sm:gap-2 sm:rounded-2xl sm:px-3.5 sm:py-2 {{ $portalHeaderNotifActive ? 'bg-white text-[#335483] shadow-[0_2px_12px_-2px_rgba(51,84,131,0.15)] ring-1 ring-slate-200/70' : 'text-slate-600 hover:bg-white/80 hover:text-[#335483] hover:shadow-sm hover:ring-1 hover:ring-slate-200/50' }}"
                    aria-label="التنبيهات"
                    @if ($portalHeaderNotifActive) aria-current="page" @endif
                >
                    <span class="relative inline-flex shrink-0">
                        <svg class="h-[1.35rem] w-[1.35rem] sm:h-5 sm:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        @if (($portalInboxUnreadCount ?? 0) > 0)
                        <span class="absolute -end-0.5 -top-0.5 flex h-3.5 min-w-[0.875rem] items-center justify-center rounded-full bg-brand-danger px-0.5 text-[8px] font-bold leading-none text-white ring-2 ring-white sm:-end-1 sm:-top-1 sm:h-4 sm:min-w-[1rem] sm:px-1 sm:text-[9px]">{{ $portalInboxUnreadCount > 99 ? '99+' : $portalInboxUnreadCount }}</span>
                        @endif
                    </span>
                    <span class="hidden sm:inline">التنبيهات</span>
                </a>
                @php $portalNotifSettingsActive = (request()->route()?->getName() ?? '') === 'portal.notifications.settings'; @endphp
                <a
                    href="{{ route('portal.notifications.settings') }}"
                    class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-[#335483]/25 sm:h-auto sm:rounded-2xl sm:px-2.5 sm:py-2 {{ $portalNotifSettingsActive ? 'bg-white text-[#335483] shadow-[0_2px_12px_-2px_rgba(51,84,131,0.15)] ring-1 ring-slate-200/70' : 'text-slate-500 hover:bg-white/80 hover:text-[#335483] hover:shadow-sm hover:ring-1 hover:ring-slate-200/50' }}"
                    aria-label="إعدادات التنبيهات"
                    @if ($portalNotifSettingsActive) aria-current="page" @endif
                >
                    <svg class="h-[1.3rem] w-[1.3rem] sm:h-5 sm:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </a>
                <x-portal.external-nav />
                <div class="hidden min-w-0 items-center gap-2 sm:flex lg:hidden">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold text-white" style="background:#335483">
                        {{ \App\Models\Profile::initialsFromName(auth()->user()->name) }}
                    </div>
                    <span class="max-w-[8rem] truncate text-xs font-medium text-gray-800 sm:max-w-[10rem] sm:text-sm">{{ auth()->user()->name }}</span>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200/80 bg-white/60 text-brand-danger/90 shadow-sm transition-all hover:border-[#f5c4c0] hover:bg-[#fdeeed]/80 hover:shadow sm:h-auto sm:w-auto sm:rounded-2xl sm:px-3.5 sm:py-2 sm:text-sm sm:font-medium" aria-label="تسجيل الخروج">
                        <svg class="h-[1.35rem] w-[1.35rem] sm:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span class="hidden sm:inline">خروج</span>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <div id="portal-overlay" class="fixed inset-0 z-30 hidden bg-black/40 lg:hidden" aria-hidden="true"></div>

    @if (auth()->check() && auth()->user()->notification_prefs_set_at === null)
        @include('partials.notification-prefs-modal')
    @endif

    <div class="mx-auto flex max-w-7xl gap-6 px-4 py-4 sm:px-6 lg:gap-8 lg:px-8 lg:py-6">
        @php
            $rn = request()->route()?->getName() ?? '';
            $isDash = $rn === 'portal.dashboard';
            $isPaths = str_starts_with($rn, 'portal.paths');
            $isPrograms = str_starts_with($rn, 'portal.programs');
            $isVol = $rn === 'portal.volunteering';
            $isCert = $rn === 'portal.certificates';
            $isProfile = str_starts_with($rn, 'portal.profile');
            $isCompetency = str_starts_with($rn, 'portal.competency');
            $navIcon = 'h-[1.125rem] w-[1.125rem] shrink-0 text-slate-500 transition-colors group-hover:text-brand sm:h-[1.15rem] sm:w-[1.15rem]';
            $navActive = 'flex min-h-[2.75rem] items-center gap-3 rounded-2xl bg-white px-3 py-2.5 text-sm font-semibold text-brand shadow-[0_2px_14px_-4px_rgba(51,84,131,0.18)] ring-1 ring-[#c5d4e4]/70 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-brand/30';
            $navIdle = 'group flex min-h-[2.75rem] items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-medium text-slate-600 transition-all hover:bg-brand-light/80 hover:text-brand hover:shadow-sm hover:ring-1 hover:ring-[#c5d4e4]/50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand/20';
            $navSectionSummary = 'flex w-full cursor-pointer list-none items-center justify-between gap-2 rounded-xl px-2 py-2 text-[10px] font-bold tracking-wide text-slate-400 transition-colors hover:text-slate-500';
        @endphp

        <aside id="portal-sidebar" class="fixed right-0 top-14 z-30 h-[calc(100vh-3.5rem)] w-[18rem] translate-x-full overflow-y-auto border-l border-[#c5d4e4]/50 bg-[#F7FAFC]/95 shadow-[8px_0_32px_-8px_rgba(51,84,131,0.12)] backdrop-blur-md sm:top-16 sm:h-[calc(100vh-4rem)] lg:static lg:h-auto lg:w-[17.5rem] lg:translate-x-0 lg:shrink-0 lg:border-0 lg:bg-transparent lg:shadow-none lg:backdrop-blur-none">
            <nav class="flex flex-col lg:sticky lg:top-16 lg:overflow-hidden lg:rounded-3xl lg:border lg:border-[#c5d4e4]/50 lg:bg-white/90 lg:shadow-[0_8px_40px_-12px_rgba(51,84,131,0.12)] lg:ring-1 lg:ring-white/60" aria-label="قائمة بوابة المستفيد">
                <div class="p-4 sm:p-5">
                    <x-portal.sidebar-identity />
                </div>

                <div class="flex flex-col gap-0.5 px-3 pb-4 sm:px-4 sm:pb-5">
                    <p class="px-2 pb-1 text-[10px] font-bold tracking-wide text-slate-400">نظرة عامة</p>
                    <a href="{{ route('portal.dashboard') }}" class="{{ $isDash ? $navActive : $navIdle }}" @if($isDash) aria-current="page" @endif>
                        <svg class="{{ $navIcon }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        <span>الرئيسية</span>
                    </a>

                    <details class="portal-nav-details mt-3 border-t border-slate-100/90 pt-3" open>
                        <summary class="{{ $navSectionSummary }}">
                            <span>التعلّم</span>
                            <svg class="portal-nav-chevron h-3.5 w-3.5 shrink-0 text-slate-300 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </summary>
                        <div class="mt-1 space-y-0.5">
                            <a href="{{ route('portal.programs') }}" class="{{ $isPrograms ? $navActive : $navIdle }}" @if($isPrograms) aria-current="page" @endif>
                                <svg class="{{ $navIcon }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                <span class="min-w-0 flex-1 truncate">البرامج واللقاءات</span>
                            </a>
                            <a href="{{ route('portal.paths') }}" class="{{ $isPaths ? $navActive : $navIdle }}" @if($isPaths) aria-current="page" @endif>
                                <svg class="{{ $navIcon }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                                <span>مساراتي</span>
                            </a>
                        </div>
                    </details>

                    <details class="portal-nav-details mt-2 border-t border-slate-100/90 pt-3" open>
                        <summary class="{{ $navSectionSummary }}">
                            <span>النشاط</span>
                            <svg class="portal-nav-chevron h-3.5 w-3.5 shrink-0 text-slate-300 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </summary>
                        <div class="mt-1 space-y-0.5">
                            <a href="{{ route('portal.volunteering') }}" class="{{ $isVol ? $navActive : $navIdle }}" @if($isVol) aria-current="page" @endif>
                                <svg class="{{ $navIcon }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span class="min-w-0 flex-1 truncate">الفرص التطوعية</span>
                            </a>
                            <a href="{{ route('portal.certificates') }}" class="{{ $isCert ? $navActive : $navIdle }}" @if($isCert) aria-current="page" @endif>
                                <svg class="{{ $navIcon }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                                <span>شهاداتي</span>
                            </a>
                            <a href="{{ route('portal.competency') }}" class="{{ $isCompetency ? $navActive : $navIdle }}" @if($isCompetency) aria-current="page" @endif>
                                <svg class="{{ $navIcon }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span>الكفاءة</span>
                            </a>
                        </div>
                    </details>

                    <details class="portal-nav-details mt-2 border-t border-slate-100/90 pt-3" open>
                        <summary class="{{ $navSectionSummary }}">
                            <span>الحساب</span>
                            <svg class="portal-nav-chevron h-3.5 w-3.5 shrink-0 text-slate-300 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </summary>
                        <div class="mt-1 space-y-0.5">
                            <a href="{{ route('portal.profile') }}" class="{{ $isProfile ? $navActive : $navIdle }}" @if($isProfile) aria-current="page" @endif>
                                <svg class="{{ $navIcon }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                <span>ملفي الشخصي</span>
                            </a>
                        </div>
                    </details>
                </div>
            </nav>
        </aside>

        <main class="min-w-0 flex-1">
            @if (auth()->check() && auth()->user()->isPortalUser() && ! auth()->user()->hasCompletedRequiredIdentityData())
            <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">بيانات حسابك غير مكتملة</p>
                <p class="mt-1">يرجى استكمال الاسم الرباعي ورقم الهوية وتاريخ الميلاد والجوال.</p>
                <a href="{{ route('portal.profile.complete') }}" class="mt-2 inline-block font-medium text-[#335483] hover:underline">استكمال البيانات</a>
            </div>
            @endif

            @if (session('success'))
            <div class="mb-4 rounded-3xl border border-[#b8e0e2] bg-[#e6f5f6]/90 px-4 py-3 text-sm text-brand-secondary shadow-[0_2px_16px_-6px_rgba(26,147,153,0.12)] backdrop-blur-sm">
                {{ session('success') }}
            </div>
            @endif

            @if (session('error'))
            <div class="mb-4 rounded-3xl border border-[#f5c4c0]/60 bg-[#fdeeed]/90 px-4 py-3 text-sm text-brand-danger shadow-[0_2px_16px_-6px_rgba(236,96,86,0.12)] backdrop-blur-sm">
                {{ session('error') }}
            </div>
            @endif

            @yield('content')
        </main>
    </div>

    <script>
        (function() {
            var toggle = document.getElementById('portal-sidebar-toggle');
            var sidebar = document.getElementById('portal-sidebar');
            var overlay = document.getElementById('portal-overlay');
            if (!toggle || !sidebar || !overlay) return;

            function openSidebar() {
                sidebar.classList.remove('translate-x-full');
                overlay.classList.remove('hidden');
                toggle.setAttribute('aria-expanded', 'true');
                toggle.setAttribute('aria-label', 'إغلاق القائمة');
            }

            function closeSidebar() {
                sidebar.classList.add('translate-x-full');
                overlay.classList.add('hidden');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.setAttribute('aria-label', 'فتح القائمة');
            }

            toggle.addEventListener('click', function() {
                sidebar.classList.contains('translate-x-full') ? openSidebar() : closeSidebar();
            });
            overlay.addEventListener('click', closeSidebar);
            sidebar.querySelectorAll('a[href]').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.matchMedia('(max-width: 1023px)').matches) {
                        closeSidebar();
                    }
                });
            });
        })();
    </script>

    @stack('scripts')
</body>
</html>
