@php
/** @var \App\Models\InboxNotification $n */
$inboxOpenUrl = \App\Filament\Support\InboxNotificationRecordActions::inboxOpenUrl(auth()->user(), $n);
$inboxOpenLabel = \App\Filament\Support\InboxNotificationRecordActions::inboxOpenLabel(auth()->user(), $n);
$canApproveProgram = \App\Filament\Support\InboxNotificationRecordActions::canApproveProgramRegistration($n);
$canRejectProgram = \App\Filament\Support\InboxNotificationRecordActions::canRejectProgramRegistration($n);
$canApprovePath = \App\Filament\Support\InboxNotificationRecordActions::canApprovePathRegistration($n);
$canRejectPath = \App\Filament\Support\InboxNotificationRecordActions::canRejectPathRegistration($n);
$canApproveVolunteer = \App\Filament\Support\InboxNotificationRecordActions::canApproveVolunteerRegistration($n);
$canRejectVolunteer = \App\Filament\Support\InboxNotificationRecordActions::canRejectVolunteerRegistration($n);
@endphp
<li class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm @if($n->read_at === null) ring-1 ring-sky-200/60 @endif">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0 flex-1 text-right">
            <div class="flex flex-wrap items-center justify-end gap-2">
                <span class="inline-flex rounded-lg bg-gray-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-gray-600 ring-1 ring-gray-200">
                    {{ $n->type->arabicLabel() }}
                </span>
                @if ($n->read_at === null)
                <span class="inline-flex rounded-lg bg-sky-50 px-2 py-0.5 text-[10px] font-bold text-sky-800 ring-1 ring-sky-200">جديد</span>
                @endif
            </div>
            <h2 class="mt-2 text-base font-bold text-gray-900">{{ $n->title }}</h2>
            @if ($n->message)
            <p class="mt-2 text-sm leading-relaxed text-gray-700 whitespace-pre-wrap">{{ $n->message }}</p>
            @endif
            @if ($n->sender)
            <p class="mt-2 text-xs text-gray-500">من: {{ $n->sender->name }}</p>
            @endif
            <time class="mt-2 block text-xs text-gray-400" datetime="{{ $n->created_at->toIso8601String() }}">{{ $n->created_at->translatedFormat('j F Y، H:i') }}</time>
        </div>
        <div class="flex w-full max-w-[16rem] shrink-0 flex-col items-stretch gap-2 sm:w-auto sm:items-end">
            @if ($inboxOpenUrl)
            <a href="{{ $inboxOpenUrl }}" @if(\App\Filament\Support\InboxNotificationRecordActions::publicUrl($n) === $inboxOpenUrl) target="_blank" rel="noopener noreferrer" @endif class="inline-flex justify-center rounded-lg px-3 py-1.5 text-center text-xs font-semibold text-gray-700 ring-1 ring-gray-200 transition hover:bg-gray-50 sm:justify-end">
                {{ $inboxOpenLabel }}
            </a>
            @endif

            @if ($canApproveProgram)
            <form method="POST" action="{{ route('portal.notifications.registration-action', $n) }}" class="w-full sm:w-auto" onsubmit="return confirm('تأكيد قبول التسجيل في البرنامج؟');">
                @csrf
                <input type="hidden" name="intent" value="approve_program">
                <button type="submit" class="w-full rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700 sm:w-auto">
                    قبول (برنامج)
                </button>
            </form>
            @endif
            @if ($canRejectProgram)
            <form method="POST" action="{{ route('portal.notifications.registration-action', $n) }}" class="flex flex-col gap-1">
                @csrf
                <input type="hidden" name="intent" value="reject_program">
                <textarea name="rejected_reason" rows="2" placeholder="سبب الرفض (اختياري)" class="w-full rounded-lg border border-gray-200 px-2 py-1 text-xs text-gray-800 placeholder:text-gray-400"></textarea>
                <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-red-700 ring-1 ring-red-200 transition hover:bg-red-50">
                    رفض (برنامج)
                </button>
            </form>
            @endif

            @if ($canApprovePath)
            <form method="POST" action="{{ route('portal.notifications.registration-action', $n) }}" class="w-full sm:w-auto" onsubmit="return confirm('تأكيد قبول التسجيل في المسار؟');">
                @csrf
                <input type="hidden" name="intent" value="approve_path">
                <button type="submit" class="w-full rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700 sm:w-auto">
                    قبول (مسار)
                </button>
            </form>
            @endif
            @if ($canRejectPath)
            <form method="POST" action="{{ route('portal.notifications.registration-action', $n) }}" class="flex flex-col gap-1">
                @csrf
                <input type="hidden" name="intent" value="reject_path">
                <textarea name="rejected_reason" rows="2" placeholder="سبب الرفض (اختياري)" class="w-full rounded-lg border border-gray-200 px-2 py-1 text-xs text-gray-800 placeholder:text-gray-400"></textarea>
                <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-red-700 ring-1 ring-red-200 transition hover:bg-red-50">
                    رفض (مسار)
                </button>
            </form>
            @endif

            @if ($canApproveVolunteer)
            <form method="POST" action="{{ route('portal.notifications.registration-action', $n) }}" class="w-full sm:w-auto" onsubmit="return confirm('تأكيد قبول التسجيل التطوعي؟');">
                @csrf
                <input type="hidden" name="intent" value="approve_volunteer">
                <button type="submit" class="w-full rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700 sm:w-auto">
                    قبول (تطوع)
                </button>
            </form>
            @endif
            @if ($canRejectVolunteer)
            <form method="POST" action="{{ route('portal.notifications.registration-action', $n) }}" class="flex flex-col gap-1">
                @csrf
                <input type="hidden" name="intent" value="reject_volunteer">
                <textarea name="rejected_reason" rows="2" placeholder="سبب الرفض (اختياري)" class="w-full rounded-lg border border-gray-200 px-2 py-1 text-xs text-gray-800 placeholder:text-gray-400"></textarea>
                <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-red-700 ring-1 ring-red-200 transition hover:bg-red-50">
                    رفض (تطوع)
                </button>
            </form>
            @endif

            @if ($n->read_at === null)
            <form method="POST" action="{{ route('portal.notifications.read', $n) }}" class="w-full sm:w-auto">
                @csrf
                <button type="submit" class="w-full rounded-lg px-3 py-1.5 text-xs font-semibold text-gray-700 ring-1 ring-gray-200 transition hover:bg-gray-50 sm:w-auto">
                    تعليم كمقروء
                </button>
            </form>
            @endif
        </div>
    </div>
</li>
