@props([
    'label',
    'value',
])

<div class="flex items-start gap-3 text-right">
    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl" style="background:#e9eff6">
        {{ $icon }}
    </div>
    <div class="min-w-0 flex-1">
        <p class="text-[11px] font-medium leading-snug" style="color:#9CA3AF">{{ $label }}</p>
        <p class="mt-0.5 text-sm font-semibold leading-snug" style="color:#111827">{{ $value }}</p>
    </div>
</div>
