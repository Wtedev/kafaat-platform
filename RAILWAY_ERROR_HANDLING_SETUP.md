# Railway error handling setup

This document describes what is implemented in the **kafaat_platform** codebase for branded Arabic error pages, visit statistics, health checks, and how Railway / Cloudflare still need manual configuration at the edge.

## What was implemented in the project

### Arabic branded error pages

Self-contained Blade views under `resources/views/errors/`:

| View | Purpose |
|------|---------|
| `layout.blade.php` | Shared RTL brand shell (no Vite dependency) |
| `403`, `404`, `419`, `429`, `500`, `503` | Primary visitor statuses |
| `502`, `504`, `505` | Extra gateway-style pages already in brand |
| `4xx.blade.php`, `5xx.blade.php` | Fallbacks for uncommon client/server codes |

No stack traces, exception class names, or debug details are shown to visitors. Pages do not query the database.

### Visit statistics

- Table: `error_page_visits` (detailed rows; indexes on `status_code`, `created_at`, `user_id`).
- Legacy daily counters table `error_page_hits` remains for historical compatibility; new recording uses visits.
- Service: `App\Services\Operations\ErrorPageVisitRecorder`
  - Once-per-request flag
  - `try/catch` so DB failures never break the error response
  - Strips sensitive query keys (`password`, `token`, secrets, …)
  - Skips `/up`, JSON requests, and noisy static paths (favicon, source maps)
- Wiring:
  - Global middleware `RecordErrorPageHit`
  - `bootstrap/app.php` `reportable` (attach throwable) + `respond` (record) + `render` (4xx/5xx fallbacks)
  - Double inserts prevented by the request flag

### Admin page

Filament page **إحصاءات صفحات الأخطاء** at `/admin/error-page-stats`:

- Totals: filtered / today / 7d / 30d
- By status, top URLs, top 404s, daily chart
- Filters: date range, status, URL contains
- Paginated recent visits (truncated user agent; user name when present)
- **حذف السجلات القديمة** (confirm) → prune older than 90 days
- Access: existing `User::canAccessFilamentAdmin()` (admin/staff panel access)

### Health check

- Laravel built-in `health: '/up'` in `bootstrap/app.php`
- `railway.json` → `healthcheckPath: "/up"`
- No DB/Redis; not counted as an error visit

### Prune command + schedule

```bash
php artisan error-pages:prune --days=90
php artisan error-pages:prune --days=90 --dry-run
```

Scheduled daily at `04:30` Asia/Riyadh in `routes/console.php` (requires the scheduler worker already used by this project).

### Emergency static fallback

Folder `emergency-fallback/` (`index.html` + `styles.css`):

- Arabic RTL, no Laravel / DB / required JS
- **Cannot** replace Railway’s edge black page by itself — see Cloudflare section below

Also kept: `public/gateway-unavailable.html` for similar external-proxy use.

### Runtime (Railway web)

`railway/run-web.sh` already starts:

```bash
php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
```

No conflicting Dockerfile / Procfile / nixpacks start commands were added.

---

## Manual steps inside Railway

1. Open the **web** Laravel service.
2. **Settings → Deploy → Healthcheck Path** = `/up` (already in `railway.json`; confirm in the UI).
3. Confirm variables (do **not** put secrets in git):
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `APP_URL=https://…`
   - Database URL / Postgres linked as usual
4. Deploy or run a one-off release so migrations run via the existing `railway/predeploy.sh` (`php artisan migrate --force`).
5. Review Deployment Logs for migrate success.
6. Smoke test an unknown path (expect branded Arabic 404).
7. Open `/admin/error-page-stats` as admin/staff.

Do **not** paste production secrets into this repository.

---

## Cloudflare (architecture only — no fake config)

**Fact:** When the Railway container does not answer, Laravel never runs. Railway’s proxy may show «Application failed to respond». App Blade views and `emergency-fallback/` on the same volume cannot change that response.

**Pattern that works:**

```
Visitor → Cloudflare (always up) → origin Railway app
                │
                └─ on origin failure / timeout:
                     Custom Error / Worker returns
                     emergency-fallback/index.html
                     (from R2 / Pages / KV — not from the dead container)
```

Railway may also set `x-railway-fallback: true` on its interstitial; some operators key a Worker off that header. Exact Worker or Custom Error settings belong in your Cloudflare dashboard — this repo does not ship API tokens, zone IDs, or invented terraform.

Keep `emergency-fallback/` (or `public/gateway-unavailable.html`) published on a static host that survives app outages.

---

## Local verification commands

```bash
php artisan migrate
php artisan test --filter=ErrorPage
vendor/bin/pint --dirty
curl -i http://127.0.0.1:8000/up
curl -i http://127.0.0.1:8000/this-does-not-exist
php artisan error-pages:prune --days=90 --dry-run
```

---

## Related docs

- `docs/operations/custom-error-pages.md` — short operational summary
- `docs/operations/monitoring-and-alerting.md` — monitoring pointers
