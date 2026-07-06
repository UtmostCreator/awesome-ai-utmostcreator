#!/usr/bin/env bats
# Tests for the tool-availability policy layer
# (scripts/ai/internal/ai-verify/50-tool-policy.sh) and the three targeted
# 90-run.sh safety fixes it accompanies (osv-scanner invocation, VERIFY_SECURITY
# opt-in gate, broadened Biome detection).
#
# 50-tool-policy.sh is NOT yet sourced by the root loader (scripts/ai/ai-verify.sh)
# in this slice, so its unit tests source it directly (after common.sh, which
# it depends on for nothing but is sourced anyway to mirror every sibling
# module's real load order) rather than going through ai-verify.sh.
#
# Hermetic: unit tests run in a throwaway tmp dir (fixture package.json /
# vendor/bin stubs) so they never depend on this repo's own composer/npm
# state. Integration tests stub trivy/semgrep/osv-scanner/pnpm on PATH so they
# never invoke the real network-touching tools.

REPO_ROOT="$(cd "$(dirname "$BATS_TEST_FILENAME")/../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/ai-verify.sh"
POLICY_MODULE="$REPO_ROOT/scripts/ai/internal/ai-verify/50-tool-policy.sh"
STEP_RUNNER_MODULE="$REPO_ROOT/scripts/ai/internal/ai-verify/40-step-runner.sh"
RUN_MODULE="$REPO_ROOT/scripts/ai/internal/ai-verify/90-run.sh"

setup() {
    TMP_DIR="$(mktemp -d)"
    STUB_BIN="$(mktemp -d)"
}

teardown() {
    rm -rf "$TMP_DIR" "$STUB_BIN" 2>/dev/null || true
}

# Sources common.sh + 40-step-runner.sh (for has_package_dependency /
# has_package_script, which 50-tool-policy.sh's can_run_tool reuses rather
# than reimplementing) + 50-tool-policy.sh, with $1 as cwd, then runs the
# remaining args as a command/function call. This mirrors 50-tool-policy.sh's
# own documented "sourced AFTER 40-step-runner.sh" load order. Isolated in a
# subshell so the `set -euo pipefail` picked up from common.sh never affects
# the bats process.
tool_policy_eval() {
    local dir="$1"
    shift
    (
        cd "$dir" || exit 1
        # shellcheck source=scripts/ai/common.sh
        source "$REPO_ROOT/scripts/ai/common.sh"
        # shellcheck source=scripts/ai/internal/ai-verify/40-step-runner.sh
        source "$STEP_RUNNER_MODULE"
        # shellcheck source=scripts/ai/internal/ai-verify/50-tool-policy.sh
        source "$POLICY_MODULE"
        "$@"
    )
}

write_package_json() {
    printf '%s' "$1" >"$TMP_DIR/package.json"
}

# --- is_standalone_safe_tool ------------------------------------------------

@test "is_standalone_safe_tool is true for every allowlisted standalone tool" {
    for tool in shellcheck shfmt actionlint gitleaks trivy semgrep osv-scanner lychee; do
        run tool_policy_eval "$TMP_DIR" is_standalone_safe_tool "$tool"
        [ "$status" -eq 0 ]
    done
}

@test "is_standalone_safe_tool is false for framework-local tools" {
    for tool in eslint phpstan pint biome vue-tsc nuxt; do
        run tool_policy_eval "$TMP_DIR" is_standalone_safe_tool "$tool"
        [ "$status" -ne 0 ]
    done
}

# --- has_composer_bin --------------------------------------------------------

@test "has_composer_bin is true when vendor/bin/<bin> is executable" {
    mkdir -p "$TMP_DIR/vendor/bin"
    printf '#!/usr/bin/env bash\ntrue\n' >"$TMP_DIR/vendor/bin/pint"
    chmod +x "$TMP_DIR/vendor/bin/pint"
    run tool_policy_eval "$TMP_DIR" has_composer_bin pint
    [ "$status" -eq 0 ]
}

@test "has_composer_bin is false when the binary is missing" {
    run tool_policy_eval "$TMP_DIR" has_composer_bin pint
    [ "$status" -ne 0 ]
}

@test "has_composer_bin is false when the file exists but is not executable" {
    mkdir -p "$TMP_DIR/vendor/bin"
    printf 'not executable\n' >"$TMP_DIR/vendor/bin/pint"
    chmod -x "$TMP_DIR/vendor/bin/pint"
    run tool_policy_eval "$TMP_DIR" has_composer_bin pint
    [ "$status" -ne 0 ]
}

