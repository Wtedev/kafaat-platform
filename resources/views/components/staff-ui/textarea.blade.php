@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    'name' => null,
    'id' => null,
    'placeholder' => null,
])

@php $fieldId = $id ?? $name; @endphp

<x-staff-ui.field :label="$label" :hint="$hint" :error="$error" :for="$fieldId">
    <textarea
        class="sui-control"
        id="{{ $fieldId }}"
        @if ($name) name="{{ $name }}" @endif
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($error) aria-invalid="true" @endif
        {{ $attributes }}
    ></textarea>
</x-staff-ui.field>
