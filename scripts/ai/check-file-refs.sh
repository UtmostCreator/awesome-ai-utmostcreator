#!/usr/bin/env bash
# Find tracked files that are not referenced anywhere else in the repository.
# Read-only: surfaces orphaned docs and unused assets. No mutation.

set -euo pipefail

# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

require_bins git rg

usage() {
    cat <<'EOF'
Usage:
  check-file-refs.sh [path] [--format json|plain] [--ext EXT[,EXT]] [--all]
                      [--exclude PATTERN]...

Find tracked files whose basename is not referenced by any other tracked file
(orphaned docs and unused assets). Read-only.

Options:
  path                 Limit the scan to tracked files under this path (default: .)
  --format json|plain  Output format (default: plain, one orphan path per line)
  --ext EXT[,EXT]      Only consider files with these extensions (e.g. md,png)
  --all                Consider every tracked file (default skips common
                       entrypoints that are referenced implicitly)
  --exclude PATTERN    Exclude a git-pathspec glob from both candidates and the
                       reference search (repeatable, e.g. --exclude
                       'database/migrations/**' --exclude 'public/build/**').
                       Use this for project-specific noise: hashed/fingerprinted
                       build output, migrations, or vendored-but-tracked assets
                       all have basenames that are legitimately never referenced
                       elsewhere by string match, so they otherwise flood the
                       orphan list with false positives (built-in defaults only
                       cover vendor/, node_modules/, .git/, .repomix-context/,
                       and this kit's own graphify-out/ cache).
  --help, -h           Show this help

If the scan returns many orphans (50+), a hint is printed to stderr suggesting
--exclude for the noisiest-looking directories; it does not affect exit code
or stdout, so scripted/JSON consumers are unaffected.

Exit codes:
  0  scan completed (orphans may or may not exist; see output)
EOF
}

scan_path="."
output_format="plain"
exts=()
include_all=0
excludes=()

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
    --exclude)
        excludes+=("${2:-}")
        shift 2
        ;;
    --exclude=*)
        excludes+=("${1#*=}")
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
# graphify-out/** is excluded unconditionally (not gated by --all): it is a
# third-party, machine-generated knowledge-graph cache tracked in git (see
# docs/ai/adapter-contract.md "Out-Of-Band Local Additions"), not a doc or
# asset this tool's orphan check is meant to evaluate. Its ~1k content-
# addressed cache blobs (graphify-out/cache/ast/**) have random hash
# basenames that are never referenced elsewhere by design, so including them
# only produces noise and multiplies the rg-per-candidate scan cost.
# User-supplied --exclude patterns are appended as additional git pathspec
# exclusions for the same reason, scoped to whatever noise is specific to the
# calling project (e.g. hashed build output, migrations, vendored-but-tracked
# assets) rather than hardcoded here for every possible framework/convention.
pathspec_excludes=(':!graphify-out/**')
for pat in "${excludes[@]+${excludes[@]}}"; do
    [[ -n "$pat" ]] && pathspec_excludes+=(":!$pat")
done
mapfile -t candidates < <(git ls-files -- "$scan_path" "${pathspec_excludes[@]}")

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
    rg_excludes=(-g '!vendor/**' -g '!node_modules/**' -g '!.git/**' \
        -g '!.repomix-context/**' -g '!graphify-out/**')
    for pat in "${excludes[@]+${excludes[@]}}"; do
        [[ -n "$pat" ]] && rg_excludes+=(-g "!$pat")
    done
    hits="$(rg --no-messages --fixed-strings --files-with-matches -- "$base" . \
        "${rg_excludes[@]}" 2>/dev/null || true)"

    # Strip rg's leading ./ and the file's own path, then check for any
    # remaining reference.
    other_refs="$(printf '%s\n' "$hits" | sed 's#^\./##' | grep -vxF "$path" || true)"

    if [[ -n "$other_refs" ]]; then
        continue
    fi

    orphans+=("$path")
done

# Noise heuristic: a large orphan count almost always means an untracked-noise
# source slipped through (hashed build output, migrations, vendored-but-tracked
# assets), not 50+ genuinely unused docs. Hint on stderr only — stdout/exit
# code/JSON payload are unaffected, so scripted consumers see no behavior
# change.
if ((${#orphans[@]} >= 50)); then
    {
        echo "check-file-refs.sh: ${#orphans[@]} orphans found — this many usually means a" 
        echo "noisy tracked directory (hashed build output, migrations, vendored-but-tracked" 
        echo "assets) rather than genuinely unused files. Inspect the most common leading" 
        echo "path segments below and re-run with --exclude 'PATTERN/**' for each:"
        printf '%s\n' "${orphans[@]}" | awk -F/ 'NF>1 {print $1"/"$2} NF==1 {print $1}' |
            sort | uniq -c | sort -rn | head -5 | awk '{printf "  %5d  %s\n", $1, $2}'
    } >&2
fi

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
