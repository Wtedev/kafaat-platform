@php
    $c = $competencyCompleteness ?? null;
    $showBanner = is_array($c) && ! ($c['complete'] ?? false);
    $waiting = is_array($c) ? ($c['waiting_label'] ?? 'بانتظار إكمال بياناتك') : 'بانتظار إكمال بياناتك';
    $percent = is_array($c) ? (int) ($c['percent'] ?? 0) : 0;
    $missing = is_array($c) ? ($c['missing'] ?? []) : [];
@endphp

@if ($showBanner)
<aside class="comp-complete mb-6 overflow-hidden rounded-[1.35rem] border border-[#335483]/15 bg-white shadow-[0_16px_40px_-24px_rgba(51,84,131,0.45)]" aria-labelledby="comp-complete-title">
    <div class="relative px-5 py-5 sm:px-6 sm:py-6">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-1" style="background:linear-gradient(90deg,#335483,#3CB878)" aria-hidden="true"></div>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 flex-1 text-right">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-800 ring-1 ring-amber-200/80">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                    {{ $waiting }}
                </span>

                <h2 id="comp-complete-title" class="mt-3 text-lg font-bold tracking-tight text-gray-900 sm:text-xl">
                    أكمل بياناتك
                </h2>
                <p class="mt-1.5 max-w-xl text-sm leading-relaxed text-gray-600">
                    لا يمكن الحصول على الشهادة دون إكمال بيانات صفحة الكفاءة. عبّئ الأقسام الأساسية أدناه لتظهر أهليتك للشهادة بشكل صحيح.
                </p>

                @if (is_array($missing) && $missing !== [])
                    <ul class="mt-3 flex flex-wrap gap-2" aria-label="البيانات الناقصة">
                        @foreach ($missing as $item)
                            <li class="rounded-lg bg-[#e9eff6] px-2.5 py-1 text-xs font-medium text-[#335483]">
                                {{ $item['label'] ?? '' }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="flex w-full flex-col items-stretch gap-3 sm:w-auto sm:min-w-[9.5rem] sm:items-end">
                <div class="text-left sm:text-center" dir="ltr">
                    <p class="text-2xl font-bold tabular-nums text-[#335483]">{{ $percent }}%</p>
                    <p class="text-[11px] font-medium text-slate-400">مكتمل</p>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100 sm:w-36">
                    <div class="h-full rounded-full transition-all duration-500" style="width: {{ max(6, $percent) }}%; background:linear-gradient(90deg,#335483,#5a7fb0)"></div>
                </div>
                <a
                    href="#competency-sections"
                    class="inline-flex items-center justify-center gap-2 rounded-2xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:opacity-95"
                    style="background:#335483"
                >
                    أكمل بياناتك الآن
                    <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </a>
            </div>
        </div>
    </div>
</aside>
@endif
