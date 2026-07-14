# حماية رقم الهوية / الإقامة

## التطبيع

- إزالة مسافات وشرطات.
- تحويل الأرقام العربية/الفارسية إلى لاتينية.
- النتيجة: 10 أرقام.

## التحقق

- يجب أن يكون الرقم **10 أرقام** بعد التطبيع (بدون checksum أو تحقق عبر خدمة خارجية).
- نوع الهوية (`national_id` / `iqama`) يُجمَع كحقل ملف شخصي فقط ولا يفرض بادئة أو تحقق خارجي.
- Service: `IdentityNumberService`.
- التفرّد: HMAC + فهرس فريد على `identity_number_lookup_hash`.

## التشفير

- القيمة الأصلية: `Crypt::encryptString()` → `identity_number_ciphertext`.
- **لا** encrypted cast على Model (تجنب فك تلقائي في JSON).

## HMAC للبحث والتفرد

```php
hash_hmac('sha256', $normalizedIdentity, IDENTITY_LOOKUP_KEY)
```

- مفتاح مستقل: `IDENTITY_LOOKUP_KEY` (ليس `APP_KEY`).
- unique index على `identity_number_lookup_hash`.
- **لا** SHA-256 عادي للتفرد.

## العرض

- `User::maskedIdentityNumber()` → `******1234`.
- Filament: نوع الهوية + مقنع فقط.
- **لا** عرض كامل في UI في هذه المرحلة.
- `IdentityNumberService::recordAuthorizedFullViewAttempt()` — نقطة extension للمرحلة 4.

## ما لا يُسجَّل

- الرقم plaintext.
- ciphertext أو hash في logs.
- الرقم في validation exception messages للمستخدم (رسالة عامة عند التكرار).

## البحث

- **لا** بحث جزئي بالهوية.
- بحث exact بالHMAC مؤجل لمرحلة الصلاحيات (`users.identity.view_full`).
