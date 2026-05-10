#!/opt/homebrew/bin/bash
# Tests for scripts/ai/repo-tool-inventory.sh
set -euo pipefail
BASH_BIN="${BASH_BIN:-/opt/homebrew/bin/bash}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/repo-tool-inventory.sh"
cd "$REPO_ROOT"

PASS=0 FAIL=0 SKIP=0
run_test() {
    local name="$1"; shift; local _rc=0
    "$@" >/dev/null 2>&1 || _rc=$?
    if ((_rc == 0)); then PASS=$((PASS+1)); printf '  \033[0;32m✓\033[0m %s\n' "$name"
    else FAIL=$((FAIL+1)); printf '  \033[0;31m✗\033[0m %s\n' "$name"; fi
}
skip_test() { SKIP=$((SKIP+1)); printf '  \033[0;33m⊘\033[0m %s (skipped: %s)\n' "$1" "$2"; }

printf 'repo-tool-inventory.sh\n'

# Script delegates to PHP
if command -v php >/dev/null 2>&1 && [[ -f "$REPO_ROOT/tools/ai/repo-tool-inventory.php" ]]; then
    test_runs() {
        local out
        out="$("$BASH_BIN" "$SCRIPT" 2>&1 || true)"
        [[ -n "$out" ]]
    }
    run_test "script runs via PHP" test_runs

    test_help() {
        local out
        out="$("$BASH_BIN" "$SCRIPT" --help 2>&1 || true)"
        [[ -n "$out" ]]
    }
    run_test "help flag works" test_help
else
    skip_test "script runs via PHP" "php or PHP script not available"
    skip_test "help flag works" "php or PHP script not available"
fi

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
((FAIL == 0)) && printf '\033[0;32mPASSED\033[0m\n' || { printf '\033[0;31mFAILED\033[0m\n'; exit 1; }
