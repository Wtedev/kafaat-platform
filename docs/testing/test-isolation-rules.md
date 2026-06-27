# Test Isolation Rules

## Config

- Do not call `config([...])` or `Config::set()` without restoring in the same test, or rely on `Tests\TestCase` application refresh between tests.
- Never change production `.env` from tests.

## Time

- Always reset frozen time: `Carbon::setTestNow()` with no argument in `tearDown`, or use `$this->travelBack()`.
- Base `TestCase` resets Carbon in `setUp` and `tearDown`.

## Auth

- Do not assume guest state after a prior test called `actingAs()`.
- Base `TestCase` calls `Auth::logout()` in `setUp` and `tearDown`.

## Permissions (Spatie)

- Use `SeedsRbacRoles` or seed explicitly per test class.
- After mutating roles/permissions, `forgetCachedPermissions()` is invoked by base `TestCase`.
- Use real role names from `RbacCatalog` (`trainee`, `privacy_officer`, etc.).

## Rate limiter

- Login/register/forgot-password tests consume shared IP quota.
- Do not disable throttling globally.
- Base `TestCase` clears named limiter keys each test.

## Cache

- Testing uses `CACHE_STORE=array` (process-local). Do not assume cache is empty unless you clear specific keys or refresh the application.
- Prefer targeted `Cache::forget($key)` over `Cache::flush()` unless the key scope is documented.

## Fakes

- Call `Notification::fake()`, `Mail::fake()`, etc. inside the test method or class `setUp`, not in static/global hooks.
- Do not leave fakes registered in service providers for tests.

## Sessions and CSRF

- Do not use `withoutMiddleware()` globally.
- Portal tests that POST should use `$this->withSession(['otp_verified' => true])` via `ActsAsOtpVerifiedUser` or explicit session setup.
- CSRF is enforced in feature tests; Laravel testing helpers include tokens automatically when session is started.

## Database

- Feature tests touching models must use `RefreshDatabase` (or `DatabaseTransactions` with clear justification, not both blindly).
- Seed RBAC once per class via `SeedsRbacRoles` in `setUp`, not in static `beforeAll`.

## Storage

- Call `Storage::fake('disk')` in `setUp` of tests that upload files.
- Use `CreatesValidPdfUpload` for PDF content.

## Middleware

- Local `withoutMiddleware()` only inside the test that requires it; never in base `TestCase`.

## Memory

- Do not raise production memory limits for tests.
- PHPUnit memory is configured in `phpunit.xml` (`1G`).
