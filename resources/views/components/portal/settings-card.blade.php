@props([
    'danger' => false,
])

<div {{ $attributes->class([
    'overflow-hidden rounded-2xl border bg-white shadow-sm',
    'border-slate-200/80' => ! $danger,
    'border-red-100 bg-red-50/30' => $danger,
]) }}>
    {{ $slot }}
</div>
