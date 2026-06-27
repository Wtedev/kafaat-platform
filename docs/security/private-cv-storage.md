# تخزين السيرة الذاتية الخاص

## القرص

- متغير البيئة: `PRIVATE_DOCUMENTS_DISK` (افتراضي محلي: `private_documents`).
- المسار: `storage/app/private-documents` — **غير** مرتبط بـ `public/storage`.
- في الإنتاج: استخدم Object Storage دائم (S3 أو ما يعادله) عبر نفس المتغير.

## الوصول

- لا URLs عامة ولا `Storage::url()`.
- تنزيل عبر مسارات محمية فقط:
  - `GET /portal/competency/cv/download` (المالك)
  - `GET /admin/beneficiaries/{user}/cv/download` (موظف بصلاحية)

## التسمية

- مسارات عشوائية: `cv/{uuid-prefix}/{uuid}.pdf`
- اسم التنزيل: `cv-BEN-{user_id}.pdf`

## Metadata

- جدول `user_documents` — لا يُعرض `path` أو `disk` في JSON.

## Headers

- `Content-Disposition: attachment`
- `X-Content-Type-Options: nosniff`
- `Cache-Control: private, no-store`

## النسخ الاحتياطي

- يجب نسخ قرص `private_documents` / bucket الإنتاج مع قاعدة البيانات.

## التشفير

- التشفير at-rest يعتمد على مزود التخزين في الإنتاج (S3 SSE إن مُفعّل).
