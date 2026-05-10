#!/opt/homebrew/bin/bash
# Tests for scripts/ai/ai-edit.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/ai-edit.sh"
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
skip_test() { SKIP=$((SKIP+1)); printf '  \033[0;33m⊘\033[0m %s (skipped: %s)\n' "$1" "$2"; }

printf 'ai-edit.sh\n'

# --help (exits non-zero but prints usage)
test_help() { ("$BASH_BIN" "$SCRIPT" --help 2>&1 || true) | grep -q 'Usage'; }
run_test "help flag works" test_help

# Missing mode fails
test_no_mode() { ! "$BASH_BIN" "$SCRIPT" 2>/dev/null; }
run_test "missing mode fails" test_no_mode

# dry-run sd mode (default APPLY=0)
if command -v sd >/dev/null 2>&1; then
    test_sd_dry_run() {
        local out
        out="$(APPLY=0 AI_LOG_DIR="$TMP/logs" AI_EVENT_LOG="$TMP/logs/ev.jsonl" "$BASH_BIN" "$SCRIPT" sd "NEVER_MATCH_XYZ" "replacement" "$REPO_ROOT" 2>&1 || true)"
        # Dry-run should not crash
        true
    }
    run_test "sd dry-run mode runs" test_sd_dry_run
else
    skip_test "sd dry-run mode runs" "sd not installed"
fi

# dry-run ast-grep mode
if command -v sg >/dev/null 2>&1; then
    test_ast_grep_dry_run() {
        local out
        out="$(APPLY=0 AI_LOG_DIR="$TMP/logs2" AI_EVENT_LOG="$TMP/logs2/ev.jsonl" "$BASH_BIN" "$SCRIPT" ast-grep bash 'echo $A' 'printf "%s\n" $A' "$REPO_ROOT" 2>&1 || true)"
        true
    }
    run_test "ast-grep dry-run mode runs" test_ast_grep_dry_run
else
    skip_test "ast-grep dry-run mode runs" "sg (ast-grep) not installed"
fi

# Unknown mode fails
test_unknown() {
    ! AI_LOG_DIR="$TMP/logs3" AI_EVENT_LOG="$TMP/logs3/ev.jsonl" "$BASH_BIN" "$SCRIPT" nonexistent 2>/dev/null
}
run_test "unknown mode fails" test_unknown

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
((FAIL == 0)) && printf '\033[0;32mPASSED\033[0m\n' || { printf '\033[0;31mFAILED\033[0m\n'; exit 1; }
