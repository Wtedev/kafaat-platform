<?php

namespace Tests\Feature\Notifications;

use App\Enums\ProfileGender;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Models\InboxNotification;
use App\Models\Profile;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Notifications\DataForumRegistrationApproved;
use App\Notifications\ProgramRegistrationApproved;
use App\Services\ProgramRegistrationService;
use App\Support\DataForumAcceptance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DataForumRegistrationApprovedTest extends TestCase
{
    use RefreshDatabase;

    private const MALE_URL = 'https://t.me/data-forum-male-test';

    private const FEMALE_URL = 'https://t.me/data-forum-female-test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'data_forum.telegram_male' => self::MALE_URL,
            'data_forum.telegram_female' => self::FEMALE_URL,
        ]);
    }

    public function test_data_forum_approval_sends_the_special_mail_and_inbox_notice(): void
    {
        Notification::fake();

        $approver = User::factory()->create();
        $beneficiary = $this->beneficiary(ProfileGender::Male);
        $program = $this->program(DataForumAcceptance::SLUG, 'ملتقى تحليل البيانات 2');
        $registration = $this->registration($program, $beneficiary);

        app(ProgramRegistrationService::class)->approve($registration, $approver);

        Notification::assertSentTo($beneficiary, DataForumRegistrationApproved::class);
        Notification::assertNotSentTo($beneficiary, ProgramRegistrationApproved::class);

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $beneficiary->id,
            'message' => DataForumAcceptance::INBOX_MESSAGE,
        ]);
        $this->assertSame(
            0,
            InboxNotification::query()
                ->where('user_id', $beneficiary->id)
                ->where('message', 'like', '%تم قبول طلبك في البرنامج التدريبي%')
                ->count(),
        );
    }

    public function test_other_programs_keep_the_default_approval_mail(): void
    {
        Notification::fake();

        $approver = User::factory()->create();
        $beneficiary = $this->beneficiary(ProfileGender::Female);
        $program = $this->program('other-program-'.uniqid(), 'برنامج آخر');
        $registration = $this->registration($program, $beneficiary);

        app(ProgramRegistrationService::class)->approve($registration, $approver);

        Notification::assertSentTo($beneficiary, ProgramRegistrationApproved::class);
        Notification::assertNotSentTo($beneficiary, DataForumRegistrationApproved::class);

        $mail = (new ProgramRegistrationApproved($registration->fresh()))->toMail($beneficiary);
        $this->assertSame('تم قبول تسجيلك — برنامج آخر', $mail->subject);
        $this->assertStringNotContainsString(DataForumAcceptance::SUBJECT, $mail->render());

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $beneficiary->id,
            'message' => 'تم قبول طلبك في البرنامج التدريبي «برنامج آخر».',
        ]);
    }

    public function test_telegram_button_follows_gender_and_unspecified_has_no_button(): void
    {
        $program = $this->program(DataForumAcceptance::SLUG, 'ملتقى تحليل البيانات 2');

        $male = $this->beneficiary(ProfileGender::Male);
        $maleMail = (new DataForumRegistrationApproved($this->registration($program, $male)))->toMail($male);
        $maleHtml = $maleMail->render();
        $this->assertSame(DataForumAcceptance::SUBJECT, $maleMail->subject);
        $this->assertStringContainsString(self::MALE_URL, $maleHtml);
        $this->assertStringNotContainsString(self::FEMALE_URL, $maleHtml);
        $this->assertStringContainsString('الانضمام إلى مجموعة تيليجرام', $maleHtml);
        $this->assertStringContainsString('قبولك النهائي في الملتقى', $maleHtml);
        $this->assertStringContainsString('dir="rtl"', $maleHtml);

        $female = $this->beneficiary(ProfileGender::Female);
        $femaleHtml = (new DataForumRegistrationApproved($this->registration($program, $female)))->toMail($female)->render();
        $this->assertStringContainsString(self::FEMALE_URL, $femaleHtml);
        $this->assertStringNotContainsString(self::MALE_URL, $femaleHtml);

        $unspecified = $this->beneficiary(null);
        $unspecifiedHtml = (new DataForumRegistrationApproved($this->registration($program, $unspecified)))->toMail($unspecified)->render();
        $this->assertStringContainsString(DataForumAcceptance::TELEGRAM_PENDING_LINE, $unspecifiedHtml);
        $this->assertStringNotContainsString('الانضمام إلى مجموعة تيليجرام', $unspecifiedHtml);
        $this->assertStringNotContainsString(self::MALE_URL, $unspecifiedHtml);
        $this->assertStringNotContainsString(self::FEMALE_URL, $unspecifiedHtml);
    }

    private function beneficiary(?ProfileGender $gender): User
    {
        $user = User::factory()->create([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Profile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'gender' => $gender,
                'membership_type' => 'beneficiary',
            ],
        );

        return $user->fresh('profile');
    }

    private function program(string $slug, string $title): TrainingProgram
    {
        return TrainingProgram::query()->create([
            'title' => $title,
            'slug' => $slug,
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'learning_path_id' => null,
            'capacity' => 5000,
            'auto_accept_registrations' => false,
        ]);
    }

    private function registration(TrainingProgram $program, User $user): ProgramRegistration
    {
        return ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => RegistrationStatus::Pending,
        ]);
    }
}
