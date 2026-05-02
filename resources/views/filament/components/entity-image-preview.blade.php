@props([
    'imageUrl',
    'placeholderLabel' => 'لا توجد صورة',
])
@if(filled($imageUrl))
    <div class="fi-entity-image-frame aspect-video w-full overflow-hidden rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
        <img src="{{ $imageUrl }}" alt="" class="h-full w-full object-cover" loading="lazy" />
    </div>
@else
    <div class="fi-entity-image-placeholder flex aspect-video w-full items-center justify-center rounded-xl bg-zinc-50 ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
        <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $placeholderLabel }}</span>
    </div>
@endif
