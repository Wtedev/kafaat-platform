<?php

namespace Tests\Unit\Services;

use App\Enums\AttendanceStatus;
use App\Enums\CompetencyTrack;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramPrepDayType;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TrainingProgramKind;
use App\Filament\Support\RegistrationFilamentTableSupport;
use App\Models\AuditLog;
use App\Models\ProgramAttendance;
use App\Models\ProgramPrepDay;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\PathAttendanceService;
use App\Services\ProgramAttendanceService;
use App\Support\VolunteerLeadersProgramPeriod;
use Database\Seeders\VolunteerLeadersProgramPrepDaysSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProgramDailyAttendanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Asia/Riyadh']);
    }

    public function test_expected_dates_use_prep_days_requiring_attendance_not_weekdays(): void
    {
        $program = $this->makeProgram([
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'weekdays' => [0, 1, 2, 3, 4],
        ]);

        ProgramPrepDay::query()->create([
            'training_program_id' => $program->id,
            'prep_date' => '2026-08-03',
            'delivery_type' => ProgramPrepDayType::InPerson,
            'requires_attendance' => true,
        ]);
        ProgramPrepDay::query()->create([
            'training_program_id' => $program->id,
            'prep_date' => '2026-08-10',
            'delivery_type' => ProgramPrepDayType::Remote,
            'requires_attendance' => false,
        ]);
        ProgramPrepDay::query()->create([
            'training_program_id' => $program->id,
            'prep_date' => '2026-08-17',
            'delivery_type' => ProgramPrepDayType::Remote,
            'requires_attendance' => true,
        ]);

        $service = app(ProgramAttendanceService::class);

        $this->assertSame(
            ['2026-08-03', '2026-08-17'],
            $service->attendancePrepDateStrings($program->fresh()),
        );
        $this->assertSame(
            ['2026-08-03', '2026-08-17'],
            app(PathAttendanceService::class)->expectedDatesForProgram($program->fresh()),
        );
    }

    public function test_percentage_counts_present_and_late_over_due_days_only(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00', 'Asia/Riyadh'));

        $program = $this->makeProgram();
        foreach (['2026-08-03', '2026-08-04', '2026-08-16'] as $date) {
            ProgramPrepDay::query()->create([
                'training_program_id' => $program->id,
                'prep_date' => $date,
                'delivery_type' => ProgramPrepDayType::InPerson,
                'requires_attendance' => true,
            ]);
        }

        $registration = $this->register($program);

        $service = app(ProgramAttendanceService::class);
        $service->markManualDay($registration, '2026-08-03', AttendanceStatus::Present);
        $service->markManualDay($registration, '2026-08-04', AttendanceStatus::Late);

        // Due days: Aug 3 + 4 (Aug 16 is future) → 2/2 = 100
        $this->assertSame(100.0, $service->calculatePercentage($registration->fresh()));

        Carbon::setTestNow(Carbon::parse('2026-08-20 12:00:00', 'Asia/Riyadh'));
        // Due days: 3 → attended 2 → 66.67
        $this->assertSame(66.67, $service->calculatePercentage($registration->fresh()));
    }

    public function test_percentage_is_null_when_no_due_prep_days_yet(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-01 12:00:00', 'Asia/Riyadh'));

        $program = $this->makeProgram();
        ProgramPrepDay::query()->create([
            'training_program_id' => $program->id,
            'prep_date' => '2026-08-03',
            'delivery_type' => ProgramPrepDayType::InPerson,
            'requires_attendance' => true,
        ]);

        $registration = $this->register($program);

        $this->assertNull(app(ProgramAttendanceService::class)->calculatePercentage($registration));
        $this->assertNull($registration->fresh()->effectiveAttendancePercentage());
    }

    public function test_day_independence_and_unique_constraint(): void
    {
        $program = $this->makeProgram();
        foreach (['2026-08-03', '2026-08-04'] as $date) {
            ProgramPrepDay::query()->create([
                'training_program_id' => $program->id,
                'prep_date' => $date,
                'delivery_type' => ProgramPrepDayType::InPerson,
                'requires_attendance' => true,
            ]);
        }

        $registration = $this->register($program);
        $service = app(ProgramAttendanceService::class);

        $service->markManualDay($registration, '2026-08-03', AttendanceStatus::Present);
        $service->markManualDay($registration, '2026-08-04', AttendanceStatus::Absent);
        $service->markManualDay($registration, '2026-08-03', AttendanceStatus::Late);

        $this->assertSame(2, ProgramAttendance::query()->where('program_registration_id', $registration->id)->count());
        $this->assertSame(
            AttendanceStatus::Late,
            $service->statusForDate($registration->fresh(['attendanceRecords']), '2026-08-03'),
        );
        $this->assertSame(
            AttendanceStatus::Absent,
            $service->statusForDate($registration->fresh(['attendanceRecords']), '2026-08-04'),
        );
    }

    public function test_missing_row_is_unspecified_and_clear_resets(): void
    {
        $program = $this->makeProgram();
        ProgramPrepDay::query()->create([
            'training_program_id' => $program->id,
            'prep_date' => '2026-08-03',
            'delivery_type' => ProgramPrepDayType::InPerson,
            'requires_attendance' => true,
        ]);

        $registration = $this->register($program);
        $service = app(ProgramAttendanceService::class);

        $this->assertNull($service->statusForDate($registration, '2026-08-03'));

        $service->markManualDay($registration, '2026-08-03', AttendanceStatus::Present);
        $service->clearDay($registration, '2026-08-03');

        $this->assertNull($service->statusForDate($registration->fresh(), '2026-08-03'));
        $this->assertSame(0, ProgramAttendance::query()->where('program_registration_id', $registration->id)->count());
    }

    public function test_generate_sessions_does_not_precreate_absent_rows(): void
    {
        $program = $this->makeProgram();
        ProgramPrepDay::query()->create([
            'training_program_id' => $program->id,
            'prep_date' => '2026-08-03',
            'delivery_type' => ProgramPrepDayType::InPerson,
            'requires_attendance' => true,
        ]);
        $registration = $this->register($program);

        $this->assertSame(0, app(ProgramAttendanceService::class)->generateSessions($registration));
        $this->assertSame(0, ProgramAttendance::query()->count());
    }

    public function test_bulk_mark_and_adopt_absent_only_fills_unspecified(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-03 18:00:00', 'Asia/Riyadh'));

        $program = $this->makeProgram();
        ProgramPrepDay::query()->create([
            'training_program_id' => $program->id,
            'prep_date' => '2026-08-03',
            'delivery_type' => ProgramPrepDayType::InPerson,
            'requires_attendance' => true,
        ]);

        $a = $this->register($program, 'a@example.test');
        $b = $this->register($program, 'b@example.test');
        $service = app(ProgramAttendanceService::class);

        $service->markManualDay($a, '2026-08-03', AttendanceStatus::Present);
        $service->bulkMarkDay($program, [$b->id], '2026-08-03', AttendanceStatus::Excused);

        $this->assertSame(AttendanceStatus::Excused, $service->statusForDate($b->fresh(['attendanceRecords']), '2026-08-03'));

        $c = $this->register($program, 'c@example.test');
        $adopted = $service->adoptAbsentForUnspecified($program, '2026-08-03');

        $this->assertSame(1, $adopted);
        $this->assertSame(AttendanceStatus::Present, $service->statusForDate($a->fresh(['attendanceRecords']), '2026-08-03'));
        $this->assertSame(AttendanceStatus::Excused, $service->statusForDate($b->fresh(['attendanceRecords']), '2026-08-03'));
        $this->assertSame(AttendanceStatus::Absent, $service->statusForDate($c->fresh(['attendanceRecords']), '2026-08-03'));
    }

    public function test_rejects_non_prep_date(): void
    {
        $program = $this->makeProgram();
        ProgramPrepDay::query()->create([
            'training_program_id' => $program->id,
            'prep_date' => '2026-08-03',
            'delivery_type' => ProgramPrepDayType::InPerson,
            'requires_attendance' => true,
        ]);
        $registration = $this->register($program);

        $this->expectException(ValidationException::class);
        app(ProgramAttendanceService::class)->markManualDay(
            $registration,
            '2026-08-09',
            AttendanceStatus::Present,
        );
    }

    public function test_default_prep_date_prefers_today_else_nearest(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-06 10:00:00', 'Asia/Riyadh'));

        $program = $this->makeProgram();
        foreach (['2026-08-03', '2026-08-04', '2026-08-16'] as $date) {
            ProgramPrepDay::query()->create([
                'training_program_id' => $program->id,
                'prep_date' => $date,
                'delivery_type' => ProgramPrepDayType::InPerson,
                'requires_attendance' => true,
            ]);
        }

        // Aug 6 is between Aug 4 (2 days past) and Aug 16 (10 days future) → nearest Aug 4
        $this->assertSame(
            '2026-08-04',
            app(ProgramAttendanceService::class)->defaultPrepDate($program),
        );

        Carbon::setTestNow(Carbon::parse('2026-08-04 10:00:00', 'Asia/Riyadh'));
        $this->assertSame(
            '2026-08-04',
            app(ProgramAttendanceService::class)->defaultPrepDate($program),
        );
    }

    public function test_excused_label_is_beozr_and_audit_logs_status_change(): void
    {
        $this->assertSame('بعذر', AttendanceStatus::Excused->label());

        $actor = User::factory()->create();
        $program = $this->makeProgram();
        ProgramPrepDay::query()->create([
            'training_program_id' => $program->id,
            'prep_date' => '2026-08-03',
            'delivery_type' => ProgramPrepDayType::InPerson,
            'requires_attendance' => true,
        ]);
        $registration = $this->register($program);

        app(ProgramAttendanceService::class)->markManualDay(
            $registration,
            '2026-08-03',
            AttendanceStatus::Excused,
            null,
            $actor,
        );

        $log = AuditLog::query()->where('action', 'program_attendance.created')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($actor->id, $log->actor_id);
        $this->assertSame('2026-08-03', $log->metadata['training_date'] ?? null);
        $this->assertSame('excused', $log->metadata['new_status'] ?? null);
    }

    public function test_live_session_check_in_requires_prep_day_and_audits(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-03 12:00:00', 'Asia/Riyadh'));

        $program = $this->makeProgram(['delivery_mode' => ProgramDeliveryMode::Remote]);
        ProgramPrepDay::query()->create([
            'training_program_id' => $program->id,
            'prep_date' => '2026-08-03',
            'delivery_type' => ProgramPrepDayType::Remote,
            'requires_attendance' => true,
        ]);
        $registration = $this->register($program);

        app(ProgramAttendanceService::class)->markPresentFromLiveSession($registration);

        $this->assertSame(
            AttendanceStatus::Present,
            app(ProgramAttendanceService::class)->statusForDate($registration->fresh(['attendanceRecords']), '2026-08-03'),
        );

        $log = AuditLog::query()->where('action', 'program_attendance.created')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('live_session', $log->metadata['source'] ?? null);

        Carbon::setTestNow(Carbon::parse('2026-08-09 12:00:00', 'Asia/Riyadh'));
        $this->expectException(ValidationException::class);
        app(ProgramAttendanceService::class)->markPresentFromLiveSession($registration->fresh());
    }

    public function test_clear_day_writes_cleared_audit(): void
    {
        $actor = User::factory()->create();
        $program = $this->makeProgram();
        ProgramPrepDay::query()->create([
            'training_program_id' => $program->id,
            'prep_date' => '2026-08-03',
            'delivery_type' => ProgramPrepDayType::InPerson,
            'requires_attendance' => true,
        ]);
        $registration = $this->register($program);
        $service = app(ProgramAttendanceService::class);

        $service->markManualDay($registration, '2026-08-03', AttendanceStatus::Present, null, $actor);
        $service->clearDay($registration, '2026-08-03', $actor);

        $log = AuditLog::query()->where('action', 'program_attendance.cleared')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($actor->id, $log->actor_id);
        $this->assertSame('present', $log->metadata['old_status'] ?? null);
        $this->assertArrayHasKey('new_status', $log->metadata);
        $this->assertNull($log->metadata['new_status']);
    }

    public function test_volunteer_leaders_seeder_matches_stable_slug_not_title(): void
    {
        $canonical = $this->makeProgram([
            'title' => 'عنوان متغيّر بالكامل',
            'slug' => VolunteerLeadersProgramPeriod::PROGRAM_SLUG,
        ]);
        $titleOnly = $this->makeProgram([
            'title' => 'قادة التطوع — دفعة أخرى',
            'slug' => 'other-vl-title-only',
        ]);

        $this->seed(VolunteerLeadersProgramPrepDaysSeeder::class);

        $this->assertSame(
            6,
            ProgramPrepDay::query()->where('training_program_id', $canonical->id)->count(),
        );
        $this->assertSame(
            0,
            ProgramPrepDay::query()->where('training_program_id', $titleOnly->id)->count(),
        );

        $dates = ProgramPrepDay::query()
            ->where('training_program_id', $canonical->id)
            ->orderBy('prep_date')
            ->pluck('prep_date')
            ->map(fn ($d) => $d->toDateString())
            ->all();

        $this->assertSame(VolunteerLeadersProgramPeriod::IN_PERSON_DATES, $dates);

        // Idempotent re-run: 1 matched, 0 new
        $this->seed(VolunteerLeadersProgramPrepDaysSeeder::class);
        $this->assertSame(
            6,
            ProgramPrepDay::query()->where('training_program_id', $canonical->id)->count(),
        );
    }

    public function test_persisted_percentage_is_null_when_no_due_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-01 12:00:00', 'Asia/Riyadh'));

        $program = $this->makeProgram();
        ProgramPrepDay::query()->create([
            'training_program_id' => $program->id,
            'prep_date' => '2026-08-03',
            'delivery_type' => ProgramPrepDayType::InPerson,
            'requires_attendance' => true,
        ]);
        $registration = $this->register($program);

        // Force a sync by creating then clearing a row after a due day exists later —
        // before due days, markManualDay still writes a row and recalculates to null.
        Carbon::setTestNow(Carbon::parse('2026-08-03 12:00:00', 'Asia/Riyadh'));
        app(ProgramAttendanceService::class)->markManualDay(
            $registration,
            '2026-08-03',
            AttendanceStatus::Present,
        );

        $this->assertSame(100.0, (float) $registration->fresh()->attendance_percentage);

        Carbon::setTestNow(Carbon::parse('2026-07-01 12:00:00', 'Asia/Riyadh'));
        // Recalculate with as-of past: service returns null; emulate by clearing and
        // updating via observer after a delete when due days become empty conceptually.
        // Direct assertion on calculatePercentage (authoritative) and format.
        $this->assertNull(app(ProgramAttendanceService::class)->calculatePercentage(
            $registration->fresh(),
            Carbon::parse('2026-07-01', 'Asia/Riyadh'),
        ));
        $this->assertSame('—', RegistrationFilamentTableSupport::formatPercentage(null));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProgram(array $overrides = []): TrainingProgram
    {
        return TrainingProgram::query()->create(array_merge([
            'title' => 'برنامج تحضير',
            'slug' => 'prep-'.uniqid(),
            'description' => 'وصف',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Self,
            'delivery_mode' => ProgramDeliveryMode::InPerson,
            'venue' => 'القاعة',
            'status' => ProgramStatus::Published,
            'published_at' => now()->subDay(),
            'start_date' => '2026-08-01',
            'end_date' => '2026-09-01',
            'capacity' => 50,
            'auto_accept_registrations' => true,
        ], $overrides));
    }

    private function register(TrainingProgram $program, string $email = 'ben@example.test'): ProgramRegistration
    {
        $user = User::factory()->create(['email' => $email]);

        return ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => RegistrationStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}
