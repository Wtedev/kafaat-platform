<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\CompetencyTrack;
use App\Enums\ProgramActivityAction;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramPrepDayType;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TrainingProgramKind;
use App\Models\ProgramActivity;
use App\Models\ProgramAttendance;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\ProgramRegistrationService;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class StaffUiProgramPageTest extends TestCase
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

    public function test_about_tab_shows_program_facts_and_counts(): void
    {
        $viewer = $this->staff(['programs.view']);
        $program = $this->program(['title' => 'برنامج التجربة', 'capacity' => 30, 'venue' => 'قاعة كفاءات', 'delivery_mode' => ProgramDeliveryMode::InPerson]);
        $this->registration($program, RegistrationStatus::Pending);
        $this->registration($program, RegistrationStatus::Approved);

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.show', $program))
            ->assertOk()
            ->assertSee('برنامج التجربة')
            ->assertSee('عن البرنامج')
            ->assertSee('سجل النشاط')
            ->assertSee('قاعة كفاءات')
            ->assertSee('بانتظار القبول')
            ->assertDontSee('href="'.route('staff-ui.programs.tab', [$program, 'settings']).'"', false);
    }

    public function test_about_tab_lists_dates_and_attendance_days(): void
    {
        $program = $this->program();
        $program->prepDays()->create(['prep_date' => '2026-12-02', 'delivery_type' => ProgramPrepDayType::InPerson, 'requires_attendance' => true]);
        $program->prepDays()->create(['prep_date' => '2026-12-03', 'delivery_type' => ProgramPrepDayType::Remote, 'requires_attendance' => true]);
        $program->attendanceLinks()->create(['name' => 'رابط المحاضرة الأولى', 'open_minutes' => 10]);

        $this->actingAsOtpVerified($this->staff(['programs.view']))
            ->get(route('staff-ui.programs.show', $program))
            ->assertOk()
            ->assertSeeInOrder(['المواعيد', 'التسجيل', 'البرنامج', 'أيام التحضير', 'النبذة'])
            ->assertSee('QR')
            ->assertSee('يوم حضوري')
            ->assertSee('يوم عن بُعد')
            ->assertSee('رابط المحاضرة الأولى')
            ->assertSee('لم يُفتح بعد')
            ->assertSee('data-sui-cal', false)
            ->assertSee('title="يوم البرنامج · تحضير QR"', false)
            ->assertSee('أيام البرنامج');
    }

    public function test_settings_tab_needs_update_permission(): void
    {
        $program = $this->program();

        $this->actingAsOtpVerified($this->staff(['programs.view']))
            ->get(route('staff-ui.programs.tab', [$program, 'settings']))
            ->assertForbidden();

        $this->actingAsOtpVerified($this->admin())
            ->get(route('staff-ui.programs.tab', [$program, 'settings']))
            ->assertOk()
            ->assertSee('البيانات الأساسية')
            ->assertSee('النشر والأرشفة');
    }

    public function test_tabs_not_built_yet_point_to_the_current_admin_panel(): void
    {
        $program = $this->program();

        $this->actingAsOtpVerified($this->admin())
            ->get(route('staff-ui.programs.tab', [$program, 'registrations']))
            ->assertOk()
            ->assertSee('قيد البناء')
            ->assertSee('فتح في لوحة الإدارة');
    }

    public function test_saving_a_settings_section_updates_the_program_and_logs_the_change(): void
    {
        $admin = $this->admin();
        $program = $this->program();

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.settings.update', [$program, 'schedule']), [
                'start_date' => '2026-12-01',
                'end_date' => '2026-12-10',
                'registration_start' => '2026-11-20',
                'registration_end' => '2026-11-30',
                'weekdays' => [0, 2],
            ])
            ->assertRedirect(route('staff-ui.programs.tab', [$program, 'settings']).'#schedule')
            ->assertSessionHas('status', 'تم حفظ «الجدول».');

        $program->refresh();
        $this->assertSame('2026-12-10', $program->end_date->toDateString());
        $this->assertSame([0, 2], $program->weekdays);

        $activity = ProgramActivity::query()->where('action', ProgramActivityAction::SettingsUpdated)->sole();
        $this->assertSame($admin->id, $activity->actor_id);
        $this->assertSame('schedule', $activity->properties['section']);
        $this->assertContains('end_date', $activity->properties['fields']);
        $this->assertNotNull($activity->created_at);
    }

    public function test_saving_unchanged_settings_does_not_log(): void
    {
        $program = $this->program();

        $this->actingAsOtpVerified($this->admin())
            ->post(route('staff-ui.programs.settings.update', [$program, 'notifications']), ['notify_audience' => '1'])
            ->assertSessionHas('status', 'لا تغييرات في «الإشعارات».');

        $this->assertDatabaseMissing('program_activities', ['action' => ProgramActivityAction::SettingsUpdated->value]);
    }

    public function test_invalid_settings_keep_the_program_unchanged(): void
    {
        $program = $this->program();

        $this->actingAsOtpVerified($this->admin())
            ->post(route('staff-ui.programs.settings.update', [$program, 'basics']), [
                'title' => '',
                'program_kind' => TrainingProgramKind::Course->value,
                'competency_track' => CompetencyTrack::Self->value,
                'delivery_mode' => ProgramDeliveryMode::Remote->value,
            ])
            ->assertSessionHasErrors('title');

        $this->assertSame('برنامج', $program->fresh()->title);
    }

    public function test_formatted_description_is_kept_unless_the_text_is_edited(): void
    {
        $html = '<p>السطر <strong>الأول</strong></p><p>السطر الثاني</p>';
        $program = $this->program(['description' => $html]);
        $basics = [
            'title' => 'برنامج',
            'program_kind' => TrainingProgramKind::Course->value,
            'competency_track' => CompetencyTrack::Professional->value,
            'delivery_mode' => ProgramDeliveryMode::Remote->value,
        ];

        $this->actingAsOtpVerified($this->admin())
            ->get(route('staff-ui.programs.tab', [$program, 'settings']))
            ->assertSee("السطر الأول\nالسطر الثاني", false)
            ->assertDontSee('&lt;p&gt;', false);

        $this->actingAsOtpVerified($this->admin())
            ->post(route('staff-ui.programs.settings.update', [$program, 'basics']), $basics + ['description' => "السطر الأول\nالسطر الثاني"]);
        $this->assertSame($html, $program->fresh()->description);

        $this->actingAsOtpVerified($this->admin())
            ->post(route('staff-ui.programs.settings.update', [$program, 'basics']), $basics + ['description' => 'نص جديد']);
        $this->assertSame('نص جديد', $program->fresh()->description);
    }

    public function test_publish_unpublish_archive_and_restore_are_logged(): void
    {
        $admin = $this->admin();
        $program = $this->program(['status' => ProgramStatus::Draft, 'published_at' => null]);
        $url = route('staff-ui.programs.status', $program);

        $this->actingAsOtpVerified($admin)->post($url, ['action' => 'publish'])->assertSessionHas('status', 'تم نشر البرنامج.');
        $this->assertSame(ProgramStatus::Published, $program->fresh()->status);

        $this->actingAsOtpVerified($admin)->post($url, ['action' => 'unpublish']);
        $this->assertSame(ProgramStatus::Draft, $program->fresh()->status);

        $this->actingAsOtpVerified($admin)->post($url, ['action' => 'archive']);
        $this->assertSame(ProgramStatus::Archived, $program->fresh()->status);

        $this->actingAsOtpVerified($admin)->post($url, ['action' => 'restore']);
        $this->assertSame(ProgramStatus::Draft, $program->fresh()->status);

        $this->assertSame(
            [
                ProgramActivityAction::Published,
                ProgramActivityAction::Unpublished,
                ProgramActivityAction::Archived,
                ProgramActivityAction::Restored,
            ],
            ProgramActivity::query()->orderBy('id')->get()->pluck('action')->all(),
        );
    }

    public function test_publishing_without_dates_is_refused(): void
    {
        $program = $this->program([
            'status' => ProgramStatus::Draft,
            'published_at' => null,
            'registration_start' => null,
            'registration_end' => null,
        ]);

        $this->actingAsOtpVerified($this->admin())
            ->post(route('staff-ui.programs.status', $program), ['action' => 'publish'])
            ->assertSessionHasErrors('status');

        $this->assertSame(ProgramStatus::Draft, $program->fresh()->status);
    }

    public function test_status_changes_need_the_matching_permission(): void
    {
        $program = $this->program(['status' => ProgramStatus::Draft, 'published_at' => null]);
        $editor = $this->staff(['programs.view', 'programs.update']);

        $this->actingAsOtpVerified($editor)
            ->post(route('staff-ui.programs.status', $program), ['action' => 'publish'])
            ->assertForbidden();

        $this->assertSame(ProgramStatus::Draft, $program->fresh()->status);
    }

    public function test_registration_attendance_and_certificate_events_are_logged_with_time(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        $registration = $this->registration($program, RegistrationStatus::Pending);

        $this->actingAs($admin);
        app(ProgramRegistrationService::class)->approve($registration, $admin);

        ProgramAttendance::query()->create([
            'program_registration_id' => $registration->id,
            'training_date' => '2026-12-01',
            'status' => AttendanceStatus::Present,
        ]);

        $actions = ProgramActivity::query()
            ->where('training_program_id', $program->id)
            ->orderBy('id')
            ->get();

        $this->assertSame(
            [
                ProgramActivityAction::RegistrationCreated,
                ProgramActivityAction::RegistrationApproved,
                ProgramActivityAction::AttendanceMarked,
            ],
            $actions->pluck('action')->all(),
        );
        $this->assertSame($admin->id, $actions[1]->actor_id);
        $this->assertSame('2026-12-01', $actions[2]->properties['date']);
        $this->assertTrue($actions->every(fn (ProgramActivity $a): bool => $a->created_at !== null));

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.programs.tab', [$program, 'activity']))
            ->assertOk()
            ->assertSee('قبول تسجيل')
            ->assertSee('تحضير');

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.programs.tab', [$program, 'activity']).'?group=attendance')
            ->assertOk()
            ->assertSee('تحضير')
            ->assertDontSee('قبول تسجيل');
    }

    public function test_wizard_logs_program_creation(): void
    {
        $creator = $this->staff(['programs.view', 'programs.create', 'programs.update']);

        $this->actingAsOtpVerified($creator)
            ->post(route('staff-ui.programs.wizard.store-new'), [
                'title' => 'من المعالج',
                'program_kind' => TrainingProgramKind::Course->value,
                'competency_track' => CompetencyTrack::Professional->value,
                'delivery_mode' => ProgramDeliveryMode::Remote->value,
            ])
            ->assertRedirect();

        $program = TrainingProgram::query()->where('title', 'من المعالج')->sole();
        $this->assertDatabaseHas('program_activities', [
            'training_program_id' => $program->id,
            'action' => ProgramActivityAction::Created->value,
            'actor_id' => $creator->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function program(array $overrides = []): TrainingProgram
    {
        return TrainingProgram::query()->create(array_merge([
            'title' => 'برنامج',
            'slug' => 'program-'.uniqid(),
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Professional,
            'delivery_mode' => ProgramDeliveryMode::Remote,
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-05',
            'registration_start' => '2026-11-01',
            'registration_end' => '2026-11-30',
            'notify_on_publish' => true,
            'notify_milestones' => true,
            'notify_registrants_on_update' => true,
        ], $overrides));
    }

    private function registration(TrainingProgram $program, RegistrationStatus $status): ProgramRegistration
    {
        return ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => User::factory()->create(['role_type' => 'beneficiary', 'is_active' => true])->id,
            'status' => $status,
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create([
            'role_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(RbacCatalog::ROLE_ADMIN);

        return $admin->fresh();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function staff(array $permissions): User
    {
        $staff = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staff->assignRole(RbacCatalog::ROLE_STAFF);
        $staff->givePermissionTo($permissions);

        return $staff->fresh();
    }
}
