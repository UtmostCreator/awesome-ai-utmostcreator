#!/usr/bin/env bash
# Guarded edit wrapper for broad repository modifications (thin loader).
#
# The implementation lives in numbered, load-ordered modules under
# scripts/ai/internal/ai-edit/; this root file stays the public, registered
# entrypoint and its behavior is byte-for-byte identical to the previous
# monolithic version. Mutations run only inside ai_edit_main's apply branches.

set -euo pipefail

# Early --introspect guard: emit this script's machine-readable JSON contract
# (static parse via sh-introspect) and exit before running any logic. --help is
# handled by this script's own dedicated early guard (curated usage text) below,
# before common.sh is sourced.
if [[ "${1:-}" == "--introspect" ]]; then
    _ai_self_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    _ai_self_tool="$_ai_self_dir/../../tools/ai/sh-introspect.php"
    if [[ -f "$_ai_self_tool" ]] && command -v "${PHP_BIN:-php}" >/dev/null 2>&1; then
        exec env AI_OUTPUT=json "${PHP_BIN:-php}" "$_ai_self_tool" "${BASH_SOURCE[0]}"
    fi
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

usage() {
    cat <<'EOF'
ai-edit.sh — guarded repository edit entrypoint

Usage:
  ai-edit.sh MODE ARGS... [root] [flags]
  ai-edit.sh ast-grep LANG PATTERN REWRITE [root] [flags]
  ai-edit.sh comby MATCH REWRITE [root] [flags]
  ai-edit.sh sd FROM TO [root] [flags]
  ai-edit.sh patch PATCH_FILE|- [root] [flags]

JSON output: AI_OUTPUT=json

Status values: dry_run applied verified no_matches error unavailable blocked verify_failed limit_exceeded

Modes:
  structural:
    ast-grep       AST-aware rewrite; uses ast-grep/sg
  text:
    comby          generic structural rewrite
    sd             regex replacement in files found by rg; fully planned before apply
  diff:
    patch          apply a validated unified diff from a file or stdin (-);
                   preflighted with git apply --check; unsafe/protected paths blocked

Params:
  scope:
    --glob VALUE+              include glob; repeatable
    --exclude VALUE+           exclude path/glob; repeatable; added to defaults

  bounds:
    --max-files VALUE          cap editable files; default 50
    --max-replacements VALUE   cap total replacements; default 500
    --max-bytes VALUE          skip files above N bytes; default 2000000

  safety:
    --dry-run                  preview only
    --apply                    apply changes
    --verify                   run ai-verify.sh after apply
    --no-verify                do not run ai-verify.sh
    --require-clean-tree       require clean tree before apply
    --allow-dirty-tree         allow dirty tree before apply

  output:
    --format json|text|help

  misc:
    --help | -h                show this help
    --introspect               print machine-readable JSON contract

Env: AI_OUTPUT APPLY VERIFY REQUIRE_CLEAN_TREE MAX_FILES MAX_REPLACEMENTS MAX_BYTES

Tools:
  primary: ast-grep comby git jq rg sd
  base utilities: awk cat sed sort wc
  mode-specific tools: see mode contract via --introspect

Examples:
  AI_OUTPUT=json bash scripts/ai/ai-edit.sh sd OldName NewName . --dry-run
  AI_OUTPUT=json bash scripts/ai/ai-edit.sh sd OldName NewName . --apply --verify
  AI_OUTPUT=json bash scripts/ai/ai-edit.sh ast-grep php 'old($A)' 'new($A)' . --dry-run
  AI_OUTPUT=json bash scripts/ai/ai-edit.sh patch /tmp/agent.patch . --dry-run
  AI_OUTPUT=json bash scripts/ai/ai-edit.sh patch - . --apply --verify < /tmp/agent.patch

Machine contract: bash scripts/ai/ai-edit.sh --introspect
Full contract: AI_OUTPUT=json php tools/ai/sh-introspect.php scripts/ai/ai-edit.sh
EOF
}

# Early --help/-h guard: emit this script's curated usage BEFORE sourcing
# common.sh, so the universal common.sh --help fallback does not shadow the
# richer bespoke help. --introspect is still handled by the common.sh guard
# (and by the parser's --introspect path) after sourcing.
if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    usage
    exit 0
fi

# shellcheck source=scripts/ai/common.sh
source "$SCRIPT_DIR/common.sh"

_ai_edit_dir="$SCRIPT_DIR/internal/ai-edit"
# shellcheck source=scripts/ai/internal/ai-edit/10-helpers.sh
source "$_ai_edit_dir/10-helpers.sh"
# shellcheck source=scripts/ai/internal/ai-edit/30-parse.sh
source "$_ai_edit_dir/30-parse.sh"
# shellcheck source=scripts/ai/internal/ai-edit/40-plan-apply.sh
source "$_ai_edit_dir/40-plan-apply.sh"
# shellcheck source=scripts/ai/internal/ai-edit/90-main.sh
source "$_ai_edit_dir/90-main.sh"

ai_edit_main "$@"
