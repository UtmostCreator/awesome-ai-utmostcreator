#!/usr/bin/env bash
# Tests for tools/ai/validate-context-budgets.php
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/tools/ai/validate-context-budgets.php"
PHP_BIN="${PHP_BIN:-$(command -v php)}"

PASS=0 FAIL=0 SKIP=0
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

run_test() {
    local name="$1"; shift; local _rc=0
    "$@" >/dev/null 2>&1 || _rc=$?
    if ((_rc == 0)); then PASS=$((PASS+1)); printf '  \033[0;32m✓\033[0m %s\n' "$name"
    else FAIL=$((FAIL+1)); printf '  \033[0;31m✗\033[0m %s\n' "$name"; fi
}

printf 'validate-context-budgets.php\n'

write_lines() {
    local path="$1" count="$2"
    mkdir -p "$(dirname "$path")"
    : >"$path"
    local i
    for ((i = 1; i <= count; i++)); do
        printf 'line %d\n' "$i" >>"$path"
    done
}

write_policy() {
    local root="$1" warn="$2" fail="$3"
    mkdir -p "$root/packages/ai-universal-rules/policies"
    cat >"$root/packages/ai-universal-rules/policies/ai-file-standards.json" <<EOF
{"line_limits":[{"id":"sample","patterns":["docs/*.md"],"warn_above":$warn,"fail_above":$fail}]}
EOF
}

test_warns_without_failing() {
    local root="$TMP/warn"
    write_policy "$root" 3 6
    write_lines "$root/docs/over-soft.md" 5
    local out
    out="$($PHP_BIN "$SCRIPT" "$root" 2>&1)"
    [[ "$out" == *"WARN sample docs/over-soft.md = 5 lines > soft max 3"* ]]
    [[ "$out" == *"failures=0"* ]]
}
run_test "soft max warnings exit successfully" test_warns_without_failing

test_fails_on_hard_max() {
    local root="$TMP/fail"
    write_policy "$root" 3 6
    write_lines "$root/docs/over-hard.md" 7
    local out rc=0
    out="$($PHP_BIN "$SCRIPT" "$root" 2>&1)" || rc=$?
    [[ "$rc" -ne 0 ]]
    [[ "$out" == *"FAIL sample docs/over-hard.md = 7 lines > hard max 6"* ]]
}
run_test "hard max violations fail" test_fails_on_hard_max

test_generated_paths_are_exempt() {
    local root="$TMP/generated"
    mkdir -p "$root/packages/ai-universal-rules/policies"
    cat >"$root/packages/ai-universal-rules/policies/ai-file-standards.json" <<'EOF'
{"line_limits":[{"id":"generated","patterns":["docs/ai/generated/*.md"],"warn_above":1,"fail_above":2}]}
EOF
    write_lines "$root/docs/ai/generated/large.md" 10
    local out
    out="$($PHP_BIN "$SCRIPT" "$root" 2>&1)"
    [[ "$out" == *"scanned 0 file(s); warnings=0 failures=0"* ]]
}
run_test "generated paths are exempt" test_generated_paths_are_exempt

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
if ((FAIL == 0)); then
    printf '\033[0;32mPASSED\033[0m\n'
else
    printf '\033[0;31mFAILED\033[0m\n'
    exit 1
fi
