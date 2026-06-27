# سجل الأمان (Security Log)

## الجدول

`security_logs` — منفصل عن Audit Log وActivity Log.

## الحقول

- `event`, `result`, `severity`, `request_id`, `identifier_hash` (HMAC للبريد عند الحاجة)
- **لا** يُخزّن OTP أو كلمة مرور أو بريد كامل.

## أحداث مُفعّلة

- `auth.login_succeeded` / `auth.login_failed` / `auth.login_blocked`
- `auth.logout`
- `auth.otp_verified` / `auth.otp_failed` / `auth.otp_expired` / `auth.otp_locked`

## Filament

`SecurityLogResource` — قراءة فقط، صلاحية `security_logs.view`.
