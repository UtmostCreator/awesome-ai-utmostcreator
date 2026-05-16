#!/usr/bin/env bash
# Tests for scripts/ai/install-mandatory-tools.sh
set -euo pipefail
BASH_BIN="${BASH_BIN:-$(command -v bash)}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/install-mandatory-tools.sh"
cd "$REPO_ROOT"

PASS=0 FAIL=0 SKIP=0
run_test() {
    local name="$1"; shift; local _rc=0
    "$@" >/dev/null 2>&1 || _rc=$?
    if ((_rc == 0)); then PASS=$((PASS+1)); printf '  \033[0;32m✓\033[0m %s\n' "$name"
    else FAIL=$((FAIL+1)); printf '  \033[0;31m✗\033[0m %s\n' "$name"; fi
}

printf 'install-mandatory-tools.sh\n'

# --dry-run mode doesn't actually install
test_dry_run() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" --dry-run 2>&1)"
    [[ "$out" == *"dry-run"* ]]
}
run_test "dry-run mode prints dry-run markers" test_dry_run

# Detects OS correctly
test_detect_os() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" --dry-run 2>&1)"
    # Should detect macOS on this system
    [[ "$out" == *"macos"* ]] || [[ "$out" == *"darwin"* ]] || [[ "$out" == *"brew"* ]]
}
run_test "detects macOS platform" test_detect_os

# Lists tools to install
test_lists_tools() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" --dry-run 2>&1)"
    # Should reference at least some tools
    [[ "$out" == *"rg"* ]] || [[ "$out" == *"fd"* ]] || [[ "$out" == *"jq"* ]] || [[ "$out" == *"ripgrep"* ]]
}
run_test "lists tools to install" test_lists_tools

# Without --dry-run, script would attempt real installs
# We do NOT test that — only dry-run is safe

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
((FAIL == 0)) && printf '\033[0;32mPASSED\033[0m\n' || { printf '\033[0;31mFAILED\033[0m\n'; exit 1; }