# --- can_run_tool: composer-bin dispatch -------------------------------------

@test "can_run_tool dispatches phpstan/psalm/phpunit/pest/rector/phpmd/deptrac to has_composer_bin" {
    for bin in phpstan psalm phpunit pest rector phpmd deptrac; do
        mkdir -p "$TMP_DIR/vendor/bin"
        printf '#!/usr/bin/env bash\ntrue\n' >"$TMP_DIR/vendor/bin/$bin"
        chmod +x "$TMP_DIR/vendor/bin/$bin"
        run tool_policy_eval "$TMP_DIR" can_run_tool "$bin"
        [ "$status" -eq 0 ]
        rm -f "$TMP_DIR/vendor/bin/$bin"
        run tool_policy_eval "$TMP_DIR" can_run_tool "$bin"
        [ "$status" -ne 0 ]
    done
}

# --- can_run_tool: package.json dependency dispatch --------------------------

@test "can_run_tool eslint reuses has_package_dependency" {
    write_package_json '{"devDependencies":{"eslint":"^9.0.0"}}'
    run tool_policy_eval "$TMP_DIR" can_run_tool eslint
    [ "$status" -eq 0 ]
}

@test "can_run_tool eslint is false without an eslint dependency" {
    write_package_json '{"devDependencies":{"lodash":"^4.0.0"}}'
    run tool_policy_eval "$TMP_DIR" can_run_tool eslint
    [ "$status" -ne 0 ]
}

@test "can_run_tool vue-tsc reuses has_package_dependency" {
    write_package_json '{"devDependencies":{"vue-tsc":"^2.0.0"}}'
    run tool_policy_eval "$TMP_DIR" can_run_tool vue-tsc
    [ "$status" -eq 0 ]
}

@test "can_run_tool nuxt/nuxi match either dependency name" {
    write_package_json '{"devDependencies":{"nuxt":"^3.0.0"}}'
    run tool_policy_eval "$TMP_DIR" can_run_tool nuxt
    [ "$status" -eq 0 ]
    run tool_policy_eval "$TMP_DIR" can_run_tool nuxi
    [ "$status" -eq 0 ]
}

@test "can_run_tool knip reuses has_package_dependency" {
    write_package_json '{"devDependencies":{"knip":"^5.0.0"}}'
    run tool_policy_eval "$TMP_DIR" can_run_tool knip
    [ "$status" -eq 0 ]
}

# --- can_run_tool: broadened biome detection ---------------------------------

@test "can_run_tool biome matches the real @biomejs/biome package name" {
    write_package_json '{"devDependencies":{"@biomejs/biome":"^1.9.0"}}'
    run tool_policy_eval "$TMP_DIR" can_run_tool biome
    [ "$status" -eq 0 ]
}

@test "can_run_tool biome matches a bare 'biome' dependency name" {
    write_package_json '{"devDependencies":{"biome":"^1.9.0"}}'
    run tool_policy_eval "$TMP_DIR" can_run_tool biome
    [ "$status" -eq 0 ]
}

@test "can_run_tool biome matches a biome.json config with no dependency entry" {
    write_package_json '{"devDependencies":{"lodash":"^4.0.0"}}'
    printf '{}' >"$TMP_DIR/biome.json"
    run tool_policy_eval "$TMP_DIR" can_run_tool biome
    [ "$status" -eq 0 ]
}

@test "can_run_tool biome matches a biome.jsonc config with no dependency entry" {
    write_package_json '{"devDependencies":{"lodash":"^4.0.0"}}'
    printf '{}' >"$TMP_DIR/biome.jsonc"
    run tool_policy_eval "$TMP_DIR" can_run_tool biome
    [ "$status" -eq 0 ]
}

@test "can_run_tool biome is false with unrelated deps and no biome config" {
    write_package_json '{"devDependencies":{"lodash":"^4.0.0"}}'
    run tool_policy_eval "$TMP_DIR" can_run_tool biome
    [ "$status" -ne 0 ]
}

# --- can_run_tool: default dispatch (standalone-safe + PATH) -----------------

