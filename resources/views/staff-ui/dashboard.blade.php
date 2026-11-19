@php
    use App\Services\StaffAlerts\StaffAlerts;

    $tz = config('app.timezone');
    $now = now()->timezone($tz);
    $firstName = trim(explode(' ', trim($staffName))[0] ?? '');
    $groups = StaffAlerts::GROUPS;
    $alertsByGroup = $alerts->groupBy(fn ($alert) => $alert->group());
    $perGroup = 3;
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
                <h2 id="dash-running-title">البرامج الجارية</h2>
                <p>البرامج المنشورة المنعقدة اليوم.</p>
            </div>
            @if ($running->isEmpty())
                <p class="sui-program-muted">لا توجد برامج منعقدة اليوم.</p>
            @else
                <div class="sui-table-wrap">
                    <table class="sui-table sui-dash-running">
                        <thead>
                            <tr>
                                <th>البرنامج</th>
                                <th>الفترة</th>
                                <th>المقبولون</th>
                                <th>بانتظار القبول</th>
                                <th>تحضير اليوم</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($running as $program)
                                @php
                                    $link = $program->todayLinks->first();
                                    $sameDay = $program->end_date === null || $program->end_date->isSameDay($program->start_date);
                                @endphp
                                <tr class="sui-program-row" data-href="{{ route('staff-ui.programs.show', $program) }}" tabindex="0" role="link">
                                    <td data-label="البرنامج">
                                        <div class="sui-program-row__program">
                                            <x-staff-ui.program-cover :program="$program" variant="thumb" />
                                            <a href="{{ route('staff-ui.programs.show', $program) }}" class="sui-dash-running__title">{{ $program->title }}</a>
                                        </div>
                                    </td>
                                    <td data-label="الفترة" class="sui-reg-table__time">
                                        {{ $sameDay ? 'اليوم فقط' : en_digits(ar_date($program->start_date, 'd MMM')).' – '.en_digits(ar_date($program->end_date, 'd MMM')) }}
                                    </td>
                                    <td data-label="المقبولون" class="sui-att-count">
                                        {{ en_num($program->accepted_count) }}@if ($program->capacity)<span class="sui-dash-muted"> / {{ en_num($program->capacity) }}</span>@endif
                                    </td>
                                    <td data-label="بانتظار القبول" class="sui-att-count">
                                        <span @class(['sui-dash-hot' => $program->pending_count > 0])>{{ en_num($program->pending_count) }}</span>
                                    </td>
                                    <td data-label="تحضير اليوم">
                                        @if ($link === null)
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
            @if ($alerts->isNotEmpty())
                <span class="sui-dash-todo__count">{{ en_num($alerts->count()) }}</span>
            @endif
        </div>

        @if ($alerts->isEmpty())
            <div class="sui-dash-clear">
                <i data-lucide="circle-check" class="sui-icon"></i>
                <p>لا يوجد ما يتطلب إجراءً منك الآن.</p>
            </div>
        @else
            <div class="sui-dash-groups">
                @foreach ($groups as $key => $group)
                    @continue(! $alertsByGroup->has($key))
                    @php $items = $alertsByGroup[$key]; @endphp
                    <div class="sui-dash-group">
                        <h3 class="sui-dash-group__title is-{{ $key }}">
                            <i data-lucide="{{ $group['icon'] }}" class="sui-icon sui-icon--sm"></i>
                            {{ $group['label'] }}
                            <span class="sui-dash-group__count">{{ en_num($items->count()) }}</span>
                        </h3>
                        <ul class="sui-dash-items">
                            @foreach ($items->take($perGroup) as $alert)
                                <li @class(['sui-dash-item', 'is-unread' => $alert->unread])>
                                    <a class="sui-dash-item__link" href="{{ route('staff-alerts.open', $alert) }}">
                                        <span class="sui-dash-item__title">{{ $alert->title }}</span>
                                        @if (filled($alert->body))
                                            <span class="sui-dash-item__body">{{ $alert->body }}</span>
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
                        @if ($items->count() > $perGroup)
                            <p class="sui-dash-more">و{{ en_num($items->count() - $perGroup) }} أخرى في قائمة التنبيهات.</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</x-staff-ui.layout>
