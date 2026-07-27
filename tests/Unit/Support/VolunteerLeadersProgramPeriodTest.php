<?php

namespace Tests\Unit\Support;

use App\Enums\ProgramStatus;
use App\Models\TrainingProgram;
use App\Support\VolunteerLeadersProgramPeriod;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VolunteerLeadersProgramPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_formats_grouped_in_person_dates(): void
    {
        $this->assertSame(
            ['3–4', '8', '16–18'],
            VolunteerLeadersProgramPeriod::inPersonDayGroups(),
        );

        $this->assertSame(
            '3–4، 8، 16–18 أغسطس 2026',
            VolunteerLeadersProgramPeriod::formatInPersonDatesLabel(),
        );
    }

    public function test_sidebar_labels_for_volunteer_leaders_program(): void
    {
        $this->assertSame('3–8 أغسطس، 16–18 أغسطس', VolunteerLeadersProgramPeriod::inPersonDaysLabel());
        $this->assertSame('المتبقي من أيام الفترة', VolunteerLeadersProgramPeriod::remoteDaysLabel());

        $program = TrainingProgram::query()->create([
            'title' => 'قادة التطوع',
            'slug' => 'period-test',
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'start_date' => Carbon::parse('2026-08-03'),
            'end_date' => Carbon::parse('2026-09-01'),
            'learning_path_id' => null,
        ]);

        $this->assertTrue(VolunteerLeadersProgramPeriod::applies($program));
    }

    public function test_does_not_apply_to_other_programs(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج آخر',
            'slug' => 'other-period-test',
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'start_date' => Carbon::parse('2026-08-03'),
            'end_date' => Carbon::parse('2026-09-01'),
            'learning_path_id' => null,
        ]);

        $this->assertFalse(VolunteerLeadersProgramPeriod::applies($program));
    }
}
