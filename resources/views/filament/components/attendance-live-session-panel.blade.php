@php
    $expiresAtMs = $session?->expires_at ? $session->expires_at->getTimestamp() * 1000 : null;
@endphp

@if ($session && $session->isActive() && $expiresAtMs)
    <div
        class="fi-attendance-live-session-panel mb-4 rounded-xl border border-success-200 bg-success-50 p-4 dark:border-success-800 dark:bg-success-950/30"
        x-data="{
            expiresAt: {{ $expiresAtMs }},
            remaining: 0,
            format() {
                const m = Math.floor(this.remaining / 60);
                const s = this.remaining % 60;
                return m + ':' + String(s).padStart(2, '0');
            },
            tick() {
                this.remaining = Math.max(0, Math.floor((this.expiresAt - Date.now()) / 1000));
            },
            init() {
                this.tick();
                setInterval(() => this.tick(), 1000);
            }
        }"
        x-show="remaining > 0"
    >
        <p class="text-sm text-success-800 dark:text-success-200">
            <span class="font-semibold">جلسة حضور مباشرة مفتوحة</span>
            — يمكن للمستفيدين التسجيل من بوابتهم. الوقت المتبقي:
            <span class="font-mono font-bold" x-text="format()"></span>
        </p>
    </div>
@endif
