@php
/** @var \App\Models\InboxNotification $n */
use App\Enums\InboxNotificationType;
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
$compact = $compact ?? false;

$typeIconKind = match ($n->type) {
    InboxNotificationType::StaffNewProgramRegistration,
    InboxNotificationType::StaffNewPathRegistration,
    InboxNotificationType::StaffNewVolunteerRegistration => 'user-plus',
    InboxNotificationType::RegistrationApproved,
    InboxNotificationType::BeneficiaryApprovedProgramStarting => 'check',
    InboxNotificationType::RegistrationRejected => 'x',
    InboxNotificationType::CertificateIssued => 'certificate',
    InboxNotificationType::VolunteerOpportunityUpdated,
    InboxNotificationType::VolunteerOpportunityPublished,
    InboxNotificationType::StaffVolunteerOpportunityCreated => 'heart',
    InboxNotificationType::ProgramLaunched,
    InboxNotificationType::ProgramUpdated,
    InboxNotificationType::LearningPathLaunched,
    InboxNotificationType::StaffTrainingEntityCreated,
    InboxNotificationType::TrainingRunStarted,
    InboxNotificationType::TrainingRunEnded,
    InboxNotificationType::RegistrationWindowOpened,
    InboxNotificationType::RegistrationWindowClosed => 'book',
    InboxNotificationType::NewsPublished,
    InboxNotificationType::NewsStaffCopy => 'news',
    default => 'bell',
};
@endphp

