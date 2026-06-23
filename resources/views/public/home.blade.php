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

        .vm-card {
            transition: transform 0.35s cubic-bezier(.22, 1, .36, 1),
                box-shadow 0.35s cubic-bezier(.22, 1, .36, 1);
        }

        .vm-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 24px 48px rgba(37, 59, 91, 0.12);
        }

    </style>
</head>
<body class="bg-[#F7FAFC] text-[#111827] antialiased font-sans">

    @php
    $homeAboutHref = request()->routeIs('home') ? '#about' : route('home') . '#about';
    @endphp

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 1. NAVBAR                                                           --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <x-public-navbar />


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 2. HERO SECTION                                                     --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section style="background: linear-gradient(150deg, #EEF5FB 0%, #F3F7FB 55%, #e9eff6 100%)">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28">
            <div class="flex flex-col lg:flex-row items-center gap-16">

                {{-- ── Text (first child = RIGHT in RTL) ── --}}
                <div class="w-full lg:w-[54%] text-right">

                    {{-- Pill badge --}}
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl text-sm font-medium mb-6 border" style="background:#e9eff6; color:#335483; border-color:#c5d4e4">
                        <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:#1a9399"></span>
                        جمعية كفاءات لبناء قدرات الشباب
                    </div>

                    {{-- Headline --}}
                    <h1 class="text-4xl sm:text-5xl lg:text-[3.4rem] font-bold leading-snug mb-5" style="color:#111827">
                        نُمكّن الشباب… و<span style="color:#335483">نصنع الأثر</span>
                    </h1>

                    {{-- Subtitle --}}
                    <p class="text-lg leading-relaxed mb-8 max-w-lg" style="color:#6B7280">
                        جمعية أهلية تعمل على تأهيل الشباب وبناء مهاراتهم، وتوسيع مشاركتهم المجتمعية عبر برامج تدريبية وفرص تطوعية وشراكات مؤسسية في خدمة المجتمع.
                    </p>

                    {{-- CTA Buttons --}}
                    <div class="flex flex-wrap gap-4 mb-10">
                        <a href="{{ route('public.programs.index') }}" class="px-7 py-3.5 rounded-2xl text-base font-semibold text-white shadow-md hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5" style="background: linear-gradient(135deg, #335483 0%, #406688 100%)">
                            استكشف برامجنا
                        </a>
                        <a href="{{ $homeAboutHref }}" class="px-7 py-3.5 rounded-2xl text-base font-semibold border-2 bg-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#e9eff6]" style="color:#335483; border-color:#c5d4e4">
                            عن الجمعية
                        </a>
                    </div>

                    {{-- Trust indicators --}}
                    <div class="flex flex-wrap gap-5 text-sm" style="color:#6B7280">
                        @foreach(['برامج تأهيل وتدريب', 'فرص تطوع مجتمعي', 'شراكات وأثر مستدام'] as $trust)
                        <div class="flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background:#1a9399"></span>
                            {{ $trust }}
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- ── مجالات عمل الجمعية (second child = LEFT in RTL) ── --}}
                <div class="w-full lg:w-[46%] flex justify-center lg:justify-start">
                    <div class="relative w-full max-w-md">

                        <div class="absolute inset-6 rounded-3xl blur-2xl opacity-50" style="background:radial-gradient(ellipse,#c5d4e4,transparent)"></div>

                        <div class="relative bg-white rounded-3xl shadow-2xl p-7 space-y-4 border border-white">

                            <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                                <div class="w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0" style="background:#e9eff6">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="#335483">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs mb-0.5" style="color:#6B7280">مجالات عملنا</p>
                                    <p class="text-sm font-bold" style="color:#111827">في خدمة الشباب والمجتمع</p>
                                </div>
                            </div>

                            <a href="{{ route('public.paths.index') }}" class="flex items-center gap-4 p-4 rounded-2xl border border-gray-100 hover:border-[#c5d4e4] hover:bg-[#f5f8fb] transition-all group">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-white shadow-sm" style="background:#e9eff6">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="#335483">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" /></svg>
                                </div>
                                <div class="flex-1 text-right min-w-0">
                                    <p class="text-sm font-bold" style="color:#335483">مسارات التأهيل</p>
                                    <p class="text-xs mt-0.5" style="color:#6B7280">رحلات تعليمية متكاملة</p>
                                </div>
                                <svg class="w-4 h-4 rotate-180 opacity-40 group-hover:opacity-100 transition-opacity" style="color:#335483" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                            </a>

                            <a href="{{ route('public.programs.index') }}" class="flex items-center gap-4 p-4 rounded-2xl border-2 transition-all group" style="border-color:#dceaf7; background: linear-gradient(145deg, #f5f8fb 0%, #e9eff6 100%)">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-white shadow-sm">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="#335483">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                                </div>
                                <div class="flex-1 text-right min-w-0">
                                    <p class="text-sm font-bold" style="color:#335483">البرامج</p>
                                    <p class="text-xs mt-0.5" style="color:#6B7280">دورات وورش ولقاءات</p>
                                </div>
                                <svg class="w-4 h-4 rotate-180 opacity-40 group-hover:opacity-100 transition-opacity" style="color:#335483" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                            </a>

                            <a href="{{ route('public.volunteering.index') }}" class="flex items-center gap-4 p-4 rounded-2xl border border-gray-100 hover:border-[#f5dfa8] hover:bg-[#fef6e6]/50 transition-all group">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-[#fef6e6]">
                                    <svg class="w-5 h-5 text-brand-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                </div>
                                <div class="flex-1 text-right min-w-0">
                                    <p class="text-sm font-bold" style="color:#111827">الفرص التطوعية</p>
                                    <p class="text-xs mt-0.5" style="color:#6B7280">شارك في خدمة المجتمع</p>
                                </div>
                                <svg class="w-4 h-4 rotate-180 opacity-40 group-hover:opacity-100 transition-opacity text-brand-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                            </a>
                        </div>

                        <div class="absolute -bottom-4 -left-4 bg-white rounded-2xl shadow-lg px-4 py-3 flex items-center gap-3 border border-gray-50">
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0" style="background:#e9eff6">
                                <svg class="w-4 h-4" style="color:#335483" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-gray-800">منذ تأسيسنا</p>
                                <p class="text-xs" style="color:#6B7280">نخدم الشباب والمجتمع</p>
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
    <section id="about" class="py-20 bg-white scroll-mt-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Heading --}}
            <div class="text-center mb-14">
                <p class="text-sm font-semibold uppercase tracking-widest mb-3" style="color:#1a9399">من نحن</p>
                <h2 class="text-3xl sm:text-4xl font-bold mb-4" style="color:#111827">جمعية كفاءات</h2>
                <p class="text-lg leading-relaxed max-w-2xl mx-auto" style="color:#6B7280">
                    جمعية أهلية غير ربحية تُعنى ببناء قدرات الشباب وتأهيلهم للمشاركة الفاعلة في المجتمع، عبر برامج تدريبية نوعية وفرص تطوعية وشراكات مؤسسية محلية.
                </p>
            </div>

            {{-- الرؤية والرسالة --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8 max-w-5xl mx-auto">

                {{-- الرؤية --}}
                <div class="vm-card relative overflow-hidden rounded-3xl p-8 sm:p-10 text-right border border-gray-100 bg-white shadow-sm">
                    <div class="absolute top-0 left-0 w-full h-1 rounded-t-3xl" style="background: linear-gradient(to left, #335483, #1a9399)"></div>
                    <div class="absolute -top-6 -left-4 text-[7rem] font-black leading-none select-none pointer-events-none" style="color:rgba(51,84,131,0.04)" aria-hidden="true">ر</div>

                    <div class="relative">
                        <div class="flex items-center justify-end gap-3 mb-6">
                            <span class="text-xs font-bold tracking-widest uppercase px-3 py-1.5 rounded-xl" style="background:#e9eff6; color:#335483">رؤيتنا</span>
                            <div class="w-14 h-14 rounded-2xl flex items-center justify-center shadow-sm" style="background: linear-gradient(145deg, #e9eff6, #DCE8F5)">
                                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="#335483">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                            </div>
                        </div>
                        <p class="text-xl sm:text-2xl font-bold leading-relaxed" style="color:#111827">
                            نصنع الأثر بشباب ملهم ومتمكن
                        </p>
                    </div>
                </div>

                {{-- الرسالة --}}
                <div class="vm-card relative overflow-hidden rounded-3xl p-8 sm:p-10 text-right border border-gray-100 shadow-sm" style="background: linear-gradient(160deg, #ffffff 0%, #f5f8fb 55%, #e9eff6 100%)">
                    <div class="absolute top-0 left-0 w-full h-1 rounded-t-3xl" style="background: linear-gradient(to left, #1a9399, #1a9399)"></div>
                    <div class="absolute -top-6 -left-4 text-[7rem] font-black leading-none select-none pointer-events-none" style="color:rgba(60,184,120,0.06)" aria-hidden="true">م</div>

                    <div class="relative">
                        <div class="flex items-center justify-end gap-3 mb-6">
                            <span class="text-xs font-bold tracking-widest uppercase px-3 py-1.5 rounded-xl bg-[#e6f5f6] text-brand-secondary">رسالتنا</span>
                            <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-white shadow-sm">
                                <svg class="w-7 h-7 text-brand-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg>
                            </div>
                        </div>
                        <p class="text-base sm:text-lg font-medium leading-loose" style="color:#374151">
                            بناء كفاءات الشباب وتعزيز قدراتهم وتأهيلهم للمشاركة المجتمعية وفق عمل مؤسسي وشراكات تكاملية
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </section>


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 4. STATISTICS SECTION                                               --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section class="py-6 px-4 sm:px-6">
        <div class="max-w-7xl mx-auto">
            <div class="rounded-3xl py-16 px-8 sm:px-14" style="background: linear-gradient(135deg, #243a55 0%, #335483 60%, #3d6589 100%)">

                {{-- Decorative circles --}}
                <div class="relative overflow-hidden rounded-3xl">
                    <div class="absolute -top-10 -left-8 w-48 h-48 rounded-full bg-white opacity-5"></div>
                    <div class="absolute bottom-0 right-1/4 w-64 h-64 rounded-full opacity-5" style="background:#335483"></div>
                </div>

                <div class="relative z-10 text-center mb-12">
                    <h2 class="text-3xl font-bold text-white mb-2">أرقام كفاءات</h2>
                    <p class="text-sm" style="color:rgba(255,255,255,0.65)">نتائج نعتز بها</p>
                </div>

                @php
                $stats = [
                ['value' => '+١٢٠٠', 'label' => 'المستفيدون'],
                ['value' => '٣٨', 'label' => 'المسارات'],
                ['value' => '٦٥', 'label' => 'البرامج'],
                ['value' => '٤٢', 'label' => 'الفرص التطوعية'],
                ['value' => '+٨٠٠', 'label' => 'الشهادات'],
                ['value' => '٢٥', 'label' => 'الشركاء'],
                ];
                @endphp

                <div class="relative z-10 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-8">
                    @foreach($stats as $stat)
                    <div class="text-center">
                        <div class="text-4xl font-bold text-white mb-1 tabular-nums">{{ $stat['value'] }}</div>
                        <div class="text-sm" style="color:rgba(255,255,255,0.65)">{{ $stat['label'] }}</div>
                    </div>
                    @endforeach
                </div>

            </div>
        </div>
    </section>


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 5. WORK AREAS SECTION                                               --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section id="work" class="py-20 bg-[#F7FAFC] scroll-mt-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <p class="text-sm font-semibold uppercase tracking-widest mb-3" style="color:#1a9399">ماذا نقدّم</p>
                <h2 class="text-3xl sm:text-4xl font-bold mb-4" style="color:#111827">برامجنا وخدماتنا</h2>
                <p class="text-lg leading-relaxed max-w-2xl mx-auto" style="color:#6B7280">
                    مسارات تأهيلية وبرامج تدريبية وفرص تطوعية تُسهم في بناء قدرات الشباب وتمكينهم من المشاركة المجتمعية.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
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
                'title' => 'مسارات التأهيل',
                'badge' => null,
                'desc' => 'مسارات تعليمية متدرّجة تجمع عدة برامج في رحلة تأهيل متكاملة للمستفيد.',
                'href' => route('public.paths.index'),
                'color' => '#1a9399',
                'bg' => '#e6f5f6',
                'soon' => false,
                ],
                [
                'title' => 'البرامج',
                'badge' => null,
                'desc' => 'دورات وورش ولقاءات تدريبية متنوّعة في مهارات ومجالات يحددها فريق الجمعية.',
                'href' => route('public.programs.index'),
                'color' => '#fbbb2e',
                'bg' => '#fef6e6',
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


    {{-- removed old "about platform" section --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 7. NEWS & EVENTS SECTION                                            --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section id="news" class="scroll-mt-24 py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex items-end justify-between mb-10">
                <a href="{{ route('public.news.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold hover:underline" style="color:#335483">
                    عرض كل الأخبار
                    <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </a>
                <div class="text-right">
                    <p class="text-sm font-semibold mb-1" style="color:#1a9399">آخر التحديثات</p>
                    <h2 class="text-2xl font-bold" style="color:#111827">الأخبار والفعاليات</h2>
                </div>
            </div>

            @php
            $newsBgs = [
            'linear-gradient(135deg, #e9eff6, #DCE8F5)',
            'linear-gradient(135deg, #e6f5f6, #c5e8ea)',
            'linear-gradient(135deg, #fef6e6, #f5dfa8)',
            ];
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @forelse ($news as $i => $item)
                <a href="{{ route('public.news.show', $item->slug) }}" class="group bg-white rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 overflow-hidden block">
                    @if ($item->image)
                    <div class="h-48 overflow-hidden">
                        <img src="{{ $item->imagePublicUrl() }}" alt="{{ $item->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                    </div>
                    @else
                    <div class="h-48 flex items-center justify-center" style="background: {{ $newsBgs[$i % 3] }}">
                        <svg class="w-12 h-12 opacity-25" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color:#335483">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" /></svg>
                    </div>
                    @endif
                    <div class="p-6 text-right">
                        <div class="flex items-center justify-between mb-3">
                            @if ($item->published_at)
                            <span class="text-xs" style="color:#6B7280">{{ $item->published_at->format('Y/m/d') }}</span>
                            @else
                            <span></span>
                            @endif
                            @if ($item->category)
                            <x-news-category-badge :category="$item->category" />
                            @endif
                        </div>
                        <h3 class="font-bold text-base mb-2 line-clamp-2 group-hover:text-[#335483] transition-colors" style="color:#111827">{{ $item->title }}</h3>
                        @if ($item->excerpt)
                        <p class="text-sm line-clamp-3" style="color:#6B7280">{{ $item->excerpt }}</p>
                        @endif
                    </div>
                </a>
                @empty
                <div class="col-span-3 bg-white rounded-3xl border border-dashed border-gray-200 p-10 text-center" style="color:#6B7280">
                    لا توجد أخبار منشورة حالياً.
                </div>
                @endforelse
            </div>
        </div>
    </section>


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 7.5. IMPACT YEAR — عام الأثر (Strategic Premium Section)           --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section id="impact-year" class="scroll-mt-24">

        {{-- ─── Hero Block ────────────────────────────────────────────────── --}}
        <div class="relative overflow-hidden" style="background: linear-gradient(135deg, #002a30 0%, #004a54 40%, #00616f 100%)">

            {{-- Decorative abstract shapes (Saudi geometric identity) --}}
            <div class="absolute inset-0 overflow-hidden pointer-events-none">
                <div class="absolute top-0 right-0 w-[700px] h-[700px] rounded-full" style="background: radial-gradient(circle, rgba(0,97,111,0.12) 0%, transparent 65%);
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
                    <polygon points="60,0 120,34.5 120,103.5 60,138 0,103.5 0,34.5" fill="none" stroke="#00616f" stroke-width="1.5" />
                </svg>
                <svg class="absolute top-24 left-28 opacity-[0.04]" width="68" height="79" viewBox="0 0 120 138">
                    <polygon points="60,0 120,34.5 120,103.5 60,138 0,103.5 0,34.5" fill="none" stroke="#00616f" stroke-width="1.5" />
                </svg>
                <svg class="absolute bottom-16 right-24 opacity-[0.05]" width="90" height="104" viewBox="0 0 120 138">
                    <polygon points="60,0 120,34.5 120,103.5 60,138 0,103.5 0,34.5" fill="none" stroke="#007a88" stroke-width="1.5" />
                </svg>
            </div>

            {{-- Hero content --}}
            <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-28 text-center">

                {{-- Pill label --}}
                <div class="reveal-fade inline-flex items-center gap-2.5 px-5 py-2 rounded-2xl border mb-8" style="background:rgba(0,97,111,0.12); border-color:rgba(0,97,111,0.30); color:#00616f">
                    <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:#00616f; animation:pulse 2s infinite"></span>
                    <span class="text-sm font-semibold tracking-wide">المبادرة الاستراتيجية ٢٠٢٦</span>
                </div>

                {{-- Main title --}}
                <div class="reveal-fade mb-5" style="transition-delay:0.08s">
                    <img
                        src="{{ asset(config('brand.logos.impact_year')) }}"
                        alt="عام الأثر"
                        class="mx-auto h-14 sm:h-16 md:h-20 w-auto"
                    />
                </div>

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
                    <a href="{{ route('impact.index') }}" class="inline-flex items-center gap-3 px-8 py-4 rounded-2xl font-semibold text-white
                              text-base shadow-lg transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl" style="background: linear-gradient(135deg, #00616f 0%, #004a54 100%)">
                        استكشف أثر كفاءات
                        <svg class="w-5 h-5 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>

            </div>
        </div>
        {{-- / Hero Block --}}

    </section>
    {{-- / عام الأثر --}}


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 8. ANNUAL REPORT SECTION                                            --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section class="py-8 px-4 sm:px-6">
        <div class="max-w-7xl mx-auto">
            <div class="relative rounded-3xl overflow-hidden" style="background: linear-gradient(135deg, #111827 0%, #335483 60%, #2a4566 100%)">

                {{-- Decorative shapes --}}
                <div class="absolute top-0 left-0 w-56 h-56 rounded-full -translate-x-1/3 -translate-y-1/3 bg-white opacity-5"></div>
                <div class="absolute bottom-0 right-1/3 w-72 h-72 rounded-full translate-y-1/2 opacity-5" style="background:#335483"></div>

                <div class="relative z-10 flex flex-col md:flex-row items-center justify-between px-10 sm:px-16 py-14 gap-10">

                    {{-- Text (right in RTL) --}}
                    <div class="text-right">
                        <div class="text-8xl font-black leading-none mb-1" style="color:rgba(255,255,255,0.12)">٢٠٢٥</div>
                        <h2 class="text-3xl font-bold text-white mb-3">التقرير السنوي</h2>
                        <p class="leading-relaxed max-w-md" style="color:rgba(255,255,255,0.65)">
                            تقرير شامل يرصد إنجازات جمعية كفاءات خلال عام ٢٠٢٥: برامجها التدريبية، عملها التطوعي، وأثرها المجتمعي.
                        </p>
                    </div>

                    {{-- Download block (left in RTL) — يوجّه لصفحة عام الأثر إلى حين توفر ملف التقرير --}}
                    <div class="flex-shrink-0">
                        <a href="{{ route('impact.index') }}" class="w-40 h-40 rounded-3xl flex flex-col items-center justify-center gap-3 cursor-pointer hover:bg-white/20 transition-colors text-white no-underline" style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2)" aria-label="صفحة عام الأثر — التقرير السنوي قيد الإعداد">
                            <svg class="w-10 h-10 text-white opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            <span class="text-white text-sm font-semibold">تحميل التقرير</span>
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </section>


    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- 9. PARTNERS SECTION                                                 --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section class="py-20 bg-white" dir="rtl">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="text-center mb-12">
                <h2 class="text-2xl sm:text-3xl font-bold" style="color:#111827">شركاؤنا</h2>
                <p class="mx-auto mt-3 max-w-xl text-sm" style="color:#6B7280">مؤسسات وشركات نفتخر بشراكتها معنا في بناء قدرات الشباب.</p>
            </div>

            @if ($partners->isEmpty())
            <div class="max-w-2xl mx-auto text-center rounded-3xl border border-dashed border-gray-200 bg-[#F7FAFC] py-14 px-6 text-sm" style="color:#6B7280">
                سيتم عرض شعارات الشركاء هنا عند إضافتهم من لوحة التحكم.
            </div>
            @else
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 sm:gap-6">
                @foreach ($partners as $partner)
                @php
                $logoUrl = $partner->logoPublicUrl();
                $hasLink = filled($partner->website_url);
                $cardClass = 'group flex w-full max-w-[180px] flex-col items-center justify-center rounded-2xl border border-gray-100 bg-white px-4 py-6 shadow-sm transition-all duration-200 hover:border-[#c5d4e4] hover:shadow-md min-h-[100px] sm:min-h-[120px]';
                @endphp
                <div class="flex justify-center">
                    @if ($hasLink)
                    <a href="{{ $partner->website_url }}" target="_blank" rel="noopener noreferrer" class="{{ $cardClass }} cursor-pointer">
                        @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $partner->name }}" class="max-h-14 sm:max-h-16 w-auto max-w-full object-contain opacity-90 transition-opacity group-hover:opacity-100" loading="lazy" />
                        <span class="mt-3 line-clamp-2 text-center text-[11px] sm:text-xs font-medium" style="color:#6B7280">{{ $partner->name }}</span>
                        @else
                        <span class="text-center text-xs sm:text-sm font-semibold leading-snug px-1" style="color:#335483">{{ $partner->name }}</span>
                        @endif
                    </a>
                    @else
                    <div class="{{ $cardClass }}">
                        @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $partner->name }}" class="max-h-14 sm:max-h-16 w-auto max-w-full object-contain opacity-90" loading="lazy" />
                        <span class="mt-3 line-clamp-2 text-center text-[11px] sm:text-xs font-medium" style="color:#6B7280">{{ $partner->name }}</span>
                        @else
                        <span class="text-center text-xs sm:text-sm font-semibold leading-snug px-1" style="color:#335483">{{ $partner->name }}</span>
                        @endif
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
            @endif

        </div>
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
