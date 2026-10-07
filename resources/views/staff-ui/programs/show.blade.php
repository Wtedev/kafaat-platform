<x-staff-ui.layout :name="$staffName" :email="$staffEmail" crumb="البرنامج" :dashboard-active="false" active-nav="programs">
    <header class="sui-page-head">
        <h1>صفحة البرنامج قيد البناء</h1>
    </header>
    <p class="sui-program-placeholder">{{ $program->title }}</p>
</x-staff-ui.layout>
