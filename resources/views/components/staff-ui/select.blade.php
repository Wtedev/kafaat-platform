@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    'name' => null,
    'id' => null,
    'options' => [],
    'selected' => null,
])

@php $fieldId = $id ?? $name; @endphp

<x-staff-ui.field :label="$label" :hint="$hint" :error="$error" :for="$fieldId">
    <select class="sui-control" id="{{ $fieldId }}" @if ($name) name="{{ $name }}" @endif @if ($error) aria-invalid="true" @endif {{ $attributes }}>
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $text }}</option>
        @endforeach
    </select>
</x-staff-ui.field>
