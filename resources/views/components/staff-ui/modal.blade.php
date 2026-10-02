@props([
    'name',
    'title',
])

<div class="sui-modal" data-sui-modal="{{ $name }}" hidden>
    <div class="sui-modal__backdrop" data-sui-modal-close></div>
    <div class="sui-modal__panel" role="dialog" aria-modal="true" aria-labelledby="sui-modal-{{ $name }}">
        <h3 class="sui-modal__title" id="sui-modal-{{ $name }}">{{ $title }}</h3>
        <div class="sui-modal__text">{{ $slot }}</div>
        @isset($actions)
            <div class="sui-modal__actions">{{ $actions }}</div>
        @endisset
    </div>
</div>
