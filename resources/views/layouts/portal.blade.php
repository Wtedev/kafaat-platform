<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>@yield('title', 'بوابة المستفيد') — كفاءات</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['IBM Plex Sans Arabic', 'Tajawal', 'sans-serif']
                    }
                }
            }
        }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet" />

    <style>
        *,
        *::before,
        *::after {
            font-family: 'IBM Plex Sans Arabic', 'Tajawal', sans-serif;
        }

        html {
            scroll-behavior: smooth;
        }

        button:focus-visible,
        a:focus-visible {
            outline: 2px solid #253B5B;
            outline-offset: 3px;
            border-radius: 8px;
        }

        #portal-sidebar {
            transition: transform 0.3s cubic-bezier(.22, 1, .36, 1);
        }
    </style>
</head>
<body class="min-h-screen bg-[#F0F4F8] text-[#111827] antialiased">

    <header class="sticky top-0 z-40 border-b border-gray-200/80 bg-white/95 shadow-sm backdrop-blur-sm">
        <div class="mx-auto flex h-14 max-w-7xl items-center justify-between gap-4 px-4 sm:h-16 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <button id="portal-sidebar-toggle" type="button" aria-label="القائمة" class="rounded-xl p-2 text-gray-500 transition-colors hover:bg-gray-100 lg:hidden">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <a href="{{ route('home') }}" class="text-lg font-bold tracking-tight sm:text-xl" style="color:#253B5B">كفاءات</a>
            </div>

            <div class="flex max-w-[min(100%,42rem)] flex-wrap items-center justify-end gap-2 sm:max-w-none sm:gap-3">
                <x-portal.external-nav />
                <div class="hidden items-center gap-2 sm:flex lg:hidden">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold text-white" style="background:#253B5B">
                        {{ \App\Models\Profile::initialsFromName(auth()->user()->name) }}
                    </div>
                    <span class="max-w-[8rem] truncate text-xs font-medium text-gray-800 sm:max-w-[10rem] sm:text-sm">{{ auth()->user()->name }}</span>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-xl px-3 py-1.5 text-sm font-medium text-red-600 transition-colors hover:bg-red-50">
                        خروج
                    </button>
                </form>
            </div>
        </div>
    </header>

    <div id="portal-overlay" class="fixed inset-0 z-30 hidden bg-black/40 lg:hidden" aria-hidden="true"></div>

    <div class="mx-auto flex max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:gap-8 lg:px-8 lg:py-8">
        @php
            $rn = request()->route()?->getName() ?? '';
            $isDash = $rn === 'portal.dashboard';
            $isPaths = str_starts_with($rn, 'portal.paths');
            $isPrograms = str_starts_with($rn, 'portal.programs');
            $isVol = $rn === 'portal.volunteering';
            $isCert = $rn === 'portal.certificates';
            $isProfile = str_starts_with($rn, 'portal.profile');
            $isCompetency = $rn === 'portal.competency';
            $navActive = 'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition-colors shadow-sm';
            $navIdle = 'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-600 transition-colors hover:bg-white hover:text-[#253B5B]';
        @endphp

        <aside id="portal-sidebar" class="fixed right-0 top-14 z-30 h-[calc(100vh-3.5rem)] w-[17rem] translate-x-full overflow-y-auto border-l border-gray-200/80 bg-white shadow-xl sm:top-16 sm:h-[calc(100vh-4rem)] lg:static lg:h-auto lg:w-64 lg:translate-x-0 lg:shrink-0 lg:border-0 lg:bg-transparent lg:shadow-none">
            <nav class="space-y-5 p-3 lg:sticky lg:top-24 lg:rounded-2xl lg:border lg:border-gray-100 lg:bg-white lg:p-3 lg:shadow-sm">
                <x-portal.sidebar-identity />

                <div>
                    <a href="{{ route('portal.dashboard') }}" class="{{ $isDash ? $navActive . ' text-white' : $navIdle }}" @if($isDash) style="background:#253B5B" @endif>
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        <span>الرئيسية</span>
                    </a>
                </div>

                <div>
                    <p class="mb-1 px-3 text-[10px] font-bold uppercase tracking-wider text-gray-400">التعلّم</p>
                    <a href="{{ route('portal.programs') }}" class="{{ $isPrograms ? $navActive . ' text-white' : $navIdle }} mb-0.5" @if($isPrograms) style="background:#253B5B" @endif>
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        <span>البرامج واللقاءات</span>
                    </a>
                    <a href="{{ route('portal.paths') }}" class="{{ $isPaths ? $navActive . ' text-white' : $navIdle }}" @if($isPaths) style="background:#253B5B" @endif>
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                        <span>مساراتي</span>
                    </a>
                </div>

                <div>
                    <p class="mb-1 px-3 text-[10px] font-bold uppercase tracking-wider text-gray-400">النشاط</p>
                    <a href="{{ route('portal.volunteering') }}" class="{{ $isVol ? $navActive . ' text-white' : $navIdle }} mb-0.5" @if($isVol) style="background:#253B5B" @endif>
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span>الفرص التطوعية</span>
                    </a>
                    <a href="{{ route('portal.certificates') }}" class="{{ $isCert ? $navActive . ' text-white' : $navIdle }} mb-0.5" @if($isCert) style="background:#253B5B" @endif>
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                        <span>شهاداتي</span>
                    </a>
                    <a href="{{ route('portal.competency') }}" class="{{ $isCompetency ? $navActive . ' text-white' : $navIdle }}" @if($isCompetency) style="background:#253B5B" @endif>
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span>الكفاءة</span>
                    </a>
                </div>

                <div>
                    <p class="mb-1 px-3 text-[10px] font-bold uppercase tracking-wider text-gray-400">الحساب</p>
                    <a href="{{ route('portal.profile') }}" class="{{ $isProfile ? $navActive . ' text-white' : $navIdle }}" @if($isProfile) style="background:#253B5B" @endif>
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <span>ملفي الشخصي</span>
                    </a>
                </div>
            </nav>
        </aside>

        <main class="min-w-0 flex-1">
            @if (session('success'))
            <div class="mb-4 rounded-2xl border px-4 py-3 text-sm" style="background:#ECFDF5; border-color:#A7F3D0; color:#065F46">
                {{ session('success') }}
            </div>
            @endif

            @if (session('error'))
            <div class="mb-4 rounded-2xl border px-4 py-3 text-sm" style="background:#FEF2F2; border-color:#FECACA; color:#991B1B">
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
            }

            function closeSidebar() {
                sidebar.classList.add('translate-x-full');
                overlay.classList.add('hidden');
            }

            toggle.addEventListener('click', function() {
                sidebar.classList.contains('translate-x-full') ? openSidebar() : closeSidebar();
            });
            overlay.addEventListener('click', closeSidebar);
        })();
    </script>
</body>
</html>
