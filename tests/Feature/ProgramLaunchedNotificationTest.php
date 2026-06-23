<?php

namespace Tests\Feature;

use App\Enums\ProgramStatus;
use App\Jobs\SendTrainingProgramLaunchedNotifications;
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
            'role_type' => 'trainee',
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
            'role_type' => 'trainee',
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
}
