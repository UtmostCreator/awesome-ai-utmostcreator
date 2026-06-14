#!/usr/bin/env bash
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

COMMON_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/ai/common.sh
source "$COMMON_DIR/common.sh"

shopt -s extglob
if ((BASH_VERSINFO[0] >= 4)); then
    shopt -s globstar
fi

usage() {
    cat <<'EOF'
Usage:
  scripts/ai/repomix-scc-router.sh <stats|plan|pack|all|clean|purge> [root] [options]

Commands:
  stats   Run scc analysis and write file/folder metrics.
  plan    Run stats and create a ranked bundle plan.
  pack    Pack bundles from an existing bundle plan.
  all     Run stats, plan, and pack.
  clean   Delete generated bundles and keep metrics files.
  purge   Delete the entire output directory.

Options:
  --output-dir <dir>          Output directory (default: .repomix-context)
  --depth <n>                 Folder grouping depth (default: 2)
  --top <n>                   Max folders to pack, 0 means all (default: 0)
  --min-code <n>              Minimum code lines per folder (default: 25)
  --min-files <n>             Minimum files per folder (default: 1)
  --min-score <n>             Minimum ranking score (default: 0)
  --min-complexity <n>        Minimum cyclomatic complexity per folder (default: 0)
  --changed-since <ref>       Limit planning and stats weighting to files changed since ref
  --churn-count <n>           Commit count used for churn weighting (default: 50)
  --style <xml|markdown|json|plain>
                              Repomix output style (default: xml)
  --split-size <size>         Repomix split size, for example 10mb
  --compress                  Enable repomix compression
  --include-logs              Include git logs in bundles
  --include-logs-count <n>    Commit count for --include-logs (default: 20)
  --include-diffs             Include git diffs in bundles
  --include-ignored           Include git-ignored files: bypass git's
                              --exclude-standard during collection and pass
                              --no-gitignore to repomix during packing
  --include-repomixignored,   Full bypass: also ignore .repomixignore and
  --no-ignore                 repomix default patterns so an explicitly chosen
                              folder is always packable. Implies
                              --include-ignored. .git and the output dir stay
                              excluded. Env: INCLUDE_REPOMIXIGNORED=1
  --help                      Show this help

Examples:
  scripts/ai/repomix-scc-router.sh stats . --depth 2
  scripts/ai/repomix-scc-router.sh plan . --depth 2 --top 0 --min-code 25 --min-files 1
  scripts/ai/repomix-scc-router.sh all . --depth 2 --compress --split-size 10mb
  scripts/ai/repomix-scc-router.sh clean .
  scripts/ai/repomix-scc-router.sh purge .
EOF
}

_ai_scc_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/internal/repomix-scc-router"
# shellcheck source=scripts/ai/internal/repomix-scc-router/10-helpers.sh
source "$_ai_scc_dir/10-helpers.sh"
# shellcheck source=scripts/ai/internal/repomix-scc-router/40-analysis-pack.sh
source "$_ai_scc_dir/40-analysis-pack.sh"
# shellcheck source=scripts/ai/internal/repomix-scc-router/90-main.sh
source "$_ai_scc_dir/90-main.sh"

repomix_scc_router_main "$@"
