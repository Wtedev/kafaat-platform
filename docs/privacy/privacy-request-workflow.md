# سير عمل طلبات الخصوصية

## أنواع الطلبات

| النوع | القيمة | Workflow |
| --- | --- | --- |
| حذف حساب | `account_deletion` | تحقق هوية → مراجعة → خطة حذف → تنفيذ |
| وصول | `data_access` | تقديم → مراجعة → إكمال + استجابة منظمة |
| تصحيح | `data_correction` | تقديم → مراجعة → اعتماد → `applyCorrection` |

## الخدمة المركزية

كل الانتقالات عبر `PrivacyRequestService` — **لا** `$request->update(['status' => ...])` خارجها.

## Filament (`PrivacyRequestResource`)

- List: UUID، مستخدم، نوع، حالة، مهلة، معيَّن، متأخر.
- View: أقسام حسب النوع + Timeline.
- Actions: assign، start review، approve، partially approve، reject، complete access، apply correction، cancel (حسب النوع/الحالة).
- **لا** EditAction عامة.

## الصلاحيات

| Permission | الاستخدام |
| --- | --- |
| `privacy_requests.view` | عرض القائمة |
| `privacy_requests.assign` | تعيين |
| `privacy_requests.review` | بدء مراجعة، إكمال Access |
| `privacy_requests.approve` | اعتماد / جزئي |
| `privacy_requests.reject` | رفض |
| `privacy_requests.correction.execute` | تنفيذ تصحيح |
| `privacy_requests.execute` | تنفيذ حذف فقط |

## Events

Activity: `privacy.access_requested`, `privacy.correction_requested`, `privacy.request_cancelled`, `privacy.access_completed`, `privacy.correction_completed`, `privacy.request_rejected`

Audit: `privacy_request.created`, `privacy_request.assigned`, `privacy_request.review_started`, `privacy_request.approved`, `privacy_request.partially_approved`, `privacy_request.rejected`, `privacy_request.cancelled`, `privacy_access.response_created`, `privacy_correction.applied`

Security (فقط أحداث أمنية): `privacy_correction.verification_failed`, `privacy_request.rate_limited`, `privacy_request.access_denied`
