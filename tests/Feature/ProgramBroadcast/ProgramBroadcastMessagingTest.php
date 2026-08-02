<?php

namespace Tests\Feature\ProgramBroadcast;

use App\Enums\AccountStatus;
use App\Enums\CompetencyTrack;
use App\Enums\ProgramBroadcastAudienceMode;
use App\Enums\ProgramBroadcastRecipientStatus;
use App\Enums\ProgramBroadcastStatus;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TrainingProgramKind;
use App\Exceptions\ProgramBroadcastException;
use App\Filament\Resources\TrainingProgramResource;
use App\Filament\Resources\TrainingProgramResource\Pages\ViewTrainingProgram;
use App\Filament\Resources\TrainingProgramResource\RelationManagers\ProgramBroadcastsRelationManager;
use App\Jobs\DispatchProgramBroadcastChunksJob;
use App\Jobs\SendProgramBroadcastChunkJob;
use App\Mail\ProgramBroadcastMail;
use App\Models\AuditLog;
use App\Models\ProgramBroadcast;
use App\Models\ProgramBroadcastRecipient;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\ProgramBroadcasts\ProgramBroadcastService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class ProgramBroadcastMessagingTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    private const TIPTAP = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"محتوى الرسالة الجماعية"}]}]}';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_relation_manager_visible_for_operational_access(): void
    {
        $admin = $this->adminUser();
        $program = $this->createProgram(['owner_id' => $admin->id, 'created_by' => $admin->id]);

        $this->actingAs($admin);

        $this->assertTrue(
            ProgramBroadcastsRelationManager::canViewForRecord($program, ViewTrainingProgram::class)
        );
    }

    public function test_relation_manager_hidden_without_operational_access(): void
    {
        $outsider = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $outsider->assignRole('staff');
        $outsider->givePermissionTo(['programs.view', 'emails.send']);

        $owner = $this->adminUser();
        $program = $this->createProgram(['owner_id' => $owner->id, 'created_by' => $owner->id]);

        $this->actingAs($outsider);

        $this->assertFalse(
            ProgramBroadcastsRelationManager::canViewForRecord($program, ViewTrainingProgram::class)
        );
    }

    public function test_create_draft_requires_emails_send_and_stakeholder_scope(): void
    {
        $service = app(ProgramBroadcastService::class);
        $owner = $this->adminUser();
        $program = $this->createProgram(['owner_id' => $owner->id, 'created_by' => $owner->id]);

        $staffNoPerm = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staffNoPerm->assignRole('staff');
        $staffNoPerm->givePermissionTo(['programs.view', 'programs.update']);
        $program->editors()->attach($staffNoPerm->id);

        $this->expectException(ProgramBroadcastException::class);
        $service->createDraft($program, $staffNoPerm, [
            'subject' => 'اختبار',
            'content' => self::TIPTAP,
        ]);
    }

    public function test_stakeholder_with_emails_send_can_create_draft(): void
    {
        $service = app(ProgramBroadcastService::class);
        [$program, $staff] = $this->programWithEditorStaff();

        $broadcast = $service->createDraft($program, $staff, [
            'subject' => 'ترحيب بالمقبولين',
            'content' => self::TIPTAP,
            'audience_mode' => ProgramBroadcastAudienceMode::Statuses->value,
            'audience_statuses' => ['approved', 'completed'],
        ]);

        $this->assertTrue($broadcast->isDraft());
        $this->assertSame('ترحيب بالمقبولين', $broadcast->subject);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'program_broadcast.draft_created',
        ]);
    }

    public function test_default_audience_is_approved_and_completed(): void
    {
        $service = app(ProgramBroadcastService::class);
        [$program, $staff] = $this->programWithEditorStaff();

        $broadcast = $service->createDraft($program, $staff, [
            'subject' => 'افتراضي',
            'content' => self::TIPTAP,
        ]);

        $this->assertSame(ProgramBroadcastAudienceMode::Statuses, $broadcast->audience_mode);
        $this->assertSame(['approved', 'completed'], $broadcast->audience_statuses);
    }

    public function test_send_queues_jobs_and_does_not_send_inline(): void
    {
        Queue::fake();
        Mail::fake();

        $service = app(ProgramBroadcastService::class);
        [$program, $staff] = $this->programWithEditorStaff();
        $this->registerBeneficiary($program, RegistrationStatus::Approved, 'a@example.com');

        $draft = $service->createDraft($program, $staff, [
            'subject' => 'إرسال فوري',
            'content' => self::TIPTAP,
        ]);

        $queued = $service->sendNow($draft, $staff);

        $this->assertSame(ProgramBroadcastStatus::Queued, $queued->status);
        $this->assertSame(1, $queued->recipients_count);
        Queue::assertPushed(DispatchProgramBroadcastChunksJob::class);
        Mail::assertNothingSent();
    }

    public function test_send_blocked_when_zero_recipients(): void
    {
        $service = app(ProgramBroadcastService::class);
        [$program, $staff] = $this->programWithEditorStaff();

        $draft = $service->createDraft($program, $staff, [
            'subject' => 'بدون مستلمين',
            'content' => self::TIPTAP,
        ]);

        $this->expectException(ProgramBroadcastException::class);
        $service->sendNow($draft, $staff);
    }

    public function test_recipients_scoped_to_current_program_only_idor_safe(): void
    {
        $service = app(ProgramBroadcastService::class);
        [$programA, $staff] = $this->programWithEditorStaff();
        $programB = $this->createProgram([
            'slug' => 'other-'.uniqid(),
            'owner_id' => $staff->id,
            'created_by' => $staff->id,
        ]);

        $this->registerBeneficiary($programA, RegistrationStatus::Approved, 'in-a@example.com');
        $this->registerBeneficiary($programB, RegistrationStatus::Approved, 'in-b@example.com');

        $draft = $service->createDraft($programA, $staff, [
            'subject' => 'برنامج أ فقط',
            'content' => self::TIPTAP,
        ]);
        $queued = $service->sendNow($draft, $staff);

        $emails = $queued->recipients()->pluck('email')->all();
        $this->assertSame(['in-a@example.com'], $emails);
    }

    public function test_audience_status_filter_excludes_non_selected(): void
    {
        $service = app(ProgramBroadcastService::class);
        [$program, $staff] = $this->programWithEditorStaff();
        $this->registerBeneficiary($program, RegistrationStatus::Approved, 'ok@example.com');
        $this->registerBeneficiary($program, RegistrationStatus::Pending, 'pending@example.com');
        $this->registerBeneficiary($program, RegistrationStatus::Rejected, 'rejected@example.com');

        $count = $service->countEligibleRecipients(
            $program,
            ProgramBroadcastAudienceMode::Statuses,
            ['approved', 'completed'],
        );

        $this->assertSame(1, $count);
    }

    public function test_skips_invalid_email_inactive_and_anonymized_accounts(): void
    {
        $service = app(ProgramBroadcastService::class);
        [$program, $staff] = $this->programWithEditorStaff();

        $this->registerBeneficiary($program, RegistrationStatus::Approved, 'good@example.com');

        $invalid = User::factory()->create([
            'email' => 'not-an-email',
            'is_active' => true,
            'account_status' => AccountStatus::Active,
            'email_verified_at' => now(),
        ]);
        ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $invalid->id,
            'status' => RegistrationStatus::Approved,
        ]);

        $inactive = User::factory()->create([
            'email' => 'inactive@example.com',
            'is_active' => false,
            'account_status' => AccountStatus::Inactive,
            'email_verified_at' => now(),
        ]);
        ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $inactive->id,
            'status' => RegistrationStatus::Approved,
        ]);

        $anon = User::factory()->create([
            'email' => 'anon@example.com',
            'is_active' => true,
            'account_status' => AccountStatus::Anonymized,
            'anonymized_at' => now(),
            'email_verified_at' => now(),
        ]);
        ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $anon->id,
            'status' => RegistrationStatus::Approved,
        ]);

        $this->assertSame(1, $service->countEligibleRecipients(
            $program,
            ProgramBroadcastAudienceMode::Statuses,
            ['approved'],
        ));
    }

    public function test_dedupes_by_user_id_and_email_case_insensitive(): void
    {
        $service = app(ProgramBroadcastService::class);
        [$program, $staff] = $this->programWithEditorStaff();

        $user = User::factory()->create([
            'email' => 'Same@Example.com',
            'is_active' => true,
            'account_status' => AccountStatus::Active,
            'email_verified_at' => now(),
            'notify_email' => false,
        ]);

        // Single registration — duplicate email check is in resolveEligibleRecipients.
        ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => RegistrationStatus::Approved,
        ]);

        $other = User::factory()->create([
            'email' => 'same@example.com',
            'is_active' => true,
            'account_status' => AccountStatus::Active,
            'email_verified_at' => now(),
        ]);
        // Unique (program, user) — different user, same email case-insensitively.
        ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $other->id,
            'status' => RegistrationStatus::Completed,
        ]);

        $draft = $service->createDraft($program, $staff, [
            'subject' => 'Dedup',
            'content' => self::TIPTAP,
            'audience_statuses' => ['approved', 'completed'],
        ]);
        $queued = $service->sendNow($draft, $staff);

        $this->assertSame(1, $queued->recipients_count);
    }

    public function test_does_not_gate_on_notify_email_preference(): void
    {
        $service = app(ProgramBroadcastService::class);
        [$program, $staff] = $this->programWithEditorStaff();

        $user = User::factory()->create([
            'email' => 'ops@example.com',
            'is_active' => true,
            'account_status' => AccountStatus::Active,
            'notify_email' => false,
            'email_verified_at' => now(),
        ]);
        ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => RegistrationStatus::Approved,
        ]);

        $this->assertTrue($service->isOperationalRecipient($user));
        $this->assertSame(1, $service->countEligibleRecipients(
            $program,
            ProgramBroadcastAudienceMode::Statuses,
            ['approved'],
        ));
    }

    public function test_chunk_job_sends_one_mail_per_recipient_and_is_idempotent(): void
    {
        Mail::fake();

        $service = app(ProgramBroadcastService::class);
        [$program, $staff] = $this->programWithEditorStaff();
        $this->registerBeneficiary($program, RegistrationStatus::Approved, 'one@example.com');
        $this->registerBeneficiary($program, RegistrationStatus::Approved, 'two@example.com');

        $draft = $service->createDraft($program, $staff, [
            'subject' => 'دفعة',
            'content' => self::TIPTAP,
        ]);

        Queue::fake();
        $queued = $service->sendNow($draft, $staff);
        Queue::assertPushed(DispatchProgramBroadcastChunksJob::class);

        // Run dispatcher + chunk synchronously for this test.
        (new DispatchProgramBroadcastChunksJob($queued->id))->handle($service);

        $ids = $queued->recipients()->pluck('id')->all();
        (new SendProgramBroadcastChunkJob($queued->id, $ids))->handle($service);
        (new SendProgramBroadcastChunkJob($queued->id, $ids))->handle($service); // retry — no double send

        Mail::assertSent(ProgramBroadcastMail::class, 2);
        $this->assertSame(2, $queued->fresh()->sent_count);
        $this->assertSame(ProgramBroadcastStatus::Completed, $queued->fresh()->status);
    }

    public function test_atomic_claim_only_one_worker_sends_mail(): void
    {
        Mail::fake();

        $service = app(ProgramBroadcastService::class);
        [$program, $staff] = $this->programWithEditorStaff();
        $this->registerBeneficiary($program, RegistrationStatus::Approved, 'race@example.com');

        Queue::fake();
        $queued = $service->sendNow($service->createDraft($program, $staff, [
            'subject' => 'سباق',
            'content' => self::TIPTAP,
        ]), $staff);

        $recipientId = (int) $queued->recipients()->value('id');

        $this->assertTrue($service->claimRecipientForSend($recipientId));
        $this->assertFalse($service->claimRecipientForSend($recipientId));
        $this->assertSame(
            ProgramBroadcastRecipientStatus::Processing,
            $queued->recipients()->findOrFail($recipientId)->status,
        );

        // Active processing claim: second worker must not send.
        (new SendProgramBroadcastChunkJob($queued->id, [$recipientId]))->handle($service);
        Mail::assertNothingSent();

        // Release back to pending and complete exactly once.
        ProgramBroadcastRecipient::query()->whereKey($recipientId)->update([
            'status' => ProgramBroadcastRecipientStatus::Pending->value,
            'updated_at' => now(),
        ]);

        (new SendProgramBroadcastChunkJob($queued->id, [$recipientId]))->handle($service);
        (new SendProgramBroadcastChunkJob($queued->id, [$recipientId]))->handle($service);

        Mail::assertSent(ProgramBroadcastMail::class, 1);
        $this->assertSame(
            ProgramBroadcastRecipientStatus::Sent,
            $queued->recipients()->findOrFail($recipientId)->status,
        );
    }

    public function test_second_job_cannot_claim_processing_or_sent_recipients(): void
    {
        Mail::fake();
        $service = app(ProgramBroadcastService::class);
        [$program, $staff] = $this->programWithEditorStaff();
        $this->registerBeneficiary($program, RegistrationStatus::Approved, 'locked@example.com');

        Queue::fake();
        $queued = $service->sendNow($service->createDraft($program, $staff, [
            'subject' => 'قفل',
            'content' => self::TIPTAP,
        ]), $staff);

        $recipientId = (int) $queued->recipients()->value('id');

        ProgramBroadcastRecipient::query()->whereKey($recipientId)->update([
            'status' => ProgramBroadcastRecipientStatus::Processing->value,
            'updated_at' => now(),
        ]);
        $this->assertFalse($service->claimRecipientForSend($recipientId));
        (new SendProgramBroadcastChunkJob($queued->id, [$recipientId]))->handle($service);
        Mail::assertNothingSent();

        ProgramBroadcastRecipient::query()->whereKey($recipientId)->update([
            'status' => ProgramBroadcastRecipientStatus::Sent->value,
            'sent_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertFalse($service->claimRecipientForSend($recipientId));
        (new SendProgramBroadcastChunkJob($queued->id, [$recipientId]))->handle($service);
        Mail::assertNothingSent();
    }

    public function test_rate_limit_429_returns_recipient_to_pending_then_succeeds_once(): void
    {
        $service = app(ProgramBroadcastService::class);
        [$program, $staff] = $this->programWithEditorStaff();
        $this->registerBeneficiary($program, RegistrationStatus::Approved, 'retry429@example.com');

        Queue::fake();
        $queued = $service->sendNow($service->createDraft($program, $staff, [
            'subject' => 'حد المعدل',
            'content' => self::TIPTAP,
        ]), $staff);

        $recipientId = (int) $queued->recipients()->value('id');
        $ids = [$recipientId];

        $mailer = new class
        {
            public int $attempts = 0;

            public function to(mixed $users, mixed $name = null): object
            {
                $attempts = &$this->attempts;

                return new class($attempts)
                {
                    public function __construct(private int &$attempts) {}

                    public function send(mixed $mailable): mixed
                    {
                        $this->attempts++;

                        if ($this->attempts === 1) {
                            throw new TransportException('HTTP 429 Too Many Requests');
                        }

                        return null;
                    }
                };
            }
        };

        Mail::swap($mailer);

        try {
            (new SendProgramBroadcastChunkJob($queued->id, $ids))->handle($service);
            $this->fail('Expected TransportException for 429');
        } catch (TransportExceptionInterface) {
            // expected — job will retry
        }

        $this->assertSame(
            ProgramBroadcastRecipientStatus::Pending,
            $queued->recipients()->findOrFail($recipientId)->status,
        );
        $this->assertSame(1, $mailer->attempts);

        (new SendProgramBroadcastChunkJob($queued->id, $ids))->handle($service);

        $this->assertSame(2, $mailer->attempts);
        $this->assertSame(
            ProgramBroadcastRecipientStatus::Sent,
            $queued->recipients()->findOrFail($recipientId)->status,
        );
        $this->assertSame(ProgramBroadcastStatus::Completed, $queued->fresh()->status);
    }

    public function test_chunk_failed_marks_remaining_failed_without_touching_sent(): void
    {
        Mail::fake();
        $service = app(ProgramBroadcastService::class);
        [$program, $staff] = $this->programWithEditorStaff();
        $this->registerBeneficiary($program, RegistrationStatus::Approved, 'kept-sent@example.com');
        $this->registerBeneficiary($program, RegistrationStatus::Approved, 'still-pending@example.com');
        $this->registerBeneficiary($program, RegistrationStatus::Approved, 'still-processing@example.com');

        Queue::fake();
        $queued = $service->sendNow($service->createDraft($program, $staff, [
            'subject' => 'استنفاد',
            'content' => self::TIPTAP,
        ]), $staff);

        $sent = $queued->recipients()->where('email', 'kept-sent@example.com')->firstOrFail();
        $pending = $queued->recipients()->where('email', 'still-pending@example.com')->firstOrFail();
        $processing = $queued->recipients()->where('email', 'still-processing@example.com')->firstOrFail();

        ProgramBroadcastRecipient::query()->whereKey($sent->id)->update([
            'status' => ProgramBroadcastRecipientStatus::Sent->value,
            'sent_at' => now(),
            'updated_at' => now(),
        ]);
        ProgramBroadcastRecipient::query()->whereKey($processing->id)->update([
            'status' => ProgramBroadcastRecipientStatus::Processing->value,
            'updated_at' => now(),
        ]);
        $queued->update(['status' => ProgramBroadcastStatus::Sending]);

        $job = new SendProgramBroadcastChunkJob($queued->id, [
            $sent->id,
            $pending->id,
            $processing->id,
        ]);
        $job->failed(new \RuntimeException('exhausted'));

        $this->assertSame(ProgramBroadcastRecipientStatus::Sent, $sent->fresh()->status);
        $this->assertSame(ProgramBroadcastRecipientStatus::Failed, $pending->fresh()->status);
        $this->assertSame(ProgramBroadcastRecipientStatus::Failed, $processing->fresh()->status);
        $this->assertSame(
            ProgramBroadcastService::ATTEMPTS_EXHAUSTED_REASON,
            $pending->fresh()->failure_reason,
        );

        $fresh = $queued->fresh();
        $this->assertSame(ProgramBroadcastStatus::CompletedWithErrors, $fresh->status);
        $this->assertNotContains($fresh->status, [
            ProgramBroadcastStatus::Sending,
            ProgramBroadcastStatus::Queued,
        ]);
        $this->assertSame(1, $fresh->sent_count);
        $this->assertSame(2, $fresh->failed_count);
    }

    public function test_stuck_processing_recipient_is_reclaimable_after_timeout(): void
    {
        Mail::fake();
        $service = app(ProgramBroadcastService::class);
        [$program, $staff] = $this->programWithEditorStaff();
        $this->registerBeneficiary($program, RegistrationStatus::Approved, 'stuck@example.com');

        Queue::fake();
        $queued = $service->sendNow($service->createDraft($program, $staff, [
            'subject' => 'عالق',
            'content' => self::TIPTAP,
        ]), $staff);

        $recipientId = (int) $queued->recipients()->value('id');
        $stuckAt = now()->subSeconds(ProgramBroadcastService::PROCESSING_STUCK_AFTER_SECONDS + 30);

        // Fresh processing must NOT be reclaimable (active claim).
        ProgramBroadcastRecipient::query()->whereKey($recipientId)->update([
            'status' => ProgramBroadcastRecipientStatus::Processing->value,
            'updated_at' => now(),
        ]);
        $this->assertFalse($service->claimRecipientForSend($recipientId));

        // Stuck past job timeout window is reclaimable via status=processing + age.
        ProgramBroadcastRecipient::query()->whereKey($recipientId)->update([
            'status' => ProgramBroadcastRecipientStatus::Processing->value,
            'updated_at' => $stuckAt,
        ]);
        $this->assertTrue($service->claimRecipientForSend($recipientId));

        // Reset to stuck processing and send via chunk — exactly one mail.
        ProgramBroadcastRecipient::query()->whereKey($recipientId)->update([
            'status' => ProgramBroadcastRecipientStatus::Processing->value,
            'updated_at' => $stuckAt,
        ]);
        (new SendProgramBroadcastChunkJob($queued->id, [$recipientId]))->handle($service);

        Mail::assertSent(ProgramBroadcastMail::class, 1);
        $this->assertSame(
            ProgramBroadcastRecipientStatus::Sent,
            $queued->recipients()->findOrFail($recipientId)->status,
        );
        $this->assertSame(ProgramBroadcastStatus::Completed, $queued->fresh()->status);
    }

    public function test_content_immutable_after_send_started(): void
    {
        Queue::fake();
        $service = app(ProgramBroadcastService::class);
        [$program, $staff] = $this->programWithEditorStaff();
        $this->registerBeneficiary($program, RegistrationStatus::Approved, 'x@example.com');

        $draft = $service->createDraft($program, $staff, [
            'subject' => 'أصلي',
            'content' => self::TIPTAP,
        ]);
        $service->sendNow($draft, $staff);

        $this->expectException(ProgramBroadcastException::class);
        $service->updateDraft($draft->fresh(), $staff, ['subject' => 'معدّل']);
    }

    public function test_cannot_delete_after_send_started(): void
    {
        Queue::fake();
        $service = app(ProgramBroadcastService::class);
        [$program, $staff] = $this->programWithEditorStaff();
        $this->registerBeneficiary($program, RegistrationStatus::Approved, 'y@example.com');

        $draft = $service->createDraft($program, $staff, [
            'subject' => 'حذف',
            'content' => self::TIPTAP,
        ]);
        $sent = $service->sendNow($draft, $staff);

        $this->assertFalse($staff->can('delete', $sent));
        $this->expectException(ProgramBroadcastException::class);
        $service->deleteDraft($sent, $staff);
    }

    public function test_concurrent_send_of_same_draft_blocked(): void
    {
        Queue::fake();
        $service = app(ProgramBroadcastService::class);
        [$program, $staff] = $this->programWithEditorStaff();
        $this->registerBeneficiary($program, RegistrationStatus::Approved, 'z@example.com');

        $draft = $service->createDraft($program, $staff, [
            'subject' => 'مزدوج',
            'content' => self::TIPTAP,
        ]);
        $service->sendNow($draft, $staff);

        $this->expectException(ProgramBroadcastException::class);
        $service->sendNow($draft->fresh(), $staff);
    }

    public function test_partial_failure_sets_completed_with_errors(): void
    {
        $service = app(ProgramBroadcastService::class);
        [$program, $staff] = $this->programWithEditorStaff();
        $this->registerBeneficiary($program, RegistrationStatus::Approved, 'ok2@example.com');
        $this->registerBeneficiary($program, RegistrationStatus::Approved, 'bad2@example.com');

        Queue::fake();
        $draft = $service->createDraft($program, $staff, [
            'subject' => 'جزئي',
            'content' => self::TIPTAP,
        ]);
        $queued = $service->sendNow($draft, $staff);

        $ok = $queued->recipients()->where('email', 'ok2@example.com')->firstOrFail();
        $bad = $queued->recipients()->where('email', 'bad2@example.com')->firstOrFail();

        $ok->update([
            'status' => ProgramBroadcastRecipientStatus::Sent,
            'sent_at' => now(),
        ]);
        $bad->update([
            'status' => ProgramBroadcastRecipientStatus::Failed,
            'failure_reason' => 'تعذّر إرسال البريد.',
        ]);

        $service->refreshAggregateCounts($queued->id);
        $fresh = $queued->fresh();

        $this->assertSame(ProgramBroadcastStatus::CompletedWithErrors, $fresh->status);
        $this->assertSame(1, $fresh->sent_count);
        $this->assertSame(1, $fresh->failed_count);
    }

    public function test_retry_failed_only_requeues_failed_recipients(): void
    {
        Queue::fake();
        Mail::fake();

        $service = app(ProgramBroadcastService::class);
        [$program, $staff] = $this->programWithEditorStaff();
        $this->registerBeneficiary($program, RegistrationStatus::Approved, 'sent@example.com');
        $this->registerBeneficiary($program, RegistrationStatus::Approved, 'fail@example.com');

        $draft = $service->createDraft($program, $staff, [
            'subject' => 'إعادة',
            'content' => self::TIPTAP,
        ]);
        $queued = $service->sendNow($draft, $staff);

        $queued->recipients()->where('email', 'sent@example.com')->update([
            'status' => ProgramBroadcastRecipientStatus::Sent,
            'sent_at' => now(),
        ]);
        $queued->recipients()->where('email', 'fail@example.com')->update([
            'status' => ProgramBroadcastRecipientStatus::Failed,
            'failure_reason' => 'خطأ مؤقت',
        ]);
        $queued->update([
            'status' => ProgramBroadcastStatus::CompletedWithErrors,
            'sent_count' => 1,
            'failed_count' => 1,
            'sending_completed_at' => now(),
        ]);

        Queue::fake();
        $retried = $service->retryFailed($queued->fresh(), $staff);

        $this->assertSame(ProgramBroadcastStatus::Queued, $retried->status);
        $this->assertSame(
            ProgramBroadcastRecipientStatus::Pending,
            $retried->recipients()->where('email', 'fail@example.com')->first()->status,
        );
        $this->assertSame(
            ProgramBroadcastRecipientStatus::Sent,
            $retried->recipients()->where('email', 'sent@example.com')->first()->status,
        );
        Queue::assertPushed(DispatchProgramBroadcastChunksJob::class);
    }

    public function test_preview_sanitizes_html_content(): void
    {
        $service = app(ProgramBroadcastService::class);
        [$program, $staff] = $this->programWithEditorStaff();

        $payload = $service->previewPayload(
            $program,
            'معاينة',
            '<p>مرحبا</p><script>alert(1)</script>',
            $staff,
        );

        $this->assertStringContainsString('مرحبا', $payload['content_html']);
        $this->assertStringNotContainsString('<script', $payload['content_html']);
    }

    public function test_copy_to_new_draft_from_sent_message(): void
    {
        Queue::fake();
        $service = app(ProgramBroadcastService::class);
        [$program, $staff] = $this->programWithEditorStaff();
        $this->registerBeneficiary($program, RegistrationStatus::Approved, 'copy@example.com');

        $draft = $service->createDraft($program, $staff, [
            'subject' => 'أصل للنسخ',
            'content' => self::TIPTAP,
            'audience_statuses' => ['approved'],
        ]);
        $sent = $service->sendNow($draft, $staff);
        $copy = $service->copyToNewDraft($sent, $staff);

        $this->assertTrue($copy->isDraft());
        $this->assertSame('أصل للنسخ', $copy->subject);
        $this->assertNotSame($sent->id, $copy->id);
    }

    public function test_audit_logs_do_not_store_full_body_or_email_lists(): void
    {
        Queue::fake();
        $service = app(ProgramBroadcastService::class);
        [$program, $staff] = $this->programWithEditorStaff();
        $this->registerBeneficiary($program, RegistrationStatus::Approved, 'audit@example.com');

        $draft = $service->createDraft($program, $staff, [
            'subject' => 'تدقيق',
            'content' => self::TIPTAP,
        ]);
        $service->sendNow($draft, $staff);

        $logs = AuditLog::query()
            ->whereIn('action', [
                'program_broadcast.draft_created',
                'program_broadcast.send_started',
            ])
            ->get();

        $this->assertGreaterThanOrEqual(2, $logs->count());

        foreach ($logs as $log) {
            $meta = is_array($log->metadata) ? json_encode($log->metadata) : (string) $log->metadata;
            $this->assertStringNotContainsString('محتوى الرسالة الجماعية', $meta);
            $this->assertStringNotContainsString('audit@example.com', $meta);
            $this->assertStringNotContainsString(self::TIPTAP, $meta);
        }
    }

    public function test_arabic_status_labels(): void
    {
        $this->assertSame('مسودة', ProgramBroadcastStatus::Draft->label());
        $this->assertSame('في الانتظار', ProgramBroadcastStatus::Queued->label());
        $this->assertSame('جارٍ الإرسال', ProgramBroadcastStatus::Sending->label());
        $this->assertSame('مكتمل', ProgramBroadcastStatus::Completed->label());
        $this->assertSame('مكتمل مع أخطاء', ProgramBroadcastStatus::CompletedWithErrors->label());
        $this->assertSame('فشل', ProgramBroadcastStatus::Failed->label());
        $this->assertSame('جارٍ الإرسال', ProgramBroadcastRecipientStatus::Processing->label());
    }

    public function test_filament_view_page_loads_broadcasts_tab_for_admin(): void
    {
        $admin = $this->adminUser();
        $program = $this->createProgram(['owner_id' => $admin->id, 'created_by' => $admin->id]);

        $this->withSession(['otp_verified' => true]);
        $this->actingAs($admin);

        Livewire::actingAs($admin)
            ->test(ViewTrainingProgram::class, ['record' => $program->getKey()])
            ->assertSuccessful();

        $this->assertContains(
            ProgramBroadcastsRelationManager::class,
            TrainingProgramResource::getRelations(),
        );
        $this->assertTrue(
            ProgramBroadcastsRelationManager::canViewForRecord($program, ViewTrainingProgram::class),
        );
    }

    public function test_create_modal_with_subject_saves_draft_without_subject_required_toast(): void
    {
        Queue::fake();
        [$program, $staff] = $this->programWithEditorStaff();
        $this->registerBeneficiary($program, RegistrationStatus::Approved, 'recv@example.com');

        $this->withSession(['otp_verified' => true]);
        $this->actingAs($staff);

        Livewire::actingAs($staff)
            ->test(ProgramBroadcastsRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->mountAction(TestAction::make('create')->table())
            ->fillForm([
                'subject' => 'تذكير بحضور البرنامج',
                'content' => json_decode(self::TIPTAP, true),
                'audience_mode' => ProgramBroadcastAudienceMode::Statuses->value,
                'audience_statuses' => ['approved', 'completed'],
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertNotified('تم حفظ المسودة');

        $broadcast = ProgramBroadcast::query()
            ->where('training_program_id', $program->id)
            ->first();

        $this->assertNotNull($broadcast);
        $this->assertSame('تذكير بحضور البرنامج', $broadcast->subject);
        $this->assertTrue($broadcast->isDraft());
    }

    public function test_create_modal_send_now_passes_filled_subject_to_service(): void
    {
        Queue::fake();
        [$program, $staff] = $this->programWithEditorStaff();
        $this->registerBeneficiary($program, RegistrationStatus::Approved, 'recv@example.com');

        $this->withSession(['otp_verified' => true]);
        $this->actingAs($staff);

        Livewire::actingAs($staff)
            ->test(ProgramBroadcastsRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->mountAction(TestAction::make('create')->table())
            ->fillForm([
                'subject' => 'تذكير بحضور البرنامج',
                'content' => json_decode(self::TIPTAP, true),
                'audience_mode' => ProgramBroadcastAudienceMode::Statuses->value,
                'audience_statuses' => ['approved', 'completed'],
            ])
            ->callMountedAction(['sendNow' => true])
            ->assertHasNoFormErrors()
            ->assertNotified('بدأ الإرسال في الخلفية');

        $broadcast = ProgramBroadcast::query()
            ->where('training_program_id', $program->id)
            ->first();

        $this->assertNotNull($broadcast);
        $this->assertSame('تذكير بحضور البرنامج', $broadcast->subject);
        $this->assertNotSame(ProgramBroadcastStatus::Draft, $broadcast->status);
        Queue::assertPushed(DispatchProgramBroadcastChunksJob::class);
    }

    public function test_create_modal_empty_subject_still_fails_validation(): void
    {
        [$program, $staff] = $this->programWithEditorStaff();

        $this->withSession(['otp_verified' => true]);
        $this->actingAs($staff);

        Livewire::actingAs($staff)
            ->test(ProgramBroadcastsRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->mountAction(TestAction::make('create')->table())
            ->fillForm([
                'subject' => '',
                'content' => json_decode(self::TIPTAP, true),
                'audience_mode' => ProgramBroadcastAudienceMode::Statuses->value,
                'audience_statuses' => ['approved', 'completed'],
            ])
            ->callMountedAction()
            ->assertHasFormErrors(['subject']);

        $this->assertSame(0, ProgramBroadcast::query()->where('training_program_id', $program->id)->count());
    }

    public function test_create_draft_blank_subject_throws_arabic_required_message(): void
    {
        $service = app(ProgramBroadcastService::class);
        [$program, $staff] = $this->programWithEditorStaff();

        try {
            $service->createDraft($program, $staff, [
                'subject' => '   ',
                'content' => self::TIPTAP,
            ]);
            $this->fail('Expected ProgramBroadcastException for blank subject.');
        } catch (ProgramBroadcastException $exception) {
            $this->assertSame('موضوع الرسالة مطلوب.', $exception->getMessage());
        }
    }

    public function test_all_recipients_mode_includes_pending_and_rejected(): void
    {
        $service = app(ProgramBroadcastService::class);
        [$program, $staff] = $this->programWithEditorStaff();
        $this->registerBeneficiary($program, RegistrationStatus::Pending, 'p@example.com');
        $this->registerBeneficiary($program, RegistrationStatus::Rejected, 'r@example.com');
        $this->registerBeneficiary($program, RegistrationStatus::Approved, 'a@example.com');

        $this->assertSame(3, $service->countEligibleRecipients(
            $program,
            ProgramBroadcastAudienceMode::All,
            null,
        ));
    }

    /**
     * @return array{0: TrainingProgram, 1: User}
     */
    private function programWithEditorStaff(): array
    {
        $owner = $this->adminUser();
        $program = $this->createProgram(['owner_id' => $owner->id, 'created_by' => $owner->id]);

        $staff = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staff->assignRole('staff');
        $staff->givePermissionTo(['programs.view', 'programs.update', 'emails.send']);
        $program->editors()->attach($staff->id);

        return [$program, $staff];
    }

    private function adminUser(): User
    {
        $admin = User::factory()->create([
            'role_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        return $admin;
    }

    private function registerBeneficiary(
        TrainingProgram $program,
        RegistrationStatus $status,
        string $email,
    ): User {
        $user = User::factory()->create([
            'email' => $email,
            'is_active' => true,
            'account_status' => AccountStatus::Active,
            'email_verified_at' => now(),
            'notify_email' => false,
        ]);

        ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => $status,
        ]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createProgram(array $overrides = []): TrainingProgram
    {
        $ownerId = $overrides['owner_id'] ?? $overrides['created_by'] ?? null;

        return TrainingProgram::query()->create(array_merge([
            'title' => 'برنامج البث',
            'slug' => 'broadcast-'.uniqid(),
            'description' => 'وصف',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Self,
            'delivery_mode' => ProgramDeliveryMode::Remote,
            'status' => ProgramStatus::Published,
            'start_date' => Carbon::parse('2026-08-01'),
            'end_date' => Carbon::parse('2026-08-15'),
            'registration_start' => Carbon::parse('2026-07-15'),
            'registration_end' => Carbon::parse('2026-08-14'),
            'capacity' => 100,
            'auto_accept_registrations' => true,
            'created_by' => $ownerId,
            'owner_id' => $ownerId,
        ], $overrides));
    }
}
