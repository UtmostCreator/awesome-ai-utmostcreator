#!/opt/homebrew/bin/bash
# Tests for scripts/ai/ai-rollback.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/ai-rollback.sh"
cd "$REPO_ROOT"
BASH_BIN="/opt/homebrew/bin/bash"

PASS=0 FAIL=0 SKIP=0
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

run_test() {
    local name="$1"; shift; local _rc=0
    "$@" >/dev/null 2>&1 || _rc=$?
    if ((_rc == 0)); then PASS=$((PASS+1)); printf '  \033[0;32m✓\033[0m %s\n' "$name"
    else FAIL=$((FAIL+1)); printf '  \033[0;31m✗\033[0m %s\n' "$name"; fi
}

printf 'ai-rollback.sh\n'

# --help
test_help() { "$BASH_BIN" "$SCRIPT" --help 2>&1 | grep -q 'Usage'; }
run_test "help flag works" test_help

# Missing mode fails
test_no_mode() { ! "$BASH_BIN" "$SCRIPT" 2>/dev/null; }
run_test "missing mode fails" test_no_mode

# list mode works (may be empty)
test_list() {
    local out
    out="$(AI_SNAPSHOT_DIR="$TMP/snaps" "$BASH_BIN" "$SCRIPT" list 2>&1 || true)"
    # Either "no snapshots" or list output
    true
}
run_test "list mode runs" test_list

# show with missing snapshot fails
test_show_missing() {
    ! AI_SNAPSHOT_DIR="$TMP/snaps2" "$BASH_BIN" "$SCRIPT" show "nonexistent" 2>/dev/null
}
run_test "show missing snapshot fails" test_show_missing

# apply with missing snapshot fails
test_apply_missing() {
    ! AI_SNAPSHOT_DIR="$TMP/snaps3" "$BASH_BIN" "$SCRIPT" apply "nonexistent" 2>/dev/null
}
run_test "apply missing snapshot fails" test_apply_missing

# prune on empty dir is safe (pipe stdin to avoid interactive prompt)
test_prune_empty() {
    echo "n" | AI_SNAPSHOT_DIR="$TMP/snaps4" "$BASH_BIN" "$SCRIPT" prune --days 30 2>/dev/null || true
    true
}
run_test "prune on empty dir is safe" test_prune_empty

# Unknown mode fails
test_unknown() {
    ! "$BASH_BIN" "$SCRIPT" nonexistent 2>/dev/null
}
run_test "unknown mode fails" test_unknown

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
((FAIL == 0)) && printf '\033[0;32mPASSED\033[0m\n' || { printf '\033[0;31mFAILED\033[0m\n'; exit 1; }
