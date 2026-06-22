<?php

namespace App\Http\Controllers;

use App\Services\Inbox\UserNotificationPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    /**
     * حفظ تفضيل إشعارات البريد من النافذة المنبثقة التي تظهر مرة واحدة لكل مستخدم.
     */
    public function acknowledge(Request $request, UserNotificationPreferences $preferences): RedirectResponse
    {
        $validated = $request->validate([
            'notify_email' => ['nullable', 'boolean'],
        ]);

        $wantsEmail = (bool) ($validated['notify_email'] ?? false);
        $settings = $preferences->normalizeFromRequest([
            'categories' => [
                'account' => [
                    'in_app' => true,
                    'email' => $wantsEmail,
                ],
            ],
        ]);

        $request->user()->forceFill([
            'notify_email' => $wantsEmail,
            'notification_settings' => $settings,
            'notification_prefs_set_at' => now(),
        ])->save();

        return back()->with('success', 'تم حفظ تفضيلات التنبيهات. يمكنك تخصيصها لاحقاً من إعدادات التنبيهات.');
    }
}
