@props([
    'title',
    'description' => null,
])

<div {{ $attributes->class('overflow-hidden rounded-2xl border border-dashed border-[#c5d4e4] bg-white shadow-sm') }}>
    <x-portal.card-header variant="bar" />
    <div class="px-6 py-12 text-center sm:py-14">
        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#e9eff6] text-[#335483]" aria-hidden="true">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
            </svg>
        </span>
        <h3 class="mt-4 text-base font-bold text-[#335483]">{{ $title }}</h3>
        @if (filled($description))
        <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-gray-600">{{ $description }}</p>
        @endif
        @if (! $slot->isEmpty())
        <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
            {{ $slot }}
        </div>
        @endif
    </div>
</div>
