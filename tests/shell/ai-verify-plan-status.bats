#!/usr/bin/env bats
# Tests for the Todo-plan checklist status guardrail
# (scripts/ai/internal/ai-verify/36-plan-status.sh), sourced by
# scripts/ai/ai-verify.sh.
#
# Hermetic: runs ai-verify.sh against a throwaway nested git repo (mirrors the
# fixture pattern in tests/shell/repomix-context-tree.bats) so untracked
# docs/tickets/**/plan*.md fixture files never touch this repo's real
# docs/tickets/ state.

REPO_ROOT="$(cd "$(dirname "$BATS_TEST_FILENAME")/../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/ai-verify.sh"

setup() {
    TMP_REPO="$(mktemp -d)"
    git -C "$TMP_REPO" init --quiet
    git -C "$TMP_REPO" config user.email test@example.com
    git -C "$TMP_REPO" config user.name Tester
}

teardown() {
    rm -rf "$TMP_REPO" 2>/dev/null || true
}

write_plan() {
    local rel="$1"
    shift
    mkdir -p "$(dirname "$TMP_REPO/$rel")"
    printf '%s\n' "$@" >"$TMP_REPO/$rel"
}

@test "plan-status is on by default and OK when no plan files are in scope" {
    run env AI_VERIFY_TEST_MODE=1 VERIFY_LINECOUNT=0 bash "$SCRIPT" "$TMP_REPO"
    [[ "$output" == *"no in-scope docs/tickets"* ]]
    [ "$status" -eq 0 ]
}

@test "plan-status is skipped when VERIFY_PLAN_STATUS=0" {
    write_plan "docs/tickets/branchA/plan-1-x.md" \
        '## Todo Plan' '- [ ] P0: incomplete'
    run env AI_VERIFY_TEST_MODE=1 VERIFY_LINECOUNT=0 VERIFY_PLAN_STATUS=0 bash "$SCRIPT" "$TMP_REPO"
    [[ "$output" == *"Skipping plan Todo-status check"* ]]
    [ "$status" -eq 0 ]
}

@test "plan-status reports OK when every checklist item is checked" {
    write_plan "docs/tickets/branchA/plan-1-x.md" \
        '## Todo Plan' '- [x] P0: done' \
        '## Acceptance Criteria' '- [x] AC-1: done'
    run env AI_VERIFY_TEST_MODE=1 VERIFY_LINECOUNT=0 bash "$SCRIPT" "$TMP_REPO"
    [[ "$output" == *"all 2 Todo item(s) complete"* ]]
    [[ "$output" == *"all 1 in-scope plan file(s) fully complete"* ]]
    [ "$status" -eq 0 ]
}

@test "plan-status WARNs (non-blocking) when some checklist items are unchecked" {
    write_plan "docs/tickets/branchA/plan-1-x.md" \
        '## Todo Plan' '- [x] P0: done' '- [ ] P1: not done yet'
    run env AI_VERIFY_TEST_MODE=1 VERIFY_LINECOUNT=0 bash "$SCRIPT" "$TMP_REPO"
    [[ "$output" == *"1 incomplete / 1 complete Todo item(s)"* ]]
    [[ "$output" == *"still have incomplete Todo items"* ]]
    [ "$status" -eq 0 ]
}

@test "plan-status ERRORs and fails the run when a difficulty notice is present" {
    write_plan "docs/tickets/branchA/plan-1-x.md" \
        '## Todo Plan' '- [ ] P0: this is impossible without more context'
    run env AI_VERIFY_TEST_MODE=1 VERIFY_LINECOUNT=0 bash "$SCRIPT" "$TMP_REPO"
    [[ "$output" == *"difficulty notice found"* ]]
    [[ "$output" == *"impossible"* ]]
    [ "$status" -eq 1 ]
}

@test "plan-status does not flag benign 'not implemented' prose outside checklist items" {
    write_plan "docs/tickets/branchA/plan-1-x.md" \
        '- Status: `PLAN ONLY — not implemented`' \
        '## Todo Plan' '- [ ] P0: add the new endpoint'
    run env AI_VERIFY_TEST_MODE=1 VERIFY_LINECOUNT=0 bash "$SCRIPT" "$TMP_REPO"
    [[ "$output" != *"difficulty notice found"* ]]
    [[ "$output" == *"1 incomplete / 0 complete Todo item(s)"* ]]
    [ "$status" -eq 0 ]
}

@test "plan-status ignores archived (DONE) plan files" {
    write_plan "docs/tickets/branchA/archive/DONE-plan-1-x.md" \
        '## Todo Plan' '- [ ] P0: this is impossible without more context'
    run env AI_VERIFY_TEST_MODE=1 VERIFY_LINECOUNT=0 bash "$SCRIPT" "$TMP_REPO"
    [[ "$output" == *"no in-scope docs/tickets"* ]]
    [ "$status" -eq 0 ]
}
