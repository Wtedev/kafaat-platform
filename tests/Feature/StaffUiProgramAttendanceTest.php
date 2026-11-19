<?php

namespace Tests\Feature;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramActivityAction;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramPrepDayType;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TrainingProgramKind;
use App\Models\ProgramAttendance;
use App\Models\ProgramAttendanceChecker;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Notifications\ProgramRegistrationApproved;
use App\Services\Attendance\ProgramAttendanceLinkService;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class StaffUiProgramAttendanceTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        config([
            'staff_ui.maintenance' => false,
            'staff_ui.ready_modules' => ['training'],
        ]);
    }

    public function test_days_can_be_added_generated_retyped_and_removed(): void
    {
        $admin = $this->admin();
        $program = $this->program(['start_date' => '2026-12-06', 'end_date' => '2026-12-17', 'weekdays' => [0, 2]]);

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.attendance.days.generate', $program), ['delivery_type' => 'in_person'])
            ->assertSessionHas('status', 'أُضيف 4 يوماً من جدول البرنامج.');
        $this->assertSame(
            ['2026-12-06', '2026-12-08', '2026-12-13', '2026-12-15'],
            $program->prepDays()->get()->map->dateString()->all(),
        );

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.attendance.days.store', $program), ['prep_date' => '2026-12-20', 'delivery_type' => 'remote'])
            ->assertSessionHas('status');
        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.attendance.days.store', $program), ['prep_date' => '2026-12-20', 'delivery_type' => 'remote'])
            ->assertSessionHasErrors('prep_date');

        $day = $program->prepDays()->whereDate('prep_date', '2026-12-06')->firstOrFail();
        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.attendance.days.update', [$program, $day]), ['delivery_type' => 'remote']);
        $this->assertSame(ProgramPrepDayType::Remote, $day->fresh()->delivery_type);

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.attendance.days.destroy', [$program, $day]));
        $this->assertNull($day->fresh());
    }

    public function test_day_with_attendance_cannot_be_removed(): void
    {
        $program = $this->program();
        $day = $program->prepDays()->create(['prep_date' => '2026-12-06', 'delivery_type' => ProgramPrepDayType::InPerson, 'requires_attendance' => true]);
        $registration = $this->registration($program, 'حاضر');
        ProgramAttendance::query()->create(['program_registration_id' => $registration->id, 'training_date' => '2026-12-06', 'status' => 'present']);

        $this->actingAsOtpVerified($this->admin())
            ->post(route('staff-ui.programs.attendance.days.destroy', [$program, $day]))
            ->assertSessionHasErrors('prep_date');

        $this->assertNotNull($day->fresh());
    }

    public function test_roster_marks_and_clears_attendance_and_finds_by_name(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        $program->prepDays()->create(['prep_date' => '2026-12-06', 'delivery_type' => ProgramPrepDayType::InPerson, 'requires_attendance' => true]);
        $noura = $this->registration($program, 'نورة السعيد');
        $fahad = $this->registration($program, 'فهد العمر');
        $this->registration($program, 'زياد المعلّق', RegistrationStatus::Pending);

        // Roster rows are labelled «تحديد {name}» (names also appear in the staff inbox panel).
        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.programs.tab', [$program, 'attendance']).'?day=2026-12-06')
            ->assertOk()
            ->assertSee('تحديد نورة السعيد')
            ->assertSee('تحديد فهد العمر')
            ->assertDontSee('تحديد زياد المعلّق');

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.programs.tab', [$program, 'attendance']).'?day=2026-12-06&q=نوره')
            ->assertSee('تحديد نورة السعيد')
            ->assertDontSee('تحديد فهد العمر');

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.attendance.toggle', [$program, $noura]), ['date' => '2026-12-06', 'present' => 1]);
        $this->assertTrue(ProgramAttendance::query()->where('program_registration_id', $noura->id)->exists());

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.attendance.toggle', [$program, $noura]), ['date' => '2026-12-06', 'present' => 0]);
        $this->assertFalse(ProgramAttendance::query()->where('program_registration_id', $noura->id)->exists());

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.attendance.bulk', $program), ['date' => '2026-12-06', 'ids' => [$noura->id, $fahad->id]])
            ->assertSessionHas('status', 'حُضّر 2.');

        $this->assertSame(
            [ProgramActivityAction::AttendanceMarked, ProgramActivityAction::AttendanceCleared],
            $program->activities()->reorder('id')
                ->whereIn('action', ProgramActivityAction::valuesInGroup('attendance'))
                ->limit(2)->pluck('action')->all(),
        );
    }

    public function test_marking_a_day_that_is_not_a_program_day_is_refused(): void
    {
        $program = $this->program();
        $registration = $this->registration($program, 'أحد');

        $this->actingAsOtpVerified($this->admin())
            ->post(route('staff-ui.programs.attendance.toggle', [$program, $registration]), ['date' => '2026-12-09', 'present' => 1])
            ->assertSessionHasErrors('training_date');
    }

    public function test_links_are_created_opened_closed_and_cancelled(): void
    {
        $admin = $this->admin();
        $program = $this->program();

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.attendance.links.store', $program), ['name' => 'المحاضرة الأولى', 'open_minutes' => 10, 'open_now' => 1])
            ->assertSessionHas('status');
        $link = $program->attendanceLinks()->sole();
        $this->assertTrue($link->isOpen());
        $this->assertSame(10, $link->openMinutes());

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.programs.tab', [$program, 'attendance']))
            ->assertSee('المحاضرة الأولى')
            ->assertSee($link->publicUrl());

        $this->actingAsOtpVerified($admin)->post(route('staff-ui.programs.attendance.links.action', [$program, $link]), ['action' => 'close']);
        $this->assertFalse($link->fresh()->isOpen());

        $this->actingAsOtpVerified($admin)->post(route('staff-ui.programs.attendance.links.action', [$program, $link]), ['action' => 'cancel']);
        $this->assertTrue($link->fresh()->isCancelled());
    }

    public function test_link_check_in_is_logged(): void
    {
        $program = $this->program();
        $registration = $this->registration($program, 'من الرابط');
        $service = app(ProgramAttendanceLinkService::class);
        $link = $service->create($program, 'رابط', 10);

        $service->markManual($link, $registration);

        $activity = $program->activities()->where('action', ProgramActivityAction::AttendanceMarked)->sole();
        $this->assertSame('رابط', $activity->properties['link']);
        $this->assertSame('من الرابط', $activity->description);
    }

    public function test_checkers_get_a_one_time_link_and_can_be_stopped(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        $program->prepDays()->create(['prep_date' => '2026-12-06', 'delivery_type' => ProgramPrepDayType::InPerson, 'requires_attendance' => true]);

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.attendance.checkers.store', $program), ['name' => 'عبدالله'])
            ->assertSessionHas('checker_link');
        $checker = ProgramAttendanceChecker::query()->sole();

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.attendance.checkers.action', [$program, $checker]), ['action' => 'deactivate']);
        $this->assertFalse((bool) $checker->fresh()->is_active);
    }

    public function test_editing_days_needs_update_but_marking_needs_only_program_access(): void
    {
        $program = $this->program();
        $program->prepDays()->create(['prep_date' => '2026-12-06', 'delivery_type' => ProgramPrepDayType::InPerson, 'requires_attendance' => true]);
        $registration = $this->registration($program, 'متدرب');
        $viewer = $this->staff(['programs.view']);

        $this->actingAsOtpVerified($viewer)
            ->post(route('staff-ui.programs.attendance.days.store', $program), ['prep_date' => '2026-12-07', 'delivery_type' => 'in_person'])
            ->assertForbidden();

        $this->actingAsOtpVerified($viewer)
            ->post(route('staff-ui.programs.attendance.toggle', [$program, $registration]), ['date' => '2026-12-06', 'present' => 1])
            ->assertRedirect();
        $this->assertTrue(ProgramAttendance::query()->where('program_registration_id', $registration->id)->exists());
    }

    public function test_in_person_acceptance_email_carries_the_attendance_qr(): void
    {
        $inPerson = $this->program(['delivery_mode' => ProgramDeliveryMode::InPerson, 'venue' => 'المقر']);
        $remote = $this->program(['slug' => 'remote-'.uniqid()]);
        $a = $this->registration($inPerson, 'حضوري');
        $b = $this->registration($remote, 'عن بعد');

        $html = (string) (new ProgramRegistrationApproved($a))->toMail($a->user)->render();
        $this->assertStringContainsString('رمز الحضور', $html);
        $this->assertStringContainsString('KAFAAT-P'.$inPerson->id.'-R'.$a->id, $html);

        $html = (string) (new ProgramRegistrationApproved($b))->toMail($b->user)->render();
        $this->assertStringNotContainsString('رمز الحضور', $html);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function program(array $overrides = []): TrainingProgram
    {
        return TrainingProgram::query()->create(array_merge([
            'title' => 'برنامج التحضير',
            'slug' => 'att-'.uniqid(),
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Professional,
            'delivery_mode' => ProgramDeliveryMode::Remote,
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'start_date' => '2026-12-06',
            'end_date' => '2026-12-10',
        ], $overrides));
    }

    private function registration(TrainingProgram $program, string $name, RegistrationStatus $status = RegistrationStatus::Approved): ProgramRegistration
    {
        return ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => User::factory()->create(['name' => $name, 'role_type' => 'beneficiary', 'is_active' => true])->id,
            'status' => $status,
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['role_type' => 'admin', 'is_active' => true, 'email_verified_at' => now()]);
        $admin->assignRole(RbacCatalog::ROLE_ADMIN);

        return $admin->fresh();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function staff(array $permissions): User
    {
        $staff = User::factory()->create(['role_type' => 'staff', 'is_active' => true, 'email_verified_at' => now()]);
        $staff->assignRole(RbacCatalog::ROLE_STAFF);
        $staff->givePermissionTo($permissions);

        return $staff->fresh();
    }
}
