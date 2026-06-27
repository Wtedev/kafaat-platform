# تصدير البيانات الشخصية (Data Export)

## الفرق عن طلب الوصول

| Access (`data_access`) | Export (`data_export`) |
| --- | --- |
| استجابة منظمة داخل النظام | ملف ZIP خاص قابل للتنزيل |
| بدون ملف | Queue Job + Private Storage |
| `access_response` JSON | `privacy_export_files` |

## Workflow

1. المستفيد يقدّم الطلب من مركز الخصوصية (يلزم كلمة المرور + إعادة تحقق).
2. مسؤول الخصوصية يراجع ويعتمد.
3. Action «توليد ملف التصدير» ينقل الطلب إلى `processing` ويُرسل Job.
4. Job يبني ZIP على Private Disk.
5. الطلب يصبح `completed` والملف `ready`.
6. المستفيد ينزّل الملف قبل `expires_at`.

## محتويات ZIP

- `README-ar.txt`
- `manifest.json`
- `account.json`, `profile.json`, … (حسب وجود البيانات)
- `documents/cv.pdf` عند وجود CV نشطة

## مستبعد نهائياً

Password hashes، OTP، sessions، identity ciphertext/hash، audit/security logs، paths، signed URLs، بيانات مستخدمين آخرين.

## الهوية في التصدير

تظهر **مقنّعة** في `account.json` (قرار إداري موثّق).

## TTL

`PRIVACY_EXPORT_TTL_DAYS` (افتراضي 7 أيام). لا يوجد تنزيل بعد الانتهاء.
