<x-staff-ui.layout :name="$staffName" :email="$staffEmail" crumb="البرامج" :dashboard-active="false" active-nav="programs">
    <header class="sui-page-head">
        <h1>{{ $program->title }}</h1>
    </header>

    <section class="sui-card sui-empty">
        <i data-lucide="construction" class="sui-icon"></i>
        <h2>صفحة البرنامج قيد البناء</h2>
        <p>{{ $program->title }}</p>
        @if ($canResumeWizard)
            <x-staff-ui.button size="sm" :href="route('staff-ui.programs.wizard', [$program, 1])">متابعة الإنشاء</x-staff-ui.button>
        @endif
        <x-staff-ui.button variant="secondary" size="sm" :href="route('staff-ui.programs.index')" icon="arrow-right">
            العودة إلى قائمة البرامج
        </x-staff-ui.button>
    </section>
</x-staff-ui.layout>
