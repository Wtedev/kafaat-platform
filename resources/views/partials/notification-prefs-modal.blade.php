{{-- نافذة منبثقة — أنماط مدمجة (تعمل في Filament وبوابة المستفيد بدون Tailwind) --}}
@once
<style>
    #notif-prefs-modal {
        position: fixed;
        inset: 0;
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        box-sizing: border-box;
        font-family: 'FF Shamel', system-ui, sans-serif;
    }
    #notif-prefs-modal *, #notif-prefs-modal *::before, #notif-prefs-modal *::after {
        box-sizing: border-box;
    }
    #notif-prefs-modal .npm-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        backdrop-filter: blur(4px);
    }
    #notif-prefs-modal .npm-card {
        position: relative;
        width: 100%;
        max-width: 26rem;
        overflow: hidden;
        border-radius: 1.25rem;
        border: 1px solid rgba(226, 232, 240, 0.9);
        background: #fff;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
        text-align: right;
        direction: rtl;
    }
    #notif-prefs-modal .npm-body {
        padding: 1.5rem 1.5rem 0;
    }
    #notif-prefs-modal .npm-icon-wrap {
        display: flex;
        justify-content: center;
        margin-bottom: 1rem;
    }
    #notif-prefs-modal .npm-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 3.5rem;
        height: 3.5rem;
        border-radius: 1rem;
        background: #e9eff6;
        color: #335483;
    }
    #notif-prefs-modal .npm-icon svg {
        width: 1.75rem;
        height: 1.75rem;
        flex-shrink: 0;
    }
    #notif-prefs-modal .npm-title {
        margin: 0;
        text-align: center;
        font-size: 1.125rem;
        font-weight: 700;
        color: #111827;
        line-height: 1.4;
    }
    #notif-prefs-modal .npm-text {
        margin: 0.5rem 0 0;
        text-align: center;
        font-size: 0.875rem;
        line-height: 1.6;
        color: #4b5563;
    }
    #notif-prefs-modal .npm-hint {
        margin: 0.5rem 0 0;
        text-align: center;
        font-size: 0.75rem;
        line-height: 1.5;
        color: #9ca3af;
    }
    #notif-prefs-modal .npm-actions {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        padding: 1.25rem 1.5rem 1.5rem;
    }
    #notif-prefs-modal .npm-btn {
        display: block;
        width: 100%;
        padding: 0.625rem 1.25rem;
        border-radius: 1rem;
        font-size: 0.875rem;
        font-weight: 600;
        line-height: 1.4;
        text-align: center;
        cursor: pointer;
        border: none;
        text-decoration: none;
        font-family: inherit;
    }
    #notif-prefs-modal .npm-btn-primary {
        background: #335483;
        color: #fff;
    }
    #notif-prefs-modal .npm-btn-primary:hover {
        opacity: 0.92;
    }
    #notif-prefs-modal .npm-btn-secondary {
        background: #fff;
        color: #475569;
        border: 1px solid #e2e8f0;
    }
    #notif-prefs-modal .npm-btn-secondary:hover {
        background: #f8fafc;
    }
    #notif-prefs-modal .npm-link {
        display: block;
        padding-top: 0.25rem;
        text-align: center;
        font-size: 0.6875rem;
        color: #335483;
        text-decoration: none;
    }
    #notif-prefs-modal .npm-link:hover {
        text-decoration: underline;
    }
</style>
@endonce

<div id="notif-prefs-modal" role="dialog" aria-modal="true" aria-labelledby="notif-prefs-title">
    <div class="npm-backdrop" aria-hidden="true"></div>

    <div class="npm-card">
        <div class="npm-body">
            <div class="npm-icon-wrap">
                <span class="npm-icon" aria-hidden="true">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                </span>
            </div>
            <h2 id="notif-prefs-title" class="npm-title">تفضيلات التنبيهات</h2>
            <p class="npm-text">هل تود تفعيل إشعارات البريد الإلكتروني لمعرفة جديد كفاءات؟</p>
            <p class="npm-hint">التنبيهات المهمة (مثل قبول تسجيلك) تظهر داخل المنصة دائماً. يمكنك تخصيص باقي الفئات لاحقاً.</p>
        </div>

        <div class="npm-actions">
            <form method="POST" action="{{ route('notification-prefs.ack') }}">
                @csrf
                <input type="hidden" name="notify_email" value="1" />
                <button type="submit" class="npm-btn npm-btn-primary">نعم، فعّل البريد للتنبيهات المهمة</button>
            </form>

            <form method="POST" action="{{ route('notification-prefs.ack') }}">
                @csrf
                <button type="submit" class="npm-btn npm-btn-secondary">لا شكراً — داخل المنصة فقط</button>
            </form>

            <a href="{{ auth()->user()?->isPortalUser() ? route('portal.notifications.settings') : url('/admin/profile') }}" class="npm-link">
                تخصيص تفصيلي للتنبيهات
            </a>
        </div>
    </div>
</div>
