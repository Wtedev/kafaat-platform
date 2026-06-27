# Session and Authentication Hardening

## Implemented controls

- Session regeneration on successful login (`LoginController`)
- OTP required every login (`otp_verified` session flag)
- Login blocked for non-`active` account status and inactive accounts
- `url.intended` cleared on login (open redirect mitigation for Filament)
- Rate limits: login, register, forgot-password, OTP verify/resend, privacy requests, export download, certificate verify
- Security logs for failed/blocked login (no plaintext email in metadata)
- Anonymized accounts cannot access portal (`BeneficiaryPortal` middleware)
- Production: `SESSION_SECURE_COOKIE`, `SESSION_HTTP_ONLY`, `SESSION_SAME_SITE=lax`

## Sensitive re-verification

`SensitiveAccessVerification` + config TTL for identity reveal and export download.

## Not implemented

- Admin MFA (organizational decision — see deployment blockers)

## Tests

- `tests/Feature/Security/SessionHardeningTest.php`
- Phase 05 `AccessControlTest`
