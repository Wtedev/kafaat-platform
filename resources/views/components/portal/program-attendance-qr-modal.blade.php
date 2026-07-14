@props([
    'programId',
    'qrDataUri',
    'passCode' => null,
    'venueLabel' => null,
])

<dialog
    id="program-attendance-qr-{{ $programId }}"
    class="portal-attendance-modal text-right"
    aria-labelledby="program-attendance-qr-title-{{ $programId }}"
>
    <div class="p-5 sm:p-6">
        <div class="flex items-start justify-between gap-3">
            <h2 id="program-attendance-qr-title-{{ $programId }}" class="text-base font-bold text-[#335483]">رمز الحضور</h2>
            <button
                type="button"
                class="portal-attendance-modal-close shrink-0 rounded-lg px-2 py-1 text-sm font-medium text-slate-500 transition hover:bg-slate-50"
                aria-label="إغلاق"
            >
                إغلاق
            </button>
        </div>

        <p class="mt-2 text-sm text-gray-600">
            أظهر رمز QR عند الوصول لتسجيل تحضيرك.
            @if (filled($venueLabel))
                المكان: {{ $venueLabel }}
            @endif
        </p>

        <div class="mt-5 flex flex-col items-center">
            <div class="inline-block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-[#c5d4e4]/50">
                <img
                    src="{{ $qrDataUri }}"
                    alt="رمز QR للحضور"
                    class="mx-auto h-[180px] w-[180px] sm:h-[220px] sm:w-[220px]"
                    width="220"
                    height="220"
                >
            </div>
            @if (filled($passCode))
            <p class="mt-3 font-mono text-xs tracking-wide text-gray-400" dir="ltr">{{ $passCode }}</p>
            @endif
        </div>
    </div>
</dialog>
