# سجل التدقيق (Audit Log)

## الجدول

`audit_logs` — أُنشئ في المرحلة 4، موسّع في المرحلة 5.

## الخدمات

- `AuditLogService` — إنشاء السجلات.
- `AuditLogger` — واجهة مركزية مع `recordOrFail()` للعمليات fail-closed.

## Append-only

- `AuditLog` يمنع `update` و`delete` على مستوى النموذج.
- Filament: `AuditLogResource` للقراءة فقط.

## أحداث حساسة (أمثلة)

- `identity.full_viewed` / `identity.full_view_denied`
- `cv.downloaded` / `cv.download_denied`
- `export.generated`
