@extends('layouts.gate')

@section('title', 'مسح QR — '.$program->title)
@section('container_width', 'max-w-xl')

@section('content')
<div class="space-y-4">
    <div class="bg-white/95 rounded-3xl shadow-xl border border-white/80 p-6 sm:p-7">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-lg font-bold text-gray-900 leading-snug">{{ $program->title }}</h1>
                <p class="mt-1 text-sm text-gray-600">
                    المتحضّرة:
                    <span class="font-semibold text-[#335483]">{{ $operatorName }}</span>
                    @if ($operatorType === 'admin')
                        <span class="text-xs text-gray-400">(أدمن)</span>
                    @endif
                </p>
            </div>
            <form method="POST" action="{{ route('gate.logout', ['program' => $program->slug]) }}">
                @csrf
                <button type="submit" class="text-xs font-medium text-gray-500 hover:text-gray-800 underline-offset-2 hover:underline">
                    خروج
                </button>
            </form>
        </div>

        <div id="gate-feedback" class="mt-5 hidden rounded-2xl border px-4 py-4 text-center" role="status" aria-live="polite">
            <p id="gate-feedback-name" class="text-base font-bold"></p>
            <p id="gate-feedback-message" class="mt-1 text-sm"></p>
        </div>

        @if (session('gate_success'))
            <div class="mt-5 rounded-2xl border px-4 py-4 text-center {{ config('brand.classes.alert_success') }}">
                @if (session('gate_beneficiary'))
                    <p class="text-base font-bold">{{ session('gate_beneficiary') }}</p>
                @endif
                <p class="mt-1 text-sm">{{ session('gate_success') }}</p>
            </div>
        @endif

        @if (session('gate_error'))
            <div class="mt-5 rounded-2xl border px-4 py-4 text-center {{ config('brand.classes.alert_danger') }}">
                <p class="text-sm">{{ session('gate_error') }}</p>
            </div>
        @endif

        <div class="mt-5">
            <div id="reader" class="overflow-hidden rounded-2xl border border-[#d7e2ef] bg-[#0f172a]" style="min-height: 240px;"></div>
            <p id="camera-hint" class="mt-2 text-center text-xs text-gray-500">وجّهي الكاميرا نحو رمز QR الخاص بالمشاركة.</p>
            <p id="camera-error" class="mt-2 hidden text-center text-xs text-red-600"></p>
        </div>

        <form id="manual-pass-form" method="POST" action="{{ route('gate.scan.store', ['program' => $program->slug]) }}" class="mt-6 space-y-3">
            @csrf
            <label for="pass" class="block text-sm font-medium text-gray-700">أو أدخلي الرمز يدوياً</label>
            <input
                id="pass"
                type="text"
                name="pass"
                value="{{ old('pass') }}"
                placeholder="KAFAAT-P…-R…"
                autocomplete="off"
                class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-mono tracking-wide focus:outline-none focus:ring-2 focus:ring-brand/25"
            />
            <button type="submit" id="manual-submit" class="w-full py-3 rounded-xl bg-brand text-white font-semibold text-sm hover:opacity-95 transition">
                تسجيل الحضور
            </button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const scanUrl = @json(route('gate.scan.store', ['program' => $program->slug]));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const feedback = document.getElementById('gate-feedback');
    const feedbackName = document.getElementById('gate-feedback-name');
    const feedbackMessage = document.getElementById('gate-feedback-message');
    const cameraError = document.getElementById('camera-error');
    const form = document.getElementById('manual-pass-form');
    const passInput = document.getElementById('pass');
    let busy = false;
    let lastCode = '';
    let lastAt = 0;

    function vibrate(ok) {
        if (!navigator.vibrate) return;
        navigator.vibrate(ok ? [40, 30, 40] : [120, 60, 120]);
    }

    function showFeedback(ok, name, message) {
        feedback.classList.remove('hidden', 'border-red-200', 'bg-red-50', 'text-red-800', 'border-emerald-200', 'bg-emerald-50', 'text-emerald-900', 'border-amber-200', 'bg-amber-50', 'text-amber-900');
        if (ok && message && message.includes('مسبقاً')) {
            feedback.classList.add('border-amber-200', 'bg-amber-50', 'text-amber-900');
        } else if (ok) {
            feedback.classList.add('border-emerald-200', 'bg-emerald-50', 'text-emerald-900');
        } else {
            feedback.classList.add('border-red-200', 'bg-red-50', 'text-red-800');
        }
        feedbackName.textContent = name || '';
        feedbackMessage.textContent = message || '';
    }

    async function submitPass(raw, fromCamera) {
        const pass = (raw || '').trim();
        if (!pass || busy) return;

        const now = Date.now();
        if (fromCamera && pass === lastCode && (now - lastAt) < 2500) {
            return;
        }
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
            showFeedback(ok, data.beneficiary_name || '', data.message || (ok ? 'تم التسجيل' : 'تعذّر التسجيل'));
            vibrate(ok);
            if (ok && passInput) {
                passInput.value = '';
            }
        } catch (e) {
            showFeedback(false, '', 'تعذّر الاتصال. حاولي مرة أخرى.');
            vibrate(false);
        } finally {
            busy = false;
        }
    }

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        submitPass(passInput?.value || '', false);
    });

    function startScanner() {
        if (!window.Html5Qrcode) {
            cameraError.classList.remove('hidden');
            cameraError.textContent = 'تعذّر تحميل ماسح الكاميرا. استخدمي الإدخال اليدوي.';
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
                (decoded) => submitPass(decoded, true),
                () => {}
            );
        }).catch(() => {
            cameraError.classList.remove('hidden');
            cameraError.textContent = 'اختي إذن الكاميرا أو استخدمي الإدخال اليدوي.';
        });
    }

    const script = document.createElement('script');
    script.src = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
    script.onload = startScanner;
    script.onerror = () => {
        cameraError.classList.remove('hidden');
        cameraError.textContent = 'تعذّر تحميل ماسح الكاميرا. استخدمي الإدخال اليدوي.';
    };
    document.head.appendChild(script);
})();
</script>
@endpush
