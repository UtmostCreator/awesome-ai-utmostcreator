#!/usr/bin/env bash
# Unified search wrapper so agents do not guess which discovery tool to call.

set -euo pipefail
# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

usage() {
    cat <<'EOF'
Usage:
  ai-search.sh MODE QUERY [root]

Modes:
  text
  files
  struct
  tracked
  all
EOF
}

mode="${1:-}"
query="${2:-}"
root="${3:-.}"

[[ -n "$mode" && -n "$query" ]] || {
    usage
    exit 2
}

case "$mode" in
text)
    "$(dirname "${BASH_SOURCE[0]}")/rg-code.sh" "$query" "$root"
    ;;
files)
    "$(dirname "${BASH_SOURCE[0]}")/fd-files.sh" "$query" "$root"
    ;;
struct)
    require_bins ast-grep
    lang="${AI_LANG:-php}"
    ast-grep run --lang "$lang" --pattern "$query" "$root"
    ;;
tracked)
    "$(dirname "${BASH_SOURCE[0]}")/rg-code.sh" "$query" "$root" --mode tracked
    ;;
all)
    "$(dirname "${BASH_SOURCE[0]}")/rg-code.sh" "$query" "$root" --mode all
    ;;
*)
    usage
    die "unknown mode: $mode"
    ;;
esac
