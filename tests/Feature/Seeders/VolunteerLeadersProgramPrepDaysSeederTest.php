<?php

namespace Tests\Feature\Seeders;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramPrepDayType;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TrainingProgramKind;
use App\Filament\Support\RegistrationFilamentTableSupport;
use App\Models\ProgramAttendance;
use App\Models\ProgramPrepDay;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\ProgramAttendanceService;
use App\Support\VolunteerLeadersProgramPeriod;
use Database\Seeders\VolunteerLeadersProgramPrepDaysSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VolunteerLeadersProgramPrepDaysSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_official_thirty_day_calendar_idempotently(): void
    {
        $program = $this->makeVlProgram();

        // Pre-existing wrong-type / outside-period rows must be corrected / pruned.
        ProgramPrepDay::query()->create([
            'training_program_id' => $program->id,
            'prep_date' => '2026-08-03',
            'delivery_type' => ProgramPrepDayType::Remote,
            'requires_attendance' => false,
        ]);
        ProgramPrepDay::query()->create([
            'training_program_id' => $program->id,
            'prep_date' => '2026-07-01',
            'delivery_type' => ProgramPrepDayType::InPerson,
            'requires_attendance' => true,
        ]);

        $registration = $this->register($program);
        DB::table('program_attendance')->insert([
            'program_registration_id' => $registration->id,
            'training_date' => '2026-08-03',
            'status' => 'present',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $attendanceId = (int) DB::table('program_attendance')->value('id');

        $this->seed(VolunteerLeadersProgramPrepDaysSeeder::class);
        $this->seed(VolunteerLeadersProgramPrepDaysSeeder::class);

        $days = ProgramPrepDay::query()
            ->where('training_program_id', $program->id)
            ->orderBy('prep_date')
            ->get();

        $this->assertCount(30, $days);
        $this->assertSame(VolunteerLeadersProgramPeriod::periodDates(), $days->map->dateString()->all());
        $this->assertTrue($days->every(fn (ProgramPrepDay $day): bool => $day->requires_attendance === true));

        $inPerson = $days->filter(fn (ProgramPrepDay $day): bool => $day->delivery_type === ProgramPrepDayType::InPerson);
        $remote = $days->filter(fn (ProgramPrepDay $day): bool => $day->delivery_type === ProgramPrepDayType::Remote);

        $this->assertCount(6, $inPerson);
        $this->assertSame(VolunteerLeadersProgramPeriod::IN_PERSON_DATES, $inPerson->map->dateString()->values()->all());
        $this->assertCount(24, $remote);
        $this->assertTrue($days->contains(fn (ProgramPrepDay $day): bool => $day->dateString() === '2026-09-01'));
        $this->assertSame(
            ProgramPrepDayType::Remote,
            $days->firstWhere(fn (ProgramPrepDay $day): bool => $day->dateString() === '2026-09-01')?->delivery_type,
        );
        $this->assertFalse(
            ProgramPrepDay::query()
                ->where('training_program_id', $program->id)
                ->whereDate('prep_date', '2026-07-01')
                ->exists(),
        );

        $this->assertDatabaseHas('program_attendance', [
            'id' => $attendanceId,
            'program_registration_id' => $registration->id,
            'training_date' => '2026-08-03',
            'status' => 'present',
        ]);
        $this->assertSame(1, ProgramAttendance::query()->count());
    }

    public function test_vl_official_calendar_percentage_math(): void
    {
        $program = $this->makeVlProgram();
        $this->seed(VolunteerLeadersProgramPrepDaysSeeder::class);
        $registration = $this->register($program);
        $service = app(ProgramAttendanceService::class);

        $this->assertSame(30, $service->countExpectedTrainingDays($program->fresh()));
        $this->assertSame(0.0, $service->calculatePercentage($registration->fresh()));
        $this->assertSame('0.0%', RegistrationFilamentTableSupport::formatPercentage(0.0));

        $service->markPresent($registration, '2026-08-03');
        $oneOfThirty = $service->calculatePercentage($registration->fresh(['attendanceRecords', 'trainingProgram']));
        $this->assertSame(3.33, $oneOfThirty);
        $this->assertSame('3.3%', RegistrationFilamentTableSupport::formatPercentage($oneOfThirty));

        foreach (['2026-08-04', '2026-08-05', '2026-08-16', '2026-08-17', '2026-08-18'] as $date) {
            $service->markPresent($registration, $date);
        }

        $sixOfThirty = $service->calculatePercentage($registration->fresh(['attendanceRecords', 'trainingProgram']));
        $this->assertSame(20.0, $sixOfThirty);
        $this->assertSame('20.0%', RegistrationFilamentTableSupport::formatPercentage($sixOfThirty));
    }

    public function test_matches_stable_slug_not_title(): void
    {
        $program = $this->makeVlProgram([
            'title' => 'عنوان مختلف عن القادة',
        ]);

        $this->seed(VolunteerLeadersProgramPrepDaysSeeder::class);

        $this->assertSame(
            30,
            ProgramPrepDay::query()->where('training_program_id', $program->id)->count(),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeVlProgram(array $overrides = []): TrainingProgram
    {
        return TrainingProgram::query()->create(array_merge([
            'title' => 'برنامج قادة التطوع',
            'slug' => VolunteerLeadersProgramPeriod::stableSlugs()[0],
            'description' => 'وصف',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'delivery_mode' => ProgramDeliveryMode::Hybrid,
            'venue' => 'القاعة',
            'status' => ProgramStatus::Published,
            'published_at' => now()->subDay(),
            'start_date' => VolunteerLeadersProgramPeriod::PERIOD_START,
            'end_date' => VolunteerLeadersProgramPeriod::PERIOD_END,
            'capacity' => 50,
            'auto_accept_registrations' => true,
        ], $overrides));
    }

    private function register(TrainingProgram $program): ProgramRegistration
    {
        $user = User::factory()->create(['email' => 'vl-prep-'.uniqid('', true).'@example.test']);

        return ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => RegistrationStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}
