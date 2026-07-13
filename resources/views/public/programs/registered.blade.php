@extends('layouts.public')

@section('title', 'تم التسجيل — '.$trainingProgram->title)

@section('content')
@php
    /** @var array $success */
    $approved = (bool) ($success['approved'] ?? false);
    $pending = (bool) ($success['pending'] ?? false);
    $showQr = (bool) ($success['show_qr'] ?? false);
    $whatsappUrl = $success['whatsapp_url'] ?? null;
    $venue = $success['venue_label'] ?? null;
@endphp

<div class="mx-auto max-w-2xl">
    <div class="reg-success overflow-hidden rounded-[1.75rem] border border-[#c5d4e4]/60 bg-white shadow-[0_20px_60px_-28px_rgba(51,84,131,0.35)]">
        <div class="reg-success__hero relative px-6 pb-8 pt-10 text-center sm:px-10">
            <div class="reg-success__glow" aria-hidden="true"></div>

            <div class="reg-success__icon mx-auto mb-5 grid h-16 w-16 place-items-center rounded-full" style="background:linear-gradient(145deg,#3CB878,#2a9a5f)">
                <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.25" d="M5 13l4 4L19 7"/>
                </svg>
            </div>

            <p class="text-xs font-bold tracking-wide text-[#3CB878]">كفاءات</p>
            <h1 class="mt-2 text-2xl font-bold text-gray-900 sm:text-3xl">تم تسجيلك بنجاح</h1>
            <p class="mt-2 text-base font-semibold text-[#335483]">{{ $trainingProgram->title }}</p>

            @if ($approved)
                <p class="mx-auto mt-3 max-w-md text-sm leading-relaxed text-gray-500">
                    تم قبول طلبك مباشرة. يمكنك المتابعة بالخطوات أدناه.
                </p>
            @elseif ($pending)
                <p class="mx-auto mt-3 max-w-md text-sm leading-relaxed text-gray-500">
                    طلبك قيد المراجعة. سنُبلغك بنتيجة القبول عبر المنصة والبريد الإلكتروني.
                </p>
            @endif
        </div>

        <div class="space-y-5 border-t border-gray-100 px-6 py-7 sm:px-10">
            @if ($pending)
                <div class="rounded-2xl border border-amber-200/80 bg-amber-50/80 px-5 py-4 text-right">
                    <p class="text-sm font-semibold text-amber-900">الخطوة التالية</p>
                    <p class="mt-1.5 text-sm leading-relaxed text-amber-800/90">
                        تأكد من تفعيل رسائل البريد الإلكتروني والتنبيهات لمعرفة حالة قبولك فور صدور القرار.
                    </p>
                    <a href="{{ $success['notifications_settings_url'] }}"
                       class="mt-4 inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-[#335483] shadow-sm ring-1 ring-[#c5d4e4]/70 transition hover:bg-[#e9eff6]">
                        إعدادات التنبيهات
                        <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </a>
                </div>
            @endif

            @if ($approved && $whatsappUrl)
                <div class="rounded-2xl border border-[#25D366]/30 bg-[#25D366]/5 px-5 py-4 text-right">
                    <p class="text-sm font-semibold text-gray-900">مجموعة واتساب البرنامج</p>
                    <p class="mt-1 text-sm text-gray-500">انضم للمجموعة المناسبة لمتابعة التحديثات والتواصل.</p>
                    <a href="{{ $whatsappUrl }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-2xl px-5 py-3.5 text-sm font-semibold text-white shadow-md transition hover:-translate-y-0.5 hover:shadow-lg sm:w-auto"
                       style="background:#25D366">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        دخول مجموعة الواتساب
                    </a>
                </div>
            @endif

            @if ($showQr && ! empty($success['qr_data_uri']))
                <div class="rounded-2xl border border-[#c5d4e4]/70 bg-[#F7FAFC] px-5 py-6 text-center">
                    <p class="text-sm font-semibold text-gray-900">بطاقة الحضور</p>
                    <p class="mx-auto mt-1 max-w-sm text-sm text-gray-500">
                        البرنامج حضوري
                        @if ($venue)
                            — {{ $venue }}
                        @endif
                        . أظهر رمز QR عند الوصول.
                    </p>
                    <div class="mx-auto mt-5 inline-block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-[#c5d4e4]/50">
                        <img
                            src="{{ $success['qr_data_uri'] }}"
                            alt="رمز QR للحضور"
                            class="mx-auto h-[180px] w-[180px] sm:h-[220px] sm:w-[220px]"
                            width="220"
                            height="220"
                        >
                    </div>
                    <p class="mt-3 font-mono text-xs tracking-wide text-gray-400" dir="ltr">{{ $success['pass_code'] }}</p>
                </div>
            @endif

            <div class="flex flex-col gap-2 sm:flex-row sm:justify-center">
                <a href="{{ route('portal.dashboard') }}"
                   class="inline-flex items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5"
                   style="background:#335483">
                    الذهاب لمنصة التدريب
                </a>
                <a href="{{ route('public.programs.show', $trainingProgram->slug) }}"
                   class="inline-flex items-center justify-center rounded-2xl border border-[#c5d4e4] bg-white px-6 py-3 text-sm font-semibold text-[#335483] transition hover:bg-[#e9eff6]">
                    العودة لصفحة البرنامج
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    .reg-success__hero {
        background:
            radial-gradient(ellipse 80% 60% at 50% 0%, rgba(51, 84, 131, 0.10), transparent 70%),
            linear-gradient(180deg, #ffffff 0%, #f8fbfd 100%);
    }
    .reg-success__icon {
        box-shadow: 0 12px 28px -10px rgba(60, 184, 120, 0.55);
        animation: reg-success-pop 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
    }
    @keyframes reg-success-pop {
        from { transform: scale(0.72); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }
</style>
@endsection
