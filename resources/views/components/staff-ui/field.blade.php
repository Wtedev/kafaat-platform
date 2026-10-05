@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    'for' => null,
])

<div {{ $attributes->class(['sui-field', 'sui-field--error' => filled($error)]) }}>
    @if ($label)
        <label class="sui-field__label" @if ($for) for="{{ $for }}" @endif>{{ $label }}</label>
    @endif
    {{ $slot }}
    @if ($error)
        <p class="sui-field__error">{{ $error }}</p>
    @elseif ($hint)
        <p class="sui-field__hint">{{ $hint }}</p>
    @endif
</div>
