<?php

namespace Tests\Feature;

use App\Enums\IdentityType;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SurveyQuestionType;
use App\Enums\SurveyType;
use App\Exports\ProgramSurveyExport;
use App\Filament\Resources\TrainingProgramResource\Pages\ViewTrainingProgram;
use App\Filament\Resources\TrainingProgramResource\RelationManagers\ProgramSurveysRelationManager;
use App\Models\ProgramRegistration;
use App\Models\ProgramSurvey;
use App\Models\SurveyAnswer;
use App\Models\SurveyCompletion;
use App\Models\SurveyResponse;
use App\Models\SurveyTemplate;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Identity\IdentityNumberService;
use App\Services\Rbac\RbacCatalog;
use App\Services\Surveys\ProgramSurveyService;
use App\Services\Surveys\TurnstileVerifier;
use App\Support\Surveys\SurveyNationalIdHasher;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\GeneratesTestIdentityData;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class ProgramSurveysTest extends TestCase
{
    use GeneratesTestIdentityData;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
    }

    public function test_an_approved_beneficiary_answers_the_pre_survey_once(): void
    {
        [$program, $survey, $identity] = $this->openPreSurvey();
        $user = $this->beneficiary($identity, 'طيبة', 'سمير', 'عبدالمنعم', 'قاضي');
        $this->register($program, $user, RegistrationStatus::Approved);

        $this->post(route('public.surveys.identify', $survey->public_token), [
            'national_id' => $identity,
            'turnstile_token' => 'test-turnstile',
        ])->assertRedirect(route('public.surveys.show', $survey->public_token));

        $this->get(route('public.surveys.show', $survey->public_token))
            ->assertOk()
            ->assertSee('طيبة ق.')
            ->assertDontSee('سمير')
            ->assertDontSee('عبدالمنعم')
            ->assertDontSee('قاضي');

        $this->post(route('public.surveys.confirm', $survey->public_token), ['choice' => 'yes'])
            ->assertRedirect(route('public.surveys.show', $survey->public_token));

        $question = $survey->questions()->firstOrFail();
        $this->post(route('public.surveys.submit', $survey->public_token), [
            'answers' => [$question->id => 4],
        ])->assertOk()->assertSee(ProgramSurveyService::THANKS_MESSAGE);

        $this->assertDatabaseCount('survey_responses', 1);
        $response = SurveyResponse::query()->firstOrFail();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            (string) $response->id,
        );
        $this->assertNotContains(HasUuids::class, class_uses_recursive(SurveyResponse::class));
        $answer = SurveyAnswer::query()->firstOrFail();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            (string) $answer->id,
        );
        $this->assertNotNull(DB::table('survey_answers')->where('id', $answer->id)->value('created_at'));
        $this->get(route('public.surveys.show', $survey->public_token))
            ->assertSessionMissing('survey_registration.'.$survey->public_token);

        $this->post(route('public.surveys.identify', $survey->public_token), [
            'national_id' => $identity,
            'turnstile_token' => 'test-turnstile',
        ])->assertOk()->assertSee(ProgramSurveyService::ALREADY_MESSAGE);
        $this->assertDatabaseCount('survey_responses', 1);
    }

    public function test_an_identity_from_another_program_gets_the_same_message_as_an_unknown_number(): void
    {
        [$program, $survey, $identity] = $this->openPreSurvey();
        $other = $this->makeProgram('برنامج آخر');
        $user = $this->beneficiary($identity, 'هدى', 'علي', 'محمد', 'العتيبي');
        $this->register($other, $user, RegistrationStatus::Approved);
        $unknown = $this->generateValidIdentityForType(IdentityType::NationalId);

        $otherResponse = $this->post(route('public.surveys.identify', $survey->public_token), [
            'national_id' => $identity,
            'turnstile_token' => 'test-turnstile',
        ]);
        $unknownResponse = $this->post(route('public.surveys.identify', $survey->public_token), [
            'national_id' => $unknown,
            'turnstile_token' => 'test-turnstile',
        ]);

        $otherResponse->assertSessionHasErrors(['national_id' => ProgramSurveyService::NOT_FOUND_MESSAGE]);
        $unknownResponse->assertSessionHasErrors(['national_id' => ProgramSurveyService::NOT_FOUND_MESSAGE]);

        $stored = DB::table('survey_access_attempts')->pluck('national_id_hash');
        $this->assertCount(2, $stored);
        foreach ([$identity, $unknown] as $number) {
            $this->assertFalse($stored->contains($number));
            $this->assertNotContains(
                IdentityNumberService::generateLookupHash(IdentityNumberService::normalize($number)),
                $stored->all(),
            );
            $this->assertContains(
                SurveyNationalIdHasher::hash(IdentityNumberService::normalize($number)),
                $stored->all(),
            );
        }

        $this->assertStringNotContainsString($identity, json_encode(DB::table('survey_access_attempts')->get(), JSON_UNESCAPED_UNICODE));
    }

    public function test_a_closed_survey_accepts_no_input(): void
    {
        [$program, $survey] = $this->openPreSurvey();
        unset($program);
        $survey->update([
            'opens_at' => now()->addDay(),
            'closes_at' => now()->addDays(2),
        ]);

        $this->get(route('public.surveys.show', $survey->public_token))
            ->assertOk()
            ->assertSee(ProgramSurveyService::UNAVAILABLE_MESSAGE)
            ->assertDontSee('name="national_id"', false);

        $this->post(route('public.surveys.identify', $survey->public_token), [
            'national_id' => '1000000000',
            'turnstile_token' => 'test-turnstile',
        ])->assertOk()->assertSee(ProgramSurveyService::UNAVAILABLE_MESSAGE);

        $this->assertDatabaseCount('survey_access_attempts', 0);
        $this->assertDatabaseCount('survey_responses', 0);
    }

    public function test_the_identity_step_is_rate_limited(): void
    {
        [, $survey] = $this->openPreSurvey();
        $this->withServerVariables(['REMOTE_ADDR' => '10.8.8.8']);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->post(route('public.surveys.identify', $survey->public_token), [
                'national_id' => '200000000'.$attempt,
                'turnstile_token' => 'test-turnstile',
            ])->assertRedirect();
        }

        $this->from(route('public.surveys.show', $survey->public_token))
            ->post(route('public.surveys.identify', $survey->public_token), [
                'national_id' => '2000000009',
                'turnstile_token' => 'test-turnstile',
            ])
            ->assertSessionHasErrors(['national_id' => 'تجاوزت عدد المحاولات المسموح بها. حاول لاحقًا.']);

        $this->assertDatabaseCount('survey_access_attempts', 10);
    }

    public function test_missing_turnstile_keys_reject_the_identity_step_outside_testing(): void
    {
        $this->app['env'] = 'production';
        config([
            'services.turnstile.fake' => true,
            'services.turnstile.site_key' => null,
            'services.turnstile.secret_key' => null,
        ]);
        Http::fake();

        $this->assertFalse(app(TurnstileVerifier::class)->passes(TurnstileVerifier::TEST_TOKEN, '127.0.0.1'));
        Http::assertNothingSent();
    }

    public function test_turnstile_fake_is_ignored_outside_testing_even_when_enabled(): void
    {
        $this->app['env'] = 'production';
        config([
            'services.turnstile.fake' => true,
            'services.turnstile.site_key' => 'site-key',
            'services.turnstile.secret_key' => 'secret-key',
        ]);
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => false], 200),
        ]);

        $this->assertFalse(app(TurnstileVerifier::class)->passes(TurnstileVerifier::TEST_TOKEN, '127.0.0.1'));
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
                && $request['response'] === TurnstileVerifier::TEST_TOKEN;
        });
    }

    public function test_a_satisfaction_response_cannot_be_tied_to_the_registration(): void
    {
        [$program, , $identity, $satisfaction] = $this->openPreSurvey(withSatisfaction: true);
        $user = $this->beneficiary($identity, 'لمى', 'سعد', 'علي', 'المشيقح');
        $registration = $this->register($program, $user, RegistrationStatus::Approved);
        $satisfaction->update(['opens_at' => now()->subHour(), 'closes_at' => now()->addHour()]);

        $this->answer($satisfaction, $identity, [$satisfaction->questions()->firstOrFail()->id => 'مفيد']);

        $response = SurveyResponse::query()->where('program_survey_id', $satisfaction->id)->firstOrFail();
        $this->assertNull($response->registration_id);
        $this->assertNull(DB::table('survey_responses')->where('id', $response->id)->value('created_at'));
        $this->assertFalse(Schema::hasColumn('survey_completions', 'id'));
        $this->assertFalse(Schema::hasColumn('survey_completions', 'created_at'));
        $this->assertFalse(Schema::hasColumn('survey_completions', 'updated_at'));
        $completion = DB::table('survey_completions')->where('program_survey_id', $satisfaction->id)->first();
        $this->assertNotNull($completion);
        $this->assertObjectNotHasProperty('id', $completion);
        $answer = SurveyAnswer::query()->where('survey_response_id', $response->id)->firstOrFail();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            (string) $answer->id,
        );
        $this->assertNotContains(HasUuids::class, class_uses_recursive(SurveyAnswer::class));
        $this->assertNull(DB::table('survey_answers')->where('id', $answer->id)->value('created_at'));
        $this->assertNull(DB::table('survey_answers')->where('id', $answer->id)->value('updated_at'));
        $this->assertDatabaseHas('survey_completions', [
            'program_survey_id' => $satisfaction->id,
            'registration_id' => $registration->id,
        ]);
        $this->get(route('public.surveys.show', $satisfaction->public_token))
            ->assertSessionMissing('survey_registration.'.$satisfaction->public_token);
    }

    public function test_satisfaction_export_order_does_not_follow_completion_order(): void
    {
        $program = $this->makeProgram('برنامج الرضا');
        $survey = ProgramSurvey::query()->create([
            'training_program_id' => $program->id,
            'type' => SurveyType::Satisfaction,
            'is_anonymous' => true,
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addHour(),
        ]);
        $question = $survey->questions()->create([
            'position' => 1,
            'type' => SurveyQuestionType::Text,
            'prompt' => 'ملاحظة',
            'required' => true,
        ]);

        $answers = ['ج', 'أ', 'ب'];
        $ids = [
            '11111111-1111-4111-8111-111111111111',
            '22222222-2222-4222-8222-222222222222',
            '33333333-3333-4333-8333-333333333333',
        ];
        $answerIds = [
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
        ];
        foreach ($answers as $index => $answer) {
            $user = $this->beneficiary($this->generateValidIdentityForType(IdentityType::NationalId), 'مستفيد', 'أ', 'ب', 'ج'.$index);
            $registration = $this->register($program, $user, RegistrationStatus::Approved);
            SurveyCompletion::query()->create([
                'program_survey_id' => $survey->id,
                'registration_id' => $registration->id,
            ]);
            $response = new SurveyResponse([
                'program_survey_id' => $survey->id,
                'registration_id' => null,
            ]);
            $response->id = $ids[$index];
            $response->timestamps = false;
            $response->save();
            $stored = new SurveyAnswer([
                'survey_response_id' => $response->id,
                'program_survey_question_id' => $question->id,
                'value' => ['answer' => $answer],
            ]);
            $stored->id = $answerIds[$index];
            $stored->save();
        }

        $this->assertFalse(Schema::hasColumn('survey_completions', 'id'));
        $this->assertNotSame(
            $ids,
            SurveyAnswer::query()->orderBy('id')->pluck('survey_response_id')->all(),
        );
        $this->assertNull(DB::table('survey_answers')->orderBy('id')->value('created_at'));

        $exported = (new ProgramSurveyExport($survey->fresh()))->collection()
            ->map(fn (array $row): string => (string) $row[0])
            ->all();

        $this->assertSame(['أ', 'ب', 'ج'], $exported);
        $this->assertNotSame(['ج', 'أ', 'ب'], $exported);
        $this->assertNotContains('رقم التسجيل', (new ProgramSurveyExport($survey))->headings());
    }

    public function test_the_post_survey_copies_the_pre_questions_and_later_template_edits_do_not_change_them(): void
    {
        [$program, $pre, , , $preTemplate] = $this->openPreSurvey(withSatisfaction: true);
        $post = $program->surveys()->where('type', SurveyType::Post)->firstOrFail();

        $this->assertSame(
            $pre->questions()->pluck('prompt')->all(),
            $post->questions()->pluck('prompt')->all(),
        );

        $preTemplate->questions()->update(['prompt' => 'سؤال القالب بعد التعديل']);
        $this->assertSame('ما مدى جاهزيتك؟', $pre->questions()->firstOrFail()->prompt);
        $this->assertSame('ما مدى جاهزيتك؟', $post->questions()->firstOrFail()->prompt);

        try {
            app(ProgramSurveyService::class)->replaceQuestions($post, [[
                'type' => SurveyQuestionType::Text->value,
                'prompt' => 'سؤال بعدي مستقل',
                'required' => true,
            ]]);
            $this->fail('أسئلة البعدي قُبل تعديلها.');
        } catch (ValidationException $exception) {
            $this->assertSame(ProgramSurveyService::POST_FOLLOWS_PRE_MESSAGE, $exception->errors()['questions'][0]);
        }
    }

    public function test_a_program_page_can_create_surveys_from_scratch_and_edit_their_questions(): void
    {
        $program = $this->makeProgram('برنامج من الصفر');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($this->admin())
            ->test(ProgramSurveysRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->mountAction(TestAction::make('provision')->table())
            ->setActionData(['source' => 'blank'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(3, $program->surveys()->count());
        $this->assertSame(0, $program->surveys()->withCount('questions')->get()->sum('questions_count'));

        $pre = $program->surveys()->where('type', SurveyType::Pre)->firstOrFail();
        $service = app(ProgramSurveyService::class);
        $service->replaceQuestions($pre, [
            [
                'type' => SurveyQuestionType::Scale->value,
                'prompt' => 'الأول',
                'required' => true,
                'scale_min_label' => 'أبدًا',
                'scale_max_label' => 'دائمًا',
            ],
            [
                'type' => SurveyQuestionType::Text->value,
                'prompt' => 'الثاني',
                'required' => false,
            ],
        ]);

        $post = $program->surveys()->where('type', SurveyType::Post)->firstOrFail();
        $this->assertSame(['الأول', 'الثاني'], $pre->questions()->orderBy('position')->pluck('prompt')->all());
        $this->assertSame(['الأول', 'الثاني'], $post->questions()->orderBy('position')->pluck('prompt')->all());

        $service->replaceQuestions($pre, [
            [
                'type' => SurveyQuestionType::Text->value,
                'prompt' => 'الثاني',
                'required' => false,
            ],
            [
                'type' => SurveyQuestionType::Scale->value,
                'prompt' => 'الأول',
                'required' => true,
                'scale_min_label' => 'أبدًا',
                'scale_max_label' => 'دائمًا',
            ],
        ]);
        $this->assertSame(['الثاني', 'الأول'], $pre->questions()->orderBy('position')->pluck('prompt')->all());
        $this->assertSame('أبدًا', $post->fresh()->questions()->orderBy('position')->skip(1)->firstOrFail()->scale_min_label);

        $service->replaceQuestions($pre, [[
            'type' => SurveyQuestionType::Scale->value,
            'prompt' => 'الأول',
            'required' => true,
            'scale_min_label' => 'أبدًا',
            'scale_max_label' => 'دائمًا',
        ]]);
        $this->assertSame(['الأول'], $pre->questions()->orderBy('position')->pluck('prompt')->all());
        $this->assertSame(['الأول'], $post->questions()->orderBy('position')->pluck('prompt')->all());

        $this->withSession(['survey_registration.'.$pre->public_token => 1])
            ->get(route('public.surveys.show', $pre->public_token))
            ->assertOk()
            ->assertSee('أبدًا')
            ->assertSee('دائمًا');
    }

    public function test_editing_questions_copied_from_a_template_does_not_change_the_template(): void
    {
        [$program, $pre, , , $preTemplate] = $this->openPreSurvey(withSatisfaction: true);

        app(ProgramSurveyService::class)->replaceQuestions($pre, [[
            'type' => SurveyQuestionType::Scale->value,
            'prompt' => 'سؤال البرنامج بعد التعديل',
            'required' => true,
            'scale_min_label' => 'أبدًا',
            'scale_max_label' => 'دائمًا',
        ]]);

        $templateQuestion = $preTemplate->questions()->firstOrFail();
        $this->assertSame('ما مدى جاهزيتك؟', $templateQuestion->prompt);
        $this->assertNull($templateQuestion->scale_min_label);
        $this->assertNull($templateQuestion->scale_max_label);
        $this->assertSame(1, $preTemplate->questions()->count());

        $this->assertSame('سؤال البرنامج بعد التعديل', $pre->questions()->firstOrFail()->prompt);
        $this->assertSame('أبدًا', $pre->questions()->firstOrFail()->scale_min_label);
        $post = $program->surveys()->where('type', SurveyType::Post)->firstOrFail();
        $this->assertSame('سؤال البرنامج بعد التعديل', $post->questions()->firstOrFail()->prompt);
        $this->assertSame('دائمًا', $post->questions()->firstOrFail()->scale_max_label);
    }

    public function test_pre_and_post_questions_lock_after_the_first_response(): void
    {
        [$program, $pre, $identity] = $this->openPreSurvey();
        $user = $this->beneficiary($identity, 'نورة', 'فهد', 'سعد', 'القحطاني');
        $this->register($program, $user, RegistrationStatus::Approved);
        $this->answer($pre, $identity, [$pre->questions()->firstOrFail()->id => 3]);

        try {
            app(ProgramSurveyService::class)->replaceQuestions($pre, [[
                'type' => SurveyQuestionType::Text->value,
                'prompt' => 'سؤال جديد',
                'required' => true,
            ]]);
            $this->fail('أسئلة القبلي قُبل تعديلها بعد الرد.');
        } catch (ValidationException $exception) {
            $this->assertSame(ProgramSurveyService::QUESTIONS_LOCKED_MESSAGE, $exception->errors()['questions'][0]);
        }

        $post = $program->surveys()->where('type', SurveyType::Post)->firstOrFail();
        try {
            app(ProgramSurveyService::class)->replaceQuestions($post, [[
                'type' => SurveyQuestionType::Text->value,
                'prompt' => 'سؤال بعدي',
                'required' => true,
            ]]);
            $this->fail('أسئلة البعدي قُبل تعديلها بعد الرد.');
        } catch (ValidationException $exception) {
            $this->assertSame(ProgramSurveyService::POST_FOLLOWS_PRE_MESSAGE, $exception->errors()['questions'][0]);
        }
        $this->assertTrue(app(ProgramSurveyService::class)->questionsLocked($post));

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::factory()->create([
            'role_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(RbacCatalog::ROLE_ADMIN);

        $component = Livewire::actingAs($admin)
            ->test(ProgramSurveysRelationManager::class, [
                'ownerRecord' => $program,
                'pageClass' => ViewTrainingProgram::class,
            ])
            ->mountAction(TestAction::make('edit')->table($pre));

        $this->assertSame(
            ProgramSurveyService::QUESTIONS_LOCKED_MESSAGE,
            $component->instance()->getMountedAction()->getModalDescription(),
        );
    }

    /**
     * @return array{0: TrainingProgram, 1: ProgramSurvey, 2: string, 3?: ProgramSurvey, 4?: SurveyTemplate}
     */
    private function openPreSurvey(bool $withSatisfaction = false): array
    {
        $program = $this->makeProgram('برنامج الاستبيان');
        $preTemplate = SurveyTemplate::query()->create(['title' => 'قالب قبلي']);
        $preTemplate->questions()->create([
            'position' => 1,
            'type' => SurveyQuestionType::Scale,
            'prompt' => 'ما مدى جاهزيتك؟',
            'required' => true,
        ]);
        $satisfactionTemplate = SurveyTemplate::query()->create(['title' => 'قالب رضا']);
        $satisfactionTemplate->questions()->create([
            'position' => 1,
            'type' => SurveyQuestionType::Text,
            'prompt' => 'ما رأيك؟',
            'required' => true,
        ]);
        app(ProgramSurveyService::class)->provision($program, $preTemplate, $satisfactionTemplate);
        $pre = $program->surveys()->where('type', SurveyType::Pre)->firstOrFail();
        $identity = $this->generateValidIdentityForType(IdentityType::NationalId);

        if (! $withSatisfaction) {
            return [$program, $pre, $identity];
        }

        return [
            $program,
            $pre,
            $identity,
            $program->surveys()->where('type', SurveyType::Satisfaction)->firstOrFail(),
            $preTemplate,
        ];
    }

    private function admin(): User
    {
        $admin = User::factory()->create([
            'role_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(RbacCatalog::ROLE_ADMIN);

        return $admin;
    }

    private function makeProgram(string $title): TrainingProgram
    {
        return TrainingProgram::query()->create([
            'title' => $title,
            'slug' => 'survey-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'registration_start' => now()->subDay()->toDateString(),
            'registration_end' => now()->addMonth()->toDateString(),
        ]);
    }

    private function beneficiary(string $identity, string $first, string $father, string $grandfather, string $family): User
    {
        $user = User::factory()->create([
            'name' => $first.' '.$father.' '.$grandfather.' '.$family,
            'first_name' => $first,
            'father_name' => $father,
            'grandfather_name' => $grandfather,
            'family_name' => $family,
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->forceFill(IdentityNumberService::prepareStoragePayload($identity, IdentityType::NationalId))->save();
        $user->assignRole('beneficiary');

        return $user->fresh();
    }

    private function register(TrainingProgram $program, User $user, RegistrationStatus $status): ProgramRegistration
    {
        return ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => $status,
        ]);
    }

    /**
     * @param  array<int, mixed>  $answers
     */
    private function answer(ProgramSurvey $survey, string $identity, array $answers): void
    {
        $this->post(route('public.surveys.identify', $survey->public_token), [
            'national_id' => $identity,
            'turnstile_token' => 'test-turnstile',
        ])->assertRedirect();
        $this->post(route('public.surveys.confirm', $survey->public_token), ['choice' => 'yes'])->assertRedirect();
        $this->post(route('public.surveys.submit', $survey->public_token), [
            'answers' => $answers,
        ])->assertOk();
    }
}
