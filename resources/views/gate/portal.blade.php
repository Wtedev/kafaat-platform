@extends('layouts.gate')

@section('title', 'بوابة التحضير — '.$program->title)
@section('container_width', 'max-w-6xl')

@push('head')
<meta name="robots" content="noindex, nofollow" />
<meta name="referrer" content="no-referrer" />
@endpush

@section('content')
<div class="space-y-4">
    <div class="bg-white/95 rounded-3xl shadow-xl border border-white/80 p-4 sm:p-7">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-base font-bold text-gray-900 leading-snug sm:text-lg">{{ $program->title }}</h1>
                <p class="mt-1 text-xs text-gray-600 sm:text-sm">
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

        <div class="mt-4 rounded-2xl border border-[#d7e2ef] bg-[#f5f8fc] px-3 py-3 sm:px-4">
            @if ($prepDateOptions !== [])
                <form method="GET" action="{{ route('gate.portal', ['program' => $program->slug]) }}" class="space-y-2">
                    @if ($tab)
                        <input type="hidden" name="tab" value="{{ $tab }}" />
                    @endif
                    @if ($search !== '')
                        <input type="hidden" name="q" value="{{ $search }}" />
                    @endif
                    <label for="prep-date" class="block text-xs font-medium text-gray-600">اختيار يوم التحضير</label>
                    <select
                        id="prep-date"
                        name="date"
                        onchange="this.form.submit()"
                        class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm font-semibold text-[#335483] focus:outline-none focus:ring-2 focus:ring-brand/25"
                    >
                        @foreach ($prepDateOptions as $optionDate => $optionLabel)
                            <option value="{{ $optionDate }}" @selected($optionDate === $prepDate)>
                                {{ $optionLabel }}@if ($optionDate === $calendarToday) (اليوم)@endif
                            </option>
                        @endforeach
                    </select>
                </form>
                @if ($dayTypeLabel)
                    <p class="mt-2 text-xs font-medium {{ $isInPersonToday ? 'text-emerald-700' : 'text-sky-700' }}">
                        نوع اليوم: {{ $dayTypeLabel }}
                        <span class="font-mono text-gray-400">· {{ $prepDate }}</span>
                    </p>
                @endif
            @else
                <p class="text-xs font-medium text-amber-700">
                    لا توجد أيام تحضير معرفة لهذا البرنامج.
                </p>
            @endif
        </div>

        <nav class="mt-5 flex gap-2 border-b border-gray-200 pb-px" aria-label="وسائل التحضير">
            @if ($isRemoteToday ?? false)
                <a
                    href="{{ route('gate.portal', ['program' => $program->slug, 'tab' => 'session', 'date' => $prepDate]) }}"
                    class="px-3 py-2 text-sm font-semibold rounded-t-lg {{ $tab === 'session' ? 'text-[#335483] border-b-2 border-[#335483]' : 'text-gray-500 hover:text-gray-800' }}"
                >
                    جلسة التحضير
                </a>
            @endif
            @if ($isInPersonToday)
                <a
                    href="{{ route('gate.portal', ['program' => $program->slug, 'tab' => 'qr', 'date' => $prepDate]) }}"
                    class="px-3 py-2 text-sm font-semibold rounded-t-lg {{ $tab === 'qr' ? 'text-[#335483] border-b-2 border-[#335483]' : 'text-gray-500 hover:text-gray-800' }}"
                >
                    مسح QR
                </a>
            @endif
            <a
                href="{{ route('gate.portal', ['program' => $program->slug, 'tab' => 'manual', 'date' => $prepDate, 'q' => $search ?: null]) }}"
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

        @if ($tab === 'session' && ($isRemoteToday ?? false))
            @include('gate.partials.live-session')
        @elseif ($tab === 'qr' && $isInPersonToday)
            <div class="mt-5">
                <div id="reader" class="overflow-hidden rounded-2xl border border-[#d7e2ef] bg-[#0f172a]" style="min-height: 240px;"></div>
                <p id="camera-hint" class="mt-2 text-center text-xs text-gray-500">وجّه الكاميرا نحو رمز QR الخاص بالمشاركة.</p>
                <p id="camera-error" class="mt-2 hidden text-center text-xs text-red-600"></p>
            </div>
        @elseif ($tab === 'manual')
            @if (! $isPrepDayToday)
                <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900">
                    لا يتوفر تحضير لليوم المحدد. اختَر يوماً من أيام البرنامج.
                </div>
            @else
                <div class="mt-5">
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

@if ($tab === 'manual' && $isPrepDayToday)
<dialog
    id="prep-unmark-dialog"
    class="fixed inset-0 m-auto h-fit w-[min(22rem,calc(100vw-2rem))] rounded-2xl border border-gray-200 bg-white p-5 shadow-xl backdrop:bg-slate-900/40"
>
    <form method="dialog" class="space-y-4 text-right">
        <div>
            <h2 class="text-sm font-bold text-gray-900">إلغاء الحضور</h2>
            <p id="prep-unmark-message" class="mt-2 text-sm leading-relaxed text-gray-600"></p>
        </div>
        <div class="flex items-center justify-start gap-2">
            <button
                type="submit"
                value="cancel"
                class="rounded-xl border border-gray-300 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
            >
                تراجع
            </button>
            <button
                type="submit"
                value="confirm"
                class="rounded-xl border border-red-600 bg-red-600 px-3.5 py-2 text-xs font-semibold text-white hover:opacity-95"
            >
                نعم، ألغِ الحضور
            </button>
        </div>
    </form>
</dialog>

<dialog
    id="prep-note-dialog"
    class="fixed inset-0 m-auto h-fit w-[min(24rem,calc(100vw-2rem))] rounded-2xl border border-gray-200 bg-white p-5 shadow-xl backdrop:bg-slate-900/40"
>
    <form method="dialog" class="space-y-4 text-right">
        <div>
            <h2 id="prep-note-title" class="text-sm font-bold text-gray-900">تسجيل الحضور</h2>
            <p id="prep-note-subtitle" class="mt-1 text-xs text-gray-500"></p>
            <label for="prep-note-input" class="mt-3 block text-xs font-medium text-gray-600">
                ملاحظة داخلية <span class="font-normal text-gray-400">(اختيارية — لا يراها المستفيد)</span>
            </label>
            <textarea
                id="prep-note-input"
                rows="3"
                maxlength="1000"
                class="mt-1.5 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/25"
                placeholder="مثال: تأخر 10 دقائق، اعتذر عن جزء من الجلسة…"
            ></textarea>
        </div>
        <div class="flex items-center justify-start gap-2">
            <button
                type="submit"
                value="cancel"
                class="rounded-xl border border-gray-300 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
            >
                تراجع
            </button>
            <button
                type="submit"
                value="confirm"
                class="rounded-xl border border-brand bg-brand px-3.5 py-2 text-xs font-semibold text-white hover:opacity-95"
            >
                حفظ
            </button>
        </div>
    </form>
</dialog>
@endif
@endsection

@push('scripts')
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const feedback = document.getElementById('gate-feedback');
    const feedbackName = document.getElementById('gate-feedback-name');
    const feedbackMessage = document.getElementById('gate-feedback-message');
    const programSlug = @json($program->slug);
    const prepDate = @json($prepDate);
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
                body: JSON.stringify({ pass, date: prepDate }),
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

    function beneficiaryName(btn) {
        return (btn.getAttribute('data-name') || btn.closest('tr')?.querySelector('td')?.innerText || '').trim();
    }

    function markButtonPresent(btn) {
        const name = beneficiaryName(btn);
        btn.textContent = 'حاضر';
        btn.className = presentBtnClass;
        btn.setAttribute('data-present', '1');
        if (name) {
            btn.setAttribute('aria-label', 'إلغاء حضور ' + name);
        }
        const row = btn.closest('[data-registration-id]');
        const noteBtn = row?.querySelector('.prep-note');
        if (noteBtn) {
            noteBtn.classList.remove('hidden');
        }
    }

    function markButtonIdle(btn) {
        const name = beneficiaryName(btn);
        btn.textContent = 'تحضير';
        btn.className = idleBtnClass;
        btn.setAttribute('data-present', '0');
        if (name) {
            btn.setAttribute('aria-label', 'تحضير ' + name);
        }
        const row = btn.closest('[data-registration-id]');
        if (row) {
            row.setAttribute('data-internal-note', '');
            const preview = row.querySelector('.internal-note-preview');
            if (preview) {
                preview.textContent = '';
                preview.classList.add('hidden');
            }
            const noteBtn = row.querySelector('.prep-note');
            if (noteBtn) {
                noteBtn.classList.add('hidden');
                noteBtn.className = 'prep-note rounded-md border px-2 py-1 text-[10px] font-semibold whitespace-nowrap transition sm:rounded-lg sm:px-2.5 sm:py-1.5 sm:text-xs border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hidden';
            }
        }
    }

    function updateInternalNoteUi(row, note) {
        if (!row) return;
        const text = (note || '').trim();
        row.setAttribute('data-internal-note', text);
        const preview = row.querySelector('.internal-note-preview');
        if (preview) {
            preview.textContent = text;
            preview.classList.toggle('hidden', text === '');
        }
        const noteBtn = row.querySelector('.prep-note');
        if (noteBtn) {
            noteBtn.classList.remove('hidden');
            noteBtn.className = 'prep-note rounded-md border px-2 py-1 text-[10px] font-semibold whitespace-nowrap transition sm:rounded-lg sm:px-2.5 sm:py-1.5 sm:text-xs ' +
                (text !== ''
                    ? 'border-amber-300 bg-amber-50 text-amber-900'
                    : 'border-gray-200 bg-white text-gray-500 hover:bg-gray-50');
        }
    }

    function confirmUnmark(name) {
        return new Promise((resolve) => {
            const dialog = document.getElementById('prep-unmark-dialog');
            const message = document.getElementById('prep-unmark-message');
            if (!dialog || typeof dialog.showModal !== 'function') {
                resolve(window.confirm('هل تؤكد إلغاء حضور «' + name + '»؟'));
                return;
            }
            if (message) {
                message.textContent = 'هل تؤكد إلغاء حضور «' + name + '»؟';
            }
            const onClose = () => {
                dialog.removeEventListener('close', onClose);
                resolve(dialog.returnValue === 'confirm');
            };
            dialog.addEventListener('close', onClose);
            dialog.returnValue = 'cancel';
            dialog.showModal();
        });
    }

    function promptInternalNote({ title, subtitle, initial }) {
        return new Promise((resolve) => {
            const dialog = document.getElementById('prep-note-dialog');
            const titleEl = document.getElementById('prep-note-title');
            const subtitleEl = document.getElementById('prep-note-subtitle');
            const input = document.getElementById('prep-note-input');
            if (!dialog || typeof dialog.showModal !== 'function' || !input) {
                const fallback = window.prompt((title || 'ملاحظة داخلية') + '\n(لا يراها المستفيد)', initial || '');
                resolve(fallback === null ? null : { confirmed: true, note: (fallback || '').trim() });
                return;
            }
            if (titleEl) titleEl.textContent = title || 'ملاحظة داخلية';
            if (subtitleEl) subtitleEl.textContent = subtitle || '';
            input.value = initial || '';
            const onClose = () => {
                dialog.removeEventListener('close', onClose);
                if (dialog.returnValue !== 'confirm') {
                    resolve(null);
                    return;
                }
                resolve({ confirmed: true, note: (input.value || '').trim() });
            };
            dialog.addEventListener('close', onClose);
            dialog.returnValue = 'cancel';
            dialog.showModal();
            input.focus();
        });
    }

    async function submitAttendance(btn, present, internalNotes) {
        const row = btn.closest('[data-registration-id]');
        if (!row) return;
        const registrationId = row.getAttribute('data-registration-id');
        btn.disabled = true;
        try {
            const url = `/gate/${programSlug}/registrations/${registrationId}/attendance`;
            const payload = { present, date: prepDate };
            if (present && typeof internalNotes === 'string') {
                payload.internal_notes = internalNotes;
            }
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });
            const data = await response.json().catch(() => ({}));
            if (!data.ok) {
                showFeedback(false, data.beneficiary_name || '', data.message || 'تعذّر التحديث.', false);
                return;
            }
            if (present) {
                markButtonPresent(btn);
                updateInternalNoteUi(row, data.internal_note || '');
                showFeedback(true, data.beneficiary_name || '', data.message || 'تم تسجيل الحضور.', data.reason === 'already_present');
            } else {
                markButtonIdle(btn);
                showFeedback(true, data.beneficiary_name || '', data.message || 'تم إلغاء الحضور.', false);
            }
        } catch (e) {
            showFeedback(false, '', 'تعذّر الاتصال. حاول مرة أخرى.', false);
        } finally {
            btn.disabled = false;
        }
    }

    async function saveNoteOnly(row, noteBtn, note) {
        const markBtn = row.querySelector('.prep-mark');
        if (!markBtn) return;
        noteBtn.disabled = true;
        markBtn.disabled = true;
        try {
            const registrationId = row.getAttribute('data-registration-id');
            const url = `/gate/${programSlug}/registrations/${registrationId}/attendance`;
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    present: true,
                    date: prepDate,
                    internal_notes: note,
                }),
            });
            const data = await response.json().catch(() => ({}));
            if (!data.ok) {
                showFeedback(false, data.beneficiary_name || '', data.message || 'تعذّر حفظ الملاحظة.', false);
                return;
            }
            markButtonPresent(markBtn);
            updateInternalNoteUi(row, data.internal_note || '');
            showFeedback(true, data.beneficiary_name || '', 'تم حفظ الملاحظة الداخلية.', false);
        } catch (e) {
            showFeedback(false, '', 'تعذّر الاتصال. حاول مرة أخرى.', false);
        } finally {
            noteBtn.disabled = false;
            markBtn.disabled = false;
        }
    }

    async function fetchList(query, page) {
        const seq = ++searchSeq;
        setSearchLoading(true);
        const url = new URL(searchBase, window.location.origin);
        url.searchParams.set('tab', 'manual');
        url.searchParams.set('partial', '1');
        if (prepDate) {
            url.searchParams.set('date', prepDate);
        }
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
            if (prepDate) {
                publicUrl.searchParams.set('date', prepDate);
            }
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

        const noteBtn = event.target.closest('.prep-note');
        if (noteBtn && !noteBtn.disabled) {
            const row = noteBtn.closest('[data-registration-id]');
            if (!row) return;
            const name = noteBtn.getAttribute('data-name') || 'المستفيد';
            const result = await promptInternalNote({
                title: 'ملاحظة داخلية',
                subtitle: 'لـ «' + name + '» — لا يراها المستفيد',
                initial: row.getAttribute('data-internal-note') || '',
            });
            if (!result) return;
            await saveNoteOnly(row, noteBtn, result.note);
            return;
        }

        const btn = event.target.closest('.prep-mark');
        if (!btn || btn.disabled) return;
        if (btn.getAttribute('data-present') === '1') {
            const name = beneficiaryName(btn) || 'هذا المستفيد';
            const confirmed = await confirmUnmark(name);
            if (!confirmed) return;
            await submitAttendance(btn, false);
            return;
        }
        const name = beneficiaryName(btn) || 'المستفيد';
        const result = await promptInternalNote({
            title: 'تسجيل الحضور',
            subtitle: 'لـ «' + name + '»',
            initial: '',
        });
        if (!result) return;
        await submitAttendance(btn, true, result.note);
    });
    @endif

    @if ($tab === 'session' && ($isRemoteToday ?? false))
    (function initGateLiveSession() {
        const root = document.querySelector('[data-gate-live-session]');
        if (!root) return;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const statusUrl = root.dataset.statusUrl;
        const startUrl = root.dataset.startUrl;
        const endUrl = root.dataset.endUrl;
        const openBtn = root.querySelector('[data-live-open]');
        const openBadge = root.querySelector('[data-live-open-badge]');
        const endBtn = root.querySelector('[data-live-end]');
        const statusLabel = root.querySelector('[data-live-status-label]');
        const countdownEl = root.querySelector('[data-live-countdown]');
        const startedEl = root.querySelector('[data-live-started]');
        const expiresEl = root.querySelector('[data-live-expires]');
        const presentEl = root.querySelector('[data-live-present]');
        const approvedEl = root.querySelector('[data-live-approved]');
        const attendeesEl = root.querySelector('[data-live-attendees]');
        const metaEl = root.querySelector('[data-live-meta]');
        const openDialog = document.getElementById('gate-live-open-dialog');
        const endDialog = document.getElementById('gate-live-end-dialog');
        let expiresAtMs = null;
        let tickTimer = null;
        let pollTimer = null;
        let busy = false;

        function formatRemaining(totalSeconds) {
            const remaining = Math.max(0, totalSeconds);
            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;
            return String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
        }

        function confirmDialog(dialog) {
            return new Promise((resolve) => {
                if (!dialog || typeof dialog.showModal !== 'function') {
                    resolve(window.confirm('هل تريد المتابعة؟'));
                    return;
                }
                const onClose = () => {
                    dialog.removeEventListener('close', onClose);
                    resolve(dialog.returnValue === 'confirm');
                };
                dialog.addEventListener('close', onClose);
                dialog.returnValue = 'cancel';
                dialog.showModal();
            });
        }

        function renderAttendees(rows) {
            if (!attendeesEl) return;
            if (!rows || rows.length === 0) {
                attendeesEl.innerHTML = '<li class="text-xs text-gray-400" data-live-empty>لا يوجد مسجلون مقبولون.</li>';
                return;
            }
            attendeesEl.innerHTML = rows.map((row) => {
                const detail = row.present
                    ? ('حاضر' + (row.marked_at ? ' — ' + row.marked_at : ''))
                    : 'لم يُسجَّل';
                const tone = row.present ? 'text-emerald-700' : 'text-gray-400';
                return '<li class="flex items-center justify-between gap-3 rounded-lg bg-[#F7FAFC] px-3 py-2 text-xs">'
                    + '<span class="min-w-0 truncate font-semibold text-gray-900"></span>'
                    + '<span class="shrink-0 ' + tone + '"></span>'
                    + '</li>';
            }).join('');
            Array.from(attendeesEl.children).forEach((li, index) => {
                const row = rows[index];
                li.children[0].textContent = row.name;
                li.children[1].textContent = row.present
                    ? ('حاضر' + (row.marked_at ? ' — ' + row.marked_at : ''))
                    : 'لم يُسجَّل';
            });
        }

        function applyStatus(status) {
            if (!status) return;
            const active = Boolean(status.active);
            const ended = Boolean(status.ended);
            if (statusLabel) {
                statusLabel.textContent = active
                    ? 'جلسة التحضير مفتوحة'
                    : (ended ? 'انتهت جلسة التحضير' : 'بانتظار فتح جلسة التحضير');
            }
            if (openBtn) {
                openBtn.hidden = active;
                openBtn.disabled = !status.can_open || active || busy;
            }
            if (openBadge) openBadge.hidden = !active;
            if (endBtn) endBtn.hidden = !active;
            if (metaEl) metaEl.hidden = !(active || ended);
            if (startedEl) startedEl.textContent = status.started_at || '—';
            if (expiresEl) expiresEl.textContent = status.closed_at || status.expires_at || '—';
            if (presentEl) presentEl.textContent = String(status.present_count ?? 0);
            if (approvedEl) approvedEl.textContent = String(status.approved_count ?? 0);
            renderAttendees(status.attendees || []);

            if (active && status.expires_at_ms) {
                expiresAtMs = status.expires_at_ms;
                updateCountdown();
                if (!tickTimer) tickTimer = window.setInterval(updateCountdown, 1000);
            } else {
                expiresAtMs = null;
                if (countdownEl) countdownEl.textContent = '00:00';
                if (tickTimer) {
                    window.clearInterval(tickTimer);
                    tickTimer = null;
                }
            }
        }

        function updateCountdown() {
            if (!countdownEl || !expiresAtMs) return;
            const remaining = Math.max(0, Math.floor((expiresAtMs - Date.now()) / 1000));
            countdownEl.textContent = formatRemaining(remaining);
            if (remaining <= 0) {
                fetchStatus();
            }
        }

        async function fetchStatus() {
            try {
                const response = await fetch(statusUrl, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const data = await response.json();
                applyStatus(data);
            } catch (e) {}
        }

        async function postAction(url) {
            if (busy) return;
            busy = true;
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: '{}',
                });
                const data = await response.json().catch(() => ({}));
                if (data.status) applyStatus(data.status);
                else await fetchStatus();
                if (data.message && typeof showFeedback === 'function') {
                    showFeedback(Boolean(data.ok), '', data.message, false);
                }
            } catch (e) {
                if (typeof showFeedback === 'function') {
                    showFeedback(false, '', 'تعذّر الاتصال. حاول مرة أخرى.', false);
                }
            } finally {
                busy = false;
            }
        }

        openBtn?.addEventListener('click', async () => {
            if (openBtn.disabled) return;
            const ok = await confirmDialog(openDialog);
            if (!ok) return;
            await postAction(startUrl);
        });

        endBtn?.addEventListener('click', async () => {
            const ok = await confirmDialog(endDialog);
            if (!ok) return;
            await postAction(endUrl);
        });

        fetchStatus();
        pollTimer = window.setInterval(fetchStatus, 3000);
    })();
    @endif
})();
</script>
@endpush
