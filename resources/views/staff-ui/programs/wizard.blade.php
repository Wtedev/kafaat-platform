@php
    $action = $program
        ? route('staff-ui.programs.wizard.store', [$program, $step])
        : route('staff-ui.programs.wizard.store-new');
    $steps = [
        1 => 'البيانات الأساسية',
        2 => 'الجدول',
        3 => 'القبول',
        4 => 'الرسالة والمجموعات',
        5 => 'النشر',
        6 => 'المراجعة',
    ];
@endphp

<x-staff-ui.layout :name="$staffName" :email="$staffEmail" crumb="البرامج" :dashboard-active="false" active-nav="programs">
    <header class="sui-page-head sui-staff-head">
        <h1>إضافة برنامج</h1>
        <x-staff-ui.button variant="secondary" size="sm" :href="route('staff-ui.programs.index')" icon="arrow-right">القائمة</x-staff-ui.button>
    </header>

    @if (session('status'))
        <p class="sui-wizard-status">{{ session('status') }}</p>
    @endif

    <ol class="sui-wizard-steps">
        @foreach ($steps as $number => $label)
            <li @class(['is-current' => $step === $number])>
                @if ($program && $number !== $step)
                    <button form="program-wizard" type="submit" name="goto" value="{{ $number }}">{{ $number }}. {{ $label }}</button>
                @else
                    <span>{{ $number }}. {{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>

    <form id="program-wizard" class="sui-card sui-wizard" data-sui-program-form method="POST" action="{{ $action }}" enctype="multipart/form-data" @if ($program) data-sui-acceptance-url="{{ route('staff-ui.programs.wizard.acceptance-preview', $program) }}" @endif>
        @csrf

        @if ($step === 1)
            @include('staff-ui.programs.partials.form-basics')
        @endif

        @if ($step === 2)
            @include('staff-ui.programs.partials.form-schedule')
        @endif

        @if ($step === 3)
            @include('staff-ui.programs.partials.form-acceptance')
        @endif

        @if ($step === 4)
            @include('staff-ui.programs.partials.form-message', ['formId' => 'program-wizard', 'previewMailAction' => route('staff-ui.programs.wizard.preview-mail', $program)])
        @endif

        @if ($step === 5)
            <x-staff-ui.toggle name="publish" value="1" label="نشر البرنامج الآن" :checked="$publishIntent" />
            @unless ($canPublish)
                <p class="sui-wizard-hint">النشر يحتاج صلاحية نشر البرامج. يمكنك حفظه كمسودة.</p>
            @endunless
            <x-staff-ui.toggle name="notify_audience" value="1" label="إشعارات المستفيدين" :checked="$notifyIntent" />
            <p class="sui-wizard-hint">يضبط إشعار النشر، وإشعار المحطات، وإشعار المسجّلين عند التحديث معاً.</p>
        @endif

        @if ($step === 6)
            @php
                $presenterSummary = collect(is_array($program->program_presenters) ? $program->program_presenters : [])
                    ->map(function (mixed $row): string {
                        if (! is_array($row)) {
                            return '';
                        }
                        $name = trim((string) ($row['name'] ?? ''));
                        $role = trim((string) ($row['role'] ?? ''));

                        return $name === '' ? '' : ($role !== '' ? $name.' — '.$role : $name);
                    })
                    ->filter()
                    ->implode('، ');
                $topicSummary = $program->session_topics_enabled
                    ? collect(is_array($program->session_topics) ? $program->session_topics : [])
                        ->map(function (mixed $row): string {
                            if (! is_array($row)) {
                                return '';
                            }
                            $title = trim((string) ($row['title'] ?? ''));
                            $who = trim((string) ($row['facilitators'] ?? ''));

                            return $title === '' ? '' : ($who !== '' ? $title.' — '.$who : $title);
                        })
                        ->filter()
                        ->implode('، ')
                    : '';
                $groupSummary = $program->whatsapp_groups_enabled
                    ? collect([
                        filled($program->whatsapp_group_male) ? 'رجال: '.$program->whatsapp_group_male : null,
                        filled($program->whatsapp_group_female) ? 'نساء: '.$program->whatsapp_group_female : null,
                    ])->filter()->implode('، ')
                    : 'غير مفعّلة';
            @endphp
            <input type="hidden" name="publish" value="{{ $publishIntent ? '1' : '0' }}">
            <dl class="sui-wizard-summary">
                <div><dt>العنوان</dt><dd>{{ $program->title }}</dd></div>
                <div><dt>النوع</dt><dd>{{ $program->program_kind?->label() }}</dd></div>
                <div><dt>مسار الكفاءة</dt><dd>{{ $program->competency_track?->shortLabel() }}</dd></div>
                <div><dt>أسلوب التنفيذ</dt><dd>{{ $program->delivery_mode?->label() }}</dd></div>
                <div><dt>المكان</dt><dd>{{ $program->venue ?: '—' }}</dd></div>
                <div><dt>المقدمون</dt><dd>{{ $presenterSummary !== '' ? $presenterSummary : '—' }}</dd></div>
                <div><dt>المحاور</dt><dd>{{ $topicSummary !== '' ? $topicSummary : '—' }}</dd></div>
                <div><dt>البداية</dt><dd>{{ $program->start_date?->toDateString() ?: '—' }}</dd></div>
                <div><dt>النهاية</dt><dd>{{ $program->end_date?->toDateString() ?: '—' }}</dd></div>
                <div><dt>التسجيل</dt><dd>{{ $program->registration_start?->toDateString() ?: '—' }} – {{ $program->registration_end?->toDateString() ?: '—' }}</dd></div>
                <div><dt>رسالة القبول</dt><dd>{{ filled($program->approval_message) ? trim(strip_tags((string) $program->approval_message)) : 'الرسالة الافتراضية' }}</dd></div>
                <div><dt>روابط المجموعات</dt><dd>{{ $groupSummary !== '' ? $groupSummary : '—' }}</dd></div>
                <div><dt>النشر</dt><dd>{{ $publishIntent ? 'منشور' : 'مسودة' }}</dd></div>
                <div><dt>الإشعارات</dt><dd>{{ $notifyIntent ? 'مفعّلة' : 'متوقفة' }}</dd></div>
            </dl>
            <h2 class="sui-wizard-sub">شروط القبول</h2>
            <ul>
                @forelse ($acceptancePreview as $line)
                    <li>{{ $line }}</li>
                @empty
                    <li>بلا شروط إضافية</li>
                @endforelse
            </ul>
            <div class="sui-form-actions">
                @foreach ([1 => 'البيانات', 2 => 'الجدول', 3 => 'القبول', 4 => 'الرسالة', 5 => 'النشر'] as $number => $label)
                    <x-staff-ui.button variant="secondary" size="sm" :href="route('staff-ui.programs.wizard', [$program, $number])">تعديل {{ $label }}</x-staff-ui.button>
                @endforeach
                <x-staff-ui.button variant="secondary" size="sm" :href="route('staff-ui.programs.wizard.preview', $program)" target="_blank">معاينة الصفحة العامة</x-staff-ui.button>
            </div>
        @endif

        <div class="sui-form-actions">
            @if ($program && $step > 1)
                <x-staff-ui.button type="submit" name="goto" value="{{ $step - 1 }}" variant="secondary">السابق</x-staff-ui.button>
            @endif
            @if ($step < 6)
                <x-staff-ui.button type="submit">التالي</x-staff-ui.button>
            @else
                <x-staff-ui.button type="submit">حفظ البرنامج</x-staff-ui.button>
            @endif
        </div>
    </form>

    @include('staff-ui.programs.partials.form-scripts')
</x-staff-ui.layout>
