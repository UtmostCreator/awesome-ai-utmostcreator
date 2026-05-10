#!/opt/homebrew/bin/bash
# Tests for scripts/ai/fd-files.sh
set -euo pipefail
BASH_BIN="${BASH_BIN:-/opt/homebrew/bin/bash}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/fd-files.sh"
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

mkdir -p "$TMP/src" "$TMP/vendor/pkg" "$TMP/node_modules/pkg" "$TMP/.git/objects"
echo "hello" > "$TMP/src/app.php"
echo "world" > "$TMP/src/util.js"
echo "hidden" > "$TMP/src/.hidden.txt"
echo "vendor" > "$TMP/vendor/pkg/lib.php"
echo "node" > "$TMP/node_modules/pkg/index.js"

printf 'fd-files.sh\n'

# --help
test_help() { "$BASH_BIN" "$SCRIPT" --help 2>&1 | grep -q 'Usage'; }
run_test "help flag works" test_help

# Basic search
test_basic() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" "app" "$TMP/src")"
    [[ "$out" == *"app.php"* ]]
}
run_test "finds files matching query" test_basic

# JSON output
test_json() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" "app" "$TMP/src" --json)"
    echo "$out" | jq -e '.[0]' >/dev/null
}
run_test "JSON output is valid array" test_json

# vendor excluded
test_vendor_excluded() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" "lib" "$TMP" 2>/dev/null || true)"
    [[ "$out" != *"vendor"* ]]
}
run_test "vendor/ excluded by default" test_vendor_excluded

# node_modules excluded
test_node_modules_excluded() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" "index" "$TMP" 2>/dev/null || true)"
    [[ "$out" != *"node_modules"* ]]
}
run_test "node_modules/ excluded by default" test_node_modules_excluded

# .git excluded
test_git_excluded() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" "objects" "$TMP" 2>/dev/null || true)"
    [[ "$out" != *".git"* ]]
}
run_test ".git/ excluded by default" test_git_excluded

# --type filter
test_type_filter() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" "." "$TMP/src" --type php)"
    [[ "$out" == *".php"* ]]
    [[ "$out" != *".js"* ]]
}
run_test "--type filters by extension" test_type_filter

# --hidden includes hidden files
test_hidden() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" "hidden" "$TMP/src" --hidden)"
    [[ "$out" == *".hidden"* ]]
}
run_test "--hidden includes hidden files" test_hidden

# No query fails
test_no_query() {
    ! "$BASH_BIN" "$SCRIPT" 2>/dev/null
}
run_test "missing query exits with error" test_no_query

# Unknown option fails
test_unknown_option() {
    ! "$BASH_BIN" "$SCRIPT" "x" --bogus 2>/dev/null
}
run_test "unknown option fails" test_unknown_option

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
((FAIL == 0)) && printf '\033[0;32mPASSED\033[0m\n' || { printf '\033[0;31mFAILED\033[0m\n'; exit 1; }
