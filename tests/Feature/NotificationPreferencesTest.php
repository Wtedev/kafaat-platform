<?php

namespace Tests\Feature;

use App\Enums\InboxNotificationType;
use App\Models\User;
use App\Services\Inbox\UserNotificationPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_settings_save_notify_email_with_duplicate_form_values(): void
    {
        $user = User::factory()->create([
            'role_type' => 'beneficiary',
            'email' => 'trainee@example.com',
            'email_verified_at' => now(),
            'notify_email' => false,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['otp_verified' => true])
            ->patch(route('portal.notifications.settings.update'), [
                'notify_email' => ['0', '1'],
                'categories' => [
                    'programs_new' => ['in_app' => '1'],
                ],
            ]);

        $response->assertRedirect(route('portal.notifications.settings'));
        $user->refresh();
        $this->assertTrue($user->notify_email);
    }

    public function test_customize_link_acknowledges_prompt_and_redirects_to_settings(): void
    {
        $user = User::factory()->create([
            'role_type' => 'beneficiary',
            'email' => 'trainee@example.com',
            'email_verified_at' => now(),
            'notify_email' => false,
            'notification_prefs_set_at' => null,
            'notification_settings' => null,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['otp_verified' => true])
            ->post(route('notification-prefs.ack'), [
                'customize' => '1',
            ]);

        $response->assertRedirect(route('portal.notifications.settings'));
        $user->refresh();
        $this->assertNotNull($user->notification_prefs_set_at);
        $this->assertFalse($user->notify_email);
        $this->assertNull($user->notification_settings);
    }

    public function test_yes_email_ack_still_saves_preferences(): void
    {
        $user = User::factory()->create([
            'role_type' => 'beneficiary',
            'email' => 'trainee@example.com',
            'email_verified_at' => now(),
            'notify_email' => false,
            'notification_prefs_set_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['otp_verified' => true])
            ->from(route('portal.notifications'))
            ->post(route('notification-prefs.ack'), [
                'notify_email' => '1',
            ]);

        $response->assertRedirect(route('portal.notifications'));
        $user->refresh();
        $this->assertNotNull($user->notification_prefs_set_at);
        $this->assertTrue($user->notify_email);
        $this->assertNotNull($user->notification_settings);
    }

    public function test_creator_audience_email_only_requires_master_toggle(): void
    {
        $user = User::factory()->create([
            'role_type' => 'beneficiary',
            'email' => 'trainee@example.com',
            'notify_email' => true,
            'notification_settings' => [
                'categories' => [
                    'programs_new' => ['in_app' => false, 'email' => false],
                ],
            ],
        ]);

        $prefs = app(UserNotificationPreferences::class);

        $this->assertTrue($prefs->wantsEmailForCreatorAudience($user, InboxNotificationType::ProgramLaunched));
        $this->assertFalse($prefs->wantsEmailForType($user, InboxNotificationType::ProgramLaunched));
    }
}
