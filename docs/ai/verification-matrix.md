# Verification Matrix

Use the narrowest verification that proves the changed behavior, then escalate only when risk requires it.

## Test Prerequisite

Before running PHPUnit, ParaTest, or the repo-wide test runner from a fresh clone, run:

```bash
composer install
```

The PHP test and validation commands in this repository depend on Composer-managed binaries and autoloaded packages under `vendor/`. Without `composer install`, commands such as `vendor/bin/phpunit`, `vendor/bin/paratest`, and `bash scripts/ai/run-repo-tests.sh` will not function correctly.

## Parallel-First Test Entry Point

Use the single repo-wide runner when you need to prove all existing repository tests:

```bash
PARATEST_PROCS=12 bash scripts/ai/run-repo-tests.sh
```

- Runs root PHPUnit through ParaTest, not serial PHPUnit, when ParaTest is available.
- Runs script-level shell suites through `tests/scripts/ai/run-all-tests.sh`, which queues suites concurrently and applies per-suite timeouts.
- Runs Bats shell tests when `bats` is installed.
- Runs package-level ParaTest only when `packages/ai-kit-tests/tests` contains `*Test.php` files.
- Runs config/catalog/generated-artifact/install-surface validators after parallel test jobs.
- Cap `PARATEST_PROCS` at `20`; default is `12`.

## Test / Verification Command Inventory

| Command | Scope | Approx timeout / budget | Evidence |
| --- | --- | ---: | --- |
| `PARATEST_PROCS=12 bash scripts/ai/run-repo-tests.sh` | Single repo-wide parallel-first test runner: root ParaTest, shell suites, optional Bats, optional package ParaTest, validators | 360s budget | `scripts/ai/run-repo-tests.sh`, `tests/scripts/ai/run-all-tests.sh` |
| `php vendor/bin/paratest --configuration phpunit.xml.dist --processes=12 --runner=WrapperRunner` | Root ParaTest over `phpunit.xml.dist` / `tests/php` | ~20s expected; 90s budget | `composer.json`, `phpunit.xml.dist` |
| `php vendor/bin/phpunit --configuration phpunit.xml.dist` | Root serial PHPUnit over `tests/php`; use only for serial-order debugging or timing comparison | ~60s; 180s budget | `composer.json`, `phpunit.xml.dist` |
| `php vendor/bin/phpunit --configuration phpunit.xml.dist --log-junit=docs/ai/generated/phpunit-junit.xml` | Serial PHPUnit profile data for slow-test ranking | ~60s; 180s budget | `composer.json`, `tests/php/Support/list-slow-tests.php` |
| `php tests/php/Support/list-slow-tests.php [N]` | Reads prior JUnit profile and ranks slow tests | short; requires prior profile | `tests/php/Support/list-slow-tests.php` |
| `bash tests/scripts/ai/run-all-tests.sh [filter]` | Parallel shell harness for `tests/scripts/ai/test-*.sh`; per-suite timeout | 360s full budget | `tests/scripts/ai/run-all-tests.sh` |
| `bats tests/shell/` | Bats tests in `tests/shell/*.bats` when Bats is installed | 360s budget | `tests/shell/*.bats` |
| `bash scripts/ai/ai-verify.sh .` | Primary repo-local AI verification wrapper; lint/static checks by detected tool | depends on installed tools; keep `VERIFY_FULL=0` unless full proof needed | `scripts/ai/ai-verify.sh` |
| `php tools/ai/ai.php verify --json` / `--changed` | AI CLI verification: config, catalog, generated checks, advisor/install docs checks | writes generated artifacts/logs | `tools/ai/commands/verify.php`, `tools/ai/ai.php` |
| `php tools/ai/verify-full-install.php` | Full install verification sequence: preflight, package verify, adapter plan, install dry-run, validators, advisor, changed verify | broad; can be slow | `tools/ai/verify-full-install.php` |
| `php tools/ai/full-install-validation.php [flags]` | Broad validation with optional full verifier/PHPUnit | default `timeout-sec=600`, idle `180`; PHPUnit stage can be longer | `tools/ai/full-install-validation.php` |
