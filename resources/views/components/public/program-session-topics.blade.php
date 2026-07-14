@props([
    'topics' => null,
    'enabled' => true,
])

@php
    $items = collect(is_array($topics) ? $topics : [])
        ->map(static function (mixed $row): ?array {
            if (! is_array($row)) {
                return null;
            }

            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                return null;
            }

            return [
                'title' => $title,
                'facilitators' => trim((string) ($row['facilitators'] ?? '')),
            ];
        })
        ->filter()
        ->values();
@endphp

@if ($enabled && $items->isNotEmpty())
<section {{ $attributes->class(['program-session-topics']) }} aria-labelledby="program-session-topics-heading">
    <div class="mb-5 flex items-start gap-3">
        <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-[#335483]/10 text-[#335483]" aria-hidden="true">
            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
        </span>
        <div class="min-w-0">
            <h3 id="program-session-topics-heading" class="text-base font-semibold tracking-tight text-[#335483] sm:text-lg">
                محاور البرنامج
            </h3>
            <p class="mt-1 text-sm leading-relaxed text-gray-500">
                المحاور والمسؤولون عن تقديم البرنامج.
            </p>
        </div>
    </div>

    <ol class="space-y-3 sm:space-y-3.5">
        @foreach ($items as $index => $topic)
            <li class="flex gap-3 rounded-2xl bg-[#F7FAFC] p-3.5 ring-1 ring-[#c5d4e4]/60 sm:gap-4 sm:p-4">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-[#335483] text-sm font-semibold tabular-nums text-white shadow-sm sm:size-10 sm:text-base" aria-hidden="true">
                    {{ $index + 1 }}
                </span>
                <div class="min-w-0 flex-1 pt-0.5">
                    <p class="text-[15px] font-semibold leading-7 text-gray-900 sm:text-base">
                        {{ $topic['title'] }}
                    </p>
                    @if ($topic['facilitators'] !== '')
                        <p class="mt-1.5 text-sm leading-6 text-gray-600">
                            <span class="font-medium text-[#335483]">المسؤولون / المدربون</span>
                            <span class="text-gray-400"> · </span>
                            <span>{{ $topic['facilitators'] }}</span>
                        </p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</section>
@endif
