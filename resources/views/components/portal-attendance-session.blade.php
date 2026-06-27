@props([
    'statusUrl',
    'checkInUrl',
    'initialActive' => false,
    'initialExpiresAtMs' => null,
])

<div
    class="portal-attendance-session mb-6 rounded-2xl border border-[#b8e0e2] bg-[#e6f5f6] p-5 shadow-sm"
    data-portal-attendance-session
    data-status-url="{{ $statusUrl }}"
    data-check-in-url="{{ $checkInUrl }}"
    data-initial-active="{{ $initialActive ? '1' : '0' }}"
    data-initial-expires-at="{{ $initialExpiresAtMs ?? '' }}"
    @if (! $initialActive) hidden @endif
>
    <h2 class="mb-1 text-base font-semibold text-[#335483]">جلسة حضور مفتوحة</h2>
    <p class="mb-4 text-sm text-gray-700">
        يمكنك تسجيل حضورك الآن. الوقت المتبقي:
        <span class="font-mono text-lg font-bold text-[#335483]" data-portal-attendance-countdown>5:00</span>
    </p>
    <form method="POST" action="{{ $checkInUrl }}">
        @csrf
        <button type="submit" class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-semibold text-white" style="background:#335483">
            تسجيل حضوري لهذا اليوم
        </button>
    </form>
</div>

@once
    @push('scripts')
        <script>
            (function () {
                function formatRemaining(totalSeconds) {
                    var remaining = Math.max(0, totalSeconds);
                    var minutes = Math.floor(remaining / 60);
                    var seconds = remaining % 60;
                    return minutes + ':' + String(seconds).padStart(2, '0');
                }

                function initPortalAttendanceSession(root) {
                    if (!root || root.dataset.portalAttendanceInitialized === '1') {
                        return;
                    }

                    root.dataset.portalAttendanceInitialized = '1';

                    var statusUrl = root.dataset.statusUrl;
                   

                    var countdownEl = root.querySelector('[data-portal-attendance-countdown]');
                    var expiresAtMs = root.dataset.initialExpiresAt ? parseInt(root.dataset.initialExpiresAt, 10) : null;
                    var pollTimer = null;
                    var tickTimer = null;

                    function setVisible(active) {
                        root.hidden = !active;
                    }

                    function updateCountdownDisplay() {
                        if (!countdownEl || !expiresAtMs) {
                            return;
                        }

                        var remaining = Math.max(0, Math.floor((expiresAtMs - Date.now()) / 1000));
                        countdownEl.textContent = formatRemaining(remaining);

                        if (remaining <= 0) {
                            setVisible(false);
                            stopTimers();
                        }
                    }

                    function stopTimers() {
                        if (pollTimer) {
                            clearInterval(pollTimer);
                            pollTimer = null;
                        }
                        if (tickTimer) {
                            clearInterval(tickTimer);
                            tickTimer = null;
                        }
                    }

                    function applySession(data) {
                        if (!data || !data.active || !data.expires_at_ms) {
                            setVisible(false);
                            stopTimers();
                            return;
                        }

                        expiresAtMs = data.expires_at_ms;
                        setVisible(true);
                        updateCountdownDisplay();

                        if (!tickTimer) {
                            tickTimer = setInterval(updateCountdownDisplay, 1000);
                        }
                    }

                    function fetchStatus() {
                        fetch(statusUrl, {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                        })
                            .then(function (response) {
                                if (!response.ok) {
                                    throw new Error('status');
                                }

                                return response.json();
                            })
                            .then(applySession)
                            .catch(function () {});
                    }

                    if (root.dataset.initialActive === '1' && expiresAtMs) {
                        applySession({ active: true, expires_at_ms: expiresAtMs });
                    }

                    fetchStatus();
                    pollTimer = setInterval(fetchStatus, 3000);
                }

                function bootPortalAttendanceSessions() {
                    document.querySelectorAll('[data-portal-attendance-session]').forEach(initPortalAttendanceSession);
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', bootPortalAttendanceSessions);
                } else {
                    bootPortalAttendanceSessions();
                }
            })();
        </script>
    @endpush
@endonce
