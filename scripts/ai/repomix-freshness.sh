#!/usr/bin/env bash
# Check freshness of the generated Repomix context bundle.
#
# Reads the run manifest written by run-repomix-context.sh and reports how old
# the context is. Use this before relying on .repomix-context bundles so agents
# do not feed stale repository context to an LLM.
#
# Policy (override via env):
#   fresh   : age <  REPOMIX_WARN_DAYS            -> exit 0 (OK)
#   stale   : REPOMIX_WARN_DAYS <= age <= MAX     -> exit 0 (WARN, suggest regen)
#   expired : age >  REPOMIX_MAX_DAYS             -> exit 3 (BLOCK, require regen)
#   missing : no manifest                         -> exit 4 (regen required)
#
# Defaults: warn at 2 days, block after 7 days.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/ai/common.sh
source "$SCRIPT_DIR/common.sh"

WARN_DAYS="${REPOMIX_WARN_DAYS:-2}"
MAX_DAYS="${REPOMIX_MAX_DAYS:-7}"
OUTPUT="${AI_OUTPUT:-text}"
ROOT="."

usage() {
    cat <<'EOF'
Usage:
  repomix-freshness.sh [root]

Options (env):
  REPOMIX_WARN_DAYS   warn threshold in days (default 2)
  REPOMIX_MAX_DAYS    block threshold in days (default 7)
  AI_OUTPUT=json      emit a JSON envelope instead of text

Exit codes:
  0  fresh or warn (usable)
  3  expired (older than REPOMIX_MAX_DAYS) — regenerate before use
  4  missing manifest — generate context first

Regenerate with:
  SECRETS_SCAN=0 bash scripts/ai/run-repomix-context.sh .
EOF
}

case "${1:-}" in
--help | -h)
    usage
    exit 0
    ;;
"") ;;
*)
    ROOT="$1"
    ;;
esac

root_abs="$(cd "$ROOT" && pwd)"
context_dir="${AI_CONTEXT_DIR:-.repomix-context}"
manifest="$root_abs/$context_dir/tree-context/run-manifest.json"
regen_cmd="SECRETS_SCAN=0 bash scripts/ai/run-repomix-context.sh ."

emit() {
    # emit STATUS AGE_SECONDS MESSAGE
    local status="$1" age_seconds="$2" message="$3"
    if [[ "$OUTPUT" == "json" ]]; then
        jq -n \
            --arg schema "1" \
            --arg tool "repomix-freshness" \
            --arg status "$status" \
            --arg manifest "$manifest" \
            --argjson age_seconds "${age_seconds:-0}" \
            --argjson warn_days "$WARN_DAYS" \
            --argjson max_days "$MAX_DAYS" \
            --arg regenerate "$regen_cmd" \
            --arg message "$message" \
            '{schema:$schema, tool:$tool, status:$status, manifest:$manifest, age_seconds:$age_seconds, warn_days:$warn_days, max_days:$max_days, regenerate:$regenerate, message:$message}'
    else
        printf '%s: %s\n' "$status" "$message"
        if [[ "$status" != "fresh" ]]; then
            printf 'regenerate: %s\n' "$regen_cmd"
        fi
    fi
}

if [[ ! -f "$manifest" ]]; then
    emit "missing" 0 "no Repomix context manifest at $context_dir/tree-context/run-manifest.json"
    exit 4
fi

ts="$(jq -r '.ts // empty' "$manifest" 2>/dev/null || true)"
if [[ -z "$ts" ]]; then
    emit "missing" 0 "Repomix manifest has no ts field; treat as stale"
    exit 4
fi

# Parse the manifest timestamp (UTC ISO8601) to epoch seconds, portably.
if gen_epoch="$(date -u -d "$ts" +%s 2>/dev/null)"; then
    :
elif gen_epoch="$(date -u -j -f '%Y-%m-%dT%H:%M:%SZ' "$ts" +%s 2>/dev/null)"; then
    :
else
    emit "missing" 0 "could not parse manifest ts '$ts'; treat as stale"
    exit 4
fi

now_epoch="$(date -u +%s)"
age_seconds=$((now_epoch - gen_epoch))
[[ "$age_seconds" -lt 0 ]] && age_seconds=0

warn_seconds=$((WARN_DAYS * 86400))
max_seconds=$((MAX_DAYS * 86400))
age_days=$((age_seconds / 86400))

if [[ "$age_seconds" -gt "$max_seconds" ]]; then
    emit "expired" "$age_seconds" "Repomix context is ${age_days}d old (> ${MAX_DAYS}d); do not use — regenerate first"
    exit 3
fi

if [[ "$age_seconds" -ge "$warn_seconds" ]]; then
    emit "stale" "$age_seconds" "Repomix context is ${age_days}d old (>= ${WARN_DAYS}d); consider regenerating"
    exit 0
fi

emit "fresh" "$age_seconds" "Repomix context is ${age_days}d old (< ${WARN_DAYS}d); OK to use"
exit 0
