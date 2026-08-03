<?php

namespace Tests\Unit\Services;

use App\Enums\AttendanceStatus;
use App\Enums\CompetencyTrack;
use App\Enums\PathStatus;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramPrepDayType;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TrainingProgramKind;
use App\Filament\Support\RegistrationFilamentTableSupport;
use App\Models\AuditLog;
use App\Models\LearningPath;
use App\Models\PathRegistration;
use App\Models\ProgramAttendance;
use App\Models\ProgramPrepDay;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\AttendanceLiveSessionService;
use App\Services\PathAttendanceService;
use App\Services\ProgramAttendanceService;
use App\Support\VolunteerLeadersProgramPeriod;
use Database\Seeders\VolunteerLeadersProgramPrepDaysSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

    public function test_missing_row_displays_as_not_present(): void
    {
        $program = $this->makeProgram();
        $this->addDay($program, '2026-08-03');
        $registration = $this->register($program);
        $service = app(ProgramAttendanceService::class);

        $this->assertFalse($service->isPresentOnDate($registration, '2026-08-03'));
        $this->assertSame('لم يحضر', $service->displayLabelForDate($registration, '2026-08-03'));
        $this->assertNull($service->statusForDate($registration, '2026-08-03'));
    }

    public function test_manual_present_creates_row_and_clear_deletes_it_with_audit(): void
    {
        $admin = User::factory()->create();
        $program = $this->makeProgram();
        $this->addDay($program, '2026-08-03');
        $registration = $this->register($program);
        $service = app(ProgramAttendanceService::class);

        $service->markPresent($registration, '2026-08-03', $admin);
        $this->assertTrue($service->isPresentOnDate($registration->fresh(['attendanceRecords']), '2026-08-03'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'program_attendance.created',
        ]);
        $this->assertTrue(
            AuditLog::query()->where('action', 'program_attendance.created')
                ->get()
                ->contains(fn (AuditLog $log): bool => ($log->metadata['source'] ?? null) === 'manual')
        );

        $service->clearDay($registration, '2026-08-03', $admin);
        $this->assertDatabaseMissing('program_attendance', [
            'program_registration_id' => $registration->id,
        ]);
        $this->assertTrue(
            AuditLog::query()->where('action', 'program_attendance.cleared')
                ->get()
                ->contains(fn (AuditLog $log): bool => ($log->metadata['source'] ?? null) === 'manual')
        );
    }

    public function test_all_prep_days_count_including_remote_and_future(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 12:00:00', 'Asia/Riyadh'));

        $program = $this->makeProgram();
        $this->addDay($program, '2026-08-03', ProgramPrepDayType::InPerson);
        $this->addDay($program, '2026-08-10', ProgramPrepDayType::Remote); // future remote
        $this->addDay($program, '2026-08-17', ProgramPrepDayType::Remote);

        // Legacy false flag must still count (model forces true on save; seed via DB for realism).
        DB::table('program_prep_days')->insert([
            'training_program_id' => $program->id,
            'prep_date' => '2026-08-20',
            'delivery_type' => ProgramPrepDayType::Remote->value,
            'requires_attendance' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(ProgramAttendanceService::class);
        $dates = $service->attendancePrepDateStrings($program->fresh());

        $this->assertSame(
            ['2026-08-03', '2026-08-10', '2026-08-17', '2026-08-20'],
            $dates,
        );

        $registration = $this->register($program);
        $service->markPresent($registration, '2026-08-03');

        // 1 present / 4 total (including future) = 25%
        $this->assertSame(25.0, $service->calculatePercentage($registration->fresh()));
        $this->assertSame(
            ['2026-08-03', '2026-08-10', '2026-08-17', '2026-08-20'],
            app(PathAttendanceService::class)->expectedDatesForProgram($program->fresh()),
        );
    }

    public function test_percentage_zero_with_days_and_dash_without(): void
    {
        $empty = $this->makeProgram(['slug' => 'empty-'.uniqid()]);
        $registrationEmpty = $this->register($empty, 'empty@example.test');
        $service = app(ProgramAttendanceService::class);

        $this->assertNull($service->calculatePercentage($registrationEmpty));
        $this->assertSame('—', RegistrationFilamentTableSupport::formatPercentage(null));

        $program = $this->makeProgram();
        foreach (['2026-08-03', '2026-08-04'] as $date) {
            $this->addDay($program, $date);
        }
        $registration = $this->register($program);

        $this->assertSame(0.0, $service->calculatePercentage($registration));
        $this->assertSame('0.0%', RegistrationFilamentTableSupport::formatPercentage(0.0));
        $this->assertSame('0 من 2', RegistrationFilamentTableSupport::programAttendanceSummary($registration->fresh(['trainingProgram'])));
    }

    public function test_path_attendance_statuses_unchanged(): void
    {
        $path = LearningPath::query()->create([
            'title' => 'مسار',
            'slug' => 'path-'.uniqid(),
            'description' => 'وصف',
            'status' => PathStatus::Published,
            'published_at' => now(),
        ]);
        $user = User::factory()->create();
        $pathReg = PathRegistration::query()->create([
            'learning_path_id' => $path->id,
            'user_id' => $user->id,
            'status' => RegistrationStatus::Approved,
            'approved_at' => now(),
        ]);

        app(PathAttendanceService::class)->markManualDay(
            $pathReg,
            '2026-08-03',
            AttendanceStatus::Late,
            'path late ok',
        );

        $this->assertDatabaseHas('path_attendance', [
            'path_registration_id' => $pathReg->id,
            'status' => AttendanceStatus::Late->value,
        ]);
        $this->assertContains(AttendanceStatus::Late, AttendanceStatus::cases());
        $this->assertContains(AttendanceStatus::Absent, AttendanceStatus::cases());
    }

    public function test_qr_uses_server_today_only_and_rejects_forged_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-03 10:00:00', 'Asia/Riyadh'));
        $program = $this->makeProgram();
        $this->addDay($program, '2026-08-03', ProgramPrepDayType::InPerson);
        $this->addDay($program, '2026-08-04', ProgramPrepDayType::InPerson);
        $registration = $this->register($program);
        $pass = sprintf('KAFAAT-P%d-R%d', $program->id, $registration->id);
        $service = app(ProgramAttendanceService::class);

        $result = $service->markPresentFromPass($program, $pass, prepDate: '2026-08-04');
        $this->assertTrue($result['ok']);
        $this->assertSame('marked', $result['reason']);
        $this->assertTrue(
            ProgramAttendance::query()
                ->where('program_registration_id', $registration->id)
                ->whereDate('training_date', '2026-08-03')
                ->where('status', AttendanceStatus::Present->value)
                ->exists()
        );
        $this->assertFalse(
            ProgramAttendance::query()
                ->where('program_registration_id', $registration->id)
                ->whereDate('training_date', '2026-08-04')
                ->exists()
        );
        $this->assertTrue(
            AuditLog::query()->where('action', 'program_attendance.created')
                ->get()
                ->contains(fn (AuditLog $log): bool => ($log->metadata['source'] ?? null) === 'qr')
        );

        Carbon::setTestNow(Carbon::parse('2026-08-10 10:00:00', 'Asia/Riyadh'));
        $this->addDay($program, '2026-08-10', ProgramPrepDayType::Remote);
        $remoteResult = $service->markPresentFromPass($program, $pass);
        $this->assertFalse($remoteResult['ok']);
        $this->assertSame('not_in_person', $remoteResult['reason']);
    }

    public function test_remote_session_requires_today_remote_day_and_links_prep_day(): void
    {
        $admin = User::factory()->create();
        $program = $this->makeProgram(['delivery_mode' => ProgramDeliveryMode::Hybrid]);
        $this->addDay($program, '2026-08-03', ProgramPrepDayType::InPerson);
        $remoteDay = $this->addDay($program, '2026-08-10', ProgramPrepDayType::Remote);
        $registration = $this->register($program);
        $live = app(AttendanceLiveSessionService::class);

        Carbon::setTestNow(Carbon::parse('2026-08-03 12:00:00', 'Asia/Riyadh'));
        try {
            $live->startProgramRemoteSession($program, $admin);
            $this->fail('Expected ValidationException for in-person day');
        } catch (ValidationException) {
            // expected
        }

        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00', 'Asia/Riyadh'));
        $session = $live->startProgramRemoteSession($program, $admin);
        $this->assertSame($remoteDay->id, $session->program_prep_day_id);
        $this->assertSame('2026-08-10', $session->session_date->toDateString());

        $again = $live->startProgramRemoteSession($program, $admin);
        $this->assertSame($session->id, $again->id);

        $live->checkInProgram($session, $registration);
        $this->assertTrue(app(ProgramAttendanceService::class)->isPresentOnDate(
            $registration->fresh(['attendanceRecords']),
            '2026-08-10',
        ));
        $this->assertTrue(
            AuditLog::query()->where('action', 'program_attendance.created')
                ->get()
                ->contains(fn (AuditLog $log): bool => ($log->metadata['source'] ?? null) === 'remote_session')
        );

        // Idempotent second check-in
        $live->checkInProgram($session, $registration);
        $this->assertSame(1, ProgramAttendance::query()->where('program_registration_id', $registration->id)->count());

        $expired = $session->fresh();
        $expired->forceFill(['expires_at' => now()->subMinutes(10)])->save();
        $this->assertFalse($expired->fresh()->isActive());

        try {
            $live->checkInProgram($expired->fresh(), $registration);
            $this->fail('Expected ValidationException for expired session');
        } catch (ValidationException) {
            // expected
        }
    }

    public function test_legacy_status_migration_keeps_present_and_deletes_absent_excused(): void
    {
        $program = $this->makeProgram();
        $this->addDay($program, '2026-08-03');
        $this->addDay($program, '2026-08-04');
        $this->addDay($program, '2026-08-05');
        $this->addDay($program, '2026-08-06');
        $registration = $this->register($program);

        // Insert legacy statuses directly (bypass service).
        foreach ([
            ['2026-08-03', 'present'],
            ['2026-08-04', 'late'],
            ['2026-08-05', 'absent'],
            ['2026-08-06', 'excused'],
        ] as [$date, $status]) {
            DB::table('program_attendance')->insert([
                'program_registration_id' => $registration->id,
                'training_date' => $date,
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('path_attendance')->insert([
            'path_registration_id' => PathRegistration::query()->create([
                'learning_path_id' => LearningPath::query()->create([
                    'title' => 'مسار حماية',
                    'slug' => 'path-protect-'.uniqid(),
                    'description' => 'وصف',
                    'status' => PathStatus::Published,
                    'published_at' => now(),
                ])->id,
                'user_id' => User::factory()->create()->id,
                'status' => RegistrationStatus::Approved->value,
                'approved_at' => now(),
            ])->id,
            'attendance_date' => '2026-08-03',
            'status' => 'late',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_08_03_160000_simplify_program_attendance_binary.php');
        $migration->up();

        $rows = ProgramAttendance::query()
            ->where('program_registration_id', $registration->id)
            ->orderBy('training_date')
            ->get();

        $this->assertCount(2, $rows);
        $this->assertSame(['2026-08-03', '2026-08-04'], $rows->map(fn ($r) => $r->training_date->toDateString())->all());
        $this->assertTrue($rows->every(fn ($r) => $r->status === AttendanceStatus::Present));
        $this->assertDatabaseHas('path_attendance', ['status' => 'late']);
        $this->assertTrue(ProgramPrepDay::query()->where('requires_attendance', false)->doesntExist());
    }

    public function test_generate_sessions_never_precreates_absent_rows(): void
    {
        $program = $this->makeProgram();
        $this->addDay($program, '2026-08-03');
        $registration = $this->register($program);
        $service = app(ProgramAttendanceService::class);

        $this->assertSame(0, $service->generateSessions($registration));
        $this->assertSame(0, $service->generateSessionsForAllRegistrations($program));
        $this->assertSame(0, ProgramAttendance::query()->count());
    }

    public function test_vl_seeder_matches_stable_slug_not_title(): void
    {
        $canonical = $this->makeProgram([
            'slug' => VolunteerLeadersProgramPeriod::stableSlugs()[0],
            'title' => 'عنوان مختلف عن القادة',
        ]);
        $this->seed(VolunteerLeadersProgramPrepDaysSeeder::class);
        $this->assertSame(
            30,
            ProgramPrepDay::query()->where('training_program_id', $canonical->id)->count(),
        );
        $this->assertSame(
            6,
            ProgramPrepDay::query()
                ->where('training_program_id', $canonical->id)
                ->where('delivery_type', ProgramPrepDayType::InPerson)
                ->count(),
        );
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

    private function addDay(
        TrainingProgram $program,
        string $date,
        ProgramPrepDayType $type = ProgramPrepDayType::InPerson,
    ): ProgramPrepDay {
        return ProgramPrepDay::query()->create([
            'training_program_id' => $program->id,
            'prep_date' => $date,
            'delivery_type' => $type,
            'requires_attendance' => true,
        ]);
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
