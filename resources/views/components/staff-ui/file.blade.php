@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    'name' => null,
    'id' => null,
])

@php $fieldId = $id ?? $name ?? 'file'; @endphp

<x-staff-ui.field :label="$label" :hint="$hint" :error="$error" :for="$fieldId">
    <label class="sui-file" for="{{ $fieldId }}">
        <input id="{{ $fieldId }}" @if ($name) name="{{ $name }}" @endif type="file" data-sui-file {{ $attributes }}>
        <span class="sui-btn sui-btn--secondary sui-btn--sm">اختيار ملف</span>
        <span class="sui-file__name" data-sui-file-name>لم يُختر ملف</span>
    </label>
</x-staff-ui.field>
