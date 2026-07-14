<?php

declare(strict_types=1);

/**
 * Data-driven Claude runtime-capability descriptor (plan-28 Phase 3 — see
 * docs/tickets/claude-agent-fleet-remediation/plan-28-permission-sot-and-render-parity-sync.md,
 * "Phase 3 — Data-driven Claude-capability filter").
 *
 * Declares what Claude Code structurally lacks relative to the canonical OpenCode agent
 * template format, and the concrete body-text transform rules `claude-agent-renderer.php`
 * applies so a rendered Claude body never asserts a capability Claude does not actually have.
 *
 * Two capabilities (`no_ask_tier`, `no_external_directory_enforcement`) have known false-claim
 * patterns in the canonical templates and carry active `rules`. The other two
 * (`no_per_path_edit_scoping`, `no_bash_level_file_op_deny`) are already disclosed accurately
 * at the canonical-template source level (shared across every runtime — see `docs.md`'s "Edit
 * Scope" section and every Claude body's Bash Command Policy footer) and carry no active rule;
 * they are still declared here so the capability list is complete and future regressions have
 * a documented home to add a rule to.
 *
 * Consolidation note (Phase 3): this table subsumes the renderer's previous 4 ad-hoc
 * `preg_replace` calls (the `external_directory` neutralization chain + the `task`(`ask`)
 * delegation rewrite) and the `str_replace` for the "Full per-script ... is in frontmatter"
 * sentence, moving them from inline code into this ordered, agent-agnostic rule table. It does
 * NOT subsume every per-agent `if ($agentId === '...')` override already in
 * `claude-agent-renderer.php` — several of those (the release-auditor/workflow-auditor/
 * fleet-assessor/config-maintainer/agent-creator-runtime-guardian Bash-Command-Policy
 * footer rewrites, release-auditor's Script-Access cross-reference fix, researcher's
 * Write/Edit-capability neutralization, docs' Script-Access override) carry hand-tuned wording
 * specific to that agent's mission framing that a generic rule would either not reach (they are
 * not phrase-substitutions at all — release-auditor's fix depends on interpreting "read-only
 * auditor" mission context) or would only match with a strictly worse, more generic replacement.
 * Per this task's explicit instruction, those are kept as exceptions layered on top of / instead
 * of the generic rules below, not force-merged. See claude-agent-renderer.php's inline comments
 * at each remaining per-agent block for the specific reasoning kept per case.
 */
