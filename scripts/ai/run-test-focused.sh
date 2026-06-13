#!/usr/bin/env bash
# run-test-focused.sh — run a FOCUSED PHPUnit selection (a --filter pattern or a
# single test file), bounded by a hard timeout. This is distinct from
# run-repo-tests.sh (whole suite) and ai-test-select.sh (selects, does not run).
#
# Usage:
#   scripts/ai/run-test-focused.sh --filter <Pattern>
#   scripts/ai/run-test-focused.sh <path/to/SomeTest.php>
#   scripts/ai/run-test-focused.sh <path/to/SomeTest.php> --filter <method>
#
# Read-only with respect to the repo (runs tests; writes no tracked files).

set -euo pipefail

# Early --introspect guard: emit this script's machine-readable JSON contract
# (static parse via sh-introspect) and exit before running anything. The target
# script is parsed as text, never executed.
if [[ "${1:-}" == "--introspect" ]]; then
    _ai_introspect_here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    _ai_introspect_tool="$_ai_introspect_here/../../tools/ai/sh-introspect.php"
    if [[ -f "$_ai_introspect_tool" ]] && command -v "${PHP_BIN:-php}" >/dev/null 2>&1; then
        exec env AI_OUTPUT=json "${PHP_BIN:-php}" "$_ai_introspect_tool" "${BASH_SOURCE[0]}"
    fi
fi

# Early --help/-h guard: emit the static introspector's compact help view.
if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    _ai_help_here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    _ai_help_tool="$_ai_help_here/../../tools/ai/sh-introspect.php"
    if [[ -f "$_ai_help_tool" ]] && command -v "${PHP_BIN:-php}" >/dev/null 2>&1; then
        exec "${PHP_BIN:-php}" "$_ai_help_tool" --format=help "${BASH_SOURCE[0]}"
    fi
fi

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

TEST_TIMEOUT="${TEST_TIMEOUT:-120}"
PHP_BIN="${PHP_BIN:-}"

if [[ "$#" -eq 0 ]]; then
    echo "ERROR: run-test-focused requires a --filter <pattern> or a test file path" >&2
    echo "       e.g. scripts/ai/run-test-focused.sh --filter ToolGatewayTest" >&2
    exit 2
fi

if [[ -z "$PHP_BIN" ]]; then
    if command -v php >/dev/null 2>&1; then
        PHP_BIN="php"
    elif command -v php.exe >/dev/null 2>&1; then
        PHP_BIN="php.exe"
    else
        PHP_BIN="php"
    fi
fi

if [[ ! -x vendor/bin/phpunit ]]; then
    echo "ERROR: vendor/bin/phpunit not found; run composer install first" >&2
    exit 1
fi

# Resolve a hard-timeout launcher so a hung/focused run is killed, not hung forever.
TIMEOUT_BIN=""
if command -v timeout >/dev/null 2>&1; then
    TIMEOUT_BIN="timeout"
elif command -v gtimeout >/dev/null 2>&1; then
    TIMEOUT_BIN="gtimeout"
fi

# Pass all caller args straight through to phpunit (a --filter pattern, a single
# file path, or both). phpunit itself validates them, keeping this wrapper thin.
set -- --configuration phpunit.xml.dist "$@"

echo "==> focused tests: phpunit $* (timeout ${TEST_TIMEOUT}s)"
if [[ -n "$TIMEOUT_BIN" ]]; then
    exec "$TIMEOUT_BIN" --kill-after=10s "$TEST_TIMEOUT" "$PHP_BIN" vendor/bin/phpunit "$@"
fi

echo "==> warn: no timeout/gtimeout binary; running WITHOUT a time limit" >&2
exec "$PHP_BIN" vendor/bin/phpunit "$@"
