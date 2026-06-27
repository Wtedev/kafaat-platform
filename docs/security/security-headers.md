# Security Headers

Implemented via `App\Http\Middleware\ApplySecurityHeaders` on all web responses.

## Enforced headers

| Header | Value (default) |
|--------|-----------------|
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | camera/mic/geo/payment/usb disabled |
| `X-Frame-Options` | `SAMEORIGIN` |
| `Content-Security-Policy` | see `config/security.php` |
| `Strict-Transport-Security` | production HTTPS only |

## CSP notes

Default CSP allows Filament/Livewire/Vite requirements:

- `script-src 'self' 'unsafe-inline' 'unsafe-eval'`
- `style-src 'self' 'unsafe-inline'`

This is **not** a strict nonce-based CSP. Tightening CSP further is a deployment enhancement — test Filament admin thoroughly before enforcement changes.

Set `SECURITY_CSP_REPORT_ONLY=true` to measure violations without blocking.

## Configuration

Environment variables in `.env.example`:

- `FORCE_HTTPS`
- `SECURITY_HSTS_ENABLED`
- `SECURITY_CSP_REPORT_ONLY`
- `TRUSTED_HOSTS` (comma-separated; enables host validation when set)

## Tests

`tests/Feature/Security/SecurityHeadersTest.php`
