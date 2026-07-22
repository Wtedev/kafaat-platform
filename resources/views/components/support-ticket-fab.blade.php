@php
    $user = auth()->user();
    $defaultName = old('name', $user?->name ?? '');
    $defaultEmail = old('email', $user?->email ?? '');
    $pageUrl = old('page_url', url()->current());
@endphp

<div class="support-fab" data-support-fab dir="rtl">
    <button
        type="button"
        class="support-fab__btn"
        data-support-open
        aria-expanded="false"
        aria-controls="support-fab-panel"
    >
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span>لدي مشكلة</span>
    </button>

    <div
        id="support-fab-panel"
        class="support-fab__panel"
        data-support-panel
        hidden
        role="dialog"
        aria-modal="true"
        aria-labelledby="support-fab-title"
    >
        <div class="support-fab__head">
            <div>
                <p class="support-fab__eyebrow">دعم كفاءات</p>
                <h2 id="support-fab-title" class="support-fab__title">لدي مشكلة</h2>
                <p class="support-fab__desc">صف المشكلة باختصار وسنراجعها عبر نظام التذاكر.</p>
            </div>
            <button type="button" class="support-fab__close" data-support-close aria-label="إغلاق">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        @if ($errors->any() && (old('subject') !== null || old('body') !== null || $errors->has('name') || $errors->has('email') || $errors->has('subject') || $errors->has('body')))
            <div class="support-fab__errors" data-support-has-errors>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('public.support-tickets.store') }}" class="support-fab__form">
            @csrf
            <input type="hidden" name="page_url" value="{{ $pageUrl }}">

            <label class="support-fab__field">
                <span>الاسم</span>
                <input type="text" name="name" value="{{ $defaultName }}" required maxlength="120" @if($user) readonly @endif>
            </label>

            <label class="support-fab__field">
                <span>البريد الإلكتروني</span>
                <input type="email" name="email" value="{{ $defaultEmail }}" required maxlength="191" @if($user) readonly @endif>
            </label>

            <label class="support-fab__field">
                <span>موضوع المشكلة</span>
                <input type="text" name="subject" value="{{ old('subject') }}" required maxlength="200" placeholder="مثال: تعذّر التسجيل في برنامج">
            </label>

            <label class="support-fab__field">
                <span>التفاصيل</span>
                <textarea name="body" rows="4" required maxlength="4000" placeholder="اكتب ماذا حدث وما الصفحة التي كنت فيها…">{{ old('body') }}</textarea>
            </label>

            <button type="submit" class="support-fab__submit">إرسال التذكرة</button>
        </form>
    </div>
</div>

<style>
.support-fab {
    position: fixed;
    z-index: 70;
    bottom: 1.25rem;
    left: 1.25rem;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.75rem;
}
.support-fab__btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border: 0;
    border-radius: 999px;
    padding: 0.8rem 1.1rem;
    background: #335483;
    color: #fff;
    font-size: 0.875rem;
    font-weight: 700;
    box-shadow: 0 14px 32px -12px rgba(51, 84, 131, 0.65);
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}
.support-fab__btn:hover {
    transform: translateY(-2px) scale(1.02);
    background: #2a466e;
    box-shadow: 0 18px 36px -12px rgba(51, 84, 131, 0.75);
}
.support-fab__panel {
    width: min(22rem, calc(100vw - 2rem));
    border-radius: 1.25rem;
    border: 1px solid rgba(51, 84, 131, 0.15);
    background: #fff;
    box-shadow: 0 24px 60px -28px rgba(15, 23, 42, 0.45);
    overflow: hidden;
}
.support-fab__head {
    display: flex;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 1rem 1rem 0.75rem;
    background: linear-gradient(135deg, rgba(51,84,131,0.08), transparent 60%);
}
.support-fab__eyebrow {
    margin: 0;
    font-size: 0.7rem;
    font-weight: 700;
    color: #335483;
}
.support-fab__title {
    margin: 0.2rem 0 0;
    font-size: 1.05rem;
    font-weight: 800;
    color: #111827;
}
.support-fab__desc {
    margin: 0.35rem 0 0;
    font-size: 0.78rem;
    line-height: 1.5;
    color: #6b7280;
}
.support-fab__close {
    border: 0;
    background: transparent;
    color: #6b7280;
    cursor: pointer;
    padding: 0.25rem;
}
.support-fab__form {
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
    padding: 0.75rem 1rem 1rem;
}
.support-fab__field {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #374151;
}
.support-fab__field input,
.support-fab__field textarea {
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    padding: 0.6rem 0.75rem;
    font-size: 0.84rem;
    font-weight: 500;
    color: #111827;
    background: #fff;
}
.support-fab__field input:focus,
.support-fab__field textarea:focus {
    outline: none;
    border-color: rgba(51,84,131,0.55);
    box-shadow: 0 0 0 3px rgba(51,84,131,0.12);
}
.support-fab__field input[readonly] {
    background: #f8fafc;
    color: #64748b;
}
.support-fab__submit {
    border: 0;
    border-radius: 0.85rem;
    padding: 0.75rem 1rem;
    background: #335483;
    color: #fff;
    font-size: 0.875rem;
    font-weight: 700;
    cursor: pointer;
}
.support-fab__errors {
    margin: 0 1rem;
    padding: 0.65rem 0.75rem;
    border-radius: 0.75rem;
    background: #fef2f2;
    color: #b91c1c;
    font-size: 0.75rem;
}
.support-fab__errors ul {
    margin: 0;
    padding-inline-start: 1rem;
}
@media (max-width: 640px) {
    .support-fab { left: 0.85rem; bottom: 0.85rem; }
}
</style>

<script>
(function () {
    var root = document.querySelector('[data-support-fab]');
    if (!root) return;
    var btn = root.querySelector('[data-support-open]');
    var panel = root.querySelector('[data-support-panel]');
    var closeBtn = root.querySelector('[data-support-close]');
    if (!btn || !panel) return;

    function openPanel() {
        panel.hidden = false;
        btn.setAttribute('aria-expanded', 'true');
    }
    function closePanel() {
        panel.hidden = true;
        btn.setAttribute('aria-expanded', 'false');
    }
    btn.addEventListener('click', function () {
        panel.hidden ? openPanel() : closePanel();
    });
    if (closeBtn) closeBtn.addEventListener('click', closePanel);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !panel.hidden) closePanel();
    });

    if (root.querySelector('[data-support-has-errors]')) {
        openPanel();
    }
})();
</script>
