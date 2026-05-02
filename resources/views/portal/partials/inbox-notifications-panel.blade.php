<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-gray-600">
        غير مقروء: <span class="font-bold tabular-nums" style="color:#253B5B">{{ $unreadCount }}</span>
    </p>
    @if ($unreadCount > 0)
    <form method="POST" action="{{ route('portal.notifications.read-all') }}">
        @csrf
        <button type="submit" class="rounded-xl px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#253B5B">
            تعليم الكل كمقروء
        </button>
    </form>
    @endif
</div>

@if ($items->isEmpty())
<x-portal.empty-state
    title="لا توجد تنبيهات"
    description="عند حدوث نشاط يخص حسابك (تسجيل، شهادة، أخبار، وغيرها) سيظهر هنا."
/>
@else
<ul class="space-y-3">
    @foreach ($items as $n)
        @include('portal.partials.inbox-notification-item', ['n' => $n])
    @endforeach
</ul>
@endif
