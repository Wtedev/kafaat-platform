# Full Suite Isolation Baseline (pre-fix)

Branch: `feature/privacy-compliance-phase-06-privacy-center-retention`  
Date: 2026-06-27  
Commits under test: `e893eb1`, `f4660fd`

## Run A — `php -d memory_limit=1G artisan test`

| Metric | Value |
| --- | --- |
| Passed | ~65 (suite aborted) |
| Failed | Fatal OOM + premature process exit |
| First failure mode | `Allowed memory size of 134217728 bytes exhausted` in `CvFileValidator.php:39` |
| Aborting test | `PrivacyPhase04\CandidatePoolConsentTest::test_granted_user_with_cv_appears_in_candidate_pool` |

**Observation:** `-d memory_limit=1G` applies to the Artisan parent process only. Laravel spawns PHPUnit as a child process that inherited the default **128M** limit from `php.ini`.

## Run B — repeat of Run A

Same outcome: OOM at ~65 tests, identical stack trace, identical aborting test.

## Run C — `php -d memory_limit=1G vendor/bin/phpunit` (direct)

| Metric | Value |
| --- | --- |
| Passed | 144 |
| Failed | 3 |
| Errors | 0 |

### Failures (stable, order-dependent)

1. `Tests\Unit\Filament\TrainingProgramViewPresenterTest::test_present_includes_core_overview_and_stats` — outdated row index after presenter added "التبعية للمسار".
2. `Tests\Unit\Filament\TrainingProgramViewPresenterTest::test_present_omits_empty_description_section` — presenter now renders placeholder prose (`—`) instead of omitting the section.
3. `Tests\Feature\ExampleTest::test_the_application_returns_a_successful_response` — home route queries `news` table without `RefreshDatabase`.

### Error types when suite continued past OOM (historical report)

When memory was sufficient but rate limiter state accumulated across tests:

- HTTP **419** (CSRF token mismatch / session not started)
- `assertSessionHasErrors('email')` failures on login tests
- `assertForbidden()` receiving **419** instead of **403**

These were **downstream symptoms** of login throttling (`throttle:login`, 5 attempts/minute per IP). Every HTTP test shares `127.0.0.1`, and the array cache driver persists counters for the PHPUnit process lifetime.

## Failure order consistency

| Run | First hard failure | Subsequent cascade |
| --- | --- | --- |
| Artisan (128M child) | OOM in Phase04 CV upload | Process death; no stable 60-failure list |
| PHPUnit direct (1G) | Presenter + ExampleTest only | No CSRF cascade |

## Pollution table (pre-fix)

| Polluting source | Affected tests | Leaked state | Evidence |
| --- | --- | --- | --- |
| PHPUnit subprocess default 128M memory | All tests after ~65 | Fatal OOM | `134217728 bytes exhausted` despite parent `-d memory_limit=1G` |
| Login rate limiter (`throttle:login`) | Login, OTP, portal POST tests | `md5('login127.0.0.1')` hit counter | 419/429 after 5+ failed logins in suite; passes when run alone |
| `ExampleTest` without migrations | Itself | Missing `news` table | SQL error on `/` |
| Outdated presenter assertions | Presenter unit tests only | N/A (test bug) | Row index mismatch |
