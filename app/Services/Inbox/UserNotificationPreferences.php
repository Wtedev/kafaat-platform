<?php

namespace App\Services\Inbox;

use App\Enums\InboxNotificationType;
use App\Enums\NotificationPreferenceCategory;
use App\Models\User;

final class UserNotificationPreferences
{
    /**
     * تحويل قيمة نموذج (checkbox / hidden مكرر) إلى منطقي.
     */
    public static function parseBool(mixed $value): bool
    {
        if (is_array($value)) {
            $value = end($value);
        }

        if ($value === null || $value === '') {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<string, array{in_app: bool, email: bool}>
     */
    public function resolvedCategories(User $user): array
    {
        $settings = $user->notification_settings;
        $stored = is_array($settings) && is_array($settings['categories'] ?? null)
            ? $settings['categories']
            : [];

        $out = [];
        foreach (NotificationPreferenceCategory::forBeneficiarySettings() as $category) {
            $defaults = $category->defaultPreferences();
            $saved = $stored[$category->value] ?? [];

            $out[$category->value] = [
                'in_app' => array_key_exists('in_app', $saved)
                    ? (bool) $saved['in_app']
                    : $defaults['in_app'],
                'email' => array_key_exists('email', $saved)
                    ? (bool) $saved['email']
                    : $defaults['email'],
            ];
        }

        return $out;
    }

    public function wantsInApp(User $user, InboxNotificationType $type): bool
    {
        if (NotificationPreferenceCatalog::isStaffOnly($type)) {
            return true;
        }

        $category = NotificationPreferenceCatalog::categoryFor($type);
        if ($category === null) {
            return true;
        }

        if (! $category->canDisableInApp()) {
            return true;
        }

        $prefs = $this->resolvedCategories($user);

        return ($prefs[$category->value]['in_app'] ?? true) === true;
    }

    public function wantsEmailForType(User $user, InboxNotificationType $type): bool
    {
        if (! $user->wantsEmailNotifications()) {
            return false;
        }

        if (! NotificationPreferenceCatalog::systemAllowsEmail($type)) {
            return false;
        }

        $category = NotificationPreferenceCatalog::categoryFor($type);
        if ($category === null || ! $category->supportsEmail()) {
            return false;
        }

        $prefs = $this->resolvedCategories($user);

        return ($prefs[$category->value]['email'] ?? false) === true;
    }

    /**
     * بريد جمهور النشر عندما يختار المنشئ إرسال تنبيه (notify_on_publish): يكفي notify_email العام.
     */
    public function wantsEmailForCreatorAudience(User $user, InboxNotificationType $type): bool
    {
        if (! $user->wantsEmailNotifications()) {
            return false;
        }

        return NotificationPreferenceCatalog::systemAllowsEmail($type);
    }

    /**
     * @param  array<string, mixed>  $input  من نموذج الإعدادات (categories.*.in_app / email)
     * @return array{categories: array<string, array{in_app: bool, email: bool}>}
     */
    public function normalizeFromRequest(array $input, bool $masterEmailEnabled = true): array
    {
        $categories = [];
        $raw = is_array($input['categories'] ?? null) ? $input['categories'] : [];

        foreach (NotificationPreferenceCategory::forBeneficiarySettings() as $category) {
            $row = is_array($raw[$category->value] ?? null) ? $raw[$category->value] : [];

            $inApp = $category->canDisableInApp()
                ? self::parseBool($row['in_app'] ?? false)
                : true;

            $email = $masterEmailEnabled
                && $inApp
                && $category->supportsEmail()
                && self::parseBool($row['email'] ?? false);

            $categories[$category->value] = [
                'in_app' => $inApp,
                'email' => $email,
            ];
        }

        return ['categories' => $categories];
    }
}
