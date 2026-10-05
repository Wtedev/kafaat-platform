@props([
    'label',
    'name' => null,
    'checked' => false,
])

<label class="sui-checkline">
    <input class="sui-check" type="checkbox" @if ($name) name="{{ $name }}" @endif @checked($checked) {{ $attributes }}>
    <span>{{ $label }}</span>
</label>
