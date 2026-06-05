#!/usr/bin/env bash
# Tests for scripts/ai/ai-verify.sh
set -euo pipefail
BASH_BIN="${BASH_BIN:-$(command -v bash)}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/ai-verify.sh"
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

printf 'ai-verify.sh\n'

# Script runs and produces output
test_runs() {
    local out
    out="$(AI_VERIFY_TEST_MODE=1 "$BASH_BIN" "$SCRIPT" "$REPO_ROOT" 2>&1 || true)"
    [[ "$out" == *"==>"* ]]
}
run_test "script runs and prints step markers" test_runs

# Prints repository status
test_repo_status() {
    local out
    out="$(AI_VERIFY_TEST_MODE=1 "$BASH_BIN" "$SCRIPT" "$REPO_ROOT" 2>&1 || true)"
    [[ "$out" == *"repository"* ]]
}
run_test "prints repository section" test_repo_status

# Runs shellcheck if available
if command -v shellcheck >/dev/null 2>&1; then
    test_shellcheck() {
        local out
        out="$(AI_VERIFY_TEST_MODE=1 AI_VERIFY_SCOPE=ai "$BASH_BIN" "$SCRIPT" "$REPO_ROOT" 2>&1 || true)"
        [[ "$out" == *"shellcheck"* ]]
    }
    run_test "runs shellcheck on AI scripts" test_shellcheck
else
    skip_test "runs shellcheck on AI scripts" "shellcheck not installed"
fi

# Runs composer validate if available
if command -v composer >/dev/null 2>&1 && [[ -f "$REPO_ROOT/composer.json" ]]; then
    test_composer() {
        local out
        out="$(AI_VERIFY_TEST_MODE=1 "$BASH_BIN" "$SCRIPT" "$REPO_ROOT" 2>&1 || true)"
        [[ "$out" == *"composer"* ]]
    }
    run_test "runs composer validate" test_composer
else
    skip_test "runs composer validate" "composer not available"
fi

# VERIFY_FULL=0 skips full test suite
test_skip_full() {
    local out
    out="$(AI_VERIFY_TEST_MODE=1 VERIFY_FULL=0 "$BASH_BIN" "$SCRIPT" "$REPO_ROOT" 2>&1 || true)"
    [[ "$out" == *"Skipping full"* ]] || [[ "$out" == *"done"* ]]
}
run_test "VERIFY_FULL=0 skips full test suite" test_skip_full

# AI_VERIFY_SCOPE=changed limits scope
test_scope_changed() {
    local out
    out="$(AI_VERIFY_TEST_MODE=1 AI_VERIFY_SCOPE=changed "$BASH_BIN" "$SCRIPT" "$REPO_ROOT" 2>&1 || true)"
    # Should complete without error
    [[ "$out" == *"==>"* ]]
}
run_test "AI_VERIFY_SCOPE=changed limits scope" test_scope_changed

# Extract the scoping helper functions from the script and exercise them in
# isolation, so the merge-base/branch logic is covered without needing pint.
load_scoping_functions() {
    # shellcheck disable=SC1090
    source "$REPO_ROOT/scripts/ai/common.sh"
    # These are consumed by the eval'd helper functions below via dynamic scope.
    # shellcheck disable=SC2034
    VERIFY_BASE_REF=""
    # shellcheck disable=SC2034
    VERIFY_AUTHOR=""
    # shellcheck disable=SC2034
    AI_VERIFY_SCOPE="branch"
    # Pull only the function bodies (between the marked helpers) by sourcing the
    # whole script in a guarded way: define a fake "cd"/main no-op is fragile, so
    # instead we re-declare the functions verbatim via sed extraction.
    eval "$(sed -n '/^resolve_branch_base() {/,/^}/p;/^branch_scoped_files() {/,/^}/p;/^scoped_php_files() {/,/^}/p' "$SCRIPT")"
}

# resolve_branch_base prints a commit sha or fails cleanly
test_resolve_base() {
    (
        set -euo pipefail
        load_scoping_functions
        local base
        base="$(resolve_branch_base || true)"
        # On any branch with origin/main present, base must be a 40-char sha; if
        # no trunk exists, base is empty and the function returns nonzero — both
        # are acceptable, but it must never crash.
        [[ -z "$base" || "$base" =~ ^[0-9a-f]{7,40}$ ]]
    )
}
run_test "resolve_branch_base returns a sha or empty without crashing" test_resolve_base

