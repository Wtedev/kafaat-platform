<?php

namespace Tests\Feature\Seeders;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Models\TrainingProgram;
use Database\Seeders\VolunteerLeadersProgramWhatsappSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VolunteerLeadersProgramWhatsappSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sets_female_whatsapp_invite_and_enables_groups(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج قادة التطوع',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Published,
            'whatsapp_groups_enabled' => false,
            'whatsapp_group_male' => 'https://chat.whatsapp.com/D8OCZdXlodtF4YARHRR6gD?mode=gi_t',
            'whatsapp_group_female' => 'https://chat.whatsapp.com/H3CZ8v5D7SCHlGurPtauSL?mode=gi_t',
        ]);

        $this->seed(VolunteerLeadersProgramWhatsappSeeder::class);

        $program->refresh();

        $this->assertTrue($program->whatsapp_groups_enabled);
        $this->assertSame(VolunteerLeadersProgramWhatsappSeeder::FEMALE_URL, $program->whatsapp_group_female);
        $this->assertSame(
            'https://chat.whatsapp.com/D8OCZdXlodtF4YARHRR6gD?mode=gi_t',
            $program->whatsapp_group_male,
        );
    }

    public function test_re_run_is_idempotent_and_does_not_change_male(): void
    {
        $male = 'https://chat.whatsapp.com/D8OCZdXlodtF4YARHRR6gD?mode=gi_t';

        $program = TrainingProgram::query()->create([
            'title' => 'قادة التطوع',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Published,
            'whatsapp_groups_enabled' => true,
            'whatsapp_group_male' => $male,
            'whatsapp_group_female' => VolunteerLeadersProgramWhatsappSeeder::FEMALE_URL,
        ]);

        $this->seed(VolunteerLeadersProgramWhatsappSeeder::class);
        $this->seed(VolunteerLeadersProgramWhatsappSeeder::class);

        $program->refresh();

        $this->assertTrue($program->whatsapp_groups_enabled);
        $this->assertSame(VolunteerLeadersProgramWhatsappSeeder::FEMALE_URL, $program->whatsapp_group_female);
        $this->assertSame($male, $program->whatsapp_group_male);
    }
}
