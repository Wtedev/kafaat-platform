<?php

namespace Tests\Feature;

use App\Enums\InboxNotificationType;
use App\Enums\OpportunityStatus;
use App\Enums\PathStatus;
use App\Enums\ProgramStatus;
use App\Jobs\SendTrainingProgramLaunchedNotifications;
use App\Models\InboxNotification;
use App\Models\LearningPath;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Services\Inbox\InboxNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishScheduledTrainingCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_publishes_due_programs_paths_and_opportunities(): void
    {
        $dueProgram = $this->program('برنامج مستحق', now()->startOfDay());
        $oldProgram = $this->program('برنامج قديم', now()->subDays(3));
        $futureProgram = $this->program('برنامج لاحق', now()->addDay());
        $duePath = $this->path('مسار مستحق', now()->startOfDay());
        $futurePath = $this->path('مسار لاحق', now()->addDay());
        $dueOpportunity = $this->opportunity('فرصة مستحقة', now()->startOfDay());
        $futureOpportunity = $this->opportunity('فرصة لاحقة', now()->addDay());

        $this->artisan('training:publish-scheduled')->assertSuccessful();

        $this->assertSame(ProgramStatus::Published, $dueProgram->fresh()->status);
        $this->assertSame(ProgramStatus::Published, $oldProgram->fresh()->status);
        $this->assertSame(ProgramStatus::Draft, $futureProgram->fresh()->status);
        $this->assertSame(PathStatus::Published, $duePath->fresh()->status);
        $this->assertSame(PathStatus::Draft, $futurePath->fresh()->status);
        $this->assertSame(OpportunityStatus::Published, $dueOpportunity->fresh()->status);
        $this->assertSame(OpportunityStatus::Draft, $futureOpportunity->fresh()->status);
    }

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

    private function program(string $title, mixed $publishedAt): TrainingProgram
    {
        return TrainingProgram::query()->create([
            'title' => $title,
            'slug' => 'program-'.uniqid(),
            'status' => ProgramStatus::Draft,
            'published_at' => $publishedAt,
            'notify_on_publish' => true,
        ]);
    }

    private function path(string $title, mixed $publishedAt): LearningPath
    {
        return LearningPath::query()->create([
            'title' => $title,
            'slug' => 'path-'.uniqid(),
            'status' => PathStatus::Draft,
            'published_at' => $publishedAt,
            'notify_on_publish' => true,
        ]);
    }

    private function opportunity(string $title, mixed $publishedAt): VolunteerOpportunity
    {
        return VolunteerOpportunity::query()->create([
            'title' => $title,
            'slug' => 'opportunity-'.uniqid(),
            'status' => OpportunityStatus::Draft,
            'published_at' => $publishedAt,
            'notify_on_publish' => true,
        ]);
    }
}
