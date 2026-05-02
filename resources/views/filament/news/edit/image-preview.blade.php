@props([
    'imageUrl',
    'placeholderLabel' => 'صورة الخبر هنا',
])
@if(filled($imageUrl))
    <div class="news-edit-image-frame aspect-video w-full overflow-hidden rounded-xl ring-1 ring-white/10">
        <img src="{{ $imageUrl }}" alt="" class="h-full w-full object-cover" loading="lazy" />
    </div>
@else
    <div class="news-edit-image-placeholder flex aspect-video w-full items-center justify-center rounded-xl ring-1 ring-white/10">
        <span class="text-sm font-medium text-zinc-500">{{ $placeholderLabel }}</span>
    </div>
@endif
