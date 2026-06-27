# مصفوفة الصلاحيات — المرحلة 5

> مصدر الحقيقة: `App\Services\Rbac\RbacCatalog`.

## مبدأ أقل صلاحية

- الصلاحيات الحساسة في `permissionsExcludedFromBroadRoles()` **لا تُمنح تلقائياً** حتى لـ `admin` و`technical_admin`:
  - `beneficiaries.identity.view_full`
  - `beneficiaries.identity.search_exact`
  - `security_logs.view_sensitive_metadata`

## مجموعات رئيسية

| المجموعة | أمثلة |
|---------|--------|
| بيانات المستفيد | `beneficiaries.view_basic`, `beneficiaries.view_contact`, `beneficiaries.update_*` |
| الهوية | `beneficiaries.identity.view_masked`, `beneficiaries.identity.view_full` |
| السيرة | `beneficiary.cv.view`, `beneficiary.cv.download`, `candidate_pool.cv.*` |
| التصدير | `exports.beneficiaries.basic`, `exports.beneficiaries.contact` |
| السجلات | `audit_logs.view`, `security_logs.view` |

## قرارات إدارية مطلوبة

- من يحصل على `beneficiaries.identity.view_full`؟
- من يرى `security_logs.view_sensitive_metadata`؟
- أي أدوار staff تحصل على `exports.beneficiaries.contact`؟
