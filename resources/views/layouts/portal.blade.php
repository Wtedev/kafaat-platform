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
        *, *::before, *::after { font-family: 'IBM Plex Sans Arabic', 'Tajawal', sans-serif; }
        html { scroll-behavior: smooth; }
        button:focus-visible, a:focus-visible { outline: 2px solid #253B5B; outline-offset: 3px; border-radius: 8px; }
        #portal-sidebar { transition: transform 0.3s cubic-bezier(.22,1,.36,1); }
    </style>
</head>
<body class="bg-[#F3F7FB] text-[#111827] antialiased">

    {{-- ── Top bar ──────────────────────────────────────────────────────── --}}
    <header class="sticky top-0 z-40 bg-white border-b border-gray-100 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16 gap-4">

            {{-- Mobile sidebar toggle + Logo --}}
            <div class="flex items-center gap-3">
                <button id="portal-sidebar-toggle" aria-label="القائمة"
                        class="lg:hidden p-2 rounded-xl text-gray-500 hover:bg-gray-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <a href="{{ route('home') }}" class="text-xl font-bold tracking-tight" style="color:#253B5B">كفاءات</a>
            </div>

            {{-- User avatar + name + logout --}}
            <div class="flex items-center gap-3">
                <div class="hidden sm:flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl flex items-center justify-center text-sm font-bold text-white flex-shrink-0"
                         style="background:#253B5B">
                        {{ mb_substr(auth()->user()->name, 0, 1) }}
                    </div>
                    <span class="text-sm font-medium" style="color:#111827">{{ auth()->user()->name }}</span>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="text-sm font-medium px-3 py-1.5 rounded-xl transition-colors hover:bg-red-50"
                            style="color:#EF4444">
                        خروج
                    </button>
                </form>
            </div>

        </div>
    </header>

    {{-- Mobile overlay --}}
    <div id="portal-overlay"
         class="fixed inset-0 z-30 bg-black/40 hidden lg:hidden"
         aria-hidden="true"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex gap-7">

        {{-- ── Sidebar (first child = RIGHT in RTL) ──────────────────────── --}}
        <aside id="portal-sidebar"
               class="fixed top-16 right-0 h-[calc(100vh-4rem)] w-64 z-30 translate-x-full overflow-y-auto
                      lg:static lg:translate-x-0 lg:h-auto lg:w-56 lg:shrink-0
                      bg-white border-l border-gray-100 shadow-lg lg:shadow-none lg:border-0">

            <nav class="p-3 lg:bg-white lg:rounded-3xl lg:border lg:border-gray-100 lg:shadow-sm">

                @php
                $navItems = [
                    [
                        'route' => 'portal.dashboard',
                        'label' => 'لوحة التحكم',
                        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
                    ],
                    [
                        'route' => 'portal.paths',
                        'label' => 'المسارات التدريبية',
                        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>',
                    ],
                    [
                        'route' => 'portal.programs',
                        'label' => 'البرامج التدريبية',
                        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>',
                    ],
                    [
                        'route' => 'portal.volunteering',
                        'label' => 'الفرص التطوعية',
                        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>',
                    ],
                    [
                        'route' => 'portal.certificates',
                        'label' => 'شهاداتي',
                        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>',
                    ],
                    [
                        'route' => 'portal.profile',
                        'label' => 'ملفي الشخصي',
                        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>',
                    ],
                ];
                @endphp

                @foreach ($navItems as $item)
                @php $active = request()->routeIs($item['route']); @endphp
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 px-3 py-3 rounded-2xl text-sm font-medium transition-all duration-200 mb-1
                          {{ $active ? 'text-white shadow-sm' : 'text-gray-600 hover:bg-[#EAF2FA] hover:text-[#253B5B]' }}"
                   style="{{ $active ? 'background:#253B5B' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0"
                         fill="none" viewBox="0 0 24 24"
                         stroke="{{ $active ? 'white' : '#6B7280' }}">
                        {!! $item['icon'] !!}
                    </svg>
                    <span>{{ $item['label'] }}</span>
                </a>
                @endforeach

            </nav>
        </aside>
        {{-- / Sidebar --}}

        {{-- ── Main content ────────────────────────────────────────────── --}}
        <main class="flex-1 min-w-0">

            @if (session('success'))
            <div class="mb-4 rounded-2xl border px-4 py-3 text-sm"
                 style="background:#ECFDF5; border-color:#A7F3D0; color:#065F46">
                {{ session('success') }}
            </div>
            @endif

            @if (session('error'))
            <div class="mb-4 rounded-2xl border px-4 py-3 text-sm"
                 style="background:#FEF2F2; border-color:#FECACA; color:#991B1B">
                {{ session('error') }}
            </div>
            @endif

            @yield('content')

        </main>

    </div>

    <script>
        (function () {
            var toggle  = document.getElementById('portal-sidebar-toggle');
            var sidebar = document.getElementById('portal-sidebar');
            var overlay = document.getElementById('portal-overlay');
            if (!toggle) return;

            function openSidebar()  {
                sidebar.classList.remove('translate-x-full');
                overlay.classList.remove('hidden');
            }
            function closeSidebar() {
                sidebar.classList.add('translate-x-full');
                overlay.classList.add('hidden');
            }

            toggle.addEventListener('click', function () {
                sidebar.classList.contains('translate-x-full') ? openSidebar() : closeSidebar();
            });
            overlay.addEventListener('click', closeSidebar);
        })();
    </script>

</body>
</html>
