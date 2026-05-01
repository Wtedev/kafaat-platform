@props([
    'editTitle' => 'تعديل',
])
<details class="relative z-10">
    <summary class="inline-flex h-8 w-8 cursor-pointer list-none items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-[#253B5B]/25 [&::-webkit-details-marker]:hidden" title="{{ $editTitle }}" aria-label="{{ $editTitle }}">
        @include('portal.competency.partials.icon-pencil')
    </summary>
    <div class="absolute end-0 top-full z-20 mt-1 w-[min(100vw-2rem,28rem)] rounded-xl border border-gray-200 bg-white p-4 shadow-lg">
        {{ $slot }}
    </div>
</details>
