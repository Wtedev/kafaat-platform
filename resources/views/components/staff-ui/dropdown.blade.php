@props([
    'align' => 'start',
])

<div {{ $attributes->class(['sui-dropdown', 'sui-dropdown--end' => $align === 'end']) }} data-sui-dropdown>
    <div data-sui-dropdown-trigger aria-haspopup="menu" aria-expanded="false">
        {{ $trigger }}
    </div>
    <div class="sui-dropdown__menu" role="menu" hidden>
        {{ $slot }}
    </div>
</div>
