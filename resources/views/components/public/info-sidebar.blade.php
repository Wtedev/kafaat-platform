@props([
    'title' => 'معلومات البرنامج',
    'dense' => false,
    'sticky' => true,
])

<aside @class(['md:sticky md:top-24' => $sticky])>
    <div class="overflow-hidden rounded-2xl bg-white">
        <div class="flex items-center gap-3 border-b border-[#e9eff6] bg-[#e9eff6]/80 px-5 py-3.5">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white" aria-hidden="true">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </span>
            <h2 class="text-sm font-bold text-[#335483]">{{ $title }}</h2>
        </div>
        <div @class(['px-5 pb-5 pt-1', $dense ? 'space-y-0 divide-y divide-gray-100' : 'space-y-1'])>
            {{ $slot }}
        </div>
    </div>
</aside>
