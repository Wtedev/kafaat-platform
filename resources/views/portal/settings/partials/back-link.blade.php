@props([
    'backRoute' => 'portal.settings',
    'backLabel' => 'الإعدادات',
])

<a href="{{ route($backRoute) }}" class="mb-6 inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 shadow-sm transition hover:text-[#335483]">
    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 19l-7-7 7-7"/></svg>
    {{ $backLabel }}
</a>
