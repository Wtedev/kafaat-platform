<?php

namespace App\Services\Support;

use App\Enums\SupportMessageSenderType;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Enums\UserActivityAction;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\SupportTicketStatusEvent;
use App\Models\User;
use App\Services\UserActivityLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class SupportTicketService
{
    /**
     * Max attempts when a concurrent create races on ticket_number uniqueness.
     * Unique index is the hard guarantee; retry avoids failing the user request.
     */
    private const TICKET_NUMBER_MAX_ATTEMPTS = 8;

    /**
     * Test-only seam to force a colliding first candidate (see SupportConversationHubTest).
     *
     * @var (\Closure(): string)|null
     */
    private static $nextTicketNumberResolver = null;

    public function __construct(
        private readonly SupportStatusMachine $statusMachine,
        private readonly SupportNotificationService $notifications,
        private readonly SupportUnreadService $unread,
    ) {}

    /**
     * @param  (\Closure(): string)|null  $resolver
     */
    public static function setNextTicketNumberResolver(?\Closure $resolver): void
    {
        self::$nextTicketNumberResolver = $resolver;
    }

    /**
     * @param  array{
     *   subject: string,
     *   body: string,
     *   category?: string|null,
     *   page_url?: string|null,
     *   related_program_id?: int|null,
     *   name?: string|null,
     *   email?: string|null,
     * }  $data
     */
    public function create(array $data, ?User $user = null): SupportTicket
    {
        $lastConflict = null;

        for ($attempt = 1; $attempt <= self::TICKET_NUMBER_MAX_ATTEMPTS; $attempt++) {
            try {
                return DB::transaction(function () use ($data, $user): SupportTicket {
                    $name = $user?->name ?: (string) ($data['name'] ?? '');
                    $email = $user?->email ?: (string) ($data['email'] ?? '');

                    $ticket = SupportTicket::query()->create([
                        'user_id' => $user?->id,
                        'ticket_number' => $this->nextTicketNumber(),
                        'name' => $name,
                        'email' => $email,
                        'subject' => $data['subject'],
                        'category' => SupportTicketCategory::tryFrom((string) ($data['category'] ?? ''))
                            ?? SupportTicketCategory::General,
                        'body' => $data['body'],
                        'page_url' => $data['page_url'] ?? null,
                        'related_program_id' => $data['related_program_id'] ?? null,
                        'status' => SupportTicketStatus::Open,
                        'priority' => SupportTicketPriority::Normal,
                        'last_message_at' => now(),
                        'last_message_sender_type' => SupportMessageSenderType::Beneficiary,
                    ]);

                    $message = SupportTicketMessage::query()->create([
                        'support_ticket_id' => $ticket->id,
                        'user_id' => $user?->id,
                        'sender_type' => SupportMessageSenderType::Beneficiary,
                        'body' => $data['body'],
                        'is_system' => false,
                        'source' => 'conversation',
                        'created_at' => now(),
                    ]);

                    SupportTicketStatusEvent::query()->create([
                        'support_ticket_id' => $ticket->id,
                        'from_status' => null,
                        'to_status' => SupportTicketStatus::Open,
                        'reason' => 'created',
                        'status_update_text' => null,
                        'actor_id' => $user?->id,
                        'support_ticket_message_id' => $message->id,
                        'created_at' => now(),
                    ]);

                    if ($user !== null) {
                        $this->unread->markTicketRead($ticket, $user, 'beneficiary');
                        UserActivityLogger::log(
                            $user,
                            UserActivityAction::SupportTicketCreated,
                            'أنشأ المستفيد تذكرة دعم '.$ticket->displayNumber().'.',
                        );
                    }

                    return $ticket->fresh(['messages', 'statusEvents']) ?? $ticket;
                });
            } catch (UniqueConstraintViolationException $e) {
                if (! $this->isTicketNumberConflict($e)) {
                    throw $e;
                }

                $lastConflict = $e;
                Log::info('support.ticket_number_collision_retry', [
                    'attempt' => $attempt,
                ]);
            }
        }

        throw $lastConflict ?? new RuntimeException('Unable to allocate a unique support ticket_number.');
    }

    /**
     * Create then notify outside the transaction so mail failure never rolls back.
     *
     * @param  array<string, mixed>  $data
     */
    public function createAndNotify(array $data, ?User $user = null): SupportTicket
    {
        $ticket = $this->create($data, $user);
        $this->notifications->notifyAdminsOfNewTicket($ticket);

        return $ticket;
    }

    /**
     * Beneficiary reply on an open conversation.
     */
    public function addBeneficiaryReply(SupportTicket $ticket, User $user, string $body): SupportTicketMessage
    {
        if ((int) $ticket->user_id !== (int) $user->id) {
            abort(403);
        }

        $status = SupportTicketStatus::coerce($ticket->status);
        if (! $status->allowsBeneficiaryReply()) {
            throw ValidationException::withMessages([
                'body' => 'هذه المحادثة مغلقة أو محلولة ولا يمكن إضافة رد جديد.',
            ]);
        }

        return DB::transaction(function () use ($ticket, $user, $body): SupportTicketMessage {
            $message = SupportTicketMessage::query()->create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'sender_type' => SupportMessageSenderType::Beneficiary,
                'body' => $body,
                'is_system' => false,
                'source' => 'conversation',
                'created_at' => now(),
            ]);

            $updates = [
                'last_message_at' => $message->created_at,
                'last_message_sender_type' => SupportMessageSenderType::Beneficiary,
            ];

            $status = SupportTicketStatus::coerce($ticket->status);
            if ($status === SupportTicketStatus::WaitingOnUser) {
                $this->applyStatusChange(
                    ticket: $ticket,
                    to: SupportTicketStatus::InProgress,
                    actor: $user,
                    reason: 'beneficiary_replied',
                    statusUpdateText: null,
                    linkedMessage: $message,
                    persistTicket: false,
                );
                $updates['status'] = SupportTicketStatus::InProgress;
            }

            $ticket->forceFill($updates)->save();
            $this->unread->markTicketRead($ticket, $user, 'beneficiary');

            UserActivityLogger::log(
                $user,
                UserActivityAction::SupportTicketReplied,
                'رد المستفيد على تذكرة الدعم '.$ticket->displayNumber().'.',
            );

            return $message;
        });
    }

    /**
     * Staff reply + optional status/priority/assignee in one transaction.
     *
     * @param  array{
     *   body: string,
     *   new_status?: string|null,
     *   status_update_text?: string|null,
     *   priority?: string|null,
     *   assigned_to?: int|null,
     *   resolution_summary?: string|null,
     * }  $data
     */
    public function addSupportReply(SupportTicket $ticket, User $actor, array $data): SupportTicketMessage
    {
        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'نص الرد مطلوب.']);
        }

        $newStatus = isset($data['new_status']) && filled($data['new_status'])
            ? SupportTicketStatus::coerce($data['new_status'])
            : null;

        $statusText = isset($data['status_update_text']) ? trim((string) $data['status_update_text']) : null;

        if ($newStatus !== null && $this->statusMachine->requiresStatusUpdateText($newStatus) && blank($statusText)) {
            throw ValidationException::withMessages([
                'status_update_text' => 'يرجى كتابة نص يوضح سبب تغيير الحالة.',
            ]);
        }

        $message = DB::transaction(function () use ($ticket, $actor, $data, $body, $newStatus, $statusText): SupportTicketMessage {
            $message = SupportTicketMessage::query()->create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $actor->id,
                'sender_type' => SupportMessageSenderType::Support,
                'body' => $body,
                'is_system' => false,
                'source' => 'conversation',
                'created_at' => now(),
            ]);

            $updates = [
                'last_message_at' => $message->created_at,
                'last_message_sender_type' => SupportMessageSenderType::Support,
            ];

            if (array_key_exists('priority', $data) && filled($data['priority'])) {
                $updates['priority'] = SupportTicketPriority::tryFrom((string) $data['priority'])
                    ?? $ticket->priority;
            }

            if (array_key_exists('assigned_to', $data)) {
                $updates['assigned_to'] = $data['assigned_to'] ?: null;
            }

            if (array_key_exists('resolution_summary', $data) && filled($data['resolution_summary'])) {
                $updates['resolution_summary'] = (string) $data['resolution_summary'];
            }

            if ($newStatus !== null && $newStatus !== SupportTicketStatus::coerce($ticket->status)) {
                $this->applyStatusChange(
                    ticket: $ticket,
                    to: $newStatus,
                    actor: $actor,
                    reason: 'support_reply',
                    statusUpdateText: $statusText,
                    linkedMessage: $message,
                    persistTicket: false,
                );
                $updates['status'] = $newStatus;
                if ($newStatus === SupportTicketStatus::Closed || $newStatus === SupportTicketStatus::Resolved) {
                    $updates['closed_at'] = now();
                }
                if ($newStatus === SupportTicketStatus::Open || $newStatus === SupportTicketStatus::InProgress) {
                    $updates['closed_at'] = null;
                }
            }

            $ticket->forceFill($updates)->save();
            $this->unread->markTicketRead($ticket, $actor, 'staff');

            return $message;
        });

        try {
            $this->notifications->notifyBeneficiaryOfSupportReply($ticket->fresh(['user']) ?? $ticket, $message, $actor);
        } catch (Throwable $e) {
            Log::warning('support.reply_notify_failed', [
                'ticket_id' => $ticket->id,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $message;
    }

    /**
     * Status-only change (assign/close/reopen toolbar) without a support reply body.
     */
    public function changeStatus(
        SupportTicket $ticket,
        User $actor,
        SupportTicketStatus $to,
        ?string $statusUpdateText = null,
        ?string $reason = 'status_change',
    ): SupportTicketStatusEvent {
        if ($this->statusMachine->requiresStatusUpdateText($to) && blank($statusUpdateText)) {
            throw ValidationException::withMessages([
                'status_update_text' => 'يرجى كتابة نص يوضح سبب تغيير الحالة.',
            ]);
        }

        return DB::transaction(function () use ($ticket, $actor, $to, $statusUpdateText, $reason): SupportTicketStatusEvent {
            $event = $this->applyStatusChange(
                ticket: $ticket,
                to: $to,
                actor: $actor,
                reason: $reason,
                statusUpdateText: $statusUpdateText,
                linkedMessage: null,
                persistTicket: true,
            );

            return $event;
        });
    }

    public function assign(SupportTicket $ticket, User $actor, ?User $assignee): SupportTicket
    {
        $ticket->forceFill(['assigned_to' => $assignee?->id])->save();

        Log::info('support.ticket_assigned', [
            'ticket_id' => $ticket->id,
            'actor_id' => $actor->id,
            'assignee_id' => $assignee?->id,
        ]);

        return $ticket;
    }

    private function applyStatusChange(
        SupportTicket $ticket,
        SupportTicketStatus $to,
        ?User $actor,
        ?string $reason,
        ?string $statusUpdateText,
        ?SupportTicketMessage $linkedMessage,
        bool $persistTicket,
    ): SupportTicketStatusEvent {
        $from = SupportTicketStatus::coerce($ticket->status);
        $this->statusMachine->assertCanTransition($from, $to);

        if ($from === $to) {
            return SupportTicketStatusEvent::query()->make([
                'support_ticket_id' => $ticket->id,
                'from_status' => $from,
                'to_status' => $to,
            ]);
        }

        $systemMessage = null;
        if (filled($statusUpdateText)) {
            $systemMessage = SupportTicketMessage::query()->create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $actor?->id,
                'sender_type' => SupportMessageSenderType::System,
                'body' => $statusUpdateText,
                'is_system' => true,
                'source' => 'status_update',
                'created_at' => now(),
            ]);
        }

        $event = SupportTicketStatusEvent::query()->create([
            'support_ticket_id' => $ticket->id,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'status_update_text' => $statusUpdateText,
            'actor_id' => $actor?->id,
            'support_ticket_message_id' => $systemMessage?->id ?? $linkedMessage?->id,
            'created_at' => now(),
        ]);

        if ($persistTicket) {
            $updates = ['status' => $to];
            if ($to === SupportTicketStatus::Closed || $to === SupportTicketStatus::Resolved) {
                $updates['closed_at'] = now();
            }
            if ($to === SupportTicketStatus::Open || $to === SupportTicketStatus::InProgress || $to === SupportTicketStatus::WaitingOnUser) {
                $updates['closed_at'] = null;
            }
            if ($systemMessage !== null) {
                $updates['last_message_at'] = $systemMessage->created_at;
                $updates['last_message_sender_type'] = SupportMessageSenderType::System;
            }
            $ticket->forceFill($updates)->save();
        }

        return $event;
    }

    /**
     * Candidate number for display shape ST-000001.
     *
     * Why unique + retry (not PG SEQUENCE / table-wide advisory lock)?
     * - Unique index is the authoritative duplicate guard (Laravel + PostgreSQL).
     * - Retry on UniqueConstraintViolationException handles concurrent candidate races
     *   without serializing every ticket create behind a wide lock.
     * - A sequence would also work but needs separate lifecycle sync with legacy
     *   id-based backfill; unique+retry keeps ST-%06d with less moving parts.
     *
     * Uses max(numeric suffix of ticket_number, max(id)) so backfilled ST-{id}
     * values and ahead-of-id display numbers both advance the candidate correctly.
     */
    private function nextTicketNumber(): string
    {
        if (self::$nextTicketNumberResolver !== null) {
            return (self::$nextTicketNumberResolver)();
        }

        $maxId = (int) (SupportTicket::query()->max('id') ?? 0);

        $maxSuffix = SupportTicket::query()
            ->whereNotNull('ticket_number')
            ->pluck('ticket_number')
            ->reduce(function (int $carry, mixed $number): int {
                if (preg_match('/^ST-(\d+)$/', (string) $number, $matches) === 1) {
                    return max($carry, (int) $matches[1]);
                }

                return $carry;
            }, 0);

        return sprintf('ST-%06d', max($maxId, $maxSuffix) + 1);
    }

    private function isTicketNumberConflict(UniqueConstraintViolationException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'ticket_number')
            || str_contains($message, 'support_tickets_ticket_number');
    }
}
