<?php

namespace Tests\Feature;

use App\Enums\CompetencyTrack;
use App\Enums\IdentityCategory;
use App\Enums\ProfileGender;
use App\Enums\ProgramActivityAction;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TrainingProgramKind;
use App\Jobs\BulkApproveProgramRegistrationsJob;
use App\Models\Profile;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Notifications\ProgramRegistrationApproved;
use App\Notifications\ProgramRegistrationRejected;
use App\Services\ProgramRegistration\BulkProgramRegistrationProcessor;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class StaffUiProgramRegistrationsTest extends TestCase
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

    public function test_tab_lists_registrants_with_status_counts_and_filters(): void
    {
        $program = $this->program();
        $this->registration($program, 'نورة السعيد', RegistrationStatus::Pending, ProfileGender::Female, IdentityCategory::Saudi);
        $this->registration($program, 'خالد العمر', RegistrationStatus::Approved, ProfileGender::Male, IdentityCategory::Resident);
        $staff = $this->staff(['programs.view', 'registrations.view', 'registrations.approve', 'registrations.reject']);

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.programs.tab', [$program, 'registrations']))
            ->assertOk()
            ->assertSee('نورة السعيد')
            ->assertSee('خالد العمر')
            ->assertSee('قبول المحدد');

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.programs.tab', [$program, 'registrations']).'?status=pending')
            ->assertOk()
            ->assertSee('نورة السعيد')
            ->assertDontSee('خالد العمر');

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.programs.tab', [$program, 'registrations']).'?gender=male&nationality=non_saudi')
            ->assertOk()
            ->assertSee('خالد العمر')
            ->assertDontSee('نورة السعيد');

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.programs.tab', [$program, 'registrations']).'?q=نوره')
            ->assertOk()
            ->assertSee('نورة السعيد')
            ->assertDontSee('خالد العمر');
    }

    public function test_single_approve_and_reject_send_their_emails(): void
    {
        Notification::fake();
        $program = $this->program();
        $first = $this->registration($program, 'الأولى', RegistrationStatus::Pending);
        $second = $this->registration($program, 'الثانية', RegistrationStatus::Pending);
        $staff = $this->staff(['programs.view', 'registrations.approve', 'registrations.reject']);

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.programs.registrations.approve', [$program, $first]))
            ->assertRedirect(route('staff-ui.programs.tab', [$program, 'registrations']));
        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.programs.registrations.reject', [$program, $second]), ['rejected_reason' => 'اكتمل العدد'])
            ->assertSessionHas('status');

        $this->assertSame(RegistrationStatus::Approved, $first->fresh()->status);
        $this->assertSame(RegistrationStatus::Rejected, $second->fresh()->status);
        $this->assertSame('اكتمل العدد', $second->fresh()->rejected_reason);
        Notification::assertSentTo($first->user, ProgramRegistrationApproved::class);
        Notification::assertSentTo($second->user, ProgramRegistrationRejected::class);
    }

    public function test_approving_needs_the_permission(): void
    {
        $program = $this->program();
        $registration = $this->registration($program, 'بدون صلاحية', RegistrationStatus::Pending);

        $this->actingAsOtpVerified($this->staff(['programs.view']))
            ->post(route('staff-ui.programs.registrations.approve', [$program, $registration]))
            ->assertForbidden();

        $this->assertSame(RegistrationStatus::Pending, $registration->fresh()->status);
    }

    public function test_registration_from_another_program_is_refused(): void
    {
        $program = $this->program();
        $other = $this->program(['slug' => 'other-'.uniqid()]);
        $registration = $this->registration($other, 'برنامج آخر', RegistrationStatus::Pending);

        $this->actingAsOtpVerified($this->admin())
            ->post(route('staff-ui.programs.registrations.approve', [$program, $registration]))
            ->assertNotFound();
    }

    public function test_bulk_approve_selected_and_all_matching(): void
    {
        Notification::fake();
        $program = $this->program();
        $a = $this->registration($program, 'أ', RegistrationStatus::Pending, ProfileGender::Female);
        $b = $this->registration($program, 'ب', RegistrationStatus::Pending, ProfileGender::Male);
        $c = $this->registration($program, 'ج', RegistrationStatus::Pending, ProfileGender::Female);
        $admin = $this->admin();

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.registrations.bulk', $program), ['action' => 'approve', 'ids' => [$a->id]])
            ->assertSessionHas('status');
        $this->assertSame(RegistrationStatus::Approved, $a->fresh()->status);
        $this->assertSame(RegistrationStatus::Pending, $b->fresh()->status);

        // Every result of the current filter (female + pending), not only the visible page.
        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.registrations.bulk', $program), [
                'action' => 'reject',
                'all_matching' => '1',
                'gender' => 'female',
                'status' => 'pending',
                'rejected_reason' => 'السبب',
            ]);
        $this->assertSame(RegistrationStatus::Rejected, $c->fresh()->status);
        $this->assertSame(RegistrationStatus::Pending, $b->fresh()->status);
        Notification::assertSentTo($c->user, ProgramRegistrationRejected::class);
    }

    public function test_large_bulk_runs_in_the_background(): void
    {
        Queue::fake();
        $program = $this->program();
        $ids = [];
        for ($i = 0; $i <= BulkProgramRegistrationProcessor::QUEUE_THRESHOLD; $i++) {
            $ids[] = $this->registration($program, 'مسجل '.$i, RegistrationStatus::Pending)->id;
        }

        $this->actingAsOtpVerified($this->admin())
            ->post(route('staff-ui.programs.registrations.bulk', $program), ['action' => 'approve', 'ids' => $ids])
            ->assertSessionHas('status');

        Queue::assertPushed(BulkApproveProgramRegistrationsJob::class);
    }

    public function test_email_preview_renders_the_real_message(): void
    {
        $program = $this->program(['title' => 'برنامج المعاينة']);
        $registration = $this->registration($program, 'سارة', RegistrationStatus::Pending);
        $admin = $this->admin();

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.programs.registrations.preview', $program).'?kind=approve&registration='.$registration->id)
            ->assertOk()
            ->assertSee('برنامج المعاينة');

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.programs.registrations.preview', $program).'?kind=reject&registration='.$registration->id.'&rejected_reason='.urlencode('اكتمل العدد'))
            ->assertOk()
            ->assertSee('اكتمل العدد');

        $this->assertSame(RegistrationStatus::Pending, $registration->fresh()->status);
    }

    public function test_closing_registration_blocks_new_registrations_and_is_logged(): void
    {
        $program = $this->program([
            'registration_start' => now()->subDay()->toDateString(),
            'registration_end' => now()->addWeek()->toDateString(),
        ]);
        $admin = $this->admin();
        $this->assertTrue($program->isRegistrationOpen());

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.registrations.window', $program), ['action' => 'close'])
            ->assertSessionHas('status');

        $program->refresh();
        $this->assertTrue($program->isRegistrationManuallyClosed());
        $this->assertFalse($program->isRegistrationOpen());
        $this->assertSame(0, TrainingProgram::query()->registrationOpen()->whereKey($program->id)->count());

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.programs.index', ['registration' => 'manual_closed']))
            ->assertSee($program->title);

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.registrations.window', $program), ['action' => 'reopen']);
        $this->assertTrue($program->fresh()->isRegistrationOpen());

        $this->assertSame(
            [ProgramActivityAction::RegistrationWindowClosed, ProgramActivityAction::RegistrationWindowReopened],
            $program->activities()->reorder('id')->pluck('action')->all(),
        );
    }

    public function test_export_downloads_an_excel_file(): void
    {
        $program = $this->program();
        $this->registration($program, 'للتصدير', RegistrationStatus::Approved);

        $response = $this->actingAsOtpVerified($this->admin())
            ->get(route('staff-ui.programs.registrations.export', $program).'?status=approved');

        $response->assertOk();
        $this->assertStringContainsString('spreadsheet', (string) $response->headers->get('content-type'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function program(array $overrides = []): TrainingProgram
    {
        return TrainingProgram::query()->create(array_merge([
            'title' => 'برنامج التسجيلات',
            'slug' => 'regs-'.uniqid(),
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Professional,
            'delivery_mode' => ProgramDeliveryMode::Remote,
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'start_date' => now()->addMonth()->toDateString(),
            'registration_start' => now()->subWeek()->toDateString(),
            'registration_end' => now()->addWeek()->toDateString(),
        ], $overrides));
    }

    private function registration(
        TrainingProgram $program,
        string $name,
        RegistrationStatus $status,
        ?ProfileGender $gender = null,
        ?IdentityCategory $identity = null,
    ): ProgramRegistration {
        $user = User::factory()->create([
            'name' => $name,
            'role_type' => 'beneficiary',
            'is_active' => true,
            'identity_category' => $identity,
        ]);
        Profile::query()->updateOrCreate(['user_id' => $user->id], ['gender' => $gender, 'birth_date' => '1998-05-01']);

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
