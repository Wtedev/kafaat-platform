# Full Suite Final Report

Date: 2026-06-27  
Branch: `fix/test-isolation-phase06`

## Baseline (pre-fix)

See [full-suite-isolation-baseline.md](./full-suite-isolation-baseline.md).

## Fixes applied

1. `phpunit.xml` — `memory_limit=1G` for PHPUnit subprocess.
2. `tests/TestCase.php` — reset Carbon, Auth, Spatie permission cache, login/register/forgot-password rate limiters.
3. `tests/Feature/ExampleTest.php` — `RefreshDatabase`.
4. `tests/Feature/TestIsolation/*` — regression tests.
5. `tests/Unit/Filament/TrainingProgramViewPresenterTest.php` — aligned with presenter.

## Full suite ×3 (`php artisan test`)

| Run | Passed | Failed | Duration |
| --- | --- | --- | --- |
| 1 | 159 | 0 | 34.91s |
| 2 | 159 | 0 | 35.02s |
| 3 | 159 | 0 | 35.36s |

Memory: ~125–127 MB peak (within 1G limit).

## Random order (`vendor/bin/phpunit --order-by=random`)

| Seed | Result |
| --- | --- |
| 1001 | OK (159 tests, 413 assertions) |
| 2002 | OK (159 tests, 413 assertions) |
| 3003 | OK (159 tests, 413 assertions) |

## Privacy permutations (`php artisan test …`)

| Order | Result |
| --- | --- |
| Phase04 → Phase05 → Phase06 | Pass |
| Phase06 → Phase04 → Phase05 | Pass |
| Phase05 → Phase06 → Phase04 | Pass |
| Baseline → Privacy phases | Pass |
| Privacy phases → Baseline | Pass |
| Login/OTP → Phase06 | Pass |
| Phase06 → Login/OTP | Pass |

## Unit tests

Included in full suite (159 total). Presenter tests pass after alignment.

## Presenter tests

- **Cause:** Test outdated after presenter added path-dependency row and placeholder description.
- **Type:** Test update (not presenter regression).
- **Change:** Label-based assertions; empty description expects `—` prose.

## Scope boundaries

No privacy center, account deletion workflow, CSRF, or middleware changes in production code.
