@props([
    'label',
    'value',
])

<div class="flex items-start gap-3 py-3 text-right">
    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" style="background:#e9eff6">
        {{ $icon }}
    </div>
    <div class="min-w-0 flex-1">
        <p class="text-xs font-medium leading-snug" style="color:#9CA3AF">{{ $label }}</p>
        <p class="mt-1 text-sm font-semibold leading-snug text-gray-900">{{ $value }}</p>
    </div>
</div>
