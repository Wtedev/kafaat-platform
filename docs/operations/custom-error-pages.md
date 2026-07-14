# Custom error pages on Railway

## What visitors see

| Situation | Who serves the page | Branded Arabic page? | Counted in Filament stats? |
|-----------|---------------------|----------------------|----------------------------|
| Laravel returns 404 / 500 / 502 / 503 / 504 / 505 | App (`resources/views/errors/*`) | Yes | Yes |
| Container is down, cold, or times out at the edge | **Railway edge** («Application failed to respond») | **No** (platform interstitial) | **No** — Laravel never runs |

Railway does **not** natively support a custom static edge error page via `railway.toml` / `public/*.html`. When the upstream container does not respond, the proxy shows Railway’s own page before any app code runs.

## Mitigation (keep Railway’s page rare)

1. **Healthcheck** — `railway.json` uses `healthcheckPath: /up` so new deploys only receive traffic when Laravel answers.
2. **Overlap** — `overlapSeconds` keeps the previous deploy up during rollout.
3. **Restart on failure** — `restartPolicyType: ON_FAILURE`.
4. Avoid serverless sleep / long cold starts when possible.

Static branded fallback (for an **external** proxy/CDN only):

- `public/gateway-unavailable.html` — copy of the Arabic wait-and-reload look.

Wire it with Cloudflare (or similar) Custom Error when Railway’s response includes `x-railway-fallback: true`, serving this HTML from storage that stays up when the app is down. Serving it only from this Railway volume does **not** help when the container itself is unreachable.

## In-app counting

- Middleware `RecordErrorPageHit` increments daily rows in `error_page_hits` for HTML responses with status 404, 500–505 (tracked set).
- Staff page: **لوحة الإدارة → الأمان والامتثال → إحصاءات صفحات الأخطاء** (`/admin/error-page-stats`).
- Cards: 404 · 500 (includes 505) · تعذّر الاستجابة / بوابة (502+503+504).
- True Railway edge downtime cannot be beacons-counted from that page; there is no Railway logs API integration in this repo.
