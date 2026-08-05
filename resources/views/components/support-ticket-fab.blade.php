@php
    $user = auth()->user();
    $isAuthenticated = $user !== null;
    $isPortalUser = $isAuthenticated && method_exists($user, 'isPortalUser') && $user->isPortalUser();
    $defaultName = old('name', $user?->name ?? '');
    $defaultEmail = old('email', $user?->email ?? '');
    $pageUrl = old('page_url', url()->current());
    $widgetStateUrl = $isPortalUser ? route('portal.support.widget.state') : null;
    $widgetStoreUrl = $isPortalUser ? route('portal.support.widget.store') : null;
    $historyUrl = $isPortalUser ? route('portal.support.index') : null;
@endphp

<div
    class="support-fab"
    data-support-fab
    data-auth="{{ $isPortalUser ? '1' : '0' }}"
    @if($widgetStateUrl) data-state-url="{{ $widgetStateUrl }}" @endif
    @if($widgetStoreUrl) data-store-url="{{ $widgetStoreUrl }}" @endif
    @if($historyUrl) data-history-url="{{ $historyUrl }}" @endif
    dir="rtl"
>
    <button
        type="button"
        class="support-fab__btn"
        data-support-open
        aria-expanded="false"
        aria-controls="support-fab-panel"
        aria-haspopup="dialog"
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
        <div class="support-fab__head" data-support-head>
            <div class="support-fab__head-text">
                <p class="support-fab__eyebrow">دعم كفاءات</p>
                <h2 id="support-fab-title" class="support-fab__title" data-support-title>لدي مشكلة</h2>
                <p class="support-fab__desc" data-support-desc>
                    @if ($isPortalUser)
                        افتح محادثة مع فريق الدعم أو تابع تذكرتك الحالية.
                    @else
                        صف المشكلة باختصار وسنراجعها عبر نظام التذاكر.
                    @endif
                </p>
            </div>
            <button type="button" class="support-fab__close" data-support-close aria-label="إغلاق">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="support-fab__body" data-support-body>
            @if ($errors->any() && (old('subject') !== null || old('body') !== null || $errors->has('name') || $errors->has('email') || $errors->has('subject') || $errors->has('body')))
                <div class="support-fab__errors" data-support-has-errors>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Guest / non-portal create form --}}
            @unless ($isPortalUser)
                <form method="POST" action="{{ route('public.support-tickets.store') }}" class="support-fab__form" data-support-guest-form>
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

                    <p class="support-fab__hint">بعد الإرسال ستصلك رقم التذكرة. متابعة المحادثة تتطلب تسجيل الدخول.</p>
                    <button type="submit" class="support-fab__submit">إرسال التذكرة</button>
                </form>
            @else
                <div class="support-fab__loading" data-support-loading hidden>
                    <span class="support-fab__spinner" aria-hidden="true"></span>
                    <span>جاري التحميل…</span>
                </div>
                <div class="support-fab__error-banner" data-support-error hidden role="alert"></div>

                <form class="support-fab__form" data-support-auth-form hidden>
                    <div class="support-fab__identity">
                        <span data-support-user-name>{{ $defaultName }}</span>
                        <span data-support-user-email>{{ $defaultEmail }}</span>
                    </div>
                    <label class="support-fab__field">
                        <span>موضوع المشكلة</span>
                        <input type="text" name="subject" data-support-subject required maxlength="200" placeholder="مثال: تعذّر التسجيل في برنامج" autocomplete="off">
                    </label>
                    <label class="support-fab__field">
                        <span>التفاصيل</span>
                        <textarea name="body" data-support-body-input rows="4" required maxlength="4000" placeholder="اكتب ماذا حدث…"></textarea>
                    </label>
                    <button type="submit" class="support-fab__submit" data-support-create-submit>إرسال وبدء المحادثة</button>
                </form>

                <div class="support-fab__chat" data-support-chat hidden>
                    <div class="support-fab__messages" data-support-messages tabindex="0" role="log" aria-live="polite" aria-relevant="additions"></div>
                    <div class="support-fab__closed" data-support-closed hidden>
                        <p data-support-closed-msg>تم إغلاق هذه التذكرة. إذا كنت بحاجة إلى مساعدة إضافية، يمكنك فتح تذكرة جديدة.</p>
                        <button type="button" class="support-fab__submit support-fab__submit--secondary" data-support-new-ticket>فتح تذكرة جديدة</button>
                        <a class="support-fab__history-link" data-support-history href="{{ $historyUrl }}">عرض سجل التذاكر</a>
                    </div>
                    <form class="support-fab__composer" data-support-composer hidden>
                        <label class="sr-only" for="support-fab-reply">اكتب ردك</label>
                        <textarea id="support-fab-reply" data-support-reply rows="2" maxlength="4000" placeholder="اكتب ردك…" required></textarea>
                        <button type="submit" class="support-fab__send" data-support-send aria-label="إرسال">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19V5m0 0l-7 7m7-7l7 7"/></svg>
                        </button>
                    </form>
                </div>
            @endunless
        </div>
    </div>
