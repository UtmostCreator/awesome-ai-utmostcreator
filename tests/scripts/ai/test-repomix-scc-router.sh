#!/usr/bin/env bash
# Tests for scripts/ai/repomix-scc-router.sh
set -euo pipefail
BASH_BIN="${BASH_BIN:-$(command -v bash)}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/repomix-scc-router.sh"
cd "$REPO_ROOT"

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

printf 'repomix-scc-router.sh\n'

# Missing command fails
test_no_command() { ! "$BASH_BIN" "$SCRIPT" 2>/dev/null; }
run_test "missing command fails" test_no_command

# --help
test_help() { "$BASH_BIN" "$SCRIPT" --help 2>&1 | grep -qi 'usage'; }
run_test "help flag works" test_help

# stats command requires scc
if command -v scc >/dev/null 2>&1; then
    test_stats() {
        "$BASH_BIN" "$SCRIPT" stats . --output-dir "$TMP/scc-out" 2>/dev/null
        [[ -d "$TMP/scc-out" ]]
    }
    run_test "stats command produces output" test_stats
else
    skip_test "stats command produces output" "scc not installed"
fi

# clean/purge require confirmation — skip interactive tests
# Just verify they parse args correctly
test_unknown_cmd() {
    ! "$BASH_BIN" "$SCRIPT" nonexistent 2>/dev/null
}
run_test "unknown command fails" test_unknown_cmd

# path_is_ignored matcher: trailing-slash dir patterns must match nested files
# (P0) and known glob forms must keep working. Source only the matcher into a
# clean subshell so we exercise the real function logic.
matcher_check() {
    "$BASH_BIN" -c '
        shopt -s extglob
        ((BASH_VERSINFO[0] >= 4)) && shopt -s globstar
        source <(sed -n "/^path_is_ignored() {/,/^}/p" "$1")
        check() { IGNORE_PATTERNS=("$2"); path_is_ignored "$3"; }
        check x ".ai-backups/" ".ai-backups/install-x/files/y.md" || exit 1
        check x ".ai-logs/" ".ai-logs/run.jsonl" || exit 1
        check x "vendor/" "vendor/pkg/file.php" || exit 1
        check x "node_modules/" "node_modules/pkg/index.js" || exit 1
        check x "generated/**" "generated/cache.json" || exit 1
        # a non-matching path must NOT be ignored
        if check x "src/" "other/app.js"; then exit 1; fi
    ' _ "$SCRIPT"
}
run_test "path_is_ignored matches nested files under trailing-slash dirs" matcher_check

# --include-ignored: collect_files must skip a .gitignore'd file by default and
# include it when INCLUDE_IGNORED=1. Source only collect_files + path_is_ignored
# into a clean subshell and run them against a throwaway git repo.
collect_ignored_check() {
    local want="$1" # "default" (exclude) or "ignored" (include)
    local repo
    repo="$(mktemp -d)"
    (
        cd "$repo"
        git init -q
        printf 'storage/\n' >.gitignore
        printf 'tracked\n' >tracked.txt
        mkdir -p storage/tmp
        printf '{"a":1}\n' >storage/tmp/data.json
    )

    # shellcheck disable=SC2016
    "$BASH_BIN" -c '
        set -euo pipefail
        shopt -s extglob
        ((BASH_VERSINFO[0] >= 4)) && shopt -s globstar
        source <(sed -n "/^path_is_ignored() {/,/^}/p;/^collect_files() {/,/^}/p" "$1")
        ROOT="$2"
        IGNORE_PATTERNS=(".git")
        INCLUDE_IGNORED="$3"
        collect_files
        printf "%s\n" "${COLLECTED_FILES[@]}"
    ' _ "$SCRIPT" "$repo" "$([[ "$want" == "ignored" ]] && echo 1 || echo 0)" >"$repo/.out"

    local rc=0
    if [[ "$want" == "ignored" ]]; then
        grep -q 'storage/tmp/data.json' "$repo/.out" || rc=1
    else
        grep -q 'storage/tmp/data.json' "$repo/.out" && rc=1
        grep -q 'tracked.txt' "$repo/.out" || rc=1
    fi
    rm -rf "$repo"
    return "$rc"
}
if command -v git >/dev/null 2>&1; then
    run_test "collect_files excludes git-ignored files by default" collect_ignored_check default
    run_test "collect_files includes git-ignored files with --include-ignored" collect_ignored_check ignored
else
    skip_test "collect_files include-ignored behavior" "git not installed"
fi

# --no-ignore / --include-repomixignored: load_ignore_patterns must DROP the
# .repomixignore patterns under full bypass so a folder listed there is still
# collectable, while .git and the output dir stay excluded. Source only
# load_ignore_patterns + path_is_ignored + collect_files into a clean subshell.
repomixignore_bypass_check() {
    local want="$1" # "default" (exclude) or "bypass" (include)
    local repo
    repo="$(mktemp -d)"
    (
        cd "$repo"
        git init -q
        printf 'generated/\n' >.repomixignore
        printf 'tracked\n' >tracked.txt
        mkdir -p generated
        printf '{"a":1}\n' >generated/out.json
    )

    # shellcheck disable=SC2016
    "$BASH_BIN" -c '
        set -euo pipefail
        shopt -s extglob
        ((BASH_VERSINFO[0] >= 4)) && shopt -s globstar
        source <(sed -n "/^path_is_ignored() {/,/^}/p;/^load_ignore_patterns() {/,/^}/p;/^collect_files() {/,/^}/p" "$1")
        ROOT="$2"
        OUTPUT_DIR_REL=".repomix-context/tree-context"
        AI_CONTEXT_HARD_EXCLUDES=(".git" ".repomix-context")
        INCLUDE_IGNORED="$3"
        INCLUDE_REPOMIXIGNORED="$3"
        load_ignore_patterns
        collect_files
        printf "%s\n" "${COLLECTED_FILES[@]}"
    ' _ "$SCRIPT" "$repo" "$([[ "$want" == "bypass" ]] && echo 1 || echo 0)" >"$repo/.out"

    local rc=0
    if [[ "$want" == "bypass" ]]; then
        grep -q 'generated/out.json' "$repo/.out" || rc=1
    else
        grep -q 'generated/out.json' "$repo/.out" && rc=1
        grep -q 'tracked.txt' "$repo/.out" || rc=1
    fi
    rm -rf "$repo"
    return "$rc"
}
if command -v git >/dev/null 2>&1; then
    run_test "load_ignore_patterns honors .repomixignore by default" repomixignore_bypass_check default
    run_test "full bypass (--no-ignore) packs .repomixignore'd folder" repomixignore_bypass_check bypass
else
    skip_test "repomixignore bypass behavior" "git not installed"
fi

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
((FAIL == 0)) && printf '\033[0;32mPASSED\033[0m\n' || { printf '\033[0;31mFAILED\033[0m\n'; exit 1; }
