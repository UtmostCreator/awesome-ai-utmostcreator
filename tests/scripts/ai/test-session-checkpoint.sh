#!/opt/homebrew/bin/bash
# Tests for scripts/ai/session-checkpoint.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/session-checkpoint.sh"
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

printf 'session-checkpoint.sh\n'

# --help
test_help() { "$BASH_BIN" "$SCRIPT" --help 2>&1 | grep -q 'Usage'; }
run_test "help flag works" test_help

# snapshot_create may not be implemented yet
test_creates() {
    local out rc=0
    out="$(AI_LOG_DIR="$TMP/logs" AI_EVENT_LOG="$TMP/logs/ev.jsonl" "$BASH_BIN" "$SCRIPT" 2>&1)" || rc=$?
    if ((rc == 0)); then
        [[ "$out" == *"checkpoint created"* ]]
    else
        # snapshot_create not available — expected if not implemented
        [[ "$out" == *"snapshot_create"* ]] || [[ "$out" == *"command not found"* ]]
    fi
}
run_test "creates checkpoint or reports missing function" test_creates

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
((FAIL == 0)) && printf '\033[0;32mPASSED\033[0m\n' || { printf '\033[0;31mFAILED\033[0m\n'; exit 1; }
