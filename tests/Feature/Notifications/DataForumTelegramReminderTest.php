<?php

namespace Tests\Feature\Notifications;

use App\Enums\ProfileGender;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Jobs\SendDataForumTelegramReminderJob;
use App\Models\EmailLog;
use App\Models\Profile;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Notifications\DataForumTelegramReminder as ReminderMail;
use App\Support\DataForumAcceptance;
use App\Support\DataForumTelegramReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DataForumTelegramReminderTest extends TestCase
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

    public function test_reminder_uses_acceptance_layout_and_gender_links(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-08 19:00:00', 'Asia/Riyadh'));

        $male = $this->beneficiary(ProfileGender::Male);
        $maleHtml = (new ReminderMail(DataForumTelegramReminder::whenWord()))->toMail($male)->render();
        $this->assertSame(
            DataForumTelegramReminder::SUBJECT,
            (new ReminderMail(DataForumTelegramReminder::WHEN_LATER))->toMail($male)->subject,
        );
        $this->assertStringContainsString('مرحباً،', $maleHtml);
        $this->assertStringContainsString('ينطلق بعد غدٍ السبت 10 أكتوبر', $maleHtml);
        $this->assertStringContainsString(self::MALE_URL, $maleHtml);
        $this->assertStringNotContainsString(self::FEMALE_URL, $maleHtml);
        $this->assertStringContainsString('الانضمام إلى مجموعة تيليجرام', $maleHtml);
        $this->assertStringContainsString('dir="rtl"', $maleHtml);
        $this->assertStringContainsString('text-align:center', $maleHtml);
        $this->assertStringContainsString(DataForumAcceptance::logoUrl(), $maleHtml);
        $this->assertStringNotContainsString('127.0.0.1', $maleHtml);
        $this->assertStringNotContainsString('text-align:right', $maleHtml);
        $this->assertStringNotContainsString('text-align:left', $maleHtml);
        $this->assertStringContainsString('إن كنت انضممت بالفعل', $maleHtml);

        $femaleHtml = (new ReminderMail(DataForumTelegramReminder::WHEN_LATER))
            ->toMail($this->beneficiary(ProfileGender::Female))
            ->render();
        $this->assertStringContainsString(self::FEMALE_URL, $femaleHtml);
        $this->assertStringNotContainsString(self::MALE_URL, $femaleHtml);

        $unspecifiedHtml = (new ReminderMail(DataForumTelegramReminder::WHEN_LATER))
            ->toMail($this->beneficiary(null))
            ->render();
        $this->assertStringContainsString(DataForumAcceptance::TELEGRAM_PENDING_LINE, $unspecifiedHtml);
        $this->assertStringNotContainsString('الانضمام إلى مجموعة تيليجرام', $unspecifiedHtml);
        $this->assertStringNotContainsString(self::MALE_URL, $unspecifiedHtml);
    }

    public function test_friday_send_says_tomorrow(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-09 09:00:00', 'Asia/Riyadh'));

        $this->assertSame(DataForumTelegramReminder::WHEN_FRIDAY, DataForumTelegramReminder::whenWord());

        $html = (new ReminderMail(DataForumTelegramReminder::whenWord()))
            ->toMail($this->beneficiary(ProfileGender::Male))
            ->render();

        $this->assertStringContainsString('ينطلق غداً السبت 10 أكتوبر', $html);
        $this->assertStringNotContainsString('بعد غدٍ', $html);
    }

    public function test_job_sends_once_and_cohort_excludes_later_approvals(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-08 19:00:00', 'Asia/Riyadh'));

        $program = $this->program();
        $first = $this->registration($program, $this->beneficiary(ProfileGender::Male), '2026-10-08 13:10:55');
        $later = $this->registration($program, $this->beneficiary(ProfileGender::Female), '2026-10-08 13:11:00');
        $otherProgram = TrainingProgram::query()->create([
            'title' => 'برنامج آخر',
            'slug' => 'other-program-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'capacity' => 50,
            'auto_accept_registrations' => false,
        ]);
        $other = $this->registration($otherProgram, $this->beneficiary(ProfileGender::Male), '2026-10-08 12:00:00');

        $this->assertSame(1, DataForumTelegramReminder::cohortQuery()->count());

        (new SendDataForumTelegramReminderJob($first->id))->handle();
        (new SendDataForumTelegramReminderJob($later->id))->handle();
        (new SendDataForumTelegramReminderJob($other->id))->handle();
        (new SendDataForumTelegramReminderJob($first->id))->handle();

        $this->assertSame(1, EmailLog::query()->where('template_key', DataForumTelegramReminder::TEMPLATE_KEY)->where('status', 'sent')->count());
        $this->assertSame(DataForumTelegramReminder::SUBJECT, EmailLog::query()->first()->subject);

        Artisan::call('data-forum:send-telegram-reminders', ['--dry-run' => true]);
        $this->assertSame('cohort=1 pending=0 already_sent=1', trim(Artisan::output()));
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

    private function program(): TrainingProgram
    {
        return TrainingProgram::query()->create([
            'title' => 'ملتقى تحليل البيانات 2',
            'slug' => DataForumAcceptance::SLUG,
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'capacity' => 2000,
            'auto_accept_registrations' => false,
        ]);
    }

    private function registration(TrainingProgram $program, User $user, ?string $approvedAt = null): ProgramRegistration
    {
        return ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => RegistrationStatus::Approved,
            'approved_at' => $approvedAt ?? '2026-10-08 13:00:00',
        ]);
    }
}
