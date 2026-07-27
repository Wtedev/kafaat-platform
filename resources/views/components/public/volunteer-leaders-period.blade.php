@props([
    'range',
    'dayGroups' => [],
    'monthLabel' => '',
    'inPersonDaysCount' => 0,
])

{{-- Compact schedule breakdown: range + in-person day chips + remote remainder --}}
<div class="space-y-2.5">
    <p class="text-sm font-medium leading-snug text-gray-900">{{ $range }}</p>

    <div class="space-y-1.5" role="list" aria-label="توزيع أيام الحضور وعن بعد">
        <div
            role="listitem"
            class="rounded-xl bg-[#F7FAFC] px-2.5 py-2 ring-1 ring-[#c5d4e4]/70"
        >
            <div class="flex items-baseline justify-between gap-2">
                <span class="text-[11px] font-semibold tracking-wide text-[#335483]">حضوري</span>
                @if ($inPersonDaysCount > 0)
                    <span class="text-[11px] tabular-nums text-gray-400">{{ en_digits((string) $inPersonDaysCount) }} أيام</span>
                @endif
            </div>
            <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                @foreach ($dayGroups as $group)
                    <span class="inline-flex min-w-[2rem] items-center justify-center rounded-lg bg-white px-2 py-1 text-[13px] font-semibold tabular-nums text-gray-900 ring-1 ring-[#c5d4e4]/80">
                        {{ en_digits($group) }}
                    </span>
                @endforeach
                @if (filled($monthLabel))
                    <span class="ms-0.5 text-[11px] font-medium text-gray-500">{{ $monthLabel }}</span>
                @endif
            </div>
        </div>

        <div
            role="listitem"
            class="flex items-center justify-between gap-2 rounded-xl bg-white px-2.5 py-2 ring-1 ring-gray-200/90"
        >
            <span class="text-[11px] font-semibold tracking-wide text-gray-500">عن بعد</span>
            <span class="text-[13px] font-medium text-gray-700">باقي أيام الفترة</span>
        </div>
    </div>
</div>
