@php
    $panelTitle = $panelTitle ?? null;
    $panelSubtitle = $panelSubtitle ?? null;
    $showViewAll = $showViewAll ?? false;
    $compact = $compact ?? false;
@endphp

<div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-[#c5d4e4]/70 bg-[#e9eff6] px-4 py-4 sm:px-5">
        <div class="text-right">
            @if ($panelTitle)
                <h2 class="text-base font-bold text-[#335483] sm:text-lg">{{ $panelTitle }}</h2>
            @else
                <h2 class="text-base font-bold text-[#335483] sm:text-lg">التنبيهات</h2>
            @endif
            @if ($panelSubtitle)
                <p class="mt-0.5 text-xs text-slate-500 sm:text-sm">{{ $panelSubtitle }}</p>
            @else
                <p class="mt-0.5 text-xs text-slate-500 sm:text-sm">
                    غير مقروء:
                    <span class="font-bold tabular-nums text-[#335483]">{{ en_num($unreadCount) }}</span>
                </p>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-1.5">
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('portal.notifications.read-all') }}">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#335483] transition hover:bg-white/70"
                        aria-label="تعليم الكل كمقروء"
                        title="تعليم الكل كمقروء"
                    >
                        {{-- WhatsApp-style double check --}}
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1.5 12.5l4 4L14.5 7"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7.5 12.5l4 4L20.5 7"/>
                        </svg>
                    </button>
                </form>
            @endif
            @if ($showViewAll)
                <a href="{{ route('portal.notifications') }}" class="inline-flex items-center gap-0.5 text-xs font-semibold text-[#335483] transition hover:opacity-80">
                    عرض الكل
                    <span aria-hidden="true">&gt;</span>
                </a>
            @endif
        </div>
    </div>

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
