@extends('layouts.portal')
@section('title', 'محادثة دعم جديدة')

@section('content')
<div class="mx-auto max-w-2xl">
    <div class="mb-6">
        <a href="{{ route('portal.support.index') }}" class="text-sm font-medium text-[#335483] hover:underline">← العودة للدعم الفني</a>
        <h1 class="mt-2 text-2xl font-bold text-gray-900">محادثة جديدة</h1>
        <p class="mt-1 text-sm text-slate-600">صف مشكلتك باختصار. سنستخدم بيانات حسابك تلقائياً.</p>
    </div>

    <form method="POST" action="{{ route('portal.support.store') }}" class="space-y-4 rounded-2xl border border-slate-200/80 bg-white p-5" data-support-create-form>
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">

        <label class="block">
            <span class="mb-1 block text-sm font-semibold text-slate-700">الموضوع</span>
            <input type="text" name="subject" value="{{ old('subject') }}" required maxlength="200" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" placeholder="مثال: تعذّر التسجيل في برنامج" />
            @error('subject')<p class="mt-1 text-xs text-brand-danger">{{ $message }}</p>@enderror
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-semibold text-slate-700">التصنيف</span>
            <select name="category" required class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                @foreach ($categories as $value => $label)
                    <option value="{{ $value }}" @selected(old('category', 'general') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('category')<p class="mt-1 text-xs text-brand-danger">{{ $message }}</p>@enderror
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-semibold text-slate-700">الرسالة الأولى</span>
            <textarea name="body" rows="6" required maxlength="4000" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" placeholder="اكتب تفاصيل المشكلة…">{{ old('body') }}</textarea>
            @error('body')<p class="mt-1 text-xs text-brand-danger">{{ $message }}</p>@enderror
        </label>

        <button type="submit" class="w-full rounded-xl px-5 py-3 text-sm font-semibold text-white shadow-sm disabled:opacity-60" style="background:#335483" data-support-submit>
            بدء المحادثة
        </button>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.querySelector('[data-support-create-form]');
    if (!form) return;
    form.addEventListener('submit', function () {
        var btn = form.querySelector('[data-support-submit]');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'جاري الإرسال…';
        }
    });
})();
</script>
@endpush
