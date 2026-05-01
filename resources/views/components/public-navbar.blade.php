{{--
    resources/views/components/public-navbar.blade.php
    Shared public navbar — used on the standalone homepage AND all public layout pages.
    Self-contained: includes inline <script> for hamburger toggle + scroll shadow.
--}}
@php
$newsHref = request()->routeIs('home') ? '#news' : route('home') . '#news';
$faqHref = request()->routeIs('home') ? '#faq' : route('home') . '#faq';
@endphp

<header id="pub-nav" class="sticky top-0 z-50 bg-white/95 backdrop-blur-sm border-b border-slate-100 shadow-sm transition-shadow duration-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 gap-6">

            {{-- Logo --}}
            <a href="{{ route('home') }}" class="text-2xl font-bold tracking-tight flex-shrink-0" style="color:#253B5B">كفاءات</a>

            {{-- Desktop Nav --}}
            <nav class="hidden lg:flex items-center gap-7 text-sm font-medium" style="color:#6B7280">
                <a href="{{ route('home') }}" class="hover:text-[#253B5B] transition-colors {{ request()->routeIs('home') ? 'font-semibold' : '' }}" @if(request()->routeIs('home')) style="color:#253B5B" @endif>الرئيسية</a>

                <a href="{{ route('impact.index') }}" class="hover:text-[#253B5B] transition-colors {{ request()->routeIs('impact.*') ? 'font-semibold' : '' }}" @if(request()->routeIs('impact.*')) style="color:#1EB890" @endif>عام الأثر</a>

                <a href="{{ route('public.paths.index') }}" class="hover:text-[#253B5B] transition-colors {{ request()->routeIs('public.paths.*') ? 'font-semibold' : '' }}" @if(request()->routeIs('public.paths.*')) style="color:#253B5B" @endif>المسارات</a>

                <a href="{{ route('public.programs.index') }}" class="hover:text-[#253B5B] transition-colors {{ request()->routeIs('public.programs.*') ? 'font-semibold' : '' }}" @if(request()->routeIs('public.programs.*')) style="color:#253B5B" @endif>البرامج</a>

                <a href="{{ route('public.volunteering.index') }}" class="hover:text-[#253B5B] transition-colors {{ request()->routeIs('public.volunteering.*') ? 'font-semibold' : '' }}" @if(request()->routeIs('public.volunteering.*')) style="color:#253B5B" @endif>الفرص التطوعية</a>

                <a href="{{ $newsHref }}" class="hover:text-[#253B5B] transition-colors">الأخبار</a>
                <a href="{{ $faqHref }}" class="hover:text-[#253B5B] transition-colors">الأسئلة الشائعة</a>
            </nav>

            {{-- Desktop Auth --}}
            <div class="hidden lg:flex items-center gap-3 flex-shrink-0">
                @auth
                @if(auth()->user()->hasRole(['admin', 'staff']))
                <a href="/admin" class="px-5 py-2 rounded-2xl text-sm font-semibold text-white shadow-sm hover:shadow-md transition-all duration-200" style="background:#253B5B">لوحة الإدارة</a>
                @else
                <a href="{{ route('portal.dashboard') }}" class="px-5 py-2 rounded-2xl text-sm font-semibold text-white shadow-sm hover:shadow-md transition-all duration-200" style="background:#253B5B">بوابتي</a>
                @endif
                @else
                <a href="{{ route('login') }}" class="px-5 py-2 rounded-2xl text-sm font-medium transition-colors hover:bg-[#EAF2FA]" style="color:#253B5B">تسجيل الدخول</a>
                <a href="{{ route('register') }}" class="px-5 py-2 rounded-2xl text-sm font-semibold text-white shadow-sm hover:shadow-md transition-all duration-200" style="background:#253B5B">إنشاء حساب</a>
                @endauth
            </div>

            {{-- Mobile Hamburger --}}
            <button id="pub-hamburger" aria-label="قائمة التنقل" class="lg:hidden p-2 rounded-xl text-gray-500 hover:bg-gray-100 transition-colors flex-shrink-0">
                <svg id="pub-ham-open" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                <svg id="pub-ham-close" class="w-6 h-6 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>

        </div>
    </div>

    {{-- Mobile Menu --}}
    <div id="pub-mobile-nav" class="hidden lg:hidden border-t border-gray-100 bg-white shadow-lg">
        <nav class="max-w-7xl mx-auto px-4 py-4 flex flex-col gap-1">
            <a href="{{ route('home') }}" class="px-4 py-2.5 rounded-xl text-sm font-medium text-gray-700 hover:bg-[#EAF2FA] hover:text-[#253B5B] transition-colors text-right">الرئيسية</a>
            <a href="{{ route('impact.index') }}" class="px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-[#EAF2FA] transition-colors text-right" style="color:#1EB890">عام الأثر</a>
            <a href="{{ route('public.paths.index') }}" class="px-4 py-2.5 rounded-xl text-sm font-medium text-gray-700 hover:bg-[#EAF2FA] hover:text-[#253B5B] transition-colors text-right">المسارات</a>
            <a href="{{ route('public.programs.index') }}" class="px-4 py-2.5 rounded-xl text-sm font-medium text-gray-700 hover:bg-[#EAF2FA] hover:text-[#253B5B] transition-colors text-right">البرامج</a>
            <a href="{{ route('public.volunteering.index') }}" class="px-4 py-2.5 rounded-xl text-sm font-medium text-gray-700 hover:bg-[#EAF2FA] hover:text-[#253B5B] transition-colors text-right">الفرص التطوعية</a>
            <a href="{{ $newsHref }}" class="px-4 py-2.5 rounded-xl text-sm font-medium text-gray-700 hover:bg-[#EAF2FA] hover:text-[#253B5B] transition-colors text-right">الأخبار</a>
            <a href="{{ $faqHref }}" class="px-4 py-2.5 rounded-xl text-sm font-medium text-gray-700 hover:bg-[#EAF2FA] hover:text-[#253B5B] transition-colors text-right">الأسئلة الشائعة</a>

            @auth
            @if(auth()->user()->hasRole(['admin', 'staff']))
            <a href="/admin" class="mt-3 px-4 py-2.5 rounded-xl text-sm font-semibold text-white text-center" style="background:#253B5B">لوحة الإدارة</a>
            @else
            <a href="{{ route('portal.dashboard') }}" class="mt-3 px-4 py-2.5 rounded-xl text-sm font-semibold text-white text-center" style="background:#253B5B">بوابتي</a>
            @endif
            @else
            <div class="mt-3 flex gap-2">
                <a href="{{ route('login') }}" class="flex-1 px-4 py-2.5 rounded-xl text-sm font-medium text-center border-2 transition-colors hover:bg-[#EAF2FA]" style="color:#253B5B; border-color:#253B5B">تسجيل الدخول</a>
                <a href="{{ route('register') }}" class="flex-1 px-4 py-2.5 rounded-xl text-sm font-semibold text-white text-center" style="background:#253B5B">إنشاء حساب</a>
            </div>
            @endauth
        </nav>
    </div>
</header>

<script>
    (function() {
        var btn = document.getElementById('pub-hamburger');
        var menu = document.getElementById('pub-mobile-nav');
        var ico = document.getElementById('pub-ham-open');
        var icoc = document.getElementById('pub-ham-close');
        if (btn) {
            btn.addEventListener('click', function() {
                var open = !menu.classList.contains('hidden');
                menu.classList.toggle('hidden', open);
                ico.classList.toggle('hidden', !open);
                icoc.classList.toggle('hidden', open);
            });
        }
        var nav = document.getElementById('pub-nav');
        if (nav) {
            window.addEventListener('scroll', function() {
                nav.style.boxShadow = window.scrollY > 10 ?
                    '0 4px 24px rgba(37,59,91,0.08)' :
                    '0 1px 3px rgba(0,0,0,0.06)';
            }, {
                passive: true
            });
        }
    })();

</script>
