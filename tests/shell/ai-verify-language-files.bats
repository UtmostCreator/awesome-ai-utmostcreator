#!/usr/bin/env bats
# Tests for the per-language file-discovery helpers
# (scripts/ai/internal/ai-verify/51-language-files.sh).
#
# As of docs/tickets/arch-todo-safe-language-verify-scripts-20260706-003959
# §8-P1, this module is NOT YET sourced by scripts/ai/ai-verify.sh, so these
# tests source it directly (plus its runtime dependency,
# scoped_changed_files_by_pathspec from 90-run.sh, and that function's own
# 10-scope.sh/20-shipped-filters.sh dependencies) inside a throwaway nested git
# repo, mirroring the fixture pattern in tests/shell/repomix-context-tree.bats
# and tests/shell/ai-verify-plan-status.bats.

REPO_ROOT="$(cd "$(dirname "$BATS_TEST_FILENAME")/../.." && pwd)"
COMMON="$REPO_ROOT/scripts/ai/common.sh"
SHIPPED_FILTERS="$REPO_ROOT/scripts/ai/internal/ai-verify/20-shipped-filters.sh"
SCOPE_MOD="$REPO_ROOT/scripts/ai/internal/ai-verify/10-scope.sh"
RUN_MOD="$REPO_ROOT/scripts/ai/internal/ai-verify/90-run.sh"
LANG_FILES="$REPO_ROOT/scripts/ai/internal/ai-verify/51-language-files.sh"

setup() {
    TMP_REPO="$(mktemp -d)"
    git -C "$TMP_REPO" init --quiet
    git -C "$TMP_REPO" config user.email test@example.com
    git -C "$TMP_REPO" config user.name Tester
}

teardown() {
    rm -rf "$TMP_REPO" 2>/dev/null || true
}

# Sources common.sh + 51-language-files.sh only (no scoped_changed_files_by_pathspec
# dependency needed for the pure language_pathspecs() function) and calls it.
run_pathspecs() {
    run bash -c '
        set -euo pipefail
        source "$1"
        source "$2"
        language_pathspecs "$3"
    ' _ "$COMMON" "$LANG_FILES" "$1"
}

# Full dependency chain for scoped_language_files(): common.sh, then the
# scoped_changed_files_by_pathspec chain (20-shipped-filters -> 10-scope ->
# 90-run, matching the root loader's real source order), then the module under
# test. Runs with cwd = $TMP_REPO so git commands resolve against the fixture
# repo, never this repo's own working tree. AI_VERIFY_SCOPE is fixed to
# "changed" (this test file only exercises the changed scope, per the task's
# "changed scope with untracked files should surface them" requirement).
run_scoped() {
    local lang="$1"
    run env AI_VERIFY_SCOPE=changed bash -c '
        set -euo pipefail
        cd "$1"
        source "$2"
        source "$3"
        source "$4"
        source "$5"
        source "$6"
        scoped_language_files "$7"
    ' _ "$TMP_REPO" "$COMMON" "$SHIPPED_FILTERS" "$SCOPE_MOD" "$RUN_MOD" "$LANG_FILES" "$lang"
}

@test "language_pathspecs prints the php glob" {
    run_pathspecs php
    [ "$status" -eq 0 ]
    [ "$output" = "*.php" ]
}

@test "language_pathspecs prints the js globs" {
    run_pathspecs js
    [ "$status" -eq 0 ]
    [ "$output" = "*.js
*.jsx
*.mjs
*.cjs" ]
}

@test "language_pathspecs prints the ts globs" {
    run_pathspecs ts
    [ "$status" -eq 0 ]
    [ "$output" = "*.ts
*.tsx
*.mts
*.cts" ]
}

@test "language_pathspecs prints the vue glob" {
    run_pathspecs vue
    [ "$status" -eq 0 ]
    [ "$output" = "*.vue" ]
}

@test "language_pathspecs prints the html globs" {
    run_pathspecs html
    [ "$status" -eq 0 ]
    [ "$output" = "*.html
*.blade.php
*.twig" ]
}

@test "language_pathspecs dies loudly on an unknown language" {
    run_pathspecs cobol
    [ "$status" -ne 0 ]
    [[ "$output" == *"unknown language: cobol"* ]]
}

@test "scoped_language_files (changed scope) surfaces untracked php files only" {
    printf '<?php\n' >"$TMP_REPO/new-file.php"
    printf 'console.log(1)\n' >"$TMP_REPO/new-file.js"
    run_scoped php
    [ "$status" -eq 0 ]
    [ "$output" = "new-file.php" ]
}

@test "scoped_language_files (changed scope) surfaces every js pathspec extension" {
    printf 'x\n' >"$TMP_REPO/a.js"
    printf 'x\n' >"$TMP_REPO/b.jsx"
    printf 'x\n' >"$TMP_REPO/c.mjs"
    printf 'x\n' >"$TMP_REPO/d.cjs"
    printf 'x\n' >"$TMP_REPO/e.ts"
    run_scoped js
    [ "$status" -eq 0 ]
    [ "$output" = "a.js
b.jsx
c.mjs
d.cjs" ]
}

@test "scoped_language_files (changed scope) excludes committed-and-unmodified files" {
    printf '<?php\n' >"$TMP_REPO/committed.php"
    git -C "$TMP_REPO" add committed.php
    git -C "$TMP_REPO" commit --quiet -m "seed committed php file"
    printf '<?php\n' >"$TMP_REPO/untracked.php"
    run_scoped php
    [ "$status" -eq 0 ]
    [ "$output" = "untracked.php" ]
}

@test "scoped_language_files (changed scope) surfaces vue files" {
    printf '<template/>\n' >"$TMP_REPO/widget.vue"
    run_scoped vue
    [ "$status" -eq 0 ]
    [ "$output" = "widget.vue" ]
}
