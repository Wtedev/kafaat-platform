@extends('layouts.portal')
@section('title', 'إعدادات التنبيهات')

@section('content')
<section class="mb-6 flex flex-wrap items-start justify-between gap-3 text-right">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">إعدادات التنبيهات</h1>
        <p class="mt-2 max-w-2xl text-sm text-gray-600">تحكّم في طريقة وصول التنبيهات إليك. تظهر التنبيهات دائماً داخل الموقع، ويمكنك تفعيل أو إيقاف نسخة البريد الإلكتروني.</p>
    </div>
    <a href="{{ route('portal.notifications') }}" class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 shadow-sm transition hover:text-[#253B5B]">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 19l-7-7 7-7"/></svg>
        عودة للتنبيهات
    </a>
</section>

<form method="POST" action="{{ route('portal.notifications.settings.update') }}" class="max-w-2xl space-y-4">
    @csrf
    @method('PATCH')

    <div class="flex items-start justify-between gap-4 rounded-3xl border border-slate-200/70 bg-white px-5 py-4 shadow-sm">
        <div class="text-right">
            <p class="text-sm font-semibold text-gray-900">التنبيهات داخل الموقع</p>
            <p class="mt-1 text-xs text-gray-500">تظهر في صندوق التنبيهات داخل بوابتك. مفعّلة دائماً ولا يمكن إيقافها.</p>
        </div>
        <input type="checkbox" checked disabled class="mt-1 h-5 w-5 shrink-0 rounded border-slate-300 text-[#253B5B] opacity-60" aria-label="التنبيهات داخل الموقع (مفعّلة دائماً)" />
    </div>

    <label for="notify_email" class="flex cursor-pointer items-start justify-between gap-4 rounded-3xl border border-slate-200/70 bg-white px-5 py-4 shadow-sm transition hover:border-[#253B5B]/30">
        <div class="text-right">
            <p class="text-sm font-semibold text-gray-900">إشعارات البريد الإلكتروني</p>
            <p class="mt-1 text-xs text-gray-500">استقبل نسخة من كل تنبيه على بريدك: <span class="font-medium text-gray-700">{{ $user->email }}</span></p>
        </div>
        <input type="checkbox" id="notify_email" name="notify_email" value="1" @checked(old('notify_email', $user->notify_email)) class="mt-1 h-5 w-5 shrink-0 rounded border-slate-300 text-[#253B5B] focus:ring-[#253B5B]/30" />
    </label>

    <div class="flex justify-end">
        <button type="submit" class="rounded-2xl px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#253B5B">
            حفظ التفضيلات
        </button>
    </div>
</form>
@endsection
