#!/usr/bin/env bash
# Web-only: migrations, permissions, and governance content (Railway preDeploy + staging web boot).
#
# Do NOT seed NewsSeeder / CleanDemoDataSeeder here — they are opt-in and destructive.
# PartnerSeeder / MediaPhotoSeeder only upsert their own public-disk prefixes; they never
# delete storage/app/public/news/images (staff news uploads). See docs/deployment/public-media-storage.md
# VolunteerLeadersProgramCoverSeeder only sets image=images/programs/... for «قادة التطوع».
set -euo pipefail

if [[ "${RAILWAY_ENVIRONMENT_NAME:-}" == "staging" && "${APP_ENV:-}" != "staging" ]]; then
  echo "Refusing predeploy: APP_ENV must be staging (got: ${APP_ENV:-unset})." >&2
  exit 1
fi

if [[ "${APP_ENV:-}" == "production" && "${RAILWAY_ENVIRONMENT_NAME:-}" == "staging" ]]; then
  echo "Refusing predeploy: APP_ENV=production inside Railway staging environment." >&2
  exit 1
fi

php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --class=PrivacyPolicySeeder --force
php artisan db:seed --class=PrivacyPolicyGenderUpdateSeeder --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=GovernanceContentSeeder --force
php artisan db:seed --class=RegulationsSeeder --force
php artisan db:seed --class=VolunteerOpportunitySeeder --force
php artisan db:seed --class=PartnerSeeder --force
php artisan db:seed --class=MediaPhotoSeeder --force
php artisan db:seed --class=VolunteerLeadersProgramCoverSeeder --force
php artisan permission:cache-reset
php artisan cache:clear
