@props([
    'spacer' => true,
])

<div
    {{ $attributes->class([
        'fixed inset-x-0 bottom-0 z-[75] border-t border-[#c5d4e4]/80 bg-white/95 shadow-[0_-8px_32px_-12px_rgba(51,84,131,0.18)] backdrop-blur-md md:hidden',
        'pb-[calc(0.75rem+env(safe-area-inset-bottom,0px))]',
    ]) }}
    dir="rtl"
>
    <div class="mx-auto max-w-7xl px-4 py-3">
        {{-- Leave room for the «لدي مشكلة» FAB on the physical left --}}
        <div class="flex justify-start ps-[7.25rem] sm:ps-[8rem]">
            <div class="min-w-0 flex-1">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>

@if ($spacer)
<div class="h-[5.25rem] shrink-0 md:hidden" aria-hidden="true"></div>
@endif
