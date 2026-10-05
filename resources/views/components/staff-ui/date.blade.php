@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    'name' => null,
    'id' => null,
])

<x-staff-ui.input
    type="date"
    :label="$label"
    :hint="$hint"
    :error="$error"
    :name="$name"
    :id="$id"
    {{ $attributes }}
/>
