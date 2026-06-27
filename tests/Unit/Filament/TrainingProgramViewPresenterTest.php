<?php

namespace Tests\Unit\Filament;

use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Filament\Support\TrainingProgramViewPresenter;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class TrainingProgramViewPresenterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_present_includes_core_overview_and_stats(): void
    {
        $owner = new User(['name' => 'لمى المشيقح']);
        $owner->id = 1;

        $program = $this->mockProgram();
        $program->fill([
            'title' => 'برنامج تجريبي',
            'program_kind' => TrainingProgramKind::Course,
            'status' => ProgramStatus::Published,
            'owner_id' => 1,
            'capacity' => 59,
            'auto_accept_registrations' => true,
            'start_date' => Carbon::parse('2026-06-01'),
            'end_date' => Carbon::parse('2026-08-10'),
            'registration_start' => Carbon::parse('2026-02-21'),
            'registration_end' => Carbon::parse('2026-08-30'),
            'published_at' => Carbon::parse('2026-02-01 10:00:00'),
        ]);
        $program->setRelation('owner', $owner);
        $program->setRelation('editors', collect([$owner]));

        $presented = TrainingProgramViewPresenter::present($program);

        $this->assertSame('16', $presented['stats'][0]['value']);
        $this->assertSame('دورة تدريبية', $presented['sections'][0]['rows'][2]['value']);
        $this->assertSame('منشور', $presented['sections'][0]['rows'][0]['value']);
    }

    public function test_present_omits_empty_description_section(): void
    {
        $program = $this->mockProgram();
        $program->fill([
            'description' => null,
            'status' => ProgramStatus::Draft,
            'program_kind' => TrainingProgramKind::Workshop,
        ]);

        $titles = array_column(TrainingProgramViewPresenter::present($program)['sections'], 'title');

        $this->assertNotContains('نبذة عن البرنامج', $titles);
    }

    public function test_present_hides_duplicate_team_row_when_editor_is_owner(): void
    {
        $owner = new User(['name' => 'منسق واحد']);
        $owner->id = 2;

        $program = $this->mockProgram();
        $program->fill([
            'status' => ProgramStatus::Draft,
            'program_kind' => TrainingProgramKind::Course,
            'owner_id' => 2,
        ]);
        $program->setRelation('owner', $owner);
        $program->setRelation('editors', collect([$owner]));

        $teamSection = collect(TrainingProgramViewPresenter::present($program)['sections'])
            ->firstWhere('title', 'الفريق');

        $this->assertCount(1, $teamSection['rows']);
    }

    private function mockProgram(): TrainingProgram
    {
        $program = Mockery::mock(TrainingProgram::class)->makePartial();
        $program->shouldReceive('totalRegistrationsCount')->andReturn(16);
        $program->shouldReceive('approvedRegistrationsCount')->andReturn(8);
        $program->shouldReceive('completedRegistrationsCount')->andReturn(2);
        $program->id = 10;
        $program->setRelation('editors', collect());

        return $program;
    }
}
