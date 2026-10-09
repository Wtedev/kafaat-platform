<?php

namespace Tests\Feature;

use App\Console\Commands\PublishScheduledTrainingCommand;
use App\Enums\LearningPathKind;
use App\Enums\OpportunityStatus;
use App\Enums\PathStatus;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Filament\Resources\LearningPathResource\Pages\CreateLearningPath;
use App\Filament\Resources\TrainingProgramResource\Pages\CreateTrainingProgram;
use App\Filament\Resources\VolunteerOpportunityResource\Pages\CreateVolunteerOpportunity;
use App\Filament\Support\TrainingEntityFormSupport;
use App\Models\LearningPath;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class TrainingPublicationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_publishing_sets_published_at_to_now_and_a_future_date_cannot_schedule(): void
    {
        $now = Carbon::parse('2026-10-09 13:40:00', config('app.timezone'));
        Carbon::setTestNow($now);

        $future = '2026-12-01';
        $immediate = [
            'publish_immediately' => true,
            'published_at' => $future,
        ];

        $this->assertTrue(TrainingEntityFormSupport::wantsPublishedStatus($immediate));
        $published = TrainingEntityFormSupport::applyPublicationSchedule($immediate);
        $this->assertTrue(Carbon::parse($published['published_at'])->equalTo($now));

        $scheduled = [
            'publish_immediately' => false,
            'published_at' => $future,
        ];

        $this->assertFalse(TrainingEntityFormSupport::wantsPublishedStatus($scheduled));
        $draft = TrainingEntityFormSupport::applyPublicationSchedule($scheduled);
        $this->assertNull($draft['published_at']);
        $this->assertNotContains(
            'لا يمكن تحديد تاريخ النشر قبل اليوم.',
            TrainingEntityFormSupport::validateProgramScheduleDates($scheduled),
        );
        $this->assertFalse(collect(TrainingEntityFormSupport::publicationInlineFields())->contains(
            fn ($field): bool => $field->getName() === 'published_at',
        ));

        $staff = $this->staff();
        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($staff)
            ->test(CreateTrainingProgram::class)
            ->fillForm([
                'title' => 'برنامج يُنشر الآن',
                'program_kind' => TrainingProgramKind::Course->value,
                'competency_track' => 'self',
                'delivery_mode' => ProgramDeliveryMode::Remote->value,
                'description' => 'نبذة',
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-15',
                'registration_start' => '2026-10-01',
                'registration_end' => '2026-11-10',
                'publish_immediately' => true,
                'published_at' => $future,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $program = TrainingProgram::query()->where('title', 'برنامج يُنشر الآن')->first();
        $this->assertNotNull($program);
        $this->assertSame(ProgramStatus::Published, $program->status);
        $this->assertTrue($program->published_at?->equalTo($now));

        Livewire::actingAs($staff)
            ->test(CreateTrainingProgram::class)
            ->fillForm([
                'title' => 'برنامج مسودة بلا جدولة',
                'program_kind' => TrainingProgramKind::Course->value,
                'competency_track' => 'self',
                'delivery_mode' => ProgramDeliveryMode::Remote->value,
                'description' => 'نبذة',
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-15',
                'registration_start' => '2026-10-01',
                'registration_end' => '2026-11-10',
                'publish_immediately' => false,
                'published_at' => $future,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $draftProgram = TrainingProgram::query()->where('title', 'برنامج مسودة بلا جدولة')->first();
        $this->assertNotNull($draftProgram);
        $this->assertSame(ProgramStatus::Draft, $draftProgram->status);
        $this->assertNull($draftProgram->published_at);

        Livewire::actingAs($staff)
            ->test(CreateLearningPath::class)
            ->fillForm([
                'title' => 'مسار بلا جدولة',
                'path_kind' => LearningPathKind::TrainingPath->value,
                'publish_immediately' => false,
                'published_at' => $future,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $path = LearningPath::query()->where('title', 'مسار بلا جدولة')->first();
        $this->assertNotNull($path);
        $this->assertSame(PathStatus::Draft, $path->status);
        $this->assertNull($path->published_at);

        $coordinator = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $coordinator->assignRole('staff');

        Livewire::actingAs($staff)
            ->test(CreateVolunteerOpportunity::class)
            ->fillForm([
                'title' => 'فرصة تُنشر الآن',
                'description' => 'وصف',
                'assigned_to' => $coordinator->id,
                'publish_immediately' => true,
                'published_at' => $future,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $opportunity = VolunteerOpportunity::query()->where('title', 'فرصة تُنشر الآن')->first();
        $this->assertNotNull($opportunity);
        $this->assertSame(OpportunityStatus::Published, $opportunity->status);
        $this->assertTrue($opportunity->published_at?->equalTo($now));
    }

    public function test_there_is_no_scheduled_publish_command_or_schedule(): void
    {
        $this->assertFalse(class_exists(PublishScheduledTrainingCommand::class));

        $commands = collect(app(Schedule::class)->events())
            ->map(fn ($event): string => (string) $event->command);

        $this->assertFalse($commands->contains(
            fn (string $command): bool => str_contains($command, 'training:publish-scheduled'),
        ));
        $this->assertTrue($commands->contains(
            fn (string $command): bool => str_contains($command, 'privacy:purge-expired-exports'),
        ));

        $this->artisan('list', ['--raw' => true])
            ->expectsOutputToContain('privacy:purge-expired-exports')
            ->doesntExpectOutputToContain('training:publish-scheduled');
    }

    private function staff(): User
    {
        $user = User::factory()->create([
            'role_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('admin');

        return $user;
    }
}
