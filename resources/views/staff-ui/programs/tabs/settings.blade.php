@php
    use App\Enums\ProgramStatus;
@endphp

<nav class="sui-settings-nav" aria-label="أقسام الإعدادات">
    @foreach ($sectionLabels as $key => $label)
        <a href="#{{ $key }}">{{ $label }}</a>
    @endforeach
    <a href="#status">النشر والأرشفة</a>
</nav>

<section id="basics" class="sui-card sui-settings-section">
    <div class="sui-card__head"><h2>{{ $sectionLabels['basics'] }}</h2></div>
    <form id="settings-basics" class="sui-wizard" data-sui-program-form method="POST" action="{{ route('staff-ui.programs.settings.update', [$program, 'basics']) }}" enctype="multipart/form-data">
        @csrf
        @include('staff-ui.programs.partials.form-basics')
        <div class="sui-form-actions">
            <x-staff-ui.button type="submit">حفظ</x-staff-ui.button>
        </div>
    </form>
</section>

<section id="schedule" class="sui-card sui-settings-section">
    <div class="sui-card__head"><h2>{{ $sectionLabels['schedule'] }}</h2></div>
    <form id="settings-schedule" class="sui-wizard" data-sui-program-form method="POST" action="{{ route('staff-ui.programs.settings.update', [$program, 'schedule']) }}">
        @csrf
        @include('staff-ui.programs.partials.form-schedule')
        <div class="sui-form-actions">
            <x-staff-ui.button type="submit">حفظ</x-staff-ui.button>
        </div>
    </form>
</section>

<section id="acceptance" class="sui-card sui-settings-section">
    <div class="sui-card__head"><h2>{{ $sectionLabels['acceptance'] }}</h2></div>
    <form id="settings-acceptance" class="sui-wizard" data-sui-program-form method="POST" action="{{ route('staff-ui.programs.settings.update', [$program, 'acceptance']) }}" data-sui-acceptance-url="{{ route('staff-ui.programs.settings.acceptance-preview', $program) }}">
        @csrf
        @include('staff-ui.programs.partials.form-acceptance')
        <div class="sui-form-actions">
            <x-staff-ui.button type="submit">حفظ</x-staff-ui.button>
        </div>
    </form>
</section>

<section id="message" class="sui-card sui-settings-section">
    <div class="sui-card__head"><h2>{{ $sectionLabels['message'] }}</h2></div>
    <form id="settings-message" class="sui-wizard" data-sui-program-form method="POST" action="{{ route('staff-ui.programs.settings.update', [$program, 'message']) }}">
        @csrf
        @include('staff-ui.programs.partials.form-message', ['formId' => 'settings-message', 'previewMailAction' => route('staff-ui.programs.settings.preview-mail', $program)])
        <div class="sui-form-actions">
            <x-staff-ui.button type="submit">حفظ</x-staff-ui.button>
        </div>
    </form>
</section>

<section id="notifications" class="sui-card sui-settings-section">
    <div class="sui-card__head"><h2>{{ $sectionLabels['notifications'] }}</h2></div>
    <form id="settings-notifications" class="sui-wizard" method="POST" action="{{ route('staff-ui.programs.settings.update', [$program, 'notifications']) }}">
        @csrf
        <x-staff-ui.toggle name="notify_audience" value="1" label="إشعارات المستفيدين" :checked="$notifyOn" />
        <p class="sui-wizard-hint">يضبط إشعار النشر، وإشعار فتح التسجيل وإغلاقه، وإشعار المسجّلين عند التحديث معاً.</p>
        <div class="sui-form-actions">
            <x-staff-ui.button type="submit">حفظ</x-staff-ui.button>
        </div>
    </form>
</section>

<section id="status" class="sui-card sui-settings-section">
    <div class="sui-card__head">
        <h2>النشر والأرشفة</h2>
        <p>الحالة الآن: <strong>{{ $program->status?->label() }}</strong></p>
    </div>
    <div class="sui-settings-status">
        @if ($program->status === ProgramStatus::Draft && $canPublish)
            <form method="POST" action="{{ route('staff-ui.programs.status', $program) }}">
                @csrf
                <input type="hidden" name="action" value="publish">
                <x-staff-ui.button type="submit" icon="send">نشر البرنامج</x-staff-ui.button>
            </form>
        @endif
        @if ($program->status === ProgramStatus::Published && $canPublish)
            <form method="POST" action="{{ route('staff-ui.programs.status', $program) }}" onsubmit="return confirm('سيختفي البرنامج من الموقع ويصبح مسودة. متابعة؟');">
                @csrf
                <input type="hidden" name="action" value="unpublish">
                <x-staff-ui.button type="submit" variant="secondary" icon="eye-off">إرجاع إلى مسودة</x-staff-ui.button>
            </form>
        @endif
        @if ($program->status !== ProgramStatus::Archived && $canArchive)
            <form method="POST" action="{{ route('staff-ui.programs.status', $program) }}" onsubmit="return confirm('سينتقل البرنامج إلى الأرشيف ويختفي من الموقع. متابعة؟');">
                @csrf
                <input type="hidden" name="action" value="archive">
                <x-staff-ui.button type="submit" variant="secondary" icon="archive">أرشفة البرنامج</x-staff-ui.button>
            </form>
        @endif
        @if ($program->status === ProgramStatus::Archived && $canArchive)
            <form method="POST" action="{{ route('staff-ui.programs.status', $program) }}">
                @csrf
                <input type="hidden" name="action" value="restore">
                <x-staff-ui.button type="submit" variant="secondary" icon="archive-restore">استعادة كمسودة</x-staff-ui.button>
            </form>
        @endif
        @unless ($canPublish || $canArchive)
            <p class="sui-wizard-hint">تغيير حالة النشر يحتاج صلاحية النشر أو الأرشفة.</p>
        @endunless
    </div>
</section>

@include('staff-ui.programs.partials.form-scripts')
