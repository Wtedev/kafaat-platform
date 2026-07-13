<!DOCTYPE html>
<html lang="ar-SA-u-nu-latn" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>@yield('title', 'بوابة التحضير') — كفاءات</title>
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
    @stack('head')
</head>
<body class="min-h-screen antialiased font-sans" style="background: linear-gradient(160deg, #EEF5FB 0%, #F7FAFC 45%, #e8eef6 100%)">
    <div class="min-h-screen flex flex-col">
        <header class="px-4 pt-6 pb-2">
            <div class="mx-auto max-w-lg text-center">
                <img src="{{ asset(config('brand.logos.kafaat')) }}" alt="كفاءات" class="h-12 w-auto mx-auto" width="160" height="48" />
                <p class="mt-2 text-xs font-semibold tracking-wide text-[#335483]/بوابة التحضير</p>
            </div>
        </header>

        <main class="flex-1 px-4 pb-10">
            <div class="mx-auto w-full @yield('container_width', 'max-w-lg')">
                @if (session('success'))
                    <div class="mb-4 rounded-2xl border px-4 py-3 text-sm {{ config('brand.classes.alert_success') }}">
                        {{ session('success') }}
                    </div>
                @endif
                @yield('content')
            </div>
        </main>
    </div>
    @stack('scripts')
</body>
</html>
