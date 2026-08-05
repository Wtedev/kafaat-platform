<?php

namespace Tests\Feature\Support;

use App\Enums\SupportMessageSenderType;
use App\Enums\SupportTicketStatus;
use App\Filament\Pages\SupportInbox;
use App\Jobs\SendSupportReplyEmailJob;
use App\Models\SupportTicket;
use App\Models\SupportTicketInternalNote;
use App\Models\User;
use App\Services\Support\SupportTicketService;
use App\Services\Support\SupportUnreadService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

/**
 * Support inbox UX overhaul — coverage for product items 1–14.
 */
class SupportInboxUxTest extends TestCase
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

    private function beneficiary(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'role_type' => 'beneficiary',
            'email_verified_at' => now(),
            'is_active' => true,
            'notify_email' => false,
            'notification_prefs_set_at' => now(),
        ], $overrides));
        $user->assignRole('beneficiary');

        return $user;
    }

    private function admin(): User
    {
        $user = User::factory()->create([
            'role_type' => 'admin',
            'email' => 'admin-inbox-'.uniqid().'@example.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $user->assignRole('admin');

        return $user;
    }

    private function staffWith(array $perms): User
    {
        $user = User::factory()->create([
            'role_type' => 'staff',
            'email' => 'staff-inbox-'.uniqid().'@example.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $user->assignRole('staff');
        foreach ($perms as $perm) {
            Permission::findOrCreate($perm, 'web');
            $user->givePermissionTo($perm);
        }

        return $user;
    }

    /** 1 — Widget create when no open ticket → conversation payload */
    public function test_widget_create_transitions_to_conversation(): void
    {
        Notification::fake();
        $user = $this->beneficiary();

        $this->actingAsOtpVerified($user)
            ->getJson(route('portal.support.widget.state'))
            ->assertOk()
            ->assertJsonPath('mode', 'create');

        $response = $this->actingAsOtpVerified($user)
            ->postJson(route('portal.support.widget.store'), [
                'subject' => 'مشكلة ويدجت',
                'body' => 'تفاصيل كافية لإنشاء تذكرة من الويدجت.',
            ])
            ->assertCreated()
            ->assertJsonPath('mode', 'conversation')
            ->assertJsonPath('created', true);

        $number = $response->json('ticket.number');
        $this->assertNotEmpty($number);
        $this->assertFalse((bool) $response->json('ticket.is_closed'));
        $this->assertTrue((bool) $response->json('ticket.can_reply'));
        $this->assertNotEmpty($response->json('ticket.messages'));
    }

    /** 2 — Open ticket skips create and opens conversation */
    public function test_widget_state_opens_existing_conversation(): void
    {
        Notification::fake();
        $user = $this->beneficiary();
        $ticket = app(SupportTicketService::class)->createAndNotify([
            'subject' => 'موجودة',
            'body' => 'نص كافٍ لتذكرة مفتوحة موجودة مسبقاً.',
        ], $user);

        $this->actingAsOtpVerified($user)
            ->getJson(route('portal.support.widget.state'))
            ->assertOk()
            ->assertJsonPath('mode', 'conversation')
            ->assertJsonPath('ticket.id', $ticket->id)
            ->assertJsonPath('ticket.number', $ticket->displayNumber());
    }

    /** 3 — One open ticket rule; second create returns existing */
    public function test_one_open_ticket_rule_returns_existing(): void
    {
        Notification::fake();
        $user = $this->beneficiary();
        $svc = app(SupportTicketService::class);

        $first = $svc->createAndNotify([
            'subject' => 'الأولى',
            'body' => 'نص كافٍ للتذكرة الأولى المفتوحة.',
        ], $user);

        $second = $svc->createAndNotify([
            'subject' => 'الثانية',
            'body' => 'محاولة ثانية يجب أن تُرجع الأولى.',
        ], $user);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, SupportTicket::query()->ownedBy($user)->count());

        $this->actingAsOtpVerified($user)
            ->postJson(route('portal.support.widget.store'), [
                'subject' => 'ثالثة',
                'body' => 'من الويدجت أيضاً تُرجع المفتوحة.',
            ])
            ->assertOk()
            ->assertJsonPath('created', false)
            ->assertJsonPath('ticket.id', $first->id);
    }

    /** 4 — Messages chronological; sides labeled */
    public function test_messages_are_chronological_with_party_labels(): void
    {
        Notification::fake();
        Queue::fake();
        $user = $this->beneficiary();
        $admin = $this->admin();
        $svc = app(SupportTicketService::class);

        $ticket = $svc->createAndNotify([
            'subject' => 'ترتيب',
            'body' => 'الرسالة الأولى من المستفيد.',
        ], $user);
        $svc->addSupportReply($ticket->fresh(), $admin, ['body' => 'رد فريق الدعم.']);
        $svc->addBeneficiaryReply($ticket->fresh(), $user, 'رد المستفيد الثاني.');

        $payload = $this->actingAsOtpVerified($user)
            ->getJson(route('portal.support.widget.show', $ticket))
            ->assertOk()
            ->json('ticket.messages');

        $this->assertGreaterThanOrEqual(3, count($payload));
        $ids = array_column($payload, 'id');
        $sorted = $ids;
        sort($sorted);
        $this->assertSame($sorted, $ids);

        $sides = array_column($payload, 'side');
        $this->assertContains('self', $sides);
        $this->assertContains('support', $sides);

        $labels = array_column($payload, 'label');
        $this->assertContains('أنت', $labels);
        $this->assertContains('فريق الدعم', $labels);
    }

    /** 5 — Beneficiary reply increments staff unread; opening marks read */
    public function test_beneficiary_reply_unread_and_mark_read(): void
    {
        Notification::fake();
        Queue::fake();
        $user = $this->beneficiary();
        $admin = $this->admin();
        $svc = app(SupportTicketService::class);
        $unread = app(SupportUnreadService::class);

        $ticket = $svc->createAndNotify([
            'subject' => 'غير مقروء',
            'body' => 'الرسالة الأولى.',
        ], $user);
        $unread->markTicketRead($ticket, $admin, 'staff');
        $this->assertSame(0, $unread->unreadBeneficiaryCountForTicket($ticket, $admin));

        $svc->addBeneficiaryReply($ticket->fresh(), $user, 'رد جديد من المستفيد.');
        $this->assertSame(1, $unread->unreadBeneficiaryCountForTicket($ticket->fresh(), $admin));

        $unread->markTicketRead($ticket->fresh(), $admin, 'staff');
        $this->assertSame(0, $unread->unreadBeneficiaryCountForTicket($ticket->fresh(), $admin));
    }

    /** 6 — Closed forbids beneficiary and staff replies */
    public function test_closed_ticket_forbids_replies_both_sides(): void
    {
        Notification::fake();
        Queue::fake();
        $user = $this->beneficiary();
        $admin = $this->admin();
        $svc = app(SupportTicketService::class);

        $ticket = $svc->createAndNotify([
            'subject' => 'للإغلاق',
            'body' => 'نص كافٍ قبل الإغلاق.',
        ], $user);
        $svc->changeStatus($ticket, $admin, SupportTicketStatus::Closed, 'مغلق للاختبار', 'close');

        $this->expectException(ValidationException::class);
        $svc->addBeneficiaryReply($ticket->fresh(), $user, 'محاولة رد مستفيد');
    }

    public function test_closed_ticket_forbids_staff_reply(): void
    {
        Notification::fake();
        Queue::fake();
        $user = $this->beneficiary();
        $admin = $this->admin();
        $svc = app(SupportTicketService::class);

        $ticket = $svc->createAndNotify([
            'subject' => 'للإغلاق 2',
            'body' => 'نص كافٍ قبل الإغلاق للموظف.',
        ], $user);
        $svc->changeStatus($ticket, $admin, SupportTicketStatus::Closed, 'مغلق', 'close');

        $this->expectException(ValidationException::class);
        $svc->addSupportReply($ticket->fresh(), $admin, ['body' => 'محاولة رد موظف']);
    }

    /** 7 — New ticket after close gets independent number */
    public function test_new_ticket_after_close_is_independent(): void
    {
        Notification::fake();
        $user = $this->beneficiary();
        $admin = $this->admin();
        $svc = app(SupportTicketService::class);

        $first = $svc->createAndNotify([
            'subject' => 'قديمة',
            'body' => 'نص كافٍ للتذكرة التي ستُغلق.',
        ], $user);
        $svc->changeStatus($first, $admin, SupportTicketStatus::Closed, 'إغلاق', 'close');

        $second = $svc->createAndNotify([
            'subject' => 'جديدة',
            'body' => 'تذكرة جديدة بعد الإغلاق برقم مستقل.',
        ], $user);

        $this->assertNotSame($first->id, $second->id);
        $this->assertNotSame($first->ticket_number, $second->ticket_number);
        $this->assertSame(SupportTicketStatus::Open, $second->status);
        $this->assertSame(2, SupportTicket::query()->ownedBy($user)->count());
    }

    /** 8 — Internal notes never leak to beneficiary UI/API */
    public function test_internal_notes_never_leak_to_beneficiary(): void
    {
        Notification::fake();
        Queue::fake();
        $user = $this->beneficiary();
        $admin = $this->admin();
        $svc = app(SupportTicketService::class);

        $ticket = $svc->createAndNotify([
            'subject' => 'ملاحظات',
            'body' => 'نص كافٍ لاختبار تسريب الملاحظات.',
        ], $user);

        $note = $svc->addInternalNote($ticket, $admin, 'ملاحظة داخلية سرية جداً');

        $this->actingAsOtpVerified($user)
            ->getJson(route('portal.support.widget.show', $ticket))
            ->assertOk()
            ->assertDontSee('ملاحظة داخلية سرية جداً', false);

        $this->actingAsOtpVerified($user)
            ->get(route('portal.support.show', $ticket))
            ->assertOk()
            ->assertDontSee('ملاحظة داخلية سرية جداً', false);

        $bodies = $ticket->messages()->visibleToBeneficiary()->pluck('body')->all();
        $this->assertNotContains('ملاحظة داخلية سرية جداً', $bodies);
        $this->assertDatabaseHas('support_ticket_internal_notes', ['id' => $note->id]);
    }

    /** 9 — Admin inbox search/filters/counters */
    public function test_admin_inbox_search_filters_and_counters(): void
    {
        Notification::fake();
        Queue::fake();
        $user = $this->beneficiary(['name' => 'أحمد البحث', 'email' => 'ahmad-search@example.com']);
        $admin = $this->admin();
        $svc = app(SupportTicketService::class);

        $open = $svc->createAndNotify([
            'subject' => 'موضوع البحث الفريد',
            'body' => 'نص كافٍ لتذكرة مفتوحة للبحث.',
        ], $user);
        $svc->addBeneficiaryReply($open->fresh(), $user, 'رد لجعلها غير مقروءة للموظف.');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(SupportInbox::class)
            ->set('search', 'موضوع البحث الفريد')
            ->assertSee('موضوع البحث الفريد')
            ->assertSee($open->displayNumber())
            ->set('filterTab', 'open')
            ->assertSee($open->displayNumber())
            ->set('filterTab', 'unread')
            ->assertSee($open->displayNumber())
            ->call('selectTicket', $open->id)
            ->assertSet('selectedTicketId', $open->id);
    }

    /** 10 — IDOR / permissions */
    public function test_idor_and_permission_gates(): void
    {
        Notification::fake();
        $owner = $this->beneficiary();
        $other = $this->beneficiary(['email' => 'other-idor@example.com']);
        $ticket = app(SupportTicketService::class)->createAndNotify([
            'subject' => 'ملكية',
            'body' => 'نص كافٍ لاختبار عزل المستفيدين.',
        ], $owner);

        $this->actingAsOtpVerified($other)
            ->getJson(route('portal.support.widget.show', $ticket))
            ->assertForbidden();

        $this->actingAsOtpVerified($other)
            ->postJson(route('portal.support.widget.reply', $ticket), ['body' => 'اختراق'])
            ->assertForbidden();

        $staff = $this->staffWith([]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::actingAs($staff)
            ->test(SupportInbox::class)
            ->assertForbidden();
    }

    /** 11 — Staff reply queues email once (PR #48) */
    public function test_staff_reply_queues_email_once(): void
    {
        Notification::fake();
        Queue::fake();
        $user = $this->beneficiary();
        $admin = $this->admin();
        $svc = app(SupportTicketService::class);

        $ticket = $svc->createAndNotify([
            'subject' => 'بريد',
            'body' => 'نص كافٍ لاختبار طابور البريد.',
        ], $user);

        $svc->addSupportReply($ticket->fresh(), $admin, ['body' => 'رد يُرسل بالبريد.']);

        Queue::assertPushed(SendSupportReplyEmailJob::class, 1);
        $this->assertSame(SupportTicketStatus::InProgress, $ticket->fresh()->status);
    }

    /** 12 — Guest flow: create + ticket number, no widget chat API without auth */
    public function test_guest_flow_has_number_and_no_private_chat(): void
    {
        Notification::fake();
        config(['app.admin_email' => 'admin-guest@example.com']);
        $this->admin();

        $this->from(route('home'))
            ->post(route('public.support-tickets.store'), [
                'name' => 'زائر',
                'email' => 'guest-ux@example.com',
                'subject' => 'من الزائر',
                'body' => 'وصف المشكلة من الزائر دون محادثة خاصة.',
            ])
            ->assertRedirect(route('home'))
            ->assertSessionHas('success');

        $ticket = SupportTicket::query()->where('email', 'guest-ux@example.com')->first();
        $this->assertNotNull($ticket);
        $this->assertNull($ticket->user_id);
        $this->assertStringContainsString($ticket->displayNumber(), (string) session('success'));

        $this->getJson(route('portal.support.widget.state'))->assertUnauthorized();
    }

    /** 13 — Closed widget state exposes new-ticket affordance copy */
    public function test_closed_ticket_widget_payload_is_read_only(): void
    {
        Notification::fake();
        $user = $this->beneficiary();
        $admin = $this->admin();
        $svc = app(SupportTicketService::class);

        $ticket = $svc->createAndNotify([
            'subject' => 'مغلقة للويدجت',
            'body' => 'نص كافٍ ثم إغلاق لعرض حالة القراءة فقط.',
        ], $user);
        $svc->changeStatus($ticket, $admin, SupportTicketStatus::Closed, 'إغلاق', 'close');

        // No open ticket → create mode, with closed_ticket hint for history CTA.
        $this->actingAsOtpVerified($user)
            ->getJson(route('portal.support.widget.state'))
            ->assertOk()
            ->assertJsonPath('mode', 'create')
            ->assertJsonPath('closed_ticket.id', $ticket->id)
            ->assertJsonPath('closed_ticket.can_reply', false);

        $this->actingAsOtpVerified($user)
            ->getJson(route('portal.support.widget.show', $ticket))
            ->assertOk()
            ->assertJsonPath('ticket.can_reply', false)
            ->assertJsonPath('ticket.is_closed', true)
            ->assertJsonFragment(['closed_message' => 'تم إغلاق هذه التذكرة. إذا كنت بحاجة إلى مساعدة إضافية، يمكنك فتح تذكرة جديدة.']);
    }

    /** 14 — Mobile/a11y signals in FAB markup (testable static attributes) */
    public function test_fab_exposes_a11y_and_chat_structure_for_portal_users(): void
    {
        $user = $this->beneficiary();

        $html = $this->actingAsOtpVerified($user)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-support-fab', $html);
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('data-auth="1"', $html);
        $this->assertStringContainsString('data-support-messages', $html);
        $this->assertStringContainsString('prefers-reduced-motion', $html);
        $this->assertStringContainsString('safe-area-inset-bottom', $html);
        $this->assertStringContainsString(route('portal.support.widget.state'), $html);
    }

    public function test_staff_reply_visible_in_inbox_and_notes_permission(): void
    {
        Notification::fake();
        Queue::fake();
        $user = $this->beneficiary();
        $admin = $this->admin();
        $svc = app(SupportTicketService::class);

        $ticket = $svc->createAndNotify([
            'subject' => 'صندوق',
            'body' => 'نص كافٍ لرد الموظف من الصندوق.',
        ], $user);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(SupportInbox::class)
            ->call('selectTicket', $ticket->id)
            ->set('replyBody', 'رد من صندوق الدعم')
            ->call('sendReply')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'body' => 'رد من صندوق الدعم',
            'sender_type' => SupportMessageSenderType::Support->value,
        ]);

        Livewire::actingAs($admin)
            ->test(SupportInbox::class)
            ->call('selectTicket', $ticket->id)
            ->set('noteBody', 'ملاحظة داخلية من الصندوق')
            ->call('addNote')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('support_ticket_internal_notes', [
            'support_ticket_id' => $ticket->id,
            'body' => 'ملاحظة داخلية من الصندوق',
        ]);
        $this->assertSame(1, SupportTicketInternalNote::query()->count());
        Queue::assertPushed(SendSupportReplyEmailJob::class, 1);
    }
}
