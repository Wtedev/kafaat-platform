# Production Environment Matrix

| Variable | Local (`.env.example`) | Production required |
|----------|------------------------|---------------------|
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | `true` | **`false`** |
| `APP_URL` | `http://localhost` | **`https://...`** |
| `APP_KEY` | empty | **required** |
| `FORCE_HTTPS` | `false` | **`true`** |
| `SESSION_DRIVER` | `file` | `database` or `redis` |
| `SESSION_SECURE_COOKIE` | `false` | **`true`** |
| `SESSION_HTTP_ONLY` | `true` | `true` |
| `SESSION_SAME_SITE` | `lax` | `lax` (verify flows) |
| `QUEUE_CONNECTION` | `database` | **`database`/`redis` + worker** |
| `CACHE_STORE` | `file` | `redis` recommended |
| `PRIVATE_DOCUMENTS_DISK` | `private_documents` | persistent volume |
| `IDENTITY_LOOKUP_KEY` | empty | **required** |
| `PRIVACY_EXPORT_TTL_DAYS` | `7` | approved value |
| `PRIVACY_RETENTION_*` | see `.env.example` | reviewed |
| `TRUSTED_HOSTS` | empty | production hostname(s) |
| `LOG_LEVEL` | `debug` | `warning` or `error` |
| `MAIL_MAILER` | `resend` | production provider |

Validation: `App\Services\Operations\ProductionEnvironmentValidator` (production only).

Health: `php artisan system:health`
