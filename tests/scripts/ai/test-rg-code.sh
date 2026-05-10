#!/opt/homebrew/bin/bash
# Tests for scripts/ai/rg-code.sh
set -euo pipefail
BASH_BIN="${BASH_BIN:-/opt/homebrew/bin/bash}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/rg-code.sh"
cd "$REPO_ROOT"

PASS=0 FAIL=0 SKIP=0
run_test() {
    local name="$1"; shift
    local _rc=0
    "$@" >/dev/null 2>&1 || _rc=$?
    if ((_rc == 0)); then PASS=$((PASS+1)); printf '  \033[0;32m✓\033[0m %s\n' "$name"
    else FAIL=$((FAIL+1)); printf '  \033[0;31m✗\033[0m %s\n' "$name"; fi
}
skip_test() { SKIP=$((SKIP+1)); printf '  \033[0;33m⊘\033[0m %s (skipped: %s)\n' "$1" "$2"; }

# Create temp test directory
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

mkdir -p "$TMP/src"
cat > "$TMP/src/app.php" <<'PHP'
<?php
class UserService {
    public function login() { return true; }
    public function logout() { return false; }
}
PHP
cat > "$TMP/src/helper.js" <<'JS'
function login() { return true; }
function logout() { return false; }
module.exports = { login, logout };
JS
mkdir -p "$TMP/vendor/pkg"
echo "vendor code login" > "$TMP/vendor/pkg/lib.php"
mkdir -p "$TMP/node_modules/pkg"
echo "node_modules login" > "$TMP/node_modules/pkg/index.js"

printf 'rg-code.sh\n'

# --help is parsed as pattern (no dedicated help flag)
# Test usage prints when no args given
test_no_args() { ! "$BASH_BIN" "$SCRIPT" 2>/dev/null; }
run_test "missing args fails" test_no_args

# Basic text search
test_basic() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" "login" "$TMP/src")"
    [[ "$out" == *"login"* ]]
}
run_test "basic pattern search finds matches" test_basic

# JSON output
test_json() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" "login" "$TMP/src" --json)"
    echo "$out" | jq -e '.[0].file' >/dev/null
    echo "$out" | jq -e '.[0].line' >/dev/null
}
run_test "JSON output has file and line" test_json

# --files output
test_files() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" "login" "$TMP/src" --files)"
    [[ "$out" == *"app.php"* ]]
}
run_test "--files lists matching files" test_files

# --count output
test_count() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" "login" "$TMP/src" --count)"
    [[ "$out" =~ [0-9] ]]
}
run_test "--count returns numeric counts" test_count

# vendor excluded by default
test_vendor_excluded() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" "login" "$TMP" --files 2>/dev/null || true)"
    [[ "$out" != *"vendor"* ]]
}
run_test "vendor/ excluded by default" test_vendor_excluded

# node_modules excluded by default
test_node_modules_excluded() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" "login" "$TMP" --files 2>/dev/null || true)"
    [[ "$out" != *"node_modules"* ]]
}
run_test "node_modules/ excluded by default" test_node_modules_excluded

# Mode: php
test_mode_php() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" "login" "$TMP/src" --mode php --files)"
    [[ "$out" == *".php"* ]]
    [[ "$out" != *".js"* ]]
}
run_test "mode php filters to .php files" test_mode_php

# Mode: js
test_mode_js() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" "login" "$TMP/src" --mode js --files)"
    [[ "$out" == *".js"* ]]
    [[ "$out" != *".php"* ]]
}
run_test "mode js filters to .js files" test_mode_js

# Mode: config
test_mode_config() {
    echo '{"key": "login"}' > "$TMP/src/config.json"
    local out
    out="$("$BASH_BIN" "$SCRIPT" "login" "$TMP/src" --mode config --files)"
    [[ "$out" == *".json"* ]]
}
run_test "mode config filters to config files" test_mode_config

# Context lines
test_context() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" "login" "$TMP/src/app.php" --context 1)"
    local lines
    lines="$(echo "$out" | wc -l | tr -d ' ')"
    ((lines > 1))
}
run_test "--context adds surrounding lines" test_context

# --type filter
test_type() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" "login" "$TMP/src" --type js --files)"
    [[ "$out" == *".js"* ]]
}
run_test "--type filters by extension" test_type

# No matches returns exit 1 (rg behavior)
test_no_matches() {
    ! "$BASH_BIN" "$SCRIPT" "definitely_no_match_xyz_$$" "$TMP/src" >/dev/null 2>&1
}
run_test "no matches returns non-zero exit" test_no_matches

# Unknown mode fails
test_unknown_mode() {
    ! "$BASH_BIN" "$SCRIPT" "login" "$TMP/src" --mode nonexistent >/dev/null 2>&1
}
run_test "unknown mode fails" test_unknown_mode

# Unknown option fails
test_unknown_option() {
    ! "$BASH_BIN" "$SCRIPT" "login" "$TMP/src" --bogus >/dev/null 2>&1
}
run_test "unknown option fails" test_unknown_option

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
((FAIL == 0)) && printf '\033[0;32mPASSED\033[0m\n' || { printf '\033[0;31mFAILED\033[0m\n'; exit 1; }
