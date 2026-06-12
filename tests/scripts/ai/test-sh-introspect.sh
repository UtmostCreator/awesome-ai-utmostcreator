#!/usr/bin/env bash
# Tests for scripts/ai/sh-introspect.sh
set -euo pipefail
BASH_BIN="${BASH_BIN:-$(command -v bash)}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/sh-introspect.sh"
TARGET="scripts/ai/ai-search.sh"
cd "$REPO_ROOT"

PASS=0 FAIL=0 SKIP=0
run_test() {
    local name="$1"; shift; local _rc=0
    "$@" >/dev/null 2>&1 || _rc=$?
    if ((_rc == 0)); then PASS=$((PASS+1)); printf '  \033[0;32m✓\033[0m %s\n' "$name"
    else FAIL=$((FAIL+1)); printf '  \033[0;31m✗\033[0m %s\n' "$name"; fi
}
skip_test() { SKIP=$((SKIP+1)); printf '  \033[0;33m⊘\033[0m %s (skipped: %s)\n' "$1" "$2"; }

printf 'sh-introspect.sh\n'

# Script delegates to PHP; skip PHP-dependent tests gracefully when unavailable.
if command -v php >/dev/null 2>&1 && [[ -f "$REPO_ROOT/tools/ai/sh-introspect.php" ]]; then
    test_runs() {
        local out
        out="$("$BASH_BIN" "$SCRIPT" "$TARGET" 2>&1 || true)"
        [[ -n "$out" ]]
    }
    run_test "script runs via PHP" test_runs

    test_help() {
        local out _rc=0
        out="$("$BASH_BIN" "$SCRIPT" --help 2>&1)" || _rc=$?
        [[ "$_rc" -eq 0 && -n "$out" ]]
    }
    run_test "help flag works (exit 0, non-empty)" test_help

    if command -v jq >/dev/null 2>&1; then
        test_json_envelope() {
            local out
            out="$(AI_OUTPUT=json "$BASH_BIN" "$SCRIPT" "$TARGET" 2>/dev/null || true)"
            printf '%s' "$out" | jq -e '.schema == "ai.sh-introspect/v1"' >/dev/null \
                && printf '%s' "$out" | jq -e '.status == "ok"' >/dev/null \
                && printf '%s' "$out" | jq -e '.tool == "sh-introspect"' >/dev/null
        }
        run_test "JSON envelope schema/status/tool" test_json_envelope

        test_missing_path_error() {
            local out
            out="$(AI_OUTPUT=json "$BASH_BIN" "$SCRIPT" "no/such/file.sh" 2>/dev/null || true)"
            printf '%s' "$out" | jq -e '.status == "error"' >/dev/null
        }
        run_test "missing path yields status=error (json)" test_missing_path_error
    else
        skip_test "JSON envelope schema/status/tool" "jq not available"
        skip_test "missing path yields status=error (json)" "jq not available"
    fi
else
    skip_test "script runs via PHP" "php or PHP script not available"
    skip_test "help flag works (exit 0, non-empty)" "php or PHP script not available"
    skip_test "JSON envelope schema/status/tool" "php or PHP script not available"
    skip_test "missing path yields status=error (json)" "php or PHP script not available"
fi

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
((FAIL == 0)) && printf '\033[0;32mPASSED\033[0m\n' || { printf '\033[0;31mFAILED\033[0m\n'; exit 1; }
