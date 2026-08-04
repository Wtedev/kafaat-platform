@extends('layouts.portal')
@section('title', 'الدعم الفني')

@section('content')
@php
    use App\Enums\SupportTicketCategory;
    use App\Enums\SupportTicketStatus;
    use App\Enums\SupportMessageSenderType;
@endphp

<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">الدعم الفني</h1>
        <p class="mt-1 max-w-2xl text-sm text-slate-600">محادثات مستمرة مع فريق كفاءات لمتابعة طلباتك وردود الدعم.</p>
    </div>
    <a href="{{ route('portal.support.create') }}" class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#335483">
        محادثة جديدة
    </a>
</div>

@if (($stats['total'] ?? 0) > 0)
<div class="mb-5 grid grid-cols-3 gap-3">
    <div class="rounded-2xl border border-slate-200/80 bg-white px-3 py-3 text-center">
        <p class="text-lg font-bold text-[#335483]">{{ $stats['open'] }}</p>
        <p class="text-[11px] text-slate-500">مفتوحة</p>
    </div>
    <div class="rounded-2xl border border-slate-200/80 bg-white px-3 py-3 text-center">
        <p class="text-lg font-bold text-brand-danger">{{ $stats['unread'] }}</p>
        <p class="text-[11px] text-slate-500">ردود جديدة</p>
    </div>
    <div class="rounded-2xl border border-slate-200/80 bg-white px-3 py-3 text-center">
        <p class="text-lg font-bold text-slate-700">{{ $stats['total'] }}</p>
        <p class="text-[11px] text-slate-500">الكل</p>
    </div>
</div>
@endif

<form method="GET" action="{{ route('portal.support.index') }}" class="mb-4 flex flex-col gap-2 rounded-2xl border border-slate-200/80 bg-white p-3 sm:flex-row sm:items-center">
    <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="بحث في العنوان أو الرقم…" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm sm:flex-1" />
    <select name="status" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
        <option value="">كل الحالات</option>
        @foreach (SupportTicketStatus::options() as $value => $label)
            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <select name="category" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
        <option value="">كل التصنيفات</option>
        @foreach (SupportTicketCategory::options() as $value => $label)
            <option value="{{ $value }}" @selected($filters['category'] === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <label class="inline-flex items-center gap-2 whitespace-nowrap px-1 text-sm text-slate-600">
        <input type="checkbox" name="unread" value="1" @checked($filters['unread']) class="rounded border-slate-300 text-[#335483]" />
        رد جديد فقط
    </label>
    <button type="submit" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200">تصفية</button>
</form>

@if ($tickets->isEmpty())
<x-portal.empty-state
    title="لا توجد محادثات بعد"
    description="ابدأ محادثة مع فريق الدعم عند وجود مشكلة أو استفسار."
>
    <a href="{{ route('portal.support.create') }}" class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm" style="background:#335483">محادثة جديدة</a>
</x-portal.empty-state>
@else
<div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white">
    <ul class="divide-y divide-slate-100" role="list">
        @foreach ($tickets as $ticket)
        @php
            $unreadCount = (int) ($ticket->unread_count ?? 0);
            $hasUnread = $unreadCount > 0;
            $sender = $ticket->last_message_sender_type;
            $senderLabel = $sender instanceof SupportMessageSenderType
                ? ($sender === SupportMessageSenderType::Beneficiary ? 'أنت' : $sender->label())
                : '—';
            $status = $ticket->status;
        @endphp
        <li>
            <a href="{{ route('portal.support.show', $ticket) }}" class="flex flex-col gap-2 px-4 py-3.5 transition hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between {{ $hasUnread ? 'bg-[#e9eff6]/40' : '' }}">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono text-xs text-slate-400">{{ $ticket->displayNumber() }}</span>
                        <span class="truncate text-sm font-semibold text-gray-900">{{ $ticket->subject }}</span>
                        @if ($hasUnread)
                            <span class="inline-flex items-center rounded-full bg-brand-danger/10 px-2 py-0.5 text-[10px] font-bold text-brand-danger">رد جديد</span>
                        @endif
                    </div>
                    <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-slate-500">
                        <span>{{ $ticket->category?->label() ?? 'عام' }}</span>
                        <span>{{ $status?->label() ?? '—' }}</span>
                        <span>أُنشئت {{ optional($ticket->created_at)?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</span>
                        @if ($ticket->last_message_at)
                            <span>آخر رسالة {{ $ticket->last_message_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }} ({{ $senderLabel }})</span>
                        @endif
                        @if ($ticket->assignee)
                            <span>المسؤول: {{ $ticket->assignee->name }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    @if ($hasUnread)
                        <span class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-brand-danger px-1.5 text-[10px] font-bold text-white">{{ $unreadCount > 99 ? '+99' : $unreadCount }}</span>
                    @endif
                    <span class="text-xs font-medium text-[#335483]">فتح المحادثة</span>
                </div>
            </a>
        </li>
        @endforeach
    </ul>
</div>
<div class="mt-4">{{ $tickets->links() }}</div>
@endif
@endsection
