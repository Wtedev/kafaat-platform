<?php

namespace Tests\Feature;

use App\Enums\InboxNotificationType;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Jobs\SendTrainingProgramLaunchedNotifications;
use App\Models\InboxNotification;
use App\Models\LearningPath;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Notifications\InboxNotificationEmail;
use App\Services\Inbox\InboxNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProgramLaunchedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_launch_sends_email_when_in_app_disabled_but_notify_email_on(): void
    {
        $trainee = User::factory()->create([
            'role_type' => 'beneficiary',
            'email' => 'trainee@example.com',
            'email_verified_at' => now(),
            'is_active' => true,
            'notify_email' => true,
            'notification_settings' => [
                'categories' => [
                    'programs_new' => ['in_app' => false, 'email' => false],
                ],
            ],
        ]);

        $program = TrainingProgram::query()->create([
            'title' => 'برنامج تجريبي',
            'slug' => 'test-program-launch',
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'notify_on_publish' => true,
        ]);

        Notification::fake();

        (new SendTrainingProgramLaunchedNotifications($program->id))
            ->handle(app(InboxNotificationService::class));

        Notification::assertSentTo($trainee, InboxNotificationEmail::class);
    }

    public function test_program_launch_skips_email_when_notify_email_off(): void
    {
        User::factory()->create([
            'role_type' => 'beneficiary',
            'email' => 'trainee@example.com',
            'email_verified_at' => now(),
            'is_active' => true,
            'notify_email' => false,
        ]);

        $program = TrainingProgram::query()->create([
            'title' => 'برنامج بدون بريد',
            'slug' => 'test-program-no-mail',
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'notify_on_publish' => true,
        ]);

        Notification::fake();

        (new SendTrainingProgramLaunchedNotifications($program->id))
            ->handle(app(InboxNotificationService::class));

        Notification::assertNothingSent();
    }

    public function test_program_launch_targets_trainees_not_volunteers_or_staff_and_omits_staff_names(): void
    {
        $publisher = User::factory()->create([
            'name' => 'منسق البرامج أحمد',
            'role_type' => 'staff',
            'email' => 'staff-publisher@example.com',
            'is_active' => true,
        ]);

        $trainee = User::factory()->create([
            'role_type' => 'beneficiary',
            'email' => 'trainee-audience@example.com',
            'is_active' => true,
            'notify_email' => false,
            'notification_settings' => [
                'categories' => [
                    'programs_new' => ['in_app' => true, 'email' => false],
                ],
            ],
        ]);

        $volunteer = User::factory()->create([
            'role_type' => 'volunteer',
            'email' => 'volunteer-audience@example.com',
            'is_active' => true,
            'notify_email' => false,
            'notification_settings' => [
                'categories' => [
                    'programs_new' => ['in_app' => true, 'email' => false],
                ],
            ],
        ]);

        $program = TrainingProgram::query()->create([
            'title' => 'برنامج موجّه',
            'slug' => 'scoped-program-launch',
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'notify_on_publish' => true,
        ]);

        (new SendTrainingProgramLaunchedNotifications($program->id, $publisher->id))
            ->handle(app(InboxNotificationService::class));

        $traineeRow = InboxNotification::query()
            ->where('user_id', $trainee->id)
            ->where('type', InboxNotificationType::ProgramLaunched->value)
            ->first();

        $this->assertNotNull($traineeRow);
        $this->assertNull($traineeRow->sender_id);
        $this->assertStringNotContainsString('منسق البرامج أحمد', $traineeRow->title);
        $this->assertStringNotContainsString('منسق البرامج أحمد', (string) $traineeRow->message);

        $this->assertFalse(
            InboxNotification::query()
                ->where('user_id', $volunteer->id)
                ->where('type', InboxNotificationType::ProgramLaunched->value)
                ->exists()
        );

        $this->assertFalse(
            InboxNotification::query()
                ->where('user_id', $publisher->id)
                ->where('type', InboxNotificationType::ProgramLaunched->value)
                ->exists()
        );
    }

    public function test_path_linked_program_launch_does_not_blast_beneficiaries(): void
    {
        $trainee = User::factory()->create([
            'role_type' => 'beneficiary',
            'email' => 'path-trainee@example.com',
            'is_active' => true,
            'notification_settings' => [
                'categories' => [
                    'programs_new' => ['in_app' => true, 'email' => false],
                ],
            ],
        ]);

        $path = LearningPath::query()->create([
            'title' => 'مسار اختبار',
            'slug' => 'path-for-program',
            'status' => 'published',
        ]);

        $program = TrainingProgram::query()->create([
            'title' => 'برنامج ضمن مسار',
            'slug' => 'path-linked-program',
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'notify_on_publish' => true,
            'learning_path_id' => $path->id,
        ]);

        (new SendTrainingProgramLaunchedNotifications($program->id))
            ->handle(app(InboxNotificationService::class));

        $this->assertFalse(
            InboxNotification::query()
                ->where('user_id', $trainee->id)
                ->where('type', InboxNotificationType::ProgramLaunched->value)
                ->exists()
        );
    }

    public function test_registration_window_closed_notifies_registrants_only(): void
    {
        $registrant = User::factory()->create([
            'role_type' => 'beneficiary',
            'email' => 'registered@example.com',
            'is_active' => true,
            'notification_settings' => [
                'categories' => [
                    'reminders' => ['in_app' => true, 'email' => false],
                ],
            ],
        ]);

        $outsider = User::factory()->create([
            'role_type' => 'beneficiary',
            'email' => 'outsider@example.com',
            'is_active' => true,
            'notification_settings' => [
                'categories' => [
                    'reminders' => ['in_app' => true, 'email' => false],
                ],
            ],
        ]);

        $program = TrainingProgram::query()->create([
            'title' => 'برنامج إغلاق تسجيل',
            'slug' => 'reg-close-scoped',
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'notify_milestones' => true,
        ]);

        ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $registrant->id,
            'status' => RegistrationStatus::Pending,
        ]);

        app(InboxNotificationService::class)->registrationWindowClosedForProgram($program);

        $this->assertTrue(
            InboxNotification::query()
                ->where('user_id', $registrant->id)
                ->where('type', InboxNotificationType::RegistrationWindowClosed->value)
                ->exists()
        );

        $this->assertFalse(
            InboxNotification::query()
                ->where('user_id', $outsider->id)
                ->where('type', InboxNotificationType::RegistrationWindowClosed->value)
                ->exists()
        );
    }
}
