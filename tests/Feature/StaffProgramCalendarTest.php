<?php

namespace Tests\Feature;

use App\Enums\ProgramPrepDayType;
use App\Enums\ProgramStatus;
use App\Models\TrainingProgram;
use App\Services\StaffUi\StaffProgramCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StaffProgramCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_registration_program_days_and_attendance_days(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج',
            'slug' => 'cal-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'registration_start' => '2026-11-25',
            'registration_end' => '2026-11-30',
            'start_date' => '2026-12-06',
            'end_date' => '2026-12-17',
            'weekdays' => [0, 2],
        ]);
        $program->prepDays()->create(['prep_date' => '2026-12-06', 'delivery_type' => ProgramPrepDayType::InPerson, 'requires_attendance' => true]);
        $program->prepDays()->create(['prep_date' => '2026-12-08', 'delivery_type' => ProgramPrepDayType::Remote, 'requires_attendance' => true]);
        $program->attendanceLinks()->create(['name' => 'المحاضرة الثالثة', 'open_minutes' => 10, 'opens_at' => Carbon::parse('2026-12-13 09:00', config('app.timezone'))]);

        $calendar = app(StaffProgramCalendar::class)->build($program, Carbon::parse('2026-12-10'));

        $this->assertTrue($calendar['has_any']);
        $this->assertSame(['2026-11', '2026-12'], array_column($calendar['months'], 'key'));
        $this->assertSame(1, $calendar['initial']);

        $days = collect($calendar['months'])
            ->flatMap(fn (array $m) => collect($m['weeks'])->flatten(1))
            ->filter()
            ->keyBy('date');

        $this->assertTrue($days['2026-11-25']['registration']);
        $this->assertFalse($days['2026-12-01']['registration']);
        $this->assertTrue($days['2026-12-06']['program']);   // Sunday
        $this->assertFalse($days['2026-12-07']['program']);  // Monday is not a program day
        $this->assertTrue($days['2026-12-08']['program']);   // Tuesday
        $this->assertFalse($days['2026-12-20']['program']);  // after the end date
        $this->assertTrue($days['2026-12-06']['qr']);
        $this->assertSame(['تحضير عن بُعد'], $days['2026-12-08']['links']);
        $this->assertSame(['المحاضرة الثالثة'], $days['2026-12-13']['links']);
        $this->assertTrue($days['2026-12-10']['today']);

        // Weeks start on Sunday: 1 Nov 2026 is a Sunday, 1 Dec 2026 a Tuesday.
        $this->assertSame('2026-11-01', $calendar['months'][0]['weeks'][0][0]['date']);
        $this->assertNull($calendar['months'][1]['weeks'][0][1]);
        $this->assertSame('2026-12-01', $calendar['months'][1]['weeks'][0][2]['date']);
    }

    public function test_program_without_dates_has_no_calendar(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'بلا تواريخ',
            'slug' => 'cal-empty-'.uniqid(),
            'status' => ProgramStatus::Draft,
        ]);

        $this->assertFalse(app(StaffProgramCalendar::class)->build($program)['has_any']);
    }
}
