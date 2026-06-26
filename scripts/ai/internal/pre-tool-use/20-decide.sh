# shellcheck shell=bash
# Procedural decision flow for the pre-tool-use policy gate.
#
# Sourced by scripts/ai/pre-tool-use.sh; not an entrypoint. The evaluation order
# (deny rules first, then maintenance mode, then tiered allow/ask rules) is
# byte-for-byte identical to the previous monolithic pre-tool-use.sh. The
# function reads the JSON tool request from stdin and exits with the decision,
# exactly as the original top-level flow did.

pre_tool_use_decide() {
    local input tool_name tool_args_raw command compact strict_allowlist executed_script

    input="$(cat)"
    if [[ -z "$input" ]]; then
        deny "JSON tool request required on stdin"
        exit 1
    fi
    if ! command -v jq >/dev/null 2>&1; then
        ask "pre-tool-use requires jq for JSON parsing; confirm command manually"
        exit 0
    fi
    if ! jq -e . >/dev/null 2>&1 <<<"$input"; then
        deny "invalid JSON tool request on stdin"
        exit 1
    fi
    tool_name="$(jq -r '.toolName // .tool_name // empty' <<<"$input")"
    tool_args_raw="$(jq -c '.toolArgs // .toolArgsRaw // .tool_input // {}' <<<"$input")"

    if is_edit_tool "$tool_name"; then
        if edit_payload_uses_create_delete_fallback "$tool_args_raw"; then
            ask 'needs-rename-approval: create+delete rename fallback is destructive and requires explicit approval'
            exit 0
        fi

        if edit_payload_requests_rename "$tool_args_raw"; then
            ask 'needs-rename-approval: direct file rename or move requires explicit approval'
            exit 0
        fi

        if edit_payload_requests_delete "$tool_args_raw"; then
            ask 'needs-delete-approval: file deletion requires explicit approval'
            exit 0
        fi

        allow
        exit 0
    fi

    if ! is_terminal_tool "$tool_name"; then
        exit 0
    fi

    command="$(jq -r '.command // .commandLine // .text // empty' <<<"$tool_args_raw")"
    compact="$(tr -s '[:space:]' ' ' <<<"$command" | sed 's/^ //; s/ $//')"
    if [[ "$compact" == cd[[:space:]]*'&& '* ]]; then
        compact="${compact#*&& }"
        compact="$(tr -s '[:space:]' ' ' <<<"$compact" | sed 's/^ //; s/ $//')"
    fi
    if [[ "$compact" == */bash[[:space:]]* || "$compact" == */sh[[:space:]]* || "$compact" == */zsh[[:space:]]* ]]; then
        compact="bash ${compact#* }"
    fi
    strict_allowlist="${AI_STRICT_ALLOWLIST:-${COPILOT_STRICT_ALLOWLIST:-0}}"

    evaluate_policy_yaml "$compact" || true

    if maintenance_mode_active; then
        executed_script="$(executed_script_token "$compact" || true)"
        if [[ -n "$executed_script" ]] && [[ "$executed_script" == *.sh || "$executed_script" == */* ]]; then
            if allow_registered_script "$compact"; then
                allow
            else
                ask 'maintenance mode allows only approved repository scripts'
            fi
            exit 0
        fi
    fi

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

    if grep -Eq '^(\./)?vendor/bin/(phpunit|pest|phpstan|psalm)\b' <<<"$compact"; then
        allow
        exit 0
    fi

    if grep -Eq '^(\./)?vendor/bin/pint[[:space:]]+--test\b' <<<"$compact"; then
        allow
        exit 0
    fi

    if grep -Eq '^(\./)?vendor/bin/rector[[:space:]]+process[[:space:]]+--dry-run\b' <<<"$compact"; then
        allow
        exit 0
    fi

    if grep -Eq '^git[[:space:]]+commit\b' <<<"$compact"; then
        ask 'Tier 2: git commit modifies history — confirm required'
        exit 0
    fi

    if grep -Eq '^git[[:space:]]+stash[[:space:]]+(push|drop|pop)\b' <<<"$compact"; then
        ask 'Tier 2: git stash push/pop/drop modifies working state — confirm required'
        exit 0
    fi

    # Tier 2: ai-edit in apply mode (APPLY=1 or VERIFY=1 prefix)
    if grep -Eq '(^|[[:space:]])(APPLY|VERIFY)=1' <<<"$compact" && grep -Eq '(^|[[:space:]])(bash[[:space:]]+)?(\./)?scripts/ai/ai-edit\.sh([[:space:]]|$)' <<<"$compact"; then
        ask 'Tier 2: ai-edit apply mode mutates source files — confirm required'
        exit 0
    fi

    # Tier 2: ai-edit dirty-tree apply mode
    if grep -Eq '(^|[[:space:]])REQUIRE_CLEAN_TREE=0' <<<"$compact" && grep -Eq '(^|[[:space:]])(bash[[:space:]]+)?(\./)?scripts/ai/ai-edit\.sh([[:space:]]|$)' <<<"$compact"; then
        ask 'Tier 2: ai-edit with REQUIRE_CLEAN_TREE=0 may edit on a dirty worktree — confirm required'
        exit 0
    fi

    # Tier 3: ai-rollback apply
    if grep -Eq '^(bash[[:space:]]+)?(\./)?scripts/ai/ai-rollback\.sh[[:space:]]+apply\b' <<<"$compact"; then
        ask 'Tier 3: ai-rollback apply is a recovery mutation — explicit approval required'
        exit 0
    fi

    # Tier 3: ai-rollback prune
    if grep -Eq '^(bash[[:space:]]+)?(\./)?scripts/ai/ai-rollback\.sh[[:space:]]+prune\b' <<<"$compact"; then
        ask 'Tier 3: ai-rollback prune deletes rollback snapshots — explicit approval required'
        exit 0
    fi

    # Tier 3: repomix-scc-router clean or purge
    if grep -Eq '^(bash[[:space:]]+)?(\./)?scripts/ai/repomix-scc-router\.sh[[:space:]]+(clean|purge)\b' <<<"$compact"; then
        ask 'Tier 3: repomix-scc-router clean/purge deletes generated artifacts — explicit approval required'
        exit 0
    fi

    # Tier 3: repomix-context-tree clean or purge
    if grep -Eq '^(bash[[:space:]]+)?(\./)?scripts/ai/repomix-context-tree\.sh[[:space:]]+(clean|purge)\b' <<<"$compact"; then
        ask 'Tier 3: repomix-context-tree clean/purge deletes generated artifacts — explicit approval required'
        exit 0
    fi

    # Tier 3: just context-clean or context-purge
    if grep -Eq '^just[[:space:]]+context-(clean|purge)\b' <<<"$compact"; then
        ask 'Tier 3: just context-clean/purge deletes generated artifacts — explicit approval required'
        exit 0
    fi

    if grep -Eq '^(bash[[:space:]]+)?(\./)?scripts/ai/watch-loop\.sh\b' <<<"$compact"; then
        deny 'watch-loop requires review of the delegated command before execution'
        exit 0
    fi

    if allow_registered_script "$compact"; then
        allow
        exit 0
    fi

    # Tier 1: pure read-only AI workflow scripts
    if grep -Eq '^(bash[[:space:]]+)?(\./)?scripts/ai/(ai-search|ai-verify|preview-file|fd-files|rg-code|git-forensics|repo-stats|query-usage|run-repo-tests|run-test-focused|git-branch-origin)\.sh\b' <<<"$compact"; then
        allow
        exit 0
    fi

    if grep -Eq '^(bash[[:space:]]+)?(\./)?tests/scripts/ai/run-all-tests\.sh\b' <<<"$compact"; then
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
        ask 'script is not approved by docs/ai/script-registry.json or the tiered hook policy'
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
            grep -Eq '^(bash[[:space:]]+)?(\./)?scripts/ai/(rg-code|fd-files|preview-file|git-forensics|gh-pr-context|ast-search|ai-search|ai-verify|repo-stats|query-usage|pack-context|repomix-context-tree|repomix-scc-router|run-repo-tests|run-test-focused|git-branch-origin|ai-structured|ai-task|ai-test-select|ai-doc-check)\.sh([[:space:]]|$)' <<<"$compact" ||
            grep -Eq '^(bash[[:space:]]+)?(\./)?tests/scripts/ai/run-all-tests\.sh([[:space:]]|$)' <<<"$compact"; then
            allow
            exit 0
        fi

        deny 'strict allowlist mode denies commands outside the explicit read-only and approved-script list'
        exit 0
    fi

    exit 0
}
