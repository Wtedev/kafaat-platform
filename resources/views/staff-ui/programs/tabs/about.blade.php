@php
    use App\Support\RichContentSupport;

    $capacity = $program->capacity;
    $genderCapacity = array_filter([
        $program->capacity_male ? 'رجال '.en_num($program->capacity_male) : null,
        $program->capacity_female ? 'نساء '.en_num($program->capacity_female) : null,
    ]);
    $weekdays = collect(is_array($program->weekdays) ? $program->weekdays : [])
        ->map(fn ($day) => $weekdayOptions[(int) $day] ?? null)
        ->filter()
        ->implode('، ');
    $presenters = collect(is_array($program->program_presenters) ? $program->program_presenters : [])
        ->map(function (mixed $row): string {
            if (! is_array($row)) {
                return '';
            }
            $name = trim((string) ($row['name'] ?? ''));
            $role = trim((string) ($row['role'] ?? ''));

            return $name === '' ? '' : ($role !== '' ? $name.' — '.$role : $name);
        })
        ->filter()
        ->values();
    $topics = $program->session_topics_enabled
        ? collect(is_array($program->session_topics) ? $program->session_topics : [])
            ->map(fn (mixed $row): string => is_array($row) ? trim((string) ($row['title'] ?? '')) : '')
            ->filter()
            ->values()
        : collect();
    $date = fn ($value): string => $value ? en_digits(ar_date($value, 'd MMM y')) : '—';
    $descriptionHtml = filled($program->description) ? RichContentSupport::toDisplayHtml((string) $program->description) : '';
@endphp

<div class="sui-program-stats">
    <article class="sui-card sui-program-kpi">
        <span class="sui-program-kpi__label">المسجّلون</span>
        <strong class="sui-program-kpi__value">{{ en_num($program->registrations_count) }}</strong>
    </article>
    <article @class(['sui-card', 'sui-program-kpi', 'is-hot' => $program->pending_registrations_count > 0])>
        <span class="sui-program-kpi__label">بانتظار القبول</span>
        <strong class="sui-program-kpi__value">{{ en_num($program->pending_registrations_count) }}</strong>
    </article>
    <article class="sui-card sui-program-kpi">
        <span class="sui-program-kpi__label">المقبولون</span>
        <strong class="sui-program-kpi__value">
            {{ en_num($program->approved_registrations_count) }}@if ($capacity !== null)<span class="sui-program-kpi__cap"> / {{ en_num($capacity) }}</span>@endif
        </strong>
    </article>
    <article class="sui-card sui-program-kpi">
        <span class="sui-program-kpi__label">أيام التحضير</span>
        <strong class="sui-program-kpi__value">{{ en_num($program->prep_days_count) }}</strong>
    </article>
    <article class="sui-card sui-program-kpi">
        <span class="sui-program-kpi__label">استبيانات مكتملة</span>
        <strong class="sui-program-kpi__value">{{ en_num($surveyCompletionsCount) }}</strong>
    </article>
    <article class="sui-card sui-program-kpi">
        <span class="sui-program-kpi__label">الشهادات الصادرة</span>
        <strong class="sui-program-kpi__value">{{ en_num($certificatesCount) }}</strong>
    </article>
</div>

