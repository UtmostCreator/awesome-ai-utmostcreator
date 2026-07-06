#!/usr/bin/env bats
# Tests for the Kotlin/Android/KMP file-discovery helpers
# (scripts/ai/internal/ai-verify/60-kotlin-files.sh).
#
# As of docs/tickets/arch-todo-android-kmp-verify-lane-20260706-010421 §8-P0-a,
# this module is NOT YET sourced by scripts/ai/ai-verify.sh, so these tests
# source it directly (plus its runtime dependency,
# scoped_changed_files_by_pathspec from 90-run.sh, and that function's own
# 10-scope.sh / 20-shipped-filters.sh dependencies) inside a throwaway nested git
# repo. This mirrors tests/shell/ai-verify-language-files.bats exactly.

REPO_ROOT="$(cd "$(dirname "$BATS_TEST_FILENAME")/../.." && pwd)"
COMMON="$REPO_ROOT/scripts/ai/common.sh"
SHIPPED_FILTERS="$REPO_ROOT/scripts/ai/internal/ai-verify/20-shipped-filters.sh"
SCOPE_MOD="$REPO_ROOT/scripts/ai/internal/ai-verify/10-scope.sh"
RUN_MOD="$REPO_ROOT/scripts/ai/internal/ai-verify/90-run.sh"
KOTLIN_FILES="$REPO_ROOT/scripts/ai/internal/ai-verify/60-kotlin-files.sh"

setup() {
    TMP_REPO="$(mktemp -d)"
    git -C "$TMP_REPO" init --quiet
    git -C "$TMP_REPO" config user.email test@example.com
    git -C "$TMP_REPO" config user.name Tester
}

teardown() {
    rm -rf "$TMP_REPO" 2>/dev/null || true
}

# Sources common.sh + 60-kotlin-files.sh only (kotlin_language_pathspecs is pure)
# and calls it.
run_pathspecs() {
    run bash -c '
        set -euo pipefail
        source "$1"
        source "$2"
        kotlin_language_pathspecs "$3"
    ' _ "$COMMON" "$KOTLIN_FILES" "$1"
}

# Full dependency chain for scoped_kotlin_files(): common.sh, then the
# scoped_changed_files_by_pathspec chain (20-shipped-filters -> 10-scope ->
# 90-run, matching the root loader's real source order), then the module under
# test. Runs with cwd = $TMP_REPO so git resolves against the fixture repo.
run_scoped() {
    run env AI_VERIFY_SCOPE=changed bash -c '
        set -euo pipefail
        cd "$1"
        source "$2"
        source "$3"
        source "$4"
        source "$5"
        source "$6"
        scoped_kotlin_files kotlin
    ' _ "$TMP_REPO" "$COMMON" "$SHIPPED_FILTERS" "$SCOPE_MOD" "$RUN_MOD" "$KOTLIN_FILES"
}

@test "kotlin_language_pathspecs prints the kotlin/gradle globs in order" {
    run_pathspecs kotlin
    [ "$status" -eq 0 ]
    [ "$output" = "*.kt
*.kts
*.gradle.kts
*.gradle
gradle/libs.versions.toml" ]
}

@test "kotlin_language_pathspecs dies loudly on an unknown language" {
    run_pathspecs cobol
    [ "$status" -ne 0 ]
    [[ "$output" == *"unknown language: cobol"* ]]
}

@test "scoped_kotlin_files (changed scope) surfaces untracked kotlin files only" {
    printf 'fun main() {}\n' >"$TMP_REPO/Main.kt"
    printf 'console.log(1)\n' >"$TMP_REPO/app.js"
    printf '<?php\n' >"$TMP_REPO/index.php"
    run_scoped
    [ "$status" -eq 0 ]
    [ "$output" = "Main.kt" ]
}

@test "scoped_kotlin_files (changed scope) surfaces gradle scripts and the version catalog" {
    mkdir -p "$TMP_REPO/gradle"
    printf 'plugins {}\n' >"$TMP_REPO/build.gradle.kts"
    printf 'rootProject.name = "x"\n' >"$TMP_REPO/settings.gradle.kts"
    printf 'apply()\n' >"$TMP_REPO/legacy.gradle"
    printf '[versions]\n' >"$TMP_REPO/gradle/libs.versions.toml"
    run_scoped
    [ "$status" -eq 0 ]
    [ "$output" = "build.gradle.kts
gradle/libs.versions.toml
legacy.gradle
settings.gradle.kts" ]
}

@test "scoped_kotlin_files (changed scope) excludes committed-and-unmodified files" {
    printf 'fun a() {}\n' >"$TMP_REPO/Committed.kt"
    git -C "$TMP_REPO" add Committed.kt
    git -C "$TMP_REPO" commit --quiet -m "seed committed kotlin file"
    printf 'fun b() {}\n' >"$TMP_REPO/Untracked.kt"
    run_scoped
    [ "$status" -eq 0 ]
    [ "$output" = "Untracked.kt" ]
}
