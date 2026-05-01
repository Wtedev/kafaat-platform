@props([
    'variant' => 'header',
])

@php
/** @var \App\Models\User|null $user */
$user = auth()->user();
$showAdmin = $user && $user->isAdmin();
$showMainSite = $user && $user->isBeneficiary();
$linkClass = $variant === 'sidebar'
    ? 'flex w-full items-center justify-start gap-2.5 rounded-xl px-3 py-2 text-sm font-medium text-gray-600 ring-1 ring-gray-200/90 bg-white/90 transition-colors hover:bg-gray-50 hover:text-[#253B5B]'
    : 'inline-flex min-h-[2.75rem] min-w-[2.75rem] shrink-0 items-center justify-center gap-2 rounded-xl border border-slate-200/70 bg-white/70 p-2 text-sm font-medium text-slate-600 shadow-sm transition-all hover:border-slate-200 hover:bg-white hover:text-[#253B5B] hover:shadow-md sm:min-h-0 sm:min-w-0 sm:rounded-2xl sm:px-3.5 sm:py-2';
@endphp

@if ($showAdmin || $showMainSite)
<div {{ $attributes->class($variant === 'sidebar' ? 'space-y-2' : 'flex shrink-0 items-center gap-1 sm:gap-2') }}>
    @if ($showAdmin)
    <a href="{{ url('/admin') }}" class="{{ $linkClass }}" aria-label="لوحة الإدارة">
        <svg class="h-[1.125rem] w-[1.125rem] shrink-0 text-slate-500 sm:h-4 sm:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
        </svg>
        <span class="hidden sm:inline">لوحة الإدارة</span>
    </a>
    @endif
    @if ($showMainSite)
    <a href="{{ route('home') }}" class="{{ $linkClass }}" aria-label="العودة للموقع الرئيسي">
        <svg class="h-[1.125rem] w-[1.125rem] shrink-0 text-slate-500 sm:h-4 sm:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
        <span class="hidden sm:inline">العودة للموقع الرئيسي</span>
    </a>
    @endif
</div>
@endif
