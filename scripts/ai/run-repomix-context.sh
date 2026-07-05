#!/usr/bin/env bash
# Generate repository context tree through the safer shared wrapper path.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/ai/common.sh
source "$SCRIPT_DIR/common.sh"

usage() {
    cat <<'EOF'
Usage:
  run-repomix-context.sh [root] [options passed to repomix-context-tree.sh]

Defaults:
  --compress
  --style xml
  --depth 2 --top 0 --min-code 25 --min-files 1
  --context-window 1000000 --reserved-output 25000 --instruction-overhead 30000 --safety-factor 0.8

Examples:
  scripts/ai/run-repomix-context.sh .
  scripts/ai/run-repomix-context.sh . --depth 2 --top 0 --min-code 25 --min-files 1
  SECRETS_SCAN=0 bash scripts/ai/run-repomix-context.sh /Users/example-user/Workspaces/example-app \
    --depth 2 --top 0 --min-code 25 --min-files 1 \
    --context-window 1000000 --reserved-output 25000 --instruction-overhead 30000 --safety-factor 0.8

  # Focused 273K budget profile:
  SECRETS_SCAN=0 bash scripts/ai/run-repomix-context.sh . \
    --context-window 273000 --reserved-output 12000 --instruction-overhead 18000 --safety-factor 0.8

  # Bundle a git-ignored folder (for example a JSON cache under storage/tmp):
  SECRETS_SCAN=0 bash scripts/ai/run-repomix-context.sh storage/tmp \
    --include-ignored --min-code 25 --min-files 1

  # Force-pack ANY selected folder even when blocked by .gitignore AND
  # .repomixignore (full bypass; .git and the output dir stay excluded):
  SECRETS_SCAN=0 bash scripts/ai/run-repomix-context.sh docs/ai/generated \
    --no-ignore --min-code 25 --min-files 1
EOF
}

case "${1:-}" in
--help | -h)
    usage
    exit 0
    ;;
esac

ROOT="${1:-.}"
shift || true

agent_session_init "run-repomix-context"
require_bins git jq rg scc repomix

root_abs="$(cd "$ROOT" && pwd)"
TREE_SCRIPT="$SCRIPT_DIR/repomix-context-tree.sh"

[[ -f "$TREE_SCRIPT" ]] || die "missing tree script at $TREE_SCRIPT"

section "Secrets scan"
require_clean_secret_scan "$root_abs"

section "Generate context tree"

if ! bash "$TREE_SCRIPT" all "$root_abs" \
    --compress \
    --style xml \
    --depth 2 \
    --top 0 \
    --min-code 25 \
    --min-files 1 \
    --context-window 1000000 \
    --reserved-output 25000 \
    --instruction-overhead 30000 \
    --safety-factor 0.8 \
    "$@"; then
    die "context tree generation failed"
fi

OUTPUT_DIR="$root_abs/.repomix-context/tree-context"
INDEX_MD="$OUTPUT_DIR/index.md"
PLAN_JSON="$OUTPUT_DIR/tree-plan.json"
MANIFEST_JSON="$OUTPUT_DIR/tree-manifest.json"
BUNDLES_DIR="$OUTPUT_DIR/bundles"

[[ -f "$INDEX_MD" ]] || die "missing generated index: $INDEX_MD"
[[ -f "$PLAN_JSON" ]] || die "missing generated plan: $PLAN_JSON"
[[ -f "$MANIFEST_JSON" ]] || die "missing generated manifest: $MANIFEST_JSON"
[[ -d "$BUNDLES_DIR" ]] || die "missing generated bundles directory: $BUNDLES_DIR"

jq . "$PLAN_JSON" >/dev/null
jq . "$MANIFEST_JSON" >/dev/null

bundle_count="$(find "$BUNDLES_DIR" -type f 2>/dev/null | wc -l | tr -d ' ')"

if [[ "$bundle_count" == "0" ]]; then
    die "no context bundles generated in $BUNDLES_DIR"
fi

jq -n \
    --arg root "$root_abs" \
    --arg index "$INDEX_MD" \
    --arg plan "$PLAN_JSON" \
    --arg manifest "$MANIFEST_JSON" \
    --arg bundles "$BUNDLES_DIR" \
    --argjson bundle_count "$bundle_count" \
    --arg ts "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
    '{
      root: $root,
      index: $index,
      plan: $plan,
      manifest: $manifest,
      bundles: $bundles,
      bundle_count: $bundle_count,
      ts: $ts
    }' >"$OUTPUT_DIR/run-manifest.json"

log_json "context.tree.run" "$(cat "$OUTPUT_DIR/run-manifest.json")"

cat <<EOF
Context package generated.

Open first:
  .repomix-context/tree-context/index.md

Machine plan:
  .repomix-context/tree-context/tree-plan.json

Manifest:
  .repomix-context/tree-context/tree-manifest.json

Bundles:
  .repomix-context/tree-context/bundles/
EOF
