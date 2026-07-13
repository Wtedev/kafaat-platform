<?php

namespace Tests\Unit\Models;

use App\Models\TrainingProgram;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TrainingProgramPortalTimingLabelTest extends TestCase
{
    public function test_remaining_days_before_start(): void
    {
        $program = new TrainingProgram([
            'start_date' => Carbon::parse('2026-07-20'),
            'end_date' => Carbon::parse('2026-07-25'),
        ]);

        $this->assertSame(
            'متبق 7 أيام',
            $program->portalTimingLabel(Carbon::parse('2026-07-13')),
        );
    }

    public function test_in_progress_label(): void
    {
        $program = new TrainingProgram([
            'start_date' => Carbon::parse('2026-07-10'),
            'end_date' => Carbon::parse('2026-07-20'),
        ]);

        $this->assertSame(
            'جار',
            $program->portalTimingLabel(Carbon::parse('2026-07-13')),
        );
    }

    public function test_ended_since_days(): void
    {
        $program = new TrainingProgram([
            'start_date' => Carbon::parse('2026-07-01'),
            'end_date' => Carbon::parse('2026-07-10'),
        ]);

        $this->assertSame(
            'منته منذ 3 أيام',
            $program->portalTimingLabel(Carbon::parse('2026-07-13')),
        );
    }

    public function test_null_when_dates_missing(): void
    {
        $program = new TrainingProgram([
            'start_date' => null,
            'end_date' => null,
        ]);

        $this->assertNull($program->portalTimingLabel(Carbon::parse('2026-07-13')));
    }
}