function aiClaudeRuntimeCapabilities(): array
{
    return [
        'no_ask_tier' => [
            'description' => 'Claude Code has no per-command "ask" interactive-approval tier the '
                . 'way OpenCode does; frontmatter only grants a tool-level `Bash` capability, never '
                . 'a per-script allow/ask/deny table, and a Claude subagent cannot invoke '
                . '`AskUserQuestion` (main-session-only tool) to honor a `task: ask` delegation.',
            'rules' => [
                [
                    'type' => 'literal',
                    'pattern' => 'Full per-script `allow`/`ask`/`deny` is in frontmatter; full guidance in `docs/ai/agent-script-access.md`.',
                    'replacement' => 'Full per-script `allow`/`ask`/`deny` is documented in the Bash Command Policy section above (Claude frontmatter only grants the `Bash` tool at the tool level, not per-script); full guidance in `docs/ai/agent-script-access.md`.',
                ],
                [
                    // Only fires for agents whose Claude tool registry entry omits `Agent`
                    // (claude-agent-tool-registry.php already denies it there for exactly this
                    // reason — no safe non-interactive fallback for `task: ask`). Agents that DO
                    // grant `Agent` (e.g. architect) can genuinely delegate, so the canonical
                    // sentence is accurate for them and must be left untouched.
                    'type' => 'regex',
                    'pattern' => '/`task`\s*\(`ask`\)\s+is only for delegating[^.]*\./',
                    'replacement' => "Task-based delegation (`task: ask` on the canonical OpenCode template) is an "
                        . 'OpenCode-only capability; it is unavailable on Claude for this role because '
                        . 'the tool registry deliberately omits the `Agent` tool here (no safe '
                        . "non-interactive fallback for OpenCode's `ask` approval tier — see "
                        . 'claude-agent-tool-registry.php). Do not attempt to delegate a sub-review or '
                        . 'spawn any subagent from this role on Claude.',
                    'condition' => static fn (array $ctx): bool => !in_array('Agent', $ctx['tools'] ?? [], true),
                ],
            ],
        ],

        'no_external_directory_enforcement' => [
            'description' => 'Claude Code has no `external_directory` permission field; the OpenCode '
                . '`external_directory: ask` permission block (and its prose) does not correspond to '
                . 'any enforced Claude tool permission — the closest analog is the runtime\'s '
                . 'general approval prompt for a Read/Bash call outside the working directory, which '
                . 'is instruction-only here, not a distinct enforced permission.',
            'rules' => [
                [
                    'type' => 'regex',
                    'pattern' => '/\s*\(OpenCode `external_directory: ask`\)/',
                    'replacement' => '',
                ],
                [
                    'type' => 'regex',
                    'pattern' => '/(?:the )?OpenCode `external_directory: ask` prompt/',
                    'replacement' => "the runtime's external-directory approval prompt",
                ],
                [
                    'type' => 'regex',
                    'pattern' => '/external-directory approval prompt/',
                    'replacement' => 'external-directory approval prompt (instruction-only on Claude Code; no tool permission enforces this boundary)',
                ],
            ],
        ],

        // Already disclosed accurately at the canonical-template source level for every agent
        // that declares a path-scoped `permission.edit` map (see docs.md's "Edit Scope" section:
        // "Claude and Copilot cannot express path-scoped edit grants, so this scope is advisory
        // there"). No renderer-level false claim has been found for this capability; declared
        // here (empty `rules`) so a future regression has a documented home for a new rule.
        'no_per_path_edit_scoping' => [
            'description' => 'Claude Code frontmatter grants `Edit`/`Write` at the tool level only; '
                . 'it cannot express the OpenCode `permission.edit` per-glob allow/deny map. Path '
                . 'scoping on Claude is a behavioral instruction, not an enforced tool permission.',
            'rules' => [],
        ],

        // Already disclosed accurately by the Bash Command Policy footer's "Do not run — and
        // `.claude/settings.json` hard-blocks" sentence (claude-agent-renderer.php), which lists
        // exactly the bash-deny-floor patterns that ARE hard-blocked and separately names the
        // ones that are only ask-tier-gated, not hard-blocked. No renderer-level false claim
        // found for this capability either; declared here for the same documentation reason.
        'no_bash_level_file_op_deny' => [
            'description' => 'A Claude agent\'s tool-level `Bash` grant cannot structurally deny '
                . 'individual command patterns (e.g. `mv`, `cp`, `rm`) the way OpenCode\'s '
                . 'per-pattern `bash:` permission map can; only `.claude/settings.json`\'s '
                . 'process-global `permissions.deny` `Bash(...)` entries provide pattern-level '
                . 'denial on Claude, and only for the patterns explicitly listed there.',
            'rules' => [],
        ],
    ];
}

/**
 * Applies every capability rule in `aiClaudeRuntimeCapabilities()`, in declaration order, to a
 * rendered Claude agent body. Rules with a `condition` callback only fire when it returns true
 * for the given `$context` (currently: `tools` — the agent's granted Claude tool list, used by
 * the `no_ask_tier` task-delegation rule). Pure function of its inputs; never re-reads
 * frontmatter or re-parses installed files.
 *
 * @param array{tools?:list<string>} $context
 */
function aiClaudeApplyRuntimeCapabilityFilters(string $body, array $context): string
{
    foreach (aiClaudeRuntimeCapabilities() as $capability) {
        foreach ($capability['rules'] as $rule) {
            if (isset($rule['condition']) && !($rule['condition'])($context)) {
                continue;
            }
            if ($rule['type'] === 'regex') {
                $body = preg_replace($rule['pattern'], $rule['replacement'], $body) ?? $body;
            } else {
                $body = str_replace($rule['pattern'], $rule['replacement'], $body);
            }
        }
    }

    return $body;
}

