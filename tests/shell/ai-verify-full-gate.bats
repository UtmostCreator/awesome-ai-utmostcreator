#!/usr/bin/env bats
# Tests for the P6 full-gate-only additions to
# scripts/ai/internal/ai-verify/90-run.sh (deptrac, composer-require-checker,
# composer-unused, playwright, vitest run).
#
# Part of docs/tickets/arch-todo-safe-language-verify-scripts-20260706-003959
# (§8-P6). Hermetic: every test runs the real ai-verify.sh against a throwaway
# git fixture repo with PATH-stubbed vendor/bin/* and pnpm, so no real tool is
# ever invoked. All checks under test are gated by VERIFY_FULL=1 and must
# never fire by default (VERIFY_FULL unset/0) nor via the per-language
# `--language php` dispatch path (scripts/ai/internal/ai-verify/53-language-dispatch.sh),
# which never calls ai_verify_run / the VERIFY_FULL branch at all.

REPO_ROOT="$(cd "$(dirname "$BATS_TEST_FILENAME")/../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/ai-verify.sh"

setup() {
    TMP_REPO="$(mktemp -d)"
    STUB_BIN="$(mktemp -d)"
    git -C "$TMP_REPO" init --quiet
    git -C "$TMP_REPO" config user.email test@example.com
    git -C "$TMP_REPO" config user.name Tester
}

teardown() {
    rm -rf "$TMP_REPO" "$STUB_BIN" 2>/dev/null || true
}

write_composer_json() {
    printf '{}' >"$TMP_REPO/composer.json"
    # Stub `composer` itself too: composer.json is untracked, so it is always
    # "in scope" for the composer validate/audit step (90-run.sh), and the
    # real composer binary is on PATH in the dev environment -- `composer
    # audit` would otherwise try to reach the network. This stub keeps every
    # test hermetic and prevents an unbounded network-wait hang.
    cat >"$STUB_BIN/composer" <<'EOF'
#!/usr/bin/env bash
echo "STUB-COMPOSER-RAN $*"
exit 0
EOF
    chmod +x "$STUB_BIN/composer"
}

write_package_json() {
    printf '%s' "$1" >"$TMP_REPO/package.json"
}

stub_pnpm() {
    cat >"$STUB_BIN/pnpm" <<'EOF'
#!/usr/bin/env bash
echo "STUB-PNPM-RAN $*"
exit 0
EOF
    chmod +x "$STUB_BIN/pnpm"
}

# Stubs vendor/bin/$1 to echo a marker and exit with $2 (default 0).
stub_php_tool() {
    local name="$1"
    local exit_code="${2:-0}"
    mkdir -p "$TMP_REPO/vendor/bin"
    cat >"$TMP_REPO/vendor/bin/$name" <<EOF
#!/usr/bin/env bash
echo "STUB-${name^^}-RAN \$*"
exit $exit_code
EOF
    chmod +x "$TMP_REPO/vendor/bin/$name"
}

# Runs the full pipeline (no --language). Extra VAR=value pairs, e.g.
# `run_full VERIFY_FULL=1`, are appended as additional env assignments.
# AI_VERIFY_SCOPE=changed + VERIFY_SECRETS=0 keep is_changed_or_branch_scope
# true so the pre-existing gitleaks/trivy/semgrep/osv-scanner steps (which run
# unconditionally in "ai"/"all" scope, unrelated to this ticket) stay off,
# isolating $status/$output to the P6 checks under test.
run_full() {
    run env PATH="$STUB_BIN:$PATH" VERIFY_LINECOUNT=0 VERIFY_PLAN_STATUS=0 \
        AI_VERIFY_SCOPE=changed VERIFY_SECRETS=0 \
        "$@" bash "$SCRIPT" "$TMP_REPO"
}

# Runs the per-language php dispatch path (ai_verify_language, which never
# calls ai_verify_run / the VERIFY_FULL branch at all).
run_lang_php() {
    run env PATH="$STUB_BIN:$PATH" VERIFY_LINECOUNT=0 VERIFY_PLAN_STATUS=0 \
        AI_VERIFY_SCOPE=changed VERIFY_SECRETS=0 \
        "$@" bash "$SCRIPT" --language php "$TMP_REPO"
}

# --- deptrac: VERIFY_FULL-gated, full-pipeline-only ------------------------

@test "deptrac runs under VERIFY_FULL=1 when vendor/bin/deptrac is executable" {
    write_composer_json
    stub_php_tool deptrac
    run_full VERIFY_FULL=1
    [ "$status" -eq 0 ]
    [[ "$output" == *"vendor/bin/deptrac analyse"* ]]
    [[ "$output" == *"STUB-DEPTRAC-RAN analyse"* ]]
}

@test "deptrac does NOT run when VERIFY_FULL is unset/0" {
    write_composer_json
    stub_php_tool deptrac
    run_full
    [[ "$output" != *"deptrac"* ]]
}

@test "deptrac does NOT run via --language php even with VERIFY_FULL=1" {
    write_composer_json
    stub_php_tool deptrac
    run_lang_php VERIFY_FULL=1
    [ "$status" -eq 0 ]
    [[ "$output" != *"deptrac"* ]]
}

# --- composer-require-checker: VERIFY_FULL-gated, full-pipeline-only -------

