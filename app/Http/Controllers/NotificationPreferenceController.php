<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    /**
     * حفظ تفضيل إشعارات البريد من النافذة المنبثقة التي تظهر مرة واحدة لكل مستخدم.
     */
    public function acknowledge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notify_email' => ['nullable', 'boolean'],
        ]);

        $request->user()->forceFill([
            'notify_email' => (bool) ($validated['notify_email'] ?? false),
            'notification_prefs_set_at' => now(),
        ])->save();

        return back()->with('success', 'تم حفظ تفضيل إشعارات البريد.');
    }
}
