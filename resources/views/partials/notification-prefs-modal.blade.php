{{-- نافذة منبثقة تظهر مرة واحدة لكل مستخدم لاختيار تفعيل إشعارات البريد --}}
<div id="notif-prefs-modal" class="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="notif-prefs-title">
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"></div>

    <div class="relative w-full max-w-md overflow-hidden rounded-3xl border border-slate-200/70 bg-white shadow-2xl">
        <div class="px-6 pt-6 text-right">
            <div class="mb-4 flex items-center justify-center">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl" style="background:#EAF2FA;color:#253B5B">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                </span>
            </div>
            <h2 id="notif-prefs-title" class="text-center text-lg font-bold text-gray-900">تفضيلات التنبيهات</h2>
            <p class="mt-2 text-center text-sm leading-relaxed text-gray-600">هل تود تفعيل إشعارات البريد الإلكتروني لمعرفة جديد كفاءات؟</p>
            <p class="mt-2 text-center text-xs text-gray-400">التنبيهات المهمة (مثل قبول تسجيلك) تظهر داخل المنصة دائماً. يمكنك تخصيص باقي الفئات لاحقاً.</p>
        </div>

        <div class="flex flex-col gap-2 px-6 pb-6 pt-5">
            <form method="POST" action="{{ route('notification-prefs.ack') }}">
                @csrf
                <input type="hidden" name="notify_email" value="1" />
                <button type="submit" class="w-full rounded-2xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#253B5B">
                    نعم، فعّل البريد للتنبيهات المهمة
                </button>
            </form>

            <form method="POST" action="{{ route('notification-prefs.ack') }}">
                @csrf
                <button type="submit" class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-medium text-slate-600 shadow-sm transition hover:bg-slate-50">
                    لا شكراً — داخل المنصة فقط
                </button>
            </form>

            <a href="{{ auth()->user()?->isPortalUser() ? route('portal.notifications.settings') : url('/admin/profile') }}" class="pt-1 text-center text-[11px] text-[#253B5B] hover:underline">
                تخصيص تفصيلي للتنبيهات
            </a>
        </div>
    </div>
</div>
