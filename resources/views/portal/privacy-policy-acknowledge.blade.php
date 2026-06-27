@extends('layouts.portal')
@section('title', 'الاطلاع على سياسة الخصوصية')

@section('content')
<div class="mx-auto max-w-3xl">
    <h1 class="mb-2 text-2xl font-bold text-gray-900">تحديث سياسة الخصوصية</h1>
    <p class="mb-6 text-sm text-gray-600">يُرجى الاطلاع على النسخة المحدّثة من سياسة الخصوصية ثم تأكيد الإقرار للمتابعة.</p>

    <div class="mb-6 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
        <p class="mb-2 text-xs text-gray-500">الإصدار {{ $policy->version }} · سريان {{ $policy->effective_at?->translatedFormat('j F Y') }}</p>
        <div class="prose prose-sm max-w-none text-right leading-relaxed text-gray-700 privacy-policy-content">
            {!! $sanitizedContent !!}
        </div>
        <p class="mt-4 text-sm">
            <a href="{{ route('public.privacy') }}" target="_blank" rel="noopener noreferrer" class="text-brand font-medium hover:underline">فتح السياسة في صفحة مستقلة</a>
        </p>
    </div>

    <form method="POST" action="{{ route('portal.privacy-policy.acknowledge.store') }}" class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
        @csrf
        <input type="hidden" name="privacy_policy_version" value="{{ $policy->version }}" />

        @if ($errors->any())
        <div class="mb-4 rounded-xl {{ config('brand.classes.alert_danger') }} px-4 py-3 text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" name="privacy_policy_acknowledged" value="1" required
                class="mt-1 rounded border-gray-300 text-brand focus:ring-brand/25" />
            <span class="text-sm text-gray-700">{{ $acknowledgementText }}
                <a href="{{ route('public.privacy') }}" target="_blank" rel="noopener noreferrer" class="text-brand font-medium hover:underline">(عرض السياسة)</a>
            </span>
        </label>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <button type="submit" class="rounded-xl bg-brand px-6 py-2.5 text-sm font-semibold text-white hover:opacity-95">
                تأكيد الإقرار والمتابعة
            </button>
        </div>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4">
        @csrf
        <button type="submit" class="rounded-xl border border-gray-200 px-6 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50">
            تسجيل الخروج
        </button>
    </form>
</div>
@endsection
