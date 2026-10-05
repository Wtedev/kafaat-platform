@props([
    'label',
    'name' => null,
    'checked' => false,
])

<label class="sui-toggle">
    <input type="checkbox" @if ($name) name="{{ $name }}" @endif @checked($checked) {{ $attributes }}>
    <span class="sui-toggle__track" aria-hidden="true"></span>
    <span>{{ $label }}</span>
</label>
