#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=tools/ai/install/lib.sh
source "$SCRIPT_DIR/lib.sh"

SOURCE_ROOT=''
TARGET_ROOT=''
FORCE=0
DRY_RUN=0

while (($# > 0)); do
    case "$1" in
    --source-root)
        SOURCE_ROOT="$2"
        shift 2
        ;;
    --target-root)
        TARGET_ROOT="$2"
        shift 2
        ;;
    --force)
        FORCE=1
        shift
        ;;
    --dry-run)
        DRY_RUN=1
        shift
        ;;
    *)
        install_die "runtime-opencode.sh: unknown option '$1'"
        ;;
    esac
done

[[ -n "$SOURCE_ROOT" ]] || install_die 'runtime-opencode.sh: --source-root is required'
[[ -n "$TARGET_ROOT" ]] || install_die 'runtime-opencode.sh: --target-root is required'

copy_dir "$SOURCE_ROOT" "$TARGET_ROOT" "$FORCE" "$DRY_RUN" 'packages/ai-universal-rules/templates/core/agents' '.opencode/agents'
copy_dir_as_skill_dirs "$SOURCE_ROOT" "$TARGET_ROOT" "$FORCE" "$DRY_RUN" 'packages/ai-universal-rules/templates/workflows' '.opencode/skills'
copy_dir "$SOURCE_ROOT" "$TARGET_ROOT" "$FORCE" "$DRY_RUN" 'packages/ai-universal-rules/templates/workflows' '.opencode/commands'
copy_dir "$SOURCE_ROOT" "$TARGET_ROOT" "$FORCE" "$DRY_RUN" 'packages/ai-universal-rules/templates/commands' '.opencode/commands'
