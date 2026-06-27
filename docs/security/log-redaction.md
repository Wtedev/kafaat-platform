# تنقية البيانات في السجلات

## الخدمة

`App\Services\Security\SensitiveDataRedactor`

## مفاتيح محظورة (case-insensitive)

`password`, `otp`, `token`, `identity_number*`, `cv_path`, `signed_url`, `session`, ...

## حدود

- عمق JSON: 6
- عدد المفاتيح: 50
- طول النص: 500 حرف

## الاستخدام

- Audit metadata عبر `AuditMetadataRedactor`
- Security log metadata مباشرة
