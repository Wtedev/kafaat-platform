<?php

namespace Tests\Feature;

use App\Enums\AttendanceLinkMode;
use App\Enums\AttendanceMarkSource;
use App\Enums\CompetencyTrack;
use App\Enums\ProgramActivityAction;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TrainingProgramKind;
use App\Jobs\IssueProgramCertificateJob;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\ProgramAttendanceMark;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Notifications\ProgramRegistrationApproved;
use App\Services\Attendance\ProgramAttendanceLinkService;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function test_days_are_added_by_type_edited_opened_and_removed(): void
    {
        $admin = $this->admin();
        $program = $this->program();

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.attendance.store', $program), ['mode' => 'qr', 'name' => 'اليوم الأول', 'open_minutes' => 10, 'open_now' => 1])
            ->assertSessionHas('status');
        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.attendance.store', $program), ['mode' => 'remote', 'name' => 'المحاضرة عن بعد', 'open_minutes' => 30]);

        [$qr, $remote] = $program->attendanceLinks()->orderBy('id')->get()->all();
        $this->assertSame(AttendanceLinkMode::Qr, $qr->mode);
        $this->assertTrue($qr->isOpen());
        $this->assertSame(AttendanceLinkMode::Remote, $remote->mode);
        $this->assertNull($remote->opens_at);

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.programs.tab', [$program, 'attendance']))
            ->assertOk()
            ->assertSee('اليوم الأول')
            ->assertSee('نسخ رابط مسح التحضير')
            ->assertSee($qr->scanUrl())
            ->assertSee('نسخ رابط المشاركين')
            ->assertSee($remote->publicUrl());

        // Changing the duration of an open link moves its closing time.
        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.attendance.update', [$program, $qr]), ['name' => 'اليوم الأول (صباحي)', 'open_minutes' => 45]);
        $qr->refresh();
        $this->assertSame('اليوم الأول (صباحي)', $qr->name);
        $this->assertSame(45, $qr->openMinutes());
        $this->assertTrue($qr->closes_at->equalTo($qr->opens_at->copy()->addMinutes(45)));

        $this->actingAsOtpVerified($admin)->post(route('staff-ui.programs.attendance.action', [$program, $qr]), ['action' => 'close']);
        $this->assertFalse($qr->fresh()->isOpen());

        $this->actingAsOtpVerified($admin)->post(route('staff-ui.programs.attendance.action', [$program, $remote]), ['action' => 'cancel']);
        $this->assertTrue($remote->fresh()->isCancelled());
    }

    public function test_checker_scans_trainee_qr_on_the_scan_link(): void
    {
        $program = $this->program(['delivery_mode' => ProgramDeliveryMode::InPerson, 'venue' => 'المقر']);
        $registration = $this->registration($program, 'سارة الحربي');
        $pending = $this->registration($program, 'معلّق', RegistrationStatus::Pending);
        $service = app(ProgramAttendanceLinkService::class);
        $link = $service->create($program, 'اليوم الأول', 15, AttendanceLinkMode::Qr);

        $this->get($link->scanUrl())->assertOk()->assertSee('التحضير غير مفتوح الآن');
        $pass = 'https://example.test/pass#KAFAAT-P'.$program->id.'-R'.$registration->id;
        $this->postJson(route('gate.link-scan.store', $link->token), ['pass' => $pass])->assertStatus(422);

        $service->open($link->fresh());
        $this->get($link->scanUrl())->assertOk()->assertDontSee('التحضير غير مفتوح الآن');

        $this->postJson(route('gate.link-scan.store', $link->token), ['pass' => $pass])
            ->assertOk()
            ->assertJson(['ok' => true, 'name' => 'سارة الحربي', 'count' => 1]);
        $this->postJson(route('gate.link-scan.store', $link->token), ['pass' => 'KAFAAT-P'.$program->id.'-R'.$registration->id])
            ->assertOk()
            ->assertJson(['ok' => true, 'count' => 1]);

        $this->postJson(route('gate.link-scan.store', $link->token), ['pass' => 'KAFAAT-P'.$program->id.'-R'.$pending->id])->assertStatus(422);
        $this->postJson(route('gate.link-scan.store', $link->token), ['pass' => 'KAFAAT-P999999-R'.$registration->id])->assertStatus(422);

        $mark = ProgramAttendanceMark::query()->sole();
        $this->assertSame(AttendanceMarkSource::Qr, $mark->source);
        $this->assertNotNull($mark->attended_at);

        // A QR link is never offered to trainees as an identity link.
        $this->get($link->publicUrl())->assertNotFound();
    }

    public function test_certificates_tab_shows_check_ins_surveys_and_manual_check_in(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        $done = $this->registration($program, 'نورة السعيد');
        $missing = $this->registration($program, 'خالد العمر');
        $this->registration($program, 'زياد الغامدي', RegistrationStatus::Rejected);
        $service = app(ProgramAttendanceLinkService::class);
        $day = $service->create($program, 'اليوم الأول', 15);
        $service->markManual($day, $done);

        $named = DB::table('program_surveys')->insertGetId(['training_program_id' => $program->id, 'type' => 'pre', 'public_token' => Str::random(40), 'is_anonymous' => false, 'created_at' => now(), 'updated_at' => now()]);
        $anon = DB::table('program_surveys')->insertGetId(['training_program_id' => $program->id, 'type' => 'satisfaction', 'public_token' => Str::random(40), 'is_anonymous' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('survey_responses')->insert(['id' => (string) Str::uuid(), 'program_survey_id' => $named, 'registration_id' => $done->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('survey_completions')->insert(['program_survey_id' => $anon, 'registration_id' => $done->id]);

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.programs.tab', [$program, 'certificates']))
            ->assertOk()
            ->assertSee('نورة السعيد')
            ->assertSee('خالد العمر')
            ->assertDontSee('تحضير زياد الغامدي — اليوم الأول')
            ->assertSee('اليوم الأول')
            ->assertSee('استبيان قبلي')
            ->assertSee('استبيان بعدي')
            ->assertSee('استبيان الرضا')
            ->assertSee('تمّ')
            ->assertSee('تحضير خالد العمر — اليوم الأول')
            ->assertDontSee('تحضير نورة السعيد — اليوم الأول')
            ->assertSee('تصميم الشهادة');

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.attendance.manual', [$program, $day, $missing]), ['q' => 'خالد'])
            ->assertRedirect(route('staff-ui.programs.tab', [$program, 'certificates']).'?q='.urlencode('خالد'));
        $this->assertTrue(ProgramAttendanceMark::query()->where('program_registration_id', $missing->id)->where('source', AttendanceMarkSource::Manual)->exists());
        $this->assertSame(2, $program->activities()->where('action', ProgramActivityAction::AttendanceMarked)->count());

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.programs.tab', [$program, 'certificates']).'?q=نوره')
            ->assertSee(route('staff-ui.users.show', $done->user_id))
            ->assertDontSee(route('staff-ui.users.show', $missing->user_id));
    }

    public function test_certificates_are_issued_for_the_selection_only_with_a_background(): void
    {
        Storage::fake('public');
        Queue::fake();
        $admin = $this->admin();
        $program = $this->program();
        $a = $this->registration($program, 'أ');
        $b = $this->registration($program, 'ب');
        $c = $this->registration($program, 'ج');

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.certificates.issue', $program), ['ids' => [$a->id]])
            ->assertSessionHasErrors('certificates');
        Queue::assertNothingPushed();

        Storage::disk('public')->put('certificates/bg.png', 'png');
        CertificateTemplate::query()->create(['training_program_id' => $program->id, 'background_path' => 'certificates/bg.png', 'elements' => []]);
        Certificate::query()->create([
            'user_id' => $c->user_id,
            'certificateable_type' => $program->getMorphClass(),
            'certificateable_id' => $program->id,
            'certificate_number' => 'KF-0001',
            'verification_code' => Str::random(16),
            'issued_at' => now(),
        ]);

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.programs.tab', [$program, 'certificates']))
            ->assertSee('KF-0001')
            ->assertSee('إصدار للمحدد');

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.certificates.issue', $program), ['ids' => [$a->id, $b->id, $c->id]])
            ->assertSessionHas('status');

        Queue::assertPushed(IssueProgramCertificateJob::class, 2);

        $viewer = $this->staff(['programs.view']);
        $this->actingAsOtpVerified($viewer)
            ->post(route('staff-ui.programs.certificates.issue', $program), ['ids' => [$a->id]])
            ->assertForbidden();
        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.tab', [$program, 'certificates']))
            ->assertOk()
            ->assertDontSee('إصدار للمحدد');
    }

    public function test_viewers_can_check_in_but_not_create_days(): void
    {
        $program = $this->program();
        $registration = $this->registration($program, 'متدرب');
        $link = app(ProgramAttendanceLinkService::class)->create($program, 'يوم', 10);
        $viewer = $this->staff(['programs.view']);

        $this->actingAsOtpVerified($viewer)
            ->post(route('staff-ui.programs.attendance.manual', [$program, $link, $registration]))
            ->assertRedirect();
        $this->assertTrue(ProgramAttendanceMark::query()->where('program_registration_id', $registration->id)->exists());

        $other = $this->program(['slug' => 'other-'.uniqid()]);
        $this->actingAsOtpVerified($viewer)
            ->post(route('staff-ui.programs.attendance.manual', [$other, $link, $registration]))
            ->assertNotFound();
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
            'start_date' => now()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
        ], $overrides));
    }

    private function registration(TrainingProgram $program, string $name, RegistrationStatus $status = RegistrationStatus::Approved): ProgramRegistration
    {
        $user = User::factory()->create(['name' => $name, 'role_type' => 'beneficiary', 'is_active' => true]);

        return ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
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
