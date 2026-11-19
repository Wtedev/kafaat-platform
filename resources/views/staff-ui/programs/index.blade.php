@php
    $clearUrl = route('staff-ui.programs.index', $view !== 'active' ? ['view' => $view] : []);
    $tz = config('app.timezone');
@endphp

<x-staff-ui.layout :name="$staffName" :email="$staffEmail" crumb="البرامج" :dashboard-active="false" active-nav="programs">
    <header class="sui-page-head sui-staff-head">
        <h1>البرامج</h1>
        @if ($canCreate)
            <x-staff-ui.button size="sm" :href="route('staff-ui.programs.create')" icon="plus">إضافة برنامج</x-staff-ui.button>
        @endif
    </header>

    @if (session('status'))
        <p class="sui-alert sui-program-alert" role="status">{{ session('status') }}</p>
    @endif

    <nav class="sui-reg-chips sui-programs-views" aria-label="القائمة">
        @foreach (array_filter(['active' => 'البرامج', 'archive' => 'الأرشيف', 'trash' => $canSeeTrash ? 'المحذوفات' : null]) as $key => $label)
            <a href="{{ route('staff-ui.programs.index', $key === 'active' ? [] : ['view' => $key]) }}" @class(['sui-reg-chip', 'is-active' => $view === $key])>
                {{ $label }} <span class="sui-reg-chip__count">{{ en_num($viewCounts[$key]) }}</span>
            </a>
        @endforeach
    </nav>

    <section class="sui-card sui-programs-panel" data-sui-programs-panel data-view="table">
        <form class="sui-table__filters sui-staff-filters sui-programs-filters" method="GET" action="{{ route('staff-ui.programs.index') }}" data-sui-staff-filters>
            @if ($view !== 'active')
                <input type="hidden" name="view" value="{{ $view }}">
            @endif
            <label class="sui-search sui-staff-search">
                <i data-lucide="search" class="sui-icon"></i>
                <input type="search" name="q" value="{{ $search }}" placeholder="اسم البرنامج" aria-label="بحث بالاسم" data-sui-staff-search autocomplete="off">
            </label>
            <x-staff-ui.select name="kind" label="نوع البرنامج" :selected="$kind" :options="$kindOptions" />
            @if ($view === 'active')
                <x-staff-ui.select name="status" label="حالة البرنامج" :selected="$status" :options="$statusOptions" />
            @endif
            <x-staff-ui.select name="registration" label="حالة التسجيل" :selected="$registration" :options="$registrationOptions" />
            @if ($filtersActive)
                <a class="sui-staff-clear" href="{{ $clearUrl }}">مسح الفلاتر</a>
            @endif
            <div class="sui-programs-toolbar">
                <span class="sui-staff-count">{{ en_num($programs->total()) }} برنامج</span>
                <div class="sui-programs-view-toggle" data-sui-programs-view-toggle role="group" aria-label="طريقة العرض">
                    <button type="button" class="sui-icon-btn is-active" data-sui-programs-view="table" aria-pressed="true" aria-label="جدول">
                        <i data-lucide="list" class="sui-icon"></i>
                    </button>
                    <button type="button" class="sui-icon-btn" data-sui-programs-view="cards" aria-pressed="false" aria-label="بطاقات">
                        <i data-lucide="layout-grid" class="sui-icon"></i>
                    </button>
                </div>
            </div>
        </form>

        @if ($programs->isEmpty())
            <div class="sui-programs-empty">
                <i data-lucide="{{ $filtersActive ? 'search-x' : 'graduation-cap' }}" class="sui-icon"></i>
                @if ($filtersActive)
                    <p>لا توجد نتائج مطابقة للفلاتر.</p>
                    <a class="sui-staff-clear" href="{{ $clearUrl }}">مسح الفلاتر</a>
                @else
                    <p>{{ ['active' => 'لا توجد برامج بعد.', 'archive' => 'لا توجد برامج مؤرشفة.', 'trash' => 'المحذوفات فاضية.'][$view] }}</p>
                @endif
            </div>
        @elseif ($view === 'trash')
            <p class="sui-programs-trash-note">البرامج المحذوفة تبقى هنا بكل بياناتها، وتقدرين تستعيدينها في أي وقت.</p>
            <div class="sui-table-wrap">
                <table class="sui-table">
                    <thead>
                        <tr>
                            <th>البرنامج</th>
                            <th>المسجلون</th>
                            <th>حُذف في</th>
                            <th>بواسطة</th>
                            <th aria-label="إجراءات"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($programs as $index => $program)
                            @php $deletedAt = $program->deleted_at?->copy()->timezone($tz); @endphp
                            <tr>
                                <td data-label="البرنامج">
                                    <div class="sui-program-row__program">
                                        <x-staff-ui.program-cover :program="$program" :index="$index" variant="thumb" />
                                        <strong>{{ $program->title }}</strong>
                                    </div>
                                </td>
                                <td data-label="المسجلون">{{ en_num((int) $program->pending_registrations_count + (int) $program->approved_registrations_count) }}</td>
                                <td data-label="حُذف في" class="sui-reg-table__time">{{ $deletedAt ? en_digits(ar_date($deletedAt, 'd MMM y')).' — '.$deletedAt->format('H:i') : '—' }}</td>
                                <td data-label="بواسطة">{{ $program->deletedBy?->name ?? '—' }}</td>
                                <td class="sui-reg-table__actions">
                                    @can('restore', $program)
                                        <form method="POST" action="{{ route('staff-ui.programs.restore', $program->id) }}" class="sui-inline-form">
                                            @csrf
                                            <x-staff-ui.button type="submit" variant="secondary" size="sm" icon="undo-2">استعادة</x-staff-ui.button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('staff-ui.programs.partials.index-pager')
        @else
            <div class="sui-programs-cards" data-sui-programs-cards hidden>
                @foreach ($programs as $index => $program)
                    @php
                        $availability = $programStatus->registrationAvailability($program);
                        $pending = $programStatus->pendingCount($program);
                        $approved = $programStatus->approvedCount($program);
                        $capacity = $program->capacity;
                        $publication = $programStatus->publicationLabel($program);
                    @endphp
                    <a class="sui-program-card" href="{{ route('staff-ui.programs.show', $program) }}">
                        <x-staff-ui.program-cover :program="$program" :index="$index" />
                        <div class="sui-program-card__body">
                            <div class="sui-program-card__title-row">
                                <h2 class="sui-program-card__title">{{ $program->title }}</h2>
                                <x-staff-ui.status :tone="$programStatus->programStatusTone($program)">
                                    {{ $programStatus->programStatusLabel($program) }}
                                </x-staff-ui.status>
                            </div>
                            <div class="sui-program-card__meta">
                                <x-staff-ui.status :tone="$availability->tone()">{{ $availability->label() }}</x-staff-ui.status>
                            </div>
                            <div class="sui-program-card__counts">
                                <div @class(['sui-program-stat', 'sui-program-stat--pending' => $pending > 0])>
                                    <span class="sui-program-stat__value">{{ en_num($pending) }}</span>
                                    <span class="sui-program-stat__label">معلّق</span>
                                </div>
                                <div class="sui-program-stat">
                                    <span class="sui-program-stat__value">
                                        {{ en_num($approved) }}@if ($capacity !== null)<span class="sui-program-stat__cap"> / {{ en_num($capacity) }}</span>@endif
                                    </span>
                                    <span class="sui-program-stat__label">مقبول</span>
                                </div>
                            </div>
                            @if ($publication !== '')
                                <p class="sui-program-card__published">{{ $publication }}</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="sui-programs-table-wrap" data-sui-programs-table>
                <div class="sui-table-wrap">
                    <table class="sui-table">
                        <thead>
                            <tr>
                                <th>البرنامج</th>
                                <th>حالة البرنامج</th>
                                <th>حالة التسجيل</th>
                                <th>معلّق</th>
                                <th>مقبول</th>
                                <th>النشر</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($programs as $index => $program)
                                @php
                                    $availability = $programStatus->registrationAvailability($program);
                                    $pending = $programStatus->pendingCount($program);
                                    $approved = $programStatus->approvedCount($program);
                                    $capacity = $program->capacity;
                                    $publication = $programStatus->publicationLabel($program);
                                @endphp
                                <tr class="sui-program-row" data-href="{{ route('staff-ui.programs.show', $program) }}" tabindex="0" role="link">
                                    <td data-label="البرنامج">
                                        <div class="sui-program-row__program">
                                            <x-staff-ui.program-cover :program="$program" :index="$index" variant="thumb" />
                                            <div>
                                                <strong>{{ $program->title }}</strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="حالة البرنامج">
                                        <x-staff-ui.status :tone="$programStatus->programStatusTone($program)">
                                            {{ $programStatus->programStatusLabel($program) }}
                                        </x-staff-ui.status>
                                    </td>
                                    <td data-label="حالة التسجيل">
                                        <x-staff-ui.status :tone="$availability->tone()">{{ $availability->label() }}</x-staff-ui.status>
                                    </td>
                                    <td data-label="معلّق">
                                        <span @class(['sui-program-stat__value', 'sui-program-stat__value--inline', 'is-hot' => $pending > 0])>{{ en_num($pending) }}</span>
                                    </td>
                                    <td data-label="مقبول">
                                        @if ($capacity !== null)
                                            {{ en_num($approved) }} / {{ en_num($capacity) }}
                                        @else
                                            {{ en_num($approved) }}
                                        @endif
                                    </td>
                                    <td data-label="النشر">@if ($publication !== ''){{ $publication }}@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @include('staff-ui.programs.partials.index-pager')
        @endif
    </section>
</x-staff-ui.layout>
