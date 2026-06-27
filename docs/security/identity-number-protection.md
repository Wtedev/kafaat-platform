# حماية رقم الهوية / الإقامة

## التطبيع

- إزالة مسافات وشرطات.
- تحويل الأرقام العربية/الفارسية إلى لاتينية.
- النتيجة: 10 أرقام.

## التحقق

- checksum محلي (mod 10) على 10 خانات.
- `national_id` يبدأ بـ `1`؛ `iqama` يبدأ بـ `2`.
- Service: `IdentityNumberService`.

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
