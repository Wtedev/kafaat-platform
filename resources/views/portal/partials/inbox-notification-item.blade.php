@php
/** @var \App\Models\InboxNotification $n */
use App\Support\InboxNotificationDisplay;

$display = InboxNotificationDisplay::present($n, auth()->user());
$displayMessage = $display['message'];
$whatsappUrl = $display['whatsapp_url'];

$inboxOpenUrl = \App\Filament\Support\InboxNotificationRecordActions::inboxOpenUrl(auth()->user(), $n);
$inboxOpenLabel = \App\Filament\Support\InboxNotificationRecordActions::inboxOpenLabel(auth()->user(), $n);
$canApproveProgram = \App\Filament\Support\InboxNotificationRecordActions::canApproveProgramRegistration($n);
$canRejectProgram = \App\Filament\Support\InboxNotificationRecordActions::canRejectProgramRegistration($n);
$canApprovePath = \App\Filament\Support\InboxNotificationRecordActions::canApprovePathRegistration($n);
$canRejectPath = \App\Filament\Support\InboxNotificationRecordActions::canRejectPathRegistration($n);
$canApproveVolunteer = \App\Filament\Support\InboxNotificationRecordActions::canApproveVolunteerRegistration($n);
$canRejectVolunteer = \App\Filament\Support\InboxNotificationRecordActions::canRejectVolunteerRegistration($n);
$isUnread = $n->read_at === null;
@endphp

