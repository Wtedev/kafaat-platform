{{--
    resources/views/components/public-footer.blade.php
    Public footer — dark theme (headquarters/map live in public-headquarters).
--}}
@php
    $site = config('site');
    $telHref = '+' . preg_replace('/\D+/', '', (string) ($site['contact_phone_e164'] ?? ''));
    $aboutHref = request()->routeIs('home') ? '#about' : route('home') . '#about';
    $legalName = $site['legal_name'] ?? 'جمعية كفاءات لبناء قدرات الشباب';
    $license = $site['license'] ?? [];
    $licenseAuthority = $license['authority'] ?? null;
    $licenseNumber = $license['number'] ?? null;
    $hasLicense = filled($licenseAuthority) && filled($licenseNumber);
@endphp
<footer class="relative min-w-0 overflow-x-hidden border-t border-white/10 bg-gradient-to-b from-[#111827] via-[#0f172a] to-[#0b1220] text-white antialiased">
    <div class="mx-auto max-w-7xl px-4 pb-[max(1.5rem,env(safe-area-inset-bottom,0px))] pt-12 sm:px-6 sm:pb-10 sm:pt-16 lg:px-8">

        <div class="grid grid-cols-1 gap-10 sm:gap-12 md:grid-cols-2 lg:grid-cols-12 lg:gap-x-10 lg:gap-y-12">

            {{-- Brand --}}
            <div class="text-center sm:text-right lg:col-span-4">
                <a href="{{ route('home') }}" class="inline-block transition-opacity hover:opacity-90" aria-label="كفاءات — الرئيسية">
                    <img src="{{ asset(config('brand.logos.kafaat_white')) }}" alt="كفاءات" class="h-10 w-auto" width="132" height="40" />
                </a>
                <p class="mx-auto mt-3 max-w-sm text-sm leading-relaxed text-gray-400 sm:mx-0 sm:max-w-none">
                    {{ $site['brand_summary'] ?? '' }}
                </p>
                <div class="mt-5 flex flex-wrap items-center justify-center gap-2.5 sm:justify-start">
                    @foreach($site['social'] ?? [] as $social)
                        <a
                            href="{{ $social['url'] }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border border-white/10 bg-white/10 text-gray-300 transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#1a9399]/20 hover:text-white hover:shadow-lg hover:shadow-[#1a9399]/20"
                            aria-label="{{ $social['label'] }}"
                        >
                            @switch($social['key'] ?? '')
                                @case('youtube')
                                    <svg class="h-[1.1rem] w-[1.1rem]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                                    @break
                                @case('linkedin')
                                    <svg class="h-[1.05rem] w-[1.05rem]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 4.127 0c0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                                    @break
                                @case('x')
                                    <svg class="h-[1rem] w-[1rem]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                                    @break
                                @case('tiktok')
                                    <svg class="h-[1.05rem] w-[0.92rem]" viewBox="0 0 448 512" fill="currentColor" aria-hidden="true" preserveAspectRatio="xMidYMid meet"><path d="M448,209.91a210.06,210.06,0,0,1-122.77-39.25V349.38A162.55,162.55,0,1,1,185,188.31V278.2a74.62,74.62,0,1,0,52.23,71.18V0l88,0a121.18,121.18,0,0,0,1.86,22.17h0A122.18,122.18,0,0,0,381,102.39a121.43,121.43,0,0,0,67,20.14Z"/></svg>
                                    @break
                                @case('instagram')
                                    <svg class="h-[1.1rem] w-[1.1rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18.5" height="18.5" x="2.75" y="2.75" rx="5" ry="5"/><circle cx="12" cy="12" r="3.35"/><circle cx="17.35" cy="6.65" r="0.85" fill="currentColor" stroke="none"/></svg>
                                    @break
                                @default
                                    <span class="text-xs font-semibold" aria-hidden="true">↗</span>
                            @endswitch
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Quick links --}}
            <div class="text-center sm:text-right lg:col-span-2">
                <h4 class="text-xs font-bold uppercase tracking-wider text-[#1a9399]">روابط سريعة</h4>
                <ul class="mt-4 space-y-2.5 text-sm">
                    <li><a href="{{ route('home') }}" class="text-gray-400 transition-colors hover:text-white">الرئيسية</a></li>
                    <li><a href="{{ $aboutHref }}" class="text-gray-400 transition-colors hover:text-white">عن كفاءات</a></li>
                    @if (Route::has('public.tracks.index'))
                    <li><a href="{{ route('public.tracks.index') }}" class="text-gray-400 transition-colors hover:text-white">مسارات الكفاءة</a></li>
                    @endif
                    <li><a href="{{ route('public.volunteering.index') }}" class="text-gray-400 transition-colors hover:text-white">الفرص التطوعية</a></li>
                    @if(Route::has('public.governance.index'))
                    <li><a href="{{ route('public.governance.index') }}" class="text-gray-400 transition-colors hover:text-white">الحوكمة</a></li>
                    @endif
                    @if(Route::has('public.media.index'))
                    <li><a href="{{ route('public.media.index') }}" class="text-gray-400 transition-colors hover:text-white">المركز الإعلامي</a></li>
                    @endif
                </ul>
            </div>

            {{-- Platform --}}
            <div class="text-center sm:text-right lg:col-span-3">
                <h4 class="text-xs font-bold uppercase tracking-wider text-[#1a9399]">المنصة والخدمات</h4>
                <ul class="mt-4 space-y-2.5 text-sm">
                    @guest
                    <li><a href="{{ route('login') }}" class="text-gray-400 transition-colors hover:text-white">تسجيل الدخول</a></li>
                    <li><a href="{{ route('register') }}" class="text-gray-400 transition-colors hover:text-white">إنشاء حساب</a></li>
                    @else
                    @if(auth()->user()->canAccessFilamentAdmin())
                    <li><a href="{{ url('/admin') }}" class="text-gray-400 transition-colors hover:text-white">لوحة الإدارة</a></li>
                    @else
                    <li><a href="{{ route('portal.dashboard') }}" class="text-gray-400 transition-colors hover:text-white">حسابي</a></li>
                    @endif
                    @endguest
                    @if(Route::has('public.regulations.index'))
                    <li><a href="{{ route('public.regulations.index') }}" class="text-gray-400 transition-colors hover:text-white">اللوائح والأنظمة</a></li>
                    @endif
                    @if(Route::has('public.news.index'))
                    <li><a href="{{ route('public.news.index') }}" class="text-gray-400 transition-colors hover:text-white">الأخبار</a></li>
                    @endif
                    <li><a href="{{ route('home') }}#faq" class="text-gray-400 transition-colors hover:text-white">الأسئلة الشائعة</a></li>
                    <li><a href="{{ route('public.privacy') }}" class="text-gray-400 transition-colors hover:text-white">سياسة الخصوصية</a></li>
                    <li><a href="{{ route('public.terms') }}" class="text-gray-400 transition-colors hover:text-white">الشروط والأحكام</a></li>
                </ul>
            </div>

            {{-- Contact --}}
            <div class="text-center sm:text-right lg:col-span-3">
                <h4 class="text-xs font-bold uppercase tracking-wider text-[#1a9399]">تواصل معنا</h4>
                <ul class="mt-4 space-y-3 text-sm">
                    <li class="flex flex-wrap items-center justify-center gap-2 sm:flex-nowrap sm:justify-end">
                        <a href="mailto:{{ $site['contact_email'] }}" class="min-w-0 break-all font-medium text-gray-200 transition-colors hover:text-white" dir="ltr">{{ $site['contact_email'] }}</a>
                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/5" aria-hidden="true">
                            <svg class="h-4 w-4 text-[#1a9399]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                        </span>
                    </li>
                    <li class="flex flex-wrap items-center justify-center gap-2 sm:flex-nowrap sm:justify-end">
                        <a href="tel:{{ $telHref }}" class="min-w-0 font-medium text-gray-200 transition-colors hover:text-white" dir="ltr">{{ $site['contact_phone_display'] ?? $site['contact_phone_local'] }}</a>
                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/5" aria-hidden="true">
                            <svg class="h-4 w-4 text-[#1a9399]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Bottom bar --}}
        <div class="mt-14 border-t border-white/10 pt-8">
            @if($hasLicense)
            <div class="mb-8 flex justify-center sm:justify-start">
                <div class="flex w-full max-w-xl items-center gap-3 rounded-2xl border border-white/10 bg-gradient-to-l from-white/[0.06] to-white/[0.02] p-3.5 shadow-inner sm:gap-4 sm:p-4">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-[#1a9399]/25 bg-[#1a9399]/10 text-[#1a9399]" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1 text-right">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-[#1a9399]">جهة الإشراف</p>
                        <p class="mt-0.5 text-sm leading-snug text-gray-200">تتبع {{ $licenseAuthority }}</p>
                    </div>
                    <div class="shrink-0 rounded-xl border border-[#1a9399]/20 bg-[#1a9399]/10 px-3 py-2 text-center">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-[#1a9399]">الترخيص</p>
                        <p class="mt-0.5 text-base font-bold tabular-nums text-white" dir="ltr">{{ $licenseNumber }}</p>
                    </div>
                </div>
            </div>
            @elseif(filled($site['license_notice'] ?? null))
            <p class="mb-8 text-center text-sm leading-relaxed text-gray-400 sm:text-right">{{ $site['license_notice'] }}</p>
            @endif

            <div class="flex flex-col items-center gap-4 text-center text-sm text-gray-500 sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:text-right">
                <p class="leading-relaxed">© {{ date('Y') }} {{ $legalName }}. جميع الحقوق محفوظة.</p>
                <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 sm:justify-end">
                    <a href="{{ route('public.privacy') }}" class="font-medium text-gray-400 transition-colors hover:text-white">سياسة الخصوصية</a>
                    <a href="{{ route('public.terms') }}" class="font-medium text-gray-400 transition-colors hover:text-white">الشروط والأحكام</a>
                </div>
            </div>
        </div>
    </div>

</footer>
