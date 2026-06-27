<?php

return [

    'preview_freshness_hours' => max(1, (int) env('PRIVACY_RETENTION_PREVIEW_FRESHNESS_HOURS', 24)),

    'default_batch_size' => max(1, (int) env('PRIVACY_RETENTION_BATCH_SIZE', 100)),

    'queue_threshold' => max(1, (int) env('PRIVACY_RETENTION_QUEUE_THRESHOLD', 500)),

    'lock_ttl_seconds' => max(60, (int) env('PRIVACY_RETENTION_LOCK_TTL', 3600)),

    'manual_execute_max_items' => max(1, (int) env('PRIVACY_RETENTION_MANUAL_MAX_ITEMS', 50)),

    'otp_grace_period_days' => max(0, (int) env('PRIVACY_RETENTION_OTP_GRACE_DAYS', 1)),

    'session_lifetime_minutes' => (int) config('session.lifetime', 120),

    'protected_audit_actions' => [
        'account_deletion',
        'account_anonymization',
        'privacy_export.downloaded',
        'privacy_export.deleted',
        'cv.downloaded',
        'permissions.assign',
        'retention_policy.activated',
        'retention_policy.deactivated',
        'retention_run.started',
        'retention_run.completed',
    ],

    'protected_security_events' => [
        'retention.execution_denied',
        'retention.verification_failed',
        'retention.concurrent_run_blocked',
        'retention.unauthorized_resource_attempt',
    ],

];
