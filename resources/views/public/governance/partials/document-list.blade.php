@props([
    'documents',
    'categoriesKey' => 'governance.survey_categories',
    'categoryOrderKey' => 'governance.survey_category_order',
    'emptyTitle' => 'لا توجد استطلاعات منشورة حالياً',
    'downloadPrefix' => 'survey',
])

@php
    $categories = config($categoriesKey, []);
    $categoryOrder = config($categoryOrderKey, []);
    $grouped = $documents->groupBy('description');
@endphp

@if ($documents->isEmpty())
<div class="py-16 text-center">
    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl" style="background:#e9eff6">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
    </div>
    <h3 class="mb-1 text-base font-semibold">{{ $emptyTitle }}</h3>
    <p class="text-xs" style="color:#9CA3AF">سيتم إضافة المحتوى قريباً.</p>
</div>
@else
<div class="space-y-5">
    @foreach ($categoryOrder as $categoryKey)
        @php $items = $grouped->get($categoryKey, collect())->sortBy('sort_order'); @endphp
        @if ($items->isEmpty())
            @continue
        @endif

        <section>
            <h3 class="mb-2.5 text-sm font-bold text-right">
                {{ $categories[$categoryKey] ?? $categoryKey }}
            </h3>

            <div class="space-y-2">
                @foreach ($items as $doc)
                @php
                    $fileUrl = $doc->filePublicUrl();
                    $downloadName = \Illuminate\Support\Str::slug($doc->title, '-').'.pdf';
                    if ($downloadName === '.pdf') {
                        $downloadName = $downloadPrefix.'-'.en_num($doc->id).'.pdf';
                    }
                @endphp
                <div class="rounded-xl border border-gray-100 bg-white px-3.5 py-2.5 shadow-sm text-right">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <h4 class="text-sm font-semibold leading-snug" style="color:var(--brand-body)">{{ $doc->title }}</h4>
                            @if ($doc->document_date)
                            <p class="mt-0.5 text-[11px]" style="color:#9CA3AF">
                                {{ ar_date($doc->document_date, 'y') }}
                            </p>
                            @endif
                        </div>

                        @if ($fileUrl)
                        <a href="{{ $fileUrl }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all hover:-translate-y-0.5"
                           style="background:#335483; color:#fff">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            عرض
                        </a>
                        @else
                        <span class="shrink-0 text-[11px]" style="color:#9CA3AF">—</span>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
@endif
