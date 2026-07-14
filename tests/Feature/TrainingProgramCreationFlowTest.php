<?php

namespace Tests\Feature;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Filament\Support\TrainingEntityFormSupport;
use App\Models\LearningPath;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class TrainingProgramCreationFlowTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
    }

    public function test_published_remote_program_appears_on_track_catalog_and_show_page(): void
    {
        $program = $this->createPublishedProgram([
            'title' => 'برنامج عن بُعد تجريبي',
            'slug' => 'remote-test-program',
            'competency_track' => CompetencyTrack::Self,
            'delivery_mode' => ProgramDeliveryMode::Remote,
        ]);

        $this->get(route('public.programs.show', $program->slug))
            ->assertOk()
            ->assertSee('برنامج عن بُعد تجريبي')
            ->assertSee('مسار الكفاءة الذاتية')
            ->assertSee('عن بُعد');

        $this->get(route('public.programs.track', CompetencyTrack::Self))
            ->assertOk()
            ->assertSee('برنامج عن بُعد تجريبي');
    }

    public function test_published_in_person_program_shows_venue_on_public_show_page(): void
    {
        $program = $this->createPublishedProgram([
            'title' => 'برنامج حضوري تجريبي',
            'slug' => 'in-person-test-program',
            'competency_track' => CompetencyTrack::Professional,
            'delivery_mode' => ProgramDeliveryMode::InPerson,
            'venue' => 'قاعة الاختبار',
        ]);

        $this->get(route('public.programs.show', $program->slug))
            ->assertOk()
            ->assertSee('مسار الكفاءة المهنية')
            ->assertSee('حضوري — قاعة الاختبار');

        $this->get(route('public.programs.track', CompetencyTrack::Professional))
            ->assertOk()
            ->assertSee('برنامج حضوري تجريبي');
    }

    public function test_track_catalog_card_shows_plain_text_excerpt_for_tiptap_description(): void
    {
        $json = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"\u0646\u0628\u0630\u0629 \u0639\u0646 \u0628\u0631\u0646\u0627\u0645\u062c \u0642\u0627\u062f\u0629 \u0627\u0644\u062a\u0637\u0648\u0639 \u0644\u062a\u0646\u0645\u064a\u0629 \u0627\u0644\u0645\u0647\u0627\u0631\u0627\u062a \u0627\u0644\u0642\u064a\u0627\u062f\u064a\u0629."}]}]}';

        $program = $this->createPublishedProgram([
            'title' => 'قادة التطوع',
            'slug' => 'volunteer-leaders-track-card',
            'competency_track' => CompetencyTrack::Community,
            'description' => $json,
        ]);

        $this->get(route('public.programs.track', CompetencyTrack::Community))
            ->assertOk()
            ->assertSee('قادة التطوع')
            ->assertSee('نبذة عن برنامج')
            ->assertDontSee('"type":"doc"', false);
    }

    public function test_public_show_page_hides_registration_date_range_but_keeps_status(): void
    {
        $program = $this->createPublishedProgram([
            'title' => 'برنامج بفترة تسجيل',
            'slug' => 'registration-window-hidden',
            'registration_start' => Carbon::parse('2026-06-01'),
            'registration_end' => Carbon::parse('2026-08-01'),
        ]);

        $this->get(route('public.programs.show', $program->slug))
            ->assertOk()
            ->assertSee('حالة التسجيل')
            ->assertSee($program->registrationWindowStatusLabel())
            ->assertDontSee('فترة التسجيل')
            ->assertDontSee('موعد التسجيل');
    }

    public function test_public_show_page_renders_program_presenters_section(): void
    {
        $program = $this->createPublishedProgram([
            'title' => 'برنامج قادة التطوع',
            'slug' => 'volunteer-leaders-presenters',
            'competency_track' => CompetencyTrack::Community,
            'delivery_mode' => ProgramDeliveryMode::Remote,
            'program_presenters' => [
                ['name' => 'د. أحمد الرفاعي', 'role' => ''],
                ['name' => 'د. محمد النصار', 'role' => ''],
            ],
        ]);

        $this->get(route('public.programs.show', $program->slug))
            ->assertOk()
            ->assertSee('مقدمو البرنامج')
            ->assertSee('د. أحمد الرفاعي')
            ->assertSee('د. محمد النصار');
    }

    public function test_apply_delivery_mode_fields_clears_venue_for_remote_programs(): void
    {
        $result = TrainingEntityFormSupport::applyDeliveryModeFields([
            'delivery_mode' => ProgramDeliveryMode::Remote->value,
            'venue' => 'يجب أن تُحذف',
        ]);

        $this->assertNull($result['venue']);
    }

    public function test_path_linked_program_inherits_competency_track_from_learning_path(): void
    {
        $path = LearningPath::query()->create([
            'title' => 'مسار تجريبي',
            'slug' => 'test-learning-path',
            'competency_track' => CompetencyTrack::Community,
            'status' => \App\Enums\PathStatus::Draft,
        ]);

        $result = TrainingEntityFormSupport::applyProgramPathLinkSettings([
            'is_linked_to_path' => true,
            'learning_path_id' => $path->id,
            'competency_track' => null,
            'capacity' => 20,
            'registration_start' => '2026-06-01',
            'registration_end' => '2026-06-10',
            'weekdays' => [0, 1],
        ]);

        $this->assertSame(CompetencyTrack::Community->value, $result['competency_track']);
        $this->assertNull($result['capacity']);
    }

    public function test_staff_with_program_permission_can_open_program_create_page(): void
    {
        $staff = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staff->assignRole('programs_management');

        $this->actingAs($staff)
            ->withSession(['otp_verified' => true])
            ->get('/admin/training-programs/create')
            ->assertOk();
    }

    public function test_create_page_works_when_staff_user_has_json_notification_settings(): void
    {
        $staff = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
            'notification_settings' => ['programs_new' => true, 'news' => false],
        ]);
        $staff->assignRole('programs_management');

        $this->actingAs($staff)
            ->withSession(['otp_verified' => true])
            ->get('/admin/training-programs/create')
            ->assertOk();
    }

    public function test_create_page_returns_forbidden_when_program_create_permission_missing_from_database(): void
    {
        \Spatie\Permission\Models\Permission::query()
            ->where('name', 'programs.create')
            ->delete();

        $staff = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staff->assignRole('programs_management');

        $this->actingAs($staff)
            ->withSession(['otp_verified' => true])
            ->get('/admin/training-programs/create')
            ->assertForbidden();
    }

    public function test_create_page_works_after_permissions_are_reseeded(): void
    {
        \Spatie\Permission\Models\Permission::query()
            ->where('name', 'programs.create')
            ->delete();

        $this->seed(RolesAndPermissionsSeeder::class);

        $staff = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staff->assignRole('programs_management');

        $this->actingAs($staff)
            ->withSession(['otp_verified' => true])
            ->get('/admin/training-programs/create')
            ->assertOk();
    }

    public function test_staff_can_persist_program_with_competency_and_delivery_fields(): void
    {
        $staff = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staff->assignRole('programs_management');

        $program = TrainingProgram::query()->create([
            'title' => 'برنامج حفظ تجريبي',
            'slug' => 'persist-test-program',
            'description' => 'اختبار حفظ البرنامج مع مسار الكفاءة وطريقة التنفيذ.',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Professional,
            'delivery_mode' => ProgramDeliveryMode::Remote,
            'status' => ProgramStatus::Draft,
            'start_date' => Carbon::parse('2026-08-01'),
            'end_date' => Carbon::parse('2026-08-15'),
            'registration_start' => Carbon::parse('2026-07-15'),
            'registration_end' => Carbon::parse('2026-08-20'),
            'created_by' => $staff->id,
            'owner_id' => $staff->id,
        ]);

        $this->assertDatabaseHas('training_programs', [
            'id' => $program->id,
            'competency_track' => CompetencyTrack::Professional->value,
            'delivery_mode' => ProgramDeliveryMode::Remote->value,
        ]);
    }

    public function test_create_page_handles_learning_path_with_invalid_competency_track_value(): void
    {
        $path = LearningPath::query()->create([
            'title' => 'مسار بقيمة غير صالحة',
            'slug' => 'invalid-competency-track-path',
            'status' => \App\Enums\PathStatus::Draft,
        ]);

        \Illuminate\Support\Facades\DB::table('learning_paths')
            ->where('id', $path->id)
            ->update(['competency_track' => 'legacy-invalid']);

        $staff = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staff->assignRole('programs_management');

        $this->withoutExceptionHandling()
            ->actingAs($staff)
            ->withSession(['otp_verified' => true])
            ->get('/admin/training-programs/create')
            ->assertOk();
    }

    public function test_admin_with_notification_modal_can_open_program_create_page(): void
    {
        $admin = User::factory()->create([
            'role_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
            'notification_prefs_set_at' => null,
        ]);
        $admin->assignRole('admin');

        $this->withoutExceptionHandling()
            ->actingAs($admin)
            ->withSession(['otp_verified' => true])
            ->get('/admin/training-programs/create')
            ->assertOk()
            ->assertSee('fi-training-schedule', false);
    }

  /**
   * @param  array<string, mixed>  $overrides
   */
    private function createPublishedProgram(array $overrides = []): TrainingProgram
    {
        return TrainingProgram::query()->create(array_merge([
            'title' => 'برنامج تجريبي',
            'slug' => 'test-program-'.uniqid(),
            'description' => 'وصف البرنامج التجريبي للاختبار.',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Self,
            'delivery_mode' => ProgramDeliveryMode::Remote,
            'status' => ProgramStatus::Published,
            'published_at' => Carbon::now()->subDay(),
            'start_date' => Carbon::parse('2026-07-01'),
            'end_date' => Carbon::parse('2026-07-15'),
            'registration_start' => Carbon::parse('2026-06-01'),
            'registration_end' => Carbon::parse('2026-08-01'),
            'capacity' => 30,
            'auto_accept_registrations' => true,
        ], $overrides));
    }
}
