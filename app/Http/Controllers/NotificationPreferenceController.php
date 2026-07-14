<?php

namespace App\Http\Controllers;

use App\Services\Inbox\UserNotificationPreferences;
use App\Services\UserActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    /**
     * حفظ تفضيل إشعارات البريد من النافذة المنبثقة التي تظهر مرة واحدة لكل مستخدم.
     */
    public function acknowledge(Request $request, UserNotificationPreferences $preferences): RedirectResponse
    {
        $user = $request->user();

        // رابط «تخصيص تفصيلي»: أغلق النافذة دون فرض تفضيلات، ثم وجّه لصفحة الإعدادات.
        if ($request->boolean('customize')) {
            $user->forceFill([
                'notification_prefs_set_at' => now(),
            ])->save();

            $settingsUrl = $user->isPortalUser()
                ? route('portal.notifications.settings')
                : url('/admin/profile');

            return redirect($settingsUrl);
        }

        $wantsEmail = UserNotificationPreferences::parseBool($request->input('notify_email'));
        $settings = $preferences->normalizeFromRequest([
            'categories' => [
                'account' => [
                    'in_app' => true,
                    'email' => $wantsEmail,
                ],
                'programs_new' => [
                    'in_app' => true,
                    'email' => $wantsEmail,
                ],
                'volunteering' => [
                    'in_app' => true,
                    'email' => $wantsEmail,
                ],
                'news' => [
                    'in_app' => false,
                    'email' => $wantsEmail,
                ],
            ],
        ], $wantsEmail);

        $previousNotifyEmail = (bool) $user->notify_email;

        $user->forceFill([
            'notify_email' => $wantsEmail,
            'notification_settings' => $settings,
            'notification_prefs_set_at' => now(),
        ])->save();

        if ($previousNotifyEmail !== $wantsEmail) {
            UserActivityLogger::logEmailNotifications($user, $wantsEmail);
        }

        return back()->with('success', 'تم حفظ تفضيلات التنبيهات. يمكنك تخصيصها لاحقاً من إعدادات التنبيهات.');
    }
}
