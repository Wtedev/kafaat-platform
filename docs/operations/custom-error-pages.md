# Custom error pages on Railway

## What visitors see

| Situation | Who serves the page | Branded Arabic page? | Counted in Filament stats? |
|-----------|---------------------|----------------------|----------------------------|
| Laravel returns 403 / 404 / 419 / 429 / 500 / 503 (+ 502/504/505) | App (`resources/views/errors/*`) | Yes | Yes (`error_page_visits`) |
| Container is down, cold, or times out at the edge | **Railway edge** («Application failed to respond») | **No** (platform interstitial) | **No** — Laravel never runs |

Railway does **not** natively support a custom static edge error page via `railway.toml` / `public/*.html`. When the upstream container does not respond, the proxy shows Railway’s own page before any app code runs.

## Mitigation (keep Railway’s page rare)

1. **Healthcheck** — `railway.json` uses `healthcheckPath: /up` so new deploys only receive traffic when Laravel answers.
2. **Overlap** — `overlapSeconds` keeps the previous deploy up during rollout.
3. **Restart on failure** — `restartPolicyType: ON_FAILURE`.
4. Avoid serverless sleep / long cold starts when possible.

Static branded fallback (for an **external** proxy/CDN only):

- `emergency-fallback/` — standalone Arabic RTL page + CSS
- `public/gateway-unavailable.html` — same look for proxy storage

Wire it with Cloudflare (or similar) Custom Error / Worker when the origin fails, serving HTML from storage that stays up when the app is down. Serving it only from this Railway volume does **not** help when the container itself is unreachable.

Full setup notes: [`RAILWAY_ERROR_HANDLING_SETUP.md`](../../RAILWAY_ERROR_HANDLING_SETUP.md).

## In-app counting

- `ErrorPageVisitRecorder` + middleware / exception `respond` write one row per HTML error response (once per request).
- Sensitive query parameters are redacted; request bodies, cookies, and auth headers are never stored.
- Staff page: **لوحة الإدارة → الأمان والامتثال → إحصاءات صفحات الأخطاء** (`/admin/error-page-stats`).
- Prune: `php artisan error-pages:prune --days=90` (scheduled daily).
- True Railway edge downtime cannot be counted from inside the app.
