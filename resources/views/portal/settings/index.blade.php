@extends('layouts.portal')
@section('title', 'الإعدادات')

@section('content')
<x-portal.settings-shell title="الإعدادات" subtitle="إدارة الحساب والتنبيهات والخصوصية." back-route="portal.dashboard" back-label="البوابة">
    <x-portal.settings-card>
        <p class="border-b border-slate-100 px-4 py-2.5 text-[11px] font-semibold text-slate-400 sm:px-5">الحساب</p>
        <x-portal.settings-row href="{{ route('portal.settings.profile') }}" label="تعديل بياناتي" hint="الصورة والاسم والهوية والمسمى">
            <x-slot:icon>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </x-slot:icon>
        </x-portal.settings-row>
        <x-portal.settings-row href="{{ route('portal.settings.account') }}" label="بيانات الدخول" hint="البريد وكلمة المرور">
            <x-slot:icon>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </x-slot:icon>
        </x-portal.settings-row>

        <p class="border-y border-slate-100 px-4 py-2.5 text-[11px] font-semibold text-slate-400 sm:px-5">التنبيهات</p>
        <x-portal.settings-row href="{{ route('portal.notifications.settings') }}" label="إعدادات التنبيهات" hint="داخل المنصة والبريد">
            <x-slot:icon>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            </x-slot:icon>
        </x-portal.settings-row>

        <p class="border-y border-slate-100 px-4 py-2.5 text-[11px] font-semibold text-slate-400 sm:px-5">القانونية</p>
        <x-portal.settings-row href="{{ route('portal.settings.legal') }}" label="سياسة الخصوصية" hint="الإصدار المعتمد">
            <x-slot:icon>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </x-slot:icon>
        </x-portal.settings-row>
    </x-portal.settings-card>

    <x-portal.settings-card danger class="mt-4">
        <p class="border-b border-red-100 px-4 py-2.5 text-[11px] font-semibold text-red-500 sm:px-5">منطقة الخطر</p>
        <x-portal.settings-row button id="open-delete-account-modal" label="حذف الحساب" hint="طلب تعمية البيانات" :danger="true">
            <x-slot:icon>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </x-slot:icon>
        </x-portal.settings-row>
    </x-portal.settings-card>
</x-portal.settings-shell>

<dialog id="delete-account-modal" class="w-[min(100%,24rem)] rounded-2xl border border-red-100 bg-white p-0 text-right shadow-xl backdrop:bg-black/40">
    <form method="POST" action="{{ route('portal.account-deletion.store') }}" class="p-5 sm:p-6" onsubmit="return confirm('هل أنت متأكد من طلب حذف حسابك؟');">
        @csrf
        <h2 class="text-base font-bold text-red-900">حذف الحساب</h2>
        <p class="mt-1.5 text-sm text-red-800/90">يُراجع الطلب ثم تُنفَّذ التعمية.</p>

        <div class="mt-5 space-y-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-red-900">كلمة المرور</label>
                <input type="password" name="password" required class="w-full rounded-xl border border-red-200 px-3.5 py-2.5 text-sm @error('password') border-brand-danger @enderror" />
                @error('password') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-red-900">سبب (اختياري)</label>
                <textarea name="reason" rows="2" maxlength="500" class="w-full rounded-xl border border-red-200 px-3.5 py-2.5 text-sm"></textarea>
            </div>
        </div>

        <div class="mt-5 flex justify-end gap-2">
            <button type="button" id="close-delete-account-modal" class="rounded-xl px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50">إلغاء</button>
            <button type="submit" class="rounded-xl bg-red-700 px-4 py-2 text-sm font-semibold text-white transition hover:opacity-95">تقديم الطلب</button>
        </div>
    </form>
</dialog>

@push('scripts')
<script>
(function () {
    var modal = document.getElementById('delete-account-modal');
    var openBtn = document.getElementById('open-delete-account-modal');
    var closeBtn = document.getElementById('close-delete-account-modal');
    if (!modal || !openBtn) return;

    openBtn.addEventListener('click', function () {
        if (typeof modal.showModal === 'function') modal.showModal();
    });

    if (closeBtn) closeBtn.addEventListener('click', function () { modal.close(); });
    modal.addEventListener('click', function (event) { if (event.target === modal) modal.close(); });

    @if ($errors->has('password'))
    if (typeof modal.showModal === 'function') modal.showModal();
    @endif
})();
</script>
@endpush
@endsection
