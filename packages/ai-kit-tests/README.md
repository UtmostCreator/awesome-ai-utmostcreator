# ai-kit-tests

Internal PHP/PHPUnit, shell, and Bats test suite for the awesome-ai-utmostcreator AI kit. This package is **not shipped** by the `packages/ai-universal-rules` installer; it lives in the repository only so the kit author can verify the kit before publishing.

The PHP tests cover unit logic (`tests/Unit/`), CLI/integration contracts against the live repo (`tests/Integration/`), and advisor pipeline coverage (`tests/Advisor/`). The shell test runner lives in `scripts/ai/run-all-tests.sh` and exercises the `scripts/ai/*.sh` wrappers; Bats specs live under `shell/`. Fixture binaries used by the shell suites live under `fixtures/bin/`.

## How to run

```bash
cd packages/ai-kit-tests
composer install --no-interaction --prefer-dist
composer test:fast            # paratest, 12 workers (~19-21s)
composer test                 # serial PHPUnit (~60s)
composer test:profile         # emits ../../docs/ai/generated/phpunit-junit.xml
composer test:slow [N]        # ranks the N slowest tests from the last profile

# Shell suite (run from the repo root, not this package):
bash packages/ai-kit-tests/scripts/ai/run-all-tests.sh
```

The root `composer.json` delegates `composer test*` to this package, so `composer test:fast` from the repo root works too.
