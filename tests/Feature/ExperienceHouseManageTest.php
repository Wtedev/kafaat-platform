<?php

namespace Tests\Feature;

use App\Mail\ExperienceHouseAccessMail;
use App\Mail\ExperienceHouseStatusMail;
use App\Models\ExperienceHouseManager;
use App\Models\ExperienceHouseRequest;
use App\Services\ExperienceHouses\ManagerAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ExperienceHouseManageTest extends TestCase
{
    use RefreshDatabase;

    private function lama(): ExperienceHouseManager
    {
        return ExperienceHouseManager::query()->where('email', 'lamaalmushyqih@gmail.com')->firstOrFail();
    }

    private function makeRequest(array $overrides = []): ExperienceHouseRequest
    {
        return ExperienceHouseRequest::query()->create(array_merge([
            'organization_name' => 'جمعية الأمل الأهلية',
            'license_number' => '1234',
            'region' => 'القصيم',
            'city' => 'بريدة',
            'contact_name' => 'سارة محمد',
            'contact_role' => 'المديرة التنفيذية',
            'contact_phone' => '0551234567',
            'contact_email' => 'sara@example.org',
            'departments' => ['strategic_planning', 'finance'],
            'support_types' => ['consultation'],
            'challenge' => 'لا تملك الجمعية خطة استراتيجية معتمدة.',
            'expected_outcome' => 'خطة معتمدة.',
            'priority' => 'urgent',
            'delivery_mode' => 'remote',
            'status' => 'new',
        ], $overrides));
    }

    /** يطلب رابط إنشاء/استعادة كلمة المرور ويستخرجه من البريد المرسل. */
    private function passwordLinkFor(string $email): string
    {
        Mail::fake();
        $this->post('/experience-houses/manage/password', ['email' => $email])->assertRedirect('/experience-houses/manage/password');

        $url = null;
        Mail::assertSent(ExperienceHouseAccessMail::class, function (ExperienceHouseAccessMail $mail) use (&$url, $email) {
            $url = $mail->url;

            return $mail->hasTo(mb_strtolower(trim($email))) && $mail->purpose === 'login';
        });

        return $url;
    }

    public function test_dashboard_requires_login(): void
    {
        $this->get('/experience-houses/manage')->assertRedirect('/experience-houses/manage/login');
        $this->get('/experience-houses/manage/login')->assertOk()->assertSee('كلمة المرور')->assertSee('أول مرة تدخل أو نسيت كلمة المرور؟');
    }

    public function test_seeded_manager_creates_password_then_logs_in_with_it(): void
    {
        $this->makeRequest();
        $url = $this->passwordLinkFor('LamaAlmushyqih@gmail.com ');

        // الرابط يعرض نموذج كلمة المرور ولا يُدخل مباشرة
        $this->get($url)->assertOk()->assertSee('فعّل حسابك');
        $this->get('/experience-houses/manage')->assertRedirect('/experience-houses/manage/login');

        $this->post($url, ['password' => 'secret123', 'password_confirmation' => 'nope'])->assertSessionHasErrors('password');
        $this->post($url, ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->post($url, ['password' => 'secret123', 'password_confirmation' => 'secret123'])->assertRedirect('/experience-houses/manage');

        $this->get('/experience-houses/manage')
            ->assertOk()
            ->assertSee('لمى')
            ->assertSee('جمعية الأمل الأهلية')
            ->assertSee('عاجل');
        $this->assertNotNull($this->lama()->fresh()->activated_at);

        // الرابط لمرة واحدة فقط
        $this->post('/experience-houses/manage/logout');
        $this->get($url)->assertRedirect('/experience-houses/manage/password')->assertSessionHas('link_error');
        $this->get('/experience-houses/manage')->assertRedirect('/experience-houses/manage/login');

        // الدخول بكلمة المرور
        $this->post('/experience-houses/manage/login', ['email' => 'lamaalmushyqih@gmail.com', 'password' => 'wrong-pass'])
            ->assertSessionHasErrors('email');
        $this->get('/experience-houses/manage')->assertRedirect('/experience-houses/manage/login');
        $this->post('/experience-houses/manage/login', ['email' => ' LamaAlmushyqih@gmail.com', 'password' => 'secret123'])
            ->assertRedirect('/experience-houses/manage');
        $this->get('/experience-houses/manage')->assertOk();
    }

    public function test_access_mail_is_activation_until_password_exists(): void
    {
        $lama = $this->lama();
        (new ExperienceHouseAccessMail($lama, 'https://x.test/a', 'login'))
            ->assertHasSubject('تفعيل حسابك في منصة طلبات الدعم - بيوت الخبرة')
            ->assertSeeInHtml('تفعيل الحساب');

        $lama->forceFill(['password' => 'secret123'])->save();
        (new ExperienceHouseAccessMail($lama->fresh(), 'https://x.test/a', 'login'))
            ->assertHasSubject('إعادة تعيين كلمة المرور - منصة طلبات الدعم - بيوت الخبرة');
        (new ExperienceHouseAccessMail($lama->fresh(), 'https://x.test/a', 'invite'))
            ->assertHasSubject('تفعيل حسابك في منصة طلبات الدعم - بيوت الخبرة');
    }

    public function test_manager_without_password_cannot_log_in(): void
    {
        $this->post('/experience-houses/manage/login', ['email' => 'lamaalmushyqih@gmail.com', 'password' => 'anything1'])
            ->assertSessionHasErrors('email');
        $this->get('/experience-houses/manage')->assertRedirect('/experience-houses/manage/login');
    }

    public function test_unknown_email_gets_same_message_and_no_mail(): void
    {
        Mail::fake();
        $this->post('/experience-houses/manage/password', ['email' => 'stranger@example.com'])
            ->assertRedirect('/experience-houses/manage/password')
            ->assertSessionHas('sent_to', 'stranger@example.com');
        Mail::assertNothingSent();
    }

    public function test_expired_link_is_rejected(): void
    {
        $url = $this->passwordLinkFor('lamaalmushyqih@gmail.com');
        $this->travel(ManagerAccess::LOGIN_MINUTES + 1)->minutes();

        $this->get($url)->assertRedirect('/experience-houses/manage/password')->assertSessionHas('link_error');
        $this->post($url, ['password' => 'secret123', 'password_confirmation' => 'secret123'])
            ->assertRedirect('/experience-houses/manage/password');
        $this->assertNull($this->lama()->fresh()->password);
    }

    public function test_manager_moves_request_through_steps_and_saves_note(): void
    {
        $req = $this->makeRequest();
        $this->withSession([ManagerAccess::SESSION_KEY => $this->lama()->id]);

        $this->get("/experience-houses/manage/requests/{$req->id}")
            ->assertOk()
            ->assertSee('بدء دراسة الطلب')
            ->assertSee('https://wa.me/966551234567', false);

        Mail::fake();
        $this->post("/experience-houses/manage/requests/{$req->id}/status", ['status' => 'in_review'])->assertRedirect();
        Mail::assertSent(ExperienceHouseStatusMail::class, fn ($m) => $m->hasTo($req->contact_email) && $m->experienceRequest->status === 'in_review');
        (new ExperienceHouseStatusMail($req->fresh()))->assertSeeInHtml('بدأنا دراسة طلبكم')->assertSeeInHtml('قيد الدراسة');
        $this->post("/experience-houses/manage/requests/{$req->id}/note", ['body' => 'تواصلنا مع سارة'])->assertRedirect();
        $this->post("/experience-houses/manage/requests/{$req->id}/note", ['body' => ''])->assertSessionHasErrors('body');

        $req->refresh();
        $this->assertSame('in_review', $req->status);
        $this->assertSame($this->lama()->id, $req->handled_by_id);
        $this->assertSame(['created', 'status', 'comment'], $req->events()->oldest('id')->pluck('type')->all());
        $statusEvent = $req->events()->where('type', 'status')->sole();
        $this->assertSame(['new', 'in_review', $this->lama()->id], [$statusEvent->from_status, $statusEvent->to_status, $statusEvent->manager_id]);

        $this->get("/experience-houses/manage/requests/{$req->id}")
            ->assertOk()
            ->assertSee('سجل الطلب')
            ->assertSee('وصل الطلب')
            ->assertSee('تواصلنا مع سارة')
            ->assertSee('0551234567');

        $this->get('/experience-houses/manage?tab=active')->assertOk()->assertSee('جمعية الأمل الأهلية');
    }

    public function test_manager_invites_teammate_who_activates_by_email(): void
    {
        Mail::fake();
        $this->withSession([ManagerAccess::SESSION_KEY => $this->lama()->id]);

        $this->post('/experience-houses/manage/team', ['name' => 'نورة', 'email' => 'Noura@Example.com'])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $member = ExperienceHouseManager::query()->where('email', 'noura@example.com')->firstOrFail();
        $this->assertNull($member->activated_at);

        $url = null;
        Mail::assertSent(ExperienceHouseAccessMail::class, function (ExperienceHouseAccessMail $mail) use (&$url) {
            $url = $mail->url;

            return $mail->hasTo('noura@example.com') && $mail->purpose === 'invite';
        });

        $this->post('/experience-houses/manage/logout');
        $this->get($url)->assertOk()->assertSee('فعّل حسابك');
        $this->post($url, ['password' => 'noura-pass1', 'password_confirmation' => 'noura-pass1'])->assertRedirect('/experience-houses/manage');
        $this->assertNotNull($member->fresh()->activated_at);
        $this->get('/experience-houses/manage/team')->assertOk()->assertSee('نورة')->assertSee('لمى المشيقح');
    }

    public function test_removed_member_loses_access(): void
    {
        $member = ExperienceHouseManager::query()->create(['name' => 'نورة', 'email' => 'noura@example.com', 'activated_at' => now()]);

        $this->withSession([ManagerAccess::SESSION_KEY => $this->lama()->id])
            ->post("/experience-houses/manage/team/{$member->id}/remove")
            ->assertRedirect();

        $this->assertFalse($member->fresh()->is_active);

        $this->withSession([ManagerAccess::SESSION_KEY => $member->id])
            ->get('/experience-houses/manage')
            ->assertRedirect('/experience-houses/manage/login');
    }
}
