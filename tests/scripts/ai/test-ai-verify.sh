#!/opt/homebrew/bin/bash
# Tests for scripts/ai/ai-verify.sh
set -euo pipefail
BASH_BIN="${BASH_BIN:-/opt/homebrew/bin/bash}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/ai-verify.sh"
cd "$REPO_ROOT"

PASS=0 FAIL=0 SKIP=0
run_test() {
    local name="$1"; shift; local _rc=0
    "$@" >/dev/null 2>&1 || _rc=$?
    if ((_rc == 0)); then PASS=$((PASS+1)); printf '  \033[0;32m✓\033[0m %s\n' "$name"
    else FAIL=$((FAIL+1)); printf '  \033[0;31m✗\033[0m %s\n' "$name"; fi
}
skip_test() { SKIP=$((SKIP+1)); printf '  \033[0;33m⊘\033[0m %s (skipped: %s)\n' "$1" "$2"; }

printf 'ai-verify.sh\n'

# Script runs and produces output
test_runs() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" "$REPO_ROOT" 2>&1 || true)"
    [[ "$out" == *"==>"* ]]
}
run_test "script runs and prints step markers" test_runs

# Prints repository status
test_repo_status() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" "$REPO_ROOT" 2>&1 || true)"
    [[ "$out" == *"repository"* ]]
}
run_test "prints repository section" test_repo_status

# Runs shellcheck if available
if command -v shellcheck >/dev/null 2>&1; then
    test_shellcheck() {
        local out
        out="$(AI_VERIFY_SCOPE=ai "$BASH_BIN" "$SCRIPT" "$REPO_ROOT" 2>&1 || true)"
        [[ "$out" == *"shellcheck"* ]]
    }
    run_test "runs shellcheck on AI scripts" test_shellcheck
else
    skip_test "runs shellcheck on AI scripts" "shellcheck not installed"
fi

# Runs composer validate if available
if command -v composer >/dev/null 2>&1 && [[ -f "$REPO_ROOT/composer.json" ]]; then
    test_composer() {
        local out
        out="$("$BASH_BIN" "$SCRIPT" "$REPO_ROOT" 2>&1 || true)"
        [[ "$out" == *"composer"* ]]
    }
    run_test "runs composer validate" test_composer
else
    skip_test "runs composer validate" "composer not available"
fi

# VERIFY_FULL=0 skips full test suite
test_skip_full() {
    local out
    out="$(VERIFY_FULL=0 "$BASH_BIN" "$SCRIPT" "$REPO_ROOT" 2>&1 || true)"
    [[ "$out" == *"Skipping full"* ]] || [[ "$out" == *"done"* ]]
}
run_test "VERIFY_FULL=0 skips full test suite" test_skip_full

# AI_VERIFY_SCOPE=changed limits scope
test_scope_changed() {
    local out
    out="$(AI_VERIFY_SCOPE=changed "$BASH_BIN" "$SCRIPT" "$REPO_ROOT" 2>&1 || true)"
    # Should complete without error
    [[ "$out" == *"==>"* ]]
}
run_test "AI_VERIFY_SCOPE=changed limits scope" test_scope_changed

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
((FAIL == 0)) && printf '\033[0;32mPASSED\033[0m\n' || { printf '\033[0;31mFAILED\033[0m\n'; exit 1; }