<li @class([
    'npm-card group relative overflow-hidden rounded-2xl border bg-white transition',
    'border-[#335483]/20 shadow-[0_10px_30px_-18px_rgba(51,84,131,0.45)] ring-1 ring-[#335483]/10' => $isUnread,
    'border-gray-100 shadow-sm hover:border-gray-200' => ! $isUnread,
])>
    @if ($isUnread)
        <span class="absolute inset-y-0 end-0 w-1 bg-[#335483]" aria-hidden="true"></span>
    @endif

    <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-start sm:justify-between sm:gap-5 sm:p-5">
        <div class="min-w-0 flex-1 text-right">
            <div class="flex flex-wrap items-center justify-end gap-2">
                <span class="inline-flex rounded-lg bg-slate-50 px-2.5 py-1 text-[10px] font-bold text-slate-600 ring-1 ring-slate-200/80">
                    {{ $n->type->arabicLabel() }}
                </span>
                @if ($isUnread)
                    <span class="inline-flex items-center gap-1 rounded-lg bg-[#e6f5f6] px-2.5 py-1 text-[10px] font-bold text-brand-secondary ring-1 ring-[#b8e0e2]">
                        <span class="h-1.5 w-1.5 rounded-full bg-brand-secondary" aria-hidden="true"></span>
                        جديد
                    </span>
                @endif
            </div>

            <h2 class="mt-2.5 text-base font-bold leading-snug text-gray-900 sm:text-[1.05rem]">{{ $n->title }}</h2>

            @if ($displayMessage)
                <p class="mt-2 text-sm leading-relaxed text-gray-600 whitespace-pre-wrap">{{ $displayMessage }}</p>
            @endif

            @if ($whatsappUrl)
                <div class="mt-3">
                    <a
                        href="{{ $whatsappUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                        style="background:#25D366"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        دخول مجموعة الواتساب
                    </a>
                </div>
            @endif

            <div class="mt-3 flex flex-wrap items-center justify-end gap-x-3 gap-y-1 text-xs text-gray-400">
                @if ($n->sender)
                    <span>من: <span class="font-medium text-gray-500">{{ $n->sender->name }}</span></span>
                @endif
                <time datetime="{{ $n->created_at->toIso8601String() }}">{{ ar_date_time($n->created_at) }}</time>
            </div>
        </div>

        <div class="flex w-full shrink-0 flex-col gap-2 sm:w-40 sm:items-stretch">
            @if ($inboxOpenUrl)
                <a href="{{ $inboxOpenUrl }}" @if(\App\Filament\Support\InboxNotificationRecordActions::publicUrl($n) === $inboxOpenUrl) target="_blank" rel="noopener noreferrer" @endif class="inline-flex justify-center rounded-xl px-3 py-2 text-center text-xs font-semibold text-[#335483] ring-1 ring-[#c5d4e4] transition hover:bg-[#e9eff6]">
                    {{ $inboxOpenLabel }}
                </a>
            @endif

            @if ($canApproveProgram)
                <form method="POST" action="{{ route('portal.notifications.registration-action', $n) }}" onsubmit="return confirm('تأكيد قبول التسجيل في البرنامج؟');">
                    @csrf
                    <input type="hidden" name="intent" value="approve_program">
                    <button type="submit" class="w-full rounded-xl bg-brand-secondary px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:opacity-95">قبول (برنامج)</button>
                </form>
            @endif
            @if ($canRejectProgram)
                <form method="POST" action="{{ route('portal.notifications.registration-action', $n) }}" class="space-y-1.5">
                    @csrf
                    <input type="hidden" name="intent" value="reject_program">
                    <textarea name="rejected_reason" rows="2" placeholder="سبب الرفض (اختياري)" class="w-full rounded-xl border border-gray-200 px-2.5 py-1.5 text-xs text-gray-800 placeholder:text-gray-400"></textarea>
                    <button type="submit" class="w-full rounded-xl px-3 py-2 text-xs font-semibold text-brand-danger ring-1 ring-[#f5c4c0] transition hover:bg-[#fdeeed]">رفض (برنامج)</button>
                </form>
            @endif

            @if ($canApprovePath)
                <form method="POST" action="{{ route('portal.notifications.registration-action', $n) }}" onsubmit="return confirm('تأكيد قبول التسجيل في المسار؟');">
                    @csrf
                    <input type="hidden" name="intent" value="approve_path">
                    <button type="submit" class="w-full rounded-xl bg-brand-secondary px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:opacity-95">قبول (مسار)</button>
                </form>
            @endif
            @if ($canRejectPath)
                <form method="POST" action="{{ route('portal.notifications.registration-action', $n) }}" class="space-y-1.5">
                    @csrf
                    <input type="hidden" name="intent" value="reject_path">
                    <textarea name="rejected_reason" rows="2" placeholder="سبب الرفض (اختياري)" class="w-full rounded-xl border border-gray-200 px-2.5 py-1.5 text-xs text-gray-800 placeholder:text-gray-400"></textarea>
                    <button type="submit" class="w-full rounded-xl px-3 py-2 text-xs font-semibold text-brand-danger ring-1 ring-[#f5c4c0] transition hover:bg-[#fdeeed]">رفض (مسار)</button>
                </form>
            @endif

            @if ($canApproveVolunteer)
                <form method="POST" action="{{ route('portal.notifications.registration-action', $n) }}" onsubmit="return confirm('تأكيد قبول التسجيل التطوعي؟');">
                    @csrf
                    <input type="hidden" name="intent" value="approve_volunteer">
                    <button type="submit" class="w-full rounded-xl bg-brand-secondary px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:opacity-95">قبول (تطوع)</button>
                </form>
            @endif
            @if ($canRejectVolunteer)
                <form method="POST" action="{{ route('portal.notifications.registration-action', $n) }}" class="space-y-1.5">
                    @csrf
                    <input type="hidden" name="intent" value="reject_volunteer">
                    <textarea name="rejected_reason" rows="2" placeholder="سبب الرفض (اختياري)" class="w-full rounded-xl border border-gray-200 px-2.5 py-1.5 text-xs text-gray-800 placeholder:text-gray-400"></textarea>
                    <button type="submit" class="w-full rounded-xl px-3 py-2 text-xs font-semibold text-brand-danger ring-1 ring-[#f5c4c0] transition hover:bg-[#fdeeed]">رفض (تطوع)</button>
                </form>
            @endif

            @if ($isUnread)
                <form method="POST" action="{{ route('portal.notifications.read', $n) }}">
                    @csrf
                    <button type="submit" class="w-full rounded-xl px-3 py-2 text-xs font-semibold text-gray-600 ring-1 ring-gray-200 transition hover:bg-gray-50">تعليم كمقروء</button>
                </form>
            @endif
        </div>
    </div>
</li>
