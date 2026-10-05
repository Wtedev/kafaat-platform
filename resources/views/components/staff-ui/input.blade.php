@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    'name' => null,
    'id' => null,
    'type' => 'text',
    'placeholder' => null,
])

@php $fieldId = $id ?? $name; @endphp

<x-staff-ui.field :label="$label" :hint="$hint" :error="$error" :for="$fieldId" {{ $attributes->only('class') }}>
    <input
        class="sui-control"
        id="{{ $fieldId }}"
        @if ($name) name="{{ $name }}" @endif
        type="{{ $type }}"
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($error) aria-invalid="true" @endif
        {{ $attributes->except('class') }}
    >
</x-staff-ui.field>
