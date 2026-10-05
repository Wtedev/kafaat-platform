@props([
    'tone' => 'neutral',
])

<span {{ $attributes->class(['sui-count', 'sui-count--primary' => $tone === 'primary']) }}>{{ $slot }}</span>
