#!/usr/bin/env bash
# Find tracked files that are not referenced anywhere else in the repository.
# Read-only: surfaces orphaned docs and unused assets. No mutation.

set -euo pipefail

# Early --introspect / --help guard: when invoked with --introspect or --help/-h
# as the FIRST argument, emit this script's machine-readable JSON contract or its
# human-readable contract (static parse via sh-introspect) and exit before running
# any logic. The target script is parsed as text, never executed.
if [[ "${1:-}" == "--introspect" || "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    _ai_self_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    _ai_self_tool="$_ai_self_dir/../../tools/ai/sh-introspect.php"
    if [[ -f "$_ai_self_tool" ]] && command -v "${PHP_BIN:-php}" >/dev/null 2>&1; then
        if [[ "${1:-}" == "--introspect" ]]; then
            exec env AI_OUTPUT=json "${PHP_BIN:-php}" "$_ai_self_tool" "${BASH_SOURCE[0]}"
        fi
        exec "${PHP_BIN:-php}" "$_ai_self_tool" --format=help "${BASH_SOURCE[0]}"
    fi
fi
# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

require_bins git rg

usage() {
    cat <<'EOF'
Usage:
  check-file-refs.sh [path] [--format json|plain] [--ext EXT[,EXT]] [--all]

Find tracked files whose basename is not referenced by any other tracked file
(orphaned docs and unused assets). Read-only.

Options:
  path                 Limit the scan to tracked files under this path (default: .)
  --format json|plain  Output format (default: plain, one orphan path per line)
  --ext EXT[,EXT]      Only consider files with these extensions (e.g. md,png)
  --all                Consider every tracked file (default skips common
                       entrypoints that are referenced implicitly)
  --help, -h           Show this help

Exit codes:
  0  scan completed (orphans may or may not exist; see output)
EOF
}

scan_path="."
output_format="plain"
exts=()
include_all=0

while [[ $# -gt 0 ]]; do
    case "$1" in
    --help | -h)
        usage
        exit 0
        ;;
    --format)
        output_format="${2:-plain}"
        shift 2
        ;;
    --format=*)
        output_format="${1#*=}"
        shift
        ;;
    --ext)
        IFS=',' read -ra exts <<<"${2:-}"
        shift 2
        ;;
    --ext=*)
        IFS=',' read -ra exts <<<"${1#*=}"
        shift
        ;;
    --all)
        include_all=1
        shift
        ;;
    --*)
        die "unknown option: $1"
        ;;
    *)
        scan_path="$1"
        shift
        ;;
    esac
done

case "$output_format" in
plain | json) ;;
*) die "invalid --format: $output_format (expected json or plain)" ;;
esac

# Files that are referenced implicitly by tooling/conventions and should not be
# reported as orphans unless --all is passed.
is_implicit_entrypoint() {
    local base="$1"
    case "$base" in
    README.md | AGENTS.md | CLAUDE.md | LICENSE | LICENSE.md | CHANGELOG.md | \
        SECURITY.md | SUPPORT.md | CONTRIBUTING.md | .gitignore | .gitattributes | \
        .editorconfig | composer.json | composer.lock | phpunit.xml.dist | \
        justfile | llms.txt | opencode.jsonc | opencode.json)
        return 0
        ;;
    *.lock)
        return 0
        ;;
    esac
    return 1
}

# Collect candidate files (tracked, under scan_path, optionally ext-filtered).
mapfile -t candidates < <(git ls-files -- "$scan_path")

orphans=()
for path in "${candidates[@]+${candidates[@]}}"; do
    [[ -n "$path" ]] || continue
    base="${path##*/}"

    if [[ ${#exts[@]} -gt 0 ]]; then
        ext="${base##*.}"
        want=0
        for e in "${exts[@]}"; do
            [[ "$ext" == "$e" ]] && want=1 && break
        done
        [[ "$want" == "1" ]] || continue
    fi

    if [[ "$include_all" != "1" ]] && is_implicit_entrypoint "$base"; then
        continue
    fi

    # A file is referenced if its basename appears in any tracked file other
    # than itself. Fixed-string match; exclude the file's own path from hits.
    # Capture into a variable first: piping rg into `grep -q` lets grep close
    # the pipe early, which makes rg exit non-zero and (under pipefail) would
    # wrongly mark referenced files as orphans.
    hits="$(rg --no-messages --fixed-strings --files-with-matches -- "$base" . \
        -g '!vendor/**' -g '!node_modules/**' -g '!.git/**' \
        -g '!.repomix-context/**' 2>/dev/null || true)"

    # Strip rg's leading ./ and the file's own path, then check for any
    # remaining reference.
    other_refs="$(printf '%s\n' "$hits" | sed 's#^\./##' | grep -vxF "$path" || true)"

    if [[ -n "$other_refs" ]]; then
        continue
    fi

    orphans+=("$path")
done

if [[ "$output_format" == "json" ]]; then
    require_bins jq
    printf '%s\n' "${orphans[@]+${orphans[@]}}" |
        { [[ ${#orphans[@]} -gt 0 ]] && cat || true; } |
        jq -R . | jq -s '{schema:"1",tool:"check-file-refs",orphans:.,count:(.|length)}'
else
    if [[ ${#orphans[@]} -eq 0 ]]; then
        echo "No orphaned files found under: $scan_path"
    else
        printf '%s\n' "${orphans[@]}"
    fi
fi
