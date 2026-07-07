#!/usr/bin/env bash
# List ticket plan files (docs/tickets/**/plan*.md) with incomplete
# "## Todo Plan" or "## Acceptance Criteria" checklist items, and archive
# fully-complete plan files into their ticket's archive/ folder.
#
# Companion to scripts/ai/internal/ai-verify/36-plan-status.sh (the ai-verify
# guardrail): that check is diff-scoped (only files in the current
# changed/staged/branch scope) and reports one combined OK/WARN/ERROR per
# file. This script scans the whole ROOT tree (default docs/tickets)
# independent of git scope, reports the "## Todo Plan" and
# "## Acceptance Criteria" sections separately (so a file with only stale ACs
# left open is distinguishable from one with only stale Todo items), and adds
# a guarded archive/ mover for fully-complete files, following
# docs/tickets/README.md's `archive/DONE-plan-{n}-{short-desc}.md` convention.
#
# Modes (first positional argument, default: todos):
#   todos    print one path per line for files with >=1 unchecked
#            "## Todo Plan" checklist item.
#   acs      print one path per line for files with >=1 unchecked
#            "## Acceptance Criteria" checklist item.
#   all      print every in-scope file incomplete in either section, with a
#            per-section checked/unchecked breakdown.
#   archive  list (default, read-only) or move ("--apply") fully-complete
#            plan files into <ticket-dir>/archive/DONE-<basename>.
#
# Read-only in every mode except "archive --apply".

set -euo pipefail

# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

SCRIPT_NAME="list-todos"

usage() {
    cat <<'EOF'
Usage:
  scripts/ai/list-todos.sh [MODE] [ROOT] [OPTIONS]

Modes (default: todos):
  todos     print one path per line for files with >=1 unchecked
            "## Todo Plan" checklist item.
  acs       print one path per line for files with >=1 unchecked
            "## Acceptance Criteria" checklist item.
  all       print every in-scope file incomplete in either section, with a
            per-section checked/unchecked breakdown.
  archive   list (default) or move ("--apply") fully-complete plan files into
            <ticket-dir>/archive/DONE-<basename>, per docs/tickets/README.md.
            "Fully complete" means: at least one checklist item was found
            across both sections, and no unchecked item remains in either
            section that is present in the file.

ROOT (default: docs/tickets) - directory tree to scan for `plan*.md` files.
Files already under any `archive/` directory are always excluded from
scanning (they are done by definition).

Options:
  --format plain|json   output format for todos/acs/all modes (default: plain)
  --apply               (archive mode only) perform the move; default is a
                         read-only listing of candidates and their
                         destination path
  --force               (archive mode + --apply only) overwrite an existing
                         destination file instead of skipping it
  --help, -h            show this help and exit 0

Exit codes:
  0  scan/listing/archive completed (incomplete items or archive candidates
     found is normal output, not a failure)
  2  invalid arguments
EOF
}

require_bins awk find

MODE="todos"
ROOT="docs/tickets"
FORMAT="plain"
APPLY=0
FORCE=0
mode_set=0
root_set=0

while [[ $# -gt 0 ]]; do
    case "$1" in
    --help | -h)
        usage
        exit 0
        ;;
    --format)
        FORMAT="${2:-plain}"
        shift 2
        ;;
    --format=*)
        FORMAT="${1#*=}"
        shift
        ;;
    --apply)
        APPLY=1
        shift
        ;;
    --force)
        FORCE=1
        shift
        ;;
    --*)
        echo "Unknown option: $1" >&2
        usage >&2
        exit 2
        ;;
    todos | acs | all | archive)
        if ((mode_set == 1)); then
            echo "Only one mode may be given (got '$1' after a mode was already set)" >&2
            exit 2
        fi
        MODE="$1"
        mode_set=1
        shift
        ;;
    *)
        if ((root_set == 1)); then
            echo "Unknown argument: $1" >&2
            usage >&2
            exit 2
        fi
        ROOT="$1"
        root_set=1
        shift
        ;;
    esac
done

case "$FORMAT" in
plain | json) ;;
*)
    echo "invalid --format: $FORMAT (expected plain or json)" >&2
    exit 2
    ;;
esac

if [[ ! -d "$ROOT" ]]; then
    echo "ROOT not found or not a directory: $ROOT" >&2
    exit 2
fi

