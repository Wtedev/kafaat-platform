@php
    /** @var \Illuminate\Support\Collection<int, array{occurred_at: \Illuminate\Support\Carbon, category: string, title: string, detail: string}> $entries */
@endphp

<div class="kafaat-user-technical-log overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
    @if ($entries->isEmpty())
        <div class="p-6 text-sm text-gray-500 dark:text-gray-400">
            لا توجد أحداث مسجّلة لهذا المستفيد بعد.
        </div>
    @else
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach ($entries as $entry)
                <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                {{ $entry['category'] }}
                            </span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $entry['title'] }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            {{ $entry['detail'] }}
                        </p>
                    </div>
                    <time class="shrink-0 text-xs text-gray-500 dark:text-gray-400" datetime="{{ $entry['occurred_at']->toIso8601String() }}">
                        {{ $entry['occurred_at']->timezone(config('app.timezone'))->translatedFormat('j F Y — H:i') }}
                    </time>
                </div>
            @endforeach
        </div>
    @endif
</div>
