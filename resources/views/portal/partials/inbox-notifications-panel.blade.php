@php
    $panelTitle = $panelTitle ?? null;
    $panelSubtitle = $panelSubtitle ?? null;
    $showViewAll = $showViewAll ?? false;
    $compact = $compact ?? false;
@endphp

<div class="npm overflow-hidden rounded-[1.35rem] border border-[#c5d4e4]/70 bg-white shadow-[0_16px_40px_-28px_rgba(51,84,131,0.35)]">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 bg-gradient-to-l from-[#e9eff6]/80 via-white to-white px-4 py-4 sm:px-5">
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

        <div class="flex flex-wrap items-center gap-2">
            @if ($showViewAll)
                <a href="{{ route('portal.notifications') }}" class="rounded-xl px-3 py-2 text-xs font-semibold text-[#335483] ring-1 ring-[#c5d4e4] transition hover:bg-[#e9eff6]">
                    عرض الكل
                </a>
            @endif
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('portal.notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="rounded-xl px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:opacity-95 sm:text-sm" style="background:#335483">
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