@test "composer-require-checker runs under VERIFY_FULL=1 when the binary is executable" {
    write_composer_json
    stub_php_tool composer-require-checker
    run_full VERIFY_FULL=1
    [ "$status" -eq 0 ]
    [[ "$output" == *"vendor/bin/composer-require-checker check composer.json"* ]]
    [[ "$output" == *"STUB-COMPOSER-REQUIRE-CHECKER-RAN check composer.json"* ]]
}

@test "composer-require-checker does NOT run when VERIFY_FULL is unset/0" {
    write_composer_json
    stub_php_tool composer-require-checker
    run_full
    [[ "$output" != *"composer-require-checker"* ]]
}

@test "composer-require-checker does NOT run via --language php even with VERIFY_FULL=1" {
    write_composer_json
    stub_php_tool composer-require-checker
    run_lang_php VERIFY_FULL=1
    [ "$status" -eq 0 ]
    [[ "$output" != *"composer-require-checker"* ]]
}

# --- composer-unused: advisory-only, never fails the run -------------------

@test "composer-unused runs under VERIFY_FULL=1 and reports findings advisory-only" {
    write_composer_json
    stub_php_tool composer-unused 1
    run_full VERIFY_FULL=1
    [ "$status" -eq 0 ]
    [[ "$output" == *"vendor/bin/composer-unused (advisory)"* ]]
    [[ "$output" == *"composer-unused reported findings"* ]]
    [[ "$output" != *"==> failed:"* ]]
}

@test "composer-unused never increments \$failures even on a non-zero stub exit" {
    write_composer_json
    stub_php_tool composer-unused 1
    run_full VERIFY_FULL=1
    # The overall run must stay exit 0: composer-unused is the ONLY stubbed
    # tool and it never counts toward $failures.
    [ "$status" -eq 0 ]
}

@test "composer-unused writes its raw output to .ai-logs/verify/composer-unused.txt" {
    write_composer_json
    stub_php_tool composer-unused 1
    run_full VERIFY_FULL=1
    [ "$status" -eq 0 ]
    [ -f "$TMP_REPO/.ai-logs/verify/composer-unused.txt" ]
    grep -q "STUB-COMPOSER-UNUSED-RAN" "$TMP_REPO/.ai-logs/verify/composer-unused.txt"
}

@test "composer-unused reports clean when the stub exits 0" {
    write_composer_json
    stub_php_tool composer-unused 0
    run_full VERIFY_FULL=1
    [ "$status" -eq 0 ]
    [[ "$output" == *"composer-unused: no unused packages reported"* ]]
}

@test "composer-unused does NOT run when VERIFY_FULL is unset/0" {
    write_composer_json
    stub_php_tool composer-unused 1
    run_full
    [[ "$output" != *"composer-unused"* ]]
}

@test "composer-unused does NOT run via --language php even with VERIFY_FULL=1" {
    write_composer_json
    stub_php_tool composer-unused 1
    run_lang_php VERIFY_FULL=1
    [ "$status" -eq 0 ]
    [[ "$output" != *"composer-unused"* ]]
}

# --- playwright: VERIFY_FULL + dependency-gated -----------------------------

@test "playwright runs under VERIFY_FULL=1 when @playwright/test is a dependency" {
    stub_pnpm
    write_package_json '{"devDependencies":{"@playwright/test":"^1.0.0"}}'
    run_full VERIFY_FULL=1
    [ "$status" -eq 0 ]
    [[ "$output" == *"pnpm exec playwright test"* ]]
    [[ "$output" == *"STUB-PNPM-RAN exec playwright test"* ]]
}

@test "playwright does NOT run when VERIFY_FULL is unset/0 even with the dependency present" {
    stub_pnpm
    write_package_json '{"devDependencies":{"@playwright/test":"^1.0.0"}}'
    run_full
    [[ "$output" != *"playwright"* ]]
}

@test "playwright does NOT run when the dependency is absent, even with VERIFY_FULL=1" {
    stub_pnpm
    write_package_json '{"devDependencies":{"lodash":"^4.0.0"}}'
    run_full VERIFY_FULL=1
    [[ "$output" != *"playwright"* ]]
}

# --- vitest run (full, unscoped): VERIFY_FULL + dependency-gated -----------

@test "vitest run fires under VERIFY_FULL=1 when vitest is a dependency" {
    stub_pnpm
    write_package_json '{"devDependencies":{"vitest":"^1.0.0"}}'
    run_full VERIFY_FULL=1
    [ "$status" -eq 0 ]
    [[ "$output" == *"pnpm exec vitest run"* ]]
    [[ "$output" == *"STUB-PNPM-RAN exec vitest run"* ]]
}

@test "vitest run does NOT fire when VERIFY_FULL is unset/0 even with vitest present" {
    stub_pnpm
    write_package_json '{"devDependencies":{"vitest":"^1.0.0"}}'
    run_full
    [[ "$output" != *"vitest run"* ]]
}

@test "vitest run does NOT fire when vitest is absent, even with VERIFY_FULL=1" {
    stub_pnpm
    write_package_json '{"devDependencies":{"lodash":"^4.0.0"}}'
    run_full VERIFY_FULL=1
    [[ "$output" != *"vitest"* ]]
}
