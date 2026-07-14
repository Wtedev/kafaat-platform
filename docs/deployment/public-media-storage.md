# Public media storage (news images & Filament uploads)

## Problem

Filament staff uploads (news images, partner logos, media-center photos, avatars, etc.) are stored on Laravel’s **`public` disk**, rooted at:

```text
storage/app/public/   → served as  /storage/...
```

News images specifically live under:

```text
storage/app/public/news/images/
```

Railway containers use an **ephemeral filesystem**. A normal redeploy starts a new container, so anything written only under `storage/app/public` **is wiped** unless that path is durable. Database rows keep the paths; the files are gone → images appear to “disappear after every deployment”.

Deploy seeders (`PartnerSeeder`, `MediaPhotoSeeder`) rewrite only their own prefixes (`partners/`, `media/photos/library/`). They do **not** delete `news/images/`. `NewsSeeder` is **not** run on Railway boot.

## Required production setup (choose one)

### Option A — Railway Volume (preferred for local `public` disk)

Keep `PUBLIC_DISK_DRIVER=local` (default) and attach a persistent volume to the **web** service so uploads survive redeploys.

**Railway Console steps:**

1. Open the project → environment (**production** / **staging**) → web service (`kafaat-platform` or staging web).
2. Open the **Volumes** tab → **Add Volume**.
3. Mount path (must match the app):

   ```text
   /app/storage/app/public
   ```

   Railpack/Laravel app root is `/app`. This is the Laravel `public` disk root (`storage_path('app/public')`).
4. Create / attach the volume, wait for it to mount, then **redeploy** the web service.
5. Set environment variable on the same service (documents ops intent; silences ephemeral warning in `railway/run-web.sh`):

   ```text
   PUBLIC_STORAGE_PERSISTENT=1
   ```

6. Confirm after deploy:

   - Logs show `PUBLIC_STORAGE_PERSISTENT=1` (or a detected mount), not the ephemeral warning.
   - `public/storage` symlink exists (start script creates it via `php artisan storage:link`).
   - Upload a news image in Filament, redeploy, and verify the image URL (`/storage/news/images/...`) still loads.

Optional override if the volume must live elsewhere:

```text
PUBLIC_DISK_ROOT=/app/storage/app/public
```

(only needed when the mount path differs from the default.)

### Option B — S3-compatible public bucket

If you already have a public-readable object store (Railway Bucket / R2 / S3):

```text
PUBLIC_DISK_DRIVER=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=...
AWS_BUCKET=...                 # or AWS_PUBLIC_BUCKET for a dedicated public bucket
AWS_URL=https://...           # public base URL / CDN (optional but recommended)
PUBLIC_DISK_URL=https://...   # overrides public disk URL used by Laravel
AWS_ENDPOINT=...              # if required by the provider
AWS_USE_PATH_STYLE_ENDPOINT=true|false
```

Private CVs/exports stay on `PRIVATE_DOCUMENTS_DISK` (often a separate bucket). Do **not** point private documents at the public disk.

## Deploy script guarantees

`railway/run-web.sh`:

- Creates `storage/app/public/news/images` (and other public prefixes) without deleting existing files.
- **Never** runs `rm -rf` on `storage/app/public` or `news/`.
- Creates `public/storage` via `storage:link` (logs failures; does not hide them with `2>/dev/null`).
- Warns at boot when `PUBLIC_DISK_DRIVER=local` and neither `PUBLIC_STORAGE_PERSISTENT` nor a volume mount is detected.

`railway/predeploy.sh` does **not** run `NewsSeeder` / `CleanDemoDataSeeder`.

## App write path

| Surface | Disk | Directory |
|---------|------|-----------|
| News (Filament + `NewsImageSyncService`) | `public` | `news/images` |
| Partners / media library seeders | `public` | `partners/`, `media/photos/library/` |

All of these follow `PUBLIC_DISK_DRIVER` / volume configuration above.

## Related

- Staging private storage: `docs/deployment/railway-staging.md` (`PRIVATE_DOCUMENTS_DISK=s3`)
- Services inventory: `docs/deployment/railway-services.md`
