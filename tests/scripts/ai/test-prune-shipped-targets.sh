#!/usr/bin/env bash
# Tests for scripts/ai/prune-shipped-targets.sh
# Read-only modes only: --help, --list, --dry-run, unknown mode.
# The destructive --apply path is intentionally NOT exercised here.
set -euo pipefail
BASH_BIN="${BASH_BIN:-$(command -v bash)}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/prune-shipped-targets.sh"
cd "$REPO_ROOT"

PASS=0 FAIL=0 SKIP=0
run_test() {
    local name="$1"; shift; local _rc=0
    "$@" >/dev/null 2>&1 || _rc=$?
    if ((_rc == 0)); then PASS=$((PASS+1)); printf '  \033[0;32m✓\033[0m %s\n' "$name"
    else FAIL=$((FAIL+1)); printf '  \033[0;31m✗\033[0m %s\n' "$name"; fi
}

printf 'prune-shipped-targets.sh\n'

test_help() { "$BASH_BIN" "$SCRIPT" --help 2>&1 | grep -q 'Usage'; }
run_test "help flag works" test_help

test_list() { "$BASH_BIN" "$SCRIPT" --list >/dev/null 2>&1; }
run_test "list mode runs read-only (exit 0)" test_list

test_dry_run() { "$BASH_BIN" "$SCRIPT" --dry-run >/dev/null 2>&1; }
run_test "dry-run mode runs read-only (exit 0)" test_dry_run

test_unknown() { ! "$BASH_BIN" "$SCRIPT" --bogus >/dev/null 2>&1; }
run_test "unknown option fails" test_unknown

# --list must not modify the worktree.
test_no_mutation() {
    local before after
    before="$(git status --porcelain | sort)"
    "$BASH_BIN" "$SCRIPT" --list >/dev/null 2>&1 || true
    after="$(git status --porcelain | sort)"
    [[ "$before" == "$after" ]]
}
run_test "list mode does not mutate worktree" test_no_mutation

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
if ((FAIL > 0)); then printf '\033[0;31mFAILED\033[0m\n'; exit 1; fi
printf '\033[0;32mPASSED\033[0m\n'
