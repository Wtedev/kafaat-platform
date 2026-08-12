<?php

namespace Tests\Feature\Gate;

use App\Enums\AttendanceStatus;
use App\Enums\CompetencyTrack;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramPrepDayType;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TrainingProgramKind;
use App\Filament\Resources\TrainingProgramResource\Pages\ViewTrainingProgram;
use App\Filament\Resources\TrainingProgramResource\RelationManagers\ProgramAttendanceCheckersRelationManager;
use App\Http\Middleware\EnsureGateAttendanceAccess;
use App\Models\AuditLog;
use App\Models\ProgramAttendance;
use App\Models\ProgramAttendanceChecker;
use App\Models\ProgramPrepDay;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\ProgramAttendanceCheckerAccessService;
use App\Services\ProgramAttendanceService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class PrepOfficerSecureLinkTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        config(['app.timezone' => 'Asia/Riyadh']);
        Carbon::setTestNow(Carbon::parse('2026-08-03 10:00:00', 'Asia/Riyadh'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_filament_tab_title_is_prep_officers(): void
    {
        $this->assertSame(
            'مسؤولو التحضير',
            (new \ReflectionClass(ProgramAttendanceCheckersRelationManager::class))->getStaticPropertyValue('title'),
        );
    }

    public function test_create_checker_by_name_only_without_email_or_notification(): void
    {
        Notification::fake();
        [$program, $admin] = $this->programWithAdmin();
        $this->addPrepDay($program, '2026-08-03');

        $service = app(ProgramAttendanceCheckerAccessService::class);
        $result = $service->create($program, 'سارة التحضير', $admin);

        $this->assertSame('سارة التحضير', $result['checker']->name);
        $this->assertNull($result['checker']->email);
        $this->assertNotNull($result['checker']->access_token_hash);
        $this->assertSame(64, strlen($result['token']));
        $this->assertSame($service->hashToken($result['token']), $result['checker']->access_token_hash);
        $this->assertStringContainsString('/access/'.$result['token'], $result['url']);

        $this->assertDatabaseMissing('program_attendance_checkers', [
            'id' => $result['checker']->id,
            'access_token_hash' => $result['token'],
        ]);

        Notification::assertNothingSent();
        $this->assertFileDoesNotExist(app_path('Notifications/AttendanceCheckerInviteCode.php'));
        $this->assertFileDoesNotExist(app_path('Services/ProgramAttendanceCheckerInviteService.php'));
    }

    public function test_valid_access_link_establishes_session_and_redirects_clean(): void
    {
        [$program] = $this->programWithAdmin();
        $this->addPrepDay($program, '2026-08-03');
        $result = app(ProgramAttendanceCheckerAccessService::class)->create($program, 'نورة');

        $this->get($result['url'])
            ->assertRedirect(route('gate.portal', $program->slug));

        $this->get(route('gate.portal', $program->slug))
            ->assertOk()
            ->assertSee('مسؤول التحضير', false)
            ->assertSee('نورة', false)
            ->assertSee($program->title, false)
            ->assertDontSee($result['token'], false);
    }

    public function test_invalid_token_is_rejected(): void
    {
        [$program] = $this->programWithAdmin();
        $this->addPrepDay($program, '2026-08-03');

        $this->get(route('gate.access', [
            'program' => $program->slug,
            'token' => str_repeat('ab', 32),
        ]))->assertRedirect(route('gate.login', $program->slug));
    }

    public function test_regenerate_invalidates_previous_link_and_session(): void
    {
        [$program, $admin] = $this->programWithAdmin();
        $this->addPrepDay($program, '2026-08-03');
        $service = app(ProgramAttendanceCheckerAccessService::class);
        $first = $service->create($program, 'ليان', $admin);

        $this->get($first['url'])->assertRedirect(route('gate.portal', $program->slug));
        $this->get(route('gate.portal', $program->slug))->assertOk();

        $second = $service->regenerateLink($first['checker']->fresh(), $admin);

        $this->get($first['url'])->assertRedirect(route('gate.login', $program->slug));
        $this->get(route('gate.portal', $program->slug))
            ->assertRedirect(route('gate.login', $program->slug));

        $this->get($second['url'])->assertRedirect(route('gate.portal', $program->slug));
        $this->get(route('gate.portal', $program->slug))->assertOk();
    }

    public function test_deactivate_invalidates_session(): void
    {
        [$program, $admin] = $this->programWithAdmin();
        $this->addPrepDay($program, '2026-08-03');
        $service = app(ProgramAttendanceCheckerAccessService::class);
        $result = $service->create($program, 'هند', $admin);

        $this->get($result['url'])->assertRedirect(route('gate.portal', $program->slug));
        $service->setActive($result['checker']->fresh(), false, $admin);

        $this->get(route('gate.portal', $program->slug))
            ->assertRedirect(route('gate.login', $program->slug));
        $this->get($result['url'])->assertRedirect(route('gate.login', $program->slug));
    }

    public function test_checker_cannot_access_other_program(): void
    {
        [$program] = $this->programWithAdmin();
        $this->addPrepDay($program, '2026-08-03');
        $other = $this->makeProgram('other-prog');
        $this->addPrepDay($other, '2026-08-03');

        $result = app(ProgramAttendanceCheckerAccessService::class)->create($program, 'عزل');
        $this->get($result['url'])->assertRedirect();

        $this->get(route('gate.portal', $other->slug))
            ->assertRedirect(route('gate.login', $other->slug));
    }

    public function test_portal_lists_approved_and_completed_only_with_full_name(): void
    {
        [$program] = $this->programWithAdmin();
        $this->addPrepDay($program, '2026-08-03');
        $result = app(ProgramAttendanceCheckerAccessService::class)->create($program, 'فاطمة');

        $approved = $this->register($program, [
            'name' => 'legacy',
            'first_name' => 'أحمد',
            'father_name' => 'محمد',
            'grandfather_name' => 'علي',
            'family_name' => 'العتيبي',
        ], RegistrationStatus::Approved);
        $completed = $this->register($program, ['name' => 'سارة المكتملة'], RegistrationStatus::Completed);
        $this->register($program, ['name' => 'معلّق'], RegistrationStatus::Pending);
        $this->register($program, ['name' => 'مرفوض'], RegistrationStatus::Rejected);
        $this->register($program, ['name' => 'ملغي'], RegistrationStatus::Cancelled);

        $this->withCheckerSession($result['checker'], $program)
            ->get(route('gate.portal', ['program' => $program->slug, 'tab' => 'manual']))
            ->assertOk()
            ->assertSee('أحمد محمد علي العتيبي', false)
            ->assertSee('سارة المكتملة', false)
            ->assertDontSee('معلّق', false)
            ->assertDontSee('مرفوض', false)
            ->assertDontSee('ملغي', false)
            ->assertSee('لم يحضر', false)
            ->assertDontSee($approved->user->email, false)
            ->assertDontSee((string) ($approved->user->identity_number ?? 'NOID'), false);

        unset($completed);
    }

    public function test_search_matches_name_parts_and_legacy_name(): void
    {
        [$program] = $this->programWithAdmin();
        $this->addPrepDay($program, '2026-08-03');
        $result = app(ProgramAttendanceCheckerAccessService::class)->create($program, 'باحث');

        $this->register($program, [
            'name' => 'old-name',
            'first_name' => 'نورة',
            'father_name' => 'سعد',
            'grandfather_name' => 'فهد',
            'family_name' => 'القحطاني',
        ]);
        $this->register($program, ['name' => 'English User']);

        $session = $this->withCheckerSession($result['checker'], $program);

        $session->get(route('gate.portal', ['program' => $program->slug, 'tab' => 'manual', 'q' => 'القحطاني']))
            ->assertOk()
            ->assertSee('نورة سعد فهد القحطاني', false)
            ->assertDontSee('English User', false);

        $session->get(route('gate.portal', ['program' => $program->slug, 'tab' => 'manual', 'q' => 'English']))
            ->assertOk()
            ->assertSee('English User', false)
            ->assertDontSee('نورة سعد فهد القحطاني', false);
    }

    public function test_manual_toggle_today_only_and_rejects_forged_date_and_non_prep_day(): void
    {
        [$program] = $this->programWithAdmin();
        $this->addPrepDay($program, '2026-08-03');
        $result = app(ProgramAttendanceCheckerAccessService::class)->create($program, 'محضّر');
        $registration = $this->register($program, ['name' => 'مستفيد']);

        $this->withCheckerSession($result['checker'], $program)
            ->postJson(route('gate.attendance.toggle', [
                'program' => $program->slug,
                'registration' => $registration->id,
            ]), [
                'present' => true,
                'date' => '2026-08-04',
            ])
            ->assertOk()
            ->assertJsonPath('present', true)
            ->assertJsonMissingPath('email')
            ->assertJsonMissingPath('identity_number')
            ->assertJsonMissingPath('phone');

        $this->assertTrue(
            ProgramAttendance::query()
                ->where('program_registration_id', $registration->id)
                ->whereDate('training_date', '2026-08-03')
                ->exists()
        );

        $this->assertTrue(
            AuditLog::query()
                ->where('action', 'program_attendance.created')
                ->get()
                ->contains(fn (AuditLog $log): bool => ($log->metadata['source'] ?? null) === 'checker_manual')
        );

        $this->withCheckerSession($result['checker'], $program)
            ->postJson(route('gate.attendance.toggle', [
                'program' => $program->slug,
                'registration' => $registration->id,
            ]), ['present' => false])
            ->assertOk()
            ->assertJsonPath('present', false);

        $this->assertSame(0, ProgramAttendance::query()->where('program_registration_id', $registration->id)->count());

        Carbon::setTestNow(Carbon::parse('2026-08-09 10:00:00', 'Asia/Riyadh'));
        $this->withCheckerSession($result['checker'], $program)
            ->postJson(route('gate.attendance.toggle', [
                'program' => $program->slug,
                'registration' => $registration->id,
            ]), ['present' => true])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'invalid_day');
    }

    public function test_qr_only_on_in_person_day_and_hidden_on_remote(): void
    {
        [$program] = $this->programWithAdmin();
        $this->addPrepDay($program, '2026-08-03', ProgramPrepDayType::InPerson);
        $result = app(ProgramAttendanceCheckerAccessService::class)->create($program, 'ماسح');
        $registration = $this->register($program, [
            'name' => 'legacy',
            'first_name' => 'مها',
            'father_name' => 'خالد',
            'grandfather_name' => 'سعيد',
            'family_name' => 'الدوسري',
        ]);
        $pass = sprintf('KAFAAT-P%d-R%d', $program->id, $registration->id);

        $this->withCheckerSession($result['checker'], $program)
            ->get(route('gate.portal', ['program' => $program->slug, 'tab' => 'qr']))
            ->assertOk()
            ->assertSee('مسح QR', false)
            ->assertDontSee('KAFAAT-P', false);

        $this->withCheckerSession($result['checker'], $program)
            ->postJson(route('gate.scan.store', $program->slug), ['pass' => $pass])
            ->assertOk()
            ->assertJsonPath('reason', 'marked')
            ->assertJsonPath('beneficiary_name', 'مها خالد سعيد الدوسري');

        $this->assertTrue(
            AuditLog::query()
                ->where('action', 'program_attendance.created')
                ->get()
                ->contains(fn (AuditLog $log): bool => ($log->metadata['source'] ?? null) === 'checker_qr')
        );

        // Duplicate
        $this->withCheckerSession($result['checker'], $program)
            ->postJson(route('gate.scan.store', $program->slug), ['pass' => $pass])
            ->assertOk()
            ->assertJsonPath('reason', 'already_present');

        // Remote day — QR hidden + rejected
        ProgramPrepDay::query()->where('training_program_id', $program->id)->delete();
        $this->addPrepDay($program, '2026-08-03', ProgramPrepDayType::Remote);

        $this->withCheckerSession($result['checker'], $program)
            ->get(route('gate.portal', ['program' => $program->slug, 'tab' => 'qr']))
            ->assertOk()
            ->assertDontSee('مسح QR', false)
            ->assertDontSee('id="reader"', false)
            ->assertDontSee('تحضير QR غير متاح اليوم', false)
            ->assertSee('التحضير اليدوي', false);

        ProgramAttendance::query()->delete();
        $this->withCheckerSession($result['checker'], $program)
            ->postJson(route('gate.scan.store', $program->slug), ['pass' => $pass])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'not_in_person');
    }

    public function test_manual_tab_renders_attendance_table_without_qr_on_remote(): void
    {
        [$program] = $this->programWithAdmin();
        $this->addPrepDay($program, '2026-08-03', ProgramPrepDayType::Remote);
        $result = app(ProgramAttendanceCheckerAccessService::class)->create($program, 'محضّر جدول');
        $this->register($program, [
            'name' => 'legacy',
            'first_name' => 'عبدالرحمن',
            'father_name' => 'سليمان',
            'grandfather_name' => 'عبدالله',
            'family_name' => 'آل الشيخ الطويل جداً للتحقق',
        ]);

        $response = $this->withCheckerSession($result['checker'], $program)
            ->get(route('gate.portal', ['program' => $program->slug, 'tab' => 'manual']))
            ->assertOk()
            ->assertDontSee('مسح QR', false)
            ->assertDontSee('تحضير QR', false)
            ->assertSee('التحضير اليدوي', false)
            ->assertSee('>الاسم<', false)
            ->assertSee('>الحالة<', false)
            ->assertSee('>الإجراء<', false)
            ->assertSee('id="manual-list"', false)
            ->assertSee('table-fixed', false)
            ->assertSee('max-w-6xl', false)
            ->assertSee('عبدالرحمن سليمان عبدالله آل الشيخ الطويل جداً للتحقق', false)
            ->assertDontSee('id="reader"', false)
            ->assertDontSee('Showing', false)
            ->assertDontSee('min-w-[28rem]', false);

        // Seed enough rows to paginate and assert Arabic pagination stays inside the custom view.
        for ($i = 0; $i < 25; $i++) {
            $this->register($program, ['name' => "مستفيد رقم {$i}"]);
        }

        $this->withCheckerSession($result['checker'], $program)
            ->get(route('gate.portal', ['program' => $program->slug, 'tab' => 'manual']))
            ->assertOk()
            ->assertSee('التنقل بين الصفحات', false)
            ->assertSee('التالي', false)
            ->assertDontSee('Showing', false)
            ->assertDontSee('results', false);

        unset($response);
    }

    public function test_admin_filament_attendance_and_remote_session_unaffected(): void
    {
        [$program, $admin] = $this->programWithAdmin();
        $this->addPrepDay($program, '2026-08-03', ProgramPrepDayType::Remote);
        $registration = $this->register($program, ['name' => 'مستفيد أدمن']);

        $this->actingAs($admin);
        app(ProgramAttendanceService::class)
            ->setPresentState($registration, '2026-08-03', true, $admin);

        $this->assertDatabaseHas('program_attendance', [
            'program_registration_id' => $registration->id,
            'status' => AttendanceStatus::Present->value,
        ]);

        $this->assertTrue(
            AuditLog::query()
                ->get()
                ->contains(fn (AuditLog $log): bool => ($log->metadata['source'] ?? null) === 'manual')
        );
    }

    public function test_tab_visible_when_program_has_prep_days_even_if_remote_delivery_mode(): void
    {
        $admin = $this->adminUser();
        $program = $this->makeProgram('remote-mode', ProgramDeliveryMode::Remote);
        $this->addPrepDay($program, '2026-08-03', ProgramPrepDayType::Remote);

        $this->actingAs($admin);
        $this->assertTrue(
            ProgramAttendanceCheckersRelationManager::canViewForRecord($program, ViewTrainingProgram::class),
        );
    }

    public function test_logout_clears_gate_session_only(): void
    {
        [$program, $admin] = $this->programWithAdmin();
        $this->addPrepDay($program, '2026-08-03');
        $result = app(ProgramAttendanceCheckerAccessService::class)->create($program, 'خروج');

        $this->actingAs($admin)
            ->withSession([
                EnsureGateAttendanceAccess::SESSION_CHECKER_ID => $result['checker']->id,
                EnsureGateAttendanceAccess::SESSION_PROGRAM_ID => $program->id,
                EnsureGateAttendanceAccess::SESSION_ACCESS_VERSION => $result['checker']->access_version,
            ])
            ->post(route('gate.logout', $program->slug))
            ->assertRedirect(route('gate.login', $program->slug));

        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(session()->has(EnsureGateAttendanceAccess::SESSION_CHECKER_ID));
    }

    public function test_gate_access_sets_security_headers(): void
    {
        [$program] = $this->programWithAdmin();
        $this->addPrepDay($program, '2026-08-03');

        $this->get(route('gate.login', $program->slug))
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_filament_create_requires_update_permission(): void
    {
        $admin = $this->adminUser();
        $program = $this->makeProgram('perm-check');
        $this->addPrepDay($program, '2026-08-03');
        $program->forceFill(['owner_id' => $admin->id, 'created_by' => $admin->id])->save();

        $this->withSession(['otp_verified' => true]);
        Livewire::actingAs($admin)
            ->test(ProgramAttendanceCheckersRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->assertSee('مسؤولو التحضير')
            ->assertSee('لا يوجد مسؤولو تحضير بعد');
    }

    /**
     * @return array{0: TrainingProgram, 1: User}
     */
    private function programWithAdmin(): array
    {
        $admin = $this->adminUser();
        $program = $this->makeProgram('gate-prep');
        $program->forceFill(['owner_id' => $admin->id, 'created_by' => $admin->id])->save();

        return [$program, $admin];
    }

    private function adminUser(): User
    {
        $admin = User::factory()->create([
            'role_type' => 'employee',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        return $admin;
    }

    private function makeProgram(
        string $slug,
        ProgramDeliveryMode $mode = ProgramDeliveryMode::InPerson,
    ): TrainingProgram {
        return TrainingProgram::query()->create([
            'title' => 'برنامج بوابة',
            'slug' => $slug.'-'.uniqid(),
            'description' => 'وصف',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Self,
            'delivery_mode' => $mode,
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

    /**
     * @param  array<string, mixed>  $userAttrs
     */
    private function register(
        TrainingProgram $program,
        array $userAttrs,
        RegistrationStatus $status = RegistrationStatus::Approved,
    ): ProgramRegistration {
        $user = User::factory()->create($userAttrs);

        return ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => $status,
            'approved_at' => $status === RegistrationStatus::Approved || $status === RegistrationStatus::Completed
                ? now()
                : null,
        ]);
    }

    private function withCheckerSession(ProgramAttendanceChecker $checker, TrainingProgram $program): static
    {
        return $this->withSession([
            EnsureGateAttendanceAccess::SESSION_CHECKER_ID => $checker->id,
            EnsureGateAttendanceAccess::SESSION_PROGRAM_ID => $program->id,
            EnsureGateAttendanceAccess::SESSION_ACCESS_VERSION => $checker->access_version,
        ]);
    }
}
