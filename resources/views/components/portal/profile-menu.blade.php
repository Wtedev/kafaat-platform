@props([
    'align' => 'end',
    'variant' => 'default',
])

@php
$u = auth()->user();
$p = $u->profile;
$avatarUrl = $p?->avatarUrl();
$displayName = $u->fullName();
$initials = \App\Models\Profile::initialsFromName($displayName);
$settingsActive = request()->routeIs('portal.settings*', 'portal.notifications.settings');
$profileActive = request()->routeIs('portal.settings.profile', 'portal.profile');
$passwordActive = request()->routeIs('portal.settings.password');
$isToolbar = $variant === 'toolbar';
$triggerClass = $isToolbar
    ? 'inline-flex h-9 max-w-[11rem] items-center gap-1.5 rounded-xl px-1.5 transition hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-[#335483]/25 sm:max-w-[12rem] sm:ps-2 sm:pe-2.5'
    : 'inline-flex h-10 w-10 items-center justify-center rounded-xl border border-transparent transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#335483]/25 sm:h-auto sm:w-auto sm:max-w-none sm:gap-2 sm:border-slate-200/70 sm:bg-white sm:px-2 sm:py-1.5 sm:shadow-sm';
@endphp

<div {{ $attributes->class('relative shrink-0') }} data-portal-profile-menu>
    <button
        type="button"
        class="{{ $triggerClass }}"
        aria-haspopup="menu"
        aria-expanded="false"
        data-portal-profile-menu-trigger
        aria-label="قائمة الحساب"
    >
        <span class="flex h-7 w-7 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-[#335483] text-[10px] font-bold text-white sm:h-8 sm:w-8 sm:rounded-xl sm:text-[11px]">
            @if ($avatarUrl)
            <img src="{{ $avatarUrl }}" alt="" class="h-full w-full object-cover" />
            @else
            {{ $initials }}
            @endif
        </span>
        @if ($isToolbar)
        <span class="hidden min-w-0 truncate text-xs font-semibold text-slate-700 sm:inline">{{ $displayName }}</span>
        <svg class="hidden h-3.5 w-3.5 shrink-0 text-slate-400 sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        @else
        <svg class="hidden h-3.5 w-3.5 shrink-0 text-slate-400 md:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        @endif
    </button>

    <div
        class="absolute top-[calc(100%+0.375rem)] z-50 hidden w-56 overflow-hidden rounded-xl border border-slate-200/80 bg-white text-right shadow-lg ring-1 ring-slate-100 {{ $align === 'start' ? 'start-0' : 'end-0' }}"
        role="menu"
        data-portal-profile-menu-panel
    >
        <div class="border-b border-slate-100 px-4 py-3">
            <p class="truncate text-sm font-semibold text-gray-900">{{ $displayName }}</p>
            <p class="mt-0.5 truncate text-xs text-gray-500" dir="ltr">{{ $u->email }}</p>
        </div>

        <div class="py-1">
            <a href="{{ route('portal.settings.profile') }}" role="menuitem" class="flex items-center gap-2.5 px-4 py-2 text-sm transition hover:bg-slate-50 {{ $profileActive ? 'font-semibold text-[#335483]' : 'text-slate-700' }}">
                تعديل بياناتي
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
