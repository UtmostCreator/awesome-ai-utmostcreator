# shellcheck shell=bash
# Step execution helpers for the AI verification gate.
#
# This module is sourced by scripts/ai/ai-verify.sh (the thin root loader);
# it is NOT an entrypoint and must not be executed directly. It is sourced
# AFTER common.sh so it may use run_guarded / run_with_timeout / log_* helpers.
# run_step mutates the global $failures tally and exports $last_step_rc.
#
# Behavior is byte-for-byte identical to the previous monolithic ai-verify.sh;
# only the file layout changed. The $last_step_rc=0 initialization keeps the same
# top-level ordering it had in the monolith (declared between run_step and
# run_step_js).

run_step() {
    local label="$1"
    shift

    echo "==> $label"

    # Run under the hang/freeze watchdog: a hard wall-clock ceiling plus
    # idle-output + idle-CPU detection that kills a stuck process group. Set
    # VERIFY_GUARD=0 to fall back to the plain wall-clock timeout wrapper.
    local rc=0
    if [[ "${VERIFY_GUARD:-1}" == "1" ]]; then
        AI_GUARD_TIMEOUT="${AI_GUARD_TIMEOUT:-$VERIFY_TIMEOUT}" run_guarded "$label" "$@" || rc=$?
    else
        run_with_timeout "$VERIFY_TIMEOUT" "$@" || rc=$?
    fi

    # Expose the last step's exit code without changing this function's own
    # return semantics: run_step has always effectively returned success so that
    # bare callers under `set -e` keep running every step and tally failures.
    last_step_rc="$rc"

    if ((rc != 0)); then
        echo "FAIL: $label failed (exit $rc)" >&2
        failures=$((failures + 1))
    fi
}

last_step_rc=0

# Wrapper for pnpm/JS verification steps. Behaves exactly like run_step (same
# streaming, watchdog, and failure counting) but, on failure, runs a focused
# private-registry auth diagnostic so a missing token does not masquerade as a
# typecheck/lint failure. Does not alter exit-code or failure-count behavior.
run_step_js() {
    local label="$1"
    run_step "$@"
    if ((last_step_rc != 0)); then
        diagnose_pnpm_auth "$label"
    fi
}

# Detect the common "implicit pnpm install hit a private registry without a
# token" failure mode. pnpm runs a deps-status check before `pnpm exec`, so an
# unset ${NPM_TOKEN} referenced by .npmrc surfaces as ERR_PNPM_FETCH_401 on a
# step that looks like a typecheck. This check is deterministic (it inspects
# .npmrc + env, not captured output) and only prints an advisory hint.
diagnose_pnpm_auth() {
    local label="${1:-pnpm step}"
    local npmrc found_ref="" referenced_var=""

    for npmrc in .npmrc "$HOME/.npmrc"; do
        [[ -f "$npmrc" ]] || continue
        # Find an auth line that interpolates an env var, e.g.
        #   //npm.pkg.github.com/:_authToken=${NPM_TOKEN}
        referenced_var="$(
            sed -n 's/.*_authToken=\${\([A-Za-z_][A-Za-z0-9_]*\)}.*/\1/p' "$npmrc" 2>/dev/null | head -n1
        )"
        if [[ -n "$referenced_var" ]]; then
            found_ref="$npmrc"
            break
        fi
    done

    [[ -n "$found_ref" ]] || return 0

    # If the referenced token variable is unset/empty, the implicit install will
    # fail with a 401 before the actual check runs.
    if [[ -z "${!referenced_var:-}" ]]; then
        log_warn "$label: '$found_ref' uses \${$referenced_var} for private-registry auth, but \$$referenced_var is unset."
        log_warn "$label: a 401/ERR_PNPM_FETCH_401 here is almost certainly missing registry auth, not a real type/lint error."
        log_warn "$label: set $referenced_var (token with read:packages) and re-run, e.g.: export $referenced_var=<token>; pnpm install"
    fi
}

has_package_script() {
    local script_name="${1:?script name required}"
    [[ -f package.json ]] || return 1
    jq -e --arg name "$script_name" '.scripts[$name] // empty' package.json >/dev/null 2>&1
}

has_package_dependency() {
    local package_name="${1:?package name required}"
    [[ -f package.json ]] || return 1
    jq -e --arg name "$package_name" '
      (.dependencies[$name] // .devDependencies[$name] // .peerDependencies[$name] // empty)
    ' package.json >/dev/null 2>&1
}