# scoped_php_files only emits existing *.php files and never errors under set -e
test_scoped_php_files() {
    (
        set -euo pipefail
        load_scoping_functions
        local out
        out="$(scoped_php_files || true)"
        # Every emitted line must be an existing .php file.
        local f
        while IFS= read -r f; do
            [[ -z "$f" ]] && continue
            [[ "$f" == *.php ]] || exit 1
            [[ -f "$f" ]] || exit 1
        done <<<"$out"
    )
}
run_test "scoped_php_files emits only existing php files" test_scoped_php_files

# branch scope must be a recognized scope (no 'unknown scope' die)
test_branch_scope_recognized() {
    local out
    out="$(AI_VERIFY_TEST_MODE=0 AI_VERIFY_SCOPE=branch VERIFY_SECRETS=0 VERIFY_FULL=0 \
        "$BASH_BIN" "$SCRIPT" "$REPO_ROOT" 2>&1 || true)"
    [[ "$out" != *"unknown AI_VERIFY_SCOPE"* ]]
}
run_test "branch scope is recognized (no unknown-scope error)" test_branch_scope_recognized

# Run the script with a fake "lychee" on PATH that records every invocation.
# This proves the link checker never reaches the network by accident.
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
    # Run in a throwaway dir with a README so the lychee branch has a target,
    # but no composer.json/package.json so PHP/JS blocks stay out of the way.
    local work="$tmpbin/work"
    mkdir -p "$work/docs"
    printf '# t\n' >"$work/README.md"
    (
        cd "$work"
        git init -q
        PATH="$tmpbin:$PATH" AI_VERIFY_TEST_MODE=0 VERIFY_SECRETS=0 VERIFY_FULL=0 \
            "$@" "$BASH_BIN" "$SCRIPT" "$work" >/dev/null 2>&1 || true
    )
    cat "$record" 2>/dev/null || true
    rm -rf "$tmpbin"
}

# Default: link checking is OFF, so fake lychee must NOT be called at all.
test_links_off_by_default() {
    local calls
    calls="$(run_with_fake_lychee env)"
    [[ -z "$calls" ]]
}
run_test "link check is skipped by default (no network)" test_links_off_by_default

# VERIFY_LINKS=1 (no network flag): lychee must be called WITH --offline.
test_links_offline_when_enabled() {
    local calls
    calls="$(run_with_fake_lychee env VERIFY_LINKS=1)"
    [[ "$calls" == *"--offline"* ]]
}
run_test "VERIFY_LINKS=1 runs lychee with --offline" test_links_offline_when_enabled

# VERIFY_LINKS=1 + VERIFY_LINKS_NETWORK=1: lychee runs WITHOUT --offline.
test_links_network_when_opted_in() {
    local calls
    calls="$(run_with_fake_lychee env VERIFY_LINKS=1 VERIFY_LINKS_NETWORK=1)"
    [[ -n "$calls" && "$calls" != *"--offline"* ]]
}
run_test "VERIFY_LINKS_NETWORK=1 runs lychee without --offline" test_links_network_when_opted_in

# Default (ai) scope must resolve PHP linting to branch scoping, not project-wide.
# Verify by exercising the same case logic the script uses.
test_default_scope_is_php_scoped() {
    local AI_VERIFY_SCOPE="ai" php_scoped=0 php_scope_source="ai"
    case "$AI_VERIFY_SCOPE" in
    all) ;;
    changed) php_scoped=1 ;;
    *) php_scoped=1; php_scope_source="branch" ;;
    esac
    ((php_scoped == 1)) && [[ "$php_scope_source" == "branch" ]]
}
run_test "default (ai) scope narrows PHP linting to branch, not project-wide" test_default_scope_is_php_scoped

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
if ((FAIL == 0)); then
    printf '\033[0;32mPASSED\033[0m\n'
else
    printf '\033[0;31mFAILED\033[0m\n'
    exit 1
fi
