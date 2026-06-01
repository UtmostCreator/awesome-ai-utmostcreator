#!/usr/bin/env bash
# Tests for scripts/ai/check-file-refs.sh
set -euo pipefail
BASH_BIN="${BASH_BIN:-$(command -v bash)}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/check-file-refs.sh"

PASS=0 FAIL=0 SKIP=0
run_test() {
    local name="$1"; shift; local _rc=0
    "$@" >/dev/null 2>&1 || _rc=$?
    if ((_rc == 0)); then PASS=$((PASS+1)); printf '  \033[0;32m✓\033[0m %s\n' "$name"
    else FAIL=$((FAIL+1)); printf '  \033[0;31m✗\033[0m %s\n' "$name"; fi
}

printf 'check-file-refs.sh\n'

# --help
test_help() { "$BASH_BIN" "$SCRIPT" --help 2>&1 | grep -q 'Usage'; }
run_test "help flag works" test_help

# Unknown option fails.
test_unknown() {
    ! "$BASH_BIN" "$SCRIPT" --bogus >/dev/null 2>&1
}
run_test "unknown option fails" test_unknown

# Invalid format fails.
test_bad_format() {
    ! "$BASH_BIN" "$SCRIPT" . --format toml >/dev/null 2>&1
}
run_test "invalid --format fails" test_bad_format

# Isolated repo: orphan detection.
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
(
    cd "$TMP"
    git init -q
    git config user.email t@t
    git config user.name t
    echo "see [guide](guide.md)" > README.md
    echo "# referenced" > guide.md
    echo "# nobody links me" > orphan.md
    git add -A
    git commit -qm init
)

test_detects_orphan() {
    local out
    out="$(cd "$TMP" && "$BASH_BIN" "$SCRIPT" . --ext md)"
    [[ "$out" == *"orphan.md"* ]]
}
run_test "detects unreferenced orphan" test_detects_orphan

test_skips_referenced() {
    local out
    out="$(cd "$TMP" && "$BASH_BIN" "$SCRIPT" . --ext md)"
    [[ "$out" != *"guide.md"* ]]
}
run_test "does not flag referenced file" test_skips_referenced

test_skips_readme_entrypoint() {
    local out
    out="$(cd "$TMP" && "$BASH_BIN" "$SCRIPT" . --ext md)"
    [[ "$out" != *"README.md"* ]]
}
run_test "skips implicit entrypoint README.md" test_skips_readme_entrypoint

test_json_contract() {
    local out
    out="$(cd "$TMP" && "$BASH_BIN" "$SCRIPT" . --ext md --format json)"
    printf '%s' "$out" | jq -e '
        .schema == "1"
        and .tool == "check-file-refs"
        and (.orphans | type == "array")
        and (.count | type == "number")
        and (.orphans | index("orphan.md") != null)
    ' >/dev/null
}
run_test "json output matches documented contract" test_json_contract

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
if ((FAIL > 0)); then printf '\033[0;31mFAILED\033[0m\n'; exit 1; fi
printf '\033[0;32mPASSED\033[0m\n'
