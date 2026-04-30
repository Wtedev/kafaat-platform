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
                        sans: ['Tajawal', 'sans-serif']
                    }
                , }
            }
        }

    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" />
</head>
<body class="bg-gray-50 font-sans text-gray-800 antialiased">

    {{-- Top bar --}}
    <header class="bg-white border-b border-gray-200 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
            <span class="text-xl font-bold text-indigo-700">كفاءات</span>
            <div class="flex items-center gap-4 text-sm text-gray-600">
                <span>مرحباً، {{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-red-500 hover:text-red-700 transition">تسجيل الخروج</button>
                </form>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex gap-8">

        {{-- Sidebar --}}
        <aside class="w-56 shrink-0">
            <nav class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                @php
                $navItems = [
                ['route' => 'portal.dashboard', 'label' => 'الرئيسية', 'icon' => '🏠'],
                ['route' => 'portal.paths', 'label' => 'مساراتي', 'icon' => '📚'],
                ['route' => 'portal.programs', 'label' => 'برامجي', 'icon' => '🎓'],
                ['route' => 'portal.volunteering', 'label' => 'الفرص التطوعية', 'icon' => '🤝'],
                ['route' => 'portal.certificates', 'label' => 'شهاداتي', 'icon' => '🏆'],
                ['route' => 'portal.profile', 'label' => 'ملفي الشخصي', 'icon' => '👤'],
                ];
                @endphp

                @foreach ($navItems as $item)
                @php $active = request()->routeIs($item['route']); @endphp
                <a href="{{ route($item['route']) }}" class="flex items-center gap-3 px-4 py-3 text-sm font-medium transition
                          {{ $active
                              ? 'bg-indigo-50 text-indigo-700 border-r-4 border-indigo-600'
                              : 'text-gray-700 hover:bg-gray-50' }}">
                    <span>{{ $item['icon'] }}</span>
                    <span>{{ $item['label'] }}</span>
                </a>
                @endforeach
            </nav>
        </aside>

        {{-- Main content --}}
        <main class="flex-1 min-w-0">
            @if (session('success'))
            <div class="mb-4 rounded-xl bg-green-50 border border-green-200 text-green-700 px-4 py-3 text-sm">
                {{ session('success') }}
            </div>
            @endif

            @if (session('error'))
            <div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                {{ session('error') }}
            </div>
            @endif

            @yield('content')
        </main>

    </div>

</body>
</html>
