#!/usr/bin/env bats
# Tests for the per-tool verification report-file helpers
# (scripts/ai/internal/ai-verify/54-reporting.sh).
#
# As of docs/tickets/arch-todo-safe-language-verify-scripts-20260706-003959
# §8-P1, this module is NOT YET sourced by scripts/ai/ai-verify.sh, so these
# tests source it directly (plus scripts/ai/common.sh for $AI_LOG_DIR) inside a
# throwaway nested git repo, mirroring the fixture pattern in
# tests/shell/ai-verify-plan-status.bats.

REPO_ROOT="$(cd "$(dirname "$BATS_TEST_FILENAME")/../.." && pwd)"
COMMON="$REPO_ROOT/scripts/ai/common.sh"
REPORTING="$REPO_ROOT/scripts/ai/internal/ai-verify/54-reporting.sh"
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

@test "verify_report_dir defaults under the AI_LOG_DIR-derived .ai-logs/verify path" {
    run bash -c '
        set -euo pipefail
        cd "$1"
        source "$2"
        source "$3"
        verify_report_dir
    ' _ "$TMP_REPO" "$COMMON" "$REPORTING"
    [ "$status" -eq 0 ]
    [ "$output" = ".ai-logs/verify" ]
    [ -d "$TMP_REPO/.ai-logs/verify" ]
}

@test "verify_report_dir honors an AI_LOG_DIR override" {
    run env AI_LOG_DIR="custom-logs" bash -c '
        set -euo pipefail
        cd "$1"
        source "$2"
        source "$3"
        verify_report_dir
    ' _ "$TMP_REPO" "$COMMON" "$REPORTING"
    [ "$status" -eq 0 ]
    [ "$output" = "custom-logs/verify" ]
    [ -d "$TMP_REPO/custom-logs/verify" ]
}

@test "verify_report_dir honors an explicit VERIFY_REPORT_DIR override" {
    run env VERIFY_REPORT_DIR="explicit-reports" bash -c '
        set -euo pipefail
        cd "$1"
        source "$2"
        source "$3"
        verify_report_dir
    ' _ "$TMP_REPO" "$COMMON" "$REPORTING"
    [ "$status" -eq 0 ]
    [ "$output" = "explicit-reports" ]
    [ -d "$TMP_REPO/explicit-reports" ]
}

@test "VERIFY_REPORT_DIR override wins even when AI_LOG_DIR is also set" {
    run env AI_LOG_DIR="custom-logs" VERIFY_REPORT_DIR="explicit-reports" bash -c '
        set -euo pipefail
        cd "$1"
        source "$2"
        source "$3"
        verify_report_dir
    ' _ "$TMP_REPO" "$COMMON" "$REPORTING"
    [ "$status" -eq 0 ]
    [ "$output" = "explicit-reports" ]
}

@test "write_verify_report_file writes the expected file and content" {
    run bash -c '
        set -euo pipefail
        cd "$1"
        source "$2"
        source "$3"
        write_verify_report_file "eslint" "json" "{\"ok\":true}"
    ' _ "$TMP_REPO" "$COMMON" "$REPORTING"
    [ "$status" -eq 0 ]
    [ "$output" = ".ai-logs/verify/eslint.json" ]
    [ -f "$TMP_REPO/.ai-logs/verify/eslint.json" ]
    run cat "$TMP_REPO/.ai-logs/verify/eslint.json"
    [ "$output" = '{"ok":true}' ]
}

@test "write_verify_report_file names the file <tool>.<extension> under the resolved report dir" {
    run bash -c '
        set -euo pipefail
        cd "$1"
        source "$2"
        source "$3"
        write_verify_report_file "phpstan" "txt" "no errors"
    ' _ "$TMP_REPO" "$COMMON" "$REPORTING"
    [ "$status" -eq 0 ]
    [ -f "$TMP_REPO/.ai-logs/verify/phpstan.txt" ]
    run cat "$TMP_REPO/.ai-logs/verify/phpstan.txt"
    [ "$output" = "no errors" ]
}

# Negative-assertion guard (FLAG-1): the rejected external suggestion's
# competing, hardcoded top-level report directory (a dot-ai-prefixed directory
# with its own "verify" subfolder, distinct from $AI_LOG_DIR) must never be
# reintroduced as a literal path in either new module.
@test "the rejected hardcoded report-dir literal does not appear in 54-reporting.sh" {
    run grep -n '\.ai/verify' "$REPORTING"
    [ "$status" -ne 0 ]
    [ -z "$output" ]
}

@test "the rejected hardcoded report-dir literal does not appear in 51-language-files.sh" {
    run grep -n '\.ai/verify' "$LANG_FILES"
    [ "$status" -ne 0 ]
    [ -z "$output" ]
}
