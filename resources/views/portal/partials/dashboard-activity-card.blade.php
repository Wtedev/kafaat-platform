@php
$toneClasses = [
    'amber' => 'bg-amber-50 text-amber-900 ring-1 ring-amber-200/80',
    'blue' => 'bg-blue-50 text-blue-900 ring-1 ring-blue-200/80',
    'emerald' => 'bg-emerald-50 text-emerald-900 ring-1 ring-emerald-200/80',
    'indigo' => 'bg-indigo-50 text-indigo-900 ring-1 ring-indigo-200/80',
    'rose' => 'bg-rose-50 text-rose-900 ring-1 ring-rose-200/80',
    'slate' => 'bg-slate-100 text-slate-800 ring-1 ring-slate-200/80',
];
$badgeClass = $toneClasses[$activity['status_tone']] ?? $toneClasses['slate'];
$isDiscover = ! empty($activity['discover']);
@endphp

<article class="group flex min-w-[17.5rem] max-w-[17.5rem] flex-none snap-start flex-col rounded-2xl border border-gray-100/80 bg-white p-5 shadow-sm transition hover:border-[#c5ddef] hover:shadow-md sm:min-w-[19rem] sm:max-w-[19rem]">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-semibold" style="background:#EAF2FA;color:#253B5B">{{ $activity['type_label'] }}</span>
        <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-semibold {{ $badgeClass }}">{{ $activity['status_label'] }}</span>
    </div>
    <h3 class="mb-3 text-right text-base font-bold leading-snug text-gray-900">{{ $activity['title'] }}</h3>

    @if ($activity['progress'] !== null)
    <div class="mb-4">
        <div class="mb-1 flex justify-between text-xs text-gray-500">
            <span>{{ number_format((float) $activity['progress'], 0) }}٪</span>
            <span>التقدم</span>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-gray-100">
            <div class="h-full rounded-full transition-all duration-500" style="width: {{ min(100, max(0, (float) $activity['progress'])) }}%; background: linear-gradient(to left, #3CB878, #253B5B)"></div>
        </div>
    </div>
    @endif

    <div class="mt-auto flex justify-end pt-2">
        @if ($isDiscover)
        <a href="{{ $activity['cta_url'] }}" class="inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold text-[#253B5B] ring-1 ring-gray-200/90 transition hover:bg-gray-50">
            {{ $activity['cta_label'] }}
            <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
        @else
        <a href="{{ $activity['cta_url'] }}" class="inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#253B5B">
            {{ $activity['cta_label'] }}
            <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
        @endif
    </div>
</article>
