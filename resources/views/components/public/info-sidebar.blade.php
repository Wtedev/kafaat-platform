@props(['title' => 'معلومات البرنامج'])

<aside {{ $attributes->merge(['class' => 'md:sticky md:top-24']) }}>
    <div class="overflow-hidden rounded-2xl bg-white">
        <div class="px-5 py-4">
            <h2 class="text-sm font-bold text-gray-900">{{ $title }}</h2>
        </div>
        <div class="space-y-1 px-5 pb-5">
            {{ $slot }}
        </div>
    </div>
</aside>
