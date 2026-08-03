#!/usr/bin/env bash
# Web-only: migrations, permissions, and governance content (Railway preDeploy + staging web boot).
#
# Runs on the WEB service only (railway.json / railway.toml preDeployCommand).
# Worker and scheduler configs intentionally omit preDeploy — do not add migrate
# there (avoids concurrent migrate races and slows non-HTTP services).
#
# Do NOT seed NewsSeeder / CleanDemoDataSeeder here — they are opt-in and destructive.
# PartnerSeeder / MediaPhotoSeeder only upsert their own public-disk prefixes; they never
# delete storage/app/public/news/images (staff news uploads). See docs/deployment/public-media-storage.md
# VolunteerLeadersProgramCoverSeeder only sets image=images/programs/... for «قادة التطوع».
# VolunteerLeadersProgramDatesSeeder sets start/end + registration window for «قادة التطوع»
#   (program 2026-08-03 → 2026-09-01; registration 2026-07-22 → 2026-08-03 inclusive).
# VolunteerLeadersProgramDescriptionSeeder sets canonical public description for «قادة التطوع» (نبذة + أسلوب التنفيذ؛ الشركاء في بطاقة الواجهة).
# VolunteerLeadersProgramDeliverySeeder sets delivery_mode=in_person (حضوري) + venue «بريدة - بيت الثقافة».

# VolunteerLeadersProgramPresentersSeeder clears program_presenters for «قادة التطوع» (public section removed).
# VolunteerLeadersProgramWhatsappSeeder sets women’s WhatsApp invite for «قادة التطوع» (male left unchanged).
# VolunteerLeadersProgramFemaleCapacitySeeder marks female seats full with capacity messaging (males remain open).
# FaeqoonProgramArchiveSeeder archives «فائقون وفائقات» (slug faykon-ofaykat) so redeploys do not republish it.
# NewsCoverAssetsSeeder only sets image=images/news/... for named articles (git-backed covers).
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
php artisan db:seed --class=VolunteerLeadersProgramDatesSeeder --force
php artisan db:seed --class=VolunteerLeadersProgramDescriptionSeeder --force
php artisan db:seed --class=VolunteerLeadersProgramDeliverySeeder --force
php artisan db:seed --class=VolunteerLeadersProgramPresentersSeeder --force
php artisan db:seed --class=VolunteerLeadersProgramWhatsappSeeder --force
php artisan db:seed --class=VolunteerLeadersProgramFemaleCapacitySeeder --force
php artisan db:seed --class=FaeqoonProgramArchiveSeeder --force
php artisan db:seed --class=NewsCoverAssetsSeeder --force
php artisan permission:cache-reset
php artisan cache:clear
