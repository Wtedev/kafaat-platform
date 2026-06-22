{{-- نافذة عائمة تظهر مرة واحدة عند أول دخول لضبط تفضيل وصول التنبيهات للبريد --}}
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
            <p class="mt-2 text-center text-sm text-gray-600">تصلك التنبيهات دائماً داخل الموقع. هل ترغب باستقبال نسخة منها على بريدك الإلكتروني أيضاً؟</p>
        </div>

        <form method="POST" action="{{ route('portal.notifications.prefs-ack') }}" class="px-6 pb-6 pt-5">
            @csrf

            <label for="modal_notify_email" class="flex cursor-pointer items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3 text-right transition hover:border-[#253B5B]/30">
                <div>
                    <p class="text-sm font-semibold text-gray-900">إشعارات البريد الإلكتروني</p>
                    <p class="mt-0.5 text-xs text-gray-500">{{ auth()->user()->email }}</p>
                </div>
                <input type="checkbox" id="modal_notify_email" name="notify_email" value="1" checked class="h-5 w-5 shrink-0 rounded border-slate-300 text-[#253B5B] focus:ring-[#253B5B]/30" />
            </label>

            <div class="mt-5 flex flex-col gap-2">
                <button type="submit" class="w-full rounded-2xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#253B5B">
                    حفظ ومتابعة
                </button>
                <p class="text-center text-[11px] text-gray-400">يمكنك تغيير هذا لاحقاً من إعدادات التنبيهات.</p>
            </div>
        </form>
    </div>
</div>
