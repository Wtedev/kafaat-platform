@props([
    'danger' => false,
])

<div {{ $attributes->class([
    'overflow-hidden rounded-2xl border bg-white shadow-sm',
    'border-[#c5d4e4]/80' => ! $danger,
    'border-red-100 bg-red-50/30' => $danger,
]) }}>
    @unless ($danger)
        <x-portal.card-header variant="bar" />
    @endunless
    {{ $slot }}
</div>
