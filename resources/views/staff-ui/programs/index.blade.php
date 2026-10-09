@php
    use App\Enums\StaffUi\StaffRegistrationAvailability;

    $clearUrl = route('staff-ui.programs.index');
@endphp

<x-staff-ui.layout :name="$staffName" :email="$staffEmail" crumb="البرامج" :dashboard-active="false" active-nav="programs">
    <header class="sui-page-head sui-staff-head">
        <h1>البرامج</h1>
        <div class="sui-programs-view-toggle" data-sui-programs-view-toggle role="group" aria-label="طريقة العرض">
            <button type="button" class="sui-icon-btn is-active" data-sui-programs-view="table" aria-pressed="true" aria-label="جدول">
                <i data-lucide="list" class="sui-icon"></i>
            </button>
            <button type="button" class="sui-icon-btn" data-sui-programs-view="cards" aria-pressed="false" aria-label="بطاقات">
                <i data-lucide="layout-grid" class="sui-icon"></i>
            </button>
        </div>
    </header>

    <section class="sui-card sui-programs-panel" data-sui-programs-panel data-view="table">
        <form class="sui-table__filters sui-staff-filters sui-programs-filters" method="GET" action="{{ route('staff-ui.programs.index') }}" data-sui-staff-filters>
            <label class="sui-search sui-staff-search">
                <i data-lucide="search" class="sui-icon"></i>
                <input type="search" name="q" value="{{ $search }}" placeholder="اسم البرنامج" aria-label="بحث بالاسم" data-sui-staff-search autocomplete="off">
            </label>
            <x-staff-ui.select name="kind" label="نوع البرنامج" :selected="$kind" :options="$kindOptions" />
            <x-staff-ui.select name="status" label="حالة البرنامج" :selected="$status" :options="$statusOptions" />
            <x-staff-ui.select name="registration" label="حالة التسجيل" :selected="$registration" :options="$registrationOptions" />
            @if ($filtersActive)
                <a class="sui-staff-clear" href="{{ $clearUrl }}">مسح الفلاتر</a>
            @endif
            <span class="sui-staff-count">{{ en_num($programs->total()) }} برنامج</span>
        </form>

        @if ($programs->isEmpty())
            <div class="sui-programs-empty">
                <i data-lucide="{{ $filtersActive ? 'search-x' : 'graduation-cap' }}" class="sui-icon"></i>
                @if ($filtersActive)
                    <p>لا توجد نتائج مطابقة للفلاتر.</p>
                    <a class="sui-staff-clear" href="{{ $clearUrl }}">مسح الفلاتر</a>
                @else
                    <p>لا توجد برامج بعد.</p>
                @endif
            </div>
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
                                @if ($availability === StaffRegistrationAvailability::Path && $program->learningPath)
                                    <span class="sui-program-card__path">{{ $program->learningPath->title }}</span>
                                @endif
                            </div>
                            @if ($availability !== StaffRegistrationAvailability::Path)
                                <div class="sui-program-card__counts">
                                    <div class="sui-program-stat sui-program-stat--pending">
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
                            @endif
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
                                                @if ($availability === StaffRegistrationAvailability::Path && $program->learningPath)
                                                    <div class="sui-program-card__path">{{ $program->learningPath->title }}</div>
                                                @endif
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
                                        @if ($availability === StaffRegistrationAvailability::Path)
                                            —
                                        @else
                                            <span class="sui-program-stat__value sui-program-stat__value--inline">{{ en_num($pending) }}</span>
                                        @endif
                                    </td>
                                    <td data-label="مقبول">
                                        @if ($availability === StaffRegistrationAvailability::Path)
                                            —
                                        @elseif ($capacity !== null)
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

            <div class="sui-pager sui-programs-pager">
                <span class="sui-pager__label">{{ en_num($programs->firstItem() ?? 0) }}–{{ en_num($programs->lastItem() ?? 0) }} من {{ en_num($programs->total()) }}</span>
                @foreach ([
                    ['url' => $programs->url(1), 'enabled' => ! $programs->onFirstPage(), 'icon' => 'chevrons-right', 'label' => 'الصفحة الأولى'],
                    ['url' => $programs->previousPageUrl(), 'enabled' => ! $programs->onFirstPage(), 'icon' => 'chevron-right', 'label' => 'السابق'],
                    ['url' => $programs->nextPageUrl(), 'enabled' => $programs->hasMorePages(), 'icon' => 'chevron-left', 'label' => 'التالي'],
                    ['url' => $programs->url($programs->lastPage()), 'enabled' => $programs->hasMorePages(), 'icon' => 'chevrons-left', 'label' => 'الصفحة الأخيرة'],
                ] as $pager)
                    @if ($pager['enabled'])
                        <a class="sui-icon-btn" href="{{ $pager['url'] }}" aria-label="{{ $pager['label'] }}"><i data-lucide="{{ $pager['icon'] }}" class="sui-icon"></i></a>
                    @else
                        <span class="sui-icon-btn" aria-disabled="true" aria-label="{{ $pager['label'] }}"><i data-lucide="{{ $pager['icon'] }}" class="sui-icon"></i></span>
                    @endif
                @endforeach
            </div>
        @endif
    </section>
</x-staff-ui.layout>
