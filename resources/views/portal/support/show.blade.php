@extends('layouts.portal')
@section('title', 'محادثة '.$ticket->displayNumber())

@section('content')
@php
    use App\Enums\SupportMessageSenderType;
@endphp

<div class="mb-4">
    <a href="{{ route('portal.support.index') }}" class="text-sm font-medium text-[#335483] hover:underline">← العودة للدعم الفني</a>
</div>

<header class="mb-5 rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="font-mono text-xs text-slate-400">{{ $ticket->displayNumber() }}</p>
            <h1 class="mt-1 text-xl font-bold text-gray-900">{{ $ticket->subject }}</h1>
            <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-500">
                <span>{{ $ticket->category?->label() ?? 'عام' }}</span>
                <span>{{ $ticket->status?->label() ?? '—' }}</span>
                <span>أُنشئت {{ optional($ticket->created_at)?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</span>
                @if ($ticket->assignee)
                    <span>المسؤول: {{ $ticket->assignee->name }}</span>
                @endif
            </div>
        </div>
        <span class="rounded-full px-3 py-1 text-xs font-bold {{ $ticket->status?->allowsBeneficiaryReply() ? 'bg-[#e9eff6] text-[#335483]' : 'bg-slate-100 text-slate-600' }}">
            {{ $ticket->status?->label() }}
        </span>
    </div>
    @unless ($canReply)
        <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
            هذه المحادثة {{ $ticket->status?->label() }}. يمكنك قراءة السجل، ولا يمكن إرسال رد جديد حتى يعيد فريق الدعم فتحها.
        </p>
    @endunless
</header>

<div class="mb-5 space-y-3" id="support-thread" data-support-thread data-ticket-id="{{ $ticket->id }}">
    @foreach ($messages as $message)
        @php
            $isMine = $message->sender_type === SupportMessageSenderType::Beneficiary;
            $isSystem = $message->sender_type === SupportMessageSenderType::System || $message->is_system;
        @endphp
        @if ($isSystem)
            <div class="mx-auto max-w-xl rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-center text-xs text-slate-600">
                <p>{{ $message->body }}</p>
                <time class="mt-1 block text-[10px] text-slate-400">{{ $message->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</time>
            </div>
        @else
            <div class="flex {{ $isMine ? 'justify-start' : 'justify-end' }}">
                <div class="max-w-[85%] rounded-2xl px-4 py-3 text-sm leading-relaxed shadow-sm sm:max-w-[70%] {{ $isMine ? 'bg-[#335483] text-white' : 'border border-slate-200 bg-white text-slate-800' }}">
                    <p class="mb-1 text-[10px] font-bold {{ $isMine ? 'text-white/70' : 'text-[#335483]' }}">
                        {{ $isMine ? 'أنت' : 'فريق الدعم' }}
                    </p>
                    <p class="whitespace-pre-wrap">{{ $message->body }}</p>
                    <time class="mt-2 block text-[10px] {{ $isMine ? 'text-white/60' : 'text-slate-400' }}">
                        {{ $message->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                    </time>
                </div>
            </div>
        @endif
    @endforeach
</div>

@if ($canReply)
<form method="POST" action="{{ route('portal.support.reply', $ticket) }}" class="sticky bottom-3 rounded-2xl border border-slate-200/80 bg-white p-3 shadow-lg sm:p-4" data-support-reply-form>
    @csrf
    <label class="block">
        <span class="sr-only">اكتب ردك</span>
        <textarea name="body" rows="3" required maxlength="4000" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" placeholder="اكتب ردك هنا…">{{ old('body') }}</textarea>
        @error('body')<p class="mt-1 text-xs text-brand-danger">{{ $message }}</p>@enderror
    </label>
    <div class="mt-2 flex justify-end">
        <button type="submit" class="rounded-xl px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-60" style="background:#335483" data-support-reply-submit>
            إرسال
        </button>
    </div>
</form>
@endif
@endsection

@push('scripts')
<script>
(function () {
    var form = document.querySelector('[data-support-reply-form]');
    if (!form) return;
    form.addEventListener('submit', function () {
        var btn = form.querySelector('[data-support-reply-submit]');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'جاري الإرسال…';
        }
    });
})();
</script>
@endpush
