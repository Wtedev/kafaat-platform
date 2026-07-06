@props([
    'years',
])

@if ($years->isEmpty())
<div class="py-20 text-center">
    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl" style="background:#e9eff6">
        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
    </div>
    <h3 class="mb-1 text-lg font-semibold" style="color:#374151">لا توجد قرارات استثمارية منشورة حالياً</h3>
    <p class="text-sm" style="color:#9CA3AF">سيتم إضافة المحتوى قريباً.</p>
</div>
@else
<div class="space-y-4">
    @foreach ($years as $yearRecord)
    @php
        $items = $yearRecord->activeItems;
        $fileUrl = $yearRecord->filePublicUrl();
        $downloadName = 'قرارات-استثمار-'.en_num($yearRecord->year).'.pdf';
        $isOpen = $loop->first;
    @endphp
    <details class="inv-year-card group overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm" {{ $isOpen ? 'open' : '' }}>
        <summary class="flex cursor-pointer list-none items-center gap-4 px-5 py-4 text-right transition-colors hover:bg-[#F8FAFC] [&::-webkit-details-marker]:hidden">
            <div class="flex min-w-0 flex-1 items-center gap-3">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-sm font-bold" style="background:#e9eff6; color:#335483">
                    {{ en_num($yearRecord->year) }}
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="text-base font-bold leading-snug" style="color:#111827">{{ $yearRecord->title }}</h3>
                    <p class="mt-0.5 text-xs" style="color:#9CA3AF">
                        @if ($items->isNotEmpty())
                            {{ en_num($items->count()) }} {{ $items->count() === 1 ? 'قرار' : 'قرارات' }}
                        @elseif ($yearRecord->empty_message)
                            لا توجد قرارات
                        @else
                            —
                        @endif
                    </p>
                </div>
            </div>
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg transition-transform group-open:rotate-180" style="background:#F3F4F6; color:#6B7280">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </span>
        </summary>

        <div class="border-t border-gray-100 px-5 py-5">
            @if ($items->isNotEmpty())
            <ol class="space-y-3">
                @foreach ($items as $item)
                <li class="flex gap-3 text-right">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-xs font-bold" style="background:#335483; color:#fff">
                        {{ en_num($loop->iteration) }}
                    </span>
                    <p class="flex-1 pt-0.5 text-sm leading-relaxed" style="color:#374151">{{ $item->content }}</p>
                </li>
                @endforeach
            </ol>
            @elseif ($yearRecord->empty_message)
            <div class="flex items-start gap-3 rounded-xl px-4 py-3 text-right" style="background:#F8FAFC">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="#9CA3AF"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-sm leading-relaxed" style="color:#6B7280">{{ $yearRecord->empty_message }}</p>
            </div>
            @endif

            @if ($fileUrl)
            <div class="mt-5 flex items-center justify-between gap-4 rounded-xl border border-gray-100 px-4 py-3.5 text-right" style="background:#F8FAFC">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg" style="background:#e9eff6; color:#335483">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold" style="color:#111827">مرفق PDF</p>
                        <p class="truncate text-xs" style="color:#9CA3AF">{{ $downloadName }}</p>
                    </div>
                </div>
                <a href="{{ $fileUrl }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="inline-flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition-all hover:-translate-y-0.5"
                   style="background:#335483; color:#fff">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    عرض PDF
                </a>
            </div>
            @endif
        </div>
    </details>
    @endforeach
</div>
@endif
