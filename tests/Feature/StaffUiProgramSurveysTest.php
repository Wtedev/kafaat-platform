<?php

namespace Tests\Feature;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramActivityAction;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SurveyQuestionType;
use App\Enums\SurveyType;
use App\Enums\TrainingProgramKind;
use App\Models\ProgramRegistration;
use App\Models\SurveyTemplate;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use App\Services\Surveys\ProgramSurveyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class StaffUiProgramSurveysTest extends TestCase
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

    public function test_surveys_are_created_from_templates_and_listed(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        $pre = SurveyTemplate::query()->create(['title' => 'قالب قبلي']);
        $pre->questions()->create(['position' => 1, 'type' => SurveyQuestionType::Scale, 'prompt' => 'ما مدى معرفتك؟', 'required' => true]);
        $sat = SurveyTemplate::query()->create(['title' => 'قالب رضا']);
        $sat->questions()->create(['position' => 1, 'type' => SurveyQuestionType::Text, 'prompt' => 'ما رأيك؟', 'required' => true]);

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.programs.tab', [$program, 'surveys']))
            ->assertOk()
            ->assertSee('إنشاء الاستبيانات')
            ->assertSee('قالب قبلي');

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.surveys.provision', $program), ['source' => 'template'])
            ->assertSessionHasErrors('pre_template_id');

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.surveys.provision', $program), ['source' => 'template', 'pre_template_id' => $pre->id, 'satisfaction_template_id' => $sat->id])
            ->assertSessionHas('status');

        $this->assertSame(3, $program->surveys()->count());
        $post = $program->surveys()->where('type', 'post')->sole();
        $this->assertSame('ما مدى معرفتك؟', $post->questions()->value('prompt'));
        $this->assertTrue((bool) $program->surveys()->where('type', 'satisfaction')->value('is_anonymous'));
        $this->assertTrue($program->activities()->where('action', ProgramActivityAction::SurveysCreated)->exists());

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.programs.tab', [$program, 'surveys']))
            ->assertOk()
            ->assertSee('الاستبيان القبلي')
            ->assertSee('الاستبيان البعدي')
            ->assertSee('استبيان الرضا')
            ->assertSee($post->publicUrl());

        // A second create is refused.
        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.surveys.provision', $program), ['source' => 'blank'])
            ->assertSessionHasErrors('surveys');
    }

    public function test_editing_pre_questions_copies_them_to_post_and_saves_the_window(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        app(ProgramSurveyService::class)->provisionFromScratch($program);
        $pre = $program->surveys()->where('type', 'pre')->sole();

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.programs.tab', [$program, 'surveys']).'?edit='.$pre->id)
            ->assertOk()
            ->assertSee('تعديل الاستبيان القبلي')
            ->assertSee('إضافة سؤال');

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.surveys.update', [$program, $pre]), [
                'opens_at' => '2026-12-01T09:00',
                'closes_at' => '2026-12-05T23:00',
                'has_questions' => 1,
                'questions' => [
                    ['type' => 'scale', 'prompt' => 'معرفتك بإدارة المشاريع', 'scale_min_label' => 'ضعيفة', 'scale_max_label' => 'ممتازة', 'required' => '1'],
                    ['type' => 'single', 'prompt' => 'مجال عملك', 'options' => "تقني\nإداري", 'required' => '0'],
                ],
            ])
            ->assertRedirect(route('staff-ui.programs.tab', [$program, 'surveys']));

        $pre->refresh();
        $this->assertTrue($pre->opens_at->equalTo(Carbon::parse('2026-12-01 09:00', config('app.timezone'))));
        $questions = $pre->questions()->orderBy('position')->get();
        $this->assertSame(['معرفتك بإدارة المشاريع', 'مجال عملك'], $questions->pluck('prompt')->all());
        $this->assertSame(['تقني', 'إداري'], $questions[1]->options);
        $this->assertFalse($questions[1]->required);
        $this->assertSame('ممتازة', $questions[0]->scale_max_label);

        $post = $program->surveys()->where('type', 'post')->sole();
        $this->assertSame(['معرفتك بإدارة المشاريع', 'مجال عملك'], $post->questions()->orderBy('position')->pluck('prompt')->all());
        $this->assertTrue($program->activities()->where('action', ProgramActivityAction::SurveyUpdated)->exists());

        // A choice question with no options is refused and nothing changes.
        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.surveys.update', [$program, $pre]), [
                'has_questions' => 1,
                'questions' => [['type' => 'multiple', 'prompt' => 'بدون خيارات', 'options' => '']],
            ])
            ->assertSessionHasErrors('questions');
        $this->assertSame(2, $pre->questions()->count());
    }

    public function test_questions_lock_after_answers_but_dates_still_save(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        app(ProgramSurveyService::class)->provisionFromScratch($program);
        $pre = $program->surveys()->where('type', 'pre')->sole();
        $pre->questions()->create(['position' => 1, 'type' => SurveyQuestionType::Scale, 'prompt' => 'سؤال', 'required' => true]);
        $registration = $this->registration($program);
        DB::table('survey_responses')->insert(['id' => (string) Str::uuid(), 'program_survey_id' => $pre->id, 'registration_id' => $registration->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.programs.tab', [$program, 'surveys']).'?edit='.$pre->id)
            ->assertSee('الأسئلة مقفلة')
            ->assertDontSee('إضافة سؤال');

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.programs.surveys.update', [$program, $pre]), [
                'closes_at' => '2026-12-20T23:00',
                'has_questions' => 1,
                'questions' => [['type' => 'text', 'prompt' => 'تغيير']],
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame('سؤال', $pre->questions()->value('prompt'));
        $this->assertSame('2026-12-20', $pre->fresh()->closes_at->timezone(config('app.timezone'))->toDateString());

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.programs.tab', [$program, 'surveys']))
            ->assertSee('من 1 مقبول');
    }

    public function test_open_and_close_now_and_export(): void
    {
        $admin = $this->admin();
        $program = $this->program();
        app(ProgramSurveyService::class)->provisionFromScratch($program);
        $sat = $program->surveys()->where('type', SurveyType::Satisfaction->value)->sole();
        $sat->update(['opens_at' => now()->addMonth(), 'closes_at' => now()->addMonths(2)]);

        $this->actingAsOtpVerified($admin)->post(route('staff-ui.programs.surveys.window', [$program, $sat]), ['action' => 'open']);
        $this->assertTrue($sat->fresh()->isOpen());

        $this->actingAsOtpVerified($admin)->post(route('staff-ui.programs.surveys.window', [$program, $sat]), ['action' => 'close']);
        $this->assertFalse($sat->fresh()->isOpen());

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.programs.surveys.export', [$program, $sat]))
            ->assertOk()
            ->assertDownload();
    }

    public function test_viewers_see_the_tab_but_cannot_change_surveys(): void
    {
        $program = $this->program();
        app(ProgramSurveyService::class)->provisionFromScratch($program);
        $pre = $program->surveys()->where('type', 'pre')->sole();
        $viewer = $this->staff(['programs.view']);

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.programs.tab', [$program, 'surveys']))
            ->assertOk()
            ->assertSee('الاستبيان القبلي')
            ->assertDontSee('فتح الآن');

        $this->actingAsOtpVerified($viewer)
            ->post(route('staff-ui.programs.surveys.window', [$program, $pre]), ['action' => 'open'])
            ->assertForbidden();

        $other = $this->program(['slug' => 'other-'.uniqid()]);
        $this->actingAsOtpVerified($this->admin())
            ->post(route('staff-ui.programs.surveys.window', [$other, $pre]), ['action' => 'open'])
            ->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function program(array $overrides = []): TrainingProgram
    {
        return TrainingProgram::query()->create(array_merge([
            'title' => 'برنامج الاستبيانات',
            'slug' => 'srv-'.uniqid(),
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
