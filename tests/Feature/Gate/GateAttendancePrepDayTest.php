<?php

namespace Tests\Feature\Gate;

use App\Enums\AttendanceStatus;
use App\Enums\CompetencyTrack;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramPrepDayType;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TrainingProgramKind;
use App\Http\Middleware\EnsureGateAttendanceAccess;
use App\Models\ProgramAttendance;
use App\Models\ProgramAttendanceChecker;
use App\Models\ProgramPrepDay;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\ProgramAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class GateAttendancePrepDayTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        config(['app.timezone' => 'Asia/Riyadh']);
        Carbon::setTestNow(Carbon::parse('2026-08-03 10:00:00', 'Asia/Riyadh'));
    }

    public function test_scan_uses_server_today_without_day_choice(): void
    {
        [$program, $admin] = $this->programWithAdmin();
        $this->addPrepDay($program, '2026-08-03');
        $this->addPrepDay($program, '2026-08-04');

        $this->actingAs($admin)
            ->get(route('gate.scan', $program->slug))
            ->assertOk()
            ->assertSee('يوم التحضير (اليوم حسب توقيت الرياض)', false)
            ->assertSee('2026-08-03', false)
            ->assertDontSee('اختاري يوم التحضير', false)
            ->assertDontSee('تغيير اليوم', false);
    }

    public function test_forged_date_query_is_ignored(): void
    {
        [$program, $admin] = $this->programWithAdmin();
        $this->addPrepDay($program, '2026-08-03');
        $this->addPrepDay($program, '2026-08-04');

        $this->actingAs($admin)
            ->get(route('gate.scan', ['program' => $program->slug, 'date' => '2026-08-04']))
            ->assertOk()
            ->assertSee('2026-08-03', false)
            ->assertDontSee('تغيير اليوم', false);
    }

    public function test_qr_marks_today_only_and_prevents_duplicate(): void
    {
        [$program, $admin] = $this->programWithAdmin();
        $this->addPrepDay($program, '2026-08-03');
        $registration = $this->register($program, 'نورة');
        $pass = sprintf('KAFAAT-P%d-R%d', $program->id, $registration->id);

        $this->actingAs($admin)
            ->postJson(route('gate.scan.store', $program->slug), [
                'pass' => $pass,
                'date' => '2026-08-04', // forged — ignored
            ])
            ->assertOk()
            ->assertJsonPath('reason', 'marked');

        $this->assertTrue(
            ProgramAttendance::query()
                ->where('program_registration_id', $registration->id)
                ->whereDate('training_date', '2026-08-03')
                ->where('status', AttendanceStatus::Present->value)
                ->exists()
        );

        $this->actingAs($admin)
            ->postJson(route('gate.scan.store', $program->slug), [
                'pass' => $pass,
                'date' => '2099-01-01',
            ])
            ->assertOk()
            ->assertJsonPath('reason', 'already_present');

        $this->assertSame(1, ProgramAttendance::query()->where('program_registration_id', $registration->id)->count());
    }

    public function test_qr_rejects_when_today_is_remote_prep_day(): void
    {
        [$program, $admin] = $this->programWithAdmin();
        $this->addPrepDay($program, '2026-08-03', ProgramPrepDayType::Remote);
        $registration = $this->register($program, 'سارة');
        $pass = sprintf('KAFAAT-P%d-R%d', $program->id, $registration->id);

        $this->actingAs($admin)
            ->postJson(route('gate.scan.store', $program->slug), ['pass' => $pass])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'not_in_person');
    }

    public function test_unauthorized_program_denied_for_checker(): void
    {
        [$program] = $this->programWithAdmin();
        $this->addPrepDay($program, '2026-08-03');

        $checker = ProgramAttendanceChecker::query()->create([
            'training_program_id' => $program->id,
            'name' => 'متحضّرة',
            'email' => 'checker@example.test',
            'is_active' => true,
            'verified_at' => now(),
        ]);

        $other = $this->makeProgram('other-gate');
        $this->addPrepDay($other, '2026-08-03');

        $this->withSession([
            EnsureGateAttendanceAccess::SESSION_CHECKER_ID => $checker->id,
            EnsureGateAttendanceAccess::SESSION_PROGRAM_ID => $program->id,
        ])->get(route('gate.scan', $other->slug))
            ->assertRedirect(route('gate.login', $other->slug));
    }

    public function test_scan_day_route_removed(): void
    {
        $this->assertFalse(Route::has('gate.scan.day'));
    }

    public function test_mark_present_from_pass_rejects_non_prep_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-09 10:00:00', 'Asia/Riyadh'));
        $program = $this->makeProgram('invalid-day');
        $this->addPrepDay($program, '2026-08-03');
        $registration = $this->register($program, 'ليان');

        $result = app(ProgramAttendanceService::class)->markPresentFromPass(
            $program,
            sprintf('KAFAAT-P%d-R%d', $program->id, $registration->id),
            prepDate: '2026-08-03',
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_day', $result['reason']);
    }

    /**
     * @return array{0: TrainingProgram, 1: User}
     */
    private function programWithAdmin(): array
    {
        $admin = User::factory()->create([
            'role_type' => 'employee',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        $program = $this->makeProgram('gate-prep');

        return [$program, $admin];
    }

    private function makeProgram(string $slug): TrainingProgram
    {
        return TrainingProgram::query()->create([
            'title' => 'برنامج بوابة',
            'slug' => $slug.'-'.uniqid(),
            'description' => 'وصف',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Self,
            'delivery_mode' => ProgramDeliveryMode::InPerson,
            'venue' => 'القاعة',
            'status' => ProgramStatus::Published,
            'published_at' => now()->subDay(),
            'capacity' => 20,
            'auto_accept_registrations' => true,
        ]);
    }

    private function addPrepDay(
        TrainingProgram $program,
        string $date,
        ProgramPrepDayType $type = ProgramPrepDayType::InPerson,
    ): void {
        ProgramPrepDay::query()->create([
            'training_program_id' => $program->id,
            'prep_date' => $date,
            'delivery_type' => $type,
            'requires_attendance' => true,
        ]);
    }

    private function register(TrainingProgram $program, string $name): ProgramRegistration
    {
        $user = User::factory()->create(['name' => $name]);

        return ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => RegistrationStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}
