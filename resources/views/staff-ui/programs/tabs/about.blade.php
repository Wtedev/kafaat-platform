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
            <dt>المدة</dt>
            <dd>
                {{ $date($program->start_date) }}
                @if ($program->end_date && ! $program->end_date->isSameDay($program->start_date))
                    إلى {{ $date($program->end_date) }}
                @endif
            </dd>
            @if ($weekdays !== '')
                <dt>أيام الأسبوع</dt>
                <dd>{{ $weekdays }}</dd>
            @endif
            <dt>فترة التسجيل</dt>
            <dd>{{ $date($program->registration_start) }} إلى {{ $date($program->registration_end) }}</dd>
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

    <section class="sui-card sui-program-about__wide">
        <div class="sui-card__head sui-card__head--row">
            <h2>آخر النشاط</h2>
            <x-staff-ui.button variant="ghost" size="sm" :href="route('staff-ui.programs.tab', [$program, 'activity'])">السجل الكامل</x-staff-ui.button>
        </div>
        @include('staff-ui.programs.partials.activity-list', ['items' => $recentActivity])
    </section>
</div>
