# Audit Log — الأساس (المرحلة 4)

## الجدول

`audit_logs` — append-only على مستوى التطبيق.

## أحداث هذه المرحلة

- `cv.uploaded`, `cv.replaced`, `cv.viewed`, `cv.downloaded`, `cv.deleted`
- `cv.migration_succeeded`, `cv.migration_failed`
- `candidate_pool.prompted`, `granted`, `declined`, `withdrawn`, `regranted`

## Redaction

- لا paths كاملة، لا signed URLs، لا محتوى سيرة، لا OTP.

## العرض

- Filament للمراجعة الإدارية (المرحلة 5 توسّع الصلاحيات).
