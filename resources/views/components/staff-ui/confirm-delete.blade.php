@props([
    'name' => 'confirm-delete',
    'title' => 'حذف السجل؟',
    'message' => 'هذا الحوار للمعاينة فقط، ولن يُحذف أي شيء.',
])

<x-staff-ui.modal :name="$name" :title="$title">
    {{ $message }}
    <x-slot:actions>
        <x-staff-ui.button variant="secondary" size="sm" type="button" data-sui-modal-close>إلغاء</x-staff-ui.button>
        <x-staff-ui.button
            variant="danger"
            size="sm"
            type="button"
            data-sui-modal-close
            data-sui-toast
            data-tone="danger"
            data-title="لم يُحذف شيء"
            data-body="المعاينة لا تنفّذ الحذف."
        >حذف</x-staff-ui.button>
    </x-slot:actions>
</x-staff-ui.modal>
