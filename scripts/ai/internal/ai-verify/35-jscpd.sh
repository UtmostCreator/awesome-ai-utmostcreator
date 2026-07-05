# shellcheck shell=bash
# Optional jscpd-based code-duplication guardrail for the AI verification gate.
#
# This module is sourced by scripts/ai/ai-verify.sh (the thin root loader);
# it is NOT an entrypoint and must not be executed directly. It is sourced
# AFTER 30-linecount.sh (reuses linecount_scoped_files for the default,
# scope-aware path list) and AFTER 40-step-runner.sh (reuses run_with_timeout
# for the anti-freeze bound around the external jscpd process).
#
# Off by default (VERIFY_JSCPD=0): mirrors the opt-in posture of VERIFY_LINKS
# and VERIFY_SECRETS elsewhere in this pipeline. When enabled, jscpd is only
# fetched via `npx --yes jscpd` if a local `jscpd` binary is not already on
# PATH — never a silent/default network fetch (see docs/ai/approval-boundaries.md).
#
# Tiering mirrors 30-linecount.sh: WARN at JSCPD_WARN_PCT, and a hard
# verification FAILURE (increments $failures) only when JSCPD_FAIL_PCT is
# explicitly set and crossed. Leaving JSCPD_FAIL_PCT empty (the default) makes
# this check advisory-only.
#
# Known limitation, documented honestly rather than silently: jscpd's markdown
# reporter only tokenizes fenced code blocks, not prose, so this check measures
# code/example-fence duplication, not full prose duplication (see
# v0.6-plan/arch-todo-v0-6-shipped-surface-program-20260704-000001/plan.md).
check_jscpd() {
    [[ "$VERIFY_JSCPD" == "1" ]] || {
        log_warn "Skipping jscpd duplication check. Use VERIFY_JSCPD=1 to enable."
        return 0
    }

    echo "==> jscpd"

    local jscpd_cmd=()
    if command -v jscpd >/dev/null 2>&1; then
        jscpd_cmd=(jscpd)
    elif command -v npx >/dev/null 2>&1; then
        jscpd_cmd=(npx --yes jscpd)
    else
        log_warn "jscpd not found and npx unavailable; skipping duplication check."
        return 0
    fi

    local paths=()
    if [[ -n "$JSCPD_PATHS" ]]; then
        # shellcheck disable=SC2206 # intentional word-splitting of a user-set path list
        paths=($JSCPD_PATHS)
    else
        while IFS= read -r f; do
            [[ -n "$f" ]] || continue
            [[ -f "$f" ]] || continue
            paths+=("$f")
        done < <(linecount_scoped_files)
        # jscpd requires at least one path; fall back to "." when the scoped
        # file list is empty (e.g. nothing changed in the current scope).
        ((${#paths[@]} > 0)) || paths=(.)
    fi

    local report_dir
    report_dir="$(mktemp -d 2>/dev/null || printf '/tmp/jscpd-report-%s' "$$")"
    # shellcheck disable=SC2064 # intentional early expansion: report_dir is fixed per invocation
    trap "rm -rf '$report_dir'" RETURN

    # .gitignore is respected by default (jscpd only exposes --no-gitignore to
    # disable that); no explicit flag is needed to enable it.
    local rc=0
    run_with_timeout "$VERIFY_TIMEOUT" "${jscpd_cmd[@]}" "${paths[@]}" \
        --reporters json \
        --output "$report_dir" \
        --min-tokens "$JSCPD_MIN_TOKENS" \
        --silent \
        >/dev/null 2>&1 || rc=$?

    local report_file="$report_dir/jscpd-report.json"
    if [[ ! -f "$report_file" ]]; then
        log_warn "jscpd produced no report (exit $rc); skipping duplication check."
        return 0
    fi

    local percentage
    percentage="$(jq -r '.statistics.total.percentage // 0' "$report_file" 2>/dev/null || echo 0)"

    local warn_hit=0 fail_hit=0
    if awk -v p="$percentage" -v w="$JSCPD_WARN_PCT" 'BEGIN{exit !(p>=w)}' </dev/null; then
        warn_hit=1
    fi
    if [[ -n "$JSCPD_FAIL_PCT" ]] && awk -v p="$percentage" -v f="$JSCPD_FAIL_PCT" 'BEGIN{exit !(p>=f)}' </dev/null; then
        fail_hit=1
    fi

    if ((fail_hit)); then
        log_error "jscpd duplication = ${percentage}% >= ${JSCPD_FAIL_PCT}% (fail threshold)"
        failures=$((failures + 1))
        log_json "verify.jscpd" "$(jq -cn --argjson pct "$percentage" --argjson fail_threshold "$JSCPD_FAIL_PCT" '{percentage:$pct, fail_threshold:$fail_threshold, status:"fail"}')" || true
    elif ((warn_hit)); then
        log_warn "jscpd duplication = ${percentage}% >= ${JSCPD_WARN_PCT}% (advisory; set JSCPD_FAIL_PCT to enforce)"
        log_json "verify.jscpd" "$(jq -cn --argjson pct "$percentage" --argjson warn_threshold "$JSCPD_WARN_PCT" '{percentage:$pct, warn_threshold:$warn_threshold, status:"warn"}')" || true
    else
        log_ok "jscpd duplication = ${percentage}% (under ${JSCPD_WARN_PCT}%)"
    fi
}
