@extends('layouts.portal')
@section('title', 'إعدادات التنبيهات')

@section('content')
@php
    use App\Enums\NotificationPreferenceCategory;
    use App\Services\Inbox\UserNotificationPreferences;

    $prefs = app(UserNotificationPreferences::class)->resolvedCategories($user);
@endphp

<x-portal.settings-shell title="إعدادات التنبيهات" subtitle="تحكم في التنبيهات داخل المنصة والبريد.">
    <form method="POST" action="{{ route('portal.notifications.settings.update') }}" class="space-y-4" id="notif-settings-form">
        @csrf
        @method('PATCH')

        @if ($errors->any())
        <div class="rounded-xl {{ config('brand.classes.alert_danger') }} px-4 py-3 text-sm">
            تعذّر حفظ الإعدادات. راجع الحقول وحاول مرة أخرى.
        </div>
        @endif

        <x-portal.settings-card>
            <p class="border-b border-slate-100 px-4 py-2.5 text-[11px] font-semibold text-slate-400 sm:px-5">داخل المنصة</p>
            <div class="divide-y divide-slate-100">
                @foreach (NotificationPreferenceCategory::forBeneficiarySettings() as $category)
                @php
                    $saved = $prefs[$category->value] ?? $category->defaultPreferences();
                    $inAppChecked = old('categories.'.$category->value.'.in_app', $saved['in_app']);
                    $emailChecked = old('categories.'.$category->value.'.email', $saved['email']);
                @endphp
                <div class="px-4 py-3.5 sm:px-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 text-right">
                            <p class="text-sm font-medium text-gray-900">{{ $category->label() }}</p>
                            @if (! $category->canDisableInApp())
                            <p class="mt-0.5 text-[11px] font-medium text-[#335483]">مفعّل دائماً</p>
                            @endif
                        </div>
                        @if ($category->canDisableInApp())
                        <label class="flex shrink-0 cursor-pointer items-center gap-2">
                            <input type="checkbox" name="categories[{{ $category->value }}][in_app]" value="1" @checked($inAppChecked) class="notif-cat-in-app h-4 w-4 rounded border-slate-300 text-[#335483] focus:ring-[#335483]/30" data-category="{{ $category->value }}" />
                        </label>
                        @else
                        <input type="hidden" name="categories[{{ $category->value }}][in_app]" value="1" />
                        @endif
                    </div>

                    @if ($category->supportsEmail())
                    <label class="notif-email-row mt-2.5 flex cursor-pointer items-center justify-between gap-3 border-t border-slate-100 pt-2.5" data-category="{{ $category->value }}">
                        <span class="text-xs text-gray-500">نسخة بريدية</span>
                        <input type="checkbox" name="categories[{{ $category->value }}][email]" value="1" @checked($emailChecked) class="notif-cat-email h-4 w-4 rounded border-slate-300 text-[#335483] focus:ring-[#335483]/30" />
                    </label>
                    @endif
                </div>
                @endforeach
            </div>
        </x-portal.settings-card>

        <x-portal.settings-card class="px-4 py-3.5 sm:px-5">
            <label for="notify_email" class="flex cursor-pointer items-center justify-between gap-4">
                <div class="text-right">
                    <p class="text-sm font-semibold text-gray-900">البريد الإلكتروني</p>
                    <p class="mt-0.5 text-xs text-gray-500" dir="ltr">{{ $user->email }}</p>
                </div>
                <input type="checkbox" id="notify_email" name="notify_email" value="1" @checked(old('notify_email', $user->notify_email)) class="h-4 w-4 shrink-0 rounded border-slate-300 text-[#335483] focus:ring-[#335483]/30" />
            </label>
        </x-portal.settings-card>

        <div class="flex justify-end">
            <button type="submit" class="rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#335483">
                حفظ
            </button>
        </div>
    </form>
</x-portal.settings-shell>

@push('scripts')
<script>
(function () {
    var form = document.getElementById('notif-settings-form');
    if (!form) return;

    function syncEmailRows() {
        var master = form.querySelector('#notify_email');
        var masterOn = master && master.checked;
        form.querySelectorAll('.notif-email-row').forEach(function (row) {
            var cat = row.getAttribute('data-category');
            var inApp = form.querySelector('.notif-cat-in-app[data-category="' + cat + '"]');
            var inAppOn = !inApp || inApp.checked;
            var show = masterOn && inAppOn;
            row.classList.toggle('opacity-40', !show);
            row.classList.toggle('pointer-events-none', !show);
        });
    }

    form.addEventListener('change', syncEmailRows);
    syncEmailRows();
})();
</script>
@endpush
@endsection
