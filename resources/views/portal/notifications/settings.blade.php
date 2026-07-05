@extends('layouts.portal')
@section('title', 'إعدادات التنبيهات')

@section('content')
@php
    use App\Enums\NotificationPreferenceCategory;
    use App\Services\Inbox\UserNotificationPreferences;

    $prefs = app(UserNotificationPreferences::class)->resolvedCategories($user);
@endphp

<section class="mb-6 flex flex-wrap items-start justify-between gap-3 text-right">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">إعدادات التنبيهات</h1>
        <p class="mt-2 max-w-2xl text-sm text-gray-600">اختر ما يهمّك فقط. التنبيهات المهمة (مثل قبول تسجيلك) تبقى داخل المنصة دائماً، وباقي الفئات يمكنك التحكم بها لتجنّب الإزعاج.</p>
    </div>
    <a href="{{ route('portal.settings') }}" class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 shadow-sm transition hover:text-[#335483]">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 19l-7-7 7-7"/></svg>
        عودة للإعدادات
    </a>
</section>

<form method="POST" action="{{ route('portal.notifications.settings.update') }}" class="max-w-2xl space-y-4" id="notif-settings-form">
    @csrf
    @method('PATCH')

    @if ($errors->any())
    <div class="rounded-2xl {{ config('brand.classes.alert_danger') }}">
        تعذّر حفظ بعض الإعدادات. يُرجى مراجعة الحقول والمحاولة مرة أخرى.
    </div>
    @endif

    <div class="rounded-3xl border border-slate-200/70 bg-white px-5 py-4 shadow-sm">
        <p class="text-sm font-semibold text-gray-900">داخل المنصة</p>
        <p class="mt-1 text-xs text-gray-500">فعّل الفئات التي تريد رؤيتها في صندوق التنبيهات.</p>

        <div class="mt-4 space-y-3">
            @foreach (NotificationPreferenceCategory::forBeneficiarySettings() as $category)
            @php
                $saved = $prefs[$category->value] ?? $category->defaultPreferences();
                $inAppChecked = old('categories.'.$category->value.'.in_app', $saved['in_app']);
                $emailChecked = old('categories.'.$category->value.'.email', $saved['email']);
            @endphp
            <div class="rounded-2xl border border-slate-100 bg-slate-50/60 px-4 py-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 text-right">
                        <p class="text-sm font-medium text-gray-900">{{ $category->label() }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">{{ $category->description() }}</p>
                        @if (! $category->canDisableInApp())
                        <p class="mt-1 text-[11px] font-medium text-[#335483]">مفعّل دائماً — تنبيهات مهمة لحسابك</p>
                        @endif
                    </div>
                    @if ($category->canDisableInApp())
                    <label class="flex shrink-0 cursor-pointer items-center gap-2">
                        <span class="text-xs text-gray-500">داخل المنصة</span>
                        <input type="checkbox" name="categories[{{ $category->value }}][in_app]" value="1" @checked($inAppChecked) class="notif-cat-in-app h-5 w-5 rounded border-slate-300 text-[#335483] focus:ring-[#335483]/30" data-category="{{ $category->value }}" />
                    </label>
                    @else
                    <input type="hidden" name="categories[{{ $category->value }}][in_app]" value="1" />
                    @endif
                </div>

                @if ($category->supportsEmail())
                <label class="mt-3 flex cursor-pointer items-center justify-between gap-3 border-t border-slate-200/80 pt-3 notif-email-row" data-category="{{ $category->value }}">
                    <span class="text-xs text-gray-600">نسخة بريدية لهذه الفئة</span>
                    <input type="checkbox" name="categories[{{ $category->value }}][email]" value="1" @checked($emailChecked) class="notif-cat-email h-4 w-4 rounded border-slate-300 text-[#335483] focus:ring-[#335483]/30" />
                </label>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-3xl border border-slate-200/70 bg-white px-5 py-4 shadow-sm">
        <label for="notify_email" class="flex cursor-pointer items-start justify-between gap-4">
            <div class="text-right">
                <p class="text-sm font-semibold text-gray-900">تفعيل البريد الإلكتروني</p>
                <p class="mt-1 text-xs text-gray-500">يُفعّل نسخة بريدية للتنبيهات المهمة (تسجيلاتك، البرامج والمسارات الجديدة عند نشرها، الأخبار، التطوع). لن يُرسل بريد لمن يُبقي الخيار معطّلاً.</p>
                <p class="mt-1 text-xs text-gray-400">{{ $user->email }}</p>
            </div>
            <input type="checkbox" id="notify_email" name="notify_email" value="1" @checked(old('notify_email', $user->notify_email)) class="mt-1 h-5 w-5 shrink-0 rounded border-slate-300 text-[#335483] focus:ring-[#335483]/30" />
        </label>
    </div>

    <div class="flex justify-end">
        <button type="submit" class="rounded-2xl px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#335483">
            حفظ التفضيلات
        </button>
    </div>
</form>

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
            var email = row.querySelector('.notif-cat-email');
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
@endsection
