@extends('layouts.portal')
@section('title', 'الفرص الوظيفية وقاعدة المرشحين')

@section('content')
<div class="mx-auto max-w-2xl">
    <h1 class="mb-2 text-2xl font-bold text-gray-900">الفرص الوظيفية وقاعدة المرشحين</h1>
    <p class="mb-6 text-sm text-gray-600">الانضمام إلى قاعدة المرشحين اختياري ولا يؤثر على البرامج أو الشهادات.</p>

    @php
        $status = $preference?->current_status;
        $statusLabel = match ($status?->value ?? 'undecided') {
            'granted' => 'أنت منضم حالياً إلى قاعدة المرشحين الداخلية.',
            'declined' => 'اخترت عدم الانضمام إلى قاعدة المرشحين.',
            'withdrawn' => 'تم سحب موافقتك، ولن يظهر ملفك ضمن قاعدة المرشحين.',
            default => 'لم تحدد اختيارك بعد.',
        };
    @endphp

    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm space-y-4">
        <p class="text-sm font-medium text-gray-800">{{ $statusLabel }}</p>
        @if ($preference?->decided_at)
        <p class="text-xs text-gray-500">آخر اختيار: {{ ar_date($preference->decided_at) }}</p>
        @endif
        @if ($activeVersion)
        <p class="text-xs text-gray-500">إصدار النص: {{ $activeVersion->version }}</p>
        @endif
        @if ($status?->value === 'granted' && ! $hasCv)
        <p class="text-sm text-amber-800 bg-amber-50 rounded-lg px-3 py-2">موافقتك فعالة، لكنك لن تظهر في قاعدة المرشحين حتى ترفع سيرة ذاتية.</p>
        @endif
        <p class="text-xs text-gray-600 leading-relaxed">{{ $consentText }}</p>
        <p class="text-sm"><a href="{{ route('public.privacy') }}" class="text-brand font-medium hover:underline">سياسة الخصوصية</a></p>

        <div class="flex flex-wrap gap-3 pt-2">
            @if ($activeVersion && in_array($status?->value, ['undecided', 'declined', 'withdrawn'], true))
            <form method="POST" action="{{ route('portal.candidate-pool.settings.grant') }}">@csrf
                <button type="submit" class="rounded-xl bg-brand px-5 py-2.5 text-sm font-semibold text-white">الانضمام</button>
            </form>
            @endif
            @if ($status?->value === 'granted')
            <form method="POST" action="{{ route('portal.candidate-pool.settings.withdraw') }}">@csrf
                <button type="submit" class="rounded-xl border border-red-200 px-5 py-2.5 text-sm font-semibold text-red-700">سحب الموافقة</button>
            </form>
            @endif
        </div>
    </div>
</div>
@endsection
