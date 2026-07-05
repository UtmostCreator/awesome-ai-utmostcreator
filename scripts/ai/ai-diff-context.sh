#!/usr/bin/env bash
# Pack changed or targeted files into AI context bundles.
# (thin loader — implementation under scripts/ai/internal/ai-diff-context/)
#
# Name kept for compatibility.
# Behaviour: packs full changed-file context; optionally includes diffs.

set -euo pipefail

# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

TOKEN_BUDGET="${TOKEN_BUDGET:-80000}"
OUTPUT_DIR="${OUTPUT_DIR:-${AI_CONTEXT_DIR}/diff}"
INCLUDE_TESTS="${INCLUDE_TESTS:-1}"
SECRETS_SCAN="${SECRETS_SCAN:-1}"
INCLUDE_DIFFS="${INCLUDE_DIFFS:-0}"
DRY_RUN="${DRY_RUN:-0}"
STRICT_TOKENS="${STRICT_TOKENS:-0}"
SPLIT_OUTPUT="${SPLIT_OUTPUT:-}"
COMMON_OPTION_CONSUMED=0

usage() {
    cat <<'EOF'
Usage:
  ai-diff-context.sh since <ref> [options]
  ai-diff-context.sh unstaged [options]
  ai-diff-context.sh pr <number> [options]
  ai-diff-context.sh recent [--count N] [options]
  ai-diff-context.sh touched <pattern> [options]

Options:
  --include-diffs         Include git diff / PR diff as context artifact
  --no-tests              Do not include related tests
  --no-secrets-scan       Disable gitleaks scan
  --dry-run               Show selected files and estimated tokens only
  --strict                Fail when output exceeds token budget
  --token-budget N        Override TOKEN_BUDGET
  --split SIZE            Pass --split-output SIZE to repomix when available
  --help                  Show help

Environment:
  TOKEN_BUDGET=80000
  INCLUDE_TESTS=1
  SECRETS_SCAN=1
  INCLUDE_DIFFS=0
  DRY_RUN=0
  STRICT_TOKENS=0
  SPLIT_OUTPUT=
  TOKEN_ESTIMATOR_CMD=custom-token-counter
EOF
}

_ai_dc_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/internal/ai-diff-context"
# shellcheck source=scripts/ai/internal/ai-diff-context/10-helpers.sh
source "$_ai_dc_dir/10-helpers.sh"
# shellcheck source=scripts/ai/internal/ai-diff-context/40-commands.sh
source "$_ai_dc_dir/40-commands.sh"
# shellcheck source=scripts/ai/internal/ai-diff-context/90-main.sh
source "$_ai_dc_dir/90-main.sh"

ai_diff_context_main "$@"
