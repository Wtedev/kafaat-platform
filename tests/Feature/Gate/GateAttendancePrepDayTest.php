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

    public function test_portal_shows_prep_day_select_defaulting_to_today(): void
    {
        [$program, $admin] = $this->programWithAdmin();
        $this->addPrepDay($program, '2026-08-03');
        $this->addPrepDay($program, '2026-08-04');

        $this->actingAs($admin)
            ->get(route('gate.portal', $program->slug))
            ->assertOk()
            ->assertSee('اختيار يوم التحضير', false)
            ->assertSee('name="date"', false)
            ->assertSee('2026-08-03', false)
            ->assertSee('(اليوم)', false);
    }

    public function test_valid_prep_date_query_selects_that_day_and_forged_is_ignored(): void
    {
        [$program, $admin] = $this->programWithAdmin();
        $this->addPrepDay($program, '2026-08-03');
        $this->addPrepDay($program, '2026-08-04');

        $this->actingAs($admin)
            ->get(route('gate.portal', ['program' => $program->slug, 'date' => '2026-08-04']))
            ->assertOk()
            ->assertSee('value="2026-08-04" selected', false);

        $this->actingAs($admin)
            ->get(route('gate.portal', ['program' => $program->slug, 'date' => '2099-01-01']))
            ->assertOk()
            ->assertSee('value="2026-08-03" selected', false);
    }

    public function test_qr_marks_selected_prep_day_and_rejects_non_prep_date(): void
    {
        [$program, $admin] = $this->programWithAdmin();
        $this->addPrepDay($program, '2026-08-03');
        $this->addPrepDay($program, '2026-08-04');
        $registration = $this->register($program, 'نورة');
        $pass = sprintf('KAFAAT-P%d-R%d', $program->id, $registration->id);

        $this->actingAs($admin)
            ->postJson(route('gate.scan.store', $program->slug), [
                'pass' => $pass,
                'date' => '2026-08-04',
            ])
            ->assertOk()
            ->assertJsonPath('reason', 'marked');

        $this->assertTrue(
            ProgramAttendance::query()
                ->where('program_registration_id', $registration->id)
                ->whereDate('training_date', '2026-08-04')
                ->where('status', AttendanceStatus::Present->value)
                ->exists()
        );

        $this->actingAs($admin)
            ->postJson(route('gate.scan.store', $program->slug), [
                'pass' => $pass,
                'date' => '2099-01-01',
            ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'invalid_day');

        $this->actingAs($admin)
            ->postJson(route('gate.scan.store', $program->slug), [
                'pass' => $pass,
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
            'name' => 'مسؤول التحضير',
            'email' => null,
            'is_active' => true,
            'access_token_hash' => hash('sha256', 'test-token-placeholder-32bytes-ok!!'),
            'access_version' => 1,
        ]);

        $other = $this->makeProgram('other-gate');
        $this->addPrepDay($other, '2026-08-03');

        $this->withSession([
            EnsureGateAttendanceAccess::SESSION_CHECKER_ID => $checker->id,
            EnsureGateAttendanceAccess::SESSION_PROGRAM_ID => $program->id,
            EnsureGateAttendanceAccess::SESSION_ACCESS_VERSION => 1,
        ])->get(route('gate.portal', $other->slug))
            ->assertRedirect(route('gate.login', $other->slug));
    }

    public function test_scan_day_route_removed(): void
    {
        $this->assertFalse(Route::has('gate.scan.day'));
    }

    public function test_mark_present_from_pass_rejects_when_today_is_not_prep_and_no_valid_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-09 10:00:00', 'Asia/Riyadh'));
        $program = $this->makeProgram('invalid-day');
        $this->addPrepDay($program, '2026-08-03');
        $registration = $this->register($program, 'ليان');

        $result = app(ProgramAttendanceService::class)->markPresentFromPass(
            $program,
            sprintf('KAFAAT-P%d-R%d', $program->id, $registration->id),
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
