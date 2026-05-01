{{--
    resources/views/public/impact.blade.php
    عام الأثر — Standalone impact year page.
    Uses shared navbar + footer components, own <head> for full-width sections.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>عام الأثر — كفاءات</title>
    <meta name="description" content="عام الأثر هو التحول من قياس الجهد إلى قياس الأثر الحقيقي. تعرف على الركائز الاستراتيجية الأربع لمنصة كفاءات." />

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
        *,
        *::before,
        *::after {
            font-family: 'IBM Plex Sans Arabic', 'Tajawal', sans-serif;
        }

        html {
            scroll-behavior: smooth;
        }

        button:focus-visible,
        a:focus-visible {
            outline: 2px solid #253B5B;
            outline-offset: 3px;
            border-radius: 8px;
        }

        .reveal-fade {
            opacity: 0;
            transform: translateY(24px);
            transition: opacity 0.7s cubic-bezier(.22, 1, .36, 1), transform 0.7s cubic-bezier(.22, 1, .36, 1);
        }

        .reveal-fade.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .gradient-text {
            background: linear-gradient(135deg, #0D1F2D 0%, #1EB890 50%, #3B82F6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .impact-card {
            transition: transform 0.35s cubic-bezier(.22, 1, .36, 1), box-shadow 0.35s cubic-bezier(.22, 1, .36, 1);
        }

        .impact-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.09);
        }

        .transformation-item {
            transition: background 0.25s, box-shadow 0.25s;
        }

        .transformation-item:hover {
            background: white;
            box-shadow: 0 4px 20px rgba(37, 59, 91, 0.07);
        }

    </style>
</head>
<body class="bg-[#F7FAFC] text-[#111827] antialiased">

    <x-public-navbar />

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- 1. HERO                                                                 --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <section class="relative overflow-hidden" style="background: linear-gradient(135deg, #0D1F2D 0%, #0a3550 40%, #063d30 100%)">

        {{-- Decorative background --}}
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute top-0 right-0 w-[700px] h-[700px] rounded-full" style="background: radial-gradient(circle, rgba(30,184,144,0.12) 0%, transparent 65%);
                        transform: translate(30%,-30%)"></div>
            <div class="absolute bottom-0 left-0 w-[600px] h-[600px] rounded-full" style="background: radial-gradient(circle, rgba(59,130,246,0.10) 0%, transparent 65%);
                        transform: translate(-30%,30%)"></div>
            <svg class="absolute inset-0 w-full h-full" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="impact-grid" width="60" height="60" patternUnits="userSpaceOnUse">
                        <path d="M 60 0 L 0 0 0 60" fill="none" stroke="rgba(255,255,255,0.03)" stroke-width="1" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#impact-grid)" />
            </svg>
            {{-- Hexagon decorations --}}
            <svg class="absolute top-12 left-16 opacity-[0.07]" width="110" height="127" viewBox="0 0 120 138">
                <polygon points="60,0 120,34.5 120,103.5 60,138 0,103.5 0,34.5" fill="none" stroke="#1EB890" stroke-width="1.5" />
            </svg>
            <svg class="absolute top-28 left-32 opacity-[0.04]" width="64" height="74" viewBox="0 0 120 138">
                <polygon points="60,0 120,34.5 120,103.5 60,138 0,103.5 0,34.5" fill="none" stroke="#1EB890" stroke-width="1.5" />
            </svg>
            <svg class="absolute bottom-16 right-24 opacity-[0.05]" width="90" height="104" viewBox="0 0 120 138">
                <polygon points="60,0 120,34.5 120,103.5 60,138 0,103.5 0,34.5" fill="none" stroke="#60A5FA" stroke-width="1.5" />
            </svg>
        </div>

        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-32 text-center">

            {{-- Pill badge --}}
            <div class="reveal-fade inline-flex items-center gap-2.5 px-5 py-2 rounded-2xl border mb-8" style="background:rgba(30,184,144,0.12); border-color:rgba(30,184,144,0.30); color:#1EB890">
                <span class="w-2 h-2 rounded-full flex-shrink-0 animate-pulse" style="background:#1EB890"></span>
                <span class="text-sm font-semibold tracking-wide">المبادرة الاستراتيجية ٢٠٢٦</span>
            </div>

            {{-- Main title --}}
            <h1 class="reveal-fade font-black leading-none tracking-tight text-white mb-5" style="font-size:clamp(4rem,12vw,9rem); transition-delay:0.08s">
                عام
                <span style="background: linear-gradient(135deg, #1EB890 0%, #60A5FA 100%);
                             -webkit-background-clip: text; -webkit-text-fill-color: transparent;
                             background-clip: text">الأثر</span>
            </h1>

            {{-- Sub-headline --}}
            <p class="reveal-fade text-2xl sm:text-3xl font-light mb-8 max-w-2xl mx-auto leading-relaxed" style="color:rgba(255,255,255,0.75); transition-delay:0.16s">
                نقيس ما نصنعه… ونبني أثراً مستداماً
            </p>

            {{-- Transformation dots --}}
            <div class="reveal-fade flex flex-col sm:flex-row items-center justify-center gap-3 mb-12 text-sm" style="color:rgba(255,255,255,0.45); transition-delay:0.24s">
                <span>من تنفيذ المبادرات إلى استدامة نتائجها</span>
                <span class="hidden sm:block w-1.5 h-1.5 rounded-full flex-shrink-0" style="background:rgba(255,255,255,0.2)"></span>
                <span>من قياس الجهد إلى قياس الأثر الحقيقي</span>
                <span class="hidden sm:block w-1.5 h-1.5 rounded-full flex-shrink-0" style="background:rgba(255,255,255,0.2)"></span>
                <span>من أنشطة متفرقة إلى منظومة متكاملة</span>
            </div>

            {{-- CTAs --}}
            <div class="reveal-fade flex flex-wrap items-center justify-center gap-4" style="transition-delay:0.32s">
                <a href="{{ route('public.programs.index') }}" class="inline-flex items-center gap-3 px-8 py-4 rounded-2xl font-semibold text-white text-base
                          shadow-lg transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl" style="background: linear-gradient(135deg, #1EB890 0%, #0ea5e9 100%)">
                    استكشف البرامج
                    <svg class="w-5 h-5 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
                <a href="{{ route('public.paths.index') }}" class="inline-flex items-center gap-3 px-8 py-4 rounded-2xl font-semibold text-sm border
                          transition-all duration-300 hover:-translate-y-1" style="color:rgba(255,255,255,0.85); border-color:rgba(255,255,255,0.2); background:rgba(255,255,255,0.06)">
                    المسارات التدريبية
                </a>
            </div>

        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- 2. TRANSFORMATION — من الجهد إلى الأثر                               --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <section class="py-24 bg-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="text-center mb-16 reveal-fade">
                <p class="text-sm font-semibold uppercase tracking-widest mb-3" style="color:#1EB890">التحول المنشود</p>
                <h2 class="text-3xl sm:text-4xl font-bold mb-4" style="color:#111827">من الجهد إلى الأثر</h2>
                <p class="max-w-xl mx-auto leading-relaxed" style="color:#6B7280">
                    عام الأثر ينقل منظومة كفاءات من منطق تنفيذ الأنشطة إلى منطق قياس النتائج الحقيقية في حياة المستفيدين.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                @php
                $shifts = [
                [
                'from' => 'قياس الجهد',
                'to' => 'قياس الأثر',
                'icon' => '
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />',
                'color' => '#1EB890',
                'bg' => 'rgba(30,184,144,0.07)',
                'desc' => 'ننتقل من عدّ الأنشطة المنفّذة إلى قياس التغيير الفعلي في قدرات المستفيدين.',
                'delay' => '0s',
                ],
                [
                'from' => 'المبادرات',
                'to' => 'نتائج مستدامة',
                'icon' => '
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M13 10V3L4 14h7v7l9-11h-7z" />',
                'color' => '#3B82F6',
                'bg' => 'rgba(59,130,246,0.07)',
                'desc' => 'تتحول كل مبادرة من فعالية مؤقتة إلى قيمة مرصودة قابلة للتوسع والاستمرار.',
                'delay' => '0.1s',
                ],
                [
                'from' => 'التنفيذ',
                'to' => 'الاستدامة',
                'icon' => '
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />',
                'color' => '#8B5CF6',
                'bg' => 'rgba(139,92,246,0.07)',
                'desc' => 'نبني منظومة مستدامة تنتج أثراً متجدداً يخدم الأجيال القادمة.',
                'delay' => '0.2s',
                ],
                ];
                @endphp

                @foreach($shifts as $shift)
                <div class="transformation-item reveal-fade rounded-3xl border border-gray-100 p-8 text-right" style="background:#F3F7FB; transition-delay:{{ $shift['delay'] }}">

                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-6" style="background:{{ $shift['bg'] }}">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="{{ $shift['color'] }}">
                            {!! $shift['icon'] !!}
                        </svg>
                    </div>

                    <div class="flex items-center gap-3 mb-3 justify-end flex-wrap">
                        <span class="text-lg font-bold" style="color:#111827">{{ $shift['to'] }}</span>
                        <div class="flex items-center gap-1.5 text-sm" style="color:#6B7280">
                            <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                            <span class="line-through opacity-60">{{ $shift['from'] }}</span>
                        </div>
                    </div>

                    <p class="text-sm leading-relaxed" style="color:#6B7280">{{ $shift['desc'] }}</p>

                </div>
                @endforeach

            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- 3. FOUR PILLARS                                                         --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <section class="py-24" style="background:#F3F7FB">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="text-center mb-16 reveal-fade">
                <p class="text-sm font-semibold uppercase tracking-widest mb-3" style="color:#1EB890">الركائز الأربع</p>
                <h2 class="text-3xl sm:text-4xl font-bold" style="color:#111827">أركان الأثر</h2>
            </div>

            @php
            $pillars = [
            [
            'icon' => '
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />',
            'title' => 'أثر الإنسان',
            'desc' => 'تطوير القدرات البشرية وتحويلها إلى نتائج حقيقية موثّقة وقابلة للقياس تنعكس على مسارات المستفيدين المهنية.',
            'num' => '١',
            'color' => '#1EB890',
            'glow' => 'rgba(30,184,144,0.10)',
            'ring' => 'rgba(30,184,144,0.22)',
            'delay' => '0s',
            ],
            [
            'icon' => '
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z" />',
            'title' => 'أثر المبادرات',
            'desc' => 'تحويل المبادرات من فعاليات مؤقتة إلى قيمة مستدامة قابلة للقياس والتوسع والتأثير في المجتمع.',
            'num' => '٢',
            'color' => '#3B82F6',
            'glow' => 'rgba(59,130,246,0.10)',
            'ring' => 'rgba(59,130,246,0.22)',
            'delay' => '0.1s',
            ],
            [
            'icon' => '
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />',
            'title' => 'أثر الأداء',
            'desc' => 'رفع الكفاءة التشغيلية وجودة التنفيذ نحو أداء مؤسسي متميز ومستدام يعكس احترافية المنظومة.',
            'num' => '٣',
            'color' => '#8B5CF6',
            'glow' => 'rgba(139,92,246,0.10)',
            'ring' => 'rgba(139,92,246,0.22)',
            'delay' => '0.2s',
            ],
            [
            'icon' => '
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />',
            'title' => 'أثر المنظمة',
            'desc' => 'بناء منظمة راسخة قائمة على الحوكمة والشفافية والاستدامة المؤسسية ذات الأثر الممتد.',
            'num' => '٤',
            'color' => '#F59E0B',
            'glow' => 'rgba(245,158,11,0.10)',
            'ring' => 'rgba(245,158,11,0.22)',
            'delay' => '0.3s',
            ],
            ];
            @endphp

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach($pillars as $pillar)
                <div class="impact-card reveal-fade group relative bg-white rounded-3xl p-8 text-right border border-gray-100 overflow-hidden cursor-default" style="transition-delay:{{ $pillar['delay'] }}">

                    {{-- Number --}}
                    <div class="absolute top-5 left-5 text-6xl font-black opacity-[0.04] select-none pointer-events-none leading-none" style="color:#111827">
                        {{ $pillar['num'] }}
                    </div>

                    {{-- Hover glow --}}
                    <div class="absolute inset-0 rounded-3xl opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none" style="background: radial-gradient(ellipse at 50% -10%, {{ $pillar['glow'] }}, transparent 70%)"></div>

                    {{-- Bottom accent --}}
                    <div class="absolute bottom-0 right-0 left-0 h-[3px] rounded-b-3xl opacity-0 group-hover:opacity-100 transition-opacity duration-300" style="background: linear-gradient(to left, {{ $pillar['color'] }}, transparent)"></div>

                    {{-- Icon --}}
                    <div class="relative w-14 h-14 rounded-2xl flex items-center justify-center mb-6 transition-transform duration-300 group-hover:scale-110" style="background:{{ $pillar['glow'] }}; box-shadow: 0 0 0 1px {{ $pillar['ring'] }}">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="{{ $pillar['color'] }}">
                            {!! $pillar['icon'] !!}
                        </svg>
                    </div>

                    <h3 class="relative text-xl font-bold mb-3" style="color:#111827">{{ $pillar['title'] }}</h3>
                    <p class="relative text-sm leading-relaxed" style="color:#6B7280">{{ $pillar['desc'] }}</p>

                </div>
                @endforeach
            </div>

        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- 4. QUOTE BLOCK                                                          --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <section class="py-24 bg-white overflow-hidden">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="reveal-fade relative">
                {{-- Decorative quote mark --}}
                <div class="absolute inset-0 flex items-start justify-center select-none pointer-events-none" aria-hidden="true">
                    <span class="font-black" style="font-size:16rem; color:rgba(30,184,144,0.04); line-height:0.65">"</span>
                </div>
                <p class="relative text-3xl sm:text-4xl lg:text-5xl font-bold leading-snug gradient-text">
                    معاً… نصنع أثراً يُقاس، ويُبنى، ويستمر
                </p>
                <div class="mt-8 w-20 h-1 rounded-full mx-auto" style="background: linear-gradient(to left, #1EB890, #3B82F6)"></div>
                <p class="mt-8 text-base leading-relaxed max-w-2xl mx-auto" style="color:#6B7280">
                    عام الأثر ليس شعاراً — بل منهجية عمل تحوّل كل نشاط تدريبي وكل فرصة تطوعية إلى قيمة مقيسة وأثر موثّق في حياة إنسان حقيقي.
                </p>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- 5. STRATEGIC CONNECTION                                                 --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <section class="py-24" style="background: linear-gradient(180deg, #F3F7FB 0%, #EAF2FA 100%)">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col lg:flex-row items-center gap-14">

                {{-- Text (RIGHT in RTL) --}}
                <div class="w-full lg:w-1/2 text-right reveal-fade">
                    <p class="text-sm font-semibold uppercase tracking-widest mb-3" style="color:#1EB890">الإطار الاستراتيجي</p>
                    <h2 class="text-2xl sm:text-3xl font-bold mb-5" style="color:#111827">
                        الترجمة التنفيذية<br>للخطة الاستراتيجية
                    </h2>
                    <p class="leading-loose mb-8" style="color:#6B7280">
                        عام الأثر هو الترجمة التنفيذية للخطة الاستراتيجية، ينقل المنظومة من منطق الأنشطة إلى منطق النتائج، ومن قياس الجهد إلى قياس التغيير الحقيقي في حياة المستفيدين.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('public.programs.index') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl text-sm font-semibold text-white
                                  shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5" style="background:#253B5B">
                            البرامج الاستراتيجية
                        </a>
                        <a href="{{ route('public.paths.index') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl text-sm font-semibold border-2
                                  hover:bg-[#EAF2FA] transition-all duration-200" style="color:#253B5B; border-color:#c5ddef">
                            المسارات التدريبية
                        </a>
                    </div>
                </div>

                {{-- Strategic pillars grid (LEFT in RTL) --}}
                <div class="w-full lg:w-1/2 reveal-fade" style="transition-delay:0.15s">
                    @php
                    $stratPillars = [
                    ['icon' => '📈', 'label' => 'تعظيم الأثر التنموي', 'bg' => 'rgba(30,184,144,0.08)', 'color' => '#1EB890'],
                    ['icon' => '💻', 'label' => 'التحول الرقمي', 'bg' => 'rgba(59,130,246,0.08)', 'color' => '#3B82F6'],
                    ['icon' => '♻️', 'label' => 'الاستدامة المالية', 'bg' => 'rgba(139,92,246,0.08)', 'color' => '#8B5CF6'],
                    ['icon' => '🧠', 'label' => 'تطوير رأس المال البشري', 'bg' => 'rgba(245,158,11,0.08)', 'color' => '#F59E0B'],
                    ];
                    @endphp
                    <div class="grid grid-cols-2 gap-4">
                        @foreach($stratPillars as $sp)
                        <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm text-right
                                    hover:shadow-md hover:-translate-y-0.5 transition-all duration-300">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl mb-3" style="background:{{ $sp['bg'] }}">{{ $sp['icon'] }}</div>
                            <p class="text-sm font-semibold" style="color:#111827">{{ $sp['label'] }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- 6. FINAL CTA                                                            --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <section class="py-20 bg-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center reveal-fade">
            <h2 class="text-2xl sm:text-3xl font-bold mb-4" style="color:#111827">هل أنت مستعد لتكون جزءاً من الأثر؟</h2>
            <p class="mb-8 text-base leading-relaxed" style="color:#6B7280">
                انضم إلى آلاف المستفيدين الذين يصنعون أثراً حقيقياً من خلال مسارات كفاءات التدريبية والتطوعية.
            </p>
            <div class="flex flex-wrap items-center justify-center gap-4">
                @auth
                <a href="{{ route('portal.dashboard') }}" class="px-8 py-4 rounded-2xl text-base font-semibold text-white shadow-md hover:shadow-lg
                          transition-all duration-200 hover:-translate-y-0.5" style="background:#253B5B">
                    بوابتي
                </a>
                @else
                <a href="{{ route('register') }}" class="px-8 py-4 rounded-2xl text-base font-semibold text-white shadow-md hover:shadow-lg
                          transition-all duration-200 hover:-translate-y-0.5" style="background:#253B5B">
                    ابدأ رحلتك
                </a>
                <a href="{{ route('public.paths.index') }}" class="px-8 py-4 rounded-2xl text-base font-semibold border-2 bg-white
                          hover:bg-[#EAF2FA] transition-all duration-200 hover:-translate-y-0.5" style="color:#253B5B; border-color:#c5ddef">
                    استكشف المسارات
                </a>
                @endauth
            </div>
        </div>
    </section>

    <x-public-footer />

    <script>
        (function() {
            var io = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        io.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.10
            });
            document.querySelectorAll('.reveal-fade').forEach(function(el) {
                io.observe(el);
            });
        })();

    </script>

</body>
</html>
