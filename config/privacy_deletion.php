<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Personal data resources included in deletion planning
    |--------------------------------------------------------------------------
    |
    | Default actions apply when no enabled retention policy overrides them.
    | Resources with retain_restricted and null retention_period_days require
    | an administrative retention decision before automated disposal.
    |
    */
    'resources' => [
        'users' => [
            'label' => 'حساب المستخدم',
            'default_action' => 'anonymize',
            'handler' => 'account_anonymization',
        ],
        'profiles' => [
            'label' => 'الملف الشخصي',
            'default_action' => 'anonymize',
            'handler' => 'profile_anonymization',
        ],
        'user_documents' => [
            'label' => 'وثائق المستخدم',
            'default_action' => 'delete',
            'handler' => 'user_documents',
        ],
        'candidate_pool_preferences' => [
            'label' => 'تفضيلات قاعدة المرشحين',
            'default_action' => 'anonymize',
            'handler' => 'candidate_pool_withdrawal',
        ],
        'candidate_pool_consent_events' => [
            'label' => 'أحداث موافقة قاعدة المرشحين',
            'default_action' => 'retain_restricted',
            'handler' => 'consent_events_retention',
        ],
        'privacy_policy_acknowledgements' => [
            'label' => 'إقرارات سياسة الخصوصية',
            'default_action' => 'retain_restricted',
            'handler' => 'policy_acknowledgements_retention',
        ],
        'sessions' => [
            'label' => 'الجلسات',
            'default_action' => 'delete',
            'handler' => 'authentication_data',
        ],
        'password_reset_tokens' => [
            'label' => 'رموز استعادة كلمة المرور',
            'default_action' => 'delete',
            'handler' => 'authentication_data',
        ],
        'email_verification_codes' => [
            'label' => 'رموز التحقق بالبريد',
            'default_action' => 'delete',
            'handler' => 'authentication_data',
        ],
        'in_app_notifications' => [
            'label' => 'إشعارات البوابة',
            'default_action' => 'delete',
            'handler' => 'notifications',
        ],
        'email_logs' => [
            'label' => 'سجل البريد',
            'default_action' => 'retain_restricted',
            'handler' => 'notifications',
        ],
        'program_registrations' => [
            'label' => 'تسجيلات البرامج',
            'default_action' => 'retain_restricted',
            'handler' => 'registrations_retention',
        ],
        'path_registrations' => [
            'label' => 'تسجيلات المسارات',
            'default_action' => 'retain_restricted',
            'handler' => 'registrations_retention',
        ],
        'volunteer_registrations' => [
            'label' => 'تسجيلات التطوع',
            'default_action' => 'retain_restricted',
            'handler' => 'registrations_retention',
        ],
        'attendance' => [
            'label' => 'سجلات الحضور',
            'default_action' => 'retain_restricted',
            'handler' => 'attendance_retention',
        ],
        'certificates' => [
            'label' => 'الشهادات',
            'default_action' => 'retain_restricted',
            'handler' => 'certificates_retention',
        ],
        'user_activity_logs' => [
            'label' => 'سجل نشاط المستفيد',
            'default_action' => 'delete',
            'handler' => 'activity_logs',
        ],
        'audit_logs' => [
            'label' => 'سجل التدقيق',
            'default_action' => 'retain_restricted',
            'handler' => 'audit_logs_retention',
        ],
        'security_logs' => [
            'label' => 'سجل الأحداث الأمنية',
            'default_action' => 'retain_restricted',
            'handler' => 'security_logs_retention',
        ],
        'privacy_export_files' => [
            'label' => 'ملفات تصدير الخصوصية',
            'default_action' => 'delete',
            'handler' => 'privacy_exports',
        ],
        'avatars' => [
            'label' => 'الصورة الشخصية',
            'default_action' => 'delete',
            'handler' => 'profile_anonymization',
        ],
        'cv_files' => [
            'label' => 'ملفات السيرة الذاتية',
            'default_action' => 'delete',
            'handler' => 'user_documents',
        ],
    ],

    'anonymized_display_name' => 'مستخدم محذوف',

    'anonymized_email_domain' => 'invalid.local',

];
