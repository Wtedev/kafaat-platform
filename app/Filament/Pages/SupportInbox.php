<?php

namespace App\Filament\Pages;

use App\Enums\SupportMessageSenderType;
use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\SupportTicketInternalNote;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\Support\SupportTicketService;
use App\Services\Support\SupportUnreadService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

class SupportInbox extends Page
{
    protected static ?string $slug = 'support-inbox';

    protected static ?string $navigationLabel = 'صندوق الدعم';

    protected static ?string $title = 'صندوق الدعم';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'الأمان والامتثال';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.pages.support-inbox';

    protected Width|string|null $maxContentWidth = Width::Full;

    #[Url(as: 'selected', except: null)]
    public ?int $selectedTicketId = null;

    /** @var 'all'|'unread'|'open'|'in_progress'|'closed' */
    public string $filterTab = 'all';

    public string $search = '';

    /** @var 'list'|'thread'|'meta' */
    public string $mobileScreen = 'list';

    public string $replyBody = '';

    public string $noteBody = '';

    public bool $sending = false;

    public ?string $mailStatus = null;

    public bool $closeConfirmOpen = false;

    public string $closeReason = '';

    public int $threadRevision = 0;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && (
            $user->isAdmin()
            || $user->can('support_tickets.view')
            || $user->can('support_tickets.reply')
        );
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canAccess()) {
            return null;
        }

        $user = auth()->user();
        if (! $user instanceof User) {
            return null;
        }

        $count = SupportTicket::query()
            ->whereRaw(
                '(SELECT COUNT(*) FROM support_ticket_messages m WHERE m.support_ticket_id = support_tickets.id AND m.sender_type = ? AND m.id > COALESCE((SELECT c.last_read_message_id FROM support_ticket_read_cursors c WHERE c.support_ticket_id = support_tickets.id AND c.user_id = ?), 0)) > 0',
                [SupportMessageSenderType::Beneficiary->value, $user->id]
            )
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public function getHeading(): string|Htmlable|null
    {
        return '';
    }

    public function getTitle(): string|Htmlable
    {
        return 'صندوق الدعم';
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        if ($this->selectedTicketId !== null) {
            $this->selectTicket($this->selectedTicketId, preserveMobile: true);
        }
    }

    public function selectTicket(int $ticketId, bool $preserveMobile = false): void
    {
        abort_unless(static::canAccess(), 403);

        $ticket = SupportTicket::query()->find($ticketId);
        if ($ticket === null) {
            $this->selectedTicketId = null;
            $this->mailStatus = null;
            $this->closeConfirmOpen = false;

            return;
        }

        $this->selectedTicketId = $ticket->id;
        $this->replyBody = '';
        $this->noteBody = '';
        $this->mailStatus = null;
        $this->closeConfirmOpen = false;
        $this->closeReason = '';
        $this->threadRevision++;

        if (! $preserveMobile) {
            $this->mobileScreen = 'thread';
        } elseif ($this->mobileScreen === 'list') {
            $this->mobileScreen = 'thread';
        }

        $user = auth()->user();
        if ($user instanceof User) {
            app(SupportUnreadService::class)->markTicketRead($ticket, $user, 'staff');
        }
    }

    public function showList(): void
    {
        $this->mobileScreen = 'list';
    }

    public function showThread(): void
    {
        $this->mobileScreen = 'thread';
    }

    public function showMeta(): void
    {
        if ($this->selectedTicketId === null) {
            return;
        }
        $this->mobileScreen = 'meta';
    }

    public function setFilterTab(string $tab): void
    {
        if (! in_array($tab, ['all', 'unread', 'open', 'in_progress', 'closed'], true)) {
            return;
        }

        $this->filterTab = $tab;
    }

    public function refreshList(): void
    {
        // Poll re-render: list + counters refresh while selection is preserved.
    }

    public function refreshThread(): void
    {
        if ($this->selectedTicketId === null) {
            return;
        }

        $ticket = SupportTicket::query()->find($this->selectedTicketId);
        $user = auth()->user();
        if ($ticket !== null && $user instanceof User) {
            app(SupportUnreadService::class)->markTicketRead($ticket, $user, 'staff');
        }

        $this->threadRevision++;
    }

    public function sendReply(): void
    {
        if ($this->sending) {
            return;
        }

        abort_unless($this->canReply(), 403);

        $ticket = $this->selectedTicket();
        $actor = auth()->user();
        if ($ticket === null || ! $actor instanceof User) {
            return;
        }

        $body = trim($this->replyBody);
        if ($body === '') {
            Notification::make()->warning()->title('نص الرد مطلوب')->send();

            return;
        }

        $this->sending = true;
        $this->mailStatus = null;

        try {
            app(SupportTicketService::class)->addSupportReply($ticket, $actor, [
                'body' => $body,
            ]);

            $this->replyBody = '';
            $this->mailStatus = 'queued';
            $this->threadRevision++;

            Notification::make()->success()->title('تم إرسال الرد')->send();
        } catch (ValidationException $e) {
            Notification::make()
                ->danger()
                ->title('تعذّر إرسال الرد')
                ->body(collect($e->errors())->flatten()->first() ?? '')
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('تعذّر إرسال الرد')
                ->body('حدث خطأ غير متوقع. حاول مرة أخرى.')
                ->send();
        } finally {
            $this->sending = false;
        }
    }

    public function setInProgress(): void
    {
        abort_unless($this->canManageStatus(), 403);

        $ticket = $this->selectedTicket();
        $actor = auth()->user();
        if ($ticket === null || ! $actor instanceof User) {
            return;
        }

        $current = SupportTicketStatus::coerce($ticket->status);
        if ($current === SupportTicketStatus::InProgress) {
            return;
        }

        if (! $current->canTransitionTo(SupportTicketStatus::InProgress)) {
            Notification::make()->warning()->title('لا يمكن تغيير الحالة')->send();

            return;
        }

        try {
            app(SupportTicketService::class)->changeStatus(
                $ticket,
                $actor,
                SupportTicketStatus::InProgress,
                null,
                'set_in_progress',
            );
            $this->threadRevision++;
            Notification::make()->success()->title('الحالة: قيد المعالجة')->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('تعذّر تغيير الحالة')->send();
        }
    }

    public function openCloseConfirm(): void
    {
        abort_unless($this->canManageStatus(), 403);
        $ticket = $this->selectedTicket();
        if ($ticket === null || ! SupportTicketStatus::coerce($ticket->status)->allowsChat()) {
            return;
        }
        $this->closeConfirmOpen = true;
        $this->closeReason = '';
    }

    public function cancelCloseConfirm(): void
    {
        $this->closeConfirmOpen = false;
        $this->closeReason = '';
    }

    public function closeTicket(): void
    {
        abort_unless($this->canManageStatus(), 403);

        $ticket = $this->selectedTicket();
        $actor = auth()->user();
        if ($ticket === null || ! $actor instanceof User) {
            return;
        }

        $reason = trim($this->closeReason);
        if ($reason === '') {
            Notification::make()->warning()->title('سبب الإغلاق مطلوب')->send();

            return;
        }

        try {
            app(SupportTicketService::class)->changeStatus(
                $ticket,
                $actor,
                SupportTicketStatus::Closed,
                $reason,
                'close',
            );
            $this->closeConfirmOpen = false;
            $this->closeReason = '';
            $this->threadRevision++;
            Notification::make()->success()->title('أُغلقت التذكرة')->send();
        } catch (ValidationException $e) {
            Notification::make()
                ->danger()
                ->title('تعذّر الإغلاق')
                ->body(collect($e->errors())->flatten()->first() ?? '')
                ->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('تعذّر الإغلاق')->send();
        }
    }

    public function addNote(): void
    {
        abort_unless($this->canInternalNotes(), 403);

        $ticket = $this->selectedTicket();
        $actor = auth()->user();
        if ($ticket === null || ! $actor instanceof User) {
            return;
        }

        $body = trim($this->noteBody);
        if ($body === '') {
            Notification::make()->warning()->title('نص الملاحظة مطلوب')->send();

            return;
        }

        try {
            app(SupportTicketService::class)->addInternalNote($ticket, $actor, $body);
            $this->noteBody = '';
            Notification::make()->success()->title('أُضيفت الملاحظة الداخلية')->send();
        } catch (ValidationException $e) {
            Notification::make()
                ->danger()
                ->title('تعذّر حفظ الملاحظة')
                ->body(collect($e->errors())->flatten()->first() ?? '')
                ->send();
        }
    }

    public function copyTicketNumber(): void
    {
        $ticket = $this->selectedTicket();
        if ($ticket === null) {
            return;
        }
        $this->dispatchCopy($ticket->displayNumber());
    }

    public function copyEmail(): void
    {
        $ticket = $this->selectedTicket();
        if ($ticket === null || blank($ticket->email)) {
            return;
        }
        $this->dispatchCopy((string) $ticket->email);
    }

    public function copyPageUrl(): void
    {
        $ticket = $this->selectedTicket();
        if ($ticket === null || blank($ticket->page_url)) {
            return;
        }
        $this->dispatchCopy((string) $ticket->page_url);
    }

    public function canReply(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($user->isAdmin() || $user->can('support_tickets.reply'));
    }

    public function canManageStatus(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($user->isAdmin() || $user->can('support_tickets.manage_status'));
    }

    public function canInternalNotes(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($user->isAdmin() || $user->can('support_tickets.internal_notes'));
    }

    /**
     * @return Collection<int, SupportTicket>
     */
    public function tickets(): Collection
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return collect();
        }

        $query = $this->baseTicketQuery($user);
        $this->applyFilterTab($query, $user);
        $this->applySearch($query);

        return $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();
    }

    /**
     * @return array{all: int, unread: int, open: int, in_progress: int, closed: int}
     */
    public function tabCounts(): array
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return ['all' => 0, 'unread' => 0, 'open' => 0, 'in_progress' => 0, 'closed' => 0];
        }

        $make = function () use ($user): Builder {
            $q = $this->baseTicketQuery($user);
            $this->applySearch($q);

            return $q;
        };

        return [
            'all' => (clone $make())->count(),
            'unread' => $this->applyUnreadFilter(clone $make(), $user)->count(),
            'open' => (clone $make())->where('status', SupportTicketStatus::Open->value)->count(),
            'in_progress' => (clone $make())->where('status', SupportTicketStatus::InProgress->value)->count(),
            'closed' => (clone $make())->whereIn('status', [
                SupportTicketStatus::Closed->value,
                SupportTicketStatus::Resolved->value,
            ])->count(),
        ];
    }

    public function selectedTicket(): ?SupportTicket
    {
        if ($this->selectedTicketId === null) {
            return null;
        }

        return SupportTicket::query()
            ->with(['assignee:id,name', 'relatedProgram:id,title', 'user:id,name,email'])
            ->find($this->selectedTicketId);
    }

    /**
     * @return Collection<int, SupportTicketMessage>
     */
    public function messages(): Collection
    {
        if ($this->selectedTicketId === null) {
            return collect();
        }

        return SupportTicketMessage::query()
            ->where('support_ticket_id', $this->selectedTicketId)
            ->where('source', '!=', 'legacy_admin_notes_marker')
            ->with(['author:id,name'])
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, SupportTicketInternalNote>
     */
    public function internalNotes(): Collection
    {
        if ($this->selectedTicketId === null || ! $this->canInternalNotes()) {
            return collect();
        }

        return SupportTicketInternalNote::query()
            ->where('support_ticket_id', $this->selectedTicketId)
            ->with(['author:id,name'])
            ->orderByDesc('id')
            ->get();
    }

    private function dispatchCopy(string $value): void
    {
        $this->dispatch('support-inbox-copy', value: $value);

        Notification::make()
            ->success()
            ->title('تم النسخ')
            ->send();
    }

    private function baseTicketQuery(User $staff): Builder
    {
        $query = SupportTicket::query()->with(['assignee:id,name', 'latestMessage']);
        app(SupportUnreadService::class)->attachUnreadBeneficiarySelect($query, $staff);

        return $query;
    }

    private function applySearch(Builder $query): void
    {
        $search = trim($this->search);
        if ($search === '') {
            return;
        }

        $like = '%'.$search.'%';
        $query->where(function (Builder $q) use ($like): void {
            $q->where('ticket_number', 'like', $like)
                ->orWhere('subject', 'like', $like)
                ->orWhere('name', 'like', $like)
                ->orWhere('email', 'like', $like);
        });
    }

    private function applyFilterTab(Builder $query, User $staff): void
    {
        match ($this->filterTab) {
            'unread' => $this->applyUnreadFilter($query, $staff),
            'open' => $query->where('status', SupportTicketStatus::Open->value),
            'in_progress' => $query->where('status', SupportTicketStatus::InProgress->value),
            'closed' => $query->whereIn('status', [
                SupportTicketStatus::Closed->value,
                SupportTicketStatus::Resolved->value,
            ]),
            default => $query,
        };
    }

    private function applyUnreadFilter(Builder $query, User $staff): Builder
    {
        return $query->whereRaw(
            '(SELECT COUNT(*) FROM support_ticket_messages m WHERE m.support_ticket_id = support_tickets.id AND m.sender_type = ? AND m.id > COALESCE((SELECT c.last_read_message_id FROM support_ticket_read_cursors c WHERE c.support_ticket_id = support_tickets.id AND c.user_id = ?), 0)) > 0',
            [SupportMessageSenderType::Beneficiary->value, $staff->id]
        );
    }
}
