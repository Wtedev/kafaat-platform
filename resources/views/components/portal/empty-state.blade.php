@props([
    'title',
    'description' => null,
])

<div {{ $attributes->class('rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-12 text-center shadow-sm sm:py-14') }}>
    <svg class="mx-auto h-11 w-11 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
    </svg>
    <h3 class="mt-4 text-base font-bold text-gray-900">{{ $title }}</h3>
    @if (filled($description))
    <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-gray-600">{{ $description }}</p>
    @endif
    @if (! $slot->isEmpty())
    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
        {{ $slot }}
    </div>
    @endif
</div>