</div>

<style>
.support-fab {
    position: fixed;
    z-index: 70;
    bottom: max(1.25rem, env(safe-area-inset-bottom));
    left: max(1.25rem, env(safe-area-inset-left));
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
}
.support-fab__btn:focus-visible {
    outline: 3px solid rgba(251, 187, 46, 0.85);
    outline-offset: 2px;
}
.support-fab__panel {
    width: min(22.5rem, calc(100vw - 1.5rem));
    max-height: min(34rem, calc(100dvh - 5.5rem - env(safe-area-inset-bottom)));
    display: flex;
    flex-direction: column;
    border-radius: 1.25rem;
    border: 1px solid rgba(51, 84, 131, 0.15);
    background: #fff;
    box-shadow: 0 24px 60px -28px rgba(15, 23, 42, 0.45);
    overflow: hidden;
}
.support-fab__head {
    flex: 0 0 auto;
    display: flex;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.85rem 1rem 0.65rem;
    background: linear-gradient(135deg, rgba(51,84,131,0.1), transparent 65%);
    border-bottom: 1px solid rgba(51, 84, 131, 0.08);
}
.support-fab__eyebrow {
    margin: 0;
    font-size: 0.68rem;
    font-weight: 700;
    color: #335483;
}
.support-fab__title {
    margin: 0.15rem 0 0;
    font-size: 1rem;
    font-weight: 800;
    color: #111827;
}
.support-fab__desc {
    margin: 0.25rem 0 0;
    font-size: 0.74rem;
    line-height: 1.45;
    color: #6b7280;
}
.support-fab__close {
    border: 0;
    background: transparent;
    color: #6b7280;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 0.5rem;
}
.support-fab__close:focus-visible {
    outline: 2px solid #335483;
}
.support-fab__body {
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.support-fab__form {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding: 0.75rem 1rem 1rem;
    overflow-y: auto;
}
.support-fab__identity {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    padding: 0.55rem 0.7rem;
    border-radius: 0.75rem;
    background: #f8fafc;
    font-size: 0.75rem;
    color: #475569;
}
.support-fab__identity span:first-child {
    font-weight: 700;
    color: #1e293b;
}
.support-fab__field {
    display: flex;
    flex-direction: column;
    gap: 0.28rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #374151;
}
.support-fab__field input,
.support-fab__field textarea,
.support-fab__composer textarea {
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    padding: 0.55rem 0.7rem;
    font-size: 0.84rem;
    font-weight: 500;
    color: #111827;
    background: #fff;
    font-family: inherit;
    resize: vertical;
}
.support-fab__field input:focus,
.support-fab__field textarea:focus,
.support-fab__composer textarea:focus {
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
    padding: 0.7rem 1rem;
    background: #335483;
    color: #fff;
    font-size: 0.875rem;
    font-weight: 700;
    cursor: pointer;
}
.support-fab__submit:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}
.support-fab__submit--secondary {
    background: #1a9399;
}
.support-fab__hint {
    margin: 0;
    font-size: 0.7rem;
    color: #6b7280;
    line-height: 1.4;
}
.support-fab__errors,
.support-fab__error-banner {
    margin: 0.5rem 1rem 0;
    padding: 0.6rem 0.75rem;
    border-radius: 0.75rem;
    background: #fef2f2;
    color: #b91c1c;
    font-size: 0.75rem;
}
.support-fab__errors ul { margin: 0; padding-inline-start: 1rem; }
.support-fab__loading {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 1.5rem 1rem;
    color: #64748b;
    font-size: 0.84rem;
}
.support-fab__spinner {
    width: 1rem;
    height: 1rem;
    border: 2px solid #c5d4e4;
    border-top-color: #335483;
    border-radius: 50%;
    animation: support-fab-spin 0.7s linear infinite;
}
@keyframes support-fab-spin { to { transform: rotate(360deg); } }
.support-fab__chat {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
}
.support-fab__messages {
    flex: 1 1 auto;
    min-height: 12rem;
    max-height: 18rem;
    overflow-y: auto;
    padding: 0.75rem 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    background: linear-gradient(180deg, #f8fafc 0%, #fff 40%);
    -webkit-overflow-scrolling: touch;
}
.support-fab__bubble {
    max-width: 88%;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}
.support-fab__bubble--self { align-self: flex-start; }
.support-fab__bubble--support { align-self: flex-end; }
.support-fab__bubble--system {
    align-self: center;
    max-width: 95%;
    text-align: center;
}
.support-fab__bubble-meta {
    font-size: 0.65rem;
    color: #94a3b8;
    font-weight: 600;
}
.support-fab__bubble-body {
    padding: 0.55rem 0.7rem;
    border-radius: 0.9rem;
    font-size: 0.82rem;
    line-height: 1.5;
    color: #111827;
    white-space: pre-wrap;
    word-break: break-word;
}
.support-fab__bubble--self .support-fab__bubble-body {
    background: #335483;
    color: #fff;
    border-bottom-right-radius: 0.3rem;
}
.support-fab__bubble--support .support-fab__bubble-body {
    background: #e9eff6;
    color: #1e293b;
    border-bottom-left-radius: 0.3rem;
}
.support-fab__bubble--system .support-fab__bubble-body {
    background: #f1f5f9;
    color: #64748b;
    font-size: 0.75rem;
}
.support-fab__composer {
    flex: 0 0 auto;
    display: flex;
    gap: 0.45rem;
    align-items: flex-end;
    padding: 0.65rem 0.75rem calc(0.65rem + env(safe-area-inset-bottom));
    border-top: 1px solid #e5e7eb;
    background: #fff;
}
.support-fab__composer textarea {
    flex: 1 1 auto;
    min-height: 2.5rem;
    max-height: 6rem;
    resize: none;
}
.support-fab__send {
    flex: 0 0 auto;
    width: 2.5rem;
    height: 2.5rem;
    border: 0;
    border-radius: 0.75rem;
    background: #335483;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}
.support-fab__send:disabled { opacity: 0.55; cursor: not-allowed; }
.support-fab__closed {
    flex: 0 0 auto;
    padding: 0.75rem 1rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    border-top: 1px solid #e5e7eb;
    background: #f8fafc;
}
.support-fab__closed p {
    margin: 0;
    font-size: 0.78rem;
    color: #475569;
    line-height: 1.5;
}
.support-fab__history-link {
    font-size: 0.75rem;
    font-weight: 700;
    color: #335483;
    text-align: center;
}
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0,0,0,0);
    border: 0;
}
@media (max-width: 640px) {
    .support-fab { left: 0.75rem; bottom: max(0.75rem, env(safe-area-inset-bottom)); }
    .support-fab__panel {
        width: min(100vw - 1.25rem, 22.5rem);
        max-height: min(70dvh, calc(100dvh - 5rem));
    }
    .support-fab__messages { max-height: none; min-height: 10rem; }
}
@media (prefers-reduced-motion: reduce) {
    .support-fab__btn { transition: none; }
    .support-fab__btn:hover { transform: none; }
    .support-fab__spinner { animation: none; border-top-color: #335483; }
}
</style>

<script>
(function () {
    var root = document.querySelector('[data-support-fab]');
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    var btn = root.querySelector('[data-support-open]');
    var panel = root.querySelector('[data-support-panel]');
    var closeBtn = root.querySelector('[data-support-close]');
    if (!btn || !panel) return;

    var isAuth = root.getAttribute('data-auth') === '1';
    var stateUrl = root.getAttribute('data-state-url');
    var storeUrl = root.getAttribute('data-store-url');
    var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('input[name="_token"]')?.value
        || '';

    var lastFocus = null;
    var pollTimer = null;
    var ticket = null;
    var sending = false;
    var stickToBottom = true;

    var loadingEl = root.querySelector('[data-support-loading]');
    var errorEl = root.querySelector('[data-support-error]');
    var authForm = root.querySelector('[data-support-auth-form]');
    var chatEl = root.querySelector('[data-support-chat]');
    var messagesEl = root.querySelector('[data-support-messages]');
    var composer = root.querySelector('[data-support-composer]');
    var closedEl = root.querySelector('[data-support-closed]');
    var replyInput = root.querySelector('[data-support-reply]');
    var titleEl = root.querySelector('[data-support-title]');
    var descEl = root.querySelector('[data-support-desc]');

    function focusable() {
        return panel.querySelectorAll('a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])');
    }

    function openPanel() {
        lastFocus = document.activeElement;
        panel.hidden = false;
        btn.setAttribute('aria-expanded', 'true');
        var nodes = focusable();
        if (nodes.length) nodes[0].focus();
        if (isAuth) loadState();
    }

    function closePanel() {
        panel.hidden = true;
        btn.setAttribute('aria-expanded', 'false');
        stopPoll();
        if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
        else btn.focus();
    }

    function stopPoll() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function startPoll() {
        stopPoll();
        if (!ticket || !ticket.poll_url) return;
        pollTimer = setInterval(function () {
            if (panel.hidden || document.hidden) return;
            pollMessages();
        }, 8000);
    }

    function showError(msg) {
        if (!errorEl) return;
        errorEl.hidden = !msg;
        errorEl.textContent = msg || '';
    }

    function setMode(mode) {
        if (loadingEl) loadingEl.hidden = mode !== 'loading';
        if (authForm) authForm.hidden = mode !== 'create';
        if (chatEl) chatEl.hidden = mode !== 'conversation';
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderMessages(list, append) {
        if (!messagesEl) return;
        if (!append) messagesEl.innerHTML = '';
        (list || []).forEach(function (m) {
            if (messagesEl.querySelector('[data-mid="' + m.id + '"]')) return;
            var wrap = document.createElement('div');
            wrap.className = 'support-fab__bubble support-fab__bubble--' + (m.side || 'system');
            wrap.setAttribute('data-mid', m.id);
            wrap.innerHTML =
                '<div class="support-fab__bubble-meta">' + escapeHtml(m.label || '') +
                (m.time ? ' · ' + escapeHtml(m.time) : '') + '</div>' +
                '<div class="support-fab__bubble-body">' + escapeHtml(m.body || '') + '</div>';
            messagesEl.appendChild(wrap);
        });
        if (stickToBottom) {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }
    }

    function applyTicket(t, fullMessages) {
        ticket = t;
        if (titleEl) titleEl.textContent = (t.number || '') + (t.status_label ? ' · ' + t.status_label : '');
        if (descEl) descEl.textContent = t.subject || '';
        setMode('conversation');
        if (fullMessages) renderMessages(t.messages || [], false);
        var canReply = !!t.can_reply;
        if (composer) composer.hidden = !canReply;
        if (closedEl) {
            closedEl.hidden = canReply;
            var msg = closedEl.querySelector('[data-support-closed-msg]');
            if (msg && t.closed_message) msg.textContent = t.closed_message;
        }
        if (canReply) startPoll();
        else stopPoll();
    }

    function loadState() {
        if (!stateUrl) return;
        setMode('loading');
        showError('');
        fetch(stateUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) {
            if (!r.ok) throw new Error('failed');
            return r.json();
        }).then(function (data) {
            if (data.user) {
                var n = root.querySelector('[data-support-user-name]');
                var e = root.querySelector('[data-support-user-email]');
                if (n) n.textContent = data.user.name || '';
                if (e) e.textContent = data.user.email || '';
            }
            if (data.mode === 'conversation' && data.ticket) {
                applyTicket(data.ticket, true);
            } else {
                ticket = null;
                stopPoll();
                if (titleEl) titleEl.textContent = 'لدي مشكلة';
                if (descEl) descEl.textContent = 'صف المشكلة باختصار وسنفتح محادثة مع فريق الدعم.';
                setMode('create');
            }
        }).catch(function () {
            setMode('create');
            showError('تعذّر تحميل المحادثة. حاول مرة أخرى.');
        });
    }

    function pollMessages() {
        if (!ticket || !ticket.poll_url || !messagesEl) return;
        var nodes = messagesEl.querySelectorAll('[data-mid]');
        var after = 0;
        if (nodes.length) after = parseInt(nodes[nodes.length - 1].getAttribute('data-mid'), 10) || 0;
        var url = ticket.poll_url + (ticket.poll_url.indexOf('?') >= 0 ? '&' : '?') + 'after=' + after;
        fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { return r.ok ? r.json() : null; })
          .then(function (data) {
              if (!data || !data.ticket) return;
              var msgs = data.ticket.messages || [];
              if (msgs.length) renderMessages(msgs, true);
              ticket.can_reply = data.ticket.can_reply;
              ticket.status_label = data.ticket.status_label;
              ticket.closed_message = data.ticket.closed_message;
              if (titleEl) titleEl.textContent = (data.ticket.number || '') + (data.ticket.status_label ? ' · ' + data.ticket.status_label : '');
              if (composer) composer.hidden = !data.ticket.can_reply;
              if (closedEl) closedEl.hidden = !!data.ticket.can_reply;
              if (!data.ticket.can_reply) stopPoll();
          }).catch(function () {});
    }

    btn.addEventListener('click', function () {
        panel.hidden ? openPanel() : closePanel();
    });
    if (closeBtn) closeBtn.addEventListener('click', closePanel);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !panel.hidden) {
            e.preventDefault();
            closePanel();
        }
        if (e.key === 'Tab' && !panel.hidden) {
            var nodes = Array.prototype.slice.call(focusable());
            if (!nodes.length) return;
            var first = nodes[0];
            var last = nodes[nodes.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    });

    if (messagesEl) {
        messagesEl.addEventListener('scroll', function () {
            var nearBottom = messagesEl.scrollHeight - messagesEl.scrollTop - messagesEl.clientHeight < 48;
            stickToBottom = nearBottom;
        });
    }

    if (authForm) {
        authForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (sending || !storeUrl) return;
            var subject = root.querySelector('[data-support-subject]');
            var body = root.querySelector('[data-support-body-input]');
            var submitBtn = root.querySelector('[data-support-create-submit]');
            sending = true;
            if (submitBtn) submitBtn.disabled = true;
            showError('');
            fetch(storeUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    subject: subject ? subject.value : '',
                    body: body ? body.value : '',
                    page_url: window.location.href
                })
            }).then(function (r) {
                return r.json().then(function (data) {
                    if (!r.ok) {
                        var msg = (data.errors && (data.errors.subject || data.errors.body || data.errors.body)) || data.message;
                        throw new Error(Array.isArray(msg) ? msg[0] : (msg || 'تعذّر الإرسال'));
                    }
                    return data;
                });
            }).then(function (data) {
                if (data.ticket) {
                    applyTicket(data.ticket, true);
                    if (subject) subject.value = '';
                    if (body) body.value = '';
                }
            }).catch(function (err) {
                showError(err.message || 'تعذّر إنشاء التذكرة.');
            }).finally(function () {
                sending = false;
                if (submitBtn) submitBtn.disabled = false;
            });
        });
    }

    if (composer) {
        composer.addEventListener('submit', function (e) {
            e.preventDefault();
            if (sending || !ticket || !ticket.reply_url || !replyInput) return;
            var text = (replyInput.value || '').trim();
            if (!text) return;
            sending = true;
            var sendBtn = root.querySelector('[data-support-send]');
            if (sendBtn) sendBtn.disabled = true;
            fetch(ticket.reply_url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ body: text })
            }).then(function (r) {
                return r.json().then(function (data) {
                    if (!r.ok) {
                        var msg = (data.errors && data.errors.body) || data.message;
                        throw new Error(Array.isArray(msg) ? msg[0] : (msg || 'تعذّر إرسال الرد'));
                    }
                    return data;
                });
            }).then(function (data) {
                replyInput.value = '';
                stickToBottom = true;
                if (data.message) renderMessages([data.message], true);
                if (data.ticket) {
                    ticket.can_reply = data.ticket.can_reply;
                    if (composer) composer.hidden = !data.ticket.can_reply;
                    if (closedEl) closedEl.hidden = !!data.ticket.can_reply;
                }
            }).catch(function (err) {
                showError(err.message || 'تعذّر إرسال الرد.');
            }).finally(function () {
                sending = false;
                if (sendBtn) sendBtn.disabled = false;
                replyInput.focus();
            });
        });

        if (replyInput) {
            replyInput.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    composer.requestSubmit();
                }
            });
        }
    }

    var newTicketBtn = root.querySelector('[data-support-new-ticket]');
    if (newTicketBtn) {
        newTicketBtn.addEventListener('click', function () {
            ticket = null;
            stopPoll();
            if (titleEl) titleEl.textContent = 'تذكرة جديدة';
            if (descEl) descEl.textContent = 'افتح تذكرة جديدة مستقلة عن المحادثة المغلقة.';
            setMode('create');
            showError('');
            var subject = root.querySelector('[data-support-subject]');
            var body = root.querySelector('[data-support-body-input]');
            if (subject) { subject.value = ''; subject.focus(); }
            if (body) body.value = '';
        });
    }

    if (root.querySelector('[data-support-has-errors]')) {
        openPanel();
    }
})();
</script>