/**
 * Extracts the `scripts/ai/<name>.sh` basenames present anywhere in a resolved `allowedBash`
 * list (the same list `aiPermissionResolveAllowedBash()` returns and every rendered Bash
 * Command Policy "Approved scripts" bullet list is built from). Basename-level matching means a
 * script gated behind a required env-var prefix (e.g. `AI_VERIFY_SCOPE=changed VERIFY_SECRETS=0
 * bash scripts/ai/ai-verify.sh *`) still counts as "present" — it IS runnable on Claude in that
 * exact form, so a Script-Access bullet naming it must not be rewritten to "not runnable".
 *
 * @param list<string> $allowedBash
 * @return array<string,true>
 */
function aiClaudeScriptAllowlistBasenames(array $allowedBash): array
{
    $basenames = [];
    foreach ($allowedBash as $cmd) {
        if (preg_match('#scripts/ai/([A-Za-z0-9_.-]+\.sh)#', $cmd, $m) === 1) {
            $basenames[$m[1]] = true;
        }
    }

    return $basenames;
}

/**
 * Script-Access-vs-allowedBash reconciliation rule (plan-28 Phase 3): generalizes the
 * researcher-only and docs-only hardcoded regexes into one rule keyed on the agent's actual
 * rendered allowlist. Scoped strictly to the body's `## Script Access` section (bounded by the
 * next `## ` heading or end of string) so it can never touch prose elsewhere.
 *
 * For each bullet line in that section containing at least one literal `` (`ask`) `` marker
 * (the OpenCode ask-tier annotation the canonical templates use), extracts every backtick-quoted
 * `*.sh` script name mentioned on the line. If EVERY named script is absent from
 * `$allowedBash` (via `aiClaudeScriptAllowlistBasenames()`), the whole line is rewritten to a
 * "not runnable on Claude Code" disclosure. If even one named script IS present (a mixed line,
 * e.g. "`ai-diff-context.sh` / `ai-verify.sh` (`ask`)" where only the first is allow-tier), the
 * line is left untouched — safer than risking a false "not runnable" claim about a script that
 * genuinely is runnable. Lines with zero `` (`ask`) `` markers, or where no script name can be
 * extracted, are also left untouched (no ask-tier claim to reconcile).
 *
 * Callers that need agent-specific wording for a line this rule WOULD otherwise rewrite (e.g.
 * docs' "stop and report `needs-scope-approval`" instruction) must apply their own
 * agent-specific `str_replace` against the ORIGINAL body text BEFORE calling this function — see
 * the `docs` block in `aiInstallerRenderClaudeAgent()`. Once a line no longer contains the
 * literal `` (`ask`) `` marker (because an earlier agent-specific override already rewrote it),
 * this function naturally skips it.
 *
 * @param list<string> $allowedBash
 */
function aiClaudeReconcileScriptAccessBullets(string $body, array $allowedBash): string
{
    $allowedBasenames = aiClaudeScriptAllowlistBasenames($allowedBash);

    $result = preg_replace_callback(
        '/^## Script Access\R.*?(?=\R## |\z)/ms',
        static function (array $sectionMatch) use ($allowedBasenames): string {
            $rewritten = preg_replace_callback(
                '/^-.*$/m',
                static fn (array $lineMatch): string => aiClaudeReconcileScriptAccessLine($lineMatch[0], $allowedBasenames),
                $sectionMatch[0]
            );

            return $rewritten ?? $sectionMatch[0];
        },
        $body
    );

    return $result ?? $body;
}

/**
 * @param array<string,true> $allowedBasenames
 */
function aiClaudeReconcileScriptAccessLine(string $line, array $allowedBasenames): string
{
    if (substr_count($line, '(`ask`)') === 0) {
        return $line;
    }

    if (preg_match_all('/`(?:[A-Za-z0-9_.\/-]*?)([A-Za-z0-9_-]+\.sh)`/', $line, $names) === 0) {
        return $line;
    }

    $scriptNames = array_values(array_unique($names[1]));
    foreach ($scriptNames as $name) {
        if (isset($allowedBasenames[$name])) {
            // At least one named script IS in this agent's rendered allowlist — rewriting the
            // whole line would falsely claim it is not runnable. Leave the line untouched.
            return $line;
        }
    }

    $label = implode('` / `', $scriptNames);

    return "- `{$label}` — not runnable on Claude Code (no `ask` approval tier; absent from the "
        . "Bash Command Policy approved list above). If this capability is needed, note the gap "
        . "in this agent's Final Output instead of attempting it here.";
}
