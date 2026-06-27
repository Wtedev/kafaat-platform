# Request ID

## Middleware

`AssignRequestId` — يُضاف إلى مجموعة `web`.

## السلوك

- ينشئ UUID لكل طلب.
- يقبل `X-Request-ID` من **Proxy موثوق فقط** إذا كان UUID صالحاً.
- يُخزّن في `$request->attributes['request_id']`.
- يُعاد في Response header `X-Request-ID`.
- يُربط بـ Audit Log وSecurity Log.
