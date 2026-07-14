@php
    $panelTitle = $panelTitle ?? null;
    $panelSubtitle = $panelSubtitle ?? null;
    $showViewAll = $showViewAll ?? false;
    $compact = $compact ?? false;
@endphp

<div class="npm overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 bg-white px-4 py-4 sm:px-5">
        <div class="text-right">
            @if ($panelTitle)
                <h2 class="text-base font-bold text-gray-900 sm:text-lg">{{ $panelTitle }}</h2>
            @else
                <h2 class="text-base font-bold text-gray-900 sm:text-lg">التنبيهات</h2>
            @endif
            @if ($panelSubtitle)
                <p class="mt-0.5 text-xs text-gray-500 sm:text-sm">{{ $panelSubtitle }}</p>
            @else
                <p class="mt-0.5 text-xs text-gray-500 sm:text-sm">
                    غير مقروء:
                    <span class="font-bold tabular-nums text-[#335483]">{{ en_num($unreadCount) }}</span>
                </p>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-1.5">
            @if ($showViewAll)
                <a href="{{ route('portal.notifications') }}" class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-medium text-[#335483] ring-1 ring-[#c5d4e4] transition hover:bg-[#e9eff6]">
                    عرض الكل
                </a>
            @endif
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('portal.notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-medium text-white shadow-sm transition hover:opacity-95" style="background:#335483">
                        تعليم الكل كمقروء
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="@if($compact) max-h-[28rem] overflow-y-auto @endif p-2.5 sm:p-3">
        @if ($items->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/70 px-4 py-8 text-center">
                <p class="text-sm font-semibold text-gray-700">لا توجد تنبيهات</p>
                <p class="mt-1 text-xs text-gray-500">عند حدوث نشاط يخص حسابك سيظهر هنا.</p>
            </div>
        @else
            <ul class="space-y-2" role="list">
                @foreach ($items as $n)
                    @include('portal.partials.inbox-notification-item', ['n' => $n, 'compact' => $compact])
                @endforeach
            </ul>
        @endif
    </div>
</div>
