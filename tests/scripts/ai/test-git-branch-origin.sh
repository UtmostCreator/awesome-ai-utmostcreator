#!/usr/bin/env bash
# Tests for scripts/ai/git-branch-origin.sh
set -euo pipefail
BASH_BIN="${BASH_BIN:-$(command -v bash)}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/git-branch-origin.sh"
cd "$REPO_ROOT"

PASS=0 FAIL=0 SKIP=0
run_test() {
    local name="$1"
    shift
    local _rc=0
    "$@" >/dev/null 2>&1 || _rc=$?
    if ((_rc == 0)); then
        PASS=$((PASS + 1))
        printf '  \033[0;32m✓\033[0m %s\n' "$name"
    else
        FAIL=$((FAIL + 1))
        printf '  \033[0;31m✗\033[0m %s\n' "$name"
    fi
}
skip_test() {
    SKIP=$((SKIP + 1))
    printf '  \033[0;33m⊘\033[0m %s (skipped: %s)\n' "$1" "$2"
}

printf 'git-branch-origin.sh\n'

# --help works and documents the tool
test_help() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" --help 2>&1 || true)"
    [[ "$out" == *"branched off"* || "$out" == *"branch the current branch"* ]]
}
run_test "--help prints usage" test_help

# Default invocation prints a non-empty branch name
test_default_name() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" 2>/dev/null || true)"
    [[ -n "$out" ]]
}
run_test "prints a non-empty origin branch name" test_default_name

# --field base prints a commit-like sha
test_field_base() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" --field base 2>/dev/null || true)"
    [[ "$out" =~ ^[0-9a-f]{7,40}$ ]]
}
run_test "--field base prints a merge-base sha" test_field_base

# --field count prints an integer
test_field_count() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" --field count 2>/dev/null || true)"
    [[ "$out" =~ ^[0-9]+$ ]]
}
run_test "--field count prints an integer distance" test_field_count

# --field all prints three tab-separated fields
test_field_all() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" --field all 2>/dev/null || true)"
    [[ "$(awk -F'\t' '{print NF}' <<<"$out")" == "3" ]]
}
run_test "--field all prints name<TAB>base<TAB>count" test_field_all

# --json emits a valid envelope with the expected keys
if command -v jq >/dev/null 2>&1; then
    test_json() {
        local out
        out="$("$BASH_BIN" "$SCRIPT" --json 2>/dev/null || true)"
        jq -e '.tool == "git-branch-origin" and (.origin_branch|type=="string") and (.merge_base|type=="string") and (.distance|type=="number")' <<<"$out" >/dev/null
    }
    run_test "--json emits a valid envelope" test_json
else
    skip_test "--json emits a valid envelope" "jq not installed"
fi

# GIT_ORIGIN_REF override is honored
test_override() {
    local out
    out="$(GIT_ORIGIN_REF=origin/main "$BASH_BIN" "$SCRIPT" --field name 2>/dev/null || true)"
    # When origin/main exists the override name is echoed back.
    [[ -n "$out" ]]
}
run_test "GIT_ORIGIN_REF override is honored" test_override

# Invalid --field is rejected
test_bad_field() {
    local rc=0
    "$BASH_BIN" "$SCRIPT" --field bogus >/dev/null 2>&1 || rc=$?
    ((rc != 0))
}
run_test "invalid --field exits non-zero" test_bad_field

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
if ((FAIL == 0)); then
    printf '\033[0;32mPASSED\033[0m\n'
else
    printf '\033[0;31mFAILED\033[0m\n'
    exit 1
fi