<li @class([
    'npm-card group relative overflow-hidden rounded-2xl border bg-white transition',
    'border-[#335483]/25 bg-[#f7f9fc] shadow-sm ring-1 ring-[#335483]/5' => $isUnread,
    'border-gray-100 hover:border-gray-200 hover:bg-slate-50/60' => ! $isUnread,
])>
    @if ($isUnread)
        <span class="absolute inset-y-0 end-0 w-1 bg-[#335483]" aria-hidden="true"></span>
    @endif

    <div @class([
        'flex items-start gap-3',
        'p-3.5 sm:gap-3.5 sm:p-4' => $compact,
        'gap-4 p-4 sm:gap-5 sm:p-5' => ! $compact,
    ])>
        <span @class([
            'mt-0.5 flex shrink-0 items-center justify-center rounded-xl text-[#335483]',
            'h-9 w-9 bg-[#e9eff6]' => $compact,
            'h-11 w-11 bg-[#e9eff6]' => ! $compact,
        ]) aria-hidden="true">
            @switch($typeIconKind)
                @case('user-plus')
                    <svg @class(['h-4 w-4' => $compact, 'h-5 w-5' => ! $compact]) fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21v-2a4 4 0 00-4-4H9a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8zM16 8h4m-2-2v4"/></svg>
                    @break
                @case('check')
                    <svg @class(['h-4 w-4' => $compact, 'h-5 w-5' => ! $compact]) fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 13l4 4L19 7"/></svg>
                    @break
                @case('x')
                    <svg @class(['h-4 w-4' => $compact, 'h-5 w-5' => ! $compact]) fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12"/></svg>
                    @break
                @case('certificate')
                    <svg @class(['h-4 w-4' => $compact, 'h-5 w-5' => ! $compact]) fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    @break
                @case('heart')
                    <svg @class(['h-4 w-4' => $compact, 'h-5 w-5' => ! $compact]) fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                    @break
                @case('book')
                    <svg @class(['h-4 w-4' => $compact, 'h-5 w-5' => ! $compact]) fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                    @break
                @case('news')
                    <svg @class(['h-4 w-4' => $compact, 'h-5 w-5' => ! $compact]) fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V9a2 2 0 012-2h2a2 2 0 012 2v9a2 2 0 01-2 2h-2z"/></svg>
                    @break
                @default
                    <svg @class(['h-4 w-4' => $compact, 'h-5 w-5' => ! $compact]) fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            @endswitch
        </span>

        <div class="min-w-0 flex-1 text-right">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-[11px] font-semibold text-gray-500">{{ $n->type->arabicLabel() }}</span>
                    @if ($isUnread)
                        <span class="inline-flex items-center gap-1 rounded-md bg-[#e6f5f6] px-1.5 py-0.5 text-[10px] font-bold text-brand-secondary">
                            <span class="h-1.5 w-1.5 rounded-full bg-brand-secondary" aria-hidden="true"></span>
                            جديد
                        </span>
                    @endif
                </div>
                <time class="shrink-0 text-[11px] text-gray-400" datetime="{{ $n->created_at->toIso8601String() }}" title="{{ ar_date_time($n->created_at) }}">
                    {{ ar_diff_for_humans($n->created_at) }}
                </time>
            </div>

            <h2 @class(['font-bold leading-snug text-gray-900', 'mt-1 text-sm' => $compact, 'mt-1.5 text-base sm:text-[1.05rem]' => ! $compact])>{{ $n->title }}</h2>

            @if ($displayMessage)
                <p @class([
                    'leading-relaxed text-gray-600 whitespace-pre-wrap',
                    'mt-1 line-clamp-2 text-xs' => $compact,
                    'mt-2 text-sm' => ! $compact,
                ])>{{ $displayMessage }}</p>
            @endif

            @if ($n->sender && ! $compact)
                <p class="mt-2.5 text-xs text-gray-400">من: <span class="font-medium text-gray-500">{{ $n->sender->name }}</span></p>
            @endif

            @php
                $hasIconActions = $whatsappUrl || $inboxOpenUrl || $isUnread;
                $hasStaffActions = $canApproveProgram || $canRejectProgram
                    || $canApprovePath || $canRejectPath
                    || $canApproveVolunteer || $canRejectVolunteer;
            @endphp

            @if ($hasIconActions)
                {{-- ms-auto: pin icon row to visual left in RTL --}}
                <div @class([
                    'ms-auto flex items-center gap-1.5',
                    'mt-2.5' => $compact,
                    'mt-3' => ! $compact,
                ])>
                    @if ($whatsappUrl)
                        <a
                            href="{{ $whatsappUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-white shadow-sm transition hover:opacity-95"
                            style="background:#25D366"
                            aria-label="دخول مجموعة الواتساب"
                            title="دخول مجموعة الواتساب"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        </a>
                    @endif

                    @if ($inboxOpenUrl)
                        <a
                            href="{{ $inboxOpenUrl }}"
                            @if(\App\Filament\Support\InboxNotificationRecordActions::publicUrl($n) === $inboxOpenUrl) target="_blank" rel="noopener noreferrer" @endif
                            class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-[#335483] ring-1 ring-[#c5d4e4] transition hover:bg-[#e9eff6]"
                            aria-label="{{ $inboxOpenLabel }}"
                            title="{{ $inboxOpenLabel }}"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </a>
                    @endif

                    @if ($isUnread)
                        <form method="POST" action="{{ route('portal.notifications.read', $n) }}" class="inline-flex shrink-0">
                            @csrf
                            <button
                                type="submit"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#335483] transition hover:bg-[#e9eff6]"
                                aria-label="تعليم كمقروء"
                                title="تعليم كمقروء"
                            >
                                {{-- Single check for mark-as-read --}}
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 12.75l6 6 9-13.5"/>
                                </svg>
                            </button>
                        </form>
                    @endif
                </div>
            @endif

            @if ($hasStaffActions)
            <div @class([
                'flex flex-wrap items-center gap-2',
                'mt-2.5' => $compact,
                'mt-3' => ! $compact,
            ])>
                @if ($canApproveProgram)
                    <form method="POST" action="{{ route('portal.notifications.registration-action', $n) }}" onsubmit="return confirm('تأكيد قبول التسجيل في البرنامج؟');">
                        @csrf
                        <input type="hidden" name="intent" value="approve_program">
                        <button type="submit" class="rounded-lg bg-brand-secondary px-2.5 py-1.5 text-[11px] font-semibold text-white shadow-sm transition hover:opacity-95">قبول (برنامج)</button>
                    </form>
                @endif
                @if ($canRejectProgram)
                    <form method="POST" action="{{ route('portal.notifications.registration-action', $n) }}" class="w-full space-y-1.5 sm:w-auto">
                        @csrf
                        <input type="hidden" name="intent" value="reject_program">
                        <textarea name="rejected_reason" rows="2" placeholder="سبب الرفض (اختياري)" class="w-full rounded-xl border border-gray-200 px-2.5 py-1.5 text-xs text-gray-800 placeholder:text-gray-400"></textarea>
                        <button type="submit" class="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-brand-danger ring-1 ring-[#f5c4c0] transition hover:bg-[#fdeeed]">رفض (برنامج)</button>
                    </form>
                @endif

                @if ($canApprovePath)
                    <form method="POST" action="{{ route('portal.notifications.registration-action', $n) }}" onsubmit="return confirm('تأكيد قبول التسجيل في المسار؟');">
                        @csrf
                        <input type="hidden" name="intent" value="approve_path">
                        <button type="submit" class="rounded-lg bg-brand-secondary px-2.5 py-1.5 text-[11px] font-semibold text-white shadow-sm transition hover:opacity-95">قبول (مسار)</button>
                    </form>
                @endif
                @if ($canRejectPath)
                    <form method="POST" action="{{ route('portal.notifications.registration-action', $n) }}" class="w-full space-y-1.5 sm:w-auto">
                        @csrf
                        <input type="hidden" name="intent" value="reject_path">
                        <textarea name="rejected_reason" rows="2" placeholder="سبب الرفض (اختياري)" class="w-full rounded-xl border border-gray-200 px-2.5 py-1.5 text-xs text-gray-800 placeholder:text-gray-400"></textarea>
                        <button type="submit" class="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-brand-danger ring-1 ring-[#f5c4c0] transition hover:bg-[#fdeeed]">رفض (مسار)</button>
                    </form>
                @endif

                @if ($canApproveVolunteer)
                    <form method="POST" action="{{ route('portal.notifications.registration-action', $n) }}" onsubmit="return confirm('تأكيد قبول التسجيل التطوعي؟');">
                        @csrf
                        <input type="hidden" name="intent" value="approve_volunteer">
                        <button type="submit" class="rounded-lg bg-brand-secondary px-2.5 py-1.5 text-[11px] font-semibold text-white shadow-sm transition hover:opacity-95">قبول (تطوع)</button>
                    </form>
                @endif
                @if ($canRejectVolunteer)
                    <form method="POST" action="{{ route('portal.notifications.registration-action', $n) }}" class="w-full space-y-1.5 sm:w-auto">
                        @csrf
                        <input type="hidden" name="intent" value="reject_volunteer">
                        <textarea name="rejected_reason" rows="2" placeholder="سبب الرفض (اختياري)" class="w-full rounded-xl border border-gray-200 px-2.5 py-1.5 text-xs text-gray-800 placeholder:text-gray-400"></textarea>
                        <button type="submit" class="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-brand-danger ring-1 ring-[#f5c4c0] transition hover:bg-[#fdeeed]">رفض (تطوع)</button>
                    </form>
                @endif
            </div>
            @endif
        </div>
    </div>
</li>
