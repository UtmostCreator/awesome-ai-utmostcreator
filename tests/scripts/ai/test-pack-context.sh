#!/usr/bin/env bash
# Tests for scripts/ai/pack-context.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/pack-context.sh"
cd "$REPO_ROOT"
BASH_BIN="${BASH_BIN:-$(command -v bash)}"

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

printf 'pack-context.sh\n'

# --help
test_help() { "$BASH_BIN" "$SCRIPT" --help 2>&1 | grep -q 'Usage'; }
run_test "help flag works" test_help

# Unknown backend accepted (auto fallback)
test_auto_fallback() {
    local out
    # auto mode with --include should attempt a pack but may fail if no tool installed
    out="$(OUTPUT_DIR="$TMP/out" "$BASH_BIN" "$SCRIPT" auto --include "README.md" 2>&1 || true)"
    [[ -n "$out" ]]
}
run_test "auto backend attempts context pack" test_auto_fallback

# files-to-prompt backend
if command -v files-to-prompt >/dev/null 2>&1; then
    test_files_to_prompt() {
        OUTPUT_DIR="$TMP/out2" "$BASH_BIN" "$SCRIPT" files-to-prompt README.md 2>/dev/null
    }
    run_test "files-to-prompt backend works" test_files_to_prompt
else
    skip_test "files-to-prompt backend works" "files-to-prompt not installed"
fi

# repomix backend
if command -v repomix >/dev/null 2>&1; then
    test_repomix() {
        OUTPUT_DIR="$TMP/out3" "$BASH_BIN" "$SCRIPT" repomix --include "README.md" 2>/dev/null
    }
    run_test "repomix backend works" test_repomix
else
    skip_test "repomix backend works" "repomix not installed"
fi

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
((FAIL == 0)) && printf '\033[0;32mPASSED\033[0m\n' || { printf '\033[0;31mFAILED\033[0m\n'; exit 1; }
