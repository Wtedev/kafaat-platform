@props([
    'programId',
    'programTitle',
    'statusUrl',
    'checkInUrl',
    'initialActive' => false,
    'initialExpiresAtMs' => null,
])

<dialog
    id="program-attendance-remote-{{ $programId }}"
    class="portal-attendance-modal text-right"
    aria-labelledby="program-attendance-remote-title-{{ $programId }}"
>
    <div class="p-5 sm:p-6">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 id="program-attendance-remote-title-{{ $programId }}" class="text-base font-bold text-[#335483]">التحضير</h2>
                <p class="mt-0.5 truncate text-xs text-gray-500">{{ $programTitle }}</p>
            </div>
            <button
                type="button"
                class="portal-attendance-modal-close shrink-0 rounded-lg px-2 py-1 text-sm font-medium text-slate-500 transition hover:bg-slate-50"
                aria-label="إغلاق"
            >
                إغلاق
            </button>
        </div>

        <div class="mt-4">
            <x-portal-attendance-session
                :status-url="$statusUrl"
                :check-in-url="$checkInUrl"
                :initial-active="$initialActive"
                :initial-expires-at-ms="$initialExpiresAtMs"
            />
            <div
                class="rounded-2xl border border-dashed border-[#c5d4e4] bg-[#F7FAFC] px-4 py-3 text-sm text-gray-600"
                data-portal-attendance-waiting
                @if ($initialActive) hidden @endif
            >
                <p class="font-semibold text-[#335483]">التحضير عن بُعد</p>
                <p class="mt-1">ستظهر خانة تسجيل التحضير هنا تلقائياً عند فتح جلسة الحضور من الإدارة.</p>
            </div>
        </div>
    </div>
</dialog>
