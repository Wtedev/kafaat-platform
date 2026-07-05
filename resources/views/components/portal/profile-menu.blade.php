@props([
    'align' => 'end',
])

@php
$u = auth()->user();
$p = $u->profile;
$avatarUrl = $p?->avatarUrl();
$initials = \App\Models\Profile::initialsFromName($u->name);
$settingsActive = request()->routeIs('portal.settings*', 'portal.notifications.settings');
$profileActive = request()->routeIs('portal.profile');
$passwordActive = request()->routeIs('portal.settings.password');
@endphp

<div {{ $attributes->class('relative shrink-0') }} data-portal-profile-menu>
    <button
        type="button"
        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-transparent transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#335483]/25 sm:h-auto sm:w-auto sm:gap-2 sm:border-slate-200/70 sm:bg-white sm:px-2 sm:py-1.5 sm:shadow-sm"
        aria-haspopup="menu"
        aria-expanded="false"
        data-portal-profile-menu-trigger
        aria-label="قائمة الحساب"
    >
        <span class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-[#335483] text-[11px] font-bold text-white sm:rounded-xl">
            @if ($avatarUrl)
            <img src="{{ $avatarUrl }}" alt="" class="h-full w-full object-cover" />
            @else
            {{ $initials }}
            @endif
        </span>
        <svg class="hidden h-3.5 w-3.5 shrink-0 text-slate-400 md:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </button>

    <div
        class="absolute top-[calc(100%+0.375rem)] z-50 hidden w-56 overflow-hidden rounded-xl border border-slate-200/80 bg-white text-right shadow-lg ring-1 ring-slate-100 {{ $align === 'start' ? 'start-0' : 'end-0' }}"
        role="menu"
        data-portal-profile-menu-panel
    >
        <div class="border-b border-slate-100 px-4 py-3">
            <p class="truncate text-sm font-semibold text-gray-900">{{ $u->name }}</p>
            <p class="mt-0.5 truncate text-xs text-gray-500" dir="ltr">{{ $u->email }}</p>
        </div>

        <div class="py-1">
            <a href="{{ route('portal.profile') }}" role="menuitem" class="flex items-center gap-2.5 px-4 py-2 text-sm transition hover:bg-slate-50 {{ $profileActive ? 'font-semibold text-[#335483]' : 'text-slate-700' }}">
                الملف الشخصي
            </a>
            <a href="{{ route('portal.settings') }}" role="menuitem" class="flex items-center gap-2.5 px-4 py-2 text-sm transition hover:bg-slate-50 {{ $settingsActive ? 'font-semibold text-[#335483]' : 'text-slate-700' }}">
                الإعدادات
            </a>
            <a href="{{ route('portal.settings.password') }}" role="menuitem" class="flex items-center gap-2.5 px-4 py-2 text-sm transition hover:bg-slate-50 {{ $passwordActive ? 'font-semibold text-[#335483]' : 'text-slate-700' }}">
                تغيير كلمة المرور
            </a>
        </div>

        <div class="border-t border-slate-100 py-1">
            <form method="POST" action="{{ route('logout') }}" role="none">
                @csrf
                <button type="submit" role="menuitem" class="flex w-full px-4 py-2 text-sm text-brand-danger transition hover:bg-red-50">
                    تسجيل الخروج
                </button>
            </form>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
(function () {
    document.querySelectorAll('[data-portal-profile-menu]').forEach(function (root) {
        var trigger = root.querySelector('[data-portal-profile-menu-trigger]');
        var panel = root.querySelector('[data-portal-profile-menu-panel]');
        if (!trigger || !panel) return;

        function close() {
            panel.classList.add('hidden');
            trigger.setAttribute('aria-expanded', 'false');
        }

        function open() {
            panel.classList.remove('hidden');
            trigger.setAttribute('aria-expanded', 'true');
        }

        trigger.addEventListener('click', function (event) {
            event.stopPropagation();
            panel.classList.contains('hidden') ? open() : close();
        });

        document.addEventListener('click', function (event) {
            if (!root.contains(event.target)) close();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') close();
        });
    });
})();
</script>
@endpush
@endonce
