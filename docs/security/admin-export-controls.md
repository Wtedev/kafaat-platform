# ضوابط التصدير الإداري

## الصلاحيات

- `exports.beneficiaries.basic` — أعمدة عامة (بدون contact افتراضياً).
- `exports.beneficiaries.contact` — بريد، جوال، تاريخ ميلاد.

## التنفيذ

- `BeneficiaryExportAuthorization::filterAllowedColumnKeys()`
- `ProfilePolicy::export()` يتطلب `exports.beneficiaries.basic`
- كل تصدير يسجل `export.generated` في Audit Log

## ممنوع افتراضياً

- رقم الهوية الكامل
- مسارات CV أو signed URLs
