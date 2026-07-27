<?php

namespace Tests\Unit\Models;

use App\Models\TrainingProgram;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TrainingProgramScheduleCardRegistrationStatusTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_open_status_shows_available_plain_label(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-27')->startOfDay());

        $program = new TrainingProgram([
            'registration_start' => Carbon::parse('2026-07-22'),
            'registration_end' => Carbon::parse('2026-08-03'),
            'start_date' => Carbon::parse('2026-08-03'),
            'end_date' => Carbon::parse('2026-09-01'),
            'learning_path_id' => null,
        ]);

        $this->assertSame('متاح التسجيل', $program->scheduleCardRegistrationStatusLabel());
    }

    public function test_not_started_status_shows_plain_label(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20')->startOfDay());

        $program = new TrainingProgram([
            'registration_start' => Carbon::parse('2026-07-27'),
            'registration_end' => Carbon::parse('2026-08-03'),
            'learning_path_id' => null,
        ]);

        $this->assertSame('لم يبدأ التسجيل', $program->scheduleCardRegistrationStatusLabel());
    }

    public function test_ended_status_shows_closed_label(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10')->startOfDay());

        $program = new TrainingProgram([
            'registration_start' => Carbon::parse('2026-07-22'),
            'registration_end' => Carbon::parse('2026-08-03'),
            'start_date' => Carbon::parse('2026-08-03'),
            'end_date' => Carbon::parse('2026-09-01'),
            'learning_path_id' => null,
        ]);

        $this->assertSame('انتهى التسجيل', $program->scheduleCardRegistrationStatusLabel());
    }

    public function test_path_only_keeps_via_path_label(): void
    {
        $program = new TrainingProgram([
            'learning_path_id' => 1,
            'registration_start' => Carbon::parse('2026-07-22'),
            'registration_end' => Carbon::parse('2026-08-03'),
        ]);

        $this->assertSame('التسجيل عبر المسار', $program->scheduleCardRegistrationStatusLabel());
    }
}
