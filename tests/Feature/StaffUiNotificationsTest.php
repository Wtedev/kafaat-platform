<?php

namespace Tests\Feature;

use App\Enums\InboxNotificationType;
use App\Enums\NotificationTargetType;
use App\Models\InboxNotification;
use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class StaffUiNotificationsTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        config(['staff_ui.maintenance' => false]);
    }

    public function test_bell_shows_an_empty_state_when_there_are_no_notifications(): void
    {
        $admin = $this->admin();

        $this->actingAsOtpVerified($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('لا توجد إشعارات.')
            ->assertDontSee('sui-badge', false)
            ->assertDontSee('تعليم الكل كمقروء');
    }

    public function test_bell_lists_the_latest_ten_and_the_unread_count(): void
    {
        $admin = $this->admin();
        $other = $this->admin();

        foreach (range(1, 12) as $i) {
            $this->notification($admin, sprintf('عنوان-%02d', $i), now()->subMinutes(13 - $i), read: $i < 10);
        }
        $this->notification($other, 'تنبيه شخص آخر', now());

        $this->actingAsOtpVerified($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('class="sui-badge">3', false)
            ->assertSee('عنوان-12')
            ->assertSee('عنوان-03')
            ->assertDontSee('عنوان-01')
            ->assertDontSee('عنوان-02')
            ->assertDontSee('تنبيه شخص آخر')
            ->assertSee('تعليم الكل كمقروء')
            ->assertSee('تعليم كمقروء');
    }

    public function test_mark_one_and_mark_all_only_touch_the_signed_in_user(): void
    {
        $admin = $this->admin();
        $other = $this->admin();
        $first = $this->notification($admin, 'الأول', now()->subMinute());
        $second = $this->notification($admin, 'الثاني', now());
        $foreign = $this->notification($other, 'للغير', now());

        $this->actingAsOtpVerified($admin)
            ->from('/admin')
            ->post(route('staff-ui.notifications.read', $first))
            ->assertRedirect('/admin');

        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNull($second->fresh()->read_at);
        $this->assertNull($foreign->fresh()->read_at);

        $this->actingAsOtpVerified($admin)
            ->from('/admin')
            ->post(route('staff-ui.notifications.read', $foreign))
            ->assertForbidden();
        $this->assertNull($foreign->fresh()->read_at);

        $this->actingAsOtpVerified($admin)
            ->from('/admin')
            ->post(route('staff-ui.notifications.read-all'))
            ->assertRedirect('/admin');

        $this->assertNotNull($second->fresh()->read_at);
        $this->assertNull($foreign->fresh()->read_at);

        $this->actingAsOtpVerified($admin)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('sui-badge', false)
            ->assertDontSee('تعليم كمقروء');
    }

    public function test_trainees_cannot_mark_staff_notifications(): void
    {
        $admin = $this->admin();
        $trainee = User::factory()->create([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $trainee->assignRole(RbacCatalog::ROLE_BENEFICIARY);
        $own = $this->notification($trainee, 'تنبيه المتدرب', now());
        $staffNote = $this->notification($admin, 'تنبيه الموظف', now());

        $this->actingAsOtpVerified($trainee)
            ->post(route('staff-ui.notifications.read-all'))
            ->assertForbidden();
        $this->actingAsOtpVerified($trainee)
            ->post(route('staff-ui.notifications.read', $own))
            ->assertForbidden();
        $this->actingAsOtpVerified($trainee)
            ->post(route('staff-ui.notifications.read', $staffNote))
            ->assertForbidden();

        $this->assertNull($own->fresh()->read_at);
        $this->assertNull($staffNote->fresh()->read_at);
    }

    private function notification(User $user, string $title, \DateTimeInterface $createdAt, bool $read = false): InboxNotification
    {
        $notification = InboxNotification::query()->create([
            'user_id' => $user->id,
            'title' => $title,
            'message' => 'نص التنبيه',
            'type' => InboxNotificationType::GeneralMessage,
            'target_type' => NotificationTargetType::SingleUser,
            'read_at' => $read ? now() : null,
        ]);
        $notification->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $notification;
    }

    private function admin(): User
    {
        $admin = User::factory()->create([
            'role_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(RbacCatalog::ROLE_ADMIN);

        return $admin->fresh();
    }
}
