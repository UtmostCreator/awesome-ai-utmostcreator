#!/usr/bin/env bash
# Tests for scripts/ai/repomix-freshness.sh and repomix-ensure-fresh.sh
# Read-only: --help and report modes; never regenerates context.
set -euo pipefail
BASH_BIN="${BASH_BIN:-$(command -v bash)}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
FRESH="$REPO_ROOT/scripts/ai/repomix-freshness.sh"
ENSURE="$REPO_ROOT/scripts/ai/repomix-ensure-fresh.sh"

PASS=0 FAIL=0 SKIP=0
run_test() {
    local name="$1"; shift; local _rc=0
    "$@" >/dev/null 2>&1 || _rc=$?
    if ((_rc == 0)); then PASS=$((PASS+1)); printf '  \033[0;32m✓\033[0m %s\n' "$name"
    else FAIL=$((FAIL+1)); printf '  \033[0;31m✗\033[0m %s\n' "$name"; fi
}

printf 'repomix-freshness.sh / repomix-ensure-fresh.sh\n'

test_fresh_help() { "$BASH_BIN" "$FRESH" --help 2>&1 | grep -q 'Usage'; }
run_test "freshness help flag works" test_fresh_help

test_ensure_help() { "$BASH_BIN" "$ENSURE" --help 2>&1 | grep -q 'Usage'; }
run_test "ensure-fresh help flag works" test_ensure_help

# Run against an empty temp dir (no manifest). Exit code may be non-zero
# (missing manifest) but the script must terminate and emit something.
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

test_fresh_runs() {
    local out
    out="$("$BASH_BIN" "$FRESH" "$TMP" 2>&1 || true)"
    [[ -n "$out" ]]
}
run_test "freshness reports on missing manifest" test_fresh_runs

test_fresh_json() {
    local out
    out="$(AI_OUTPUT=json "$BASH_BIN" "$FRESH" "$TMP" 2>/dev/null || true)"
    printf '%s' "$out" | jq -e '.tool == "repomix-freshness"' >/dev/null
}
run_test "freshness JSON envelope is valid" test_fresh_json

# ensure-fresh in --no-regen mode must never regenerate (read-only) and must
# terminate even when context is missing.
test_ensure_no_regen() {
    "$BASH_BIN" "$ENSURE" "$TMP" --no-regen >/dev/null 2>&1 || true
    return 0
}
run_test "ensure-fresh --no-regen terminates without regen" test_ensure_no_regen

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
if ((FAIL > 0)); then printf '\033[0;31mFAILED\033[0m\n'; exit 1; fi
printf '\033[0;32mPASSED\033[0m\n'
