@props([
    'group',
    'tabs' => [],
])

<div {{ $attributes->class('sui-tabs') }} role="tablist" data-sui-tabs="{{ $group }}">
    @foreach ($tabs as $tab)
        <button
            type="button"
            class="sui-tab {{ ($tab['active'] ?? false) ? 'is-active' : '' }}"
            role="tab"
            data-sui-tab="{{ $tab['id'] }}"
            aria-selected="{{ ($tab['active'] ?? false) ? 'true' : 'false' }}"
        >
            <span>{{ $tab['label'] }}</span>
            @if (isset($tab['count']))
                <x-staff-ui.count :tone="($tab['active'] ?? false) ? 'primary' : 'neutral'">{{ $tab['count'] }}</x-staff-ui.count>
            @endif
        </button>
    @endforeach
</div>
