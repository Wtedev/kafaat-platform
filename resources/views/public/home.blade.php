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
        *, *::before, *::after { font-family: 'IBM Plex Sans Arabic', 'Tajawal', sans-serif; }
        html { scroll-behavior: smooth; }
        /* Multi-line text truncation */
        .clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        /* FAQ accordion */
        .faq-body { max-height: 0; overflow: hidden; transition: max-height 0.35s ease; }
        .faq-body.open { max-height: 500px; }
        .faq-chevron { transition: transform 0.3s ease; }
        .faq-chevron.open { transform: rotate(45deg); }
        /* Subtle focus ring for accessibility */
        button:focus-visible, a:focus-visible { outline: 2px solid #253B5B; outline-offset: 3px; border-radius: 8px; }
    </style>
</head>
<body class="bg-[#F7FAFC] text-[#111827] antialiased">


{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{-- 1. NAVBAR                                                           --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}
<header id="site-nav" class="sticky top-0 z-50 bg-white/95 backdrop-blur-sm border-b border-slate-100 shadow-sm transition-shadow duration-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 gap-6">

            {{-- Logo --}}
            <a href="{{ route('home') }}" class="text-2xl font-bold tracking-tight flex-shrink-0" style="color:#253B5B">كفاءات</a>

            {{-- Desktop Nav --}}
            <nav class="hidden lg:flex items-center gap-7 text-sm font-medium" style="color:#6B7280">
                <a href="{{ route('home') }}"                         class="hover:text-[#253B5B] transition-colors {{ request()->routeIs('home') ? 'text-[#253B5B] font-semibold' : '' }}">الرئيسية</a>
                <a href="{{ route('public.paths.index') }}"          class="hover:text-[#253B5B] transition-colors {{ request()->routeIs('public.paths.*') ? 'text-[#253B5B] font-semibold' : '' }}">المسارات</a>
                <a href="{{ route('public.programs.index') }}"       class="hover:text-[#253B5B] transition-colors {{ request()->routeIs('public.programs.*') ? 'text-[#253B5B] font-semibold' : '' }}">البرامج</a>
                <a href="{{ route('public.volunteering.index') }}"   class="hover:text-[#253B5B] transition-colors {{ request()->routeIs('public.volunteering.*') ? 'text-[#253B5B] font-semibold' : '' }}">الفرص التطوعية</a>
                <a href="#news"  class="hover:text-[#253B5B] transition-colors">الأخبار</a>
                <a href="#faq"   class="hover:text-[#253B5B] transition-colors">الأسئلة الشائعة</a>
            </nav>

            {{-- Desktop Auth Buttons --}}
            <div class="hidden lg:flex items-center gap-3 flex-shrink-0">
                @auth
                    <a href="{{ route('portal.dashboard') }}" class="px-5 py-2 rounded-2xl text-sm font-semibold text-white shadow-sm hover:shadow-md transition-all duration-200" style="background:#253B5B">
                        بوابتي
                    </a>
                @else
                    <a href="{{ route('login') }}"    class="px-5 py-2 rounded-2xl text-sm font-medium transition-colors hover:bg-[#EAF2FA]" style="color:#253B5B">تسجيل الدخول</a>
                    <a href="{{ route('register') }}" class="px-5 py-2 rounded-2xl text-sm font-semibold text-white shadow-sm hover:shadow-md transition-all duration-200" style="background:#253B5B">إنشاء حساب</a>
                @endauth
            </div>

            {{-- Mobile Hamburger --}}
            <button id="nav-hamburger" aria-label="قائمة التنقل" class="lg:hidden p-2 rounded-xl text-gray-500 hover:bg-gray-100 transition-colors flex-shrink-0">
                <svg id="hamburger-open"  class="w-6 h-6"        fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg id="hamburger-close" class="w-6 h-6 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    {{-- Mobile Menu --}}
    <div id="mobile-nav" class="hidden lg:hidden border-t border-gray-100 bg-white shadow-lg">
        <nav class="max-w-7xl mx-auto px-4 py-4 flex flex-col gap-1">
            <a href="{{ route('home') }}"                       class="px-4 py-2.5 rounded-xl text-sm font-medium text-gray-700 hover:bg-[#EAF2FA] hover:text-[#253B5B] transition-colors text-right">الرئيسية</a>
            <a href="{{ route('public.paths.index') }}"         class="px-4 py-2.5 rounded-xl text-sm font-medium text-gray-700 hover:bg-[#EAF2FA] hover:text-[#253B5B] transition-colors text-right">المسارات</a>
            <a href="{{ route('public.programs.index') }}"      class="px-4 py-2.5 rounded-xl text-sm font-medium text-gray-700 hover:bg-[#EAF2FA] hover:text-[#253B5B] transition-colors text-right">البرامج</a>
            <a href="{{ route('public.volunteering.index') }}"  class="px-4 py-2.5 rounded-xl text-sm font-medium text-gray-700 hover:bg-[#EAF2FA] hover:text-[#253B5B] transition-colors text-right">الفرص التطوعية</a>
            <a href="#news"                                     class="px-4 py-2.5 rounded-xl text-sm font-medium text-gray-700 hover:bg-[#EAF2FA] hover:text-[#253B5B] transition-colors text-right">الأخبار</a>
            <a href="#faq"                                      class="px-4 py-2.5 rounded-xl text-sm font-medium text-gray-700 hover:bg-[#EAF2FA] hover:text-[#253B5B] transition-colors text-right">الأسئلة الشائعة</a>
            @auth
                <a href="{{ route('portal.dashboard') }}" class="mt-3 px-4 py-2.5 rounded-xl text-sm font-semibold text-white text-center" style="background:#253B5B">بوابتي</a>
            @else
                <div class="mt-3 flex gap-2">
                    <a href="{{ route('login') }}"    class="flex-1 px-4 py-2.5 rounded-xl text-sm font-medium text-center border-2 transition-colors hover:bg-[#EAF2FA]" style="color:#253B5B; border-color:#253B5B">تسجيل الدخول</a>
                    <a href="{{ route('register') }}" class="flex-1 px-4 py-2.5 rounded-xl text-sm font-semibold text-white text-center" style="background:#253B5B">إنشاء حساب</a>
                </div>
            @endauth
        </nav>
    </div>
</header>


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
                    <a href="{{ route('register') }}"
                       class="px-7 py-3.5 rounded-2xl text-base font-semibold text-white shadow-md hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5"
                       style="background: linear-gradient(135deg, #253B5B 0%, #2e4a73 100%)">
                        ابدأ رحلتك
                    </a>
                    @endguest
                    @auth
                    <a href="{{ route('portal.dashboard') }}"
                       class="px-7 py-3.5 rounded-2xl text-base font-semibold text-white shadow-md hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5"
                       style="background: linear-gradient(135deg, #253B5B 0%, #2e4a73 100%)">
                        بوابتي
                    </a>
                    @endauth
                    <a href="{{ route('public.paths.index') }}"
                       class="px-7 py-3.5 rounded-2xl text-base font-semibold border-2 bg-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#EAF2FA]"
                       style="color:#253B5B; border-color:#c5ddef">
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
            <a href="{{ route('public.paths.index') }}"
               class="group block bg-white rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1.5 p-8 text-right">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-2xl mb-5 transition-transform group-hover:scale-110" style="background:#EAF2FA">🗺️</div>
                <h3 class="text-xl font-bold mb-3 transition-colors" style="color:#111827">المسارات التدريبية</h3>
                <p class="text-sm leading-relaxed mb-5" style="color:#6B7280">سلاسل تعليمية منظمة تأخذك من الأساسيات إلى الاحترافية في مجالات متنوعة، مصممة لتناسب جميع المستويات.</p>
                <span class="inline-flex items-center gap-1.5 text-sm font-semibold" style="color:#253B5B">
                    استكشف المسارات
                    <svg class="w-4 h-4 rotate-180 transition-transform group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </span>
            </a>

            {{-- Card: Training Programs (highlighted) --}}
            <a href="{{ route('public.programs.index') }}"
               class="group block rounded-3xl shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1.5 p-8 text-right"
               style="background: linear-gradient(145deg, #EAF2FA 0%, #F3F7FB 100%); border: 1px solid #dceaf7">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-2xl mb-5 bg-white shadow-sm transition-transform group-hover:scale-110">📘</div>
                <h3 class="text-xl font-bold mb-3" style="color:#111827">البرامج التدريبية</h3>
                <p class="text-sm leading-relaxed mb-5" style="color:#6B7280">برامج تدريبية متخصصة بمحتوى عملي وتطبيقي تُصدر شهادات معتمدة عند إتمامها بنجاح.</p>
                <span class="inline-flex items-center gap-1.5 text-sm font-semibold" style="color:#253B5B">
                    استكشف البرامج
                    <svg class="w-4 h-4 rotate-180 transition-transform group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </span>
            </a>

            {{-- Card: Volunteering --}}
            <a href="{{ route('public.volunteering.index') }}"
               class="group block bg-white rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1.5 p-8 text-right">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-2xl mb-5 bg-green-50 transition-transform group-hover:scale-110">🤝</div>
                <h3 class="text-xl font-bold mb-3" style="color:#111827">الفرص التطوعية</h3>
                <p class="text-sm leading-relaxed mb-5" style="color:#6B7280">انضم إلى مجتمع التطوع وأحدث فارقاً حقيقياً. ساعات تطوعك توثَّق وتُحتسب في سجلك المهني.</p>
                <span class="inline-flex items-center gap-1.5 text-sm font-semibold" style="color:#3CB878">
                    تصفح الفرص
                    <svg class="w-4 h-4 rotate-180 transition-transform group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
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
                ['value' => '+١٢٠٠', 'label' => 'المستفيدون',      'icon' => '👥'],
                ['value' => '٣٨',    'label' => 'المسارات',         'icon' => '🗺️'],
                ['value' => '٦٥',    'label' => 'البرامج',          'icon' => '📘'],
                ['value' => '٤٢',    'label' => 'الفرص التطوعية',   'icon' => '🤝'],
                ['value' => '+٨٠٠',  'label' => 'الشهادات',         'icon' => '🏅'],
                ['value' => '٢٥',    'label' => 'الشركاء',          'icon' => '🤲'],
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
                    <a href="{{ route('public.paths.index') }}"
                       class="px-7 py-3 rounded-2xl text-sm font-semibold text-white shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5"
                       style="background:#253B5B">
                        اعرف أكثر
                    </a>
                    @guest
                    <a href="{{ route('register') }}"
                       class="px-7 py-3 rounded-2xl text-sm font-semibold border-2 transition-all duration-200 hover:bg-[#EAF2FA] hover:-translate-y-0.5"
                       style="color:#253B5B; border-color:#c5ddef">
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
                    <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
                <div class="text-right">
                    <p class="text-sm font-semibold mb-1" style="color:#3CB878">أحدث المتاح</p>
                    <h2 class="text-2xl font-bold" style="color:#111827">برامج كفاءات</h2>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @forelse ($programs as $program)
                <a href="{{ route('public.programs.show', $program->slug) }}"
                   class="group bg-white rounded-3xl border border-gray-50 shadow-sm hover:shadow-lg transition-all duration-300 hover:-translate-y-1 p-6 block text-right">
                    <div class="flex items-center justify-between mb-4">
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-xl text-green-700 bg-green-100">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>مفتوح
                        </span>
                        <span class="text-xs font-medium px-3 py-1.5 rounded-xl" style="background:#EAF2FA; color:#253B5B">برنامج تدريبي</span>
                    </div>
                    <h3 class="font-bold text-base mb-2 clamp-2 group-hover:text-[#253B5B] transition-colors" style="color:#111827">{{ $program->title }}</h3>
                    <p class="text-sm clamp-2 mb-4" style="color:#6B7280">{{ $program->description }}</p>
                    <div class="flex items-center justify-between text-xs border-t border-gray-50 pt-4" style="color:#6B7280">
                        @if($program->start_date)
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            {{ $program->start_date->format('Y/m/d') }}
                        </span>
                        @endif
                        <span class="font-semibold" style="color:#3CB878">سجّل الآن ←</span>
                    </div>
                </a>
                @empty
                @foreach([
                    ['t' => 'مهارات التواصل الفعّال',    'd' => 'طوّر مهاراتك في التواصل والعرض والتقديم لتنجح في بيئة العمل الحديثة.',          'dt' => '٢٠٢٦/٦/١'],
                    ['t' => 'أساسيات ريادة الأعمال',     'd' => 'دورة شاملة تأخذك من الفكرة إلى المشروع الناجح عبر مراحل عملية متدرجة.',          'dt' => '٢٠٢٦/٦/١٥'],
                    ['t' => 'القيادة وإدارة الفرق',       'd' => 'تعلّم مهارات القيادة وبناء الفرق وإدارة الأداء في المنظمات الحديثة.',              'dt' => '٢٠٢٦/٧/١'],
                ] as $s)
                <div class="bg-white rounded-3xl border border-gray-50 shadow-sm p-6 text-right">
                    <div class="flex items-center justify-between mb-4">
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-xl text-green-700 bg-green-100"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>مفتوح</span>
                        <span class="text-xs font-medium px-3 py-1.5 rounded-xl" style="background:#EAF2FA; color:#253B5B">برنامج تدريبي</span>
                    </div>
                    <h3 class="font-bold text-base mb-2" style="color:#111827">{{ $s['t'] }}</h3>
                    <p class="text-sm clamp-2 mb-4" style="color:#6B7280">{{ $s['d'] }}</p>
                    <div class="flex items-center justify-between text-xs border-t border-gray-50 pt-4" style="color:#6B7280">
                        <span>📅 {{ $s['dt'] }}</span>
                        <span class="font-semibold" style="color:#3CB878">سجّل الآن ←</span>
                    </div>
                </div>
                @endforeach
                @endforelse
            </div>
        </div>

        {{-- ── Opportunities row ── --}}
        <div>
            <div class="flex items-end justify-between mb-8">
                <a href="{{ route('public.volunteering.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold hover:underline" style="color:#253B5B">
                    عرض الكل
                    <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
                <div class="text-right">
                    <p class="text-sm font-semibold mb-1" style="color:#3CB878">تطوّع وأحدث أثراً</p>
                    <h2 class="text-2xl font-bold" style="color:#111827">الفرص التطوعية</h2>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @forelse ($opportunities as $opp)
                <a href="{{ route('public.volunteering.show', $opp->slug) }}"
                   class="group bg-white rounded-3xl border border-gray-50 shadow-sm hover:shadow-lg transition-all duration-300 hover:-translate-y-1 p-6 block text-right">
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
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ number_format((float)$opp->hours_expected, 0) }} ساعة
                        </span>
                        @endif
                        <span class="font-semibold" style="color:#3CB878">تقدّم الآن ←</span>
                    </div>
                </a>
                @empty
                @foreach([
                    ['t' => 'مساعد إداري للمؤتمر السنوي',  'd' => 'ساعد في تنظيم وإدارة فعاليات المؤتمر السنوي للجمعية.',                              'h' => '١٥'],
                    ['t' => 'مدرّب أساسيات الحاسوب',        'd' => 'ساهم في تدريب الفئات المحتاجة على أساسيات استخدام الحاسوب والإنترنت.',               'h' => '٢٠'],
                    ['t' => 'مشرف معسكر شبابي',              'd' => 'اشرف على تنظيم معسكر التطوير الشخصي للطلاب الجامعيين.',                             'h' => '٣٠'],
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
                <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
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
<footer style="background:#111827" class="text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10 mb-12">

            {{-- Brand --}}
            <div class="text-right lg:col-span-1">
                <a href="{{ route('home') }}" class="text-2xl font-bold text-white inline-block mb-4">كفاءات</a>
                <p class="text-gray-400 text-sm leading-relaxed mb-6">
                    منصة تدريب وتطوع متكاملة تسعى إلى بناء قدرات الشباب وتمكينهم من التميز في مساراتهم المهنية.
                </p>
                <div class="flex items-center gap-3 justify-end">
                    @foreach(['𝕏', 'in', 'f', '▶'] as $s)
                    <a href="#" class="w-9 h-9 rounded-xl bg-white/10 flex items-center justify-center text-sm text-gray-300 hover:bg-white/20 transition-colors" aria-label="social">{{ $s }}</a>
                    @endforeach
                </div>
            </div>

            {{-- Quick Links --}}
            <div class="text-right">
                <h4 class="font-bold text-white mb-5 text-sm uppercase tracking-wider">روابط سريعة</h4>
                <ul class="space-y-3">
                    @foreach([
                        ['الرئيسية',          'home'],
                        ['المسارات التدريبية', 'public.paths.index'],
                        ['البرامج التدريبية',  'public.programs.index'],
                        ['الفرص التطوعية',    'public.volunteering.index'],
                    ] as [$label, $routeName])
                    <li><a href="{{ route($routeName) }}" class="text-gray-400 hover:text-white text-sm transition-colors">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </div>

            {{-- Platform --}}
            <div class="text-right">
                <h4 class="font-bold text-white mb-5 text-sm uppercase tracking-wider">المنصة</h4>
                <ul class="space-y-3">
                    <li><a href="{{ route('login') }}"    class="text-gray-400 hover:text-white text-sm transition-colors">تسجيل الدخول</a></li>
                    <li><a href="{{ route('register') }}" class="text-gray-400 hover:text-white text-sm transition-colors">إنشاء حساب</a></li>
                    <li><a href="#faq"                    class="text-gray-400 hover:text-white text-sm transition-colors">الأسئلة الشائعة</a></li>
                    <li><a href="#"                       class="text-gray-400 hover:text-white text-sm transition-colors">سياسة الخصوصية</a></li>
                    <li><a href="#"                       class="text-gray-400 hover:text-white text-sm transition-colors">الشروط والأحكام</a></li>
                </ul>
            </div>

            {{-- Contact --}}
            <div class="text-right">
                <h4 class="font-bold text-white mb-5 text-sm uppercase tracking-wider">تواصل معنا</h4>
                <ul class="space-y-3 text-sm text-gray-400">
                    <li class="flex items-center gap-2 justify-end"><span>info@kafaat.com</span><span class="text-gray-500 flex-shrink-0">✉️</span></li>
                    <li class="flex items-center gap-2 justify-end"><span>+966 5X XXX XXXX</span><span class="text-gray-500 flex-shrink-0">📞</span></li>
                    <li class="flex items-center gap-2 justify-end"><span>المملكة العربية السعودية</span><span class="text-gray-500 flex-shrink-0">📍</span></li>
                </ul>
            </div>

        </div>

        {{-- Divider + Copyright --}}
        <div class="border-t border-white/10 pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-gray-500">
            <p>© {{ date('Y') }} كفاءات. جميع الحقوق محفوظة.</p>
            <div class="flex items-center gap-5">
                <a href="#" class="hover:text-gray-300 transition-colors">سياسة الخصوصية</a>
                <a href="#" class="hover:text-gray-300 transition-colors">الشروط والأحكام</a>
            </div>
        </div>

    </div>
</footer>


{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{-- JavaScript                                                          --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}
<script>
    // ── Mobile nav toggle ───────────────────────────────────────────
    (function () {
        var btn     = document.getElementById('nav-hamburger');
        var menu    = document.getElementById('mobile-nav');
        var iconO   = document.getElementById('hamburger-open');
        var iconC   = document.getElementById('hamburger-close');
        btn.addEventListener('click', function () {
            var open = !menu.classList.contains('hidden');
            menu.classList.toggle('hidden', open);
            iconO.classList.toggle('hidden', !open);
            iconC.classList.toggle('hidden', open);
        });
    })();

    // ── FAQ accordion ───────────────────────────────────────────────
    function toggleFaq(idx) {
        var body = document.getElementById('faq-body-' + idx);
        var icon = document.getElementById('faq-icon-' + idx);
        var isOpen = body.classList.contains('open');

        // Close all
        document.querySelectorAll('.faq-body').forEach(function (el) { el.classList.remove('open'); });
        document.querySelectorAll('.faq-chevron').forEach(function (el) { el.classList.remove('open'); });

        // Toggle current
        if (!isOpen) {
            body.classList.add('open');
            icon.classList.add('open');
        }
    }

    // ── Navbar shadow on scroll ─────────────────────────────────────
    (function () {
        var nav = document.getElementById('site-nav');
        window.addEventListener('scroll', function () {
            nav.style.boxShadow = window.scrollY > 10
                ? '0 4px 24px rgba(37,59,91,0.08)'
                : '0 1px 3px rgba(0,0,0,0.06)';
        }, { passive: true });
    })();
</script>

</body>
</html>

