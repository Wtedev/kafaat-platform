@props([
    'title',
    'subtitle' => null,
    'backRoute' => 'portal.settings',
    'backLabel' => 'الإعدادات',
    'maxWidth' => 'max-w-xl',
])

<div {{ $attributes->class([$maxWidth, 'mx-auto']) }}>
    <nav class="mb-5 flex items-center gap-1.5 text-xs text-slate-500" aria-label="مسار الإعدادات">
        <a href="{{ route($backRoute) }}" class="font-medium transition hover:text-[#335483]">{{ $backLabel }}</a>
        @if ($title !== $backLabel)
        <span aria-hidden="true" class="text-slate-300">/</span>
        <span class="truncate text-slate-700">{{ $title }}</span>
        @endif
    </nav>

    <header class="mb-6 text-right">
        <h1 class="text-xl font-bold tracking-tight text-gray-900 sm:text-2xl">{{ $title }}</h1>
        @if ($subtitle)
        <p class="mt-1.5 text-sm leading-relaxed text-gray-500">{{ $subtitle }}</p>
        @endif
    </header>

    {{ $slot }}
</div>
