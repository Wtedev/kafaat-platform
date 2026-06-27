@php
    /** @var array<int, array{key: string, label: string, old: string, new: string}> $changes */
@endphp

<style>
    .kafaat-settings-changes,
    .kafaat-settings-changes * {
        box-sizing: border-box;
    }

    .kafaat-settings-changes {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        max-height: min(55vh, 24rem);
        overflow-y: auto;
    }

    .kafaat-settings-changes__card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin: 0;
        padding: 0.65rem 0.85rem;
        border-radius: 0.65rem;
        border: 1px solid rgba(255, 255, 255, 0.12);
        background: rgba(255, 255, 255, 0.04);
    }

    .kafaat-settings-changes__field {
        flex: 0 0 auto;
        max-width: 42%;
        font-size: 0.8125rem;
        font-weight: 600;
        line-height: 1.35;
        color: #f4f4f5;
    }

    .kafaat-settings-changes__diff {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex: 1 1 auto;
        gap: 0.4rem;
        min-width: 0;
        font-size: 0.8125rem;
        line-height: 1.35;
        white-space: nowrap;
    }

    .kafaat-settings-changes__chip {
        display: inline-flex;
        align-items: center;
        gap: 0.2rem;
        padding: 0.2rem 0.45rem;
        border-radius: 0.4rem;
        max-width: 9rem;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .kafaat-settings-changes__chip-label {
        font-size: 0.6875rem;
        font-weight: 700;
        opacity: 0.85;
        flex-shrink: 0;
    }

    .kafaat-settings-changes__chip-value {
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .kafaat-settings-changes__chip--old {
        background: rgba(239, 68, 68, 0.16);
        color: #fca5a5;
    }

    .kafaat-settings-changes__chip--old .kafaat-settings-changes__chip-value {
        text-decoration: line-through;
        text-decoration-color: rgba(252, 165, 165, 0.5);
    }

    .kafaat-settings-changes__chip--new {
        background: rgba(34, 197, 94, 0.16);
        color: #86efac;
        font-weight: 600;
    }

    .kafaat-settings-changes__arrow {
        flex-shrink: 0;
        color: #71717a;
        font-size: 0.75rem;
        line-height: 1;
    }

    .kafaat-settings-changes--empty {
        padding: 1rem;
        border-radius: 0.65rem;
        border: 1px dashed rgba(255, 255, 255, 0.12);
        text-align: center;
    }

    .kafaat-settings-changes__empty {
        margin: 0;
        font-size: 0.875rem;
        color: #a1a1aa;
    }

    html:not(.dark) .kafaat-settings-changes__card {
        background: #fafafa;
        border-color: #e4e4e7;
    }

    html:not(.dark) .kafaat-settings-changes__field {
        color: #18181b;
    }

    html:not(.dark) .kafaat-settings-changes__chip--old {
        background: #fee2e2;
        color: #b91c1c;
    }

    html:not(.dark) .kafaat-settings-changes__chip--new {
        background: #dcfce7;
        color: #15803d;
    }
</style>

@if ($changes === [])
    <div class="kafaat-settings-changes kafaat-settings-changes--empty">
        <p class="kafaat-settings-changes__empty">لا توجد تعديلات للتطبيق.</p>
    </div>
@else
    <ul class="kafaat-settings-changes" role="list">
        @foreach ($changes as $change)
            <li class="kafaat-settings-changes__card">
                <span class="kafaat-settings-changes__field">{{ $change['label'] }}</span>
                <span class="kafaat-settings-changes__diff">
                    <span class="kafaat-settings-changes__chip kafaat-settings-changes__chip--old">
                        <span class="kafaat-settings-changes__chip-label">من</span>
                        <span class="kafaat-settings-changes__chip-value">{{ $change['old'] }}</span>
                    </span>
                    <span class="kafaat-settings-changes__arrow" aria-hidden="true">←</span>
                    <span class="kafaat-settings-changes__chip kafaat-settings-changes__chip--new">
                        <span class="kafaat-settings-changes__chip-label">إلى</span>
                        <span class="kafaat-settings-changes__chip-value">{{ $change['new'] }}</span>
                    </span>
                </span>
            </li>
        @endforeach
    </ul>
@endif
