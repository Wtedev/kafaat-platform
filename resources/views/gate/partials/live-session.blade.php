@php
    $live = $liveSession ?? [
        'can_open' => false,
        'active' => false,
        'ended' => false,
        'session_minutes' => $liveSessionMinutes ?? 5,
        'remaining_seconds' => 0,
        'expires_at_ms' => null,
        'started_at' => null,
        'expires_at' => null,
        'closed_at' => null,
        'present_count' => 0,
        'approved_count' => 0,
        'attendees' => [],
    ];
@endphp

<section
    id="gate-live-session"
    class="mt-5 rounded-2xl border border-[#d7e2ef] bg-[#f5f8fc] p-4 sm:p-5"
    data-gate-live-session
    data-status-url="{{ route('gate.live-session.status', $program) }}"
    data-start-url="{{ route('gate.live-session.start', $program) }}"
    data-end-url="{{ route('gate.live-session.end', $program) }}"
    data-session-minutes="{{ (int) ($live['session_minutes'] ?? 5) }}"
    aria-labelledby="gate-live-session-title"
>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 id="gate-live-session-title" class="text-sm font-bold text-[#335483] sm:text-base">جلسة التحضير</h2>
            <p class="mt-1 text-xs text-gray-600" data-live-status-label>
                @if ($live['active'])
                    جلسة التحضير مفتوحة
                @elseif ($live['ended'])
                    انتهت جلسة التحضير
                @else
                    بانتظار فتح جلسة التحضير
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                data-live-open
                class="rounded-xl bg-[#335483] px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-50"
                @disabled(! $live['can_open'] || $live['active'])
                @if ($live['active']) hidden @endif
            >
                فتح جلسة التحضير
            </button>
            <span
                data-live-open-badge
                class="inline-flex items-center rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white"
                @if (! $live['active']) hidden @endif
            >
                جلسة التحضير مفتوحة
            </span>
            <button
                type="button"
                data-live-end
                class="rounded-xl border border-red-600 bg-white px-3.5 py-2 text-xs font-semibold text-red-700 transition hover:bg-red-50"
                @if (! $live['active']) hidden @endif
            >
                إنهاء الجلسة الآن
            </button>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3" data-live-meta @if (! $live['active'] && ! $live['ended']) hidden @endif>
        <div class="rounded-xl bg-white px-3 py-3 ring-1 ring-[#c5d4e4]/70">
            <p class="text-[11px] text-gray-500">الوقت المتبقي</p>
            <p class="mt-1 font-mono text-2xl font-bold tabular-nums text-[#335483]" data-live-countdown>
                {{ sprintf('%02d:%02d', intdiv((int) $live['remaining_seconds'], 60), ((int) $live['remaining_seconds']) % 60) }}
            </p>
        </div>
        <div class="rounded-xl bg-white px-3 py-3 ring-1 ring-[#c5d4e4]/70">
            <p class="text-[11px] text-gray-500">بداية الجلسة</p>
            <p class="mt-1 text-sm font-semibold text-gray-900" data-live-started>{{ $live['started_at'] ?? '—' }}</p>
        </div>
        <div class="rounded-xl bg-white px-3 py-3 ring-1 ring-[#c5d4e4]/70">
            <p class="text-[11px] text-gray-500">نهاية الجلسة</p>
            <p class="mt-1 text-sm font-semibold text-gray-900" data-live-expires>{{ $live['closed_at'] ?? $live['expires_at'] ?? '—' }}</p>
        </div>
    </div>

    <div class="mt-4 rounded-xl bg-white px-3 py-3 ring-1 ring-[#c5d4e4]/70">
        <p class="text-xs text-gray-500">
            الحضور:
            <span class="font-bold text-gray-900 tabular-nums" data-live-present>{{ en_num($live['present_count'] ?? 0) }}</span>
            من
            <span class="font-bold text-gray-900 tabular-nums" data-live-approved>{{ en_num($live['approved_count'] ?? 0) }}</span>
            مقبول
        </p>
        <ul class="mt-3 max-h-64 space-y-2 overflow-y-auto" data-live-attendees role="list" aria-live="polite">
            @forelse ($live['attendees'] as $row)
                <li class="flex items-center justify-between gap-3 rounded-lg bg-[#F7FAFC] px-3 py-2 text-xs">
                    <span class="min-w-0 truncate font-semibold text-gray-900">{{ $row['name'] }}</span>
                    <span class="shrink-0 {{ $row['present'] ? 'text-emerald-700' : 'text-gray-400' }}">
                        {{ $row['present'] ? ('حاضر'.($row['marked_at'] ? ' — '.$row['marked_at'] : '')) : 'لم يُسجَّل' }}
                    </span>
                </li>
            @empty
                <li class="text-xs text-gray-400" data-live-empty>لا يوجد مسجلون مقبولون.</li>
            @endforelse
        </ul>
    </div>
</section>

<dialog
    id="gate-live-open-dialog"
    class="fixed inset-0 z-50 m-auto h-fit w-[min(22rem,calc(100vw-2rem))] max-h-[calc(100dvh-2rem)] overflow-y-auto rounded-2xl border border-gray-200 bg-white p-5 shadow-xl backdrop:bg-slate-900/40"
>
    <form method="dialog" class="space-y-4 text-right">
        <div>
            <h2 class="text-sm font-bold text-gray-900">فتح جلسة التحضير</h2>
            <p class="mt-2 text-sm leading-relaxed text-gray-600">
                سيتم فتح التحضير للمستفيدين لمدة {{ (int) ($liveSessionMinutes ?? 5) }} دقائق. هل تريد المتابعة؟
            </p>
        </div>
        <div class="flex items-center justify-start gap-2">
            <button type="submit" value="cancel" class="rounded-xl border border-gray-300 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">تراجع</button>
            <button type="submit" value="confirm" class="rounded-xl bg-[#335483] px-3.5 py-2 text-xs font-semibold text-white hover:opacity-95">نعم، افتح الجلسة</button>
        </div>
    </form>
</dialog>

<dialog
    id="gate-live-end-dialog"
    class="fixed inset-0 z-50 m-auto h-fit w-[min(22rem,calc(100vw-2rem))] max-h-[calc(100dvh-2rem)] overflow-y-auto rounded-2xl border border-gray-200 bg-white p-5 shadow-xl backdrop:bg-slate-900/40"
>
    <form method="dialog" class="space-y-4 text-right">
        <div>
            <h2 class="text-sm font-bold text-gray-900">إنهاء الجلسة</h2>
            <p class="mt-2 text-sm leading-relaxed text-gray-600">
                سيتم إغلاق التحضير فوراً ولن يتمكن المستفيدون من التسجيل بعدها. هل تريد المتابعة؟
            </p>
        </div>
        <div class="flex items-center justify-start gap-2">
            <button type="submit" value="cancel" class="rounded-xl border border-gray-300 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">تراجع</button>
            <button type="submit" value="confirm" class="rounded-xl border border-red-600 bg-red-600 px-3.5 py-2 text-xs font-semibold text-white hover:opacity-95">نعم، أنهِ الجلسة</button>
        </div>
    </form>
</dialog>
