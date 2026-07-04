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
        install_die "base.sh: unknown option '$1'"
        ;;
    esac
done

[[ -n "$SOURCE_ROOT" ]] || install_die 'base.sh: --source-root is required'
[[ -n "$TARGET_ROOT" ]] || install_die 'base.sh: --target-root is required'

copy_file "$SOURCE_ROOT" "$TARGET_ROOT" "$FORCE" "$DRY_RUN" 'packages/ai-universal-rules/templates/core/AGENTS.template.md' 'AGENTS.md'
copy_file "$SOURCE_ROOT" "$TARGET_ROOT" "$FORCE" "$DRY_RUN" 'packages/ai-universal-rules/templates/core/project-context.template.md' 'docs/ai/project-context.md'
copy_file "$SOURCE_ROOT" "$TARGET_ROOT" "$FORCE" "$DRY_RUN" 'packages/ai-universal-rules/templates/shared/guardrails/AI-GUARDRAILS.md' 'docs/ai/AI-GUARDRAILS.md'
copy_dir "$SOURCE_ROOT" "$TARGET_ROOT" "$FORCE" "$DRY_RUN" 'packages/ai-universal-rules/templates/capabilities/project-context' 'docs/ai/capabilities/project-context'
copy_dir "$SOURCE_ROOT" "$TARGET_ROOT" "$FORCE" "$DRY_RUN" 'packages/ai-universal-rules/templates/capabilities/verify-change' 'docs/ai/capabilities/verify-change'
copy_dir "$SOURCE_ROOT" "$TARGET_ROOT" "$FORCE" "$DRY_RUN" 'packages/ai-universal-rules/templates/capabilities/review-diff' 'docs/ai/capabilities/review-diff'
