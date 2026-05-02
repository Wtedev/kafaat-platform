{{--
    resources/views/components/public-footer.blade.php
    Shared dark footer — used on homepage and all public layout pages.
--}}
<footer style="background:#111827" class="min-w-0 overflow-x-hidden text-white">
    <div class="mx-auto max-w-7xl px-4 pb-[max(1.5rem,env(safe-area-inset-bottom,0px))] pt-10 sm:px-6 sm:pb-8 sm:pt-16 lg:px-8">
        <div class="mb-8 grid grid-cols-1 gap-8 sm:mb-12 sm:gap-10 md:grid-cols-2 lg:grid-cols-4">

            {{-- Brand --}}
            <div class="min-w-0 text-center sm:text-right lg:col-span-1">
                <a href="{{ route('home') }}" class="mb-3 inline-block text-xl font-bold text-white sm:mb-4 sm:text-2xl">كفاءات</a>
                <p class="mb-5 text-sm leading-relaxed text-gray-400 sm:mb-6">
                    منصة تدريب وتطوع متكاملة تسعى إلى بناء قدرات الشباب وتمكينهم من التميز في مساراتهم المهنية.
                </p>
                <div class="flex flex-wrap items-center justify-center gap-2.5 sm:justify-end sm:gap-3">
                    @foreach(['𝕏', 'in', 'f', '▶'] as $s)
                    <a href="#" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 text-sm text-gray-300 transition-colors hover:bg-white/20 sm:h-9 sm:w-9" aria-label="social">{{ $s }}</a>
                    @endforeach
                </div>
            </div>

            {{-- Quick Links --}}
            <div class="min-w-0 text-center sm:text-right">
                <h4 class="mb-3 text-sm font-bold text-white sm:mb-5">روابط سريعة</h4>
                <ul class="space-y-2.5 sm:space-y-3">
                    <li><a href="{{ route('home') }}" class="text-gray-400 hover:text-white text-sm transition-colors">الرئيسية</a></li>
                    <li><a href="{{ route('impact.index') }}" class="text-gray-400 hover:text-white text-sm transition-colors">عام الأثر</a></li>
                    <li><a href="{{ route('public.paths.index') }}" class="text-gray-400 hover:text-white text-sm transition-colors">المسارات التدريبية</a></li>
                    <li><a href="{{ route('public.programs.index') }}" class="text-gray-400 hover:text-white text-sm transition-colors">البرامج التدريبية</a></li>
                    <li><a href="{{ route('public.volunteering.index') }}" class="text-gray-400 hover:text-white text-sm transition-colors">الفرص التطوعية</a></li>
                </ul>
            </div>

            {{-- Platform --}}
            <div class="min-w-0 text-center sm:text-right">
                <h4 class="mb-3 text-sm font-bold text-white sm:mb-5">المنصة</h4>
                <ul class="space-y-2.5 sm:space-y-3">
                    @guest
                    <li><a href="{{ route('login') }}" class="text-gray-400 hover:text-white text-sm transition-colors">تسجيل الدخول</a></li>
                    <li><a href="{{ route('register') }}" class="text-gray-400 hover:text-white text-sm transition-colors">إنشاء حساب</a></li>
                    @else
                    @if(auth()->user()->canAccessFilamentAdmin())
                    <li><a href="{{ url('/admin') }}" class="text-gray-400 hover:text-white text-sm transition-colors">لوحة الإدارة</a></li>
                    @else
                    <li><a href="{{ route('portal.dashboard') }}" class="text-gray-400 hover:text-white text-sm transition-colors">بوابتي</a></li>
                    @endif
                    @endguest
                    <li><a href="{{ route('home') }}#faq" class="text-gray-400 hover:text-white text-sm transition-colors">الأسئلة الشائعة</a></li>
                    <li><a href="#" class="text-gray-400 hover:text-white text-sm transition-colors">سياسة الخصوصية</a></li>
                    <li><a href="#" class="text-gray-400 hover:text-white text-sm transition-colors">الشروط والأحكام</a></li>
                </ul>
            </div>

            {{-- Contact --}}
            <div class="min-w-0 text-center sm:text-right">
                <h4 class="mb-3 text-sm font-bold text-white sm:mb-5">تواصل معنا</h4>
                <ul class="space-y-2.5 text-sm text-gray-400 sm:space-y-3">
                    <li class="flex items-start justify-center gap-2 sm:justify-end sm:items-center">
                        <span class="min-w-0 break-all text-center sm:text-right" dir="ltr">info@kafaat.com</span>
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-500 sm:mt-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    </li>
                    <li class="flex items-center justify-center gap-2 sm:justify-end">
                        <span class="min-w-0 whitespace-normal break-words text-center sm:text-right" dir="ltr">+966 5X XXX XXXX</span>
                        <svg class="h-4 w-4 shrink-0 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                    </li>
                    <li class="flex items-start justify-center gap-2 sm:justify-end sm:items-center">
                        <span class="min-w-0 text-center sm:text-right">المملكة العربية السعودية</span>
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-500 sm:mt-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    </li>
                </ul>
            </div>

        </div>

        {{-- Divider + Copyright --}}
        <div class="flex flex-col items-center gap-3 border-t border-white/10 pt-6 text-center text-sm text-gray-500 sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:pt-8 sm:text-right">
            <p class="max-w-prose leading-relaxed sm:max-w-none">© {{ date('Y') }} كفاءات. جميع الحقوق محفوظة.</p>
            <div class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2 sm:justify-end">
                <a href="#" class="shrink-0 transition-colors hover:text-gray-300">سياسة الخصوصية</a>
                <a href="#" class="shrink-0 transition-colors hover:text-gray-300">الشروط والأحكام</a>
            </div>
        </div>

    </div>
</footer>
