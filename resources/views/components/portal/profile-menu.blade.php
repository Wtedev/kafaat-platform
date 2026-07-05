@props([
    'align' => 'end',
])

@php
$u = auth()->user();
$p = $u->profile;
$avatarUrl = $p?->avatarUrl();
$initials = \App\Models\Profile::initialsFromName($u->name);
$profileActive = request()->routeIs('portal.profile');
$passwordActive = request()->routeIs('portal.settings.password');
@endphp

<div {{ $attributes->class('relative shrink-0') }} data-portal-profile-menu>
    <button
        type="button"
        class="inline-flex h-11 items-center gap-2 rounded-xl border border-slate-200/80 bg-white/80 px-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-[#335483]/25 sm:rounded-2xl sm:px-2.5 sm:py-1.5"
        aria-haspopup="menu"
        aria-expanded="false"
        data-portal-profile-menu-trigger
        aria-label="قائمة الحساب"
    >
        <span class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br from-[#335483] to-[#1a9399] text-xs font-bold text-white">
            @if ($avatarUrl)
            <img src="{{ $avatarUrl }}" alt="" class="h-full w-full object-cover" />
            @else
            {{ $initials }}
            @endif
        </span>
        <span class="hidden max-w-[8rem] truncate sm:inline">{{ $u->name }}</span>
        <svg class="hidden h-4 w-4 shrink-0 text-slate-400 sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </button>

    <div
        class="absolute top-[calc(100%+0.5rem)] z-50 hidden min-w-[12.5rem] overflow-hidden rounded-2xl border border-slate-200/80 bg-white py-1.5 text-right shadow-[0_16px_40px_-12px_rgba(51,84,131,0.22)] ring-1 ring-slate-100 {{ $align === 'start' ? 'start-0' : 'end-0' }}"
        role="menu"
        data-portal-profile-menu-panel
    >
        <a href="{{ route('portal.profile') }}" role="menuitem" class="flex items-center gap-2.5 px-4 py-2.5 text-sm transition hover:bg-slate-50 {{ $profileActive ? 'font-semibold text-[#335483]' : 'text-slate-700' }}">
            <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            الملف الشخصي
        </a>
        <a href="{{ route('portal.settings.password') }}" role="menuitem" class="flex items-center gap-2.5 px-4 py-2.5 text-sm transition hover:bg-slate-50 {{ $passwordActive ? 'font-semibold text-[#335483]' : 'text-slate-700' }}">
            <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
            تغيير كلمة المرور
        </a>
        <div class="my-1 border-t border-slate-100"></div>
        <form method="POST" action="{{ route('logout') }}" role="none">
            @csrf
            <button type="submit" role="menuitem" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-sm text-brand-danger transition hover:bg-red-50">
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                تسجيل الخروج
            </button>
        </form>
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
