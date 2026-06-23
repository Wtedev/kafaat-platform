@props(['category', 'size' => 'sm'])

@php
    $map = config('brand.news_categories');
    $def = $map[$category] ?? ($map['أخرى'] ?? 'class="bg-gray-100 text-gray-600 ring-1 ring-gray-200"');
    $isClass = Str::startsWith($def, 'class=');
    $isStyle = Str::startsWith($def, 'style=');
    $padding = $size === 'md' ? 'px-3 py-1.5' : 'px-3 py-1';
@endphp

<span {{ $attributes->merge([
    'class' => trim('text-xs font-medium rounded-xl '.$padding.($isClass ? ' '.ltrim(Str::between($def, 'class="', '"')) : '')),
]) }} @if($isStyle) style="{{ Str::between($def, 'style="', '"') }}" @endif>
    {{ $category }}
</span>
