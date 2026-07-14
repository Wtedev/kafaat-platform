@php
    $panelTitle = $panelTitle ?? null;
    $panelSubtitle = $panelSubtitle ?? null;
    $showViewAll = $showViewAll ?? false;
    $compact = $compact ?? false;
@endphp

<div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
    <x-portal.card-header
        :title="$panelTitle ?? 'التنبيهات'"
        :subtitle="$panelSubtitle"
    >
        @if (! $panelSubtitle)
            <p class="mt-0.5 text-xs text-slate-500 sm:text-sm">
                غير مقروء:
                <span class="font-bold tabular-nums text-[#335483]">{{ en_num($unreadCount) }}</span>
            </p>
        @endif

        <x-slot:actions>
            @if ($showViewAll)
                <a href="{{ route('portal.notifications') }}" class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-medium text-[#335483] ring-1 ring-[#c5d4e4] transition hover:bg-white/70">
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
        </x-slot:actions>
    </x-portal.card-header>

    <div class="@if($compact) max-h-[28rem] overflow-y-auto @endif p-2.5 sm:p-3">
        @if ($items->isEmpty())
            <div class="rounded-2xl border border-dashed border-[#c5d4e4] bg-[#e9eff6]/40 px-4 py-8 text-center">
                <p class="text-sm font-semibold text-[#335483]">لا توجد تنبيهات</p>
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
