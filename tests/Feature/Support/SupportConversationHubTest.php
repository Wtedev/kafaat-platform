<?php

namespace Tests\Feature\Support;

use App\Enums\InboxNotificationType;
use App\Enums\SupportMessageSenderType;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketStatus;
use App\Filament\Resources\SupportTicketResource;
use App\Filament\Resources\SupportTicketResource\Pages\ViewSupportTicket;
use App\Models\InboxNotification;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\SupportTicketStatusEvent;
use App\Models\User;
use App\Notifications\SupportTicketCreatedMail;
use App\Services\Inbox\UserNotificationPreferences;
use App\Services\Rbac\PermissionMatrixCatalog;
use App\Services\Rbac\RbacCatalog;
use App\Services\Support\SupportTicketService;
use App\Services\Support\SupportUnreadService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class SupportConversationHubTest extends TestCase
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
            'email' => 'admin-support-'.uniqid().'@example.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $user->assignRole('admin');

        return $user;
    }

    private function actingPortal(User $user)
    {
        return $this->actingAsOtpVerified($user);
    }

    public function test_portal_create_ticket_creates_message_and_status_event(): void
    {
        Notification::fake();
        $user = $this->beneficiary();

        $response = $this->actingPortal($user)->post(route('portal.support.store'), [
            'subject' => 'مشكلة تسجيل',
            'category' => SupportTicketCategory::Registration->value,
            'body' => 'لا أستطيع إكمال التسجيل في البرنامج المطلوب.',
            'idempotency_key' => 'key-1',
        ]);

        $ticket = SupportTicket::query()->first();
        $this->assertNotNull($ticket);
        $response->assertRedirect(route('portal.support.show', $ticket));

        $this->assertSame($user->id, $ticket->user_id);
        $this->assertNotEmpty($ticket->ticket_number);
        $this->assertSame(SupportTicketStatus::Open, $ticket->status);
        $this->assertSame(1, $ticket->messages()->count());
        $this->assertSame(SupportMessageSenderType::Beneficiary, $ticket->messages()->first()->sender_type);
        $this->assertSame(1, $ticket->statusEvents()->count());
        $this->assertSame($user->name, $ticket->name);
        $this->assertSame($user->email, $ticket->email);
    }

    public function test_create_is_idempotent_with_same_key(): void
    {
        Notification::fake();
        $user = $this->beneficiary();

        $this->actingPortal($user)->post(route('portal.support.store'), [
            'subject' => 'موضوع',
            'category' => 'general',
            'body' => 'وصف كافٍ للمشكلة هنا.',
            'idempotency_key' => 'same-key',
        ])->assertRedirect();

        $this->actingPortal($user)->post(route('portal.support.store'), [
            'subject' => 'موضوع آخر',
            'category' => 'general',
            'body' => 'وصف كافٍ للمشكلة هنا مرة أخرى.',
            'idempotency_key' => 'same-key',
        ])->assertRedirect();

        $this->assertSame(1, SupportTicket::query()->count());
    }

    public function test_beneficiary_cannot_view_others_ticket_idor(): void
    {
        Notification::fake();
        $owner = $this->beneficiary(['email' => 'owner@example.com']);
        $other = $this->beneficiary(['email' => 'other@example.com']);

        $ticket = app(SupportTicketService::class)->createAndNotify([
            'subject' => 'خاص',
            'category' => 'general',
            'body' => 'رسالة خاصة بالمالك فقط هنا.',
        ], $owner);

        $this->actingPortal($other)
            ->get(route('portal.support.show', $ticket))
            ->assertForbidden();
    }

    public function test_list_shows_only_own_tickets(): void
    {
        Notification::fake();
        $a = $this->beneficiary(['email' => 'a@example.com']);
        $b = $this->beneficiary(['email' => 'b@example.com']);
        $svc = app(SupportTicketService::class);
        $svc->createAndNotify(['subject' => 'OWN-TICKET-ALPHA', 'category' => 'general', 'body' => 'نص التذكرة الخاصة بالمستخدم أ.'], $a);
        $svc->createAndNotify(['subject' => 'OTHER-TICKET-BETA', 'category' => 'general', 'body' => 'نص التذكرة الخاصة بالمستخدم ب.'], $b);

        $this->actingPortal($a)
            ->get(route('portal.support.index'))
            ->assertOk()
            ->assertSee('OWN-TICKET-ALPHA')
            ->assertDontSee('OTHER-TICKET-BETA');
    }

    public function test_support_reply_marks_unread_and_clears_on_open(): void
    {
        Notification::fake();
        $user = $this->beneficiary(['notify_email' => true]);
        $admin = $this->admin();
        $svc = app(SupportTicketService::class);
        $unread = app(SupportUnreadService::class);

        $ticket = $svc->createAndNotify([
            'subject' => 'استفسار',
            'category' => 'general',
            'body' => 'أحتاج مساعدة بخصوص الحساب من فضلكم.',
        ], $user);

        $this->assertSame(0, $unread->unreadSupportReplyCount($user));

        $svc->addSupportReply($ticket, $admin, [
            'body' => 'مرحباً، تم استلام طلبك وسنساعدك.',
            'new_status' => SupportTicketStatus::InProgress->value,
        ]);

        $this->assertSame(1, $unread->unreadSupportReplyCount($user));

        $this->actingPortal($user)
            ->get(route('portal.support.show', $ticket))
            ->assertOk()
            ->assertSee('مرحباً، تم استلام طلبك');

        $this->assertSame(0, $unread->unreadSupportReplyCount($user));
    }

    public function test_badge_endpoint_returns_unread_count(): void
    {
        Notification::fake();
        $user = $this->beneficiary();
        $admin = $this->admin();
        $svc = app(SupportTicketService::class);
        $ticket = $svc->createAndNotify([
            'subject' => 'badge',
            'category' => 'general',
            'body' => 'نص كافٍ لاختبار شارة غير المقروء.',
        ], $user);
        $svc->addSupportReply($ticket, $admin, ['body' => 'رد الدعم الفني على التذكرة.']);

        $this->actingPortal($user)
            ->getJson(route('portal.support.unread-count'))
            ->assertOk()
            ->assertJsonPath('count', 1);
    }

    public function test_closed_ticket_rejects_beneficiary_reply(): void
    {
        Notification::fake();
        $user = $this->beneficiary();
        $admin = $this->admin();
        $svc = app(SupportTicketService::class);
        $ticket = $svc->createAndNotify([
            'subject' => 'إغلاق',
            'category' => 'general',
            'body' => 'نص كافٍ لاختبار إغلاق المحادثة.',
        ], $user);

        $svc->addSupportReply($ticket, $admin, [
            'body' => 'تم الحل.',
            'new_status' => SupportTicketStatus::Closed->value,
            'status_update_text' => 'أُغلقت بعد الحل.',
        ]);

        $this->actingPortal($user)
            ->post(route('portal.support.reply', $ticket), ['body' => 'محاولة رد بعد الإغلاق'])
            ->assertSessionHasErrors('body');
    }

    public function test_status_change_requires_text_for_closed(): void
    {
        Notification::fake();
        $user = $this->beneficiary();
        $admin = $this->admin();
        $svc = app(SupportTicketService::class);
        $ticket = $svc->createAndNotify([
            'subject' => 'حالة',
            'category' => 'general',
            'body' => 'نص كافٍ لاختبار إلزام نص الحالة.',
        ], $user);

        $this->expectException(ValidationException::class);
        $svc->addSupportReply($ticket, $admin, [
            'body' => 'سنغلق التذكرة.',
            'new_status' => SupportTicketStatus::Closed->value,
        ]);
    }

    public function test_status_event_recorded_with_message_link(): void
    {
        Notification::fake();
        $user = $this->beneficiary();
        $admin = $this->admin();
        $svc = app(SupportTicketService::class);
        $ticket = $svc->createAndNotify([
            'subject' => 'حدث',
            'category' => 'technical',
            'body' => 'نص كافٍ لاختبار سجل حالات التذكرة.',
        ], $user);

        $svc->addSupportReply($ticket, $admin, [
            'body' => 'نعمل على المشكلة.',
            'new_status' => SupportTicketStatus::InProgress->value,
        ]);

        $event = SupportTicketStatusEvent::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('to_status', SupportTicketStatus::InProgress->value)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame(SupportTicketStatus::Open, $event->from_status);
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertNotNull($event->support_ticket_message_id);
    }

    public function test_legacy_admin_notes_not_shown_to_beneficiary(): void
    {
        $user = $this->beneficiary();
        $ticket = SupportTicket::query()->create([
            'user_id' => $user->id,
            'ticket_number' => 'ST-LEGACY1',
            'name' => $user->name,
            'email' => $user->email,
            'subject' => 'قديمة',
            'category' => SupportTicketCategory::General,
            'body' => 'الوصف الأصلي للتذكرة القديمة.',
            'status' => SupportTicketStatus::Open,
            'admin_notes' => 'ملاحظة داخلية سرية جداً',
            'last_message_at' => now(),
            'last_message_sender_type' => SupportMessageSenderType::Beneficiary,
        ]);

        SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'sender_type' => SupportMessageSenderType::Beneficiary,
            'body' => 'الوصف الأصلي للتذكرة القديمة.',
            'source' => 'legacy_body',
            'created_at' => now(),
        ]);

        SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => SupportMessageSenderType::System,
            'body' => 'ملاحظة داخلية قديمة محفوظة في سجل التذكرة (غير مرئية للمستفيد).',
            'is_system' => true,
            'source' => 'legacy_admin_notes_marker',
            'created_at' => now(),
        ]);

        $this->actingPortal($user)
            ->get(route('portal.support.show', $ticket))
            ->assertOk()
            ->assertSee('الوصف الأصلي')
            ->assertDontSee('ملاحظة داخلية سرية جداً')
            ->assertDontSee('غير مرئية للمستفيد');
    }

    public function test_in_app_notification_on_support_reply_not_for_own_message(): void
    {
        Notification::fake();
        $user = $this->beneficiary();
        $admin = $this->admin();
        $svc = app(SupportTicketService::class);
        $ticket = $svc->createAndNotify([
            'subject' => 'تنبيه',
            'category' => 'general',
            'body' => 'نص كافٍ لاختبار تنبيه داخل المنصة.',
        ], $user);

        $svc->addSupportReply($ticket, $admin, ['body' => 'رد من فريق الدعم يظهر في الصندوق.']);

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $user->id,
            'type' => InboxNotificationType::SupportReply->value,
        ]);

        $before = InboxNotification::query()->where('user_id', $user->id)->count();
        $svc->addBeneficiaryReply($ticket->fresh(), $user, 'رد من المستفيد نفسه لا ينشئ تنبيهاً.');
        $after = InboxNotification::query()->where('user_id', $user->id)->count();
        $this->assertSame($before, $after);
    }

    public function test_support_replies_email_default_false_even_if_notify_email_true(): void
    {
        $user = $this->beneficiary([
            'notify_email' => true,
            'notification_settings' => null,
        ]);

        $prefs = app(UserNotificationPreferences::class);
        $this->assertFalse($prefs->wantsSupportRepliesEmail($user));
        $this->assertFalse($prefs->wantsEmailForType($user, InboxNotificationType::SupportReply));
    }

    public function test_support_replies_email_honors_explicit_setting(): void
    {
        $user = $this->beneficiary([
            'notify_email' => true,
            'notification_settings' => [
                'support_replies_email' => true,
                'categories' => [
                    'support' => ['in_app' => true, 'email' => true],
                ],
            ],
        ]);

        $prefs = app(UserNotificationPreferences::class);
        $this->assertTrue($prefs->wantsSupportRepliesEmail($user));
    }

    public function test_saving_notification_settings_mirrors_support_replies_email(): void
    {
        $user = $this->beneficiary(['notify_email' => true]);

        $this->actingPortal($user)->patch(route('portal.notifications.settings.update'), [
            'notify_email' => '1',
            'categories' => [
                'support' => ['in_app' => '1', 'email' => '1'],
                'account' => ['in_app' => '1'],
            ],
        ])->assertRedirect();

        $user->refresh();
        $this->assertTrue((bool) ($user->notification_settings['support_replies_email'] ?? false));
    }

    public function test_public_guest_can_still_submit_fab_ticket(): void
    {
        Notification::fake();
        config(['app.admin_email' => 'admin-support@example.com']);
        $this->admin();

        $this->post(route('public.support-tickets.store'), [
            'name' => 'زائر',
            'email' => 'guest@example.com',
            'subject' => 'مشكلة عامة',
            'body' => 'وصف المشكلة من الزائر غير المسجل.',
            'page_url' => 'https://example.com/',
        ])->assertRedirect();

        $ticket = SupportTicket::query()->first();
        $this->assertNotNull($ticket);
        $this->assertNull($ticket->user_id);
        $this->assertSame(1, $ticket->messages()->count());
    }

    public function test_authenticated_portal_user_redirected_from_fab_to_hub(): void
    {
        $user = $this->beneficiary();

        $this->actingPortal($user)
            ->post(route('public.support-tickets.store'), [
                'name' => $user->name,
                'email' => $user->email,
                'subject' => 'x',
                'body' => 'should redirect to hub create page instead.',
            ])
            ->assertRedirect(route('portal.support.create'));

        $this->assertSame(0, SupportTicket::query()->count());
    }

    public function test_ticket_numbers_are_unique_and_retry_on_collision(): void
    {
        Notification::fake();
        $user = $this->beneficiary();
        $svc = app(SupportTicketService::class);

        $first = $svc->create([
            'subject' => 'أولى',
            'category' => 'general',
            'body' => 'نص كافٍ لإنشاء التذكرة الأولى للرقم.',
        ], $user);

        $this->assertMatchesRegularExpression('/^ST-\d{6}$/', (string) $first->ticket_number);

        $calls = 0;
        SupportTicketService::setNextTicketNumberResolver(function () use (&$calls, $first): string {
            $calls++;
            if ($calls === 1) {
                return (string) $first->ticket_number;
            }

            return 'ST-999901';
        });

        try {
            $second = $svc->create([
                'subject' => 'ثانية',
                'category' => 'general',
                'body' => 'نص كافٍ لإنشاء التذكرة الثانية بعد التصادم.',
            ], $user);
        } finally {
            SupportTicketService::setNextTicketNumberResolver(null);
        }

        $this->assertSame(2, $calls, 'allocator should retry after unique collision');
        $this->assertSame('ST-999901', $second->ticket_number);
        $this->assertNotSame($first->ticket_number, $second->ticket_number);
        $this->assertSame(
            2,
            SupportTicket::query()->distinct()->count('ticket_number')
        );
    }

    public function test_admin_new_ticket_mail_resolves_mixed_case_admin_email(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'role_type' => 'admin',
            'email' => 'admin.support.hub@example.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $admin->assignRole('admin');

        config(['app.admin_email' => '  Admin.Support.Hub@Example.com ']);

        $user = $this->beneficiary();
        app(SupportTicketService::class)->createAndNotify([
            'subject' => 'إشعار أدمن',
            'category' => 'general',
            'body' => 'نص كافٍ لاختبار تطبيع بريد المسؤول.',
        ], $user);

        Notification::assertSentTo($admin, SupportTicketCreatedMail::class);
    }

    public function test_admin_can_open_conversation_view(): void
    {
        Notification::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = $this->beneficiary();
        $admin = $this->admin();
        $ticket = app(SupportTicketService::class)->createAndNotify([
            'subject' => 'إدارة',
            'category' => 'general',
            'body' => 'نص كافٍ لفتح صفحة المحادثة في الإدارة.',
        ], $user);

        Livewire::actingAs($admin)
            ->test(ViewSupportTicket::class, ['record' => $ticket->getKey()])
            ->assertSuccessful();

        $this->assertTrue($ticket->fresh()->messages()->exists());
    }

    public function test_unread_count_excludes_own_beneficiary_messages(): void
    {
        Notification::fake();
        $user = $this->beneficiary();
        $admin = $this->admin();
        $svc = app(SupportTicketService::class);
        $unread = app(SupportUnreadService::class);

        $ticket = $svc->createAndNotify([
            'subject' => 'عد',
            'category' => 'general',
            'body' => 'الرسالة الأولى من المستفيد لا تُحسب.',
        ], $user);

        $svc->addBeneficiaryReply($ticket, $user, 'رد إضافي من المستفيد أيضاً لا يُحسب.');
        $this->assertSame(0, $unread->unreadSupportReplyCount($user));

        $svc->addSupportReply($ticket->fresh(), $admin, ['body' => 'رد الدعم يُحسب مرة واحدة.']);
        $svc->addSupportReply($ticket->fresh(), $admin, ['body' => 'ورد ثانٍ من الدعم يُحسب أيضاً.']);
        $this->assertSame(2, $unread->unreadSupportReplyCount($user));
    }

    public function test_reopen_allows_beneficiary_reply_again(): void
    {
        Notification::fake();
        $user = $this->beneficiary();
        $admin = $this->admin();
        $svc = app(SupportTicketService::class);
        $ticket = $svc->createAndNotify([
            'subject' => 'إعادة فتح',
            'category' => 'general',
            'body' => 'نص كافٍ لاختبار إعادة فتح المحادثة.',
        ], $user);

        $svc->changeStatus($ticket, $admin, SupportTicketStatus::Closed, 'إغلاق مؤقت', 'close');
        $svc->changeStatus($ticket->fresh(), $admin, SupportTicketStatus::Open, 'إعادة فتح للمتابعة', 'reopen');

        $this->actingPortal($user)
            ->post(route('portal.support.reply', $ticket->fresh()), [
                'body' => 'شكراً لإعادة الفتح، هذا ردي الجديد.',
            ])
            ->assertRedirect(route('portal.support.show', $ticket));
    }

    public function test_nav_support_icon_present_in_portal_layout(): void
    {
        $user = $this->beneficiary();

        $this->actingPortal($user)
            ->get(route('portal.support.index'))
            ->assertOk()
            ->assertSee('aria-label="الدعم الفني"', false)
            ->assertSee('data-support-nav', false);
    }

    public function test_support_permissions_are_seeded_and_in_matrix(): void
    {
        $names = [
            'support_tickets.view',
            'support_tickets.reply',
            'support_tickets.assign',
            'support_tickets.manage_status',
        ];

        $this->assertSame(
            4,
            Permission::query()->where('guard_name', RbacCatalog::GUARD_WEB)->whereIn('name', $names)->count()
        );

        foreach ($names as $name) {
            $this->assertContains($name, RbacCatalog::allPermissionNames());
        }

        $assignable = PermissionMatrixCatalog::assignablePermissionNames();
        foreach ($names as $name) {
            $this->assertContains($name, $assignable);
        }
    }

    public function test_staff_without_support_perms_cannot_access_filament_resource(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $staff = User::factory()->create([
            'role_type' => 'staff',
            'email' => 'staff-no-support-'.uniqid().'@example.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $staff->assignRole('staff');

        $this->actingAs($staff);
        $this->assertFalse(SupportTicketResource::canAccess());
        $this->assertFalse($staff->can('support_tickets.view'));
    }

    public function test_staff_with_support_view_can_access_filament_resource(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $staff = User::factory()->create([
            'role_type' => 'staff',
            'email' => 'staff-support-'.uniqid().'@example.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $staff->assignRole('staff');
        $staff->givePermissionTo('support_tickets.view');

        $this->actingAs($staff);
        $this->assertTrue(SupportTicketResource::canAccess());
        $this->assertTrue($staff->can('support_tickets.view'));
    }
}
