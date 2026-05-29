#!/usr/bin/env bash
set -euo pipefail

POLICY_FILE="${AI_POLICY_FILE:-${COPILOT_POLICY_FILE:-policies/copilot/policy.yaml}}"
# maintenance mode allows repository-delivered scripts only

deny() {
    jq -cn --arg reason "$1" '{permissionDecision:"deny", permissionDecisionReason:$reason}'
}

allow() {
    jq -cn '{permissionDecision:"allow"}'
}

input="$(cat)"
tool_name="$(jq -r '.toolName // .tool_name // empty' <<<"$input")"
tool_args_raw="$(jq -c '.toolArgs // .toolArgsRaw // .tool_input // {}' <<<"$input")"

is_terminal_tool() {
    case "$1" in
    bash | runTerminalCommand | execute/runInTerminal)
        return 0
        ;;
    *)
        return 1
        ;;
    esac
}

allow_registered_script() {
    local compact="$1"
    local registry_file="${AI_SCRIPT_REGISTRY_FILE:-${COPILOT_SCRIPT_REGISTRY_FILE:-docs/ai/script-registry.json}}"
    local path escaped

    if ! command -v jq >/dev/null 2>&1 || [[ ! -f "$registry_file" ]]; then
        return 1
    fi

    while IFS= read -r path; do
        [[ -n "$path" ]] || continue
        escaped="$(printf '%s' "$path" | sed 's/[][.^$*+?(){}|\\]/\\&/g')"
        if grep -Eq "^(bash[[:space:]]+)?(\./)?${escaped}([[:space:]]|$)" <<<"$compact"; then
            return 0
        fi
    done < <(jq -r '
        [
          (.scripts // {} | to_entries[]?.value.installed_path),
          (.scripts // {} | to_entries[]?.value.source_path),
          (.scripts[]? | select(.approval == "allow") | .path)
        ]
        | flatten
        | map(select(type == "string" and . != ""))
        | unique[]
    ' "$registry_file" 2>/dev/null)

    return 1
}

evaluate_policy_yaml() {
    local compact="$1"

    command -v yq >/dev/null 2>&1 || return 1
    [[ -f "$POLICY_FILE" ]] || return 1

    local encoded rule pattern reason

    policy_match() {
        local pattern="$1"
        PATTERN="$pattern" perl -e 'my $pattern = $ENV{PATTERN}; my $input = do { local $/; <STDIN> }; exit(($input =~ /$pattern/m) ? 0 : 1);' <<<"$compact"
    }

    while IFS= read -r encoded; do
        [[ -n "$encoded" ]] || continue
        rule="$(printf '%s' "$encoded" | base64 -d)"
        pattern="$(printf '%s' "$rule" | yq -r '.pattern')"
        reason="$(printf '%s' "$rule" | yq -r '.reason')"
        if policy_match "$pattern"; then
            deny "$reason"
            exit 0
        fi
    done < <(yq -r '.deny[]? | @base64' "$POLICY_FILE" 2>/dev/null || true)

    if [[ "${AI_STRICT_ALLOWLIST:-${COPILOT_STRICT_ALLOWLIST:-0}}" != '1' ]]; then
        while IFS= read -r encoded; do
            [[ -n "$encoded" ]] || continue
            rule="$(printf '%s' "$encoded" | base64 -d)"
            pattern="$(printf '%s' "$rule" | yq -r '.pattern')"
            if policy_match "$pattern"; then
                allow
                exit 0
            fi
        done < <(yq -r '.allow[]? | @base64' "$POLICY_FILE" 2>/dev/null || true)
    fi

    while IFS= read -r encoded; do
        [[ -n "$encoded" ]] || continue
        rule="$(printf '%s' "$encoded" | base64 -d)"
        pattern="$(printf '%s' "$rule" | yq -r '.pattern')"
        reason="$(printf '%s' "$rule" | yq -r '.reason')"
        if policy_match "$pattern"; then
            jq -cn --arg reason "$reason" '{permissionDecision:"ask", permissionDecisionReason:$reason}'
            exit 0
        fi
    done < <(yq -r '.confirm[]? | @base64' "$POLICY_FILE" 2>/dev/null || true)

    return 1
}

if ! is_terminal_tool "$tool_name"; then
    exit 0
fi

command="$(jq -r '.command // .commandLine // .text // empty' <<<"$tool_args_raw")"
compact="$(tr -s '[:space:]' ' ' <<<"$command" | sed 's/^ //; s/ $//')"
strict_allowlist="${AI_STRICT_ALLOWLIST:-${COPILOT_STRICT_ALLOWLIST:-0}}"

evaluate_policy_yaml "$compact" || true

if grep -Eq '(^|[[:space:]])(sudo|su -|mkfs|dd|shutdown|reboot|halt|poweroff|mount|umount)([[:space:]]|$)' <<<"$compact"; then
    deny 'dangerous system command blocked by repo policy'
    exit 0
fi

if grep -Eq '(^|[[:space:]])(chmod|chown|chgrp)([[:space:]]|$)' <<<"$compact"; then
    deny 'filesystem permission mutation blocked by repo policy'
    exit 0
fi

if grep -Eq '(^|[[:space:]])rm([[:space:]]|$)' <<<"$compact"; then
    deny 'rm blocked by repo policy'
    exit 0
fi

if grep -Eq '^git[[:space:]]+(push|reset[[:space:]]+--hard|clean[[:space:]]+-|checkout[[:space:]]+--|restore[[:space:]]+--|rebase[[:space:]]|filter-branch|reflog[[:space:]]+delete)' <<<"$compact"; then
    deny 'destructive git command blocked by repo policy'
    exit 0
fi

if grep -Eq '(curl|wget).*[|][[:space:]]*(sh|bash|zsh|python|python3|php|node|ruby)' <<<"$compact"; then
    deny 'remote pipe-to-shell execution blocked by repo policy'
    exit 0
fi

if grep -Eq '(curl|wget|nc|ncat|netcat)[[:space:]].*(-d|--data|--upload-file|--data-binary)' <<<"$compact"; then
    deny 'possible data exfiltration command blocked by repo policy'
    exit 0
fi

if grep -Eq '(^|[[:space:]])(cat|bat|less|head|tail)([[:space:]]|$)' <<<"$compact" &&
    grep -Eq '(^|[[:space:]])[^[:space:]]*\.env([^[:space:]]*)?([[:space:]]|$)' <<<"$compact" &&
    ! grep -Eq '(^|[[:space:]])[^[:space:]]*\.env\.example([[:space:]]|$)' <<<"$compact"; then
    deny 'direct .env secret extraction blocked by repo policy'
    exit 0
fi

if allow_registered_script "$compact"; then
    allow
    exit 0
fi

if grep -Eq '^(rg|fd|fzf|bat|jq|yq|mlr|fx|delta|eza|ls|pwd|cat|head|tail|wc|sort|uniq|cut|date|env|which|type|file|stat|du|df)\b' <<<"$compact"; then
    allow
    exit 0
fi

if grep -Eq '^git[[:space:]]+(log|show|diff|status|grep|blame|ls-files|branch|tag|describe|shortlog|rev-parse|cat-file|check-ignore|stash[[:space:]]+list)\b' <<<"$compact"; then
    allow
    exit 0
fi

if grep -Eq '^gh[[:space:]]+(pr[[:space:]]+(view|list|checks|diff)|issue[[:space:]]+(view|list)|repo[[:space:]]+view|run[[:space:]]+(list|view))\b' <<<"$compact"; then
    allow
    exit 0
fi

if grep -Eq '^ast-grep[[:space:]]+run([[:space:]]|$)' <<<"$compact" &&
    ! grep -Eq '(^|[[:space:]])--(rewrite|update-all|U)([[:space:]]|$)' <<<"$compact"; then
    allow
    exit 0
fi

if grep -Eq '^(semgrep[[:space:]]+scan|gitleaks[[:space:]]+detect|trivy[[:space:]]+fs|shellcheck|actionlint|lychee)\b' <<<"$compact"; then
    allow
    exit 0
fi

if grep -Eq '^shfmt[[:space:]]+-d\b' <<<"$compact"; then
    allow
    exit 0
fi

if grep -Eq '^composer[[:space:]]+(validate|show|depends|audit|check-platform-reqs|diagnose)\b' <<<"$compact"; then
    allow
    exit 0
fi

if grep -Eq '^pnpm[[:space:]]+(exec[[:space:]]+(tsc|eslint|biome|knip)|audit|list|outdated)\b' <<<"$compact"; then
    allow
    exit 0
fi

if grep -Eq '^vendor/bin/(phpunit|pest|phpstan|psalm)\b' <<<"$compact"; then
    allow
    exit 0
fi

if grep -Eq '^vendor/bin/pint[[:space:]]+--test\b' <<<"$compact"; then
    allow
    exit 0
fi

if grep -Eq '^vendor/bin/rector[[:space:]]+process[[:space:]]+--dry-run\b' <<<"$compact"; then
    allow
    exit 0
fi

if grep -Eq '^git[[:space:]]+commit\b' <<<"$compact"; then
    jq -cn --arg reason 'Tier 2: git commit modifies history — confirm required' \
        '{permissionDecision:"ask", permissionDecisionReason:$reason}'
    exit 0
fi

if grep -Eq '^git[[:space:]]+stash[[:space:]]+(push|drop|pop)\b' <<<"$compact"; then
    jq -cn --arg reason 'Tier 2: git stash push/pop/drop modifies working state — confirm required' \
        '{permissionDecision:"ask", permissionDecisionReason:$reason}'
    exit 0
fi

# Tier 2: ai-edit in apply mode (APPLY=1 or VERIFY=1 prefix)
if grep -Eq '(^|[[:space:]])(APPLY|VERIFY)=1' <<<"$compact" && grep -Eq '(^|[[:space:]])(bash[[:space:]]+)?(\./)?scripts/ai/ai-edit\.sh([[:space:]]|$)' <<<"$compact"; then
    jq -cn --arg reason 'Tier 2: ai-edit apply mode mutates source files — confirm required' \
        '{permissionDecision:"ask", permissionDecisionReason:$reason}'
    exit 0
fi

# Tier 2: ai-edit dirty-tree apply mode
if grep -Eq '(^|[[:space:]])REQUIRE_CLEAN_TREE=0' <<<"$compact" && grep -Eq '(^|[[:space:]])(bash[[:space:]]+)?(\./)?scripts/ai/ai-edit\.sh([[:space:]]|$)' <<<"$compact"; then
    jq -cn --arg reason 'Tier 2: ai-edit with REQUIRE_CLEAN_TREE=0 may edit on a dirty worktree — confirm required' \
        '{permissionDecision:"ask", permissionDecisionReason:$reason}'
    exit 0
fi

# Tier 3: ai-rollback apply
if grep -Eq '^(bash[[:space:]]+)?(\./)?scripts/ai/ai-rollback\.sh[[:space:]]+apply\b' <<<"$compact"; then
    jq -cn --arg reason 'Tier 3: ai-rollback apply is a recovery mutation — explicit approval required' \
        '{permissionDecision:"ask", permissionDecisionReason:$reason}'
    exit 0
fi

# Tier 3: ai-rollback prune
if grep -Eq '^(bash[[:space:]]+)?(\./)?scripts/ai/ai-rollback\.sh[[:space:]]+prune\b' <<<"$compact"; then
    jq -cn --arg reason 'Tier 3: ai-rollback prune deletes rollback snapshots — explicit approval required' \
        '{permissionDecision:"ask", permissionDecisionReason:$reason}'
    exit 0
fi

# Tier 3: repomix-scc-router clean or purge
if grep -Eq '^(bash[[:space:]]+)?(\./)?scripts/ai/repomix-scc-router\.sh[[:space:]]+(clean|purge)\b' <<<"$compact"; then
    jq -cn --arg reason 'Tier 3: repomix-scc-router clean/purge deletes generated artifacts — explicit approval required' \
        '{permissionDecision:"ask", permissionDecisionReason:$reason}'
    exit 0
fi

# Tier 3: repomix-context-tree clean or purge
if grep -Eq '^(bash[[:space:]]+)?(\./)?scripts/ai/repomix-context-tree\.sh[[:space:]]+(clean|purge)\b' <<<"$compact"; then
    jq -cn --arg reason 'Tier 3: repomix-context-tree clean/purge deletes generated artifacts — explicit approval required' \
        '{permissionDecision:"ask", permissionDecisionReason:$reason}'
    exit 0
fi

# Tier 3: just context-clean or context-purge
if grep -Eq '^just[[:space:]]+context-(clean|purge)\b' <<<"$compact"; then
    jq -cn --arg reason 'Tier 3: just context-clean/purge deletes generated artifacts — explicit approval required' \
        '{permissionDecision:"ask", permissionDecisionReason:$reason}'
    exit 0
fi

# Tier 1: pure read-only AI workflow scripts
if grep -Eq '^(bash[[:space:]]+)?(\./)?scripts/ai/(ai-search|ai-verify|preview-file|fd-files|rg-code|git-forensics|repo-stats|query-usage)\.sh\b' <<<"$compact"; then
    allow
    exit 0
fi

# Tier 1†: read-only-adjacent scripts (write only to known generated directories)
if grep -Eq '^(bash[[:space:]]+)?(\./)?scripts/ai/(ai-diff-context|pack-context|gh-pr-context|repomix-context-tree)\.sh\b' <<<"$compact"; then
    allow
    exit 0
fi

if grep -Eq '^(\./)?scripts/(ai|copilot|opencode)/(ai-structured|ai-task)\.sh\b' <<<"$compact"; then
    allow
    exit 0
fi

if grep -Eq '^(\./)?scripts/(ai|copilot|opencode)/(ai-test-select|ai-doc-check)\.sh\b' <<<"$compact"; then
    allow
    exit 0
fi

# Tier 1: ai-edit dry-run (no APPLY=1 or VERIFY=1)
if grep -Eq '^(bash[[:space:]]+)?(\./)?scripts/ai/ai-edit\.sh\b' <<<"$compact"; then
    allow
    exit 0
fi

# Tier 1: ai-rollback read-only subcommands (list, show)
if grep -Eq '^(bash[[:space:]]+)?(\./)?scripts/ai/ai-rollback\.sh[[:space:]]+(list|show)\b' <<<"$compact"; then
    allow
    exit 0
fi

# Tier 1: repomix-scc-router read-only subcommands
if grep -Eq '^(bash[[:space:]]+)?(\./)?scripts/ai/repomix-scc-router\.sh[[:space:]]+(stats|plan|run|bundle)\b' <<<"$compact"; then
    allow
    exit 0
fi

if grep -Eq '^(bash[[:space:]]+)?(\./)?scripts/ai/[^[:space:]]+\.sh\b' <<<"$compact"; then
    jq -cn --arg reason 'script is not approved by docs/ai/script-registry.json or the tiered hook policy' '{permissionDecision:"ask", permissionDecisionReason:$reason}'
    exit 0
fi

# watch-loop: tier is inherited from the delegated command; fall through to other rules

if [[ "$strict_allowlist" == '1' ]]; then
    if grep -Eq '(^|[^|])[;]|&&|\|\||(^|[^>])>([^>]|$)|`|\$\(|(^|[[:space:]])tee([[:space:]]|$)' <<<"$compact"; then
        deny 'strict allowlist mode blocks shell metacharacters and tee to prevent safe-prefix bypasses'
        exit 0
    fi

    if grep -Eq '^(rg|fd|fzf|bat|jq|yq|ast-grep|semgrep|delta|eza|ls|wc|cut|sort|uniq|tr|stat|file|du|tree|pwd|whoami|id|uname|date|env|printenv|echo|printf)([[:space:]]|$)' <<<"$compact" ||
        grep -Eq '^git([[:space:]]+--no-pager)?[[:space:]]+(grep|log|blame|show|diff|status|rev-parse|symbolic-ref|describe|ls-files|range-diff)([[:space:]]|$)' <<<"$compact" ||
        grep -Eq '^git([[:space:]]+--no-pager)?[[:space:]]+worktree[[:space:]]+list([[:space:]]|$)' <<<"$compact" ||
        grep -Eq '^gh[[:space:]]+(issue[[:space:]]+(view|list)|pr[[:space:]]+(view|list|checks)|repo[[:space:]]+view|search[[:space:]]+(issues|prs)|workflow[[:space:]]+view|run[[:space:]]+(view|list))([[:space:]]|$)' <<<"$compact" ||
        grep -Eq '^(bash[[:space:]]+)?(\./)?scripts/ai/(rg-code|fd-files|preview-file|git-forensics|gh-pr-context|ast-search|ai-search|ai-verify|repo-stats|query-usage|pack-context|repomix-context-tree|repomix-scc-router)\.sh([[:space:]]|$)' <<<"$compact"; then
        allow
        exit 0
    fi

    deny 'strict allowlist mode denies commands outside the explicit read-only and approved-script list'
    exit 0
fi

exit 0
