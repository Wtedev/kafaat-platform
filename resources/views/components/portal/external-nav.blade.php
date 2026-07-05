@props([
    'variant' => 'header',
])

@php
/** @var \App\Models\User|null $user */
$user = auth()->user();
$showAdmin = $user && $user->isAdmin();
$showMainSite = $user && $user->isBeneficiary();
$linkClass = match ($variant) {
    'sidebar' => 'flex w-full items-center justify-start gap-2.5 rounded-xl px-3 py-2 text-sm font-medium text-gray-600 ring-1 ring-gray-200/90 bg-white/90 transition-colors hover:bg-gray-50 hover:text-[#335483]',
    'toolbar' => 'inline-flex h-9 shrink-0 items-center gap-1.5 rounded-xl px-2 text-slate-600 transition hover:bg-white hover:text-[#335483] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#335483]/25 sm:px-2.5',
    default => 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-[#335483] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#335483]/25 sm:h-auto sm:w-auto sm:rounded-xl sm:border sm:border-slate-200/70 sm:bg-white sm:px-2.5 sm:py-2 sm:shadow-sm',
};
$iconClass = $variant === 'toolbar'
    ? 'h-[1.125rem] w-[1.125rem] shrink-0 text-current'
    : 'h-[1.125rem] w-[1.125rem] shrink-0 text-slate-500 sm:h-4 sm:w-4';
$labelClass = $variant === 'toolbar'
    ? 'hidden text-xs font-medium sm:inline'
    : 'sr-only sm:not-sr-only sm:ms-1 sm:inline sm:text-xs sm:font-medium';
@endphp

@if ($showAdmin || $showMainSite)
<div {{ $attributes->class(match ($variant) {
    'sidebar' => 'space-y-2',
    'toolbar' => 'flex shrink-0 items-center gap-0.5',
    default => 'flex shrink-0 items-center gap-1 sm:gap-2',
}) }}>
    @if ($showAdmin)
    <a href="{{ url('/admin') }}" class="{{ $linkClass }}" aria-label="لوحة الإدارة">
        <svg class="{{ $iconClass }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
        </svg>
        <span class="{{ $labelClass }}">لوحة الإدارة</span>
    </a>
    @endif
    @if ($showMainSite)
    <a href="{{ route('home') }}" class="{{ $linkClass }}" aria-label="العودة للموقع الرئيسي">
        <svg class="{{ $iconClass }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
        <span class="{{ $labelClass }}">الموقع</span>
    </a>
    @endif
</div>
@endif
