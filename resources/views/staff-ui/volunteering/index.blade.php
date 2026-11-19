@php
    use App\Enums\OpportunityStatus;

    $views = [
        'all' => 'الكل',
        'published' => 'منشورة',
        'draft' => 'مسودات',
        'archived' => 'الأرشيف',
    ];
    $tones = [
        OpportunityStatus::Published->value => 'success',
        OpportunityStatus::Draft->value => 'muted',
        OpportunityStatus::Archived->value => 'warning',
    ];
    $statusLabels = [
        OpportunityStatus::Published->value => 'منشورة',
        OpportunityStatus::Draft->value => 'مسودة',
        OpportunityStatus::Archived->value => 'مؤرشفة',
    ];
    $clearUrl = route('staff-ui.volunteering.index', $view !== 'all' ? ['view' => $view] : []);
    $gradients = config('brand.image_gradients', ['linear-gradient(135deg, #eef6f1, #d9ebe0)']);
    $programs = $opportunities;
@endphp

<x-staff-ui.layout :name="$staffName" :email="$staffEmail" crumb="الفرص التطوعية" :dashboard-active="false" active-nav="volunteering">
    <header class="sui-page-head sui-staff-head">
        <h1>الفرص التطوعية</h1>
        @if ($canCreate)
            <x-staff-ui.button size="sm" :href="route('staff-ui.volunteering.create')" icon="plus">إضافة فرصة</x-staff-ui.button>
        @endif
    </header>

    @if (session('status'))
        <p class="sui-alert sui-program-alert" role="status">{{ session('status') }}</p>
    @endif

    <p class="sui-vol-note">
        <i data-lucide="info" class="sui-icon sui-icon--sm"></i>
        يتم التسجيل في الفرص واحتساب الساعات وإصدار الشهادات عبر المنصة الوطنية للعمل التطوعي. وتعرض هذه الصفحة الفرص في موقع كفاءات مع رابط التسجيل.
    </p>

    <nav class="sui-reg-chips sui-programs-views" aria-label="القائمة">
        @foreach ($views as $key => $label)
            <a href="{{ route('staff-ui.volunteering.index', $key === 'all' ? [] : ['view' => $key]) }}" @class(['sui-reg-chip', 'is-active' => $view === $key])>
                {{ $label }} <span class="sui-reg-chip__count">{{ en_num($viewCounts[$key]) }}</span>
            </a>
        @endforeach
    </nav>

    <section class="sui-card sui-programs-panel">
        <form class="sui-table__filters sui-staff-filters sui-programs-filters" method="GET" action="{{ route('staff-ui.volunteering.index') }}">
            @if ($view !== 'all')
                <input type="hidden" name="view" value="{{ $view }}">
            @endif
            <label class="sui-search sui-staff-search">
                <i data-lucide="search" class="sui-icon"></i>
                <input type="search" name="q" value="{{ $search }}" placeholder="عنوان الفرصة" aria-label="بحث بالعنوان" autocomplete="off">
            </label>
            @if ($search !== '')
                <a class="sui-staff-clear" href="{{ $clearUrl }}">مسح البحث</a>
            @endif
            <div class="sui-programs-toolbar">
                <span class="sui-staff-count">{{ en_num($opportunities->total()) }} فرصة</span>
            </div>
        </form>

        @if ($opportunities->isEmpty())
            <div class="sui-programs-empty">
                <i data-lucide="{{ $search !== '' ? 'search-x' : 'hand-heart' }}" class="sui-icon"></i>
                @if ($search !== '')
                    <p>لا توجد فرص مطابقة للبحث.</p>
                    <a class="sui-staff-clear" href="{{ $clearUrl }}">مسح البحث</a>
                @else
                    <p>{{ ['all' => 'لا توجد فرص تطوعية بعد.', 'published' => 'لا توجد فرص منشورة.', 'draft' => 'لا توجد مسودات.', 'archived' => 'لا توجد فرص مؤرشفة.'][$view] }}</p>
                @endif
            </div>
        @else
            <div class="sui-table-wrap">
                <table class="sui-table">
                    <thead>
                        <tr>
                            <th>الفرصة</th>
                            <th>الحالة</th>
                            <th>الفترة</th>
                            <th>السعة</th>
                            <th>رابط التسجيل</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($opportunities as $index => $opportunity)
                            @php $status = $opportunity->status?->value ?? 'draft'; @endphp
                            <tr>
                                <td data-label="الفرصة">
                                    <a class="sui-program-row__program sui-vol-row__link" href="{{ route('staff-ui.volunteering.edit', $opportunity) }}">
                                        <div class="sui-program-thumb">
                                            @if (filled($opportunity->image))
                                                <div class="sui-program-thumb__media">
                                                    <img src="{{ $opportunity->imagePublicUrl() }}" alt="" class="sui-program-thumb__img" loading="lazy" decoding="async">
                                                </div>
                                            @else
                                                <div class="sui-program-thumb__placeholder" style="background: {{ $gradients[$index % max(1, count($gradients))] }}" aria-hidden="true">
                                                    <i data-lucide="hand-heart" class="sui-icon"></i>
                                                </div>
                                            @endif
                                        </div>
                                        <strong>{{ $opportunity->title }}</strong>
                                    </a>
                                </td>
                                <td data-label="الحالة">
                                    <x-staff-ui.status :tone="$tones[$status] ?? 'muted'">{{ $statusLabels[$status] ?? $status }}</x-staff-ui.status>
                                </td>
                                <td data-label="الفترة" class="sui-reg-table__time">
                                    @if ($opportunity->start_date || $opportunity->end_date)
                                        {{ $opportunity->start_date ? en_digits(ar_date($opportunity->start_date, 'd MMM y')) : '—' }}
                                        @if ($opportunity->end_date && ! $opportunity->end_date->equalTo($opportunity->start_date))
                                            – {{ en_digits(ar_date($opportunity->end_date, 'd MMM y')) }}
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td data-label="السعة">{{ $opportunity->capacity !== null ? en_num($opportunity->capacity) : '—' }}</td>
                                <td data-label="رابط التسجيل">
                                    @if ($opportunity->hasExternalUrl())
                                        <a class="sui-vol-link" href="{{ $opportunity->external_url }}" target="_blank" rel="noopener noreferrer">
                                            <i data-lucide="external-link" class="sui-icon sui-icon--sm"></i> فتح الرابط
                                        </a>
                                    @else
                                        <x-staff-ui.status tone="warning">بلا رابط</x-staff-ui.status>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('staff-ui.programs.partials.index-pager')
        @endif
    </section>
</x-staff-ui.layout>
