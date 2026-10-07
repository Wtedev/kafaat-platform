@php
    $hasPrograms = $programs->isNotEmpty();
@endphp

<x-staff-ui.layout :name="$staffName" :email="$staffEmail" crumb="البرامج" :dashboard-active="false" active-nav="programs">
    <header class="sui-page-head sui-programs-head">
        <div>
            <h1>البرامج</h1>
            <p class="sui-programs-count">{{ $programs->total() }} برنامج</p>
        </div>
        @if ($hasPrograms)
            <div class="sui-view-toggle" role="group" aria-label="طريقة العرض" data-sui-program-toggle>
                <button type="button" data-sui-program-view="cards" aria-pressed="true">بطاقات</button>
                <button type="button" data-sui-program-view="table" aria-pressed="false">جدول</button>
            </div>
        @endif
    </header>

    <form class="sui-table__filters sui-programs-filters" method="GET" action="{{ route('staff-ui.programs.index') }}" data-sui-staff-filters>
        <label class="sui-search sui-programs-search">
            <i data-lucide="search" class="sui-icon"></i>
            <input type="search" name="q" value="{{ $search }}" placeholder="اسم البرنامج" aria-label="بحث بالاسم" data-sui-staff-search autocomplete="off">
        </label>
        <x-staff-ui.select name="kind" label="نوع البرنامج" :selected="$kind" :options="$kindOptions" />
        <x-staff-ui.select name="status" label="حالة البرنامج" :selected="$status" :options="$statusOptions" />
        @if ($filtersActive)
            <a class="sui-staff-clear" href="{{ route('staff-ui.programs.index') }}">مسح الفلاتر</a>
        @endif
    </form>

    @if (! $hasPrograms)
        <div class="sui-programs-empty">
            @if ($filtersActive)
                <i data-lucide="search-x" class="sui-icon"></i>
                <p>لا توجد برامج مطابقة للبحث أو الفلاتر.</p>
                <x-staff-ui.button variant="secondary" size="sm" :href="route('staff-ui.programs.index')">مسح الفلاتر</x-staff-ui.button>
            @else
                <i data-lucide="graduation-cap" class="sui-icon"></i>
                <p>لا توجد برامج بعد.</p>
            @endif
        </div>
    @else
        <script>
            (function () {
                var view = "cards";
                try {
                    view = localStorage.getItem("staff-ui.programs.view") || "cards";
                } catch (error) {}
                if (view !== "table") view = "cards";
                document.documentElement.setAttribute("data-sui-program-view", view);
            })();
        </script>

        <div class="sui-programs sui-programs--cards" data-sui-staff-directory>
            @foreach ($programs as $program)
                @php
                    $snapshot = $statusService->snapshot($program);
                    $cover = $program->image;
                    $contain = filled($cover) && $program->imageUsesContainFit();
                    $surface = $contain ? $program->imageHeroSurfaceColor() : null;
                @endphp
                <a
                    class="sui-program-card"
                    href="{{ route('staff-ui.programs.show', $program) }}"
                    data-registration="{{ $snapshot->registrationKey }}"
                    data-pending="{{ $snapshot->pendingCount }}"
                    data-accepted="{{ $snapshot->acceptedLabel }}"
                    data-publication="{{ $snapshot->publicationLine }}"
                >
                    <div @class(['sui-program-cover', 'is-fallback' => blank($cover)]) @if ($surface) style="background: {{ $surface }}" @endif>
                        @if (filled($cover))
                            <img src="{{ $program->imagePublicUrl() }}" alt="" @class(['is-contain' => $contain])>
                        @else
                            <span class="sui-program-cover__mark" aria-hidden="true">
                                <i data-lucide="graduation-cap" class="sui-icon"></i>
                            </span>
                        @endif
                    </div>
                    <div class="sui-program-card__body">
                        <h2>{{ $program->title }}</h2>
                        <div class="sui-program-badges">
                            <x-staff-ui.status :tone="$snapshot->programTone">{{ $snapshot->programLabel }}</x-staff-ui.status>
                            <x-staff-ui.status :tone="$snapshot->registrationTone">{{ $snapshot->registrationLabel }}</x-staff-ui.status>
                        </div>
                        <div class="sui-program-metrics">
                            <p class="sui-program-pending">
                                <span class="sui-program-pending__value">{{ $snapshot->pendingCount }}</span>
                                <span>طلبات معلّقة</span>
                            </p>
                            <p class="sui-program-accepted">
                                <span>{{ $snapshot->acceptedLabel }}</span>
                                <span>مقبولون من السعة</span>
                            </p>
                        </div>
                        <p class="sui-program-published">{{ $snapshot->publicationLine ?? $snapshot->programLabel }}</p>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="sui-programs sui-programs--table">
            <x-staff-ui.data-table
                server
                :columns="[
                    ['label' => 'البرنامج', 'key' => 'title'],
                    ['label' => 'حالة البرنامج', 'key' => 'program_status'],
                    ['label' => 'حالة التسجيل', 'key' => 'registration'],
                    ['label' => 'طلبات معلّقة', 'key' => 'pending'],
                    ['label' => 'مقبولون من السعة', 'key' => 'accepted'],
                    ['label' => 'النشر', 'key' => 'published'],
                ]"
            >
                @foreach ($programs as $program)
                    @php
                        $snapshot = $statusService->snapshot($program);
                        $cover = $program->image;
                    @endphp
                    <tr data-sui-row-href="{{ route('staff-ui.programs.show', $program) }}" data-registration="{{ $snapshot->registrationKey }}">
                        <td data-label="البرنامج">
                            <a class="sui-program-row" href="{{ route('staff-ui.programs.show', $program) }}">
                                @if (filled($cover))
                                    <img class="sui-program-thumb" src="{{ $program->imagePublicUrl() }}" alt="">
                                @else
                                    <span class="sui-program-thumb is-fallback" aria-hidden="true"></span>
                                @endif
                                <span>{{ $program->title }}</span>
                            </a>
                        </td>
                        <td data-label="حالة البرنامج">
                            <x-staff-ui.status :tone="$snapshot->programTone">{{ $snapshot->programLabel }}</x-staff-ui.status>
                        </td>
                        <td data-label="حالة التسجيل">
                            <x-staff-ui.status :tone="$snapshot->registrationTone">{{ $snapshot->registrationLabel }}</x-staff-ui.status>
                        </td>
                        <td data-label="طلبات معلّقة"><strong class="sui-program-pending__value">{{ $snapshot->pendingCount }}</strong></td>
                        <td data-label="مقبولون من السعة">{{ $snapshot->acceptedLabel }}</td>
                        <td data-label="النشر">{{ $snapshot->publicationLine ?? $snapshot->programLabel }}</td>
                    </tr>
                @endforeach
            </x-staff-ui.data-table>
        </div>

        <div class="sui-pager">
            <span class="sui-pager__label">{{ $programs->firstItem() ?? 0 }}–{{ $programs->lastItem() ?? 0 }} من {{ $programs->total() }}</span>
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
</x-staff-ui.layout>
