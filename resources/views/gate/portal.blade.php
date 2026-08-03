@extends('layouts.gate')

@section('title', 'بوابة التحضير — '.$program->title)
@section('container_width', 'max-w-2xl')

@push('head')
<meta name="robots" content="noindex, nofollow" />
<meta name="referrer" content="no-referrer" />
@endpush

@section('content')
@php
    use App\Enums\AttendanceStatus;
@endphp
<div class="space-y-4">
    <div class="bg-white/95 rounded-3xl shadow-xl border border-white/80 p-5 sm:p-7">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-lg font-bold text-gray-900 leading-snug">{{ $program->title }}</h1>
                <p class="mt-1 text-sm text-gray-600">
                    مسؤول التحضير:
                    <span class="font-semibold text-[#335483]">{{ $operatorName }}</span>
                    @if ($operatorType === 'admin')
                        <span class="text-xs text-gray-400">(إدارة)</span>
                    @endif
                </p>
            </div>
            <form method="POST" action="{{ route('gate.logout', ['program' => $program->slug]) }}">
                @csrf
                <button type="submit" class="text-xs font-medium text-gray-500 hover:text-gray-800 underline-offset-2 hover:underline whitespace-nowrap">
                    خروج
                </button>
            </form>
        </div>

        <div class="mt-4 rounded-2xl border border-[#d7e2ef] bg-[#f5f8fc] px-4 py-3">
            <p class="text-xs text-gray-500">اليوم حسب توقيت الرياض</p>
            <p class="mt-0.5 text-sm font-bold text-[#335483]">{{ $prepDateLabel }}</p>
            <p class="mt-0.5 text-xs font-mono text-gray-500">{{ $prepDate }}</p>
            @if ($dayTypeLabel)
                <p class="mt-2 text-xs font-medium {{ $isInPersonToday ? 'text-emerald-700' : 'text-sky-700' }}">
                    نوع اليوم: {{ $dayTypeLabel }}
                </p>
            @else
                <p class="mt-2 text-xs font-medium text-amber-700">
                    اليوم ليس من أيام البرنامج، ولا يتوفر تحضير اليوم.
                </p>
            @endif
        </div>

        <nav class="mt-5 flex gap-2 border-b border-gray-200 pb-px" aria-label="وسائل التحضير">
            @if ($isInPersonToday)
                <a
                    href="{{ route('gate.portal', ['program' => $program->slug, 'tab' => 'qr']) }}"
                    class="px-3 py-2 text-sm font-semibold rounded-t-lg {{ $tab === 'qr' ? 'text-[#335483] border-b-2 border-[#335483]' : 'text-gray-500 hover:text-gray-800' }}"
                >
                    مسح QR
                </a>
            @endif
            <a
                href="{{ route('gate.portal', ['program' => $program->slug, 'tab' => 'manual', 'q' => $search ?: null]) }}"
                class="px-3 py-2 text-sm font-semibold rounded-t-lg {{ $tab === 'manual' ? 'text-[#335483] border-b-2 border-[#335483]' : 'text-gray-500 hover:text-gray-800' }}"
            >
                التحضير اليدوي
            </a>
        </nav>

        <div id="gate-feedback" class="mt-4 hidden rounded-2xl border px-4 py-3 text-center" role="status" aria-live="polite">
            <p id="gate-feedback-name" class="text-sm font-bold"></p>
            <p id="gate-feedback-message" class="mt-0.5 text-sm"></p>
        </div>

        @if (session('gate_success'))
            <div class="mt-4 rounded-2xl border px-4 py-3 text-center {{ config('brand.classes.alert_success') }}">
                @if (session('gate_beneficiary'))
                    <p class="text-sm font-bold">{{ session('gate_beneficiary') }}</p>
                @endif
                <p class="mt-0.5 text-sm">{{ session('gate_success') }}</p>
            </div>
        @endif

        @if (session('gate_error'))
            <div class="mt-4 rounded-2xl border px-4 py-3 text-center {{ config('brand.classes.alert_danger') }}">
                <p class="text-sm">{{ session('gate_error') }}</p>
            </div>
        @endif

        @if ($tab === 'qr' && $isInPersonToday)
            <div class="mt-5">
                <div id="reader" class="overflow-hidden rounded-2xl border border-[#d7e2ef] bg-[#0f172a]" style="min-height: 240px;"></div>
                <p id="camera-hint" class="mt-2 text-center text-xs text-gray-500">وجّه الكاميرا نحو رمز QR الخاص بالمشاركة.</p>
                <p id="camera-error" class="mt-2 hidden text-center text-xs text-red-600"></p>
            </div>
        @elseif ($tab === 'manual')
            @if (! $isPrepDayToday)
                <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900">
                    اليوم ليس من أيام البرنامج، ولا يتوفر تحضير اليوم.
                </div>
            @else
                <form method="GET" action="{{ route('gate.portal', ['program' => $program->slug]) }}" class="mt-5">
                    <input type="hidden" name="tab" value="manual" />
                    <label for="q" class="sr-only">بحث بالاسم</label>
                    <div class="flex gap-2">
                        <input
                            id="q"
                            type="search"
                            name="q"
                            value="{{ $search }}"
                            placeholder="بحث بالاسم…"
                            autocomplete="off"
                            class="flex-1 rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/25"
                        />
                        <button type="submit" class="rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:opacity-95">
                            بحث
                        </button>
                    </div>
                </form>

                <ul class="mt-4 divide-y divide-gray-100" id="manual-list">
                    @forelse ($registrations as $registration)
                        @php
                            $user = $registration->user;
                            $fullName = $user?->fullName() ?: ($user?->name ?? '—');
                            $isPresent = $registration->attendanceRecords
                                ->contains(fn ($row) => $row->status === AttendanceStatus::Present);
                        @endphp
                        <li
                            class="flex flex-col gap-3 py-3 sm:flex-row sm:items-center sm:justify-between"
                            data-registration-id="{{ $registration->id }}"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $fullName }}</p>
                                <p class="mt-0.5 text-xs attendance-label {{ $isPresent ? 'text-emerald-700' : 'text-gray-500' }}">
                                    {{ $isPresent ? 'حاضر' : 'لم يحضر' }}
                                </p>
                            </div>
                            <div class="flex shrink-0 gap-2" role="group" aria-label="حالة التحضير لـ {{ $fullName }}">
                                <button
                                    type="button"
                                    class="attendance-toggle flex-1 sm:flex-none rounded-xl px-4 py-2.5 text-sm font-semibold transition border {{ $isPresent ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}"
                                    data-present="1"
                                    @disabled(! $isPrepDayToday)
                                >
                                    حاضر
                                </button>
                                <button
                                    type="button"
                                    class="attendance-toggle flex-1 sm:flex-none rounded-xl px-4 py-2.5 text-sm font-semibold transition border {{ ! $isPresent ? 'bg-gray-700 text-white border-gray-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}"
                                    data-present="0"
                                    @disabled(! $isPrepDayToday)
                                >
                                    لم يحضر
                                </button>
                            </div>
                        </li>
                    @empty
                        <li class="py-8 text-center text-sm text-gray-500">
                            @if ($search !== '')
                                لا توجد نتائج مطابقة للبحث.
                            @else
                                لا يوجد مسجلون مقبولون لهذا البرنامج.
                            @endif
                        </li>
                    @endforelse
                </ul>

                @if ($registrations && $registrations->hasPages())
                    <div class="mt-4">
                        {{ $registrations->links() }}
                    </div>
                @endif
            @endif
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const feedback = document.getElementById('gate-feedback');
    const feedbackName = document.getElementById('gate-feedback-name');
    const feedbackMessage = document.getElementById('gate-feedback-message');
    const programSlug = @json($program->slug);
    let busy = false;

    function showFeedback(ok, name, message, already) {
        if (!feedback) return;
        feedback.classList.remove('hidden', 'border-red-200', 'bg-red-50', 'text-red-800', 'border-emerald-200', 'bg-emerald-50', 'text-emerald-900', 'border-amber-200', 'bg-amber-50', 'text-amber-900');
        if (ok && already) {
            feedback.classList.add('border-amber-200', 'bg-amber-50', 'text-amber-900');
        } else if (ok) {
            feedback.classList.add('border-emerald-200', 'bg-emerald-50', 'text-emerald-900');
        } else {
            feedback.classList.add('border-red-200', 'bg-red-50', 'text-red-800');
        }
        feedbackName.textContent = name || '';
        feedbackMessage.textContent = message || '';
    }

    @if ($tab === 'qr' && $isInPersonToday)
    const scanUrl = @json(route('gate.scan.store', ['program' => $program->slug]));
    const cameraError = document.getElementById('camera-error');
    let lastCode = '';
    let lastAt = 0;

    function vibrate(ok) {
        if (!navigator.vibrate) return;
        navigator.vibrate(ok ? [40, 30, 40] : [120, 60, 120]);
    }

    async function submitPass(raw) {
        const pass = (raw || '').trim();
        if (!pass || busy) return;
        const now = Date.now();
        if (pass === lastCode && (now - lastAt) < 2500) return;
        lastCode = pass;
        lastAt = now;
        busy = true;
        try {
            const response = await fetch(scanUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ pass }),
            });
            const data = await response.json().catch(() => ({}));
            const ok = Boolean(data.ok);
            showFeedback(ok, data.beneficiary_name || '', data.message || (ok ? 'تم التسجيل' : 'تعذّر التسجيل'), data.reason === 'already_present');
            vibrate(ok);
        } catch (e) {
            showFeedback(false, '', 'تعذّر الاتصال. حاول مرة أخرى.', false);
            vibrate(false);
        } finally {
            busy = false;
        }
    }

    function startScanner() {
        if (!window.Html5Qrcode) {
            cameraError.classList.remove('hidden');
            cameraError.textContent = 'تعذّر تحميل ماسح الكاميرا.';
            return;
        }
        const scanner = new Html5Qrcode('reader');
        Html5Qrcode.getCameras().then((cameras) => {
            if (!cameras || cameras.length === 0) {
                cameraError.classList.remove('hidden');
                cameraError.textContent = 'لا توجد كاميرا متاحة على هذا الجهاز.';
                return;
            }
            const rear = cameras.find((c) => /back|rear|بيئة|خلف/i.test(c.label)) || cameras[cameras.length - 1];
            return scanner.start(
                rear.id,
                { fps: 8, qrbox: { width: 240, height: 240 }, aspectRatio: 1.0 },
                (decoded) => submitPass(decoded),
                () => {}
            );
        }).catch(() => {
            cameraError.classList.remove('hidden');
            cameraError.textContent = 'اختَر إذن الكاميرا للمتابعة.';
        });
    }

    const script = document.createElement('script');
    script.src = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
    script.onload = startScanner;
    script.onerror = () => {
        cameraError.classList.remove('hidden');
        cameraError.textContent = 'تعذّر تحميل ماسح الكاميرا.';
    };
    document.head.appendChild(script);
    @endif

    @if ($tab === 'manual' && $isPrepDayToday)
    document.getElementById('manual-list')?.addEventListener('click', async (event) => {
        const btn = event.target.closest('.attendance-toggle');
        if (!btn || busy) return;
        const row = btn.closest('[data-registration-id]');
        if (!row) return;
        const registrationId = row.getAttribute('data-registration-id');
        const present = btn.getAttribute('data-present') === '1';
        busy = true;
        btn.disabled = true;
        try {
            const url = `/gate/${programSlug}/registrations/${registrationId}/attendance`;
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ present }),
            });
            const data = await response.json().catch(() => ({}));
            if (!data.ok) {
                showFeedback(false, data.beneficiary_name || '', data.message || 'تعذّر التحديث.', false);
                return;
            }
            showFeedback(true, data.beneficiary_name || '', data.message || 'تم التحديث.', false);
            const label = row.querySelector('.attendance-label');
            if (label) {
                label.textContent = present ? 'حاضر' : 'لم يحضر';
                label.className = 'mt-0.5 text-xs attendance-label ' + (present ? 'text-emerald-700' : 'text-gray-500');
            }
            row.querySelectorAll('.attendance-toggle').forEach((el) => {
                const isPresentBtn = el.getAttribute('data-present') === '1';
                const active = (present && isPresentBtn) || (!present && !isPresentBtn);
                if (isPresentBtn) {
                    el.className = 'attendance-toggle flex-1 sm:flex-none rounded-xl px-4 py-2.5 text-sm font-semibold transition border ' +
                        (active ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50');
                } else {
                    el.className = 'attendance-toggle flex-1 sm:flex-none rounded-xl px-4 py-2.5 text-sm font-semibold transition border ' +
                        (active ? 'bg-gray-700 text-white border-gray-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50');
                }
            });
        } catch (e) {
            showFeedback(false, '', 'تعذّر الاتصال. حاول مرة أخرى.', false);
        } finally {
            busy = false;
            btn.disabled = false;
        }
    });
    @endif
})();
</script>
@endpush
