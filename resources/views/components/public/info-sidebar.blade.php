@props([
    'title' => 'معلومات البرنامج',
    'dense' => false,
])

<aside {{ $attributes->merge(['class' => 'md:sticky md:top-24']) }}>
    <div class="overflow-hidden rounded-2xl bg-white">
        <div @class(['px-5', $dense ? 'py-3' : 'py-4'])>
            <h2 class="text-sm font-bold text-gray-900">{{ $title }}</h2>
        </div>
        <div @class(['px-5 pb-5', $dense ? 'space-y-0 divide-y divide-gray-100' : 'space-y-1'])>
            {{ $slot }}
        </div>
    </div>
</aside>
