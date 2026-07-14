#!/usr/bin/env bash
# Exact single-file Repomix wrapper.
#
# Runs from a repository root and passes ONE repository-relative file through
# `repomix --stdin`. Defaults to `--compress --style xml` and writes to the
# generated single-file context output area unless `--output` is provided.
#
# Usage:
#   run-repomix-file.sh [REPO_ROOT] FILE [--style STYLE] [--output PATH] [--no-compress]
#
# Examples:
#   scripts/ai/run-repomix-file.sh . docs/ai/project-context.md
#   scripts/ai/run-repomix-file.sh /path/to/repo src/app.php --style json --output out/app.xml --no-compress

set -euo pipefail

# Early --introspect guard: emit this script's machine-readable JSON contract
# (static parse via sh-introspect) and exit before running any logic. The
# target script is parsed as text, never executed.
if [[ "${1:-}" == "--introspect" ]]; then
    _ai_introspect_here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    _ai_introspect_tool="$_ai_introspect_here/../../tools/ai/sh-introspect.php"
    if [[ -f "$_ai_introspect_tool" ]] && command -v "${PHP_BIN:-php}" >/dev/null 2>&1; then
        exec env AI_OUTPUT=json "${PHP_BIN:-php}" "$_ai_introspect_tool" "${BASH_SOURCE[0]}"
    fi
fi

usage() {
    cat <<'EOF'
Usage:
  run-repomix-file.sh [REPO_ROOT] FILE [options]

Arguments:
  REPO_ROOT   Repository root (optional; defaults to the current directory).
  FILE        File to pack, absolute or relative to REPO_ROOT.

Options:
  --style STYLE    Repomix output style (default: xml).
  --output PATH    Output file path (relative paths resolve under REPO_ROOT).
                   Default: .repomix-context/single-file/<sanitized-path>.<style>
  --no-compress    Disable the default --compress flag.
  --help, -h       Show this help.

Defaults:
  --compress
  --style xml
EOF
}

main() {
    local style='xml'
    local output=''
    local compress=1
    local positionals=()

    while (($# > 0)); do
        case "$1" in
        --help | -h)
            usage
            return 0
            ;;
        --style)
            [[ $# -ge 2 ]] || {
                printf 'error: --style requires a value\n' >&2
                return 2
            }
            style="$2"
            shift 2
            ;;
        --output)
            [[ $# -ge 2 ]] || {
                printf 'error: --output requires a value\n' >&2
                return 2
            }
            output="$2"
            shift 2
            ;;
        --no-compress)
            compress=0
            shift
            ;;
        --compress)
            compress=1
            shift
            ;;
        --)
            shift
            while (($# > 0)); do
                positionals+=("$1")
                shift
            done
            ;;
        -*)
            printf 'error: unknown option: %s\n' "$1" >&2
            return 2
            ;;
        *)
            positionals+=("$1")
            shift
            ;;
        esac
    done

    local repo file
    if ((${#positionals[@]} >= 2)); then
        repo="${positionals[0]}"
        file="${positionals[1]}"
    elif ((${#positionals[@]} == 1)); then
        repo='.'
        file="${positionals[0]}"
    else
        printf 'error: a FILE argument is required\n' >&2
        usage >&2
        return 2
    fi

    [[ -d "$repo" ]] || {
        printf 'error: repository root not found: %s\n' "$repo" >&2
        return 1
    }
    local repo_abs
    repo_abs="$(cd "$repo" && pwd)"

    # Resolve the repository-relative path for the target file.
    local rel
    case "$file" in
    /*)
        rel="${file#"$repo_abs"/}"
        if [[ "$rel" == "$file" ]]; then
            printf 'error: file is not inside repository root: %s\n' "$file" >&2
            return 1
        fi
        ;;
    *)
        rel="${file#./}"
        ;;
    esac

    [[ -f "$repo_abs/$rel" ]] || {
        printf 'error: file not found: %s\n' "$repo_abs/$rel" >&2
        return 1
    }

    command -v repomix >/dev/null 2>&1 || {
        printf 'error: repomix not found on PATH\n' >&2
        return 1
    }

    # Determine the output path.
    local out
    if [[ -n "$output" ]]; then
        case "$output" in
        /*) out="$output" ;;
        *) out="$repo_abs/$output" ;;
        esac
    else
        local sanitized="${rel//\//__}"
        out="$repo_abs/.repomix-context/single-file/${sanitized}.${style}"
    fi

    local out_dir
    out_dir="$(dirname "$out")"
    mkdir -p "$out_dir"

    # Build the repomix argument list.
    local args=(--stdin --style "$style")
    ((compress == 1)) && args+=(--compress)
    args+=(--output "$out")

    # Pass the single repository-relative path to repomix via stdin.
    printf '%s\n' "$rel" | (cd "$repo_abs" && repomix "${args[@]}")

    # Write a small run manifest next to the output.
    local manifest="${out_dir}/run-manifest.json"
    local generated_at
    generated_at="$(date -u +%Y-%m-%dT%H:%M:%SZ 2>/dev/null || printf 'unknown')"
    printf '{\n  "file": "%s",\n  "output": "%s",\n  "style": "%s",\n  "compress": %s,\n  "generated_at": "%s"\n}\n' \
        "$rel" "$out" "$style" "$([[ "$compress" == 1 ]] && printf 'true' || printf 'false')" "$generated_at" \
        >"$manifest"

    printf 'packed %s -> %s\n' "$rel" "$out"
}

main "$@"
