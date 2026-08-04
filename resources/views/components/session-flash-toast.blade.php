{{--
    One-shot session flash as a bottom floating toast.
    Clears the bottom-left «لدي مشكلة» FAB on mobile; bottom-center on larger screens.
--}}
@if (session('success') || session('error'))
<div
    class="session-flash"
    data-session-flash-toast
    dir="rtl"
>
    <div class="session-flash__stack" role="status" aria-live="polite" aria-atomic="true">
        @if (session('success'))
        <div
            class="session-flash__item session-flash__item--success"
            data-flash-item
            data-flash-variant="success"
        >
            <p class="session-flash__text">{{ session('success') }}</p>
            <button
                type="button"
                class="session-flash__dismiss"
                data-flash-dismiss
                aria-label="إغلاق"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        @endif

        @if (session('error'))
        <div
            class="session-flash__item session-flash__item--error"
            data-flash-item
            data-flash-variant="error"
        >
            <p class="session-flash__text">{{ session('error') }}</p>
            <button
                type="button"
                class="session-flash__dismiss"
                data-flash-dismiss
                aria-label="إغلاق"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        @endif
    </div>
</div>

<style>
.session-flash {
    position: fixed;
    z-index: 80;
    inset-inline: 0;
    /* Sit above the fixed FAB (bottom-left) so they never overlap. */
    bottom: calc(5.25rem + env(safe-area-inset-bottom, 0px));
    display: flex;
    justify-content: center;
    padding-inline: 1rem;
    pointer-events: none;
}

@media (min-width: 768px) {
    .session-flash {
        /* FAB stays left; centered toast clears it without extra lift. */
        bottom: calc(1.75rem + env(safe-area-inset-bottom, 0px));
    }
}

.session-flash__stack {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    width: min(28rem, 100%);
    pointer-events: none;
}

.session-flash__item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    width: 100%;
    pointer-events: auto;
    border-radius: 1rem;
    border: 1px solid transparent;
    padding: 0.85rem 1rem;
    font-size: 0.875rem;
    line-height: 1.55;
    text-align: right;
    box-shadow:
        0 10px 30px -12px rgba(26, 55, 90, 0.28),
        0 2px 10px -4px rgba(26, 55, 90, 0.12);
    backdrop-filter: blur(10px);
    opacity: 0;
    transform: translateY(1.1rem);
    animation: session-flash-enter 420ms cubic-bezier(0.22, 1, 0.36, 1) forwards;
}

.session-flash__item--success {
    border-color: #b8e0e2;
    background: rgba(230, 245, 246, 0.96);
    color: var(--brand-secondary, #1a9399);
}

.session-flash__item--error {
    border-color: #f5c4c0;
    background: rgba(253, 238, 237, 0.96);
    color: var(--brand-danger, #ec6056);
}

.session-flash__text {
    margin: 0;
    flex: 1;
    min-width: 0;
}

.session-flash__dismiss {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-top: 0.05rem;
    width: 1.75rem;
    height: 1.75rem;
    border: 0;
    border-radius: 999px;
    background: transparent;
    color: currentColor;
    opacity: 0.55;
    cursor: pointer;
    transition: opacity 160ms ease, background-color 160ms ease;
}

.session-flash__dismiss:hover,
.session-flash__dismiss:focus-visible {
    opacity: 1;
    background: rgba(0, 0, 0, 0.05);
    outline: none;
}

.session-flash__item.is-leaving {
    animation: session-flash-leave 280ms ease-in forwards;
    pointer-events: none;
}

@keyframes session-flash-enter {
    from {
        opacity: 0;
        transform: translateY(1.1rem);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes session-flash-leave {
    from {
        opacity: 1;
        transform: translateY(0);
    }
    to {
        opacity: 0;
        transform: translateY(0.65rem);
    }
}

@media (prefers-reduced-motion: reduce) {
    .session-flash__item,
    .session-flash__item.is-leaving {
        animation-duration: 1ms;
        animation-iteration-count: 1;
    }
}
</style>

<script>
(function () {
    var root = document.querySelector('[data-session-flash-toast]');
    if (!root) return;

    var AUTO_MS = 5200;

    function dismiss(item) {
        if (!item || item.classList.contains('is-leaving')) return;
        item.classList.add('is-leaving');
        var done = false;
        function remove() {
            if (done) return;
            done = true;
            item.remove();
            if (!root.querySelector('[data-flash-item]')) {
                root.remove();
            }
        }
        item.addEventListener('animationend', remove, { once: true });
        setTimeout(remove, 360);
    }

    root.querySelectorAll('[data-flash-item]').forEach(function (item) {
        var btn = item.querySelector('[data-flash-dismiss]');
        if (btn) {
            btn.addEventListener('click', function () {
                dismiss(item);
            });
        }
        setTimeout(function () {
            dismiss(item);
        }, AUTO_MS);
    });
})();
</script>
@endif
