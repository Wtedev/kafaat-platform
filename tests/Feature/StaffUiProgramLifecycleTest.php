<?php

namespace Tests\Feature;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramActivityAction;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SurveyQuestionType;
use App\Enums\TrainingProgramKind;
use App\Models\CertificateTemplate;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use App\Services\Surveys\ProgramSurveyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class StaffUiProgramLifecycleTest extends TestCase
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

    public function test_copy_makes_a_draft_with_content_surveys_and_certificate_but_no_people(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        Storage::disk('public')->put('programs/covers/a.jpg', 'img');
        Storage::disk('public')->put('certificates/bg.png', 'bg');
        $source = $this->program([
            'title' => 'دورة القيادة',
            'description' => 'وصف الدورة',
            'capacity' => 40,
            'registration_questions' => [['label' => 'سؤال', 'type' => 'text']],
            'image' => 'programs/covers/a.jpg',
        ]);
        $this->registration($source);
        app(ProgramSurveyService::class)->provisionFromScratch($source);
        $pre = $source->surveys()->where('type', 'pre')->sole();
        app(ProgramSurveyService::class)->replaceQuestions($pre, [['type' => SurveyQuestionType::Scale->value, 'prompt' => 'مستواك؟']]);
        CertificateTemplate::query()->create(['training_program_id' => $source->id, 'background_path' => 'certificates/bg.png', 'elements' => [['type' => 'name']], 'hours' => 6]);

        $response = $this->actingAsOtpVerified($admin)->post(route('staff-ui.programs.copy', $source));

        $copy = TrainingProgram::query()->whereKeyNot($source->id)->sole();
        $response->assertRedirect(route('staff-ui.programs.tab', [$copy, 'settings']));
        $this->assertSame('دورة القيادة (نسخة)', $copy->title);
        $this->assertSame(ProgramStatus::Draft, $copy->status);
        $this->assertNotSame($source->slug, $copy->slug);
        $this->assertSame(40, $copy->capacity);
        $this->assertSame('وصف الدورة', $copy->description);
        $this->assertSame($source->registration_questions, $copy->registration_questions);
        $this->assertNull($copy->start_date);
        $this->assertSame(0, $copy->registrations()->count());
        $this->assertNotSame($source->image, $copy->image);
        Storage::disk('public')->assertExists($copy->image);

        $this->assertSame(3, $copy->surveys()->count());
        $this->assertSame(['مستواك؟'], $copy->surveys()->where('type', 'pre')->sole()->questions()->pluck('prompt')->all());
        $this->assertSame(['مستواك؟'], $copy->surveys()->where('type', 'post')->sole()->questions()->pluck('prompt')->all());
        $this->assertNotSame($pre->public_token, $copy->surveys()->where('type', 'pre')->value('public_token'));

        $template = $copy->certificateTemplate()->sole();
        $this->assertSame([['type' => 'name']], $template->elements);
        $this->assertNotSame('certificates/bg.png', $template->background_path);
        Storage::disk('public')->assertExists($template->background_path);

        $this->assertTrue($copy->activities()->where('action', ProgramActivityAction::Created)->where('description', 'نسخة من «دورة القيادة»')->exists());
    }

    public function test_deleting_moves_a_program_to_the_trash_and_restoring_brings_everything_back(): void
    {
        $admin = $this->admin();
        $program = $this->program(['title' => 'برنامج سيُحذف']);
        $registration = $this->registration($program);
        $trainee = $registration->user;

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.delete', $program))
            ->assertRedirect(route('staff-ui.programs.index', ['view' => 'trash']));

        $this->assertSoftDeleted($program);
        $this->assertSame($admin->id, TrainingProgram::withTrashed()->find($program->id)->deleted_by);
        $this->assertDatabaseHas('program_registrations', ['id' => $registration->id]);

        // Gone from staff lists, the public site and the trainee's account.
        $this->actingAsOtpVerified($admin)->get(route('staff-ui.programs.index'))->assertDontSee(route('staff-ui.programs.show', $program->id));
        $this->actingAsOtpVerified($admin)->get(route('staff-ui.programs.show', $program->id))->assertNotFound();
        $this->get(route('public.programs.show', $program->slug))->assertNotFound();
        $this->assertSame(0, $trainee->programRegistrations()->count());
        $this->assertNotNull($registration->fresh()->trainingProgram);

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.programs.index', ['view' => 'trash']))
            ->assertOk()
            ->assertSee('برنامج سيُحذف')
            ->assertSee('استعادة');

        // A new program can reuse the title without a slug clash.
        $this->program(['title' => 'برنامج سيُحذف', 'slug' => null]);

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.restore', $program->id))
            ->assertRedirect(route('staff-ui.programs.show', $program->id));
        $this->assertNotSoftDeleted($program);
        $this->assertSame(1, $trainee->programRegistrations()->count());
        $this->assertSame(
            [ProgramActivityAction::Deleted->value, ProgramActivityAction::Undeleted->value],
            $program->activities()->whereIn('action', [ProgramActivityAction::Deleted->value, ProgramActivityAction::Undeleted->value])->pluck('action')->map(fn ($a) => $a->value)->sort()->values()->all(),
        );
    }

    public function test_archive_list_and_permissions(): void
    {
        $this->program(['title' => 'برنامج مؤرشف', 'status' => ProgramStatus::Archived]);
        $this->program(['title' => 'برنامج حالي']);
        $viewer = $this->staff(['programs.view']);

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.index', ['view' => 'archive']))
            ->assertSee('برنامج مؤرشف')
            ->assertDontSee('المحذوفات');

        // Without the delete permission the trash falls back to the main list.
        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.index', ['view' => 'trash']))
            ->assertSee('برنامج حالي');

        $program = TrainingProgram::query()->where('title', 'برنامج حالي')->sole();
        $this->actingAsOtpVerified($viewer)->post(route('staff-ui.programs.delete', $program))->assertForbidden();
        $this->actingAsOtpVerified($viewer)->post(route('staff-ui.programs.copy', $program))->assertForbidden();
        $this->assertNotSoftDeleted($program);
    }

    public function test_the_admin_panel_delete_only_moves_to_the_trash(): void
    {
        $program = $this->program();
        $program->delete();

        $this->assertSoftDeleted($program);
        $this->assertDatabaseHas('training_programs', ['id' => $program->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function program(array $overrides = []): TrainingProgram
    {
        return TrainingProgram::query()->create(array_merge([
            'title' => 'برنامج',
            'slug' => 'lc-'.uniqid(),
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Professional,
            'delivery_mode' => ProgramDeliveryMode::Remote,
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
        ], $overrides));
    }

    private function registration(TrainingProgram $program): ProgramRegistration
    {
        $user = User::factory()->create(['role_type' => 'beneficiary', 'is_active' => true]);

        return ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => RegistrationStatus::Approved,
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
