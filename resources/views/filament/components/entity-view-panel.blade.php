@php
    use Filament\Support\Enums\IconSize;
    use function Filament\Support\generate_icon_html;

    /** @var array<int, array{label: string, value: string, icon: string}> $stats */
    /** @var array<int, array{title: string, icon?: string, rows?: array<int, array{label: string, value: string, icon: string, badge?: string|null, field?: string|null}>, prose?: string, field?: string|null}> $sections */
    /** @var array{title?: string, field?: string, url?: string, has_custom?: bool, empty_label?: string}|null $cover */
    $editable = $editable ?? false;
    $cover = $cover ?? null;
@endphp

<div class="kafaat-entity-view">
    @if (is_array($cover) && (! empty($cover['url']) || ! empty($cover['field'])))
        <section class="kafaat-entity-view__cover {{ empty($cover['has_custom']) ? 'is-placeholder' : 'has-custom' }}">
            <header class="kafaat-entity-view__cover-header">
                <div class="kafaat-entity-view__section-heading">
                    <span class="kafaat-entity-view__section-icon" aria-hidden="true">
                        {!! generate_icon_html('heroicon-o-photo', size: IconSize::Small)?->toHtml() ?? '' !!}
                    </span>
                    <h3 class="kafaat-entity-view__section-title">{{ $cover['title'] ?? 'صورة الغلاف' }}</h3>
                </div>
                @if ($editable && ! empty($cover['field']))
                    <button
                        type="button"
                        class="kafaat-entity-view__edit-btn kafaat-entity-view__edit-btn--section"
                        title="تعديل {{ $cover['title'] ?? 'الصورة' }}"
                        wire:click="mountAction('editEntityField', { field: @js($cover['field']) })"
                    >
                        {!! generate_icon_html('heroicon-o-pencil-square', size: IconSize::Small)?->toHtml() ?? '' !!}
                    </button>
                @endif
            </header>

            <div class="kafaat-entity-view__cover-frame">
                @if (! empty($cover['url']))
                    <img
                        src="{{ $cover['url'] }}"
                        alt=""
                        class="kafaat-entity-view__cover-img"
                        loading="lazy"
                    >
                @endif

                @if (empty($cover['has_custom']))
                    <div class="kafaat-entity-view__cover-empty">
                        <span class="kafaat-entity-view__cover-empty-icon" aria-hidden="true">
                            {!! generate_icon_html('heroicon-o-arrow-up-tray', size: IconSize::Large)?->toHtml() ?? '' !!}
                        </span>
                        <p class="kafaat-entity-view__cover-empty-text">
                            {{ $cover['empty_label'] ?? 'لم تُرفع صورة بعد' }}
                        </p>
                        @if ($editable && ! empty($cover['field']))
                            <button
                                type="button"
                                class="kafaat-entity-view__cover-empty-btn"
                                wire:click="mountAction('editEntityField', { field: @js($cover['field']) })"
                            >
                                رفع صورة البرنامج
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </section>
    @endif

    @if (! empty($stats))
        <div class="kafaat-entity-view__stats" role="list">
            @foreach ($stats as $stat)
                <div class="kafaat-entity-view__stat" role="listitem">
                    <span class="kafaat-entity-view__stat-icon" aria-hidden="true">
                        {!! generate_icon_html($stat['icon'], size: IconSize::Medium)?->toHtml() ?? '' !!}
                    </span>
                    <div class="kafaat-entity-view__stat-body">
                        <span class="kafaat-entity-view__stat-label">{{ $stat['label'] }}</span>
                        <span class="kafaat-entity-view__stat-value">{{ $stat['value'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="kafaat-entity-view__sections">
        @foreach ($sections as $section)
            <section class="kafaat-entity-view__section">
                <header class="kafaat-entity-view__section-header">
                    <div class="kafaat-entity-view__section-header-actions">
                        @if (! empty($section['header_actions']))
                            @foreach ($section['header_actions'] as $headerAction)
                                <a
                                    href="{{ $headerAction['url'] }}"
                                    class="kafaat-entity-view__header-action-btn"
                                    title="{{ $headerAction['label'] }}"
                                >
                                    @if (! empty($headerAction['icon']))
                                        {!! generate_icon_html($headerAction['icon'], size: IconSize::Small)?->toHtml() ?? '' !!}
                                    @endif
                                    <span>{{ $headerAction['label'] }}</span>
                                </a>
                            @endforeach
                        @endif
                        @if (! empty($section['field']))
                            <button
                                type="button"
                                class="kafaat-entity-view__edit-btn kafaat-entity-view__edit-btn--section"
                                title="تعديل {{ $section['title'] }}"
                                wire:click="mountAction('editEntityField', { field: @js($section['field']) })"
                            >
                                {!! generate_icon_html('heroicon-o-pencil-square', size: IconSize::Small)?->toHtml() ?? '' !!}
                            </button>
                        @endif
                    </div>
                    <div class="kafaat-entity-view__section-heading">
                        @if (! empty($section['icon']))
                            <span class="kafaat-entity-view__section-icon" aria-hidden="true">
                                {!! generate_icon_html($section['icon'], size: IconSize::Small)?->toHtml() ?? '' !!}
                            </span>
                        @endif
                        <h3 class="kafaat-entity-view__section-title">{{ $section['title'] }}</h3>
                    </div>
                </header>

                @if (! empty($section['prose']))
                    @php
                        $proseBody = (string) $section['prose'];
                        $proseIsRichHtml = $proseBody !== '' && $proseBody !== '—' && \App\Support\RichContentSupport::isRichContent($proseBody);
                        $proseHtml = $proseBody === '—' ? $proseBody : \App\Support\RichContentSupport::toDisplayHtml($proseBody);
                    @endphp
                    @if ($proseBody === '—')
                        <div class="kafaat-entity-view__prose">—</div>
                    @elseif ($proseIsRichHtml)
                        <div class="kafaat-entity-view__prose prose max-w-none">{!! $proseHtml !!}</div>
                    @else
                        <div class="kafaat-entity-view__prose">{!! $proseHtml !!}</div>
                    @endif
                @elseif (! empty($section['rows']))
                    <dl class="kafaat-entity-view__rows">
                        @foreach ($section['rows'] as $row)
                            <div class="kafaat-entity-view__row">
                                <dt class="kafaat-entity-view__row-label">
                                    <span class="kafaat-entity-view__row-icon" aria-hidden="true">
                                        {!! generate_icon_html($row['icon'], size: IconSize::Small)?->toHtml() ?? '' !!}
                                    </span>
                                    <span>{{ $row['label'] }}</span>
                                </dt>
                                <dd class="kafaat-entity-view__row-value">
                                    <span class="kafaat-entity-view__row-value-body">
                                        @if (! empty($row['badge']))
                                            <span @class([
                                                'kafaat-entity-view__badge',
                                                'kafaat-entity-view__badge--'.$row['badge'],
                                            ])>{{ $row['value'] }}</span>
                                        @else
                                            <span>{{ $row['value'] }}</span>
                                        @endif

                                        @if (! empty($row['companion_badge']))
                                            <span @class([
                                                'kafaat-entity-view__badge',
                                                'kafaat-entity-view__badge--'.($row['companion_badge_tone'] ?? 'gray'),
                                            ])>{{ $row['companion_badge'] }}</span>
                                        @endif
                                    </span>

                                    @if ($editable && ! empty($row['row_actions']))
                                        <span class="kafaat-entity-view__row-actions">
                                            @foreach ($row['row_actions'] as $rowAction)
                                                <button
                                                    type="button"
                                                    class="kafaat-entity-view__row-action-btn"
                                                    wire:click="mountAction(@js($rowAction['action']))"
                                                >
                                                    {{ $rowAction['label'] }}
                                                </button>
                                            @endforeach
                                        </span>
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </section>
        @endforeach
    </div>
</div>
