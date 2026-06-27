# File Upload and Download Security

Consolidated reference for Phase 07 review. Prior implementation in Phases 04–06.

## Uploads (CV, avatar, correction docs)

- MIME validation from content (not extension alone)
- Size limits (`CV_MAX_SIZE_KB`)
- Private disk (`PRIVATE_DOCUMENTS_DISK`)
- Randomized storage paths — no `getClientOriginalName()` as path
- SVG/script uploads rejected where tested

## Downloads

- Authorization via policies + ownership checks
- CV/export: private disk streaming with `nosniff`, attachment disposition
- Export download: password re-verification + rate limit
- IDOR denied with security log (`privacy_export.download_denied`)

## ZIP / PDF

- Export ZIP: allowlisted domains in export DTOs
- PDF: remote resources restricted via library config (review mPDF/DOMPDF settings)

## Tests

- `tests/Feature/PrivacyPhase04/PrivateCvStorageTest.php`
- `tests/Feature/Security/PrivacyIdorTest.php`
- `tests/Feature/PrivacyPhase06/PersonalDataExportTest.php`

No new upload features added in Phase 07.