<div class="sui-program-about">
    <section class="sui-card">
        <div class="sui-card__head sui-card__head--row">
            <h2>بيانات البرنامج</h2>
            @if ($canUpdate)
                <x-staff-ui.button variant="ghost" size="sm" icon="pencil" :href="route('staff-ui.programs.tab', [$program, 'settings'])">تعديل</x-staff-ui.button>
            @endif
        </div>
        <dl class="sui-facts">
            <dt>النوع</dt>
            <dd>{{ $program->program_kind?->label() ?? '—' }}</dd>
            <dt>مسار الكفاءة</dt>
            <dd>{{ $program->competency_track?->shortLabel() ?? '—' }}</dd>
            <dt>أسلوب التنفيذ</dt>
            <dd>{{ $program->delivery_mode?->label() ?? '—' }}@if (filled($program->venue)) — {{ $program->venue }}@endif</dd>
            <dt>السعة</dt>
            <dd>{{ $genderCapacity !== [] ? implode('، ', $genderCapacity) : ($capacity !== null ? en_num($capacity) : 'بلا حد') }}</dd>
            <dt>طريقة القبول</dt>
            <dd>{{ $program->auto_accept_registrations ? 'تلقائي لمن يستوفي الشروط' : 'يدوي' }}</dd>
            @if ($presenters->isNotEmpty())
                <dt>المقدمون</dt>
                <dd>{{ $presenters->implode('، ') }}</dd>
            @endif
            @if ($topics->isNotEmpty())
                <dt>المحاور</dt>
                <dd>{{ $topics->implode('، ') }}</dd>
            @endif
            <dt>مجموعات التواصل</dt>
            <dd>{{ $program->whatsapp_groups_enabled ? 'مفعّلة' : 'غير مفعّلة' }}</dd>
            <dt>النشر</dt>
            <dd>{{ $programStatus->publicationLabel($program) ?: '—' }}</dd>
        </dl>
    </section>

    <div class="sui-program-about__side">
        <section class="sui-card sui-program-dates">
            <div class="sui-card__head">
                <h2>المواعيد</h2>
            </div>
            @if ($calendar['has_any'])
                <div class="sui-cal" data-sui-cal data-initial="{{ $calendar['initial'] }}">
                    <div class="sui-cal__nav">
                        <button type="button" class="sui-icon-btn" data-sui-cal-prev aria-label="الشهر السابق"><i data-lucide="chevron-right" class="sui-icon"></i></button>
                        @foreach ($calendar['months'] as $i => $month)
                            <strong class="sui-cal__title" data-sui-cal-title="{{ $i }}" @if ($i !== $calendar['initial']) hidden @endif>{{ $month['label'] }}</strong>
                        @endforeach
                        <button type="button" class="sui-icon-btn" data-sui-cal-next aria-label="الشهر التالي"><i data-lucide="chevron-left" class="sui-icon"></i></button>
                    </div>
                    @foreach ($calendar['months'] as $i => $month)
                        <div class="sui-cal__month" data-sui-cal-month="{{ $i }}" @if ($i !== $calendar['initial']) hidden @endif>
                            <div class="sui-cal__grid" role="grid" aria-label="{{ $month['label'] }}">
                                @foreach (['ح', 'ن', 'ث', 'ر', 'خ', 'ج', 'س'] as $weekday)
                                    <span class="sui-cal__weekday" role="columnheader">{{ $weekday }}</span>
                                @endforeach
                                @foreach ($month['weeks'] as $week)
                                    @foreach ($week as $cell)
                                        @if ($cell === null)
                                            <span class="sui-cal__cell is-empty" aria-hidden="true"></span>
                                        @else
                                            @php
                                                $notes = array_filter([
                                                    $cell['registration'] ? 'فترة التسجيل' : null,
                                                    $cell['program'] ? 'يوم البرنامج' : null,
                                                    $cell['qr'] ? 'تحضير QR' : null,
                                                    ...$cell['links'],
                                                ]);
                                            @endphp
                                            <span
                                                role="gridcell"
                                                @class([
                                                    'sui-cal__cell',
                                                    'is-registration' => $cell['registration'],
                                                    'is-program' => $cell['program'],
                                                    'is-today' => $cell['today'],
                                                ])
                                                @if ($notes !== []) title="{{ implode(' · ', $notes) }}" @endif
                                            >
                                                <span class="sui-cal__day">{{ en_num($cell['day']) }}</span>
                                                @if ($cell['qr'] || $cell['links'] !== [])
                                                    <span class="sui-cal__marks">
                                                        @if ($cell['qr'])
                                                            <i data-lucide="qr-code" class="sui-cal__mark is-qr" aria-label="تحضير QR"></i>
                                                        @endif
                                                        @if ($cell['links'] !== [])
                                                            <i data-lucide="link" class="sui-cal__mark is-link" aria-label="رابط تحضير"></i>
                                                        @endif
                                                    </span>
                                                @endif
                                            </span>
                                        @endif
                                    @endforeach
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    <ul class="sui-cal__legend">
                        <li><span class="sui-cal__swatch is-registration"></span>التسجيل</li>
                        <li><span class="sui-cal__swatch is-program"></span>أيام البرنامج</li>
                        <li><i data-lucide="qr-code" class="sui-cal__mark is-qr"></i>تحضير QR</li>
                        <li><i data-lucide="link" class="sui-cal__mark is-link"></i>رابط تحضير</li>
                    </ul>
                </div>
            @endif
            <div class="sui-program-dates__grid">
                <div class="sui-program-date">
                    <span class="sui-program-date__label">التسجيل</span>
                    <strong>{{ $date($program->registration_start) }}</strong>
                    <span>إلى {{ $date($program->registration_end) }}</span>
                </div>
                <div class="sui-program-date">
                    <span class="sui-program-date__label">البرنامج</span>
                    <strong>{{ $date($program->start_date) }}</strong>
                    @if ($program->end_date && ! $program->end_date->isSameDay($program->start_date))
                        <span>إلى {{ $date($program->end_date) }}</span>
                    @endif
                    @if ($weekdays !== '')
                        <span class="sui-program-date__days">{{ $weekdays }}</span>
                    @endif
                </div>
            </div>
            <div class="sui-program-dates__attendance">
                <h3>أيام التحضير</h3>
                @forelse ($attendanceDays as $day)
                    <div class="sui-attendance-day">
                        <span @class(['sui-attendance-day__kind', 'is-qr' => $day['kind'] === 'qr'])>
                            <i data-lucide="{{ $day['kind'] === 'qr' ? 'qr-code' : 'link' }}" class="sui-icon sui-icon--sm" aria-hidden="true"></i>
                            {{ $day['kind'] === 'qr' ? 'QR' : 'رابط' }}
                        </span>
                        <span class="sui-attendance-day__date">{{ $day['date'] ? $date($day['date']) : '—' }}</span>
                        <span class="sui-attendance-day__title">{{ $day['title'] }}</span>
                        @if ($day['state'] !== null || $day['count'] !== null)
                            <span class="sui-attendance-day__meta">
                                {{ $day['state'] }}@if ($day['count'] !== null) · {{ en_num($day['count']) }} حاضر@endif
                            </span>
                        @endif
                    </div>
                @empty
                    <p class="sui-program-muted">لا توجد أيام تحضير ولا روابط بعد.</p>
                @endforelse
            </div>
        </section>
        <script>
            document.querySelectorAll('[data-sui-cal]').forEach((cal) => {
                const months = cal.querySelectorAll('[data-sui-cal-month]');
                const titles = cal.querySelectorAll('[data-sui-cal-title]');
                const prev = cal.querySelector('[data-sui-cal-prev]');
                const next = cal.querySelector('[data-sui-cal-next]');
                let current = Number(cal.dataset.initial || 0);
                const show = () => {
                    months.forEach((el, i) => { el.hidden = i !== current; });
                    titles.forEach((el, i) => { el.hidden = i !== current; });
                    prev.disabled = current === 0;
                    next.disabled = current === months.length - 1;
                };
                prev.addEventListener('click', () => { if (current > 0) { current -= 1; show(); } });
                next.addEventListener('click', () => { if (current < months.length - 1) { current += 1; show(); } });
                show();
            });
        </script>

        <section class="sui-card">
            <div class="sui-card__head">
                <h2>النبذة</h2>
            </div>
            @if ($descriptionHtml !== '')
                <div class="sui-program-description">{!! $descriptionHtml !!}</div>
            @else
                <p class="sui-program-muted">لا توجد نبذة بعد.</p>
            @endif
        </section>
    </div>

    <section class="sui-card sui-program-about__wide">
        <div class="sui-card__head sui-card__head--row">
            <h2>آخر النشاط</h2>
            <x-staff-ui.button variant="ghost" size="sm" :href="route('staff-ui.programs.tab', [$program, 'activity'])">السجل الكامل</x-staff-ui.button>
        </div>
        @include('staff-ui.programs.partials.activity-list', ['items' => $recentActivity])
    </section>
</div>
