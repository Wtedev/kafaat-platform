@props([
    'tone' => 'info',
])

<span {{ $attributes->class(['sui-status', 'sui-status--'.$tone]) }}>
    <span class="sui-status__dot" aria-hidden="true"></span>
    <span>{{ $slot }}</span>
</span>
