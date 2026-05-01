<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>@yield('title', 'كفاءات')</title>

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
    </style>

    @yield('head')
</head>
<body class="bg-[#F7FAFC] text-[#111827] antialiased">

    {{-- ── Navbar ──────────────────────────────────────────────────────── --}}
    <x-public-navbar />
    {{-- Flash messages --}}
    @if (session('success') || session('error'))
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-5">
        @if (session('success'))
        <div class="rounded-2xl border px-4 py-3 text-sm mb-2" style="background:#ECFDF5; border-color:#A7F3D0; color:#065F46">
            {{ session('success') }}
        </div>
        @endif
        @if (session('error'))
        <div class="rounded-2xl border px-4 py-3 text-sm" style="background:#FEF2F2; border-color:#FECACA; color:#991B1B">
            {{ session('error') }}
        </div>
        @endif
    </div>
    @endif

    {{-- Page content --}}
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="mt-16 border-t border-gray-100 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8
                    flex flex-col sm:flex-row items-center justify-between gap-4 text-sm" style="color:#6B7280">
            <a href="{{ route('home') }}" class="font-bold text-base" style="color:#253B5B">كفاءات</a>
            <p>© {{ date('Y') }} كفاءات — جميع الحقوق محفوظة</p>
            <div class="flex items-center gap-5">
                <a href="{{ route('public.paths.index') }}"        class="hover:text-[#253B5B] transition-colors">المسارات</a>
                <a href="{{ route('public.programs.index') }}"     class="hover:text-[#253B5B] transition-colors">البرامج</a>
                <a href="{{ route('public.volunteering.index') }}" class="hover:text-[#253B5B] transition-colors">التطوع</a>
            </div>
        </div>
    </footer>

    @yield('scripts')
</body>
</html>
