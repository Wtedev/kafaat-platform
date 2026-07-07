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
