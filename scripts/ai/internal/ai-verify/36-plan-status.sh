# shellcheck shell=bash
# Todo-plan checklist status guardrail for the AI verification gate.
#
# This module is sourced by scripts/ai/ai-verify.sh (the thin root loader);
# it is NOT an entrypoint and must not be executed directly. It is sourced
# AFTER common.sh (for log_* / log_json) and AFTER 10-scope.sh (for
# linecount_scoped_files, reused here for the same scope-aware file list).
#
# Purpose: architecture-plan-writer and implementer both work against Todo
# markdown plans under docs/tickets/**/plan*.md (`- [ ]` / `- [x]` checklist
# items in `## Todo Plan` and `## Acceptance Criteria`). This check scans any
# such plan file that is in scope and reports:
#   - OK   when every checklist item in the file is checked (`- [x]`)
#   - WARN when some items are still unchecked (`- [ ]`) — incomplete, not a
#     hard failure, since a plan is normally read/updated across many sessions
#   - ERROR (hard verification failure, increments $failures) when a checklist
#     item itself (a `- [ ]`/`- [x]` line, not the surrounding prose) contains
#     a difficulty-notice phrase (see PLAN_STATUS_DIFFICULTY_PATTERN) such as
#     "impossible", "not enough context", or "missing <context|information|...>"
#     — these mark a Todo/AC item an agent could not carry out as written and
#     that a human should look at. Scanning is deliberately limited to
#     checklist-item lines: scanning the whole file would also match ordinary,
#     benign prose (e.g. a plan's own `Status: ... not implemented` header,
#     which just means "not started yet", not "in difficulty").
#
# This intentionally does not require a new top-level scripts/ai/*.sh
# entrypoint or any agent permission change: implementer already has
# permission to invoke ai-verify.sh (see .opencode/agents/implementer.md), so
# folding the check in here is enough to "wire it into implementer" per
# docs/tickets discussion. architecture-plan-writer is not wired directly in
# this slice (its bash allowlist stays minimal by design); extending that is a
# separate, larger permission decision.
#
# Off-by-default posture is NOT used here (unlike VERIFY_JSCPD/VERIFY_LINKS):
# this check needs no external tool, only grep/awk over already-scoped text
# files, so it is cheap enough to default on like VERIFY_LINECOUNT.

# Default docs/tickets plan-file glob, scoped to top-level ticket folders'
# plan.md / plan-N-desc.md files. Archived (already-complete) plans under any
# archive/ subfolder are intentionally excluded — they are done by definition.
#
# Deliberately does NOT include a bare "not implemented" — that phrase alone is
# the normal, expected description of any not-yet-started Todo/AC item and
# would false-positive on nearly every fresh plan. The narrower "cannot ... be
# implemented" / "unable to implement" forms below capture the genuine
# difficulty case (an item the agent tried and could not carry out).
PLAN_STATUS_DIFFICULTY_PATTERN="${PLAN_STATUS_DIFFICULTY_PATTERN:-impossible|not enough context|missing (context|information|details?|requirements?|decision|owner|approval)|insufficient (context|information|detail)|cannot be (done|completed|implemented)|unable to (implement|complete|proceed)|no clear (owner|path forward)|blocked by unknown}"

# True when $1 is an in-scope Todo plan file this check should inspect:
# docs/tickets/<folder>/plan*.md, excluding anything under an archive/ dir.
is_plan_status_target() {
    case "$1" in
    docs/tickets/*/archive/* | */archive/*) return 1 ;;
    docs/tickets/*/plan*.md) return 0 ;;
    esac
    return 1
}

# Print "checked<TAB>unchecked" counts of top-level Markdown task items
# (`- [ ]` / `- [x]`, optionally indented) in the given file.
plan_status_checklist_counts() {
    local file="$1"
    awk '
        match($0, /^[[:space:]]*-[[:space:]]+\[[xX ]\]/) {
            box = substr($0, RSTART, RLENGTH)
            if (box ~ /\[[xX]\]$/) { checked++ } else { unchecked++ }
        }
        END { printf "%d\t%d\n", checked + 0, unchecked + 0 }
    ' "$file"
}

# Print "line: text" for each *checklist-item* line (`- [ ]`/`- [x]`, optionally
# indented) that also matches PLAN_STATUS_DIFFICULTY_PATTERN (case-insensitive),
# used as evidence in the ERROR report. Only checklist-item lines are scanned —
# see the header comment for why the rest of the file's prose is out of scope.
plan_status_difficulty_hits() {
    local file="$1"
    awk -v pat="$PLAN_STATUS_DIFFICULTY_PATTERN" '
        match($0, /^[[:space:]]*-[[:space:]]+\[[xX ]\]/) {
            if (tolower($0) ~ pat) { print NR ": " $0 }
        }
    ' "$file"
}

check_plan_status() {
    [[ "$VERIFY_PLAN_STATUS" == "1" ]] || {
        log_warn "Skipping plan Todo-status check. Use VERIFY_PLAN_STATUS=1 to enable."
        return 0
    }

    echo "==> plan-status"

    local -a plan_files=()
    while IFS= read -r f; do
        [[ -n "$f" ]] || continue
        [[ -f "$f" ]] || continue
        is_plan_status_target "$f" || continue
        plan_files+=("$f")
    done < <(linecount_scoped_files)

    if ((${#plan_files[@]} == 0)); then
        log_ok "plan-status: no in-scope docs/tickets/**/plan*.md files"
        return 0
    fi

    local file counts checked unchecked hits
    local files_error=0 files_warn=0 files_ok=0

    for file in "${plan_files[@]}"; do
        # `|| true`: grep exits non-zero on no match, which under this script's
        # `set -e` would otherwise abort the whole check on the (common) case
        # of a clean file with no difficulty notices.
        hits="$(plan_status_difficulty_hits "$file" || true)"
        if [[ -n "$hits" ]]; then
            log_error "plan-status $file: difficulty notice found (blocks completion):"
            while IFS= read -r hit; do
                [[ -n "$hit" ]] || continue
                echo "    $hit" >&2
            done <<<"$hits"
            files_error=$((files_error + 1))
            continue
        fi

        counts="$(plan_status_checklist_counts "$file")"
        checked="${counts%%$'\t'*}"
        unchecked="${counts##*$'\t'}"

        if ((checked == 0 && unchecked == 0)); then
            log_warn "plan-status $file: no \`- [ ]\`/\`- [x]\` checklist items found"
            continue
        elif ((unchecked > 0)); then
            log_warn "plan-status $file: $unchecked incomplete / $checked complete Todo item(s)"
            files_warn=$((files_warn + 1))
        else
            log_ok "plan-status $file: all $checked Todo item(s) complete"
            files_ok=$((files_ok + 1))
        fi
    done

    if ((files_error > 0)); then
        echo "FAIL: plan-status $files_error plan file(s) flagged with difficulty notices" >&2
        failures=$((failures + files_error))
    elif ((files_warn > 0)); then
        log_warn "plan-status: $files_warn plan file(s) still have incomplete Todo items"
    elif ((files_ok > 0)); then
        log_ok "plan-status: all ${#plan_files[@]} in-scope plan file(s) fully complete"
    fi

    log_json "verify.plan_status" "$(jq -cn \
        --argjson total "${#plan_files[@]}" \
        --argjson ok "$files_ok" \
        --argjson warn "$files_warn" \
        --argjson error "$files_error" \
        '{total:$total, ok:$ok, warn:$warn, error:$error}')" || true
}
