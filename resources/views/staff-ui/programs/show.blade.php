<x-staff-ui.layout :name="$staffName" :email="$staffEmail" crumb="البرامج" :dashboard-active="false" active-nav="programs">
    <header class="sui-page-head sui-program-head">
        <a class="sui-back" href="{{ route('staff-ui.programs.index') }}">البرامج</a>
        <div class="sui-program-head__row">
            <x-staff-ui.program-cover :program="$program" variant="thumb" />
            <div class="sui-program-head__text">
                <h1>{{ $program->title }}</h1>
                <div class="sui-program-head__meta">
                    <x-staff-ui.status :tone="$programStatus->programStatusTone($program)">{{ $programStatus->programStatusLabel($program) }}</x-staff-ui.status>
                    <x-staff-ui.status :tone="$availability->tone()">{{ $availability->label() }}</x-staff-ui.status>
                    @if ($program->program_kind)
                        <span class="sui-program-head__kind">{{ $program->program_kind->label() }}</span>
                    @endif
                </div>
            </div>
            <div class="sui-program-head__actions">
                @if ($canResumeWizard)
                    <x-staff-ui.button size="sm" :href="route('staff-ui.programs.wizard', [$program, 1])">متابعة الإنشاء</x-staff-ui.button>
                @endif
                @if ($program->status === \App\Enums\ProgramStatus::Published)
                    <x-staff-ui.button variant="secondary" size="sm" icon="external-link" :href="route('public.programs.show', $program)" target="_blank">الصفحة العامة</x-staff-ui.button>
                @endif
            </div>
        </div>
    </header>

    <x-staff-ui.tabs group="program" class="sui-program-tabs" :tabs="$tabs" />

    @if (session('status'))
        <p class="sui-alert sui-program-alert" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->has('status'))
        <p class="sui-alert sui-alert--danger sui-program-alert" role="alert">{{ $errors->first('status') }}</p>
    @endif

    <div class="sui-stack">
        @switch ($tab)
            @case ('about')
                @include('staff-ui.programs.tabs.about')
                @break
            @case ('registrations')
                @include('staff-ui.programs.tabs.registrations')
                @break
            @case ('attendance')
                @include('staff-ui.programs.tabs.attendance')
                @break
            @case ('surveys')
                @include('staff-ui.programs.tabs.surveys')
                @break
            @case ('certificates')
                @include('staff-ui.programs.tabs.certificates')
                @break
            @case ('activity')
                @include('staff-ui.programs.tabs.activity')
                @break
            @case ('settings')
                @include('staff-ui.programs.tabs.settings')
                @break
            @default
                <section class="sui-card sui-empty">
                    <i data-lucide="construction" class="sui-icon"></i>
                    <h2>تبويب «{{ \App\Http\Controllers\StaffUi\StaffProgramPageController::TABS[$tab] }}» قيد البناء</h2>
                    <p>إلى أن يكتمل، يُدار من لوحة الإدارة الحالية.</p>
                    <x-staff-ui.button variant="secondary" size="sm" icon="external-link" :href="$adminUrl">فتح في لوحة الإدارة</x-staff-ui.button>
                </section>
        @endswitch
    </div>
</x-staff-ui.layout>
