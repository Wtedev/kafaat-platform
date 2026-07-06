@props(['title' => 'معلومات البرنامج'])

<aside {{ $attributes->merge(['class' => 'lg:sticky lg:top-24']) }}>
    <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4" style="background:#F8FAFC">
            <h2 class="text-sm font-bold" style="color:#111827">{{ $title }}</h2>
        </div>
        <div class="space-y-4 px-5 py-5">
            {{ $slot }}
        </div>
    </div>
</aside>
