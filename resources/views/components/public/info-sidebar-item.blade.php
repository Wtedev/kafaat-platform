@props([
    'label',
    'value',
    'dense' => false,
])

<div @class([
    'flex items-start text-right',
    $dense ? 'gap-2.5 py-2.5' : 'gap-3 py-3',
])>
    <div @class([
        'flex shrink-0 items-center justify-center rounded-xl',
        $dense ? 'h-8 w-8' : 'h-10 w-10',
    ]) style="background:#e9eff6">
        {{ $icon }}
    </div>
    <div class="min-w-0 flex-1">
        <p class="text-xs font-medium leading-snug" style="color:#9CA3AF">{{ $label }}</p>
        <p @class([$dense ? 'mt-0.5' : 'mt-1', 'text-sm font-semibold leading-snug text-gray-900'])>{{ $value }}</p>
    </div>
</div>
