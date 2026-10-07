<div class="mb-4 rounded-xl border border-gray-200 bg-white p-4 text-sm dark:border-gray-700 dark:bg-gray-900">
    @unless ($banner['ready'])
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="font-semibold text-amber-800 dark:text-amber-200">لم يُعتمد تصميم الشهادة بعد</p>
            <a href="{{ $banner['designUrl'] }}" class="inline-flex items-center rounded-lg bg-amber-500 px-4 py-2 font-semibold text-white">تعيين التصميم</a>
        </div>
    @else
        <div class="flex flex-wrap items-center gap-4">
            @if ($banner['thumbnail'])
                <img src="{{ $banner['thumbnail'] }}" alt="تصميم الشهادة" class="h-16 w-24 rounded border object-cover">
            @endif
            <div class="min-w-0 flex-1">
                <p class="font-medium text-gray-800 dark:text-gray-100">{{ $banner['summary'] }}</p>
                <p class="mt-1 text-gray-600 dark:text-gray-300">
                    {{ $banner['eligible'] }} مؤهل، {{ $banner['issued'] }} صادرة، {{ $banner['awaiting'] }} بانتظار البيانات
                </p>
            </div>
            <a href="{{ $banner['designUrl'] }}" class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 font-semibold">تعديل التصميم</a>
        </div>
    @endunless
</div>
