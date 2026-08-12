@extends('layouts.gate')

@section('title', 'بوابة التحضير — '.$program->title)
@section('container_width', 'max-w-6xl')

@push('head')
<meta name="robots" content="noindex, nofollow" />
<meta name="referrer" content="no-referrer" />
@endpush

@section('content')
<div class="space-y-4">
    <div class="bg-white/95 rounded-3xl shadow-xl border border-white/80 p-4 sm:p-6">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-base font-bold text-gray-900 leading-snug sm:text-[1.05rem]">{{ $program->title }}</h1>
                <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                    <span class="font-medium text-[#335483]">{{ $operatorName }}</span>
                    @if ($operatorType === 'admin')
                        <span class="text-gray-400">إدارة</span>
                    @endif
                    <span class="text-gray-300" aria-hidden="true">·</span>
                    <span
                        class="{{ $dayTypeLabel ? ($isInPersonToday ? 'text-emerald-700' : 'text-sky-700') : 'text-amber-700' }}"
                        title="{{ $prepDate }} — توقيت الرياض"
                    >
                        @if ($dayTypeLabel)
                            {{ $prepDateLabel }} · {{ $dayTypeLabel }}
                        @else
                            {{ $prepDateLabel }} · لا يتوفر تحضير اليوم
                        @endif
                    </span>
                </p>
            </div>
            <form method="POST" action="{{ route('gate.logout', ['program' => $program->slug]) }}">
                @csrf
                <button type="submit" class="text-xs font-medium text-gray-500 hover:text-gray-800 underline-offset-2 hover:underline whitespace-nowrap">
                    خروج
                </button>
            </form>
        </div>

        @if ($isInPersonToday)
            <nav class="mt-4 flex gap-2 border-b border-gray-200 pb-px" aria-label="وسائل التحضير">
                <a
                    href="{{ route('gate.portal', ['program' => $program->slug, 'tab' => 'qr']) }}"
                    class="px-3 py-2 text-sm font-semibold rounded-t-lg {{ $tab === 'qr' ? 'text-[#335483] border-b-2 border-[#335483]' : 'text-gray-500 hover:text-gray-800' }}"
                >
                    مسح QR
                </a>
                <a
                    href="{{ route('gate.portal', ['program' => $program->slug, 'tab' => 'manual', 'q' => $search ?: null]) }}"
                    class="px-3 py-2 text-sm font-semibold rounded-t-lg {{ $tab === 'manual' ? 'text-[#335483] border-b-2 border-[#335483]' : 'text-gray-500 hover:text-gray-800' }}"
                >
                    التحضير اليدوي
                </a>
            </nav>
        @endif

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
                <div class="{{ $isInPersonToday ? 'mt-5' : 'mt-4' }}">
                    <label for="q" class="sr-only">بحث بالاسم</label>
                    <input
                        id="q"
                        type="search"
                        name="q"
                        value="{{ $search }}"
                        placeholder="بحث بالاسم…"
                        autocomplete="off"
                        data-search-url="{{ route('gate.portal', ['program' => $program->slug]) }}"
                        class="w-full rounded-xl border border-gray-300 px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-brand/25 sm:px-4 sm:py-2.5 sm:text-sm"
                    />
                    <p
                        id="manual-search-status"
                        class="mt-1.5 hidden text-[11px] text-gray-400 sm:text-xs"
                        role="status"
                        aria-live="polite"
                    >
                        جارٍ البحث…
                    </p>
                </div>

                <div id="manual-results" class="mt-4">
                    @include('gate.partials.manual-list')
                </div>
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
    const idleBtnClass = 'prep-mark rounded-md border px-2.5 py-1 text-[10px] font-semibold whitespace-nowrap transition sm:rounded-lg sm:px-3.5 sm:py-1.5 sm:text-xs bg-transparent text-gray-700 border-gray-300 hover:bg-gray-50';
    const presentBtnClass = 'prep-mark rounded-md border px-2.5 py-1 text-[10px] font-semibold whitespace-nowrap transition sm:rounded-lg sm:px-3.5 sm:py-1.5 sm:text-xs bg-emerald-600 text-white border-emerald-600';
    const searchInput = document.getElementById('q');
    const results = document.getElementById('manual-results');
    const searchStatus = document.getElementById('manual-search-status');
    const searchBase = searchInput?.getAttribute('data-search-url') || window.location.pathname;
    let searchTimer = null;
    let searchSeq = 0;

    function setSearchLoading(on) {
        if (!searchStatus) return;
        searchStatus.classList.toggle('hidden', !on);
    }

    function markButtonPresent(btn) {
        btn.textContent = 'حاضر';
        btn.className = presentBtnClass;
        btn.setAttribute('data-present', '1');
    }

    async function fetchList(query, page) {
        const seq = ++searchSeq;
        setSearchLoading(true);
        const url = new URL(searchBase, window.location.origin);
        url.searchParams.set('tab', 'manual');
        url.searchParams.set('partial', '1');
        if (query) {
            url.searchParams.set('q', query);
        }
        if (page && Number(page) > 1) {
            url.searchParams.set('page', String(page));
        }
        try {
            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const html = await response.text();
            if (seq !== searchSeq) return;
            if (!response.ok) {
                showFeedback(false, '', 'تعذّر تحديث القائمة.', false);
                return;
            }
            if (results) {
                results.innerHTML = html;
            }
            const publicUrl = new URL(window.location.href);
            publicUrl.searchParams.set('tab', 'manual');
            if (query) {
                publicUrl.searchParams.set('q', query);
            } else {
                publicUrl.searchParams.delete('q');
            }
            if (page && Number(page) > 1) {
                publicUrl.searchParams.set('page', String(page));
            } else {
                publicUrl.searchParams.delete('page');
            }
            publicUrl.searchParams.delete('partial');
            history.replaceState({}, '', publicUrl);
        } catch (e) {
            if (seq !== searchSeq) return;
            showFeedback(false, '', 'تعذّر الاتصال. حاول مرة أخرى.', false);
        } finally {
            if (seq === searchSeq) {
                setSearchLoading(false);
            }
        }
    }

    searchInput?.addEventListener('input', () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => {
            fetchList(searchInput.value.trim(), 1);
        }, 350);
    });

    results?.addEventListener('click', async (event) => {
        const pageLink = event.target.closest('#manual-pagination a');
        if (pageLink) {
            event.preventDefault();
            const next = new URL(pageLink.href, window.location.origin);
            fetchList(searchInput?.value.trim() || '', next.searchParams.get('page') || '1');
            return;
        }

        const btn = event.target.closest('.prep-mark');
        if (!btn || btn.disabled) return;
        if (btn.getAttribute('data-present') === '1') {
            showFeedback(true, '', 'مسجّل حاضر مسبقاً.', true);
            return;
        }
        const row = btn.closest('[data-registration-id]');
        if (!row) return;
        const registrationId = row.getAttribute('data-registration-id');
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
                body: JSON.stringify({ present: true }),
            });
            const data = await response.json().catch(() => ({}));
            if (!data.ok) {
                showFeedback(false, data.beneficiary_name || '', data.message || 'تعذّر التحديث.', false);
                return;
            }
            markButtonPresent(btn);
            showFeedback(true, data.beneficiary_name || '', data.message || 'تم تسجيل الحضور.', data.reason === 'already_present');
        } catch (e) {
            showFeedback(false, '', 'تعذّر الاتصال. حاول مرة أخرى.', false);
        } finally {
            btn.disabled = false;
        }
    });
    @endif
})();
</script>
@endpush
