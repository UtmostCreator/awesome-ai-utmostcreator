#!/usr/bin/env bash
# Compatibility test for the scripts/ai/common.sh facade split.
# Verifies that after splitting common.sh into scripts/ai/lib/*.sh:
#   - every public function in the inventory still resolves,
#   - the facade can be sourced twice (idempotent source guards),
#   - the facade can be sourced from a subdirectory (BASH_SOURCE-relative lib dir).
# Run: bash tests/scripts/ai/test-common-source.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
COMMON_SH="$REPO_ROOT/scripts/ai/common.sh"
BASH_BIN="${BASH_BIN:-$(command -v bash)}"

PASS=0
FAIL=0

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

# Full public-function inventory that must survive the split. Private
# guard/snapshot helpers are intentionally excluded: they are internal-only and
# were renamed to the _ai_* prefix during the refactor.
REQUIRED_FUNCTIONS=(
    agent_session_init
    append_log_entry
    log_json
    log_info
    log_ok
    log_warn
    log_error
    command_exists
    require_bash_version
    json_available
    find_timeout_bin
    find_fd_bin
    now_ms
    redact_sensitive_text
    redact_json_payload
    json_compact_or_raw
    emit_envelope
    emit_blocked_envelope
    rotate_log_if_needed_locked
    append_jsonl_safe
    repo_root
    classify_command
    approval_env_for_category
    command_basename
    realpath_safe
    assert_inside_repo
    repo_relative_path
    assert_relative_safe_path
    path_matches_protected_pattern
    estimate_tokens_string
    truncate_file_preview
    require_approval
    enforce_command_policy
    wait_for_capture_flag
    die
    section
    require_bins
    require_clean_tree
    git_root
    run_with_timeout
    run_guarded
    require_clean_secret_scan
    estimate_file_tokens_fallback
    estimate_tokens
    within_token_budget
    secrets_scan
    snapshot_create
    snapshot_apply_manifest
    snapshot_apply
)

# 1. Source from repo root; assert every public function resolves.
test_all_functions_resolve() {
    "$BASH_BIN" -c "
        export NO_COLOR=1
        source '$COMMON_SH'
        missing=0
        for fn in ${REQUIRED_FUNCTIONS[*]}; do
            if ! declare -F \"\$fn\" >/dev/null 2>&1; then
                echo \"missing function: \$fn\" >&2
                missing=1
            fi
        done
        exit \$missing
    "
}
run_test "all public functions resolve after split" test_all_functions_resolve

# 2. Sourcing twice must be idempotent (source guards prevent re-running),
#    and functions must still resolve afterward.
test_double_source_idempotent() {
    "$BASH_BIN" -c "
        export NO_COLOR=1
        source '$COMMON_SH'
        source '$COMMON_SH'
        declare -F die >/dev/null
        declare -F run_guarded >/dev/null
        declare -F snapshot_apply >/dev/null
    "
}
run_test "facade can be sourced twice (idempotent guards)" test_double_source_idempotent

# 3. Source from a subdirectory: the lib dir is resolved from BASH_SOURCE, not
#    from the caller's CWD, so a cd into an unrelated dir must not break loading.
test_source_from_subdirectory() {
    local tmpd
    tmpd="$(mktemp -d "${TMPDIR:-/tmp}/test-common-source.XXXXXX")"
    "$BASH_BIN" -c "
        export NO_COLOR=1
        cd '$tmpd'
        source '$COMMON_SH'
        declare -F emit_envelope >/dev/null
        declare -F log_json >/dev/null
    "
    local rc=$?
    rm -rf "$tmpd"
    return "$rc"
}
run_test "facade can be sourced from a subdirectory" test_source_from_subdirectory

# 4. Sanity: a couple of moved functions actually work end-to-end after the
#    split (envelope emission round-trips valid JSON via jq).
test_emit_envelope_valid_json() {
    "$BASH_BIN" -c "
        export NO_COLOR=1
        source '$COMMON_SH'
        out=\"\$(emit_envelope ok test-tool '{\"k\":1}')\"
        printf '%s' \"\$out\" | jq -e '.status == \"ok\" and .tool == \"test-tool\"' >/dev/null
    "
}
run_test "emit_envelope still emits valid JSON after split" test_emit_envelope_valid_json

printf '\n=== Results ===\n'
printf '  Passed:  %d\n' "$PASS"
printf '  Failed:  %d\n' "$FAIL"

if ((FAIL > 0)); then
    printf '\n\033[0;31mFAILED\033[0m\n'
    exit 1
fi
printf '\n\033[0;32mPASSED\033[0m\n'
exit 0
