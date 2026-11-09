<?php

namespace Tests\Feature\Programs;

use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Jobs\SendTatawaaWaeiInviteRemindersJob;
use App\Mail\TatawaaWaeiInviteMail;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Programs\TatawaaWaeiInviteReminderService;
use App\Support\TatawaaWaeiInviteMail as Invite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TatawaaWaeiInviteReminderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_skips_people_already_registered_in_tatawaa_waei(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'تطوع واعي',
            'slug' => Invite::PROGRAM_SLUG,
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'capacity' => 100,
            'auto_accept_registrations' => false,
        ]);

        $registered = User::factory()->create([
            'email' => 'registered@example.com',
            'name' => 'مسجلة كاملة',
        ]);
        ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $registered->id,
            'status' => RegistrationStatus::Approved,
        ]);

        $recipients = [
            ['email' => 'registered@example.com', 'name' => 'مسجلة كاملة'],
            ['email' => 'open@example.com', 'name' => 'مرشحة مفتوحة'],
        ];

        $report = app(TatawaaWaeiInviteReminderService::class)->send(
            dryRun: true,
            recipients: $recipients,
        );

        $this->assertSame(2, $report['total']);
        $this->assertSame(1, $report['eligible']);
        $this->assertSame(1, $report['skipped_registered']);
        $this->assertSame(0, $report['sent']);
    }

    public function test_send_dispatches_reminder_mail_only_to_unregistered(): void
    {
        Mail::fake();

        $program = TrainingProgram::query()->create([
            'title' => 'تطوع واعي',
            'slug' => Invite::PROGRAM_SLUG,
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'capacity' => 100,
            'auto_accept_registrations' => false,
        ]);

        $registered = User::factory()->create([
            'email' => 'registered@example.com',
            'name' => 'مسجلة كاملة',
        ]);
        ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $registered->id,
            'status' => RegistrationStatus::Pending,
        ]);

        $recipients = [
            ['email' => 'registered@example.com', 'name' => 'مسجلة كاملة'],
            ['email' => 'open@example.com', 'name' => 'مرشحة مفتوحة'],
        ];

        $report = app(TatawaaWaeiInviteReminderService::class)->send(
            dryRun: false,
            recipients: $recipients,
        );

        $this->assertSame(1, $report['sent']);
        $this->assertSame(1, $report['skipped_registered']);

        Mail::assertSent(TatawaaWaeiInviteMail::class, function (TatawaaWaeiInviteMail $mail): bool {
            return $mail->hasTo('open@example.com')
                && $mail->reminder === true
                && $mail->recipientName === 'مرشحة مفتوحة';
        });
        Mail::assertNotSent(TatawaaWaeiInviteMail::class, function (TatawaaWaeiInviteMail $mail): bool {
            return $mail->hasTo('registered@example.com');
        });
    }

    public function test_command_can_queue_delayed_job_with_recipients(): void
    {
        Queue::fake();

        $path = storage_path('app/tatawaa-waei-test-recipients.json');
        file_put_contents($path, json_encode([
            ['email' => 'a@example.com', 'name' => 'A'],
            ['email' => 'b@example.com', 'name' => 'B'],
        ], JSON_THROW_ON_ERROR));

        $this->artisan('tatawaa-waei:send-invite-reminders', [
            '--path' => $path,
            '--at' => '2026-11-10 18:00',
        ])->assertSuccessful();

        Queue::assertPushed(SendTatawaaWaeiInviteRemindersJob::class, function (SendTatawaaWaeiInviteRemindersJob $job): bool {
            return count($job->recipients) === 2
                && $job->recipients[0]['email'] === 'a@example.com';
        });

        @unlink($path);
    }
}
