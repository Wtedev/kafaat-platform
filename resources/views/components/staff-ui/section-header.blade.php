@props([
    'title',
])

<div {{ $attributes->class('sui-section-head') }}>
    <h2 class="sui-section-head__title">{{ $title }}</h2>
    <div class="sui-section-head__actions">
        <x-staff-ui.dropdown align="end">
            <x-slot:trigger>
                <x-staff-ui.button variant="secondary" size="sm" icon="calendar" type="button">نطاق التاريخ</x-staff-ui.button>
            </x-slot:trigger>
            <div class="sui-popover">
                <x-staff-ui.date label="من" name="range_from" />
                <x-staff-ui.date label="إلى" name="range_to" />
            </div>
        </x-staff-ui.dropdown>
        <x-staff-ui.dropdown align="end">
            <x-slot:trigger>
                <x-staff-ui.button variant="secondary" size="sm" icon="chevron-down" type="button">آخر 30 يوم</x-staff-ui.button>
            </x-slot:trigger>
            <button type="button" class="sui-menu-item">آخر 7 أيام</button>
            <button type="button" class="sui-menu-item">آخر 30 يوم</button>
            <button type="button" class="sui-menu-item">آخر 90 يوم</button>
        </x-staff-ui.dropdown>
        <x-staff-ui.button
            variant="secondary"
            size="sm"
            icon="download"
            type="button"
            data-sui-toast
            data-tone="info"
            data-title="معاينة"
            data-body="زر التصدير شكلي في هذه الصفحة."
        >تصدير</x-staff-ui.button>
    </div>
</div>
