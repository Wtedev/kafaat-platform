@props([
    'documents',
])

@php
    $categories = config('governance.general_assembly_minute_categories', []);
    $categoryOrder = config('governance.general_assembly_minute_category_order', []);
    $grouped = $documents->groupBy('description');
@endphp

@if ($documents->isEmpty())
<div class="py-20 text-center">
    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl" style="background:#e9eff6">
        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
    </div>
    <h3 class="mb-1 text-lg font-semibold" style="color:#374151">لا توجد محاضر منشورة حالياً</h3>
    <p class="text-sm" style="color:#9CA3AF">سيتم إضافة المحتوى قريباً.</p>
</div>
@else
<div class="space-y-8">
    @foreach ($categoryOrder as $categoryKey)
        @php $items = $grouped->get($categoryKey, collect())->sortBy('sort_order'); @endphp
        @if ($items->isEmpty())
            @continue
        @endif

        <section>
            <h3 class="mb-4 text-base font-bold text-right" style="color:#111827">
                {{ $categories[$categoryKey] ?? $categoryKey }}
            </h3>

            <div class="space-y-3">
                @foreach ($items as $doc)
                @php
                    $fileUrl = $doc->filePublicUrl();
                    $downloadName = \Illuminate\Support\Str::slug($doc->title, '-').'.pdf';
                    if ($downloadName === '.pdf') {
                        $downloadName = 'minutes-'.en_num($doc->id).'.pdf';
                    }
                @endphp
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm text-right">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <h4 class="text-sm font-bold leading-snug sm:text-base" style="color:#111827">{{ $doc->title }}</h4>
                            @if ($doc->document_date)
                            <p class="mt-1 text-xs" style="color:#9CA3AF">
                                {{ ar_date($doc->document_date, 'y') }}
                            </p>
                            @endif
                        </div>

                        @if ($fileUrl)
                        <a href="{{ $fileUrl }}"
                           download="{{ $downloadName }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition-all hover:-translate-y-0.5"
                           style="background:#335483; color:#fff">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            تحميل
                        </a>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
@endif
