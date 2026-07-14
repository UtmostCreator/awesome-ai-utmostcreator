#!/usr/bin/env bash
# shellcheck disable=SC2016
set -euo pipefail

COMMON_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/ai/common.sh
source "$COMMON_DIR/common.sh"

usage() {
    cat <<'EOF'
Usage:
  scripts/ai/repomix-context-tree.sh <analyze|plan|pack|all|clean|purge> [root] [options]

Commands:
  analyze   Generate planner and human/machine index outputs.
  plan      Alias of analyze.
  pack      Pack only routes marked as decision=pack.
  all       Run analyze then pack.
  clean     Remove generated bundles/indexes and keep plan files.
  purge     Remove the full tree-context output directory.

Options:
  --output-dir <dir>          Base output directory (default: .repomix-context)
  --depth <n>                 Folder grouping depth for stats (default: 2)
  --top <n>                   Max routes to consider, 0 means all (default: 0)
  --min-code <n>              Minimum code lines per route (default: 25)
  --min-files <n>             Minimum files per route (default: 1)
  --min-score <n>             Minimum ranking score (default: 0)
  --min-complexity <n>        Minimum complexity (default: 0)
  --changed-since <ref>       Scope stats input to files changed since ref
  --churn-count <n>           Commit count for churn weighting (default: 50)
  --style <xml|markdown|json|plain>
  --split-size <size>
  --compress
  --include-logs
  --include-logs-count <n>
  --include-diffs
  --include-ignored           Include git-ignored files (bypass git
                              --exclude-standard and repomix .gitignore)
  --include-repomixignored,   Full bypass: also ignore .repomixignore and
  --no-ignore                 repomix default patterns so an explicitly chosen
                              folder is always packable (implies
                              --include-ignored). Env: INCLUDE_REPOMIXIGNORED=1
  --context-window <n>        Context window estimate (default: 1000000)
  --reserved-output <n>       Reserved output tokens (default: 25000)
  --instruction-overhead <n>  Instruction overhead tokens (default: 30000)
  --safety-factor <float>     Safety multiplier (default: 0.8)
  --help
EOF
}


_ai_ctree_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/internal/repomix-context-tree"
_ai_repomix_shared_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/internal/repomix-shared"
# shellcheck source=scripts/ai/internal/repomix-context-tree/10-helpers.sh
source "$_ai_ctree_dir/10-helpers.sh"
# shellcheck source=scripts/ai/internal/repomix-context-tree/40-build-pack.sh
source "$_ai_ctree_dir/40-build-pack.sh"
# shellcheck source=scripts/ai/internal/repomix-shared/10-common-opts.sh
source "$_ai_repomix_shared_dir/10-common-opts.sh"
# shellcheck source=scripts/ai/internal/repomix-context-tree/90-main.sh
source "$_ai_ctree_dir/90-main.sh"

repomix_context_tree_main "$@"