# Builds a PATH-equivalent directory of symlinks to every executable
# currently reachable on $PATH, EXCEPT the named tool, and echoes it. This
# hides a single real tool deterministically (regardless of which host/CI
# image is running the suite) while every other command common.sh/bash needs
# (git, jq, sed, coreutils, ...) stays fully available.
path_hiding_tool() {
    local hidden="$1" hide_dir="$2" d f name
    mkdir -p "$hide_dir"
    while IFS= read -r d; do
        [[ -d "$d" ]] || continue
        for f in "$d"/*; do
            [[ -x "$f" && -f "$f" ]] || continue
            name="$(basename "$f")"
            [[ "$name" == "$hidden" ]] && continue
            [[ -e "$hide_dir/$name" ]] && continue
            ln -sf "$f" "$hide_dir/$name" 2>/dev/null || true
        done
    done < <(printf '%s' "$PATH" | tr ':' '\n')
    printf '%s' "$hide_dir"
}

@test "can_run_tool default dispatch is true for a standalone-safe tool on PATH" {
    cat >"$STUB_BIN/shellcheck" <<'EOF'
#!/usr/bin/env bash
exit 0
EOF
    chmod +x "$STUB_BIN/shellcheck"
    run env PATH="$STUB_BIN:$PATH" bash -c '
        set -euo pipefail
        cd "$1" || exit 1
        source "$2/scripts/ai/common.sh"
        source "$3"
        source "$4"
        can_run_tool shellcheck
    ' _ "$TMP_DIR" "$REPO_ROOT" "$STEP_RUNNER_MODULE" "$POLICY_MODULE"
    [ "$status" -eq 0 ]
}

@test "can_run_tool default dispatch is false for a standalone-safe tool NOT on PATH" {
    HIDE_DIR="$(mktemp -d)"
    path_hiding_tool shellcheck "$HIDE_DIR" >/dev/null
    run env PATH="$HIDE_DIR" bash -c '
        set -euo pipefail
        cd "$1" || exit 1
        source "$2/scripts/ai/common.sh"
        source "$3"
        source "$4"
        can_run_tool shellcheck
    ' _ "$TMP_DIR" "$REPO_ROOT" "$STEP_RUNNER_MODULE" "$POLICY_MODULE"
    rm -rf "$HIDE_DIR"
    [ "$status" -ne 0 ]
    [ "$status" -ne 127 ]
}

@test "can_run_tool default dispatch is false for a tool not in the standalone-safe allowlist" {
    run tool_policy_eval "$TMP_DIR" can_run_tool some-unknown-tool-xyz
    [ "$status" -ne 0 ]
}

# --- 90-run.sh fix: osv-scanner invocation (source-grep, no execution) -------
#
# AI_VERIFY_TEST_MODE=1 exits ai_verify_run() before the security-scanner
# section is ever reached (it stubs shellcheck/composer/pnpm and returns
# early), so the osv-scanner invocation string cannot be observed by running
# the pipeline in test mode. It is verified directly against the source
# instead, exactly as instructed.

@test "90-run.sh no longer uses the deprecated osv-scanner --lockfile= invocation" {
    run grep -c -- '--lockfile=' "$RUN_MODULE"
    [ "$status" -ne 0 ]
    [ "$output" = "0" ]
}

@test "90-run.sh invokes osv-scanner as 'scan source -r .' (both call sites)" {
    run grep -c -- 'osv-scanner scan source -r \.' "$RUN_MODULE"
    [ "$status" -eq 0 ]
    [ "$output" = "2" ]
}

# --- 90-run.sh fix: VERIFY_SECURITY opt-in gate (full-pipeline integration) --
#
# The security-scanner block lives inline in ai_verify_run(), reached only in
# the non-test-mode path, so these run the real (non-AI_VERIFY_TEST_MODE)
# pipeline against an empty throwaway git fixture with trivy/semgrep/
# osv-scanner stubbed on PATH so no real scanner ever executes.

stub_scanners() {
    local tool
    for tool in trivy semgrep osv-scanner; do
        cat >"$STUB_BIN/$tool" <<EOF
#!/usr/bin/env bash
echo "STUB-${tool^^}-RAN \$*"
exit 0
EOF
        chmod +x "$STUB_BIN/$tool"
    done
}

new_git_fixture() {
    local dir="$1"
    git -C "$dir" init --quiet
    git -C "$dir" config user.email test@example.com
    git -C "$dir" config user.name Tester
}

@test "VERIFY_SECURITY unset: broad scanners are skipped in changed scope (default output preserved)" {
    new_git_fixture "$TMP_DIR"
    stub_scanners
    run env PATH="$STUB_BIN:$PATH" AI_VERIFY_SCOPE=changed VERIFY_LINECOUNT=0 \
        VERIFY_JSCPD=0 VERIFY_PLAN_STATUS=0 VERIFY_SECRETS=0 VERIFY_LINKS=0 \
        bash "$SCRIPT" "$TMP_DIR"
    [[ "$output" == *"Skipping broad security scanners in changed scope."* ]]
    [[ "$output" == *"VERIFY_SECURITY=1"* ]]
    [[ "$output" != *"STUB-TRIVY-RAN"* ]]
    [[ "$output" != *"STUB-SEMGREP-RAN"* ]]
    [[ "$output" != *"STUB-OSV-SCANNER-RAN"* ]]
    [ "$status" -eq 0 ]
}

@test "VERIFY_SECURITY=1: broad scanners run even in changed scope" {
    new_git_fixture "$TMP_DIR"
    stub_scanners
    run env PATH="$STUB_BIN:$PATH" AI_VERIFY_SCOPE=changed VERIFY_SECURITY=1 \
        VERIFY_LINECOUNT=0 VERIFY_JSCPD=0 VERIFY_PLAN_STATUS=0 VERIFY_SECRETS=0 \
        VERIFY_LINKS=0 bash "$SCRIPT" "$TMP_DIR"
    [[ "$output" != *"Skipping broad security scanners"* ]]
    [[ "$output" == *"STUB-TRIVY-RAN"* ]]
    [[ "$output" == *"STUB-SEMGREP-RAN"* ]]
    [[ "$output" == *"STUB-OSV-SCANNER-RAN"* ]]
    [ "$status" -eq 0 ]
}

@test "AI_VERIFY_SCOPE=all: broad scanners still run without VERIFY_SECURITY (unchanged behavior)" {
    new_git_fixture "$TMP_DIR"
    stub_scanners
    run env PATH="$STUB_BIN:$PATH" AI_VERIFY_SCOPE=all VERIFY_LINECOUNT=0 \
        VERIFY_JSCPD=0 VERIFY_PLAN_STATUS=0 VERIFY_SECRETS=0 VERIFY_LINKS=0 \
        bash "$SCRIPT" "$TMP_DIR"
    [[ "$output" != *"Skipping broad security scanners"* ]]
    [[ "$output" == *"STUB-TRIVY-RAN"* ]]
    [ "$status" -eq 0 ]
}

# --- 90-run.sh fix: broadened Biome detection (full-pipeline integration) ----
#
# pnpm is stubbed so a real `pnpm exec biome check .` is never attempted (the
# real command would trigger pnpm's implicit install/network fetch for an
# unresolved dependency); only whether the step is *attempted* (its "==>"
# label is echoed by run_step before the stub runs) is asserted.

stub_pnpm() {
    cat >"$STUB_BIN/pnpm" <<'EOF'
#!/usr/bin/env bash
echo "STUB-PNPM-RAN $*"
exit 0
EOF
    chmod +x "$STUB_BIN/pnpm"
}

@test "biome check runs when package.json declares @biomejs/biome" {
    new_git_fixture "$TMP_DIR"
    stub_pnpm
    write_package_json '{"devDependencies":{"@biomejs/biome":"^1.9.0"}}'
    run env PATH="$STUB_BIN:$PATH" AI_VERIFY_SCOPE=changed VERIFY_LINECOUNT=0 \
        VERIFY_JSCPD=0 VERIFY_PLAN_STATUS=0 VERIFY_SECRETS=0 VERIFY_LINKS=0 \
        bash "$SCRIPT" "$TMP_DIR"
    [[ "$output" == *"pnpm exec biome check ."* ]]
    [[ "$output" == *"STUB-PNPM-RAN exec biome check ."* ]]
}

@test "biome check is skipped when package.json has only unrelated dependencies" {
    new_git_fixture "$TMP_DIR"
    stub_pnpm
    write_package_json '{"devDependencies":{"lodash":"^4.0.0"}}'
    run env PATH="$STUB_BIN:$PATH" AI_VERIFY_SCOPE=changed VERIFY_LINECOUNT=0 \
        VERIFY_JSCPD=0 VERIFY_PLAN_STATUS=0 VERIFY_SECRETS=0 VERIFY_LINKS=0 \
        bash "$SCRIPT" "$TMP_DIR"
    [[ "$output" != *"biome check ."* ]]
}
