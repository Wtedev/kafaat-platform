<?php

namespace Tests\Feature\Portal;

use App\Enums\AttendanceStatus;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramPrepDayType;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Models\AttendanceLiveSession;
use App\Models\ProgramAttendance;
use App\Models\ProgramPrepDay;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\ProgramAttendanceService;
use App\Support\Portal\PortalProgramDetailPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class PortalProgramDetailTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
        config(['app.timezone' => 'Asia/Riyadh']);
        Carbon::setTestNow(Carbon::parse('2026-08-12 10:00:00', 'Asia/Riyadh'));
    }

    public function test_owner_can_view_program_details(): void
    {
        [$user, $program] = $this->registeredBeneficiaryWithProgram();

        $this->actingAsOtpVerified($user)
            ->get(route('portal.programs.show', $program))
            ->assertOk()
            ->assertSee($program->title, false)
            ->assertSee('ملخص المشاركة', false)
            ->assertSee('سجل الحضور', false)
            ->assertSee('معلومات التسجيل', false);
    }

    public function test_other_beneficiary_cannot_open_another_users_program_details(): void
    {
        [, $program] = $this->registeredBeneficiaryWithProgram();
        $stranger = $this->makeBeneficiary('stranger@example.test');

        $this->actingAsOtpVerified($stranger)
            ->get(route('portal.programs.show', $program))
            ->assertNotFound();
    }

    public function test_guest_is_redirected_from_program_details(): void
    {
        [, $program] = $this->registeredBeneficiaryWithProgram();

        $this->get(route('portal.programs.show', $program))
            ->assertRedirect();
    }

    public function test_swapping_program_id_to_unregistered_program_is_not_found(): void
    {
        [$user] = $this->registeredBeneficiaryWithProgram();
        $other = TrainingProgram::query()->create([
            'title' => 'برنامج آخر',
            'slug' => 'other-program-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'delivery_mode' => ProgramDeliveryMode::Remote,
        ]);

        $this->actingAsOtpVerified($user)
            ->get(route('portal.programs.show', $other))
            ->assertNotFound();
    }

    public function test_future_prep_days_are_not_shown_as_absent(): void
    {
        [$user, $program, $registration] = $this->registeredBeneficiaryWithProgram(
            ProgramDeliveryMode::Hybrid,
            withDates: true,
        );
        $this->addPrepDay($program, '2026-08-10', ProgramPrepDayType::InPerson);
        $this->addPrepDay($program, '2026-08-12', ProgramPrepDayType::Remote);
        $this->addPrepDay($program, '2026-08-16', ProgramPrepDayType::InPerson);
        $this->addPrepDay($program, '2026-08-18', ProgramPrepDayType::Remote);

        ProgramAttendance::query()->create([
            'program_registration_id' => $registration->id,
            'training_date' => '2026-08-10',
            'status' => AttendanceStatus::Present,
            'notes' => 'تحضير بوابة QR | اليوم: 2026-08-10',
        ]);

        $html = $this->actingAsOtpVerified($user)
            ->get(route('portal.programs.show', $program))
            ->assertOk()
            ->assertSee('حاضر', false)
            ->assertSee('لم يُفتح التحضير بعد', false)
            ->assertSee('QR', false)
            ->assertDontSee('لم يحضر', false)
            ->assertSee('25.0%', false)
            ->getContent();

        $this->assertStringNotContainsString('انضم للواتساب', $html);

        $percentage = app(ProgramAttendanceService::class)->calculatePercentage($registration->fresh());
        $this->assertSame(25.0, $percentage);

        $detail = app(PortalProgramDetailPresenter::class)->present($registration->fresh(['trainingProgram.prepDays', 'attendanceRecords']), $user);
        $byDate = collect($detail['attendance_log'])->keyBy('date');

        $this->assertSame('present', $byDate['2026-08-10']['status_key']);
        $this->assertSame('qr', $byDate['2026-08-10']['method_key']);
        $this->assertSame('upcoming', $byDate['2026-08-12']['status_key']);
        $this->assertSame('upcoming', $byDate['2026-08-16']['status_key']);
        $this->assertSame('upcoming', $byDate['2026-08-18']['status_key']);
        $this->assertSame(0, $detail['summary']['absent']);
        $this->assertSame(1, $detail['summary']['present']);
        $this->assertSame(4, $detail['summary']['total']);
    }

    public function test_past_unmarked_day_is_absent_and_grades_section_hidden_without_score(): void
    {
        [$user, $program] = $this->registeredBeneficiaryWithProgram(
            ProgramDeliveryMode::InPerson,
            withDates: true,
        );
        $this->addPrepDay($program, '2026-08-10', ProgramPrepDayType::InPerson);
        $this->addPrepDay($program, '2026-08-16', ProgramPrepDayType::InPerson);

        $this->actingAsOtpVerified($user)
            ->get(route('portal.programs.show', $program))
            ->assertOk()
            ->assertSee('غائب', false)
            ->assertSee('لم يُفتح التحضير بعد', false)
            ->assertDontSee('التقييم والدرجة', false)
            ->assertSee('نسبة الحضور', false);
    }

    public function test_score_is_shown_out_of_one_hundred_and_ineligibility_reason_is_explicit(): void
    {
        [$user, $program, $registration] = $this->registeredBeneficiaryWithProgram();
        $registration->update([
            'score' => 100,
            'attendance_percentage' => 0,
        ]);
        $this->addPrepDay($program, '2026-08-10', ProgramPrepDayType::Remote);
        $this->addPrepDay($program, '2026-08-12', ProgramPrepDayType::Remote);

        $this->actingAsOtpVerified($user)
            ->get(route('portal.programs.show', $program))
            ->assertOk()
            ->assertSee('100.0 من 100', false)
            ->assertSee('التقييم والدرجة', false)
            ->assertSee('غير مؤهل', false)
            ->assertSee('أقل من 75%', false)
            ->assertDontSee('>100.0<', false);
    }

    public function test_cancelled_registration_is_shown_as_withdrawn_and_hides_join_cta(): void
    {
        [$user, $program, $registration] = $this->registeredBeneficiaryWithProgram(
            ProgramDeliveryMode::Remote,
            withDates: true,
        );
        $registration->update(['status' => RegistrationStatus::Cancelled]);
        $this->addPrepDay($program, '2026-08-12', ProgramPrepDayType::Remote);

        $this->actingAsOtpVerified($user)
            ->get(route('portal.programs.show', $program))
            ->assertOk()
            ->assertSee('منسحب', false)
            ->assertDontSee('الانضمام للجلسة', false)
            ->assertSee('لم تُحسم الأهلية بعد', false);
    }

    public function test_not_started_and_completed_program_status_labels(): void
    {
        [$user, $upcoming] = $this->registeredBeneficiaryWithProgram(
            ProgramDeliveryMode::InPerson,
            start: '2026-08-16',
            end: '2026-08-18',
        );
        $this->actingAsOtpVerified($user)
            ->get(route('portal.programs.show', $upcoming))
            ->assertOk()
            ->assertSee('لم يبدأ', false)
            ->assertSee('حضوري', false);

        [$user2, $ended] = $this->registeredBeneficiaryWithProgram(
            ProgramDeliveryMode::Remote,
            start: '2026-08-03',
            end: '2026-08-05',
            email: 'ended@example.test',
        );
        $this->actingAsOtpVerified($user2)
            ->get(route('portal.programs.show', $ended))
            ->assertOk()
            ->assertSee('مكتمل', false)
            ->assertSee('عن بُعد', false);
    }

    public function test_hybrid_label_and_remote_method_from_notes(): void
    {
        [$user, $program, $registration] = $this->registeredBeneficiaryWithProgram(
            ProgramDeliveryMode::Hybrid,
            withDates: true,
        );
        $this->addPrepDay($program, '2026-08-10', ProgramPrepDayType::Remote);
        ProgramAttendance::query()->create([
            'program_registration_id' => $registration->id,
            'training_date' => '2026-08-10',
            'status' => AttendanceStatus::Present,
            'notes' => 'تسجيل حضور ذاتي',
        ]);

        $this->actingAsOtpVerified($user)
            ->get(route('portal.programs.show', $program))
            ->assertOk()
            ->assertSee('مدمج', false)
            ->assertSee('جلسة عن بُعد', false);
    }

    public function test_open_remote_session_shows_join_as_primary_action(): void
    {
        [$user, $program] = $this->registeredBeneficiaryWithProgram(
            ProgramDeliveryMode::Remote,
            withDates: true,
        );
        $this->addPrepDay($program, '2026-08-12', ProgramPrepDayType::Remote);
        AttendanceLiveSession::query()->create([
            'attendable_type' => $program->getMorphClass(),
            'attendable_id' => $program->id,
            'session_date' => '2026-08-12',
            'created_by' => $user->id,
            'started_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->actingAsOtpVerified($user)
            ->get(route('portal.programs.show', $program))
            ->assertOk()
            ->assertSee('الانضمام للجلسة', false)
            ->assertSee('الجلسة مفتوحة الآن', false)
            ->assertDontSee('انضم للواتساب', false);
    }

    public function test_programs_list_details_link_points_to_portal_show(): void
    {
        [$user, $program] = $this->registeredBeneficiaryWithProgram();

        $this->actingAsOtpVerified($user)
            ->get(route('portal.programs'))
            ->assertOk()
            ->assertSee(route('portal.programs.show', $program), false)
            ->assertSee('تفاصيل البرنامج', false);
    }

    /**
     * @return array{0: User, 1: TrainingProgram, 2: ProgramRegistration}
     */
    private function registeredBeneficiaryWithProgram(
        ProgramDeliveryMode $deliveryMode = ProgramDeliveryMode::Remote,
        bool $withDates = false,
        ?string $start = null,
        ?string $end = null,
        string $email = 'owner@example.test',
    ): array {
        $user = $this->makeBeneficiary($email);

        $program = TrainingProgram::query()->create([
            'title' => 'قادة التطوع',
            'slug' => 'vl-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'learning_path_id' => null,
            'delivery_mode' => $deliveryMode,
            'venue' => $deliveryMode->hasPhysicalComponent() ? 'قاعة كفاءات' : null,
            'start_date' => $start ?? ($withDates ? '2026-08-03' : null),
            'end_date' => $end ?? ($withDates ? '2026-08-18' : null),
            'whatsapp_groups_enabled' => true,
            'whatsapp_group_male' => 'https://chat.whatsapp.com/test-group',
        ]);

        $registration = ProgramRegistration::query()->create([
            'user_id' => $user->id,
            'training_program_id' => $program->id,
            'status' => RegistrationStatus::Approved,
            'approved_at' => now()->subDay(),
        ]);

        return [$user, $program, $registration];
    }

    private function makeBeneficiary(string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
            'notification_prefs_set_at' => now(),
        ]);
        $user->assignRole('beneficiary');

        return $user;
    }

    private function addPrepDay(TrainingProgram $program, string $date, ProgramPrepDayType $type): ProgramPrepDay
    {
        return ProgramPrepDay::query()->create([
            'training_program_id' => $program->id,
            'prep_date' => $date,
            'delivery_type' => $type,
            'requires_attendance' => true,
        ]);
    }
}
