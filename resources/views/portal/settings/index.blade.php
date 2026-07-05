@extends('layouts.portal')
@section('title', 'الإعدادات')

@section('content')
<section class="mb-8 text-right">
    <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">الإعدادات</h1>
    <p class="mt-2 text-sm text-gray-600">إدارة حسابك وتفضيلاتك وخيارات الخصوصية.</p>
</section>

<div class="max-w-2xl space-y-8">
    <div>
        <h2 class="mb-3 px-1 text-xs font-bold uppercase tracking-wider text-slate-400">Account</h2>
        <div class="overflow-hidden rounded-3xl border border-slate-200/70 bg-white shadow-sm">
            <a href="{{ route('portal.settings.account') }}" class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-slate-50">
                <svg class="h-4 w-4 shrink-0 rotate-180 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <div class="min-w-0 flex-1 text-right">
                    <p class="text-sm font-semibold text-gray-900">بيانات الدخول</p>
                    <p class="mt-0.5 text-xs text-gray-500">البريد الإلكتروني ومعلومات تسجيل الدخول</p>
                </div>
            </a>
        </div>
    </div>

    <div>
        <h2 class="mb-3 px-1 text-xs font-bold uppercase tracking-wider text-slate-400">Notifications</h2>
        <div class="overflow-hidden rounded-3xl border border-slate-200/70 bg-white shadow-sm">
            <a href="{{ route('portal.notifications.settings') }}" class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-slate-50">
                <svg class="h-4 w-4 shrink-0 rotate-180 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <div class="min-w-0 flex-1 text-right">
                    <p class="text-sm font-semibold text-gray-900">إعدادات التنبيهات</p>
                    <p class="mt-0.5 text-xs text-gray-500">التحكم في التنبيهات داخل المنصة والبريد</p>
                </div>
            </a>
        </div>
    </div>

    <div>
        <h2 class="mb-3 px-1 text-xs font-bold uppercase tracking-wider text-slate-400">Legal</h2>
        <div class="overflow-hidden rounded-3xl border border-slate-200/70 bg-white shadow-sm">
            <a href="{{ route('portal.settings.legal') }}" class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-slate-50">
                <svg class="h-4 w-4 shrink-0 rotate-180 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <div class="min-w-0 flex-1 text-right">
                    <p class="text-sm font-semibold text-gray-900">سياسة الخصوصية</p>
                    <p class="mt-0.5 text-xs text-gray-500">الاطلاع على سياسة الخصوصية المعتمدة</p>
                </div>
            </a>
        </div>
    </div>

    <div>
        <h2 class="mb-3 px-1 text-xs font-bold uppercase tracking-wider text-red-400">Danger Zone</h2>
        <div class="overflow-hidden rounded-3xl border border-red-100 bg-red-50/40 shadow-sm">
            <button type="button" id="open-delete-account-modal" class="flex w-full items-center justify-between gap-4 px-5 py-4 text-right transition hover:bg-red-50">
                <svg class="h-4 w-4 shrink-0 rotate-180 text-red-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-red-900">حذف الحساب</p>
                    <p class="mt-0.5 text-xs text-red-700">تقديم طلب حذف وتعمية بياناتك</p>
                </div>
            </button>
        </div>
    </div>
</div>

<dialog id="delete-account-modal" class="w-[min(100%,28rem)] rounded-3xl border border-red-100 bg-white p-0 text-right shadow-xl backdrop:bg-black/40">
    <form method="POST" action="{{ route('portal.account-deletion.store') }}" class="p-6 sm:p-8" onsubmit="return confirm('هل أنت متأكد من طلب حذف حسابك؟');">
        @csrf
        <h2 class="text-lg font-bold text-red-900">حذف الحساب</h2>
        <p class="mt-2 text-sm leading-relaxed text-red-800">سيُراجع الطلب ويُنفَّذ التعمية وليس الحذف الكامل للسجل.</p>

        <div class="mt-6 space-y-4">
            <div>
                <label class="mb-1 block text-sm font-medium text-red-900">كلمة المرور</label>
                <input type="password" name="password" required class="w-full rounded-xl border border-red-200 px-4 py-2.5 text-sm @error('password') border-brand-danger @enderror" />
                @error('password') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-red-900">سبب اختياري</label>
                <textarea name="reason" rows="2" maxlength="500" class="w-full rounded-xl border border-red-200 px-4 py-2.5 text-sm"></textarea>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap justify-end gap-3">
            <button type="button" id="close-delete-account-modal" class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50">
                إلغاء
            </button>
            <button type="submit" class="rounded-xl bg-red-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:opacity-95">
                تقديم طلب الحذف
            </button>
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

    if (closeBtn) {
        closeBtn.addEventListener('click', function () { modal.close(); });
    }

    modal.addEventListener('click', function (event) {
        if (event.target === modal) modal.close();
    });

    @if ($errors->has('password'))
    if (typeof modal.showModal === 'function') modal.showModal();
    @endif
})();
</script>
@endpush
@endsection
