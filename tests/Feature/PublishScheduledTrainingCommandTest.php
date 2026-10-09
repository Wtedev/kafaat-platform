<?php

namespace Tests\Feature;

use App\Enums\InboxNotificationType;
use App\Enums\ProgramStatus;
use App\Jobs\SendTrainingProgramLaunchedNotifications;
use App\Models\InboxNotification;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Inbox\InboxNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishScheduledTrainingCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_launch_notification_is_sent_only_when_the_publish_moment_is_today(): void
    {
        $trainee = User::factory()->create([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
            'notify_email' => false,
            'notification_settings' => [
                'categories' => [
                    'programs_new' => ['in_app' => true, 'email' => false],
                ],
            ],
        ]);

        $current = TrainingProgram::query()->create([
            'title' => 'برنامج اليوم',
            'slug' => 'program-today',
            'status' => ProgramStatus::Published,
            'published_at' => now()->startOfDay(),
            'notify_on_publish' => true,
        ]);
        $old = TrainingProgram::query()->create([
            'title' => 'برنامج أقدم من اليوم',
            'slug' => 'program-old',
            'status' => ProgramStatus::Published,
            'published_at' => now()->subDay()->startOfDay(),
            'notify_on_publish' => true,
        ]);

        $inbox = app(InboxNotificationService::class);
        (new SendTrainingProgramLaunchedNotifications($current->id))->handle($inbox);
        (new SendTrainingProgramLaunchedNotifications($old->id))->handle($inbox);

        $this->assertTrue($this->hasLaunchNotice($trainee->id, $current->id));
        $this->assertFalse($this->hasLaunchNotice($trainee->id, $old->id));
    }

    private function hasLaunchNotice(int $userId, int $programId): bool
    {
        return InboxNotification::query()
            ->where('user_id', $userId)
            ->where('type', InboxNotificationType::ProgramLaunched)
            ->where('context->id', $programId)
            ->exists();
    }
}
