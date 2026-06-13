#!/usr/bin/env bash
# Bundle the --help (or --introspect) output of every *.sh tool under one or more
# scan directories into a single file. The file list is discovered dynamically
# (no hardcoded list), so it never drifts when scripts are added or removed. The
# target scripts are run with --help/--introspect ONLY, which the universal early
# guards handle without executing any script's real logic.
#
# Usage:
#   bash scripts/ai/build-ai-help-bundle.sh [--mode help|introspect]
#                                           [--scan-dir DIR]... [--output PATH]
#
#   # default: scan scripts/ai only, write all-scripts-help-<timestamp>.txt
#   bash scripts/ai/build-ai-help-bundle.sh
#
#   # scan extra directories (repeatable); default scan dir is dropped once you
#   # pass --scan-dir, so pass scripts/ai explicitly if you still want it
#   bash scripts/ai/build-ai-help-bundle.sh --scan-dir scripts/ai --scan-dir tools/bin
#
#   bash scripts/ai/build-ai-help-bundle.sh --mode introspect -o .ai-logs/introspect.txt
#
# Output format per file:
#   ===== <path> =====
#   <--help or --introspect output>
#   ===
#
# --help/-h and --introspect on THIS script report its own contract (early
# guard), matching the repo-wide convention.

set -euo pipefail

# Early --introspect guard: emit this script's machine-readable JSON contract
# (static parse via sh-introspect) and exit before running any logic.
if [[ "${1:-}" == "--introspect" ]]; then
    _ai_introspect_here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    _ai_introspect_tool="$_ai_introspect_here/../../tools/ai/sh-introspect.php"
    if [[ -f "$_ai_introspect_tool" ]] && command -v "${PHP_BIN:-php}" >/dev/null 2>&1; then
        exec env AI_OUTPUT=json "${PHP_BIN:-php}" "$_ai_introspect_tool" "${BASH_SOURCE[0]}"
    fi
fi

# Early --help/-h guard: emit this script's human-readable contract (the static
# introspector's compact --format=help view) and exit before running any logic.
if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    _ai_help_here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    _ai_help_tool="$_ai_help_here/../../tools/ai/sh-introspect.php"
    if [[ -f "$_ai_help_tool" ]] && command -v "${PHP_BIN:-php}" >/dev/null 2>&1; then
        exec "${PHP_BIN:-php}" "$_ai_help_tool" --format=help "${BASH_SOURCE[0]}"
    fi
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(git -C "$SCRIPT_DIR" rev-parse --show-toplevel 2>/dev/null || true)"
if [[ -z "$REPO_ROOT" ]]; then
    REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
fi
cd "$REPO_ROOT"

mode="help"
output_file="" # default computed below (timestamped) when not given
scan_dirs=()   # default applied below when none given

while (($# > 0)); do
    case "$1" in
    --mode)
        [[ $# -ge 2 ]] || {
            echo "ERROR: --mode requires a value (help|introspect)" >&2
            exit 2
        }
        mode="$2"
        shift 2
        ;;
    --mode=*)
        mode="${1#*=}"
        shift
        ;;
    --scan-dir)
        [[ $# -ge 2 ]] || {
            echo "ERROR: --scan-dir requires a directory path" >&2
            exit 2
        }
        scan_dirs+=("$2")
        shift 2
        ;;
    --scan-dir=*)
        scan_dirs+=("${1#*=}")
        shift
        ;;
    --output | -o)
        [[ $# -ge 2 ]] || {
            echo "ERROR: --output requires a file path" >&2
            exit 2
        }
        output_file="$2"
        shift 2
        ;;
    --output=*)
        output_file="${1#*=}"
        shift
        ;;
    *)
        echo "ERROR: unknown argument: $1 (expected --mode help|introspect, --scan-dir DIR, --output PATH)" >&2
        exit 2
        ;;
    esac
done

case "$mode" in
help) flag="--help" ;;
introspect) flag="--introspect" ;;
*)
    echo "ERROR: unknown --mode: $mode (expected help or introspect)" >&2
    exit 2
    ;;
esac

# Default scan directory is scripts/ai only (relative to repo root).
if ((${#scan_dirs[@]} == 0)); then
    scan_dirs=("scripts/ai")
fi

# Default output file is all-scripts-help-<timestamp>.txt in the gitignored
# evidence root when --output is not specified.
if [[ -z "$output_file" ]]; then
    output_file=".ai-logs/all-scripts-help-$(date +%Y%m%d-%H%M%S).txt"
fi

# Discover *.sh files under each scan directory (sorted, deduplicated). lib/
# subdirectories hold internal sourced modules with no runnable --help surface,
# so they are excluded. No hardcoded file list.
declare -A seen=()
files=()
for dir in "${scan_dirs[@]}"; do
    if [[ ! -d "$dir" ]]; then
        echo "WARNING: scan directory not found, skipping: $dir" >&2
        continue
    fi
    while IFS= read -r found; do
        [[ -z "$found" ]] && continue
        # strip leading ./ for stable paths
        found="${found#./}"
        if [[ -z "${seen[$found]:-}" ]]; then
            seen[$found]=1
            files+=("$found")
        fi
    done < <(find "$dir" -type f -name '*.sh' -not -path '*/lib/*' | sort)
done

if ((${#files[@]} == 0)); then
    echo "ERROR: no *.sh scripts discovered under: ${scan_dirs[*]}" >&2
    exit 1
fi

out_dir="$(dirname "$output_file")"
if [[ "$out_dir" != "." ]]; then
    mkdir -p "$out_dir"
fi

: >"$output_file"
failures=0

for file in "${files[@]}"; do
    {
        printf '===== %s =====\n' "$file"
        if [[ ! -f "$file" ]]; then
            printf 'ERROR: file not found\n'
        else
            set +e
            bash "$file" "$flag"
            status=$?
            set -e
            if [[ "$status" -ne 0 ]]; then
                printf '\n[exit_status=%s]\n' "$status"
            fi
        fi
        printf '===\n'
    } >>"$output_file" 2>&1

    if [[ ! -f "$file" ]]; then
        failures=$((failures + 1))
    fi
done

echo "Bundle (mode=$mode) written to: $output_file"
echo "Scanned: ${scan_dirs[*]}"
echo "Discovered ${#files[@]} script(s)."

if (("$failures" > 0)); then
    echo "Completed with $failures missing file(s)." >&2
    exit 1
fi
