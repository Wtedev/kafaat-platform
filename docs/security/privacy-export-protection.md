# حماية تصدير البيانات الشخصية

## طبقات الحماية

1. **Private storage** — قرص `PRIVATE_DOCUMENTS_DISK` فقط.
2. **HTTPS** — مطلوب في الإنتاج.
3. **Authorization** — المالك فقط؛ UUID غير قابل للتخمين.
4. **Reauthentication** — `SensitiveAccessVerification` عند التقديم والتنزيل.
5. **Short expiry** — `expires_at` إلزامي للملف الجاهز.
6. **No-store headers** — `Cache-Control: private, no-store`.
7. **حذف الملف** — `privacy:purge-expired-exports` + Scheduler.

## بدون

- Public URLs أو signed URLs مخزّنة.
- إرسال ZIP بالبريد.
- ZipCrypto أو تشفير ضعيف.
- PII في Queue payload أو Audit metadata.

## Encryption at Rest

يعتمد على تكوين التخزين/الاستضافة (قرص مشفر أو Object Storage). لا يُفترض تشفير at-rest دون دليل من الإعداد.
