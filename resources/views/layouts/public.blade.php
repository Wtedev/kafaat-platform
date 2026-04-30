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
                        sans: ['Tajawal', 'sans-serif']
                    }
                }
            }
        }

    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" />
</head>
<body class="bg-gray-50 font-sans text-gray-800 antialiased">

    {{-- Header --}}
    <header class="bg-white border-b border-gray-200 shadow-sm sticky top-0 z-30">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">

            <a href="{{ route('home') }}" class="text-xl font-bold text-indigo-700 tracking-tight">كفاءات</a>

            <nav class="hidden sm:flex items-center gap-6 text-sm font-medium text-gray-600">
                <a href="{{ route('public.paths.index') }}" class="hover:text-indigo-600 transition {{ request()->routeIs('public.paths.*')          ? 'text-indigo-600' : '' }}">المسارات التعليمية</a>
                <a href="{{ route('public.programs.index') }}" class="hover:text-indigo-600 transition {{ request()->routeIs('public.programs.*')       ? 'text-indigo-600' : '' }}">البرامج التدريبية</a>
                <a href="{{ route('public.volunteering.index') }}" class="hover:text-indigo-600 transition {{ request()->routeIs('public.volunteering.*')   ? 'text-indigo-600' : '' }}">الفرص التطوعية</a>
            </nav>

            <div class="flex items-center gap-3 text-sm">
                @auth
                <a href="{{ route('portal.dashboard') }}" class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition text-xs">
                    بوابتي
                </a>
                @else
                <a href="{{ route('login') }}" class="text-gray-600 hover:text-indigo-600 transition">تسجيل الدخول</a>
                @endauth
            </div>

        </div>
    </header>

    {{-- Flash messages --}}
    @if (session('success') || session('error'))
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
        @if (session('success'))
        <div class="rounded-xl bg-green-50 border border-green-200 text-green-700 px-4 py-3 text-sm">
            {{ session('success') }}
        </div>
        @endif
        @if (session('error'))
        <div class="rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
            {{ session('error') }}
        </div>
        @endif
    </div>
    @endif

    {{-- Page content --}}
    <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="mt-16 border-t border-gray-200 bg-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-center text-xs text-gray-400">
            © {{ date('Y') }} كفاءات — جميع الحقوق محفوظة
        </div>
    </footer>

</body>
</html>
