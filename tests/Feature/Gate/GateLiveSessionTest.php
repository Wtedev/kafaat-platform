<?php

namespace Tests\Feature\Gate;

use App\Enums\AttendanceStatus;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramPrepDayType;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TrainingProgramKind;
use App\Http\Middleware\EnsureGateAttendanceAccess;
use App\Models\AttendanceLiveSession;
use App\Models\ProgramAttendance;
use App\Models\ProgramAttendanceChecker;
use App\Models\ProgramPrepDay;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\AttendanceLiveSessionService;
use App\Services\ProgramAttendanceCheckerAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class GateLiveSessionTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        config(['app.timezone' => 'Asia/Riyadh']);
        Carbon::setTestNow(Carbon::parse('2026-08-12 10:00:00', 'Asia/Riyadh'));
    }

    public function test_checker_can_start_five_minute_remote_session_with_confirm_copy(): void
    {
        [$program, $checker, $session] = $this->remoteProgramWithChecker();

        $session->get(route('gate.portal', $program))
            ->assertOk()
            ->assertSee('tab=session', false)
            ->assertSee('جلسة التحضير', false)
            ->assertSee('فتح جلسة التحضير', false)
            ->assertSee('سيتم فتح التحضير للمستفيدين لمدة 5 دقائق', false);

        $response = $session->postJson(route('gate.live-session.start', $program))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('reused', false)
            ->assertJsonPath('status.active', true)
            ->assertJsonPath('status.remaining_seconds', 300);

        $live = AttendanceLiveSession::query()->latest('id')->first();
        $this->assertNotNull($live);
        $this->assertSame($checker->id, $live->opened_by_checker_id);
        $this->assertNull($live->created_by);
        $this->assertSame(300, $live->remainingSeconds());
        $this->assertTrue($response->json('status.can_open'));
    }

    public function test_repeated_start_does_not_extend_or_duplicate(): void
    {
        [$program, , $session] = $this->remoteProgramWithChecker();

        $first = $session->postJson(route('gate.live-session.start', $program))->assertOk();
        Carbon::setTestNow(Carbon::parse('2026-08-12 10:01:00', 'Asia/Riyadh'));
        $second = $session->postJson(route('gate.live-session.start', $program))
            ->assertOk()
            ->assertJsonPath('reused', true)
            ->assertJsonPath('status.active', true);

        $this->assertSame(1, AttendanceLiveSession::query()->count());
        $this->assertSame($first->json('status.expires_at_ms'), $second->json('status.expires_at_ms'));
        $this->assertLessThanOrEqual(240, $second->json('status.remaining_seconds'));
        $this->assertGreaterThan(230, $second->json('status.remaining_seconds'));
    }

    public function test_session_expires_after_five_minutes_server_side(): void
    {
        [$program, , $gate] = $this->remoteProgramWithChecker();
        $beneficiary = $this->registerBeneficiary($program);

        $gate->postJson(route('gate.live-session.start', $program))->assertOk();
        $live = AttendanceLiveSession::query()->first();

        Carbon::setTestNow(Carbon::parse('2026-08-12 10:05:00', 'Asia/Riyadh'));
        $this->assertFalse($live->fresh()->isActive());
        $this->assertNull(app(AttendanceLiveSessionService::class)->activeSessionFor($program, '2026-08-12'));

        $this->actingAs($beneficiary)->withSession(['otp_verified' => true])
            ->post(route('portal.programs.attendance.check-in', $program))
            ->assertRedirect();

        $this->assertSame(0, ProgramAttendance::query()->where('program_registration_id', $beneficiary->programRegistrations()->first()->id)->count());
    }

    public function test_manual_end_sets_closed_at_and_blocks_check_in(): void
    {
        [$program, , $gate] = $this->remoteProgramWithChecker();
        $beneficiary = $this->registerBeneficiary($program);
        $registration = $beneficiary->programRegistrations()->first();

        $gate->postJson(route('gate.live-session.start', $program))->assertOk();
        $gate->postJson(route('gate.live-session.end', $program))
            ->assertOk()
            ->assertJsonPath('status.active', false)
            ->assertJsonPath('status.ended', true);

        $live = AttendanceLiveSession::query()->first();
        $this->assertNotNull($live->closed_at);
        $this->assertFalse($live->isActive());

        $this->actingAs($beneficiary)->withSession(['otp_verified' => true])
            ->post(route('portal.programs.attendance.check-in', $program))
            ->assertRedirect();

        $this->assertSame(0, ProgramAttendance::query()->where('program_registration_id', $registration->id)->count());
    }

    public function test_beneficiary_can_check_in_only_while_session_active(): void
    {
        [$program, , $gate] = $this->remoteProgramWithChecker();
        $beneficiary = $this->registerBeneficiary($program);
        $registration = $beneficiary->programRegistrations()->first();

        $this->actingAs($beneficiary)->withSession(['otp_verified' => true])
            ->post(route('portal.programs.attendance.check-in', $program))
            ->assertRedirect();
        $this->assertSame(0, ProgramAttendance::query()->count());

        $gate->postJson(route('gate.live-session.start', $program))->assertOk();

        $this->actingAs($beneficiary)->withSession(['otp_verified' => true])
            ->post(route('portal.programs.attendance.check-in', $program))
            ->assertRedirect()
            ->assertSessionHas('attendance_success');

        $this->assertSame(1, ProgramAttendance::query()->where('program_registration_id', $registration->id)->count());
        $record = ProgramAttendance::query()->first();
        $this->assertSame(AttendanceStatus::Present, $record->status);
        $this->assertStringContainsString('تسجيل حضور ذاتي', (string) $record->notes);
    }

    public function test_cannot_open_live_session_on_in_person_day(): void
    {
        [$program, , $session] = $this->remoteProgramWithChecker(ProgramPrepDayType::InPerson);

        $session->get(route('gate.portal', $program))
            ->assertOk()
            ->assertDontSee('tab=session', false)
            ->assertDontSee('فتح جلسة التحضير', false);

        $session->postJson(route('gate.live-session.start', $program))
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->assertSame(0, AttendanceLiveSession::query()->count());
    }

    public function test_guest_cannot_start_live_session(): void
    {
        [$program] = $this->remoteProgramWithChecker();

        $this->flushSession()
            ->postJson(route('gate.live-session.start', $program))
            ->assertRedirect(route('gate.login', $program));
    }

    public function test_status_lists_present_count_and_remaining_from_server(): void
    {
        [$program, , $gate] = $this->remoteProgramWithChecker();
        $beneficiary = $this->registerBeneficiary($program, 'حاضر الجلسة');
        $this->registerBeneficiary($program, 'لم يسجل بعد', 'absent@example.test');

        $gate->postJson(route('gate.live-session.start', $program))->assertOk();
        Carbon::setTestNow(Carbon::parse('2026-08-12 10:02:30', 'Asia/Riyadh'));

        $this->actingAs($beneficiary)->withSession(['otp_verified' => true])
            ->post(route('portal.programs.attendance.check-in', $program));

        $status = $gate->getJson(route('gate.live-session.status', $program))
            ->assertOk()
            ->assertJsonPath('active', true)
            ->assertJsonPath('present_count', 1)
            ->assertJsonPath('approved_count', 2)
            ->json();

        $this->assertSame(150, $status['remaining_seconds']);
        $this->assertNotEmpty($status['started_at']);
        $this->assertNotEmpty($status['expires_at']);
    }

    public function test_service_end_session_is_idempotent(): void
    {
        [$program] = $this->remoteProgramWithChecker();
        $admin = User::factory()->create();
        $live = app(AttendanceLiveSessionService::class);
        $session = $live->startProgramRemoteSession($program, $admin);
        $ended = $live->endSession($session);
        $again = $live->endSession($ended);

        $this->assertFalse($again->isActive());
        $this->assertNotNull($again->closed_at);
    }

    public function test_checker_opener_is_accepted_by_service(): void
    {
        [$program, $checker] = $this->remoteProgramWithChecker();
        $live = app(AttendanceLiveSessionService::class);
        $session = $live->startProgramRemoteSession($program, $checker);

        $this->assertSame($checker->id, $session->opened_by_checker_id);
        $this->assertNull($session->created_by);

        try {
            Carbon::setTestNow(Carbon::parse('2026-08-11 10:00:00', 'Asia/Riyadh'));
            $live->startProgramRemoteSession($program, $checker);
            $this->fail('Expected ValidationException');
        } catch (ValidationException) {
            // expected — not a remote prep day on Aug 11
        }
    }

    /**
     * @return array{0: TrainingProgram, 1: ProgramAttendanceChecker, 2: \Illuminate\Foundation\Testing\TestCase}
     */
    private function remoteProgramWithChecker(
        ProgramPrepDayType $type = ProgramPrepDayType::Remote,
    ): array {
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج جلسة التحضير',
            'slug' => 'live-session-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'program_kind' => TrainingProgramKind::Course,
            'delivery_mode' => ProgramDeliveryMode::Hybrid,
            'venue' => 'قاعة',
        ]);

        ProgramPrepDay::query()->create([
            'training_program_id' => $program->id,
            'prep_date' => '2026-08-12',
            'delivery_type' => $type,
            'requires_attendance' => true,
        ]);

        $access = app(ProgramAttendanceCheckerAccessService::class)->create($program, 'محضّر الجلسة');
        /** @var ProgramAttendanceChecker $checker */
        $checker = $access['checker'];

        $session = $this->withSession([
            EnsureGateAttendanceAccess::SESSION_CHECKER_ID => $checker->id,
            EnsureGateAttendanceAccess::SESSION_PROGRAM_ID => $program->id,
            EnsureGateAttendanceAccess::SESSION_ACCESS_VERSION => (int) $checker->access_version,
        ]);

        return [$program, $checker, $session];
    }

    private function registerBeneficiary(
        TrainingProgram $program,
        string $name = 'مستفيد',
        string $email = 'beneficiary@example.test',
    ): User {
        $user = User::factory()->create([
            'email' => $email,
            'name' => $name,
            'first_name' => $name,
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
            'notification_prefs_set_at' => now(),
        ]);
        $user->assignRole('beneficiary');

        ProgramRegistration::query()->create([
            'user_id' => $user->id,
            'training_program_id' => $program->id,
            'status' => RegistrationStatus::Approved,
            'approved_at' => now()->subDay(),
        ]);

        return $user;
    }
}
