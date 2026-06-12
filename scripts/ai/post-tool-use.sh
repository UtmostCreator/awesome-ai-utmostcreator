#!/usr/bin/env bash
set -euo pipefail
# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

usage() {
    cat <<'EOF'
Usage:
  post-tool-use.sh < tool-event.json

Reads a JSON tool result event from stdin and appends a normalized event log entry.
EOF
}

case "${1:-}" in
--help | -h)
    usage
    exit 0
    ;;
esac

mkdir -p "$AI_LOG_DIR"
input="$(cat)"
[[ -n "$input" ]] || die "JSON tool event required on stdin"
jq -e . >/dev/null 2>&1 <<<"$input" || die "invalid JSON tool event on stdin"
SESSION_ID="${SESSION_ID:-post-tool-use-$(date +%Y%m%d-%H%M%S)-$$}"
TRACE_ID="${TRACE_ID:-trc-${SESSION_ID}}"
TASK_ID="${TASK_ID:-tsk-${SESSION_ID}}"

classify_failure() {
    jq -r '
    .toolResult as $r
    | (.toolArgs.command // "") as $cmd
    | (.toolResult.error // "") as $err
    | if ($r.resultType // "") == "timeout" then "tool_timeout"
      elif ($err | ascii_downcase | test("approval required|confirm required|explicit approval|human review required")) then "approval_missing"
      elif ($err | ascii_downcase | test("not found|required tools not found|missing")) then "tool_unavailable"
      elif ($err | ascii_downcase | test("unsafe mutation|destructive|blocked by repo policy")) then "unsafe_mutation_blocked"
      elif ($err | ascii_downcase | test("denied|blocked|permission")) then "authorization_denied"
      elif ($err | ascii_downcase | test("unknown option|unknown mode|usage|required|file required")) then "invalid_tool_input"
      elif ($err | ascii_downcase | test("network|connection|dns|tls")) then "external_dependency_failure"
      elif ($cmd | test("phpunit|pest|vitest|jest|npm run test|pnpm run test|yarn test")) then "test_failure"
      elif ($cmd | test("eslint|biome|phpstan|psalm|shellcheck|markdownlint|stylelint|tsc")) then "lint_failure"
      elif ($cmd | test("validate-ai-config|validate-ai-catalog|generate-ai-catalog|jq .*schema|ajv")) then "schema_validation_failure"
      else "unknown"
      end
  ' <<<"$input"
}

authorization_decision() {
    case "$1" in
    approval_missing)
        printf 'ask\n'
        ;;
    authorization_denied | unsafe_mutation_blocked)
        printf 'denied\n'
        ;;
    *)
        printf 'allowed\n'
        ;;
    esac
}

