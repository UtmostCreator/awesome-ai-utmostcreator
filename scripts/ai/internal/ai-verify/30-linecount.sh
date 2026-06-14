# shellcheck shell=bash
# File line-count guardrail for the AI verification gate.
#
# This module is sourced by scripts/ai/ai-verify.sh (the thin root loader);
# it is NOT an entrypoint and must not be executed directly. It is sourced
# AFTER common.sh (for log_* / log_json) and AFTER 10-scope.sh (for
# linecount_scoped_files). It mutates the global $failures tally on hard errors.
#
# Behavior is byte-for-byte identical to the previous monolithic ai-verify.sh;
# only the file layout changed.

# Tiered file line-count guardrail. Counts lines per in-scope file and reports:
#   >= LINECOUNT_INFO  -> info  (heads-up)
#   >= LINECOUNT_WARN  -> warn  (should refactor soon)
#   >= LINECOUNT_ERROR -> error (urgent refactor; counts as a verification failure)
# Each file is reported at its highest matching tier only. Files that exceed the
# error threshold increment the global failure tally so ai-verify exits non-zero.
check_line_counts() {
    [[ "$VERIFY_LINECOUNT" == "1" ]] || {
        log_warn "Skipping line-count check. Use VERIFY_LINECOUNT=1 to enable."
        return 0
    }

    echo "==> line-count"

    local file lines errors=0 flagged=0
    while IFS= read -r file; do
        [[ -n "$file" ]] || continue
        [[ -f "$file" ]] || continue
        # Skip binary files: grep -Iq prints nothing and returns 1 for binaries.
        grep -Iq . "$file" 2>/dev/null || continue

        lines="$(wc -l <"$file" 2>/dev/null | tr -d ' ')"
        [[ "$lines" =~ ^[0-9]+$ ]] || continue

        if ((lines >= LINECOUNT_ERROR)); then
            log_error "line-count $file = $lines lines >= $LINECOUNT_ERROR (URGENT refactor needed)"
            errors=$((errors + 1))
            flagged=$((flagged + 1))
        elif ((lines >= LINECOUNT_WARN)); then
            log_warn "line-count $file = $lines lines >= $LINECOUNT_WARN (refactor recommended)"
            flagged=$((flagged + 1))
        elif ((lines >= LINECOUNT_INFO)); then
            log_info "line-count $file = $lines lines >= $LINECOUNT_INFO (getting large)"
            flagged=$((flagged + 1))
        fi
    done < <(linecount_scoped_files)

    if ((errors > 0)); then
        echo "FAIL: line-count $errors file(s) >= $LINECOUNT_ERROR lines (urgent refactor)" >&2
        failures=$((failures + errors))
        log_json "verify.linecount" "$(jq -cn --argjson errors "$errors" --argjson flagged "$flagged" \
            --argjson error_threshold "$LINECOUNT_ERROR" \
            '{errors:$errors, flagged:$flagged, error_threshold:$error_threshold}')" || true
    elif ((flagged == 0)); then
        log_ok "line-count: all in-scope files under $LINECOUNT_INFO lines"
    fi
}
