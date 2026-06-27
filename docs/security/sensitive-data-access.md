# الوصول إلى البيانات الحساسة

## الهوية المقنعة

- افتراضياً: `User::maskedIdentityNumber()` في Filament والبوابة.
- لا يُعرض الرقم الكامل في الجداول أو التصدير.

## كشف الهوية الكامل

- **POST** `/admin/beneficiaries/{user}/identity/reveal`
- يتطلب: `beneficiaries.identity.view_full` + تأكيد كلمة المرور + سبب مهني.
- يُسجّل `identity.full_viewed` في Audit Log (fail-closed).
- الاستجابة: JSON قصير العمر مع `Cache-Control: no-store, private`.

## بيانات التواصل

- `beneficiaries.view_contact` لعرض البريد/الجوال كاملين.
- بدونها: `SensitiveContactMasker` في جداول Filament والتصدير.

## السيرة

- `downloadCv` policy على تنزيل الملفات.
- `beneficiary.cv.view` للعرض دون تنزيل.
