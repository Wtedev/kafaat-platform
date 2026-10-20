<?php

namespace Tests\Feature;

use App\Enums\ProgramStatus;
use App\Models\Profile;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Support\ProgramRegistrationQuestions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class TatawaaWaeiRegistrationQuestionsTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seedRbacRoles();
    }

    public function test_program_page_shows_extra_questions_one_step_at_a_time(): void
    {
        $user = $this->makePortalUser();
        $program = $this->makeProgram();

        $response = $this->actingAsOtpVerified($user)
            ->get(route('public.programs.show', $program));

        $response->assertOk();
        $response->assertSee('سجل الآن', false);
        $response->assertSee('id="program-register-dialog"', false);
        $response->assertSee('6 أسئلة', false);
        $response->assertSee('سؤال 1 من 6', false);
        $response->assertSee('أقر بالالتزام التام حتى نهاية الحملة ديسمبر 2026م', false);
        $response->assertDontSee('الاسم الثلاثي ثم السجل المدني', false);
        $response->assertSee('رابط حسابك الحالي على منصة LinkedIn (إن وجد)', false);
        $response->assertSee('التخصص الأكاديمي أو المهني الحالي', false);
        $response->assertSee('data-registration-step="1"', false);
        $response->assertSee('data-registration-next', false);
    }

    public function test_registration_stores_the_extra_answers(): void
    {
        $user = $this->makePortalUser();
        $program = $this->makeProgram();

        $response = $this->actingAsOtpVerified($user)
            ->post(route('public.programs.register', $program), [
                'attendance_acknowledgement' => '1',
                'answers' => [
                    'linkedin_url' => 'https://linkedin.com/in/example',
                    'major' => 'علوم حاسب',
                    'content_experience' => 'لا توجد خبرة',
                    'ai_knowledge' => 'أستخدمها بشكل محدود',
                    'reason_to_join' => 'أريد أن أصنع محتوى يخدم المجتمع.',
                    'commitment_ready' => 'نعم، ملتزم تماماً',
                ],
            ]);

        $registration = ProgramRegistration::query()
            ->where('training_program_id', $program->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($registration);
        $response->assertRedirect(route('public.programs.registered', [
            'trainingProgram' => $program->slug,
            'registration' => $registration->id,
        ]));
        $this->assertSame('علوم حاسب', $registration->registration_answers['major']);
        $this->assertArrayNotHasKey('acknowledgment', $registration->registration_answers);
    }

    public function test_registration_rejects_a_missing_required_answer(): void
    {
        $user = $this->makePortalUser();
        $program = $this->makeProgram();

        $this->actingAsOtpVerified($user)
            ->from(route('public.programs.show', $program))
            ->post(route('public.programs.register', $program), [
                'attendance_acknowledgement' => '1',
                'answers' => [
                    'major' => 'علوم حاسب',
                ],
            ])
            ->assertSessionHasErrors('answers.content_experience');

        $this->assertDatabaseMissing('program_registrations', [
            'training_program_id' => $program->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_linkedin_can_be_left_empty(): void
    {
        $user = $this->makePortalUser();
        $program = $this->makeProgram();

        $this->actingAsOtpVerified($user)
            ->post(route('public.programs.register', $program), [
                'attendance_acknowledgement' => '1',
                'answers' => [
                    'linkedin_url' => '',
                    'major' => 'علوم حاسب',
                    'content_experience' => 'لا توجد خبرة',
                    'ai_knowledge' => 'لم يسبق لي استخدامها',
                    'reason_to_join' => 'أرغب في التعلم.',
                    'commitment_ready' => 'نعم، حسب تفرغي',
                ],
            ])
            ->assertRedirect();

        $answers = ProgramRegistration::query()->firstOrFail()->registration_answers;
        $this->assertArrayNotHasKey('linkedin_url', $answers);
    }

    private function makePortalUser(): User
    {
        $user = User::factory()->create([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('beneficiary');
        Profile::query()->create(['user_id' => $user->id]);

        return $user;
    }

    private function makeProgram(): TrainingProgram
    {
        return TrainingProgram::query()->create([
            'title' => 'تطوع واعي',
            'slug' => 'tatawaa-waei-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'registration_questions' => ProgramRegistrationQuestions::forTatawaaWaei(),
        ]);
    }
}
