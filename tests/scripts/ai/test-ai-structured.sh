#!/usr/bin/env bash
# Tests for scripts/ai/ai-structured.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/ai-structured.sh"
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

printf 'ai-structured.sh\n'

# Missing mode shows usage
test_no_mode() { ! "$BASH_BIN" "$SCRIPT" 2>/dev/null; }
run_test "missing mode exits with error" test_no_mode

# json mode on composer.json
test_json() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" json "$REPO_ROOT/composer.json" '.name' 2>/dev/null)"
    [[ -n "$out" ]]
}
run_test "json mode queries composer.json" test_json

# validate-json on valid file
test_validate_json_valid() {
    echo '{"key":"value"}' > "$TMP/valid.json"
    "$BASH_BIN" "$SCRIPT" validate-json "$TMP/valid.json" 2>/dev/null
}
run_test "validate-json passes for valid JSON" test_validate_json_valid

# validate-json on invalid file
test_validate_json_invalid() {
    echo '{invalid' > "$TMP/invalid.json"
    ! "$BASH_BIN" "$SCRIPT" validate-json "$TMP/invalid.json" 2>/dev/null
}
run_test "validate-json fails for invalid JSON" test_validate_json_invalid

# yaml mode
if command -v yq >/dev/null 2>&1; then
    test_yaml() {
        echo -e "key: value\nlist:\n  - a\n  - b" > "$TMP/test.yaml"
        local out
        out="$("$BASH_BIN" "$SCRIPT" yaml "$TMP/test.yaml" '.key' 2>/dev/null)"
        [[ "$out" == *"value"* ]]
    }
    run_test "yaml mode queries YAML files" test_yaml

    test_validate_yaml_valid() {
        echo "key: value" > "$TMP/valid.yaml"
        "$BASH_BIN" "$SCRIPT" validate-yaml "$TMP/valid.yaml" 2>/dev/null
    }
    run_test "validate-yaml passes for valid YAML" test_validate_yaml_valid
else
    skip_test "yaml mode queries YAML files" "yq not installed"
    skip_test "validate-yaml passes for valid YAML" "yq not installed"
fi

# csv mode
test_csv() {
    printf 'name,age\nAlice,30\nBob,25\n' > "$TMP/test.csv"
    local out
    out="$("$BASH_BIN" "$SCRIPT" csv "$TMP/test.csv" 2>/dev/null)"
    [[ "$out" == *"Alice"* ]]
}
run_test "csv mode shows CSV content" test_csv

# csv --head N
test_csv_head() {
    printf 'name,age\nAlice,30\nBob,25\nCharlie,35\n' > "$TMP/head.csv"
    local out
    out="$("$BASH_BIN" "$SCRIPT" csv "$TMP/head.csv" --head 2 2>/dev/null)"
    [[ "$out" == *"Alice"* ]]
}
run_test "csv --head limits output" test_csv_head

# Missing file fails
test_missing_file() {
    ! "$BASH_BIN" "$SCRIPT" json "$TMP/nonexistent.json" '.key' 2>/dev/null
}
run_test "missing file fails" test_missing_file

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
((FAIL == 0)) && printf '\033[0;32mPASSED\033[0m\n' || { printf '\033[0;31mFAILED\033[0m\n'; exit 1; }
