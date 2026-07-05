@props([
    'labelClass' => 'mb-1.5 block text-sm font-medium text-gray-700',
])

@php
$user = auth()->user();
$identityTypeLabel = $user?->identity_type?->label() ?? '—';
$maskedIdentity = $user?->maskedIdentityNumber() ?? '—';
$lockedInputClass = 'w-full cursor-not-allowed rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-600';
@endphp

<div>
    <label class="{{ $labelClass }}">نوع الهوية</label>
    <input type="text" value="{{ $identityTypeLabel }}" readonly tabindex="-1" class="{{ $lockedInputClass }}" />
</div>

<div>
    <label class="{{ $labelClass }}">رقم الهوية / الإقامة</label>
    <div class="relative">
        <input
            type="text"
            value="{{ $maskedIdentity }}"
            readonly
            tabindex="-1"
            dir="ltr"
            class="{{ $lockedInputClass }} pe-10 font-mono tracking-wider"
            aria-describedby="identity-locked-hint"
        />
        <span class="pointer-events-none absolute inset-y-0 end-3 flex items-center text-slate-400" aria-hidden="true">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V7A1.5 1.5 0 005 8.5v6A1.5 1.5 0 006.5 16h7a1.5 1.5 0 001.5-1.5v-6A1.5 1.5 0 0014 8.5v-1.5A4.5 4.5 0 0010 1zm3 6V5.5a3 3 0 10-6 0V7h6z" clip-rule="evenodd" />
            </svg>
        </span>
    </div>
    <p id="identity-locked-hint" class="mt-1.5 text-xs leading-relaxed text-slate-500">
        مسجّل ولا يمكن تعديله من هنا. للتعديل، يرجى التواصل مع الدعم.
    </p>
</div>
