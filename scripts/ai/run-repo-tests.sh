#!/usr/bin/env bash
# Run the repository's existing test suites with parallel-first defaults.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

PARATEST_PROCS="${PARATEST_PROCS:-12}"
MAX_PARATEST_PROCS="${MAX_PARATEST_PROCS:-20}"
SUITE_TIMEOUT="${SUITE_TIMEOUT:-360}"
PHP_BIN="${PHP_BIN:-}"

if ! [[ "$PARATEST_PROCS" =~ ^[0-9]+$ ]]; then
    echo "ERROR: PARATEST_PROCS must be numeric" >&2
    exit 2
fi

if ((PARATEST_PROCS > MAX_PARATEST_PROCS)); then
    PARATEST_PROCS="$MAX_PARATEST_PROCS"
fi

if [[ -z "$PHP_BIN" ]]; then
    if command -v php.exe >/dev/null 2>&1; then
        PHP_BIN="php.exe"
    else
        PHP_BIN="php"
    fi
fi

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

JOBS=()
NAMES=()
LOGS=()

run_job() {
    local name="$1"
    shift
    local log="$TMP_DIR/${name//[^A-Za-z0-9_.-]/_}.log"

    echo "==> start: $name"
    ("$@") >"$log" 2>&1 &
    JOBS+=("$!")
    NAMES+=("$name")
    LOGS+=("$log")
}

run_job "php-paratest-root" \
    "$PHP_BIN" vendor/bin/paratest --configuration phpunit.xml.dist --processes="$PARATEST_PROCS" --runner=WrapperRunner

run_job "script-tests" \
    bash tests/scripts/ai/run-all-tests.sh

if command -v bats >/dev/null 2>&1 && [[ -d tests/shell ]]; then
    if file tests/shell/*.bats 2>/dev/null | grep -q 'CRLF'; then
        echo "==> skip: bats-shell-tests (CRLF checkout; normalize *.bats to LF to run under Bash/Bats)"
    else
    run_job "bats-shell-tests" bats tests/shell
    fi
else
    echo "==> skip: bats-shell-tests (bats not installed or tests/shell missing)"
fi

if [[ -f packages/ai-kit-tests/phpunit.xml.dist ]] && [[ -d packages/ai-kit-tests/tests ]]; then
    if find packages/ai-kit-tests/tests -type f -name '*Test.php' -print -quit | grep -q .; then
        run_job "php-paratest-package" \
            "$PHP_BIN" packages/ai-kit-tests/vendor/bin/paratest \
                --configuration packages/ai-kit-tests/phpunit.xml.dist \
                --processes="$PARATEST_PROCS" \
                --runner=WrapperRunner
    else
        echo "==> skip: php-paratest-package (no package Test.php files yet)"
    fi
fi

failures=0
for i in "${!JOBS[@]}"; do
    pid="${JOBS[$i]}"
    name="${NAMES[$i]}"
    log="${LOGS[$i]}"
    set +e
    wait "$pid"
    rc=$?
    set -e

    if ((rc == 0)); then
        echo "==> pass: $name"
    else
        echo "==> fail: $name (exit $rc)" >&2
        failures=$((failures + 1))
    fi

    echo "--- $name log ---"
    cat "$log"
    echo "--- end $name log ---"
done

echo "==> validators"
"$PHP_BIN" tools/ai/validate-ai-config.php
"$PHP_BIN" tools/ai/validate-ai-catalog.php
"$PHP_BIN" tools/ai/validate-generated-artifacts.php
"$PHP_BIN" tools/ai/validate-install-surface.php --strict

if ((failures > 0)); then
    echo "ERROR: $failures parallel test job(s) failed" >&2
    exit 1
fi

echo "==> all repo tests passed"
