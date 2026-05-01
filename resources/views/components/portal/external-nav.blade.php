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
    : 'inline-flex items-center gap-2 rounded-xl px-3 py-1.5 text-sm font-medium text-gray-600 ring-1 ring-gray-200/90 bg-white/90 transition-colors hover:bg-gray-50 hover:text-[#253B5B]';
@endphp

@if ($showAdmin || $showMainSite)
<div {{ $attributes->class($variant === 'sidebar' ? 'space-y-2' : 'flex flex-wrap items-center gap-2') }}>
    @if ($showAdmin)
    <a href="{{ url('/admin') }}" class="{{ $linkClass }}">
        <svg class="h-4 w-4 shrink-0 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
        </svg>
        <span>لوحة الإدارة</span>
    </a>
    @endif
    @if ($showMainSite)
    <a href="{{ route('home') }}" class="{{ $linkClass }}">
        <svg class="h-4 w-4 shrink-0 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
        <span>العودة للموقع الرئيسي</span>
    </a>
    @endif
</div>
@endif
