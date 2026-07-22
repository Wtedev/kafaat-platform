{{--
    resources/views/public/home.blade.php
    Kafaat Platform — Public Homepage (standalone, does NOT extend layouts.public)
--}}
<!DOCTYPE html>
<html lang="ar-SA-u-nu-latn" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>جمعية كفاءات — بناء قدرات الشباب</title>
    <meta name="description" content="جمعية كفاءات لبناء قدرات الشباب: تعرّف على رسالتنا ورؤيتنا، برامجنا التدريبية، فرص التطوع، وأخبارنا وفعالياتنا." />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        html {
            scroll-behavior: smooth;
        }

        /* Multi-line text truncation */
        .clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* FAQ accordion */
        .faq-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.35s ease;
        }

        .faq-body.open {
            max-height: 500px;
        }

        .faq-chevron {
            transition: transform 0.3s ease;
        }

        .faq-chevron.open {
            transform: rotate(45deg);
        }

        /* Subtle focus ring for accessibility */
        button:focus-visible,
        a:focus-visible {
            outline: 2px solid #335483;
            outline-offset: 3px;
            border-radius: 8px;
        }

        /* ── Scroll reveal ─────────────────────────────────────────────── */
        .reveal-fade {
            opacity: 0;
            transform: translateY(28px);
            transition: opacity 0.7s cubic-bezier(.22, 1, .36, 1),
                transform 0.7s cubic-bezier(.22, 1, .36, 1);
        }

        .reveal-fade.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .stat-counter {
            display: inline-block;
            min-width: 2.5ch;
            font-variant-numeric: tabular-nums;
        }

        .stat-item {
            opacity: 0;
            transform: translateY(12px);
            transition: opacity 0.65s cubic-bezier(.22, 1, .36, 1),
                transform 0.65s cubic-bezier(.22, 1, .36, 1);
        }

        #kafaat-stats.is-visible .stat-item {
            opacity: 1;
            transform: translateY(0);
        }

        #kafaat-stats.is-visible .stat-item:nth-child(1) { transition-delay: 0.05s; }
        #kafaat-stats.is-visible .stat-item:nth-child(2) { transition-delay: 0.12s; }
        #kafaat-stats.is-visible .stat-item:nth-child(3) { transition-delay: 0.19s; }
        #kafaat-stats.is-visible .stat-item:nth-child(4) { transition-delay: 0.26s; }
        #kafaat-stats.is-visible .stat-item:nth-child(5) { transition-delay: 0.33s; }
        #kafaat-stats.is-visible .stat-item:nth-child(6) { transition-delay: 0.4s; }

        .vm-card {
            transition: transform 0.35s cubic-bezier(.22, 1, .36, 1),
                box-shadow 0.35s cubic-bezier(.22, 1, .36, 1);
        }

        .news-slider-track {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .news-slider-track::-webkit-scrollbar {
            display: none;
        }

        .vm-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 24px 48px rgba(37, 59, 91, 0.12);
        }

        /* ── Homepage hero ─────────────────────────────────────────────── */
        .home-hero {
            --hero-navy: #243a55;
            --hero-brand: #335483;
            --hero-teal: #1a9399;
            position: relative;
            isolation: isolate;
            min-height: min(92vh, 820px);
            display: flex;
            align-items: flex-end;
            overflow: hidden;
            color: #fff;
            /* Abstract teal hero art — section background only (not foreground content) */
            background-color: var(--hero-navy);
            background-image: url("{{ asset('images/home/hero-year-of-impact.png') }}");
            background-size: cover;
            background-position: left center;
            background-repeat: no-repeat;
        }

        .home-hero__media {
            position: absolute;
            inset: 0;
            z-index: 0;
            pointer-events: none;
        }

        .home-hero__veil {
            position: absolute;
            inset: 0;
            /* Soft scrim only — keep teal gradients vivid; right side is already dark in the art */
            background:
                linear-gradient(105deg,
                    rgba(12, 40, 48, 0.04) 0%,
                    rgba(12, 40, 48, 0.08) 40%,
                    rgba(18, 42, 58, 0.32) 72%,
                    rgba(20, 38, 55, 0.48) 100%),
                linear-gradient(180deg,
                    transparent 0%,
                    transparent 48%,
                    rgba(18, 42, 58, 0.28) 100%);
        }

        .home-hero__glow {
            position: absolute;
            width: 42vw;
            max-width: 480px;
            height: 42vw;
            max-height: 480px;
            border-radius: 50%;
            right: -8%;
            bottom: -18%;
            background: radial-gradient(circle, rgba(26, 147, 153, 0.28) 0%, transparent 68%);
            pointer-events: none;
            animation: home-hero-glow 7s ease-in-out infinite alternate;
        }

        .home-hero__content {
            position: relative;
            z-index: 1;
            width: 100%;
            padding: clamp(5.5rem, 12vh, 7.5rem) 0 clamp(3.25rem, 8vh, 5.5rem);
        }

        .home-hero__brand {
            opacity: 0;
            transform: translateY(18px);
            animation: home-hero-rise 0.85s cubic-bezier(.22, 1, .36, 1) 0.12s forwards;
        }

        .home-hero__brand-rule {
            width: 2.75rem;
            height: 3px;
            border-radius: 9999px;
            background: linear-gradient(90deg, var(--hero-teal), rgba(26, 147, 153, 0.15));
            transform-origin: right center;
            animation: home-hero-rule 0.9s cubic-bezier(.22, 1, .36, 1) 0.45s both;
        }

        .home-hero__copy {
            opacity: 0;
            transform: translateY(22px);
            animation: home-hero-rise 0.9s cubic-bezier(.22, 1, .36, 1) 0.28s forwards;
        }

        /* Single-line slogan; fluid clamp keeps it fitting without touching other type. */
        .home-hero__headline {
            letter-spacing: normal;
            line-height: 1.08;
            padding-inline: 0.15em;
            white-space: nowrap;
            font-size: clamp(1.35rem, 3.6vw + 0.55rem, 3.35rem);
        }

        @media (max-width: 360px) {
            .home-hero__headline {
                white-space: normal;
            }
        }

        .home-hero__actions {
            opacity: 0;
            transform: translateY(18px);
            animation: home-hero-rise 0.9s cubic-bezier(.22, 1, .36, 1) 0.48s forwards;
        }

        .home-hero__cta-primary {
            background: #fff;
            color: var(--hero-brand);
            box-shadow: 0 10px 28px rgba(16, 30, 48, 0.22);
        }

        .home-hero__cta-primary:hover {
            background: #f3f7fb;
            transform: translateY(-2px);
            box-shadow: 0 14px 32px rgba(16, 30, 48, 0.28);
        }

        .home-hero__cta-secondary {
            color: #fff;
            border: 1.5px solid rgba(255, 255, 255, 0.55);
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(6px);
        }

        .home-hero__cta-secondary:hover {
            background: rgba(255, 255, 255, 0.14);
            border-color: rgba(255, 255, 255, 0.85);
            transform: translateY(-2px);
        }

        @keyframes home-hero-rise {
            from { opacity: 0; transform: translateY(22px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes home-hero-rule {
            from { transform: scaleX(0); opacity: 0; }
            to { transform: scaleX(1); opacity: 1; }
        }

        @keyframes home-hero-glow {
            from { opacity: 0.55; transform: translateY(0); }
            to { opacity: 1; transform: translateY(-12px); }
        }

        @media (max-width: 1023px) {
            .home-hero {
                min-height: min(88vh, 720px);
                align-items: flex-end;
                background-image: url("{{ asset('images/home/hero-year-of-impact-mobile.png') }}");
                background-position: center top;
            }

            .home-hero__veil {
                background:
                    linear-gradient(180deg,
                        transparent 0%,
                        transparent 42%,
                        rgba(18, 42, 58, 0.28) 68%,
                        rgba(16, 34, 48, 0.52) 100%);
            }

            .home-hero__glow {
                width: 70vw;
                height: 70vw;
                right: -20%;
                bottom: -10%;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .home-hero__brand,
            .home-hero__brand-rule,
            .home-hero__copy,
            .home-hero__actions,
            .home-hero__glow {
                animation: none !important;
                opacity: 1;
                transform: none;
            }
        }

        /* ── Partners marquee ──────────────────────────────────────────── */
        .partners-marquee {
            position: relative;
            overflow: hidden;
            -webkit-mask-image: linear-gradient(90deg, transparent 0%, #000 6%, #000 94%, transparent 100%);
            mask-image: linear-gradient(90deg, transparent 0%, #000 6%, #000 94%, transparent 100%);
        }

        .partners-marquee__track {
            display: flex;
            width: max-content;
            animation: partners-marquee-scroll 42s linear infinite;
            will-change: transform;
        }

        .partners-marquee:hover .partners-marquee__track,
        .partners-marquee:focus-within .partners-marquee__track {
            animation-play-state: paused;
        }

        .partners-marquee__group {
            display: flex;
            align-items: stretch;
            gap: 1rem;
            flex-shrink: 0;
            padding-inline-end: 1rem;
        }

        .partners-marquee__card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 9.5rem;
            padding: 0.5rem 0.5rem;
            border: none;
            border-radius: 0;
            background: transparent;
            box-shadow: none;
            text-decoration: none;
            transition: transform 0.2s ease;
            flex-shrink: 0;
        }

        a.partners-marquee__card:hover {
            transform: translateY(-2px);
        }

        /* Logos as uploaded — object-contain only, no blend/filter/tiles. */
        .partners-marquee__card img {
            display: block;
            max-height: 3rem;
            width: auto;
            max-width: 100%;
            height: auto;
            object-fit: contain;
            object-position: center;
        }

        @keyframes partners-marquee-scroll {
            from { transform: translateX(0); }
            to { transform: translateX(-50%); }
        }

        @media (min-width: 640px) {
            .partners-marquee__group {
                gap: 1.25rem;
                padding-inline-end: 1.25rem;
            }

            .partners-marquee__card {
                width: 11rem;
                padding: 0.65rem 0.65rem;
            }

            .partners-marquee__card img {
                max-height: 3.5rem;
            }
        }

        @media (min-width: 1024px) {
            .partners-marquee__track {
                animation-duration: 55s;
            }

            .partners-marquee__card {
                width: 12rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .partners-marquee {
                -webkit-mask-image: none;
                mask-image: none;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
            }

            .partners-marquee::-webkit-scrollbar {
                display: none;
            }

            .partners-marquee__track {
                animation: none;
                padding-inline: 0.25rem;
            }

            .partners-marquee__group[data-marquee-clone] {
                display: none;
            }
        }

        /* ── Annual report banner ──────────────────────────────────────── */
        .annual-report-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            min-height: 3.25rem;
            width: 100%;
            padding: 0.9rem 1.35rem;
            border-radius: 1rem;
            color: #fff;
            font-size: 0.95rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.22);
            transition: background 0.2s ease, border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        }

        .annual-report-cta:hover {
            background: rgba(255, 255, 255, 0.16);
            border-color: rgba(255, 255, 255, 0.4);
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.18);
        }

        @media (min-width: 768px) {
            .annual-report-cta {
                width: 10rem;
                height: 10rem;
                flex-direction: column;
                min-height: 0;
                padding: 1.25rem;
                border-radius: 1.5rem;
            }
        }

    </style>
</head>
<body class="bg-[#F7FAFC] text-[#111827] antialiased font-sans">

    @php
    $homeAboutHref = request()->routeIs('home') ? '#about' : route('home') . '#about';
    $selfTrackMeta = config('competency_tracks.tracks.self', []);
    $selfTrackColor = $selfTrackMeta['color'] ?? config('brand.secondary');
    $selfTrackBg = $selfTrackMeta['bg_tint'] ?? config('brand.secondary_light');
    @endphp

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 1. NAVBAR                                                           --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <x-public-navbar />

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

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 2. HERO SECTION                                                     --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section class="home-hero" aria-label="مقدمة الصفحة">
        <div class="home-hero__media" aria-hidden="true">
            {{-- Section CSS background-image; light veil keeps copy readable without muddying the teal --}}
            <div class="home-hero__veil"></div>
            <div class="home-hero__glow"></div>
        </div>

        <div class="home-hero__content">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                {{-- Content on the RTL start (visual right) — clear of left/top baked title --}}
                <div class="max-w-2xl text-right">

                    <div class="home-hero__brand mb-6">
                        <img
                            src="{{ asset('images/home/hero-year-of-impact-badge.png') }}"
                            alt="عام الأثر 2026 — The year of impact"
                            class="h-12 sm:h-14 lg:h-16 w-auto max-w-full"
                            width="339"
                            height="99"
                        />
                        <div class="home-hero__brand-rule mt-4" aria-hidden="true"></div>
                    </div>

                    <div class="home-hero__copy">
                        <h1 class="home-hero__headline font-extrabold text-white mb-5">
                            نمكن الشباب. ونصنع الأثر
                        </h1>
                        <p class="text-sm sm:text-base leading-relaxed text-white/70 max-w-md mb-8">
                            نؤهّل الشباب ونوسّع مشاركتهم المجتمعية عبر برامج تدريبية وفرص تطوعية وشراكات مستدامة.
                        </p>
                    </div>

                    <div class="home-hero__actions flex flex-wrap gap-3 sm:gap-4">
                        <a href="#programs" class="home-hero__cta-primary inline-flex items-center justify-center px-7 py-3.5 rounded-2xl text-base font-semibold transition-all duration-200">
                            استكشف برامجنا
                        </a>
                        <a href="{{ $homeAboutHref }}" class="home-hero__cta-secondary inline-flex items-center justify-center px-7 py-3.5 rounded-2xl text-base font-semibold transition-all duration-200">
                            عن الجمعية
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </section>


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 3. ABOUT SECTION                                                    --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <x-public.about-section />


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 4. STATISTICS SECTION                                               --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section id="kafaat-stats" class="py-6 px-4 sm:px-6">
        <div class="max-w-7xl mx-auto">
            <div class="rounded-3xl py-16 px-8 sm:px-14" style="background: linear-gradient(135deg, #243a55 0%, #335483 60%, #3d6589 100%)">

                {{-- Decorative circles --}}
                <div class="relative overflow-hidden rounded-3xl">
                    <div class="absolute -top-10 -left-8 w-48 h-48 rounded-full bg-white opacity-5"></div>
                    <div class="absolute bottom-0 right-1/4 w-64 h-64 rounded-full opacity-5" style="background:#335483"></div>
                </div>

                <div class="relative z-10 text-center mb-12">
                    <h2 class="text-3xl font-bold text-white mb-2">أرقام كفاءات للسنة الماضية</h2>
                    <p class="text-sm" style="color:rgba(255,255,255,0.65)">نتائج نعتز بها</p>
                </div>

                @php
                $stats = [
                    ['count' => 2497, 'prefix' => '+', 'suffix' => '', 'label' => 'مستفيد', 'icon' => 'users'],
                    ['count' => 18, 'prefix' => '', 'suffix' => '+', 'label' => 'برامج تدريبية', 'icon' => 'academic-cap'],
                    ['count' => 274, 'prefix' => '', 'suffix' => '', 'label' => 'جهة مستفيدة', 'icon' => 'building'],
                    ['count' => 20, 'prefix' => '', 'suffix' => '', 'label' => 'جهات داعمة', 'icon' => 'handshake'],
                    ['count' => 124, 'prefix' => '', 'suffix' => '', 'label' => 'فرص تطوعية', 'icon' => 'heart-hand'],
                    ['count' => 1, 'prefix' => '+', 'suffix' => ' مليون', 'label' => 'الظهور الإعلامي', 'icon' => 'eye'],
                ];
                @endphp

                <div class="relative z-10 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-8">
                    @foreach($stats as $stat)
                    <div class="stat-item text-center">
                        <div class="mx-auto mb-3 flex h-9 w-9 items-center justify-center text-white/90" aria-hidden="true">
                            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                @switch($stat['icon'])
                                    @case('users')
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                        @break
                                    @case('academic-cap')
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" />
                                        @break
                                    @case('building')
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                        @break
                                    @case('handshake')
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m11 17 2 2a1 1 0 1 0 3-3" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l.47.28a2 2 0 0 0 1.42.25L21 4" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 3 1 11h-2" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3 2 14l6.5 6.5a1 1 0 1 0 3-3" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h8" />
                                        @break
                                    @case('heart-hand')
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                                        @break
                                    @case('eye')
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        @break
                                @endswitch
                            </svg>
                        </div>
                        <div
                            class="stat-counter text-4xl font-bold text-white mb-1"
                            data-stat-count="{{ $stat['count'] }}"
                            data-stat-prefix="{{ $stat['prefix'] }}"
                            data-stat-suffix="{{ $stat['suffix'] }}"
                            aria-label="{{ $stat['prefix'] }}{{ number_format($stat['count']) }}{{ $stat['suffix'] }}"
                        >{{ $stat['prefix'] }}0{{ $stat['suffix'] }}</div>
                        <div class="text-sm" style="color:rgba(255,255,255,0.65)">{{ $stat['label'] }}</div>
                    </div>
                    @endforeach
                </div>

            </div>
        </div>
    </section>


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 6. WORK AREAS SECTION                                               --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section id="work" class="py-20 bg-[#F7FAFC] scroll-mt-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <p class="text-sm font-semibold uppercase tracking-widest mb-3" style="color:#1a9399">أنشطة كفاءات</p>
                <h2 class="text-3xl sm:text-4xl font-bold mb-4" style="color:#111827">ماذا نقدم</h2>
                <p class="text-lg leading-relaxed max-w-2xl mx-auto" style="color:#6B7280">
                    مسارات تأهيلية وبرامج تدريبية وفرص تطوعية تُسهم في بناء قدرات الشباب وتمكينهم من المشاركة المجتمعية.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @php
                $workAreas = [
                [
                'title' => 'تدريب سند',
                'badge' => 'قريباً',
                'desc' => 'برنامج تأهيلي متخصّص يُطلَق قريباً لدعم الشباب في مساراتهم المهنية والمجتمعية.',
                'href' => null,
                'color' => '#4f53a3',
                'bg' => '#ededf7',
                'soon' => true,
                ],
                [
                'title' => 'البرامج',
                'badge' => null,
                'desc' => 'برامج منظّمة ضمن مسارات الكفاءة الذاتية والمهنية والمجتمعية.',
                'href' => route('home').'#programs',
                'color' => $selfTrackColor,
                'bg' => $selfTrackBg,
                'soon' => false,
                ],
                [
                'title' => 'الفرص التطوعية',
                'badge' => null,
                'desc' => 'فرص للمشاركة في العمل التطوعي وخدمة المجتمع ضمن مبادرات الجمعية المعتمدة.',
                'href' => route('public.volunteering.index'),
                'color' => '#ec6056',
                'bg' => '#fdeeed',
                'soon' => false,
                ],
                ];
                @endphp
                @foreach($workAreas as $area)
                @if ($area['href'] && ! ($area['soon'] ?? false))
                <a href="{{ $area['href'] }}" class="vm-card block rounded-3xl border border-gray-100 bg-white p-6 text-right shadow-sm hover:shadow-md transition-all">
                    @else
                    <div class="vm-card block rounded-3xl border border-dashed border-gray-200 bg-white/80 p-6 text-right shadow-sm opacity-90">
                        @endif
                        <div class="flex items-center justify-end gap-2 mb-4">
                            @if (! empty($area['badge']))
                            <span class="text-[10px] font-bold uppercase tracking-wide px-2.5 py-1 rounded-lg bg-[#ededf7] text-brand-sanad">{{ $area['badge'] }}</span>
                            @endif
                            <div class="w-12 h-12 rounded-2xl flex items-center justify-center" style="background:{{ $area['bg'] }}">
                                <span class="w-3 h-3 rounded-full" style="background:{{ $area['color'] }}"></span>
                            </div>
                        </div>
                        <h3 class="text-lg font-bold mb-2" style="color:#111827">{{ $area['title'] }}</h3>
                        <p class="text-sm leading-relaxed" style="color:#6B7280">{{ $area['desc'] }}</p>
                        @if ($area['href'] && ! ($area['soon'] ?? false))
                </a>
                @else
            </div>
            @endif
            @endforeach
        </div>
        </div>
    </section>


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 7. COMPETENCY TRACKS / PROGRAMS                                     --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section id="programs" class="scroll-mt-24 bg-[#F7FAFC] py-20 sm:py-24">
        <div class="mx-auto max-w-[94rem] px-4 sm:px-6 lg:px-10">
            <x-public.competency-tracks-section :programCounts="$programCounts" :trackPrograms="$trackPrograms" />
        </div>
    </section>


    {{-- removed old "about platform" section --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 8. NEWS & EVENTS SECTION                                            --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section id="news" class="scroll-mt-24 py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-10 flex flex-row items-end justify-between gap-4">
                <div class="text-right">
                    <p class="mb-1 text-sm font-semibold" style="color:#1a9399">آخر التحديثات</p>
                    <h2 class="text-2xl font-bold" style="color:#111827">الأخبار والفعاليات</h2>
                </div>
                <a href="{{ route('public.news.index') }}" class="inline-flex shrink-0 items-center gap-1.5 text-sm font-semibold hover:underline" style="color:#335483">
                    عرض كل الأخبار
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                </a>
            </div>

            @php
            $newsBgs = config('brand.image_gradients');
            @endphp

            @if ($news->isEmpty())
            <div class="bg-white rounded-3xl border border-dashed border-gray-200 p-10 text-center" style="color:#6B7280">
                لا توجد أخبار منشورة حالياً.
            </div>
            @else
            <div class="relative">
                <button type="button" id="news-slider-prev" class="absolute top-1/2 z-10 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 bg-white text-[#335483] shadow-md transition hover:bg-[#e9eff6] end-0 sm:flex" aria-label="الأقدم">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <button type="button" id="news-slider-next" class="absolute top-1/2 z-10 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 bg-white text-[#335483] shadow-md transition hover:bg-[#e9eff6] start-0 sm:flex" aria-label="الأحدث">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>

                <div id="news-slider" dir="rtl" class="news-slider-track flex snap-x snap-mandatory gap-6 overflow-x-auto scroll-smooth pb-2 pe-1 ps-1 sm:pe-12 sm:ps-12">
                    @foreach ($news as $i => $item)
                    <a href="{{ route('public.news.show', $item->slug) }}" data-news-card class="group block w-[min(100%,320px)] shrink-0 snap-start overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-xl sm:w-[320px]">
                        @if ($item->image)
                        <div class="h-48 overflow-hidden">
                            <img src="{{ $item->imagePublicUrl() }}" alt="{{ $item->title }}" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy">
                        </div>
                        @else
                        <div class="flex h-48 items-center justify-center" style="background: {{ $newsBgs[$i % count($newsBgs)] }}">
                            <svg class="h-12 w-12 opacity-25" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color:#335483">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" /></svg>
                        </div>
                        @endif
                        <div class="p-6 text-right">
                            <div class="mb-3 flex items-center justify-between gap-2">
                                @if ($item->category)
                                <x-news-category-badge :category="$item->category" />
                                @else
                                <span></span>
                                @endif
                                @if ($item->published_at)
                                <span class="text-xs shrink-0" style="color:#6B7280">{{ $item->published_at->format('Y/m/d') }}</span>
                                @endif
                            </div>
                            <h3 class="mb-2 line-clamp-2 text-base font-bold transition-colors group-hover:text-[#335483]" style="color:#111827">{{ $item->title }}</h3>
                            @if ($item->excerpt)
                            <p class="line-clamp-3 text-sm" style="color:#6B7280">{{ $item->excerpt }}</p>
                            @endif
                        </div>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </section>


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 8. ANNUAL REPORT SECTION                                            --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section class="py-6 sm:py-8 px-4 sm:px-6" aria-labelledby="annual-report-heading">
        <div class="mx-auto max-w-7xl">
            <div class="relative overflow-hidden rounded-2xl sm:rounded-3xl" style="background: linear-gradient(135deg, #111827 0%, #335483 60%, #2a4566 100%)">

                <div class="pointer-events-none absolute -start-10 -top-10 h-40 w-40 rounded-full bg-white opacity-5 sm:h-56 sm:w-56" aria-hidden="true"></div>
                <div class="pointer-events-none absolute -bottom-16 end-1/4 h-48 w-48 rounded-full opacity-5 sm:h-72 sm:w-72" style="background:#335483" aria-hidden="true"></div>

                <div class="relative z-10 flex flex-col gap-7 px-5 py-9 sm:gap-9 sm:px-10 sm:py-12 md:flex-row md:items-center md:justify-between lg:gap-12 lg:px-16 lg:py-14">

                    <div class="min-w-0 flex-1 text-right">
                        <div class="mb-1 text-5xl font-black leading-none sm:text-7xl lg:text-8xl" style="color:rgba(255,255,255,0.12)" aria-hidden="true">2025</div>
                        <h2 id="annual-report-heading" class="mb-3 text-2xl font-bold text-white sm:text-3xl">التقرير السنوي</h2>
                        <p class="max-w-md text-sm leading-relaxed sm:text-base" style="color:rgba(255,255,255,0.7)">
                            تقرير شامل يرصد إنجازات جمعية كفاءات خلال عام 2025: برامجها التدريبية، عملها التطوعي، وأثرها المجتمعي.
                        </p>
                    </div>

                    <div class="w-full shrink-0 md:w-auto">
                        <a
                            href="{{ asset('reports/annual-report-2025.pdf') }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="annual-report-cta"
                            aria-label="عرض التقرير السنوي 2025"
                        >
                            <svg class="h-6 w-6 shrink-0 opacity-95 sm:h-9 sm:w-9" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <span>عرض التقرير</span>
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </section>


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 9. PARTNERS SECTION                                                 --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section class="overflow-hidden bg-white py-16 sm:py-20" dir="rtl" aria-labelledby="partners-heading">
        <div class="mx-auto mb-10 max-w-7xl px-4 text-center sm:mb-12 sm:px-6 lg:px-8">
            <p class="mb-2 text-sm font-semibold" style="color:#1a9399">شركاء النجاح</p>
            <h2 id="partners-heading" class="text-2xl font-bold sm:text-3xl" style="color:#111827">شركاؤنا</h2>
            <p class="mx-auto mt-3 max-w-xl text-sm leading-relaxed" style="color:#6B7280">مؤسسات وشركات نفتخر بشراكتها معنا في بناء قدرات الشباب.</p>
        </div>

        @if ($partners->isEmpty())
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl rounded-3xl border border-dashed border-gray-200 bg-[#F7FAFC] px-6 py-14 text-center text-sm" style="color:#6B7280">
                سيتم عرض شعارات الشركاء هنا عند إضافتهم من لوحة التحكم.
            </div>
        </div>
        @else
        @php
            $partnerItems = $partners->values();
            // Repeat enough times so the marquee always feels continuous on wide screens.
            $marqueeFill = max(1, (int) ceil(10 / max(1, $partnerItems->count())));
            $marqueePartners = collect();
            for ($i = 0; $i < $marqueeFill; $i++) {
                $marqueePartners = $marqueePartners->concat($partnerItems);
            }
        @endphp
        <div class="partners-marquee" dir="ltr" aria-label="شريط شركاء الجمعية">
            <div class="partners-marquee__track">
                @foreach ([false, true] as $isClone)
                <div class="partners-marquee__group" @if ($isClone) data-marquee-clone="true" aria-hidden="true" @endif>
                    @foreach ($marqueePartners as $partner)
                    @php
                        $logoUrl = $partner->logoPublicUrl();
                        $hasLink = filled($partner->website_url) && ! $isClone;
                    @endphp
                    @if ($logoUrl)
                    @if ($hasLink)
                    <a
                        href="{{ $partner->website_url }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="partners-marquee__card"
                        dir="rtl"
                    >
                        <img src="{{ $logoUrl }}" alt="{{ $partner->name }}" loading="lazy" decoding="async" />
                    </a>
                    @else
                    <div class="partners-marquee__card" dir="rtl" @if ($isClone) tabindex="-1" @endif>
                        <img src="{{ $logoUrl }}" alt="{{ $isClone ? '' : $partner->name }}" loading="lazy" decoding="async" />
                    </div>
                    @endif
                    @endif
                    @endforeach
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </section>


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 10. FAQ SECTION                                                     --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section id="faq" class="scroll-mt-24 py-20" style="background:#F3F7FB">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="text-center mb-12">
                <p class="text-sm font-semibold uppercase tracking-widest mb-2" style="color:#1a9399">لديك سؤال؟</p>
                <h2 class="text-3xl font-bold" style="color:#111827">الأسئلة الشائعة</h2>
            </div>

            @php
            $faqs = [
            ['q' => 'ما هي جمعية كفاءات؟',
            'a' => 'جمعية أهلية غير ربحية تُعنى ببناء قدرات الشباب وتأهيلهم للمشاركة المجتمعية، عبر برامج تدريبية وفرص تطوعية وشراكات مؤسسية.'],
            ['q' => 'من يستفيد من برامج الجمعية؟',
            'a' => 'تستهدف الجمعية الشباب والشابات الراغبين في تنمية مهاراتهم والمشاركة في العمل التطوعي والمجتمعي، وفق شروط كل برنامج أو فرصة.'],
            ['q' => 'كيف أشارك في التطوع؟',
            'a' => 'تصفّح قسم «الفرص التطوعية»، اختر الفرصة المناسبة لك، وقدّم طلب التسجيل. سيتواصل معك فريق الجمعية بعد مراجعة الطلب.'],
            ['q' => 'كيف أسجّل في برنامج تدريبي؟',
            'a' => 'من صفحة «البرامج» اختر البرنامج أو المسار المناسب، ثم اتبع خطوات التسجيل. بعض البرامج تتطلب إنشاء حساب ومتابعة حالة الطلب.'],
            ['q' => 'أين مقر الجمعية؟',
            'a' => 'مقرّنا في بريدة — القصيم. تجد العنوان التفصيلي وساعات العمل وخريطة الموقع في أسفل الصفحة ضمن «تواصل معنا».'],
            ['q' => 'كيف أتابع أخبار الجمعية؟',
            'a' => 'من قسم «الأخبار والفعاليات» في الموقع، أو عبر حسابات الجمعية على منصات التواصل الاجتماعي المذكورة في التذييل.'],
            ];
            @endphp

            <div class="space-y-3">
                @foreach($faqs as $idx => $faq)
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    {{-- Button: text first (right in RTL), icon second (left in RTL) --}}
                    <button onclick="toggleFaq({{ $idx }})" class="w-full flex items-center justify-between px-6 py-5 hover:bg-gray-50 transition-colors cursor-pointer">
                        <span id="faq-q-{{ $idx }}" class="font-semibold text-base text-right flex-1 leading-snug" style="color:#111827">{{ $faq['q'] }}</span>
                        <svg id="faq-icon-{{ $idx }}" class="faq-chevron w-5 h-5 flex-shrink-0 ms-4" style="color:#6B7280" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </button>
                    <div id="faq-body-{{ $idx }}" class="faq-body">
                        <div class="px-6 pb-5 pt-1 text-right">
                            <p class="text-sm leading-relaxed" style="color:#6B7280">{{ $faq['a'] }}</p>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

        </div>
    </section>


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 11. FOOTER                                                          --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <x-public-footer />


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- JavaScript                                                          --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <script>
        // ── FAQ accordion ───────────────────────────────────────────────
        function toggleFaq(idx) {
            var body = document.getElementById('faq-body-' + idx);
            var icon = document.getElementById('faq-icon-' + idx);
            var isOpen = body.classList.contains('open');

            // Close all
            document.querySelectorAll('.faq-body').forEach(function(el) {
                el.classList.remove('open');
            });
            document.querySelectorAll('.faq-chevron').forEach(function(el) {
                el.classList.remove('open');
            });

            // Toggle current
            if (!isOpen) {
                body.classList.add('open');
                icon.classList.add('open');
            }
        }

        // ── News slider (يمين → يسار) ─────────────────────────────────
        (function() {
            var track = document.getElementById('news-slider');
            if (!track) return;

            var cards = Array.from(track.querySelectorAll('[data-news-card]'));
            if (cards.length === 0) return;

            var prevBtn = document.getElementById('news-slider-prev');
            var nextBtn = document.getElementById('news-slider-next');
            var index = 0;
            var timer = null;

            function canScroll() {
                return track.scrollWidth > track.clientWidth + 4;
            }

            function toggleControls() {
                var show = canScroll() && cards.length > 1;
                [prevBtn, nextBtn].forEach(function(btn) {
                    if (!btn) return;
                    btn.style.visibility = show ? 'visible' : 'hidden';
                    btn.style.pointerEvents = show ? 'auto' : 'none';
                });
            }

            function scrollToIndex(nextIndex) {
                index = ((nextIndex % cards.length) + cards.length) % cards.length;
                var card = cards[index];
                var trackRect = track.getBoundingClientRect();
                var cardRect = card.getBoundingClientRect();
                track.scrollBy({
                    left: cardRect.left - trackRect.left,
                    behavior: 'smooth',
                });
            }

            function syncIndexFromScroll() {
                if (!canScroll()) {
                    index = 0;
                    return;
                }
                var trackRect = track.getBoundingClientRect();
                var trackCenter = trackRect.right - track.clientWidth / 2;
                var closest = 0;
                var closestDist = Infinity;
                cards.forEach(function(card, i) {
                    var rect = card.getBoundingClientRect();
                    var cardCenter = rect.right - rect.width / 2;
                    var dist = Math.abs(cardCenter - trackCenter);
                    if (dist < closestDist) {
                        closestDist = dist;
                        closest = i;
                    }
                });
                index = closest;
            }

            function advance() {
                if (!canScroll()) return;
                scrollToIndex(index >= cards.length - 1 ? 0 : index + 1);
            }

            var sectionVisible = true;

            function startAuto() {
                stopAuto();
                if (sectionVisible && canScroll() && cards.length > 1) {
                    timer = setInterval(advance, 5000);
                }
            }

            function stopAuto() {
                if (timer) {
                    clearInterval(timer);
                    timer = null;
                }
            }

            if (prevBtn) {
                prevBtn.addEventListener('click', function() {
                    scrollToIndex(index + 1);
                    startAuto();
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function() {
                    scrollToIndex(index - 1);
                    startAuto();
                });
            }

            track.addEventListener('scroll', syncIndexFromScroll, {
                passive: true
            });
            track.addEventListener('mouseenter', stopAuto);
            track.addEventListener('mouseleave', startAuto);
            track.addEventListener('focusin', stopAuto);
            track.addEventListener('focusout', startAuto);
            window.addEventListener('resize', function() {
                toggleControls();
                if (!canScroll()) stopAuto();
                else startAuto();
            });

            var newsSection = document.getElementById('news');
            if (newsSection && 'IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function(entries) {
                    sectionVisible = entries[0].isIntersecting;
                    if (sectionVisible) {
                        startAuto();
                    } else {
                        stopAuto();
                    }
                }, { threshold: 0.15 });
                observer.observe(newsSection);
            }

            track.scrollLeft = 0;
            toggleControls();
            startAuto();
        })();

        // ── Stats counter (IntersectionObserver) ───────────────────────
        (function() {
            var section = document.getElementById('kafaat-stats');
            if (!section) return;

            var counters = section.querySelectorAll('[data-stat-count]');
            if (!counters.length) return;

            function easeOutQuart(t) {
                return 1 - Math.pow(1 - t, 4);
            }

            function formatStatValue(value) {
                return Math.round(value).toLocaleString('en-US');
            }

            function renderStat(el, value) {
                var prefix = el.dataset.statPrefix || '';
                var suffix = el.dataset.statSuffix || '';
                el.textContent = prefix + formatStatValue(value) + suffix;
            }

            function animateStat(el, delay) {
                var target = parseFloat(el.dataset.statCount);
                var duration = 2600;
                var startTime = null;

                window.setTimeout(function() {
                    function step(timestamp) {
                        if (startTime === null) startTime = timestamp;
                        var progress = Math.min((timestamp - startTime) / duration, 1);
                        var eased = easeOutQuart(progress);
                        renderStat(el, target * eased);

                        if (progress < 1) {
                            window.requestAnimationFrame(step);
                        } else {
                            renderStat(el, target);
                        }
                    }

                    window.requestAnimationFrame(step);
                }, delay);
            }

            var hasAnimated = false;
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (!entry.isIntersecting || hasAnimated) return;

                    hasAnimated = true;
                    section.classList.add('is-visible');

                    counters.forEach(function(el, index) {
                        renderStat(el, 0);
                        animateStat(el, index * 110);
                    });

                    observer.disconnect();
                });
            }, { threshold: 0.3 });

            observer.observe(section);
        })();

        // ── Scroll reveal (IntersectionObserver) ───────────────────────
        (function() {
            var io = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        io.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.12
            });
            document.querySelectorAll('.reveal-fade').forEach(function(el) {
                io.observe(el);
            });
        })();

    </script>

    <x-support-ticket-fab />

</body>
</html>
