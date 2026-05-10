#!/opt/homebrew/bin/bash
# Tests for scripts/ai/query-usage.sh
set -euo pipefail
BASH_BIN="${BASH_BIN:-/opt/homebrew/bin/bash}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/query-usage.sh"
cd "$REPO_ROOT"

PASS=0 FAIL=0 SKIP=0
run_test() {
    local name="$1"; shift; local _rc=0
    "$@" >/dev/null 2>&1 || _rc=$?
    if ((_rc == 0)); then PASS=$((PASS+1)); printf '  \033[0;32m✓\033[0m %s\n' "$name"
    else FAIL=$((FAIL+1)); printf '  \033[0;31m✗\033[0m %s\n' "$name"; fi
}

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

printf 'query-usage.sh\n'

# --help
test_help() { "$BASH_BIN" "$SCRIPT" --help 2>&1 | grep -q 'Usage'; }
run_test "help flag works" test_help

# File usage output
test_file_usage() {
    echo "hello world" > "$TMP/test.txt"
    local out
    out="$("$BASH_BIN" "$SCRIPT" "$TMP/test.txt")"
    [[ "$out" == *"query_usage:"* ]]
    [[ "$out" == *"bytes:"* ]]
    [[ "$out" == *"raw_estimated_tokens:"* ]]
}
run_test "file usage prints YAML output" test_file_usage

# Correct token estimate for file
test_file_tokens() {
    printf 'A%.0s' {1..400} > "$TMP/exact.txt"
    local out
    out="$("$BASH_BIN" "$SCRIPT" "$TMP/exact.txt")"
    echo "$out" | grep -q "raw_estimated_tokens: 100"
}
run_test "400 bytes → 100 tokens" test_file_tokens

# Directory usage (use a real repo directory)
test_dir_usage() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" "$REPO_ROOT/scripts/ai" 2>/dev/null || true)"
    [[ "$out" == *"query_usage:"* ]]
}
run_test "directory usage works" test_dir_usage

# Multiplier
test_multiplier() {
    printf 'A%.0s' {1..100} > "$TMP/mult.txt"
    local out
    out="$("$BASH_BIN" "$SCRIPT" "$TMP/mult.txt" --multiplier 2 --multiplier-label 2x)"
    echo "$out" | grep -q "multiplier: 2"
    echo "$out" | grep -q "multiplier_label: 2x"
    echo "$out" | grep -q "weighted_usage: 50.00"
}
run_test "multiplier affects weighted_usage" test_multiplier

# Reserved output
test_reserved_output() {
    echo "x" > "$TMP/res.txt"
    local out
    out="$("$BASH_BIN" "$SCRIPT" "$TMP/res.txt" --reserved-output 8000)"
    echo "$out" | grep -q "reserved_output_tokens: 8000"
}
run_test "reserved-output option works" test_reserved_output

# Missing path fails
test_missing_path() {
    ! "$BASH_BIN" "$SCRIPT" "$TMP/nonexistent" 2>/dev/null
}
run_test "missing path exits with error" test_missing_path

# Unknown option fails
test_unknown_option() {
    ! "$BASH_BIN" "$SCRIPT" "$TMP" --bogus 2>/dev/null
}
run_test "unknown option fails" test_unknown_option

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
((FAIL == 0)) && printf '\033[0;32mPASSED\033[0m\n' || { printf '\033[0;31mFAILED\033[0m\n'; exit 1; }
