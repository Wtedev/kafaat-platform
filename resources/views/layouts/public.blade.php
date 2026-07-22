<!DOCTYPE html>
<html lang="ar-SA-u-nu-latn" dir="rtl" class="no-js">
<head>
    <script>document.documentElement.classList.replace('no-js', 'js');</script>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>@yield('title', 'كفاءات')</title>
    <meta name="description" content="@yield('meta_description', 'جمعية كفاءات لبناء قدرات الشباب — أخبار الجمعية، برامجها التدريبية، فرص التطوع، والحوكمة.')" />
    <meta property="og:title" content="@yield('title', 'كفاءات')" />
    <meta property="og:description" content="@yield('meta_description', 'جمعية كفاءات لبناء قدرات الشباب — أخبار الجمعية وبرامجها وفرص التطوع.')" />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="{{ url()->current() }}" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        html {
            scroll-behavior: smooth;
        }

        button:focus-visible,
        a:focus-visible {
            outline: 2px solid var(--brand-primary, #335483);
            outline-offset: 3px;
            border-radius: 8px;
        }

    </style>

    @yield('head')
</head>
<body class="bg-[#F7FAFC] text-brand-body antialiased font-sans">

    {{-- ── Navbar ──────────────────────────────────────────────────────── --}}
    <x-public-navbar />
    {{-- Flash messages --}}
    @if (session('success') || session('error'))
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-5">
        @if (session('success'))
        <div class="rounded-2xl border px-4 py-3 text-sm mb-2 {{ config('brand.classes.alert_success') }}">
            {{ session('success') }}
        </div>
        @endif
        @if (session('error'))
        <div class="rounded-2xl border px-4 py-3 text-sm {{ config('brand.classes.alert_danger') }}">
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
    <x-public-footer />

    <x-support-ticket-fab />

    @yield('scripts')
</body>
</html>