execution_status() {
    local failure_category="$1"

    if [[ "$failure_category" == "approval_missing" || "$failure_category" == "authorization_denied" || "$failure_category" == "unsafe_mutation_blocked" ]]; then
        printf 'blocked\n'
        return 0
    fi

    jq -r '
      if (.toolResult.resultType // "") == "timeout" then "timeout"
      elif (.toolResult.resultType // "") == "success" and (.toolResult.isError // false) == false then "success"
      elif (.toolResult.resultType // "") == "error" or (.toolResult.isError // false) == true then "error"
      else "unknown"
      end
    ' <<<"$input"
}

detect_mutation() {
    jq -r '
      (.toolName // "") as $tool
      | (.toolArgs.command // "") as $cmd
      | if (.toolMutatesState? != null) then .toolMutatesState
        elif ($tool | ascii_downcase) == "write" then true
        elif ($cmd | test("(^|[[:space:]])(rm|mv|cp|chmod|chown|touch|tee)([[:space:]]|$)")) then true
        elif ($cmd | test("git\\s+(commit|stash\\s+(push|pop|drop)|reset\\s+--hard|clean\\s+-|checkout\\s+--)")) then true
        elif ($cmd | test("scripts/ai/(ai-edit|ai-rollback)\\.sh")) then true
        else false
        end
    ' <<<"$input"
}

failure_category=""
if jq -e '.toolResult.resultType? == "error" or .toolResult.isError? == true' >/dev/null 2>&1 <<<"$input"; then
    failure_category="$(classify_failure)"
fi

auth_decision="$(authorization_decision "${failure_category:-unknown}")"
exec_status="$(execution_status "${failure_category:-unknown}")"
mutates_state="$(detect_mutation)"
if command -v sha256sum >/dev/null 2>&1; then
    args_hash="$(jq -cS '.toolArgs // {}' <<<"$input" | sha256sum | awk '{print "sha256:" $1}')"
else
    args_hash="$(jq -cS '.toolArgs // {}' <<<"$input" | shasum -a 256 | awk '{print "sha256:" $1}')"
fi

entry="$(jq -cn \
    --argjson event "$input" \
    --arg event_version "1.1" \
    --arg event_type "$(if [[ -n "$failure_category" ]]; then printf 'tool.failure'; else printf 'tool.result'; fi)" \
    --arg trace_id "$TRACE_ID" \
    --arg session_id "$SESSION_ID" \
    --arg task_id "$TASK_ID" \
    --arg actor_id "${ACTOR_ID:-post-tool-use}" \
    --arg delegated_by "${DELEGATED_BY:-}" \
    --arg args_hash "$args_hash" \
    --arg auth_decision "$auth_decision" \
    --arg failure_category "$failure_category" \
    --arg exec_status "$exec_status" \
    --arg repo_root "$(git_root 2>/dev/null || pwd)" \
    --arg git_branch "$(git rev-parse --abbrev-ref HEAD 2>/dev/null || printf 'unknown')" \
    --arg git_commit "$(git rev-parse HEAD 2>/dev/null || printf 'unknown')" \
    --argjson mutates_state "$mutates_state" \
    '{
      event_version: $event_version,
      event_type: $event_type,
      trace_id: $trace_id,
      session_id: $session_id,
      task_id: $task_id,
      timestamp: ($event.timestamp // (now | strftime("%Y-%m-%dT%H:%M:%SZ"))),
      actor: {
        type: "agent",
        id: $actor_id,
        delegated_by: (if $delegated_by == "" then null else $delegated_by end)
      },
      tool: {
        name: ($event.toolName // "unknown"),
        category: ($event.toolCategory // null),
        args_hash: (if $args_hash == "" then null else $args_hash end),
        mutates_state: $mutates_state
      },
      authorization: {
        policy_version: ($event.policyVersion // "1"),
        decision: $auth_decision,
        approval_required: (if $auth_decision == "ask" then true elif $auth_decision == "allowed" then false else null end),
        approved_by: null,
        reason: (if $failure_category == "" then null else $failure_category end)
      },
      execution: {
        status: $exec_status,
        latency_ms: ($event.durationMs // $event.toolResult.durationMs // null),
        retry_count: ($event.retryCount // null),
        exit_code: ($event.toolResult.exitCode // null),
        output_truncated: ($event.toolResult.outputTruncated // null)
      },
      cost: {
        model: ($event.model // null),
        input_tokens: ($event.inputTokens // null),
        output_tokens: ($event.outputTokens // null),
        estimated_cost_usd: ($event.estimatedCostUsd // null)
      },
      failure: {
        category: (if $failure_category == "" then null else $failure_category end),
        message: ($event.toolResult.error // null),
        resolution: null
      },
      repository: {
        root: $repo_root,
        git_branch: (if $git_branch == "" or $git_branch == "unknown" then null else $git_branch end),
        git_commit: (if $git_commit == "" or $git_commit == "unknown" then null else $git_commit end)
      },
      output: {
        preview: (($event.toolResult.output // $event.toolResult.stderr // null) | if type == "string" then .[:400] else null end)
      },
      details: {
        tool_args: ($event.toolArgs // {}),
        result_type: ($event.toolResult.resultType // "unknown")
      }
    }')"

append_log_entry "$entry"
