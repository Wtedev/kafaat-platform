{{--
    resources/views/components/public-footer.blade.php
    Shared dark footer — used on homepage and all public layout pages.
--}}
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
                    <li><a href="{{ route('home') }}" class="text-gray-400 hover:text-white text-sm transition-colors">الرئيسية</a></li>
                    <li><a href="{{ route('impact.index') }}" class="text-gray-400 hover:text-white text-sm transition-colors">عام الأثر</a></li>
                    <li><a href="{{ route('public.paths.index') }}" class="text-gray-400 hover:text-white text-sm transition-colors">المسارات التدريبية</a></li>
                    <li><a href="{{ route('public.programs.index') }}" class="text-gray-400 hover:text-white text-sm transition-colors">البرامج التدريبية</a></li>
                    <li><a href="{{ route('public.volunteering.index') }}" class="text-gray-400 hover:text-white text-sm transition-colors">الفرص التطوعية</a></li>
                </ul>
            </div>

            {{-- Platform --}}
            <div class="text-right">
                <h4 class="font-bold text-white mb-5 text-sm uppercase tracking-wider">المنصة</h4>
                <ul class="space-y-3">
                    @guest
                    <li><a href="{{ route('login') }}" class="text-gray-400 hover:text-white text-sm transition-colors">تسجيل الدخول</a></li>
                    <li><a href="{{ route('register') }}" class="text-gray-400 hover:text-white text-sm transition-colors">إنشاء حساب</a></li>
                    @else
                    <li><a href="{{ route('portal.dashboard') }}" class="text-gray-400 hover:text-white text-sm transition-colors">بوابتي</a></li>
                    @endguest
                    <li><a href="{{ route('home') }}#faq" class="text-gray-400 hover:text-white text-sm transition-colors">الأسئلة الشائعة</a></li>
                    <li><a href="#" class="text-gray-400 hover:text-white text-sm transition-colors">سياسة الخصوصية</a></li>
                    <li><a href="#" class="text-gray-400 hover:text-white text-sm transition-colors">الشروط والأحكام</a></li>
                </ul>
            </div>

            {{-- Contact --}}
            <div class="text-right">
                <h4 class="font-bold text-white mb-5 text-sm uppercase tracking-wider">تواصل معنا</h4>
                <ul class="space-y-3 text-sm text-gray-400">
                    <li class="flex items-center gap-2 justify-end">
                        <span>info@kafaat.com</span>
                        <svg class="w-4 h-4 flex-shrink-0 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    </li>
                    <li class="flex items-center gap-2 justify-end">
                        <span>+966 5X XXX XXXX</span>
                        <svg class="w-4 h-4 flex-shrink-0 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                    </li>
                    <li class="flex items-center gap-2 justify-end">
                        <span>المملكة العربية السعودية</span>
                        <svg class="w-4 h-4 flex-shrink-0 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    </li>
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
