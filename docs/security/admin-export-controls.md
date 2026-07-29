# ضوابط التصدير الإداري

## الصلاحيات

- `exports.beneficiaries.basic` — أعمدة عامة (بدون contact افتراضياً).
- `exports.beneficiaries.contact` — بريد، جوال، تاريخ ميلاد.
- `exports.training` — تصدير مسجّلي البرامج التدريبية (مستقل عن `registrations.view`).

## التنفيذ

- `BeneficiaryExportAuthorization::filterAllowedColumnKeys()`
- `ProfilePolicy::export()` يتطلب `exports.beneficiaries.basic`
- `ProgramRegistrationExportAuthorization::canExport()` يتطلب `exports.training` + `viewOperational` على البرنامج
- كل تصدير يسجل `export.generated` في Audit Log (مفاتيح الأعمدة وعدد الصفوف فقط — بلا قيم شخصية)

## ممنوع افتراضياً

- رقم الهوية الكامل (يُصدَّر مقنّعاً فقط عند امتلاك صلاحية الهوية المقنّعة)
- مسارات CV أو signed URLs
- كلمات المرور / OTP / الرموز
