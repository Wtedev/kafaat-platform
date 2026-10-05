@props([
    'group',
    'tabs' => [],
])

<div {{ $attributes->class('sui-tabs') }} role="tablist" data-sui-tabs="{{ $group }}">
    @foreach ($tabs as $tab)
        @if (! empty($tab['href']))
            <a
                href="{{ $tab['href'] }}"
                class="sui-tab {{ ($tab['active'] ?? false) ? 'is-active' : '' }}"
                role="tab"
                data-sui-tab="{{ $tab['id'] }}"
                aria-selected="{{ ($tab['active'] ?? false) ? 'true' : 'false' }}"
            >
        @else
        <button
            type="button"
            class="sui-tab {{ ($tab['active'] ?? false) ? 'is-active' : '' }} {{ ($tab['disabled'] ?? false) ? 'is-disabled' : '' }}"
            role="tab"
            data-sui-tab="{{ $tab['id'] }}"
            aria-selected="{{ ($tab['active'] ?? false) ? 'true' : 'false' }}"
            @disabled($tab['disabled'] ?? false)
            @if ($tab['disabled'] ?? false) aria-disabled="true" @endif
        >
        @endif
            <span>{{ $tab['label'] }}</span>
            @if (isset($tab['count']))
                <x-staff-ui.count :tone="($tab['active'] ?? false) ? 'primary' : 'neutral'">{{ $tab['count'] }}</x-staff-ui.count>
            @endif
        @if (! empty($tab['href']))
            </a>
        @else
        </button>
        @endif
    @endforeach
</div>
