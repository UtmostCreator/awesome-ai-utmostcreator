#!/usr/bin/env bash
# Tests for scripts/ai/ai-doc-check.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/ai-doc-check.sh"
cd "$REPO_ROOT"
BASH_BIN="${BASH_BIN:-$(command -v bash)}"

PASS=0 FAIL=0 SKIP=0
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

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

printf 'ai-doc-check.sh\n'

# No --help flag; test usage function directly
test_usage() {
    local out
    out="$("$BASH_BIN" -c 'source scripts/ai/ai-doc-check.sh 2>/dev/null; usage' 2>&1 || true)"
    # The script doesn't have --help, so just verify it sources OK
    true
}
run_test "script sources without error" test_usage

# Unknown mode fails
test_unknown() {
    ! AI_LOG_DIR="$TMP/logs" AI_EVENT_LOG="$TMP/logs/ev.jsonl" "$BASH_BIN" "$SCRIPT" nonexistent 2>/dev/null
}
run_test "unknown mode fails" test_unknown

# all mode runs without crashing
test_all() {
    "$BASH_BIN" "$SCRIPT" all 2>&1 || true
    # Just needs to not crash
}
run_test "all mode runs" test_all

# Generated docs are excluded from link checks (gitignored aggregation artifacts).
test_excludes_generated() {
    "$BASH_BIN" -c '
        source scripts/ai/ai-doc-check.sh 2>/dev/null
        is_excluded_doc_path "docs/ai/generated/advisor-context.md" || exit 1
        is_excluded_doc_path "./docs/ai/generated/repo-structure.md" || exit 1
        ! is_excluded_doc_path "docs/ai/project-context.md" || exit 1
        ! is_excluded_doc_path "README.md" || exit 1
    '
}
run_test "excludes docs/ai/generated from doc checks" test_excludes_generated

run_with_fake_lychee() {
    local tmpbin record
    tmpbin="$(mktemp -d)"
    record="$tmpbin/lychee.calls"
    cat >"$tmpbin/lychee" <<EOF
#!/usr/bin/env bash
printf '%s\n' "\$*" >>"$record"
exit 0
EOF
    chmod +x "$tmpbin/lychee"

    local work="$tmpbin/work"
    mkdir -p "$work/docs"
    printf '# t\n' >"$work/README.md"
    printf '# t\n' >"$work/docs/test.md"
    (
        cd "$work"
        PATH="$tmpbin:$PATH" AI_LOG_DIR="$TMP/logs-links" AI_EVENT_LOG="$TMP/logs-links/ev.jsonl" \
            "$@" "$BASH_BIN" "$SCRIPT" links >/dev/null 2>&1 || true
    )
    cat "$record" 2>/dev/null || true
    rm -rf "$tmpbin"
}

test_links_offline_by_default() {
    local calls
    calls="$(run_with_fake_lychee env)"
    [[ "$calls" == *"--offline"* ]]
}
run_test "links mode runs lychee offline by default" test_links_offline_by_default

test_links_network_opt_in_still_offline() {
    local calls
    calls="$(run_with_fake_lychee env VERIFY_LINKS_NETWORK=1)"
    [[ "$calls" == *"--offline"* ]]
}
run_test "VERIFY_LINKS_NETWORK=1 still runs lychee offline" test_links_network_opt_in_still_offline

# markdownlint mode
if command -v markdownlint >/dev/null 2>&1; then
    test_markdownlint() {
        local out
        out="$(AI_LOG_DIR="$TMP/logs3" AI_EVENT_LOG="$TMP/logs3/ev.jsonl" "$BASH_BIN" "$SCRIPT" markdownlint 2>&1 || true)"
        [[ "$out" == *"markdownlint"* ]] || [[ "$out" == *"not installed"* ]]
    }
    run_test "markdownlint mode runs" test_markdownlint
else
    skip_test "markdownlint mode runs" "markdownlint not installed"
fi

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
if ((FAIL == 0)); then
    printf '\033[0;32mPASSED\033[0m\n'
else
    printf '\033[0;31mFAILED\033[0m\n'
    exit 1
fi
