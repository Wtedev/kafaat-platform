@php
    use App\Services\StaffAlerts\StaffAlerts;

    $tz = config('app.timezone');
    $now = now()->timezone($tz);
    $firstName = trim(explode(' ', trim($staffName))[0] ?? '');
    $groups = StaffAlerts::GROUPS;
@endphp

<x-staff-ui.layout :name="$staffName" :email="$staffEmail" crumb="لوحة التحكم" :dashboard-active="true">
    <header class="sui-page-head sui-dash-head">
        <div>
            <h1>مرحباً{{ $firstName !== '' ? '، '.$firstName : '' }}</h1>
            <p class="sui-dash-head__date">{{ en_digits(ar_date($now, 'EEEE d MMMM y')) }}</p>
        </div>
    </header>

    @if ($canPrograms)
        <section aria-labelledby="dash-stats-title">
            <h2 id="dash-stats-title" class="sui-dash-title">إحصاءات المنصة — {{ $monthLabel }}</h2>
            <div class="sui-dash-stats">
                @foreach ($stats as $stat)
                    @php
                        $diff = $stat['value'] - $stat['previous'];
                    @endphp
                    <article class="sui-card sui-dash-stat">
                        <span class="sui-dash-stat__icon"><i data-lucide="{{ $stat['icon'] }}" class="sui-icon sui-icon--sm"></i></span>
                        <span class="sui-dash-stat__label">{{ $stat['label'] }}</span>
                        <strong class="sui-dash-stat__value">{{ en_num($stat['value']) }}</strong>
                        <span class="sui-dash-stat__prev">الشهر الماضي: {{ en_num($stat['previous']) }}</span>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="sui-card" aria-labelledby="dash-running-title">
            <div class="sui-card__head">
                <h2 id="dash-running-title">البرامج الجارية والقادمة</h2>
                <p>البرامج المنشورة المنعقدة اليوم، والتي تبدأ خلال ثلاثين يوماً.</p>
            </div>
            @if ($running->isEmpty() && $upcoming->isEmpty())
                <p class="sui-program-muted">لا توجد برامج جارية أو قادمة.</p>
            @else
                <div class="sui-table-wrap">
                    <table class="sui-table sui-dash-running">
                        <thead>
                            <tr>
                                <th>البرنامج</th>
                                <th>الحالة</th>
                                <th>الفترة</th>
                                <th>المقبولون</th>
                                <th>بانتظار القبول</th>
                                <th>تحضير اليوم</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($running->concat($upcoming) as $program)
                                @php
                                    $isRunning = $running->contains($program);
                                    $link = $isRunning ? $program->todayLinks->first() : null;
                                    $sameDay = $program->end_date === null || $program->end_date->isSameDay($program->start_date);
                                    $daysLeft = $isRunning ? 0 : (int) $now->copy()->startOfDay()->diffInDays($program->start_date->copy()->startOfDay());
                                @endphp
                                <tr class="sui-program-row" data-href="{{ route('staff-ui.programs.show', $program) }}" tabindex="0" role="link">
                                    <td data-label="البرنامج">
                                        <div class="sui-program-row__program">
                                            <x-staff-ui.program-cover :program="$program" variant="thumb" />
                                            <a href="{{ route('staff-ui.programs.show', $program) }}" class="sui-dash-running__title">{{ $program->title }}</a>
                                        </div>
                                    </td>
                                    <td data-label="الحالة">
                                        @if ($isRunning)
                                            <x-staff-ui.status tone="success">جارٍ</x-staff-ui.status>
                                        @else
                                            <x-staff-ui.status tone="info">{{ $daysLeft === 1 ? 'يبدأ غداً' : 'يبدأ بعد '.en_num($daysLeft).' '.($daysLeft <= 10 ? 'أيام' : 'يوماً') }}</x-staff-ui.status>
                                        @endif
                                    </td>
                                    <td data-label="الفترة" class="sui-reg-table__time">
                                        {{ $sameDay ? en_digits(ar_date($program->start_date, 'd MMM')) : en_digits(ar_date($program->start_date, 'd MMM')).' – '.en_digits(ar_date($program->end_date, 'd MMM')) }}
                                    </td>
                                    <td data-label="المقبولون" class="sui-att-count">
                                        {{ en_num($program->accepted_count) }}@if ($program->capacity)<span class="sui-dash-muted"> / {{ en_num($program->capacity) }}</span>@endif
                                    </td>
                                    <td data-label="بانتظار القبول" class="sui-att-count">
                                        <span @class(['sui-dash-hot' => $program->pending_count > 0])>{{ en_num($program->pending_count) }}</span>
                                    </td>
                                    <td data-label="تحضير اليوم">
                                        @if (! $isRunning)
                                            <span class="sui-dash-muted">—</span>
                                        @elseif ($link === null)
                                            <span class="sui-dash-muted">لم يُفتح</span>
                                        @elseif ($link->isOpen())
                                            <x-staff-ui.status tone="success">مفتوح · {{ en_num($link->marks_count) }} حاضر</x-staff-ui.status>
                                        @else
                                            <x-staff-ui.status tone="muted">أُغلق · {{ en_num($link->marks_count) }} حاضر</x-staff-ui.status>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    <section class="sui-card sui-dash-todo" aria-labelledby="dash-todo-title">
        <div class="sui-card__head sui-card__head--row">
            <div>
                <h2 id="dash-todo-title">ملخص التنبيهات</h2>
                <p>ما يتطلب إجراءً الآن، ويختفي كل بند تلقائياً عند إنجازه.</p>
            </div>
            @if ($alertSummary !== [])
                <span class="sui-dash-todo__count">{{ en_num(count($alertSummary)) }}</span>
            @endif
        </div>

        @if ($alertSummary === [])
            <div class="sui-dash-clear">
                <i data-lucide="circle-check" class="sui-icon"></i>
                <p>لا يوجد ما يتطلب إجراءً منك الآن.</p>
            </div>
        @else
            <ul class="sui-dash-summary">
                @foreach ($alertSummary as $row)
                    <li @class(['sui-dash-summary__row', 'is-unread' => $row['unread']])>
                        <details>
                            <summary>
                                <span @class(['sui-alert-item__icon', 'is-'.$row['group']])><i data-lucide="{{ $groups[$row['group']]['icon'] ?? 'bell' }}" class="sui-icon sui-icon--sm"></i></span>
                                <span class="sui-dash-summary__text">
                                    <span class="sui-dash-summary__title">{{ $row['title'] }}</span>
                                    @if ($row['detail'])
                                        <span class="sui-dash-summary__detail">{{ $row['detail'] }}</span>
                                    @endif
                                </span>
                                @if ($row['items']->count() > 1)
                                    <i data-lucide="chevron-down" class="sui-icon sui-icon--sm sui-dash-summary__chevron"></i>
                                @endif
                            </summary>
                            <ul class="sui-dash-summary__items">
                                @foreach ($row['items'] as $alert)
                                    <li>
                                        <a href="{{ route('staff-alerts.open', $alert) }}">
                                            <span>{{ $alert->kind === 'program.attendance' ? $alert->title.' — '.$alert->body : ($alert->body ?: $alert->title) }}</span>
                                            @if (in_array($alert->kind, ['registrations.program', 'registrations.volunteer', 'delivery.broadcast', 'delivery.email', 'program.survey'], true))
                                                <small>{{ en_num($alert->count) }}</small>
                                            @endif
                                        </a>
                                        @if ($alertService->isDismissible($alert))
                                            <form method="POST" action="{{ route('staff-alerts.dismiss', $alert) }}" class="sui-inline-form">
                                                @csrf
                                                <button type="submit" class="sui-icon-btn" aria-label="إخفاء" title="إخفاء لدى الجميع حتى يتغيّر"><i data-lucide="x" class="sui-icon sui-icon--sm"></i></button>
                                            </form>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-staff-ui.layout>
