# Full Suite State Isolation — Root Causes and Fixes

## 1. PHPUnit subprocess memory limit (primary abort)

**Cause:** `php artisan test` executes `vendor/bin/phpunit` in a subprocess that did not inherit CLI `-d memory_limit=1G`.

**Affected:** Entire suite after ~65 tests (CV PDF validation allocates finfo buffers).

**Fix:** Added to `phpunit.xml`:

```xml
<ini name="memory_limit" value="1G"/>
```

**Production impact:** None. Test configuration only.

## 2. Login rate limiter cross-test leakage

**Cause:** `AppServiceProvider` registers `RateLimiter::for('login')` with `Limit::perMinute(5)->by($request->ip())`. Tests share IP `127.0.0.1`. Cache store in testing is `array`, which persists for the PHPUnit process.

**Affected:** Any test performing POST login after five failed attempts in earlier tests. Symptoms included 429 responses misread as session/CSRF failures (419).

**Fix:** `Tests\TestCase::resetConfiguredRateLimiters()` clears `md5('{limiter}{ip}')` keys for `login`, `register`, and `forgot-password` in both `setUp()` and `tearDown()`.

**Production impact:** None. Reset runs only in tests.

## 3. ExampleTest missing database schema

**Cause:** `/` queries published news; test did not migrate.

**Fix:** `RefreshDatabase` on `ExampleTest`.

## 4. TrainingProgramViewPresenterTest drift

**Cause:** Presenter gained rows ("التبعية للمسار") and always renders description with placeholder. Tests asserted stale indices/behavior.

**Fix:** Assert by row label; expect placeholder prose for empty description. See commit `test: align training program presenter coverage`.

## Isolation regression tests

`tests/Feature/TestIsolation/` verifies:

- Rate limiter counters reset between cases
- Config, Carbon, and Auth do not leak
- Permission grants do not appear on fresh users after DB refresh
