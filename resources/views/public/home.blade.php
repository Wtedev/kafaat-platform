{{--
    resources/views/public/home.blade.php
    Kafaat Platform — Public Homepage (standalone, does NOT extend layouts.public)
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>كفاءات — منصة التدريب والتطوع والشهادات</title>
    <meta name="description" content="كفاءات منصة تدريب وتطوع متكاملة لبناء قدرات الشباب من خلال المسارات التدريبية والبرامج والفرص التطوعية والشهادات المعتمدة." />

    {{-- CDN Tailwind Play (supports arbitrary values & JIT) --}}
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

    {{-- Fonts: IBM Plex Sans Arabic (primary) + Tajawal (fallback) --}}
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
            outline: 2px solid #253B5B;
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

        /* ── Impact pillar card hover ───────────────────────────────────── */
        .impact-pillar-card {
            transition: transform 0.35s cubic-bezier(.22, 1, .36, 1),
                box-shadow 0.35s cubic-bezier(.22, 1, .36, 1);
        }

        .impact-pillar-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.10);
        }

        /* ── Gradient text cross-browser ───────────────────────────────── */
        .gradient-text {
            background: linear-gradient(135deg, #0D1F2D 0%, #1EB890 50%, #3B82F6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

    </style>
</head>
<body class="bg-[#F7FAFC] text-[#111827] antialiased">


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 1. NAVBAR                                                           --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <x-public-navbar />


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 2. HERO SECTION                                                     --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section style="background: linear-gradient(150deg, #EEF5FB 0%, #F3F7FB 55%, #EAF2FA 100%)">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28">
            <div class="flex flex-col lg:flex-row items-center gap-16">

                {{-- ── Text (first child = RIGHT in RTL) ── --}}
                <div class="w-full lg:w-[54%] text-right">

                    {{-- Pill badge --}}
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl text-sm font-medium mb-6 border" style="background:#EAF2FA; color:#253B5B; border-color:#c5ddef">
                        <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:#3CB878"></span>
                        منصة التدريب والتطوع المعتمدة
                    </div>

                    {{-- Headline --}}
                    <h1 class="text-4xl sm:text-5xl lg:text-[3.4rem] font-bold leading-snug mb-5" style="color:#111827">
                        مرحبًا بك،<br>
                        في <span style="color:#253B5B">كفاءات</span>
                    </h1>

                    {{-- Subtitle --}}
                    <p class="text-lg leading-relaxed mb-8 max-w-lg" style="color:#6B7280">
                        نبني قدرات الشباب من خلال مسارات تدريبية متكاملة، وبرامج احترافية، وفرص تطوعية هادفة، وشهادات معتمدة تفتح أمامك آفاقاً جديدة.
                    </p>

                    {{-- CTA Buttons --}}
                    <div class="flex flex-wrap gap-4 mb-10">
                        @guest
                        <a href="{{ route('register') }}" class="px-7 py-3.5 rounded-2xl text-base font-semibold text-white shadow-md hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5" style="background: linear-gradient(135deg, #253B5B 0%, #2e4a73 100%)">
                            ابدأ رحلتك
                        </a>
                        @endguest
                        @auth
                        <a href="{{ route('portal.dashboard') }}" class="px-7 py-3.5 rounded-2xl text-base font-semibold text-white shadow-md hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5" style="background: linear-gradient(135deg, #253B5B 0%, #2e4a73 100%)">
                            بوابتي
                        </a>
                        @endauth
                        <a href="{{ route('public.paths.index') }}" class="px-7 py-3.5 rounded-2xl text-base font-semibold border-2 bg-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#EAF2FA]" style="color:#253B5B; border-color:#c5ddef">
                            استكشف المنصة
                        </a>
                    </div>

                    {{-- Trust indicators --}}
                    <div class="flex flex-wrap gap-5 text-sm" style="color:#6B7280">
                        @foreach(['+١٢٠٠ مستفيد', '+٦٥ برنامج', '+٨٠٠ شهادة'] as $trust)
                        <div class="flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background:#3CB878"></span>
                            {{ $trust }}
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- ── Dashboard Preview Card (second child = LEFT in RTL) ── --}}
                <div class="w-full lg:w-[46%] flex justify-center lg:justify-start">
                    <div class="relative w-full max-w-md">

                        {{-- Glow backdrop --}}
                        <div class="absolute inset-6 rounded-3xl blur-2xl opacity-50" style="background:radial-gradient(ellipse,#c5ddef,transparent)"></div>

                        {{-- Main card --}}
                        <div class="relative bg-white rounded-3xl shadow-2xl p-7 space-y-5 border border-white">

                            {{-- Card header --}}
                            <div class="flex items-center justify-between">
                                <div class="w-11 h-11 rounded-2xl flex items-center justify-center text-xl flex-shrink-0" style="background:#EAF2FA">📊</div>
                                <div class="text-right">
                                    <p class="text-xs mb-0.5" style="color:#6B7280">أهلاً، أحمد!</p>
                                    <p class="text-sm font-bold" style="color:#111827">لوحة تقدمك</p>
                                </div>
                            </div>

                            {{-- Mini stat tiles --}}
                            <div class="grid grid-cols-3 gap-3">
                                <div class="rounded-2xl p-3 text-center" style="background:#EAF2FA">
                                    <div class="text-2xl font-bold" style="color:#253B5B">٥</div>
                                    <div class="text-xs mt-0.5" style="color:#6B7280">مسارات</div>
                                </div>
                                <div class="rounded-2xl p-3 text-center bg-green-50">
                                    <div class="text-2xl font-bold text-green-600">٣</div>
                                    <div class="text-xs mt-0.5 text-green-500">شهادات</div>
                                </div>
                                <div class="rounded-2xl p-3 text-center bg-amber-50">
                                    <div class="text-2xl font-bold text-amber-500">١٢</div>
                                    <div class="text-xs mt-0.5 text-amber-400">ساعة</div>
                                </div>
                            </div>

                            {{-- Progress bars --}}
                            <div class="space-y-4">
                                <div>
                                    <div class="flex items-center justify-between mb-1.5">
                                        <span class="text-xs font-semibold" style="color:#3CB878">٧٥٪</span>
                                        <span class="text-xs font-medium text-gray-600">البرنامج التدريبي المتقدم</span>
                                    </div>
                                    <div class="h-2 rounded-full overflow-hidden bg-gray-100">
                                        <div class="h-full rounded-full" style="width:75%; background:linear-gradient(to left,#3CB878,#2da86e)"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex items-center justify-between mb-1.5">
                                        <span class="text-xs font-semibold text-blue-400">٤٠٪</span>
                                        <span class="text-xs font-medium text-gray-600">مسار ريادة الأعمال</span>
                                    </div>
                                    <div class="h-2 rounded-full overflow-hidden bg-gray-100">
                                        <div class="h-full rounded-full" style="width:40%; background:linear-gradient(to left,#60A5FA,#3B82F6)"></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Badges --}}
                            <div class="flex flex-wrap gap-2 pt-1">
                                <span class="px-3 py-1.5 rounded-xl text-xs font-medium bg-green-100 text-green-700">✓ متقدم</span>
                                <span class="px-3 py-1.5 rounded-xl text-xs font-medium" style="background:#EAF2FA; color:#253B5B">🏅 شهادة ممتازة</span>
                                <span class="px-3 py-1.5 rounded-xl text-xs font-medium bg-amber-100 text-amber-600">⭐ نشط</span>
                            </div>
                        </div>

                        {{-- Floating notification --}}
                        <div class="absolute -bottom-4 -left-4 bg-white rounded-2xl shadow-lg px-4 py-3 flex items-center gap-3 border border-gray-50">
                            <div class="w-8 h-8 rounded-xl bg-green-100 flex items-center justify-center text-sm flex-shrink-0">🏅</div>
                            <div>
                                <p class="text-xs font-semibold text-gray-800">شهادة جديدة!</p>
                                <p class="text-xs" style="color:#6B7280">تم إصدارها للتو</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 3. INTRO / VALUE SECTION                                            --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Heading --}}
            <div class="text-center mb-14">
                <p class="text-sm font-semibold uppercase tracking-widest mb-3" style="color:#3CB878">ما نقدّمه</p>
                <h2 class="text-3xl sm:text-4xl font-bold mb-4" style="color:#111827">نبذة عن كفاءات</h2>
                <p class="text-lg leading-relaxed max-w-2xl mx-auto" style="color:#6B7280">
                    كفاءات منصة تدريب وتطوع متكاملة تهدف إلى تمكين الشباب وتطوير مهاراتهم من خلال مسارات تعليمية احترافية وبرامج تدريبية معتمدة وفرص تطوعية توثّق الخبرة وتُحدث أثراً مجتمعياً حقيقياً.
                </p>
            </div>

            {{-- Feature Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                {{-- Card: Learning Paths --}}
                <a href="{{ route('public.paths.index') }}" class="group block bg-white rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1.5 p-8 text-right">
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-2xl mb-5 transition-transform group-hover:scale-110" style="background:#EAF2FA">🗺️</div>
                    <h3 class="text-xl font-bold mb-3 transition-colors" style="color:#111827">المسارات التدريبية</h3>
                    <p class="text-sm leading-relaxed mb-5" style="color:#6B7280">سلاسل تعليمية منظمة تأخذك من الأساسيات إلى الاحترافية في مجالات متنوعة، مصممة لتناسب جميع المستويات.</p>
                    <span class="inline-flex items-center gap-1.5 text-sm font-semibold" style="color:#253B5B">
                        استكشف المسارات
                        <svg class="w-4 h-4 rotate-180 transition-transform group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </span>
                </a>

                {{-- Card: Training Programs (highlighted) --}}
                <a href="{{ route('public.programs.index') }}" class="group block rounded-3xl shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1.5 p-8 text-right" style="background: linear-gradient(145deg, #EAF2FA 0%, #F3F7FB 100%); border: 1px solid #dceaf7">
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-2xl mb-5 bg-white shadow-sm transition-transform group-hover:scale-110">📘</div>
                    <h3 class="text-xl font-bold mb-3" style="color:#111827">البرامج التدريبية</h3>
                    <p class="text-sm leading-relaxed mb-5" style="color:#6B7280">برامج تدريبية متخصصة بمحتوى عملي وتطبيقي تُصدر شهادات معتمدة عند إتمامها بنجاح.</p>
                    <span class="inline-flex items-center gap-1.5 text-sm font-semibold" style="color:#253B5B">
                        استكشف البرامج
                        <svg class="w-4 h-4 rotate-180 transition-transform group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </span>
                </a>

                {{-- Card: Volunteering --}}
                <a href="{{ route('public.volunteering.index') }}" class="group block bg-white rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1.5 p-8 text-right">
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-2xl mb-5 bg-green-50 transition-transform group-hover:scale-110">🤝</div>
                    <h3 class="text-xl font-bold mb-3" style="color:#111827">الفرص التطوعية</h3>
                    <p class="text-sm leading-relaxed mb-5" style="color:#6B7280">انضم إلى مجتمع التطوع وأحدث فارقاً حقيقياً. ساعات تطوعك توثَّق وتُحتسب في سجلك المهني.</p>
                    <span class="inline-flex items-center gap-1.5 text-sm font-semibold" style="color:#3CB878">
                        تصفح الفرص
                        <svg class="w-4 h-4 rotate-180 transition-transform group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </span>
                </a>

            </div>
        </div>
    </section>


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 4. STATISTICS SECTION                                               --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section class="py-6 px-4 sm:px-6">
        <div class="max-w-7xl mx-auto">
            <div class="rounded-3xl py-16 px-8 sm:px-14" style="background: linear-gradient(135deg, #1a2d45 0%, #253B5B 60%, #2e4a6b 100%)">

                {{-- Decorative circles --}}
                <div class="relative overflow-hidden rounded-3xl">
                    <div class="absolute -top-10 -left-8 w-48 h-48 rounded-full bg-white opacity-5"></div>
                    <div class="absolute bottom-0 right-1/4 w-64 h-64 rounded-full bg-blue-300 opacity-5"></div>
                </div>

                <div class="relative z-10 text-center mb-12">
                    <h2 class="text-3xl font-bold text-white mb-2">أرقام كفاءات</h2>
                    <p class="text-blue-200 text-sm">نتائج نعتز بها</p>
                </div>

                @php
                $stats = [
                ['value' => '+١٢٠٠', 'label' => 'المستفيدون', 'icon' => '👥'],
                ['value' => '٣٨', 'label' => 'المسارات', 'icon' => '🗺️'],
                ['value' => '٦٥', 'label' => 'البرامج', 'icon' => '📘'],
                ['value' => '٤٢', 'label' => 'الفرص التطوعية', 'icon' => '🤝'],
                ['value' => '+٨٠٠', 'label' => 'الشهادات', 'icon' => '🏅'],
                ['value' => '٢٥', 'label' => 'الشركاء', 'icon' => '🤲'],
                ];
                @endphp

                <div class="relative z-10 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-8">
                    @foreach($stats as $stat)
                    <div class="text-center">
                        <div class="text-3xl mb-3">{{ $stat['icon'] }}</div>
                        <div class="text-4xl font-bold text-white mb-1 tabular-nums">{{ $stat['value'] }}</div>
                        <div class="text-blue-200 text-sm">{{ $stat['label'] }}</div>
                    </div>
                    @endforeach
                </div>

            </div>
        </div>
    </section>


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 5. ABOUT PLATFORM SECTION                                           --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col lg:flex-row items-center gap-16">

                {{-- ── Text (first child = RIGHT in RTL) ── --}}
                <div class="w-full lg:w-1/2 text-right">
                    <p class="text-sm font-semibold uppercase tracking-widest mb-3" style="color:#3CB878">لماذا كفاءات</p>
                    <h2 class="text-3xl sm:text-4xl font-bold mb-5" style="color:#111827">عن المنصة التدريبية</h2>
                    <p class="leading-relaxed mb-4" style="color:#6B7280">
                        كفاءات منصة شاملة أُنشئت لتكون المرجع الأول للشباب الراغب في تطوير مهاراته والمساهمة في مجتمعه. نجمع في مكان واحد المسارات التعليمية المتدرجة والبرامج التدريبية المتخصصة وأوسع قاعدة للفرص التطوعية.
                    </p>
                    <p class="leading-relaxed mb-8" style="color:#6B7280">
                        كل خطوة تخطوها على منصتنا تُوثَّق وتتحول إلى سجل مهني احترافي يعززك أمام أصحاب العمل والمؤسسات الشريكة.
                    </p>
                    <div class="flex flex-wrap gap-4">
                        <a href="{{ route('public.paths.index') }}" class="px-7 py-3 rounded-2xl text-sm font-semibold text-white shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5" style="background:#253B5B">
                            اعرف أكثر
                        </a>
                        @guest
                        <a href="{{ route('register') }}" class="px-7 py-3 rounded-2xl text-sm font-semibold border-2 transition-all duration-200 hover:bg-[#EAF2FA] hover:-translate-y-0.5" style="color:#253B5B; border-color:#c5ddef">
                            انضم مجاناً
                        </a>
                        @endguest
                    </div>
                </div>

                {{-- ── Visual (second child = LEFT in RTL) ── --}}
                <div class="w-full lg:w-1/2 flex justify-center">
                    <div class="relative w-full max-w-sm">
                        <div class="absolute -top-5 -right-5 w-28 h-28 rounded-3xl opacity-50" style="background:#EAF2FA"></div>
                        <div class="absolute -bottom-5 -left-5 w-20 h-20 rounded-2xl opacity-40 bg-green-100"></div>
                        <div class="relative bg-white rounded-3xl shadow-xl border border-gray-50 p-9 text-center">
                            <div class="w-20 h-20 rounded-3xl flex items-center justify-center text-4xl mx-auto mb-5" style="background:#EAF2FA">🎓</div>
                            <h3 class="text-xl font-bold mb-2" style="color:#253B5B">منصة متكاملة</h3>
                            <p class="text-sm mb-7" style="color:#6B7280">للتدريب والتطوع والشهادات</p>
                            <div class="space-y-3 text-right">
                                @foreach(['مسارات تعليمية متدرجة','برامج تدريبية معتمدة','فرص تطوعية موثقة','شهادات رقمية قابلة للتحقق'] as $feat)
                                <div class="flex items-center gap-3 justify-end">
                                    <span class="text-sm text-gray-700">{{ $feat }}</span>
                                    <div class="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold" style="background:#DCFCE7; color:#3CB878">✓</div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 6. PROGRAMS & OPPORTUNITIES SECTION                                 --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section class="py-20" style="background:#F3F7FB">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- ── Programs row ── --}}
            <div class="mb-16">
                <div class="flex items-end justify-between mb-8">
                    <a href="{{ route('public.programs.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold hover:underline" style="color:#253B5B">
                        عرض الكل
                        <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </a>
                    <div class="text-right">
                        <p class="text-sm font-semibold mb-1" style="color:#3CB878">أحدث المتاح</p>
                        <h2 class="text-2xl font-bold" style="color:#111827">برامج كفاءات</h2>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    @php
                    $programGradients = [
                    'linear-gradient(135deg,#1EB890 0%,#0ea5e9 100%)',
                    'linear-gradient(135deg,#253B5B 0%,#3B82F6 100%)',
                    'linear-gradient(135deg,#8B5CF6 0%,#3B82F6 100%)',
                    'linear-gradient(135deg,#F59E0B 0%,#EF4444 100%)',
                    'linear-gradient(135deg,#1EB890 0%,#8B5CF6 100%)',
                    'linear-gradient(135deg,#253B5B 0%,#1EB890 100%)',
                    ];
                    @endphp
                    @forelse ($programs as $index => $program)
                    <a href="{{ route('public.programs.show', $program->slug) }}" class="group bg-white rounded-3xl border border-gray-50 shadow-sm hover:shadow-lg
                              transition-all duration-300 hover:-translate-y-1 block text-right overflow-hidden">

                        {{-- Image or gradient header --}}
                        @if ($program->image)
                        <img src="{{ asset('storage/' . $program->image) }}" alt="{{ $program->title }}" class="w-full h-28 object-cover">
                        @else
                        <div class="h-28 w-full flex items-end p-4" style="background:{{ $programGradients[$index % 6] }}">
                            <span class="text-white text-lg font-black leading-tight opacity-90">{{ $program->title }}</span>
                        </div>
                        @endif

                        <div class="p-6">
                            <div class="flex items-center justify-between mb-3">
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-xl text-green-700 bg-green-100">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>مفتوح
                                </span>
                                <span class="text-xs font-medium px-3 py-1.5 rounded-xl" style="background:#EAF2FA; color:#253B5B">برنامج تدريبي</span>
                            </div>
                            @if (!$program->image)
                            <p class="text-sm clamp-2 mb-4" style="color:#6B7280">{{ $program->description }}</p>
                            @else
                            <h3 class="font-bold text-base mb-2 clamp-1 group-hover:text-[#253B5B] transition-colors" style="color:#111827">{{ $program->title }}</h3>
                            <p class="text-sm clamp-2 mb-4" style="color:#6B7280">{{ $program->description }}</p>
                            @endif
                            <div class="flex items-center justify-between text-xs border-t border-gray-50 pt-4" style="color:#6B7280">
                                @if($program->start_date)
                                <span class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    {{ $program->start_date->format('Y/m/d') }}
                                </span>
                                @else
                                <span></span>
                                @endif
                                <span class="font-semibold" style="color:#3CB878">سجّل الآن ←</span>
                            </div>
                        </div>
                    </a>
                    @empty
                    <div class="col-span-3 py-12 text-center rounded-3xl border border-dashed border-gray-200 bg-white" style="color:#6B7280">
                        لا توجد برامج منشورة حالياً.
                    </div>
                    @endforelse
                </div>
            </div>

            {{-- ── Opportunities row ── --}}
            <div>
                <div class="flex items-end justify-between mb-8">
                    <a href="{{ route('public.volunteering.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold hover:underline" style="color:#253B5B">
                        عرض الكل
                        <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </a>
                    <div class="text-right">
                        <p class="text-sm font-semibold mb-1" style="color:#3CB878">تطوّع وأحدث أثراً</p>
                        <h2 class="text-2xl font-bold" style="color:#111827">الفرص التطوعية</h2>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    @forelse ($opportunities as $opp)
                    <a href="{{ route('public.volunteering.show', $opp->slug) }}" class="group bg-white rounded-3xl border border-gray-50 shadow-sm hover:shadow-lg transition-all duration-300 hover:-translate-y-1 p-6 block text-right">
                        <div class="flex items-center justify-between mb-4">
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-xl text-green-700 bg-green-100">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>متاحة
                            </span>
                            <span class="text-xs font-medium px-3 py-1.5 rounded-xl bg-green-50 text-green-700">🤝 تطوع</span>
                        </div>
                        <h3 class="font-bold text-base mb-2 clamp-2 group-hover:text-[#253B5B] transition-colors" style="color:#111827">{{ $opp->title }}</h3>
                        <p class="text-sm clamp-2 mb-4" style="color:#6B7280">{{ $opp->description }}</p>
                        <div class="flex items-center justify-between text-xs border-t border-gray-50 pt-4" style="color:#6B7280">
                            @if($opp->hours_expected)
                            <span class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                {{ number_format((float)$opp->hours_expected, 0) }} ساعة
                            </span>
                            @endif
                            <span class="font-semibold" style="color:#3CB878">تقدّم الآن ←</span>
                        </div>
                    </a>
                    @empty
                    @foreach([
                    ['t' => 'مساعد إداري للمؤتمر السنوي', 'd' => 'ساعد في تنظيم وإدارة فعاليات المؤتمر السنوي للجمعية.', 'h' => '١٥'],
                    ['t' => 'مدرّب أساسيات الحاسوب', 'd' => 'ساهم في تدريب الفئات المحتاجة على أساسيات استخدام الحاسوب والإنترنت.', 'h' => '٢٠'],
                    ['t' => 'مشرف معسكر شبابي', 'd' => 'اشرف على تنظيم معسكر التطوير الشخصي للطلاب الجامعيين.', 'h' => '٣٠'],
                    ] as $s)
                    <div class="bg-white rounded-3xl border border-gray-50 shadow-sm p-6 text-right">
                        <div class="flex items-center justify-between mb-4">
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-xl text-green-700 bg-green-100"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>متاحة</span>
                            <span class="text-xs font-medium px-3 py-1.5 rounded-xl bg-green-50 text-green-700">🤝 تطوع</span>
                        </div>
                        <h3 class="font-bold text-base mb-2" style="color:#111827">{{ $s['t'] }}</h3>
                        <p class="text-sm clamp-2 mb-4" style="color:#6B7280">{{ $s['d'] }}</p>
                        <div class="flex items-center justify-between text-xs border-t border-gray-50 pt-4" style="color:#6B7280">
                            <span class="flex items-center gap-1">⏱ {{ $s['h'] }} ساعة</span>
                            <span class="font-semibold" style="color:#3CB878">تقدّم الآن ←</span>
                        </div>
                    </div>
                    @endforeach
                    @endforelse
                </div>
            </div>

        </div>
    </section>


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 7. NEWS & EVENTS SECTION                                            --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section id="news" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex items-end justify-between mb-10">
                <a href="#" class="inline-flex items-center gap-1.5 text-sm font-semibold hover:underline" style="color:#253B5B">
                    عرض كل الأخبار
                    <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </a>
                <div class="text-right">
                    <p class="text-sm font-semibold mb-1" style="color:#3CB878">آخر التحديثات</p>
                    <h2 class="text-2xl font-bold" style="color:#111827">الأخبار والفعاليات</h2>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                <article class="group bg-white rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 overflow-hidden">
                    <div class="h-48 flex items-center justify-center text-5xl" style="background: linear-gradient(135deg, #EAF2FA, #DCE8F5)">🚀</div>
                    <div class="p-6 text-right">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs text-[#6B7280]">١ مايو ٢٠٢٦</span>
                            <span class="text-xs font-medium px-3 py-1 rounded-xl" style="background:#EAF2FA; color:#253B5B">إطلاق</span>
                        </div>
                        <h3 class="font-bold text-base mb-2 group-hover:text-[#253B5B] transition-colors" style="color:#111827">إطلاق مسار التقنية والبرمجة الجديد</h3>
                        <p class="text-sm clamp-3" style="color:#6B7280">أطلقت منصة كفاءات مساراً تدريبياً جديداً متكاملاً في مجال البرمجة وتطوير التطبيقات يناسب المبتدئين والمتوسطين.</p>
                    </div>
                </article>

                <article class="group bg-white rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 overflow-hidden">
                    <div class="h-48 flex items-center justify-center text-5xl" style="background: linear-gradient(135deg, #ECFDF5, #D1FAE5)">🎤</div>
                    <div class="p-6 text-right">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs text-[#6B7280]">٢٨ أبريل ٢٠٢٦</span>
                            <span class="text-xs font-medium px-3 py-1 rounded-xl bg-green-100 text-green-700">ورشة عمل</span>
                        </div>
                        <h3 class="font-bold text-base mb-2 group-hover:text-[#253B5B] transition-colors" style="color:#111827">ورشة عمل: مهارات الاتصال الفعّال</h3>
                        <p class="text-sm clamp-3" style="color:#6B7280">ورشة عمل مكثفة تُعقد عبر الإنترنت تُعنى بتطوير مهارات التواصل اللفظي والكتابي في بيئات العمل المهنية.</p>
                    </div>
                </article>

                <article class="group bg-white rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 overflow-hidden">
                    <div class="h-48 flex items-center justify-center text-5xl" style="background: linear-gradient(135deg, #FFF7ED, #FED7AA)">🤲</div>
                    <div class="p-6 text-right">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs text-[#6B7280]">٢٠ أبريل ٢٠٢٦</span>
                            <span class="text-xs font-medium px-3 py-1 rounded-xl bg-amber-100 text-amber-700">شراكة</span>
                        </div>
                        <h3 class="font-bold text-base mb-2 group-hover:text-[#253B5B] transition-colors" style="color:#111827">شراكة مع جمعية التنمية المجتمعية</h3>
                        <p class="text-sm clamp-3" style="color:#6B7280">توقيع اتفاقية شراكة استراتيجية تهدف إلى توسيع فرص التطوع وتعزيز التدريب المجتمعي في المناطق المختلفة.</p>
                    </div>
                </article>

            </div>
        </div>
    </section>


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 7.5. IMPACT YEAR — عام الأثر (Strategic Premium Section)           --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section id="impact-year">

        {{-- ─── Hero Block ────────────────────────────────────────────────── --}}
        <div class="relative overflow-hidden" style="background: linear-gradient(135deg, #0D1F2D 0%, #0a3550 40%, #063d30 100%)">

            {{-- Decorative abstract shapes (Saudi geometric identity) --}}
            <div class="absolute inset-0 overflow-hidden pointer-events-none">
                <div class="absolute top-0 right-0 w-[700px] h-[700px] rounded-full" style="background: radial-gradient(circle, rgba(30,184,144,0.12) 0%, transparent 65%);
                            transform: translate(30%, -30%)"></div>
                <div class="absolute bottom-0 left-0 w-[600px] h-[600px] rounded-full" style="background: radial-gradient(circle, rgba(59,130,246,0.10) 0%, transparent 65%);
                            transform: translate(-30%, 30%)"></div>
                <svg class="absolute inset-0 w-full h-full" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <pattern id="impact-grid" width="60" height="60" patternUnits="userSpaceOnUse">
                            <path d="M 60 0 L 0 0 0 60" fill="none" stroke="rgba(255,255,255,0.03)" stroke-width="1" />
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#impact-grid)" />
                </svg>
                <svg class="absolute top-12 left-16 opacity-[0.07]" width="110" height="127" viewBox="0 0 120 138">
                    <polygon points="60,0 120,34.5 120,103.5 60,138 0,103.5 0,34.5" fill="none" stroke="#1EB890" stroke-width="1.5" />
                </svg>
                <svg class="absolute top-24 left-28 opacity-[0.04]" width="68" height="79" viewBox="0 0 120 138">
                    <polygon points="60,0 120,34.5 120,103.5 60,138 0,103.5 0,34.5" fill="none" stroke="#1EB890" stroke-width="1.5" />
                </svg>
                <svg class="absolute bottom-16 right-24 opacity-[0.05]" width="90" height="104" viewBox="0 0 120 138">
                    <polygon points="60,0 120,34.5 120,103.5 60,138 0,103.5 0,34.5" fill="none" stroke="#60A5FA" stroke-width="1.5" />
                </svg>
            </div>

            {{-- Hero content --}}
            <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-28 text-center">

                {{-- Pill label --}}
                <div class="reveal-fade inline-flex items-center gap-2.5 px-5 py-2 rounded-2xl border mb-8" style="background:rgba(30,184,144,0.12); border-color:rgba(30,184,144,0.30); color:#1EB890">
                    <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:#1EB890; animation:pulse 2s infinite"></span>
                    <span class="text-sm font-semibold tracking-wide">المبادرة الاستراتيجية ٢٠٢٦</span>
                </div>

                {{-- Main title --}}
                <h2 class="reveal-fade font-black leading-none tracking-tight text-white mb-5" style="font-size:clamp(4rem,12vw,8rem); transition-delay:0.08s">
                    عام
                    <span style="background: linear-gradient(135deg, #1EB890 0%, #60A5FA 100%);
                                 -webkit-background-clip: text; -webkit-text-fill-color: transparent;
                                 background-clip: text">الأثر</span>
                </h2>

                {{-- Sub-headline --}}
                <p class="reveal-fade text-2xl sm:text-3xl font-light mb-6 max-w-2xl mx-auto leading-relaxed" style="color:rgba(255,255,255,0.75); transition-delay:0.16s">
                    نقيس ما نصنعه… ونبني أثراً مستداماً
                </p>

                {{-- Transformation pillars --}}
                <div class="reveal-fade flex flex-col sm:flex-row items-center justify-center gap-3 mb-12 text-sm" style="color:rgba(255,255,255,0.45); transition-delay:0.24s">
                    <span>من تنفيذ المبادرات إلى استدامة نتائجها</span>
                    <span class="hidden sm:block w-1.5 h-1.5 rounded-full flex-shrink-0" style="background:rgba(255,255,255,0.2)"></span>
                    <span>من قياس الجهد إلى قياس الأثر الحقيقي</span>
                    <span class="hidden sm:block w-1.5 h-1.5 rounded-full flex-shrink-0" style="background:rgba(255,255,255,0.2)"></span>
                    <span>من أنشطة متفرقة إلى منظومة متكاملة</span>
                </div>

                {{-- CTA --}}
                <div class="reveal-fade" style="transition-delay:0.32s">
                    <a href="{{ route('public.programs.index') }}" class="inline-flex items-center gap-3 px-8 py-4 rounded-2xl font-semibold text-white
                              text-base shadow-lg transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl" style="background: linear-gradient(135deg, #1EB890 0%, #0ea5e9 100%)">
                        استكشف أثر كفاءات
                        <svg class="w-5 h-5 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>

            </div>
        </div>
        {{-- / Hero Block --}}

        {{-- ─── 4 Impact Pillars ──────────────────────────────────────────── --}}
        <div class="py-24" style="background:#F3F7FB">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="text-center mb-16 reveal-fade">
                    <p class="text-sm font-semibold uppercase tracking-widest mb-3" style="color:#1EB890">الركائز الأربع</p>
                    <h3 class="text-3xl sm:text-4xl font-bold" style="color:#111827">أركان الأثر</h3>
                </div>

                @php
                $impactPillars = [
                [
                'icon' => '
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />',
                'title' => 'أثر الإنسان',
                'desc' => 'تطوير القدرات البشرية وتحويلها إلى نتائج حقيقية موثّقة وقابلة للقياس.',
                'color' => '#1EB890',
                'glow' => 'rgba(30,184,144,0.10)',
                'ring' => 'rgba(30,184,144,0.22)',
                'delay' => '0s',
                ],
                [
                'icon' => '
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z" />',
                'title' => 'أثر المبادرات',
                'desc' => 'تحويل المبادرات من فعاليات مؤقتة إلى قيمة مستدامة قابلة للقياس والتوسع.',
                'color' => '#3B82F6',
                'glow' => 'rgba(59,130,246,0.10)',
                'ring' => 'rgba(59,130,246,0.22)',
                'delay' => '0.1s',
                ],
                [
                'icon' => '
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />',
                'title' => 'أثر الأداء',
                'desc' => 'رفع الكفاءة التشغيلية وجودة التنفيذ نحو أداء مؤسسي متميز ومستدام.',
                'color' => '#8B5CF6',
                'glow' => 'rgba(139,92,246,0.10)',
                'ring' => 'rgba(139,92,246,0.22)',
                'delay' => '0.2s',
                ],
                [
                'icon' => '
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />',
                'title' => 'أثر المنظمة',
                'desc' => 'بناء منظمة راسخة قائمة على الحوكمة والشفافية والاستدامة المؤسسية.',
                'color' => '#F59E0B',
                'glow' => 'rgba(245,158,11,0.10)',
                'ring' => 'rgba(245,158,11,0.22)',
                'delay' => '0.3s',
                ],
                ];
                @endphp

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    @foreach($impactPillars as $pillar)
                    <div class="impact-pillar-card reveal-fade group relative bg-white rounded-3xl p-8 text-right
                                border border-gray-100 shadow-sm overflow-hidden cursor-default" style="transition-delay:{{ $pillar['delay'] }}">

                        {{-- Radial glow overlay (hover) --}}
                        <div class="absolute inset-0 rounded-3xl opacity-0 group-hover:opacity-100
                                    transition-opacity duration-500 pointer-events-none" style="background: radial-gradient(ellipse at 50% -10%, {{ $pillar['glow'] }}, transparent 70%)"></div>

                        {{-- Bottom accent line (hover) --}}
                        <div class="absolute bottom-0 right-0 left-0 h-[3px] rounded-b-3xl
                                    opacity-0 group-hover:opacity-100 transition-opacity duration-400" style="background: linear-gradient(to left, {{ $pillar['color'] }}, transparent)"></div>

                        {{-- Icon --}}
                        <div class="relative w-14 h-14 rounded-2xl flex items-center justify-center mb-6
                                    transition-transform duration-300 group-hover:scale-110" style="background:{{ $pillar['glow'] }}; box-shadow: 0 0 0 1px {{ $pillar['ring'] }}">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="{{ $pillar['color'] }}">
                                {!! $pillar['icon'] !!}
                            </svg>
                        </div>

                        {{-- Title --}}
                        <h4 class="relative text-xl font-bold mb-3" style="color:#111827">
                            {{ $pillar['title'] }}
                        </h4>

                        {{-- Description --}}
                        <p class="relative text-sm leading-relaxed" style="color:#6B7280">
                            {{ $pillar['desc'] }}
                        </p>

                    </div>
                    @endforeach
                </div>

            </div>
        </div>
        {{-- / 4 Impact Pillars --}}

        {{-- ─── Impact Statement (Quote Block) ──────────────────────────── --}}
        <div class="py-20 bg-white overflow-hidden">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <div class="reveal-fade relative">
                    {{-- Oversized decorative quote mark --}}
                    <div class="absolute inset-0 flex items-start justify-center select-none pointer-events-none" aria-hidden="true">
                        <span class="text-[12rem] leading-none font-black" style="color:rgba(30,184,144,0.05); line-height:0.7">"</span>
                    </div>
                    {{-- Quote --}}
                    <p class="relative text-3xl sm:text-4xl lg:text-5xl font-bold leading-snug gradient-text">
                        معاً… نصنع أثراً يُقاس، ويُبنى، ويستمر
                    </p>
                    {{-- Divider --}}
                    <div class="mt-8 w-20 h-1 rounded-full mx-auto" style="background: linear-gradient(to left, #1EB890, #3B82F6)"></div>
                </div>
            </div>
        </div>
        {{-- / Impact Statement --}}

        {{-- ─── Strategic Connection ──────────────────────────────────────── --}}
        <div class="py-20" style="background: linear-gradient(180deg, #F3F7FB 0%, #EAF2FA 100%)">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col lg:flex-row items-center gap-14">

                    {{-- Text (first child = RIGHT in RTL) --}}
                    <div class="w-full lg:w-1/2 text-right reveal-fade">
                        <p class="text-sm font-semibold uppercase tracking-widest mb-3" style="color:#1EB890">الإطار الاستراتيجي</p>
                        <h3 class="text-2xl sm:text-3xl font-bold mb-5" style="color:#111827">
                            الترجمة التنفيذية<br>للخطة الاستراتيجية
                        </h3>
                        <p class="leading-loose mb-8" style="color:#6B7280">
                            عام الأثر هو الترجمة التنفيذية للخطة الاستراتيجية للجمعية، ينقل المنظومة من منطق الأنشطة إلى منطق النتائج، ومن قياس الجهد إلى قياس التغيير الحقيقي في حياة المستفيدين.
                        </p>
                        <a href="{{ route('public.programs.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold transition-all duration-200
                                  hover:gap-3 hover:opacity-80" style="color:#1EB890">
                            تعرّف على برامجنا الاستراتيجية
                            <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    </div>

                    {{-- Strategic pillars grid (second child = LEFT in RTL) --}}
                    <div class="w-full lg:w-1/2 reveal-fade" style="transition-delay:0.15s">
                        @php
                        $stratPillars = [
                        ['icon' => '📈', 'label' => 'تعظيم الأثر التنموي', 'bg' => 'rgba(30,184,144,0.08)'],
                        ['icon' => '💻', 'label' => 'التحول الرقمي', 'bg' => 'rgba(59,130,246,0.08)'],
                        ['icon' => '♻️', 'label' => 'الاستدامة المالية', 'bg' => 'rgba(139,92,246,0.08)'],
                        ['icon' => '🧠', 'label' => 'تطوير رأس المال البشري', 'bg' => 'rgba(245,158,11,0.08)'],
                        ];
                        @endphp
                        <div class="grid grid-cols-2 gap-4">
                            @foreach($stratPillars as $sp)
                            <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm text-right
                                        hover:shadow-md hover:-translate-y-0.5 transition-all duration-300">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl mb-3 ms-auto" style="background:{{ $sp['bg'] }}">{{ $sp['icon'] }}</div>
                                <p class="text-sm font-semibold" style="color:#111827">{{ $sp['label'] }}</p>
                            </div>
                            @endforeach
                        </div>
                    </div>

                </div>
            </div>
        </div>
        {{-- / Strategic Connection --}}

    </section>
    {{-- / عام الأثر --}}


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 8. ANNUAL REPORT SECTION                                            --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section class="py-8 px-4 sm:px-6">
        <div class="max-w-7xl mx-auto">
            <div class="relative rounded-3xl overflow-hidden" style="background: linear-gradient(135deg, #111827 0%, #253B5B 60%, #1e304d 100%)">

                {{-- Decorative shapes --}}
                <div class="absolute top-0 left-0 w-56 h-56 rounded-full -translate-x-1/3 -translate-y-1/3 bg-white opacity-5"></div>
                <div class="absolute bottom-0 right-1/3 w-72 h-72 rounded-full translate-y-1/2 bg-blue-400 opacity-5"></div>

                <div class="relative z-10 flex flex-col md:flex-row items-center justify-between px-10 sm:px-16 py-14 gap-10">

                    {{-- Text (right in RTL) --}}
                    <div class="text-right">
                        <div class="text-8xl font-black leading-none mb-1" style="color:rgba(255,255,255,0.12)">٢٠٢٥</div>
                        <h2 class="text-3xl font-bold text-white mb-3">التقرير السنوي</h2>
                        <p class="text-blue-200 leading-relaxed max-w-md">
                            تقرير شامل يرصد إنجازات منصة كفاءات خلال عام ٢٠٢٥، من مسارات وبرامج وشهادات وأثر مجتمعي حقيقي.
                        </p>
                    </div>

                    {{-- Download block (left in RTL) --}}
                    <div class="flex-shrink-0">
                        <div class="w-40 h-40 rounded-3xl flex flex-col items-center justify-center gap-3 cursor-pointer hover:bg-white/20 transition-colors" style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2)">
                            <span class="text-4xl">📄</span>
                            <span class="text-white text-sm font-semibold">تحميل التقرير</span>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 9. PARTNERS SECTION                                                 --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="text-center mb-12">
                <p class="text-sm font-semibold uppercase tracking-widest mb-2" style="color:#3CB878">يثقون بنا</p>
                <h2 class="text-2xl font-bold" style="color:#111827">شركاؤنا</h2>
            </div>

            <div class="flex flex-wrap justify-center items-center gap-5">
                @foreach(range(1,6) as $i)
                <div class="w-36 h-20 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-center text-sm font-medium bg-white hover:shadow-md hover:border-[#c5ddef] transition-all duration-200 cursor-pointer" style="color:#6B7280">
                    شريك {{ $i }}
                </div>
                @endforeach
            </div>

        </div>
    </section>


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 10. FAQ SECTION                                                     --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section id="faq" class="py-20" style="background:#F3F7FB">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="text-center mb-12">
                <p class="text-sm font-semibold uppercase tracking-widest mb-2" style="color:#3CB878">لديك سؤال؟</p>
                <h2 class="text-3xl font-bold" style="color:#111827">الأسئلة الشائعة</h2>
            </div>

            @php
            $faqs = [
            ['q' => 'ما هي منصة كفاءات؟',
            'a' => 'كفاءات منصة إلكترونية متكاملة تهدف إلى تمكين الشباب من خلال توفير مسارات تدريبية وبرامج تعليمية معتمدة وفرص تطوعية موثقة وشهادات رقمية قابلة للتحقق.'],
            ['q' => 'كيف يمكنني التسجيل في مسار تدريبي؟',
            'a' => 'أنشئ حساباً مجانياً على المنصة، ثم تصفح المسارات المتاحة وانقر على "التسجيل". سيصلك تأكيد التسجيل فور موافقة الإدارة.'],
            ['q' => 'هل المحتوى مجاني بالكامل؟',
            'a' => 'نعم، معظم المسارات والبرامج مجانية بالكامل. بعض البرامج المتخصصة قد تتطلب رسوماً رمزية تُحدد عند التسجيل.'],
            ['q' => 'كيف أحصل على شهادة إتمام؟',
            'a' => 'عند إتمام برنامج أو مسار بنسبة حضور وأداء تتجاوز الحد المطلوب تُصدر شهادة رقمية تلقائياً تجدها في ملفك الشخصي ويمكنك تحميلها أو مشاركتها.'],
            ['q' => 'ما الفرق بين المسار والبرنامج التدريبي؟',
            'a' => 'المسار عبارة عن سلسلة من الدورات المتدرجة تأخذك من مستوى إلى آخر في مجال محدد. البرنامج التدريبي عادةً أقصر وأكثر تخصصاً ويُركز على مهارة أو موضوع بعينه.'],
            ['q' => 'كيف أتقدم لفرصة تطوعية؟',
            'a' => 'تصفح الفرص المتاحة في قسم التطوع، انقر على الفرصة التي تناسبك ثم انقر "التقدم". ستتلقى ردًا من فريق المنصة خلال ٣–٥ أيام عمل.'],
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

</body>
</html>
