<?php

namespace App\Services\ProgramBroadcasts;

use App\Enums\AccountStatus;
use App\Enums\AuditLogResult;
use App\Enums\ProgramBroadcastAudienceMode;
use App\Enums\ProgramBroadcastRecipientStatus;
use App\Enums\ProgramBroadcastStatus;
use App\Enums\RegistrationStatus;
use App\Exceptions\ProgramBroadcastException;
use App\Jobs\DispatchProgramBroadcastChunksJob;
use App\Mail\ProgramBroadcastMail;
use App\Models\ProgramBroadcast;
use App\Models\ProgramBroadcastRecipient;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\RichContentSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class ProgramBroadcastService
{
    public const CHUNK_SIZE = 25;

    /**
     * Stuck `processing` rows older than this are reclaimable.
     * Must exceed SendProgramBroadcastChunkJob::$timeout (180s).
     */
    public const PROCESSING_STUCK_AFTER_SECONDS = 240;

    public const ATTEMPTS_EXHAUSTED_REASON = 'استُنفدت محاولات الإرسال.';

    /** @var list<string> */
    public const DEFAULT_AUDIENCE_STATUSES = [
        'approved',
        'completed',
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{subject: string, content: mixed, audience_mode?: string, audience_statuses?: list<string>|null}  $data
     */
    public function createDraft(TrainingProgram $program, User $actor, array $data): ProgramBroadcast
    {
        $this->assertCanCreate($actor, $program);

        $broadcast = ProgramBroadcast::query()->create([
            'training_program_id' => $program->id,
            'created_by' => $actor->id,
            'subject' => $this->normalizeSubject($data['subject'] ?? ''),
            'content' => $this->normalizeContent($data['content'] ?? null),
            'audience_mode' => $this->normalizeAudienceMode($data['audience_mode'] ?? null)->value,
            'audience_statuses' => $this->normalizeAudienceStatuses(
                $data['audience_mode'] ?? null,
                $data['audience_statuses'] ?? null,
            ),
            'status' => ProgramBroadcastStatus::Draft,
        ]);

        $this->auditLogger->record(
            $actor,
            'program_broadcast.draft_created',
            AuditLogResult::Success,
            resource: $broadcast,
            metadata: [
                'training_program_id' => $program->id,
                'broadcast_id' => $broadcast->id,
                'audience_mode' => $broadcast->audience_mode?->value,
            ],
        );

        return $broadcast;
    }

    /**
     * @param  array{subject?: string, content?: mixed, audience_mode?: string, audience_statuses?: list<string>|null}  $data
     */
    public function updateDraft(ProgramBroadcast $broadcast, User $actor, array $data): ProgramBroadcast
    {
        $this->assertCanUpdate($actor, $broadcast);

        if (! $broadcast->isDraft()) {
            throw ProgramBroadcastException::immutableContent();
        }

        $payload = [];

        if (array_key_exists('subject', $data)) {
            $payload['subject'] = $this->normalizeSubject((string) $data['subject']);
        }

        if (array_key_exists('content', $data)) {
            $payload['content'] = $this->normalizeContent($data['content']);
        }

        if (array_key_exists('audience_mode', $data) || array_key_exists('audience_statuses', $data)) {
            $mode = $data['audience_mode'] ?? $broadcast->audience_mode?->value;
            $payload['audience_mode'] = $this->normalizeAudienceMode($mode)->value;
            $payload['audience_statuses'] = $this->normalizeAudienceStatuses(
                $mode,
                $data['audience_statuses'] ?? $broadcast->audience_statuses,
            );
        }

        if ($payload !== []) {
            $broadcast->update($payload);
        }

        return $broadcast->fresh() ?? $broadcast;
    }

    public function deleteDraft(ProgramBroadcast $broadcast, User $actor): void
    {
        if (! $actor->can('delete', $broadcast)) {
            throw ProgramBroadcastException::unauthorized();
        }

        if (! $broadcast->canBeDeleted()) {
            throw ProgramBroadcastException::notDraft();
        }

        $broadcastId = $broadcast->id;
        $programId = $broadcast->training_program_id;
        $broadcast->delete();

        $this->auditLogger->record(
            $actor,
            'program_broadcast.draft_deleted',
            AuditLogResult::Success,
            metadata: [
                'training_program_id' => $programId,
                'broadcast_id' => $broadcastId,
            ],
        );
    }

    /**
     * Preview payload with sanitized HTML — never trust raw editor input.
     *
     * @return array{subject: string, content_html: string, beneficiary_name: string, program_title: string}
     */
    public function previewPayload(TrainingProgram $program, string $subject, mixed $content, ?User $sampleUser = null): array
    {
        $normalizedContent = RichContentSupport::normalizeForStorage($content);

        return [
            'subject' => mb_substr(trim($subject), 0, 255),
            'content_html' => RichContentSupport::toDisplayHtml($normalizedContent),
            'beneficiary_name' => $sampleUser?->certificateName() ?: $sampleUser?->name ?: 'المستفيد',
            'program_title' => (string) $program->title,
        ];
    }

    /**
     * Count eligible recipients for the current program only (IDOR-safe).
     *
     * @param  list<string>|null  $audienceStatuses
     */
    public function countEligibleRecipients(
        TrainingProgram $program,
        ProgramBroadcastAudienceMode|string|null $audienceMode,
        ?array $audienceStatuses,
    ): int {
        $probe = new ProgramBroadcast([
            'audience_mode' => $this->normalizeAudienceMode($audienceMode)->value,
            'audience_statuses' => $this->normalizeAudienceStatuses(
                $audienceMode,
                $audienceStatuses,
            ),
        ]);

        return $this->resolveEligibleRecipients($probe, $program)->count();
    }

    /**
     * Confirm send: snapshot recipients, mark queued, dispatch chunk jobs.
     * Never sends mail inside the HTTP/Livewire request.
     */
    public function sendNow(ProgramBroadcast $broadcast, User $actor): ProgramBroadcast
    {
        if (! $actor->can('send', $broadcast)) {
            throw ProgramBroadcastException::unauthorized();
        }

        if (! $broadcast->isDraft()) {
            throw ProgramBroadcastException::concurrentSend();
        }

        $program = $broadcast->trainingProgram;
        if ($program === null) {
            throw ProgramBroadcastException::emptyAudience();
        }

        $eligible = $this->resolveEligibleRecipients($broadcast, $program);

        if ($eligible->isEmpty()) {
            throw ProgramBroadcastException::emptyAudience();
        }

        $queued = DB::transaction(function () use ($broadcast, $eligible, $actor): ProgramBroadcast {
            /** @var ProgramBroadcast|null $locked */
            $locked = ProgramBroadcast::query()
                ->whereKey($broadcast->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || ! $locked->isDraft()) {
                throw ProgramBroadcastException::concurrentSend();
            }

            $rows = $eligible->map(static function (array $row) use ($locked): array {
                return [
                    'program_broadcast_id' => $locked->id,
                    'user_id' => $row['user_id'],
                    'program_registration_id' => $row['program_registration_id'],
                    'email' => $row['email'],
                    'name' => $row['name'],
                    'registration_status' => $row['registration_status'],
                    'status' => ProgramBroadcastRecipientStatus::Pending->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->all();

            foreach (array_chunk($rows, 100) as $chunk) {
                ProgramBroadcastRecipient::query()->insert($chunk);
            }

            $locked->update([
                'status' => ProgramBroadcastStatus::Queued,
                'recipients_count' => count($rows),
                'sent_count' => 0,
                'failed_count' => 0,
                'skipped_count' => 0,
                'sending_started_at' => now(),
                'sending_completed_at' => null,
            ]);

            $this->auditLogger->record(
                $actor,
                'program_broadcast.send_started',
                AuditLogResult::Success,
                resource: $locked,
                metadata: [
                    'training_program_id' => $locked->training_program_id,
                    'broadcast_id' => $locked->id,
                    'recipients_count' => count($rows),
                    'audience_mode' => $locked->audience_mode?->value,
                ],
            );

            return $locked->fresh() ?? $locked;
        });

        DispatchProgramBroadcastChunksJob::dispatch($queued->id);

        return $queued;
    }

    /**
     * Re-queue failed recipients only; idempotent for already-sent rows.
     */
    public function retryFailed(ProgramBroadcast $broadcast, User $actor): ProgramBroadcast
    {
        if (! $actor->can('retryFailed', $broadcast)) {
            throw ProgramBroadcastException::unauthorized();
        }

        if (! $broadcast->canRetryFailed()) {
            throw ProgramBroadcastException::cannotRetry();
        }

        $reset = DB::transaction(function () use ($broadcast, $actor): ProgramBroadcast {
            /** @var ProgramBroadcast $locked */
            $locked = ProgramBroadcast::query()
                ->whereKey($broadcast->id)
                ->lockForUpdate()
                ->firstOrFail();

            $failedCount = $locked->recipients()
                ->where('status', ProgramBroadcastRecipientStatus::Failed)
                ->count();

            if ($failedCount === 0) {
                throw ProgramBroadcastException::cannotRetry();
            }

            $locked->recipients()
                ->where('status', ProgramBroadcastRecipientStatus::Failed)
                ->update([
                    'status' => ProgramBroadcastRecipientStatus::Pending->value,
                    'failure_reason' => null,
                    'provider_message_id' => null,
                    'sent_at' => null,
                    'updated_at' => now(),
                ]);

            $locked->update([
                'status' => ProgramBroadcastStatus::Queued,
                'failed_count' => 0,
                'sending_started_at' => $locked->sending_started_at ?? now(),
                'sending_completed_at' => null,
            ]);

            $this->auditLogger->record(
                $actor,
                'program_broadcast.retry_failed',
                AuditLogResult::Success,
                resource: $locked,
                metadata: [
                    'training_program_id' => $locked->training_program_id,
                    'broadcast_id' => $locked->id,
                    'retry_count' => $failedCount,
                ],
            );

            return $locked->fresh() ?? $locked;
        });

        DispatchProgramBroadcastChunksJob::dispatch($reset->id);

        return $reset;
    }

    public function copyToNewDraft(ProgramBroadcast $source, User $actor): ProgramBroadcast
    {
        $program = $source->trainingProgram;
        if ($program === null) {
            throw ProgramBroadcastException::unauthorized();
        }

        return $this->createDraft($program, $actor, [
            'subject' => $source->subject,
            'content' => $source->content,
            'audience_mode' => $source->audience_mode?->value,
            'audience_statuses' => $source->audience_statuses,
        ]);
    }

    /**
     * Process a chunk of recipient IDs. Idempotent via atomic pending→processing claim.
     * Also reclaims stuck `processing` rows older than PROCESSING_STUCK_AFTER_SECONDS.
     *
     * @param  list<int>  $recipientIds
     */
    public function processRecipientChunk(int $broadcastId, array $recipientIds): void
    {
        $broadcast = ProgramBroadcast::query()->find($broadcastId);
        if ($broadcast === null) {
            return;
        }

        $this->markSendingIfQueued($broadcast);

        $programTitle = (string) ($broadcast->trainingProgram?->title ?? 'برنامج تدريبي');

        $recipients = ProgramBroadcastRecipient::query()
            ->where('program_broadcast_id', $broadcastId)
            ->whereIn('id', $recipientIds)
            ->where(function ($query): void {
                $this->scopeClaimableRecipients($query);
            })
            ->get();

        foreach ($recipients as $recipient) {
            $this->sendToRecipient($broadcast, $recipient, $programTitle);
        }

        $this->refreshAggregateCounts($broadcastId);
    }

    /**
     * Mark remaining incomplete recipients for this chunk as failed after job tries are exhausted.
     * Never touches `sent` (or skipped) rows. Then settles aggregate/final broadcast status.
     *
     * @param  list<int>  $recipientIds
     */
    public function markChunkAttemptsExhausted(int $broadcastId, array $recipientIds): void
    {
        if ($recipientIds === []) {
            $this->refreshAggregateCounts($broadcastId);

            return;
        }

        ProgramBroadcastRecipient::query()
            ->where('program_broadcast_id', $broadcastId)
            ->whereIn('id', $recipientIds)
            ->whereIn('status', ProgramBroadcastRecipientStatus::incompleteValues())
            ->update([
                'status' => ProgramBroadcastRecipientStatus::Failed->value,
                'failure_reason' => self::ATTEMPTS_EXHAUSTED_REASON,
                'updated_at' => now(),
            ]);

        $this->refreshAggregateCounts($broadcastId);
    }

    public function refreshAggregateCounts(int $broadcastId): void
    {
        /** @var ProgramBroadcast|null $broadcast */
        $broadcast = ProgramBroadcast::query()->find($broadcastId);
        if ($broadcast === null) {
            return;
        }

        $incomplete = $broadcast->recipients()
            ->whereIn('status', ProgramBroadcastRecipientStatus::incompleteValues())
            ->count();
        $sent = $broadcast->recipients()
            ->where('status', ProgramBroadcastRecipientStatus::Sent)
            ->count();
        $failed = $broadcast->recipients()
            ->where('status', ProgramBroadcastRecipientStatus::Failed)
            ->count();
        $skipped = $broadcast->recipients()
            ->where('status', ProgramBroadcastRecipientStatus::Skipped)
            ->count();

        $payload = [
            'sent_count' => $sent,
            'failed_count' => $failed,
            'skipped_count' => $skipped,
        ];

        if ($incomplete === 0 && $broadcast->recipients_count > 0) {
            $payload['sending_completed_at'] = now();
            $payload['status'] = $this->resolveTerminalStatus($sent, $failed, $skipped, (int) $broadcast->recipients_count);

            if (! $broadcast->status?->isTerminal()) {
                $this->auditLogger->record(
                    Auth::user() instanceof User ? Auth::user() : $broadcast->creator,
                    'program_broadcast.send_completed',
                    $failed > 0 && $sent === 0 ? AuditLogResult::Failure : AuditLogResult::Success,
                    resource: $broadcast,
                    metadata: [
                        'training_program_id' => $broadcast->training_program_id,
                        'broadcast_id' => $broadcast->id,
                        'sent_count' => $sent,
                        'failed_count' => $failed,
                        'skipped_count' => $skipped,
                        'final_status' => $payload['status'] instanceof ProgramBroadcastStatus
                            ? $payload['status']->value
                            : (string) $payload['status'],
                    ],
                );
            }
        } elseif ($broadcast->status === ProgramBroadcastStatus::Queued) {
            $payload['status'] = ProgramBroadcastStatus::Sending;
        }

        $broadcast->update($payload);
    }

    /**
     * IDs still claimable for dispatch: pending, or stuck processing past the reclaim window.
     *
     * @return list<int>
     */
    public function claimableRecipientIds(int $broadcastId): array
    {
        return ProgramBroadcastRecipient::query()
            ->where('program_broadcast_id', $broadcastId)
            ->where(function ($query): void {
                $this->scopeClaimableRecipients($query);
            })
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return Collection<int, array{user_id: int, program_registration_id: int, email: string, name: string, registration_status: string}>
     */
    public function resolveEligibleRecipients(ProgramBroadcast $broadcast, TrainingProgram $program): Collection
    {
        $registrations = $this->eligibleRegistrationsQuery(
            $program,
            $broadcast->audience_mode,
            $broadcast->audience_statuses,
        )
            ->with(['user:id,name,email,is_active,account_status,privacy_deleted_at,anonymized_at'])
            ->get();

        $seenUserIds = [];
        $seenEmails = [];
        $rows = collect();

        foreach ($registrations as $registration) {
            /** @var ProgramRegistration $registration */
            $user = $registration->user;
            if (! $this->isOperationalRecipient($user)) {
                continue;
            }

            $email = strtolower(trim((string) $user->email));
            $userId = (int) $user->id;

            if (isset($seenUserIds[$userId]) || isset($seenEmails[$email])) {
                continue;
            }

            $seenUserIds[$userId] = true;
            $seenEmails[$email] = true;

            $rows->push([
                'user_id' => $userId,
                'program_registration_id' => (int) $registration->id,
                'email' => $email,
                'name' => $user->certificateName() ?: (string) $user->name,
                'registration_status' => $registration->status instanceof RegistrationStatus
                    ? $registration->status->value
                    : (string) $registration->status,
            ]);
        }

        return $rows->values();
    }

    /**
     * @param  list<string>|null  $audienceStatuses
     * @return Builder<ProgramRegistration>
     */
    public function eligibleRegistrationsQuery(
        TrainingProgram $program,
        ProgramBroadcastAudienceMode|string|null $audienceMode,
        ?array $audienceStatuses,
    ): Builder {
        $mode = $this->normalizeAudienceMode($audienceMode);
        $statuses = $this->normalizeAudienceStatuses($mode->value, $audienceStatuses);

        $query = ProgramRegistration::query()
            ->where('training_program_id', $program->id)
            ->whereHas('user', function (Builder $userQuery): void {
                $userQuery
                    ->where('is_active', true)
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->whereNull('privacy_deleted_at')
                    ->whereNull('anonymized_at')
                    ->whereNotIn('account_status', [
                        AccountStatus::Inactive->value,
                        AccountStatus::Anonymized->value,
                        AccountStatus::DeletionProcessing->value,
                    ]);
            });

        if ($mode === ProgramBroadcastAudienceMode::Statuses) {
            $query->whereIn('status', $statuses ?? self::DEFAULT_AUDIENCE_STATUSES);
        }

        return $query;
    }

    public function isOperationalRecipient(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (! $user->is_active) {
            return false;
        }

        if ($user->privacy_deleted_at !== null || $user->anonymized_at !== null) {
            return false;
        }

        if (! $user->allowsOperationalAccess()) {
            return false;
        }

        $email = trim((string) $user->email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Operational program mail — do NOT gate on notify_email (marketing preference).
        return true;
    }

    private function sendToRecipient(
        ProgramBroadcast $broadcast,
        ProgramBroadcastRecipient $recipient,
        string $programTitle,
    ): void {
        if (! $this->claimRecipientForSend($recipient->id)) {
            return;
        }

        try {
            $sentMessage = Mail::to($recipient->email)->send(
                new ProgramBroadcastMail($broadcast, $recipient, $programTitle),
            );

            $messageId = null;
            if (is_object($sentMessage) && method_exists($sentMessage, 'getMessageId')) {
                $messageId = $sentMessage->getMessageId();
            }

            ProgramBroadcastRecipient::query()
                ->whereKey($recipient->id)
                ->where('status', ProgramBroadcastRecipientStatus::Processing)
                ->update([
                    'status' => ProgramBroadcastRecipientStatus::Sent->value,
                    'provider_message_id' => is_string($messageId) ? $messageId : null,
                    'sent_at' => now(),
                    'failure_reason' => null,
                    'updated_at' => now(),
                ]);
        } catch (TransportExceptionInterface $e) {
            if ($this->isRateLimited($e)) {
                $this->releaseRecipientToPending($recipient->id);

                Log::warning('Program broadcast rate-limited; returned recipient to pending for retry.', [
                    'broadcast_id' => $broadcast->id,
                    'recipient_id' => $recipient->id,
                    'exception_class' => $e::class,
                ]);

                throw $e;
            }

            $this->markRecipientFailed($recipient, $this->safeFailureReason($e));
            Log::warning('Program broadcast recipient transport failure.', [
                'broadcast_id' => $broadcast->id,
                'recipient_id' => $recipient->id,
                'exception_class' => $e::class,
            ]);
        } catch (Throwable $e) {
            $this->markRecipientFailed($recipient, $this->safeFailureReason($e));
            Log::warning('Program broadcast recipient send failure.', [
                'broadcast_id' => $broadcast->id,
                'recipient_id' => $recipient->id,
                'exception_class' => $e::class,
            ]);
        }
    }

    /**
     * Atomically claim a recipient before calling the mail provider.
     * Wins only for pending, or stuck processing past PROCESSING_STUCK_AFTER_SECONDS.
     * Fresh processing / sent / failed / skipped → no claim, no send.
     */
    public function claimRecipientForSend(int $recipientId): bool
    {
        $claimed = ProgramBroadcastRecipient::query()
            ->whereKey($recipientId)
            ->where(function ($query): void {
                $this->scopeClaimableRecipients($query);
            })
            ->update([
                'status' => ProgramBroadcastRecipientStatus::Processing->value,
                'updated_at' => now(),
            ]);

        return $claimed > 0;
    }

    /**
     * @param  Builder<ProgramBroadcastRecipient>|\Illuminate\Database\Query\Builder  $query
     */
    private function scopeClaimableRecipients($query): void
    {
        $stuckBefore = now()->subSeconds(self::PROCESSING_STUCK_AFTER_SECONDS);

        $query
            ->where('status', ProgramBroadcastRecipientStatus::Pending->value)
            ->orWhere(function ($stuck) use ($stuckBefore): void {
                $stuck
                    ->where('status', ProgramBroadcastRecipientStatus::Processing->value)
                    ->where('updated_at', '<', $stuckBefore);
            });
    }

    private function releaseRecipientToPending(int $recipientId): void
    {
        ProgramBroadcastRecipient::query()
            ->whereKey($recipientId)
            ->where('status', ProgramBroadcastRecipientStatus::Processing)
            ->update([
                'status' => ProgramBroadcastRecipientStatus::Pending->value,
                'updated_at' => now(),
            ]);
    }

    private function isRateLimited(Throwable $e): bool
    {
        $haystack = strtolower($e->getMessage().' '.(string) $e->getCode());

        return str_contains($haystack, '429')
            || str_contains($haystack, 'too many')
            || str_contains($haystack, 'rate limit');
    }

    private function markRecipientFailed(ProgramBroadcastRecipient $recipient, string $reason): void
    {
        ProgramBroadcastRecipient::query()
            ->whereKey($recipient->id)
            ->where('status', ProgramBroadcastRecipientStatus::Processing)
            ->update([
                'status' => ProgramBroadcastRecipientStatus::Failed->value,
                'failure_reason' => mb_substr($reason, 0, 500),
                'updated_at' => now(),
            ]);
    }

    private function markSendingIfQueued(ProgramBroadcast $broadcast): void
    {
        ProgramBroadcast::query()
            ->whereKey($broadcast->id)
            ->where('status', ProgramBroadcastStatus::Queued)
            ->update([
                'status' => ProgramBroadcastStatus::Sending->value,
                'sending_started_at' => $broadcast->sending_started_at ?? now(),
            ]);
    }

    private function resolveTerminalStatus(int $sent, int $failed, int $skipped, int $total): ProgramBroadcastStatus
    {
        if ($sent === 0 && $failed > 0) {
            return ProgramBroadcastStatus::Failed;
        }

        if ($failed > 0) {
            return ProgramBroadcastStatus::CompletedWithErrors;
        }

        if ($sent + $skipped >= $total || $failed === 0) {
            return ProgramBroadcastStatus::Completed;
        }

        return ProgramBroadcastStatus::CompletedWithErrors;
    }

    private function safeFailureReason(Throwable $e): string
    {
        $message = $e->getMessage();
        $message = preg_replace('/\b[A-Za-z0-9_\-]{20,}\b/', '[redacted]', $message) ?? $message;
        $message = preg_replace('/re[_-]?send[^\s]*/i', 'provider', $message) ?? $message;

        if (str_contains(strtolower($message), '429') || str_contains(strtolower($message), 'too many')) {
            return 'تجاوز حد معدل الإرسال لدى مزوّد البريد. أعد المحاولة لاحقاً.';
        }

        return 'تعذّر إرسال البريد. '.$message;
    }

    private function normalizeSubject(string $subject): string
    {
        $subject = trim($subject);

        if ($subject === '') {
            throw new ProgramBroadcastException('موضوع الرسالة مطلوب.');
        }

        return mb_substr($subject, 0, 255);
    }

    private function normalizeContent(mixed $content): string
    {
        $normalized = RichContentSupport::normalizeForStorage($content);
        $plain = RichContentSupport::toPlainText($normalized);

        if ($normalized === null || trim($plain) === '') {
            throw new ProgramBroadcastException('محتوى الرسالة مطلوب.');
        }

        return $normalized;
    }

    private function normalizeAudienceMode(ProgramBroadcastAudienceMode|string|null $mode): ProgramBroadcastAudienceMode
    {
        if ($mode instanceof ProgramBroadcastAudienceMode) {
            return $mode;
        }

        return ProgramBroadcastAudienceMode::tryFrom((string) $mode)
            ?? ProgramBroadcastAudienceMode::Statuses;
    }

    /**
     * @param  list<string>|null  $statuses
     * @return list<string>|null
     */
    private function normalizeAudienceStatuses(ProgramBroadcastAudienceMode|string|null $mode, ?array $statuses): ?array
    {
        $resolvedMode = $this->normalizeAudienceMode($mode);

        if ($resolvedMode === ProgramBroadcastAudienceMode::All) {
            return null;
        }

        $raw = is_array($statuses) ? $statuses : self::DEFAULT_AUDIENCE_STATUSES;
        $out = [];

        foreach ($raw as $value) {
            $status = $value instanceof RegistrationStatus
                ? $value
                : RegistrationStatus::tryFrom((string) $value);

            if ($status !== null) {
                $out[] = $status->value;
            }
        }

        $out = array_values(array_unique($out));

        return $out === [] ? self::DEFAULT_AUDIENCE_STATUSES : $out;
    }

    private function assertCanCreate(User $actor, TrainingProgram $program): void
    {
        if (! $actor->can('create', [ProgramBroadcast::class, $program])) {
            throw ProgramBroadcastException::unauthorized();
        }
    }

    private function assertCanUpdate(User $actor, ProgramBroadcast $broadcast): void
    {
        if (! $actor->can('update', $broadcast)) {
            throw ProgramBroadcastException::unauthorized();
        }
    }
}
