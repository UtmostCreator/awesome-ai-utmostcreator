#!/usr/bin/env bash
# Smoke tests for thin read-only wrapper scripts that previously had no suite:
#   ai-install-coverage.sh, scripts/doctor.sh, scripts/hooks/pre-commit.sh,
#   scripts/hooks/commit-msg.sh
#
# repo-stats.sh and ai-file-freshness.sh moved to
# /home/utmostcreator/Projects/agent-repo-tools/test/test-misc-wrappers.sh
# (REUSABLE bucket, see docs/tickets/arch-todo-scripts-ai-reusable-extraction-20260711-162902/).
set -euo pipefail
BASH_BIN="${BASH_BIN:-$(command -v bash)}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
cd "$REPO_ROOT"

PASS=0 FAIL=0 SKIP=0
run_test() {
    local name="$1"; shift; local _rc=0
    "$@" >/dev/null 2>&1 || _rc=$?
    if ((_rc == 0)); then PASS=$((PASS+1)); printf '  \033[0;32m✓\033[0m %s\n' "$name"
    else FAIL=$((FAIL+1)); printf '  \033[0;31m✗\033[0m %s\n' "$name"; fi
}

printf 'misc wrappers\n'

# ai-install-coverage.sh: validates install surface; should run to completion.
test_install_coverage() {
    "$BASH_BIN" "$REPO_ROOT/scripts/ai/ai-install-coverage.sh" >/dev/null 2>&1
}
run_test "ai-install-coverage.sh runs" test_install_coverage

# doctor.sh: environment report, exit 0.
# Capture first (do not pipe a long-running script into `grep -q`; SIGPIPE +
# pipefail would make the test flap).
test_doctor() {
    local out
    out="$("$BASH_BIN" "$REPO_ROOT/scripts/doctor.sh" 2>&1)"
    [[ "$out" == *"doctor"* ]]
}
run_test "doctor.sh prints report" test_doctor

# pre-commit hook: runs to completion.
test_pre_commit() {
    local out
    out="$("$BASH_BIN" "$REPO_ROOT/scripts/hooks/pre-commit.sh" 2>&1)"
    [[ "$out" == *"pre-commit"* ]]
}
run_test "pre-commit hook runs" test_pre_commit

# commit-msg hook: with no commit-msg file it must fail gracefully (exit != 0).
test_commit_msg_missing() {
    ! "$BASH_BIN" "$REPO_ROOT/scripts/hooks/commit-msg.sh" >/dev/null 2>&1
}
run_test "commit-msg hook fails without message file" test_commit_msg_missing

# commit-msg hook: accepts a valid message file.
test_commit_msg_ok() {
    local tmp
    tmp="$(mktemp)"
    echo "fix: a valid conventional message" > "$tmp"
    local rc=0
    "$BASH_BIN" "$REPO_ROOT/scripts/hooks/commit-msg.sh" "$tmp" >/dev/null 2>&1 || rc=$?
    rm -f "$tmp"
    return "$rc"
}
run_test "commit-msg hook accepts a valid message" test_commit_msg_ok

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
if ((FAIL > 0)); then printf '\033[0;31mFAILED\033[0m\n'; exit 1; fi
printf '\033[0;32mPASSED\033[0m\n'
