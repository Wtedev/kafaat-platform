<?php

namespace Tests\Feature\Seeders;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Models\TrainingProgram;
use Database\Seeders\VolunteerLeadersProgramDeliverySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VolunteerLeadersProgramDeliverySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sets_hybrid_delivery_and_venue_for_matching_title(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج قادة التطوع',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Published,
            'delivery_mode' => ProgramDeliveryMode::Remote,
            'venue' => null,
        ]);

        $this->seed(VolunteerLeadersProgramDeliverySeeder::class);

        $program->refresh();

        $this->assertSame(ProgramDeliveryMode::Hybrid, $program->delivery_mode);
        $this->assertSame(VolunteerLeadersProgramDeliverySeeder::VENUE, $program->venue);
        $this->assertSame('هايبرد (حضوري وعن بعد)', $program->delivery_mode->label());
    }

    public function test_re_run_is_idempotent_when_delivery_already_set(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'قادة التطوع',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Published,
            'delivery_mode' => ProgramDeliveryMode::Hybrid,
            'venue' => VolunteerLeadersProgramDeliverySeeder::VENUE,
        ]);

        $this->seed(VolunteerLeadersProgramDeliverySeeder::class);
        $this->seed(VolunteerLeadersProgramDeliverySeeder::class);

        $program->refresh();

        $this->assertSame(ProgramDeliveryMode::Hybrid, $program->delivery_mode);
        $this->assertSame(VolunteerLeadersProgramDeliverySeeder::VENUE, $program->venue);
    }
}
