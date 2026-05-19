#!/usr/bin/env bash
set -euo pipefail
BASH_BIN="${BASH_BIN:-$(command -v bash)}"
AI_OUTPUT=json "$BASH_BIN" scripts/ai/ai-search.sh doctor | jq -e '.status=="ok"' >/dev/null
AI_OUTPUT=json "$BASH_BIN" scripts/ai/ai-search.sh text AGENTS.md . --dry-run | jq -e '.status=="dry_run"' >/dev/null
AI_OUTPUT=json "$BASH_BIN" scripts/ai/ai-search.sh unsafe-all AGENTS.md . | jq -e '.status=="unsafe_blocked"' >/dev/null
AI_OUTPUT=json "$BASH_BIN" scripts/ai/ai-search.sh docs "Project Summary" . --fixed | jq -e '.status=="ok" or .status=="no_matches"' >/dev/null
AI_OUTPUT=json "$BASH_BIN" scripts/ai/ai-search.sh text "XYZZY_NO_MATCH_4f7a2b9c" tools --fixed | jq -e '.status=="no_matches" and (.matches|length)==0' >/dev/null
AI_OUTPUT=json "$BASH_BIN" scripts/ai/ai-search.sh tracked AGENTS . --fixed | jq -e '.status=="ok" and (.matches|length)>0' >/dev/null
AI_LANG=php AI_OUTPUT=json "$BASH_BIN" scripts/ai/ai-search.sh struct '$A' tools --fixed | jq -e '.status=="ok" or .status=="no_matches"' >/dev/null

repo_root="$(git rev-parse --show-toplevel)"
tmp_dir="$(mktemp -d)"
trap 'rm -rf "$tmp_dir"' EXIT
(cd "$tmp_dir" && AI_OUTPUT=json "$BASH_BIN" "$repo_root/scripts/ai/ai-search.sh" changed "ai-search" "$repo_root" --fixed | jq -e '.status=="ok" or .status=="no_matches"' >/dev/null)
echo "ai-search tests passed"
