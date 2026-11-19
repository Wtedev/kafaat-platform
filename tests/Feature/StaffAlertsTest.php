<?php

namespace Tests\Feature;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TrainingProgramKind;
use App\Models\InboxNotification;
use App\Models\ProgramRegistration;
use App\Models\StaffAlert;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Attendance\ProgramAttendanceLinkService;
use App\Services\Inbox\InboxNotificationService;
use App\Services\Rbac\RbacCatalog;
use App\Services\StaffAlerts\StaffAlerts;
use App\Services\Surveys\ProgramSurveyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class StaffAlertsTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        config(['staff_ui.maintenance' => false, 'staff_ui.ready_modules' => ['training']]);
    }

    public function test_pending_requests_are_one_alert_per_program_that_follows_the_count_and_clears_itself(): void
    {
        $admin = $this->admin();
        $program = $this->program(['title' => 'دورة القيادة']);
        $first = $this->registration($program, RegistrationStatus::Pending);
        $this->registration($program, RegistrationStatus::Pending);
        $alerts = app(StaffAlerts::class);

        $alerts->refresh();
        $alert = StaffAlert::query()->sole();
        $this->assertSame('registrations.program:'.$program->id, $alert->key);
        $this->assertSame('2 طلبات بانتظار القبول', $alert->title);
        $this->assertSame(1, $alerts->unreadCount($admin));

        // Opening it marks it read and goes to the pending requests.
        $this->actingAsOtpVerified($admin)
            ->get(route('staff-alerts.open', $alert))
            ->assertRedirect(route('staff-ui.programs.tab', [$program, 'registrations']).'?status=pending');
        $this->assertSame(0, $alerts->unreadCount($admin));

        // A new request makes it unread again for everyone.
        $this->travel(1)->minutes();
        $this->registration($program, RegistrationStatus::Pending);
        $alerts->refresh();
        $this->assertSame('3 طلبات بانتظار القبول', $alert->fresh()->title);
        $this->assertSame(1, $alerts->unreadCount($admin));

        // Handling the requests resolves it.
        ProgramRegistration::query()->where('training_program_id', $program->id)->update(['status' => RegistrationStatus::Approved->value]);
        $alerts->refresh();
        $this->assertNotNull($alert->fresh()->resolved_at);
        $this->assertCount(0, $alerts->visibleFor($admin));
        $this->assertNotNull($first->fresh());
    }

    public function test_new_registrations_no_longer_flood_staff_inboxes(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        $registration = $this->registration($program, RegistrationStatus::Pending);

        app(InboxNotificationService::class)->notifyStaffOfNewProgramRegistration($registration);
        app(InboxNotificationService::class)->programLaunched($program);

        $this->assertSame(0, InboxNotification::query()->where('user_id', $admin->id)->count());
    }

    public function test_reminders_open_attendance_low_survey_and_ended_program_without_certificates(): void
    {
        $admin = $this->admin();
        $running = $this->program(['title' => 'برنامج جارٍ']);
        $this->registration($running, RegistrationStatus::Approved);
        $this->registration($running, RegistrationStatus::Approved);
        $link = app(ProgramAttendanceLinkService::class)->create($running, 'اليوم الأول', 30);
        app(ProgramAttendanceLinkService::class)->open($link);
        app(ProgramSurveyService::class)->provisionFromScratch($running);
        $running->surveys()->update(['opens_at' => now()->addMonth(), 'closes_at' => now()->addMonths(2)]);
        $running->surveys()->where('type', 'post')->update(['opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        $ended = $this->program(['title' => 'برنامج انتهى', 'start_date' => now()->subDays(10)->toDateString(), 'end_date' => now()->subDays(3)->toDateString()]);
        $this->registration($ended, RegistrationStatus::Approved);

        app(StaffAlerts::class)->refresh();
        $kinds = StaffAlert::query()->live()->pluck('kind')->sort()->values()->all();
        $this->assertSame(['program.attendance', 'program.certificates', 'program.survey'], $kinds);

        $page = $this->actingAsOtpVerified($admin)->get(route('staff-ui.programs.index'))->assertOk();
        $page->assertSee('التحضير مفتوح الآن: اليوم الأول')
            ->assertSee('انتهى البرنامج وما انصدرت شهاداته')
            ->assertSee('الاستبيان بعدي يقفل قريب وما عبّاه إلا 0 من 2');

        // Dismissing hides it for everyone until it grows.
        $survey = StaffAlert::query()->where('kind', 'program.survey')->sole();
        $this->actingAsOtpVerified($admin)->from(route('staff-ui.programs.index'))->post(route('staff-alerts.dismiss', $survey))->assertRedirect();
        app(StaffAlerts::class)->refresh();
        $this->assertNotNull($survey->fresh()->dismissed_at);
        $this->assertFalse(app(StaffAlerts::class)->visibleFor($this->admin())->contains('id', $survey->id));

        // Requests can't be dismissed; they go away when handled.
        $attendance = StaffAlert::query()->where('kind', 'program.attendance')->sole();
        $this->actingAsOtpVerified($admin)->post(route('staff-alerts.dismiss', $attendance))->assertStatus(422);
    }

    public function test_failed_program_emails_and_support_tickets_reach_the_right_people(): void
    {
        $program = $this->program();
        DB::table('email_logs')->insert([
            'recipient_email' => 'x@example.test', 'subject' => 'قبول', 'template_key' => 'program_registration.approved',
            'status' => 'failed', 'sent_at' => now(), 'training_program_id' => $program->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(StaffAlerts::class)->refresh();

        $this->assertTrue(StaffAlert::query()->where('kind', 'delivery.email')->exists());
        $programStaff = $this->staff(['programs.view']);
        $nobody = $this->staff([]);
        $this->assertCount(1, app(StaffAlerts::class)->visibleFor($programStaff));
        $this->assertCount(0, app(StaffAlerts::class)->visibleFor($nobody));

        $alert = StaffAlert::query()->sole();
        $this->actingAsOtpVerified($nobody)->get(route('staff-alerts.open', $alert))->assertNotFound();
    }

    public function test_staff_on_the_admin_panel_see_alerts_in_its_bell(): void
    {
        $staff = $this->staff(['programs.view', 'view_notifications']);
        $program = $this->program(['title' => 'برنامج اللوحة']);
        $this->registration($program, RegistrationStatus::Pending);
        app(StaffAlerts::class)->refresh();

        $this->actingAsOtpVerified($staff)
            ->get('/admin')
            ->assertOk()
            ->assertSee('1 طلب بانتظار القبول')
            ->assertSee('برنامج اللوحة');

        // Without the new pages the alert opens the program in the admin panel.
        $alert = StaffAlert::query()->sole();
        $this->assertStringContainsString('/admin/training-programs/'.$program->id, app(StaffAlerts::class)->linkFor($alert, $staff));
    }

    public function test_read_all_clears_the_badge(): void
    {
        $admin = $this->admin();
        $this->registration($this->program(), RegistrationStatus::Pending);
        app(StaffAlerts::class)->refresh();

        $this->actingAsOtpVerified($admin)->from('/admin')->post(route('staff-alerts.read-all'))->assertRedirect('/admin');
        $this->assertSame(0, app(StaffAlerts::class)->unreadCount($admin));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function program(array $overrides = []): TrainingProgram
    {
        return TrainingProgram::query()->create(array_merge([
            'title' => 'برنامج',
            'slug' => 'al-'.uniqid(),
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Professional,
            'delivery_mode' => ProgramDeliveryMode::Remote,
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
        ], $overrides));
    }

    private function registration(TrainingProgram $program, RegistrationStatus $status): ProgramRegistration
    {
        $user = User::factory()->create(['role_type' => 'beneficiary', 'is_active' => true]);

        return ProgramRegistration::query()->create(['training_program_id' => $program->id, 'user_id' => $user->id, 'status' => $status]);
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
        if ($permissions !== []) {
            $staff->givePermissionTo($permissions);
        }

        return $staff->fresh();
    }
}