# ---- File discovery ------------------------------------------------------
# Mirrors scripts/ai/internal/ai-verify/36-plan-status.sh's
# is_plan_status_target (docs/tickets/*/plan*.md, archive/ excluded), but
# generalized to any ROOT and to the whole tree (this script is not
# diff-scoped).
is_todo_target() {
    local rel="$1"
    case "$rel" in
    */archive/* | archive/*) return 1 ;;
    esac
    return 0
}

collect_target_files() {
    local f
    while IFS= read -r f; do
        [[ -n "$f" ]] || continue
        is_todo_target "$f" || continue
        printf '%s\n' "$f"
    done < <(find "$ROOT" -type f -name 'plan*.md' 2>/dev/null | LC_ALL=C sort)
}

# Print "todo_checked<TAB>todo_unchecked<TAB>acs_checked<TAB>acs_unchecked" for
# the "## Todo Plan" and "## Acceptance Criteria" sections of $1, each section
# running from its heading to the next "## " heading or EOF. A file missing
# one of the two headings reports 0/0 for that section (neither blocks the
# other from being reported as incomplete or archivable).
section_checklist_counts() {
    local file="$1"
    awk '
        /^## / {
            line = $0
            sub(/[[:space:]]+$/, "", line)
            if (line == "## Todo Plan") { sec = "todo" }
            else if (line == "## Acceptance Criteria") { sec = "acs" }
            else { sec = "" }
            next
        }
        sec != "" && match($0, /^[[:space:]]*-[[:space:]]+\[[xX ]\]/) {
            box = substr($0, RSTART, RLENGTH)
            checked = (box ~ /\[[xX]\]$/)
            if (sec == "todo") { if (checked) { tc++ } else { tu++ } }
            else                { if (checked) { ac++ } else { au++ } }
        }
        END { printf "%d\t%d\t%d\t%d\n", tc+0, tu+0, ac+0, au+0 }
    ' "$file"
}

# Read a JSON-lines-ish "path<TAB>tc<TAB>tu<TAB>ac<TAB>au" blob from stdin and
# emit a single envelope: {schema, tool, mode, items[], count}.
emit_json() {
    local mode="$1"
    require_bins jq
    jq -R -s --arg tool "$SCRIPT_NAME" --arg mode "$mode" '
        (split("\n") | map(select(length > 0)) | map(split("\t")) | map({
            path: .[0],
            todo_checked: (.[1] | tonumber),
            todo_unchecked: (.[2] | tonumber),
            acs_checked: (.[3] | tonumber),
            acs_unchecked: (.[4] | tonumber)
        })) as $items
        | {schema: "1", tool: $tool, mode: $mode, items: $items, count: ($items | length)}
    '
}

run_todos() {
    local file counts tu rows=""
    while IFS= read -r file; do
        [[ -n "$file" ]] || continue
        counts="$(section_checklist_counts "$file")"
        tu="$(printf '%s' "$counts" | cut -f2)"
        ((tu > 0)) || continue
        if [[ "$FORMAT" == "json" ]]; then
            rows+="$file"$'\t'"$counts"$'\n'
        else
            printf '%s\n' "$file"
        fi
    done < <(collect_target_files)
    [[ "$FORMAT" == "json" ]] && printf '%s' "$rows" | emit_json "todos"
    return 0
}

run_acs() {
    local file counts au rows=""
    while IFS= read -r file; do
        [[ -n "$file" ]] || continue
        counts="$(section_checklist_counts "$file")"
        au="$(printf '%s' "$counts" | cut -f4)"
        ((au > 0)) || continue
        if [[ "$FORMAT" == "json" ]]; then
            rows+="$file"$'\t'"$counts"$'\n'
        else
            printf '%s\n' "$file"
        fi
    done < <(collect_target_files)
    [[ "$FORMAT" == "json" ]] && printf '%s' "$rows" | emit_json "acs"
    return 0
}

run_all() {
    local file counts tc tu ac au rows=""
    while IFS= read -r file; do
        [[ -n "$file" ]] || continue
        counts="$(section_checklist_counts "$file")"
        tc="$(printf '%s' "$counts" | cut -f1)"
        tu="$(printf '%s' "$counts" | cut -f2)"
        ac="$(printf '%s' "$counts" | cut -f3)"
        au="$(printf '%s' "$counts" | cut -f4)"
        ((tu > 0 || au > 0)) || continue
        if [[ "$FORMAT" == "json" ]]; then
            rows+="$file"$'\t'"$counts"$'\n'
        else
            printf '%s\ttodo:%s/%s\tacs:%s/%s\n' "$file" "$tu" "$tc" "$au" "$ac"
        fi
    done < <(collect_target_files)
    [[ "$FORMAT" == "json" ]] && printf '%s' "$rows" | emit_json "all"
    return 0
}

run_archive() {
    local file counts tc tu ac au dir base dest moved=0 candidates=0
    while IFS= read -r file; do
        [[ -n "$file" ]] || continue
        counts="$(section_checklist_counts "$file")"
        tc="$(printf '%s' "$counts" | cut -f1)"
        tu="$(printf '%s' "$counts" | cut -f2)"
        ac="$(printf '%s' "$counts" | cut -f3)"
        au="$(printf '%s' "$counts" | cut -f4)"

        # Not archivable: no checklist items anywhere (ambiguous), or any
        # unchecked item remains in a section that is present in the file.
        ((tc + tu + ac + au > 0)) || continue
        ((tu == 0)) || continue
        ((au == 0)) || continue

        candidates=$((candidates + 1))
        dir="$(dirname "$file")"
        base="${file##*/}"
        case "$base" in
        DONE-*) dest="$dir/archive/$base" ;;
        *) dest="$dir/archive/DONE-$base" ;;
        esac

        if ((APPLY == 0)); then
            printf '%s -> %s\n' "$file" "$dest"
            continue
        fi

        if [[ -e "$dest" && "$FORCE" != "1" ]]; then
            log_warn "destination already exists, skipping (use --force to overwrite): $dest"
            continue
        fi

        mkdir -p "$dir/archive"
        if git rev-parse --is-inside-work-tree >/dev/null 2>&1 &&
            git ls-files --error-unmatch "$file" >/dev/null 2>&1; then
            git mv -f -- "$file" "$dest"
        else
            mv -f -- "$file" "$dest"
        fi
        log_ok "archived: $file -> $dest"
        moved=$((moved + 1))
    done < <(collect_target_files)

    if ((APPLY == 1)); then
        log_ok "archive complete: $moved file(s) moved"
    elif ((candidates == 0)); then
        echo "No fully-complete plan files found under: $ROOT" >&2
    fi
    return 0
}

case "$MODE" in
todos) run_todos ;;
acs) run_acs ;;
all) run_all ;;
archive) run_archive ;;
esac
