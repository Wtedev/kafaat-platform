<?php

namespace Tests\Feature\Support;

use App\Enums\SupportMessageSenderType;
use App\Enums\SupportTicketStatus;
use App\Jobs\SendSupportReplyEmailJob;
use App\Mail\SupportReplyMail;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\Support\SupportTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class SupportReplyEmailTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        SupportTicketService::setNextTicketNumberResolver(null);
    }

    protected function tearDown(): void
    {
        SupportTicketService::setNextTicketNumberResolver(null);
        parent::tearDown();
    }

    private function beneficiary(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'role_type' => 'beneficiary',
            'name' => 'مستفيد الاختبار',
            'email' => 'beneficiary-reply-'.uniqid().'@example.com',
            'email_verified_at' => now(),
            'is_active' => true,
            'notify_email' => false,
            'notification_settings' => null,
            'notification_prefs_set_at' => now(),
        ], $overrides));
        $user->assignRole('beneficiary');

        return $user;
    }

    private function admin(): User
    {
        $user = User::factory()->create([
            'role_type' => 'admin',
            'email' => 'admin-reply-'.uniqid().'@example.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $user->assignRole('admin');

        return $user;
    }

    private function createTicket(User $user, array $data = []): SupportTicket
    {
        Notification::fake();

        return app(SupportTicketService::class)->createAndNotify(array_merge([
            'subject' => 'موضوع التذكرة للاختبار',
            'category' => 'general',
            'body' => 'نص أولي كافٍ لإنشاء تذكرة الدعم للاختبار.',
        ], $data), $user);
    }

    public function test_staff_reply_queues_email_job_not_sent_inline(): void
    {
        Queue::fake();
        Mail::fake();

        $user = $this->beneficiary();
        $admin = $this->admin();
        $ticket = $this->createTicket($user);

        app(SupportTicketService::class)->addSupportReply($ticket, $admin, [
            'body' => 'رد الدعم يصل بالبريد.',
        ]);

        Queue::assertPushed(SendSupportReplyEmailJob::class, 1);
        Mail::assertNothingSent();
    }

    public function test_staff_reply_email_sent_with_expected_content(): void
    {
        Mail::fake();

        $user = $this->beneficiary(['notify_email' => false]);
        $admin = $this->admin();
        $ticket = $this->createTicket($user);

        $message = app(SupportTicketService::class)->addSupportReply($ticket, $admin, [
            'body' => 'نص رد فريق الدعم للمستفيد.',
            'new_status' => SupportTicketStatus::InProgress->value,
        ]);

        Mail::assertSent(SupportReplyMail::class, function (SupportReplyMail $mail) use ($user, $ticket, $message): bool {
            $mail->assertTo($user->email);
            $mail->assertHasSubject('رد جديد على تذكرة الدعم رقم '.$ticket->displayNumber());

            $html = $mail->render();

            return str_contains($html, 'مستفيد الاختبار')
                && str_contains($html, $ticket->subject)
                && str_contains($html, 'نص رد فريق الدعم للمستفيد.')
                && str_contains($html, SupportTicketStatus::InProgress->label())
                && str_contains($html, 'عرض التذكرة والرد')
                && str_contains($html, route('portal.support.show', $ticket))
                && str_contains($html, 'يرجى عدم الرد على هذا البريد')
                && ! str_contains($html, (string) ($ticket->admin_notes ?? '___never___'))
                && $mail->message->is($message);
        });

        $this->assertNotNull($message->fresh()->beneficiary_email_sent_at);
    }

    public function test_reply_saved_even_when_mail_fails(): void
    {
        Queue::fake();

        $user = $this->beneficiary();
        $admin = $this->admin();
        $ticket = $this->createTicket($user);

        $message = app(SupportTicketService::class)->addSupportReply($ticket, $admin, [
            'body' => 'الرد محفوظ رغم فشل البريد.',
        ]);

        $this->assertDatabaseHas('support_ticket_messages', [
            'id' => $message->id,
            'body' => 'الرد محفوظ رغم فشل البريد.',
        ]);
        Queue::assertPushed(SendSupportReplyEmailJob::class, 1);

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('resend unavailable'));

        try {
            (new SendSupportReplyEmailJob($message->id))->handle();
            $this->fail('Expected mail transport failure to bubble for queue retry.');
        } catch (\RuntimeException $e) {
            $this->assertSame('resend unavailable', $e->getMessage());
        }

        $this->assertDatabaseHas('support_ticket_messages', [
            'id' => $message->id,
            'body' => 'الرد محفوظ رغم فشل البريد.',
        ]);
        $this->assertNull($message->fresh()->beneficiary_email_sent_at);
    }

    public function test_no_email_on_ticket_open_or_unread_poll(): void
    {
        Queue::fake();
        Mail::fake();

        $user = $this->beneficiary();
        $admin = $this->admin();
        $ticket = $this->createTicket($user);

        Queue::assertNotPushed(SendSupportReplyEmailJob::class);

        $this->actingAsOtpVerified($user)
            ->get(route('portal.support.show', $ticket))
            ->assertOk();

        $this->actingAsOtpVerified($user)
            ->getJson(route('portal.support.unread-count'))
            ->assertOk();

        Queue::assertNotPushed(SendSupportReplyEmailJob::class);
        Mail::assertNothingSent();

        // Status-only change without a staff textual reply → no email.
        app(SupportTicketService::class)->changeStatus(
            $ticket->fresh(),
            $admin,
            SupportTicketStatus::InProgress,
            statusUpdateText: null,
        );

        Queue::assertNotPushed(SendSupportReplyEmailJob::class);
    }

    public function test_status_change_with_reply_sends_one_email_containing_both(): void
    {
        Mail::fake();

        $user = $this->beneficiary();
        $admin = $this->admin();
        $ticket = $this->createTicket($user);

        app(SupportTicketService::class)->addSupportReply($ticket, $admin, [
            'body' => 'تم التحويل لانتظار ردك مع هذا الرد.',
            'new_status' => SupportTicketStatus::WaitingOnUser->value,
        ]);

        Mail::assertSent(SupportReplyMail::class, 1);
        Mail::assertSent(SupportReplyMail::class, function (SupportReplyMail $mail) use ($ticket): bool {
            $html = $mail->render();

            return str_contains($html, 'تم التحويل لانتظار ردك مع هذا الرد.')
                && str_contains($html, SupportTicketStatus::WaitingOnUser->label())
                && str_contains($html, $ticket->displayNumber());
        });
    }

    public function test_job_retry_does_not_duplicate_email(): void
    {
        Mail::fake();

        $user = $this->beneficiary();
        $admin = $this->admin();
        $ticket = $this->createTicket($user);

        $message = app(SupportTicketService::class)->addSupportReply($ticket, $admin, [
            'body' => 'رد واحد فقط حتى مع إعادة المحاولة.',
        ]);

        Mail::assertSent(SupportReplyMail::class, 1);
        $this->assertNotNull($message->fresh()->beneficiary_email_sent_at);

        // Simulate queue retry after a successful send marked the message.
        (new SendSupportReplyEmailJob($message->id))->handle();

        Mail::assertSent(SupportReplyMail::class, 1);
    }

    public function test_no_cross_user_email_leak(): void
    {
        Mail::fake();

        $owner = $this->beneficiary(['email' => 'owner-ticket@example.com', 'name' => 'صاحب التذكرة']);
        $other = $this->beneficiary(['email' => 'other-user@example.com', 'name' => 'مستخدم آخر']);
        $admin = $this->admin();

        $ticket = $this->createTicket($owner, ['subject' => 'تذكرة المالك فقط']);

        app(SupportTicketService::class)->addSupportReply($ticket, $admin, [
            'body' => 'محتوى سري لصاحب التذكرة فقط.',
        ]);

        Mail::assertSent(SupportReplyMail::class, 1);
        Mail::assertSent(SupportReplyMail::class, function (SupportReplyMail $mail) use ($owner, $other): bool {
            $mail->assertTo($owner->email);
            $recipients = collect($mail->to)->pluck('address')->all();

            return ! in_array($other->email, $recipients, true);
        });

        $this->actingAsOtpVerified($other)
            ->get(route('portal.support.show', $ticket))
            ->assertForbidden();
    }

    public function test_email_sent_even_when_notify_email_and_legacy_preference_off(): void
    {
        Mail::fake();

        $user = $this->beneficiary([
            'notify_email' => false,
            'notification_settings' => [
                'support_replies_email' => false,
                'categories' => [
                    'support' => ['in_app' => true, 'email' => false],
                ],
            ],
        ]);
        $admin = $this->admin();
        $ticket = $this->createTicket($user);

        app(SupportTicketService::class)->addSupportReply($ticket, $admin, [
            'body' => 'الرد يصل رغم إغلاق تفضيل البريد القديم.',
        ]);

        Mail::assertSent(SupportReplyMail::class, 1);
    }

    public function test_failure_logging_omits_message_body(): void
    {
        Event::fake([MessageLogged::class]);

        $user = $this->beneficiary();
        $message = SupportTicketMessage::query()->create([
            'support_ticket_id' => $this->createTicket($user)->id,
            'user_id' => $this->admin()->id,
            'sender_type' => SupportMessageSenderType::Support,
            'body' => 'نص سري جداً يجب ألا يظهر في السجلات',
            'is_system' => false,
            'source' => 'conversation',
            'created_at' => now(),
        ]);

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('transport down'));

        try {
            (new SendSupportReplyEmailJob($message->id))->handle();
            $this->fail('Expected transport failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame('transport down', $e->getMessage());
        }

        Event::assertDispatched(
            MessageLogged::class,
            function (MessageLogged $event): bool {
                if ($event->level !== 'warning' || ! str_contains((string) $event->message, 'support.reply_email')) {
                    return false;
                }

                $encoded = json_encode([
                    'message' => $event->message,
                    'context' => $event->context,
                ], JSON_UNESCAPED_UNICODE);

                return $encoded !== false
                    && ! str_contains($encoded, 'نص سري جداً يجب ألا يظهر في السجلات');
            }
        );
    }

    public function test_guest_ticket_staff_reply_emails_ticket_address(): void
    {
        Mail::fake();
        Notification::fake();

        $admin = $this->admin();
        $ticket = app(SupportTicketService::class)->createAndNotify([
            'name' => 'زائر',
            'email' => 'guest-support@example.com',
            'subject' => 'تذكرة زائر',
            'category' => 'general',
            'body' => 'وصف من زائر غير مسجل في المنصة.',
        ], null);

        app(SupportTicketService::class)->addSupportReply($ticket, $admin, [
            'body' => 'رد على تذكرة الزائر.',
        ]);

        Mail::assertSent(SupportReplyMail::class, function (SupportReplyMail $mail): bool {
            $mail->assertTo('guest-support@example.com');

            return str_contains($mail->render(), 'رد على تذكرة الزائر.');
        });
    }
}
