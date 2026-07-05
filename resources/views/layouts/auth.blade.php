<!DOCTYPE html>
<html lang="ar-SA-u-nu-latn" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>@yield('title', 'كفاءات')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        button:focus-visible,
        a:focus-visible,
        input:focus-visible {
            outline: 2px solid #335483;
            outline-offset: 3px;
            border-radius: 8px;
        }

    </style>
</head>
<body class="min-h-screen antialiased font-sans flex items-center justify-center py-12 px-4" style="background: linear-gradient(150deg, #EEF5FB 0%, #F3F7FB 55%, #e9eff6 100%)">

    <div class="w-full @yield('container_width', 'max-w-md')">

        {{-- Logo --}}
        <div class="text-center mb-8">
            <a href="{{ route('home') }}" class="inline-flex justify-center" aria-label="كفاءات — الرئيسية">
                <img src="{{ asset(config('brand.logos.kafaat')) }}" alt="كفاءات" class="h-14 w-auto mx-auto" width="185" height="56" />
            </a>
            <p class="text-sm mt-3" style="color:#6B7280">جمعية كفاءات لبناء قدرات الشباب</p>
        </div>

        {{-- Card --}}
        <div class="bg-white rounded-3xl shadow-xl border border-white/80 p-8">
            @yield('content')
        </div>

        {{-- Back to home --}}
        <div class="text-center mt-6">
            <a href="{{ route('home') }}" class="text-sm transition-colors hover:underline" style="color:#6B7280">
                ← العودة للرئيسية
            </a>
        </div>

    </div>

</body>
</html>
