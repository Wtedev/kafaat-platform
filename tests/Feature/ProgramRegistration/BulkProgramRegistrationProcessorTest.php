<?php

namespace Tests\Feature\ProgramRegistration;

use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Jobs\BulkApproveProgramRegistrationsJob;
use App\Jobs\BulkRejectProgramRegistrationsJob;
use App\Models\AuditLog;
use App\Models\InboxNotification;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\ProgramRegistration\BulkProgramRegistrationProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class BulkProgramRegistrationProcessorTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        Notification::fake();
    }

    public function test_bulk_approve_skips_already_approved_respects_capacity_and_audits_once(): void
    {
        $admin = $this->admin();
        $program = $this->program(['capacity' => 2]);
        $already = $this->registration($program, RegistrationStatus::Approved);
        $pendingA = $this->registration($program, RegistrationStatus::Pending);
        $pendingB = $this->registration($program, RegistrationStatus::Pending);

        $summary = app(BulkProgramRegistrationProcessor::class)->approve(
            collect([$pendingA, $pendingB, $already]),
            $admin,
            $program,
            ['status' => ['value' => 'pending']],
        );

        $this->assertSame(1, $summary['approved']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertSame(1, $summary['capacity_blocked']);
        $this->assertSame(RegistrationStatus::Approved, $pendingA->fresh()->status);
        $this->assertSame(RegistrationStatus::Pending, $pendingB->fresh()->status);

        $this->assertSame(1, AuditLog::query()->where('action', 'program_registrations.bulk_approve')->count());
        $log = AuditLog::query()->where('action', 'program_registrations.bulk_approve')->first();
        $this->assertSame($admin->id, $log?->actor_id);
        $this->assertSame(3, $log?->metadata['summary']['total'] ?? null);
        $this->assertSame('pending', $log?->metadata['filters']['status']['value'] ?? null);
    }

    public function test_bulk_reject_with_shared_reason_and_audits_once(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        $pending = $this->registration($program, RegistrationStatus::Pending);
        $approved = $this->registration($program, RegistrationStatus::Approved);

        $summary = app(BulkProgramRegistrationProcessor::class)->reject(
            collect([$pending, $approved]),
            $admin,
            $program,
            'سبب تجريبي',
            ['gender' => ['value' => 'male']],
        );

        $this->assertSame(1, $summary['rejected']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertSame(RegistrationStatus::Rejected, $pending->fresh()->status);
        $this->assertSame('سبب تجريبي', $pending->fresh()->rejected_reason);
        $this->assertSame(RegistrationStatus::Approved, $approved->fresh()->status);

        $this->assertSame(1, AuditLog::query()->where('action', 'program_registrations.bulk_reject')->count());
    }

    public function test_bulk_export_audit_records_once(): void
    {
        $admin = $this->admin();
        $program = $this->program();

        app(BulkProgramRegistrationProcessor::class)->auditExport(
            $admin,
            $program,
            4,
            ['status' => ['value' => 'approved']],
        );

        $this->assertSame(1, AuditLog::query()->where('action', 'program_registrations.bulk_export')->count());
        $log = AuditLog::query()->where('action', 'program_registrations.bulk_export')->first();
        $this->assertSame(4, $log?->metadata['summary']['exported'] ?? null);
    }

    public function test_queue_jobs_process_and_notify_actor(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        $regs = collect(range(1, 3))->map(fn () => $this->registration($program, RegistrationStatus::Pending));

        (new BulkApproveProgramRegistrationsJob(
            $admin->id,
            $program->id,
            $regs->pluck('id')->all(),
            ['status' => ['value' => 'pending']],
        ))->handle(app(BulkProgramRegistrationProcessor::class));

        foreach ($regs as $reg) {
            $this->assertSame(RegistrationStatus::Approved, $reg->fresh()->status);
        }
        $this->assertTrue(
            InboxNotification::query()
                ->where('user_id', $admin->id)
                ->where('title', 'اكتمل القبول الجماعي')
                ->exists(),
        );

        $pendingReject = $this->registration($program, RegistrationStatus::Pending);
        (new BulkRejectProgramRegistrationsJob(
            $admin->id,
            $program->id,
            [$pendingReject->id],
            'رفض جماعي',
            [],
        ))->handle(app(BulkProgramRegistrationProcessor::class));

        $this->assertSame(RegistrationStatus::Rejected, $pendingReject->fresh()->status);
        $this->assertTrue(
            InboxNotification::query()
                ->where('user_id', $admin->id)
                ->where('title', 'اكتمل الرفض الجماعي')
                ->exists(),
        );
    }

    public function test_queue_threshold_constant_is_fifty(): void
    {
        $this->assertSame(50, BulkProgramRegistrationProcessor::QUEUE_THRESHOLD);
        Queue::fake();

        $this->assertTrue(class_exists(BulkApproveProgramRegistrationsJob::class));
        $this->assertTrue(class_exists(BulkRejectProgramRegistrationsJob::class));
    }

    private function admin(): User
    {
        $admin = User::factory()->create([
            'role_type' => 'employee',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        return $admin;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function program(array $overrides = []): TrainingProgram
    {
        return TrainingProgram::query()->create(array_merge([
            'title' => 'برنامج جماعي',
            'slug' => 'bulk-reg-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'capacity' => 100,
            'auto_accept_registrations' => false,
        ], $overrides));
    }

    private function registration(TrainingProgram $program, RegistrationStatus $status): ProgramRegistration
    {
        $user = User::factory()->create(['role_type' => 'beneficiary']);

        return ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => $status,
            'approved_at' => $status === RegistrationStatus::Approved ? now() : null,
        ]);
    }
}
