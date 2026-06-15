#!/usr/bin/env bash
# Run the repository's existing test suites with parallel-first defaults.

set -euo pipefail

# Early --introspect guard: emit this script's machine-readable JSON contract
# (static parse via sh-introspect) and exit before running any logic (including
# the test suites). The target script is parsed as text, never executed.
if [[ "${1:-}" == "--introspect" ]]; then
    _ai_introspect_here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    _ai_introspect_tool="$_ai_introspect_here/../../tools/ai/sh-introspect.php"
    if [[ -f "$_ai_introspect_tool" ]] && command -v "${PHP_BIN:-php}" >/dev/null 2>&1; then
        exec env AI_OUTPUT=json "${PHP_BIN:-php}" "$_ai_introspect_tool" "${BASH_SOURCE[0]}"
    fi
fi

# Early --help/-h guard: emit this script's human-readable contract (the static
# introspector's compact --format=help view) and exit before running any tests.
if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    _ai_help_here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    _ai_help_tool="$_ai_help_here/../../tools/ai/sh-introspect.php"
    if [[ -f "$_ai_help_tool" ]] && command -v "${PHP_BIN:-php}" >/dev/null 2>&1; then
        exec "${PHP_BIN:-php}" "$_ai_help_tool" --format=help "${BASH_SOURCE[0]}"
    fi
fi

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT" || exit 1

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
    # Prefer a native (Unix) php. Under WSL the Windows php.exe is on PATH but
    # cannot resolve the \\wsl.localhost\ UNC working directory, so CMD.EXE
    # rejects it and paratest workers crash. Only fall back to php.exe when no
    # native php exists (e.g. Git Bash on real Windows).
    if command -v php >/dev/null 2>&1; then
        PHP_BIN="php"
    elif command -v php.exe >/dev/null 2>&1; then
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

# Resolve a hard-timeout launcher once. Each job is bounded by SUITE_TIMEOUT so a
# hung suite is killed and reported as failed instead of blocking the run forever.
# Falls back to running unbounded only when no timeout binary is available.
TIMEOUT_BIN=""
if command -v timeout >/dev/null 2>&1; then
    TIMEOUT_BIN="timeout"
elif command -v gtimeout >/dev/null 2>&1; then
    TIMEOUT_BIN="gtimeout"
fi

run_job() {
    local name="$1"
    shift
    local log="$TMP_DIR/${name//[^A-Za-z0-9_.-]/_}.log"

    echo "==> start: $name"
    if [[ -n "$TIMEOUT_BIN" ]]; then
        ("$TIMEOUT_BIN" --kill-after=10s "$SUITE_TIMEOUT" "$@") >"$log" 2>&1 &
    else
        echo "==> warn: no timeout/gtimeout binary; running '$name' WITHOUT a time limit" >&2
        ("$@") >"$log" 2>&1 &
    fi
    JOBS+=("$!")
    NAMES+=("$name")
    LOGS+=("$log")
}

# Inner $1/$2/$PHP_BIN are expanded by the inner `bash -lc`, not the outer shell.
# shellcheck disable=SC2016
run_job "php-root-tests" \
    bash -lc '
if [[ -x vendor/bin/paratest ]]; then
    exec "$1" vendor/bin/paratest --configuration phpunit.xml.dist --processes="$2" --runner=WrapperRunner
fi

if [[ -x vendor/bin/phpunit ]]; then
    exec "$1" vendor/bin/phpunit --configuration phpunit.xml.dist
fi

echo "ERROR: neither vendor/bin/paratest nor vendor/bin/phpunit is available" >&2
exit 1
' _ "$PHP_BIN" "$PARATEST_PROCS"

run_job "script-tests" \
    bash tests/scripts/ai/run-all-tests.sh

bats_path="$(command -v bats 2>/dev/null || true)"
if [[ -n "$bats_path" ]] && [[ -d tests/shell ]]; then
    if file tests/shell/*.bats 2>/dev/null | grep -q 'CRLF'; then
        echo "==> skip: bats-shell-tests (CRLF checkout; normalize *.bats to LF to run under Bash/Bats)"
    elif [[ "$bats_path" == /mnt/c/* || "$bats_path" == *.exe ]]; then
        # A Windows-hosted bats on PATH (common under WSL) has a CRLF shebang
        # and cannot run Linux test files; skip instead of failing the suite.
        echo "==> skip: bats-shell-tests (Windows bats at $bats_path is unusable under WSL; install a native bats)"
    elif ! bats --version >/dev/null 2>&1; then
        echo "==> skip: bats-shell-tests (bats smoke check failed; install a native bats)"
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
