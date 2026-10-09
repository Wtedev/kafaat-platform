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
use App\Notifications\ProgramRegistrationApproved;
use App\Services\ProgramRegistrationService;
use App\Support\DataForumAcceptance;
use App\Support\ProgramApprovalMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DataForumRegistrationApprovedTest extends TestCase
{
    use RefreshDatabase;

    private const MALE_URL = 'https://t.me/data-forum-male-test';

    private const FEMALE_URL = 'https://t.me/data-forum-female-test';

    public function test_data_forum_approval_sends_the_special_mail_and_inbox_notice(): void
    {
        Notification::fake();

        $approver = User::factory()->create();
        $beneficiary = $this->beneficiary(ProfileGender::Male);
        $program = $this->program(DataForumAcceptance::SLUG, 'ملتقى تحليل البيانات 2');
        $registration = $this->registration($program, $beneficiary);

        app(ProgramRegistrationService::class)->approve($registration, $approver);

        Notification::assertSentTo($beneficiary, ProgramRegistrationApproved::class);

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
        $maleMail = (new ProgramRegistrationApproved($this->registration($program, $male)))->toMail($male);
        $maleHtml = $maleMail->render();
        $this->assertSame(ProgramApprovalMail::FORUM_SUBJECT, $maleMail->subject);
        $this->assertStringContainsString(self::MALE_URL, $maleHtml);
        $this->assertStringNotContainsString(self::FEMALE_URL, $maleHtml);
        $this->assertStringContainsString(ProgramApprovalMail::BUTTON_LABEL, $maleHtml);
        $this->assertStringContainsString('قبولك النهائي في الملتقى', $maleHtml);
        $this->assertStringContainsString('<strong>رحلة</strong>', $maleHtml);
        $this->assertStringContainsString('dir="rtl"', $maleHtml);
        $this->assertStringContainsString('text-align:center', $maleHtml);
        $this->assertStringContainsString(DataForumAcceptance::logoUrl(), $maleHtml);
        $this->assertStringNotContainsString('127.0.0.1', $maleHtml);
        $this->assertStringNotContainsString('text-align:right', $maleHtml);
        $this->assertStringNotContainsString('text-align:left', $maleHtml);

        $female = $this->beneficiary(ProfileGender::Female);
        $femaleHtml = (new ProgramRegistrationApproved($this->registration($program, $female)))->toMail($female)->render();
        $this->assertStringContainsString(self::FEMALE_URL, $femaleHtml);
        $this->assertStringNotContainsString(self::MALE_URL, $femaleHtml);

        $unspecified = $this->beneficiary(null);
        $unspecifiedHtml = (new ProgramRegistrationApproved($this->registration($program, $unspecified)))->toMail($unspecified)->render();
        $this->assertStringContainsString(ProgramApprovalMail::PENDING_LINE, $unspecifiedHtml);
        $this->assertStringNotContainsString(ProgramApprovalMail::BUTTON_LABEL, $unspecifiedHtml);
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
        $attributes = [
            'title' => $title,
            'slug' => $slug,
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'learning_path_id' => null,
            'capacity' => 5000,
            'auto_accept_registrations' => false,
        ];

        if ($slug === DataForumAcceptance::SLUG) {
            $attributes['approval_message'] = ProgramApprovalMail::FORUM_BODY;
            $attributes['whatsapp_groups_enabled'] = true;
            $attributes['whatsapp_group_male'] = self::MALE_URL;
            $attributes['whatsapp_group_female'] = self::FEMALE_URL;
        }

        return TrainingProgram::query()->create($attributes);
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
