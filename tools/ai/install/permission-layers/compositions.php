<?php

declare(strict_types=1);

require_once __DIR__ . '/compose.php';

/**
 * Per-agent composition specs consumed by tools/ai/generate-agent-permissions.php.
 *
 * Only agents listed here are regenerated from the layered permission system; all
 * other shipped agent frontmatter remains hand-maintained until a later slice migrates
 * it (see docs/tickets/arch-todo-permission-layer-composition-20260705T004618Z/plan.md,
 * Slices 3-5). Agent keys are filename stems, never frontmatter `id` (super-implementer
 * ships `id: implementer` while its filename differs).
 *
 * Refactor pass (docs/tickets/arch-todo-permission-packs-handoff-20260705-141148/plan.md):
 * every agent entry below is built from the small, reviewed vocabulary in this directory
 * instead of hand-typed raw arrays, so a rule is always one of a few known shapes:
 *   - `aiPermissionAgentSpecReadonly()` / `aiPermissionAgentSpecImpl()` (agent-spec.php):
 *     collapse the {compose_spec, render} shape into one call with named arguments — an
 *     agent states only what differs from its profile's defaults.
 *   - `aiPermissionBashAllow()` / `aiPermissionBashAsk()` / `aiPermissionBashDeny()` /
 *     `aiPermissionEditAllow()` / `aiPermissionEditDeny()` (rules.php): typed rule
 *     constructors instead of raw `['permission' => ..., 'pattern' => ..., 'effect' => ...]`
 *     literals.
 *   - `aiPatternAiScript()` / `aiPatternAiTool()` / `aiPatternGit()` (patterns.php):
 *     canonical builders for the most-repeated command shapes.
 *   - `aiPermissionRenderTaskAsk()` / `aiPermissionRenderTaskAllow()` /
 *     `aiPermissionRenderNoTask()` / `aiPermissionRenderScriptRunner()` (render-spec.php):
 *     the repeated `render` metadata shape.
 *   - `aiPermissionPackSetFullProof()` / `aiPermissionPackSetCommonReadDeny()`
 *     (pack-sets.php): named bundles for pack-name combinations shared by 2+ agents.
 * This is a pure structural refactor — every composed model is unchanged (verified via
 * `php tools/ai/generate-agent-permissions.php --check` plus a before/after composed-model
 * comparison); see that ticket's plan.md for the verification method and results.
 *
 * `deny_packs` / `allow_packs` / `ask_packs` (named, reusable, effect-homogeneous rule
 * groups from packs.php — Slice 10) are preferred for anything shared by two or more
 * agents. `exceptions` is reserved for genuinely agent-unique, non-reusable one-offs; do
 * not re-introduce cross-agent duplication into `exceptions` (that was the exact problem
 * Slice 10 fixed, and the N-8 sweep re-fixed after a second round of drift).
 *
 * @return array<string,array{compose_spec:array<string,mixed>,render:array{extra_scalars:array<string,string>,quote:string}}>
 */
function aiPermissionAgentCompositions(): array
{
    return [
        'researcher' => aiPermissionAgentSpecReadonly(
            editSurface: 'research-sessions',
            render: aiPermissionRenderTaskAsk('double'),
            // Researcher's git/gh command set and script tightening are otherwise unique
            // among migrated agents; the 'rg *' deny is shared with script-runner, and the
            // jq/yq deny is shared with architecture-plan-writer (Slice C).
            denyPacks: ['core.safe_read.deny_rg', 'core.safe_read.deny_jq_yq'],
            exceptions: [
                // Path-scoped write surface replaces prior mkdir/printf>>/cat>> shell-append
                // patterns (AC-4): researcher may also append research evidence to
                // docs/tickets/*.md, so grant that surface too.
                aiPermissionEditAllow('docs/tickets/**'),

                // Researcher-specific date/uuid probe (exact format, not the generic `date *`).
                aiPermissionBashAllow('date -u +%Y-%m-%dT%H:%M:%SZ'),
                aiPermissionBashDeny('date *'),
                aiPermissionBashAllow('uuidgen'),

                // Extra read-only git/gh commands used for research history/PR context.
                aiPermissionBashAllow(aiPatternGit('remote*')),
                aiPermissionBashAllow(aiPatternGit('merge-base*')),
                aiPermissionBashAllow(aiPatternGit('rev-list*')),
                aiPermissionBashAllow(aiPatternGit('cherry*')),
                aiPermissionBashAllow(aiPatternGit('for-each-ref*')),
                aiPermissionBashAllow('gh pr status*'),
                aiPermissionBashAllow('gh pr list*'),
                aiPermissionBashAllow('gh pr view*'),
                aiPermissionBashAllow('gh search prs*'),
                aiPermissionBashAllow('gh search commits*'),
                aiPermissionBashAllow('gh issue list*'),
                aiPermissionBashAllow('gh issue view*'),
                aiPermissionBashAllow('gh repo view*'),
                aiPermissionBashAllow('scc --no-complexity --no-cocomo *'),

                // NOTE: the former 'ai-install-coverage.sh' deny exception here (and the
                // 'ai-test-select.sh'/'run-repo-tests.sh*' pair below it) were removed
                // (docs/tickets/arch-todo-optional-agent-permission-composition-
                // 20260705T221434Z/plan.md) — 'ai-install-coverage.sh' is not part of
                // any readonly-profile tier (only reachable via the opt-in
                // 'verify.install_coverage_allow' pack), so its default is already deny for
                // this agent; the exception was a pure no-op restatement. Removed because
                // 'ui-builder' (impl profile, '*': ask baseline) needs the identical
                // pattern as a REAL override (ask -> deny), which would have duplicated
                // across 2+ agents (testNoExceptionPatternDuplicatedAcrossTwoOrMoreAgents).
                // Verified zero rendered-output change for researcher via '--check'.
                // NOTE: the former 'ai-test-select.sh'/'run-repo-tests.sh*' deny exceptions
                // here were removed (docs/tickets/arch-todo-optional-agent-permission-
                // composition-20260705T221434Z/plan.md) — both were pure no-op restatements
                // for this readonly-profile agent (ai-verify tier, which grants these two,
                // is never included in the 'readonly' profile in the first place, and the
                // renderer already omits any exception whose effect matches the '*' floor),
                // and `docs` (impl profile) now needs the identical pattern pair as a REAL
                // tightening (impl's ai-verify tier does grant them by default), which would
                // have duplicated across 2+ agents (testNoExceptionPatternDuplicatedAcross
                // TwoOrMoreAgents). Verified zero rendered-output change for researcher via
                // `--check`.
            ],
            // Secret-path deny backstop (plan-2-opencode-secret-deny-backstop): same shared
            // pack + rationale as reviewer; renders after this agent's reader allows.
            backstopDenyPacks: ['core.safe_read.deny_secret_reads'],
        ),

        'architect' => aiPermissionAgentSpecReadonly(
            editSurface: 'none',
            render: aiPermissionRenderTaskAllow(),
            denyPacks: aiPermissionPackSetCommonReadDeny(),
            // Secret-path deny backstop (plan-2-opencode-secret-deny-backstop): same shared
            // pack + rationale as reviewer; renders after this agent's reader allows.
            backstopDenyPacks: ['core.safe_read.deny_secret_reads'],
        ),

        'repository-researcher' => aiPermissionAgentSpecReadonly(
            editSurface: 'none',
            render: aiPermissionRenderTaskAsk(),
            // Ground truth: this agent's bash '*' fallback is 'ask' (looser than the
            // 'deny' most readonly agents ship); N-3 requires pinning each agent's own
            // shipped baseline rather than assuming a universal floor.
            starBaseline: 'ask',
            // Deliberately narrower "script-first" agent (description: "Strict script-first
            // repository researcher using ai-search before raw search"). Fully covered by
            // packs — no leftover agent-specific exceptions.
            denyPacks: ['core.safe_read.deny_script_first_generics'],
            askPacks: ['raw_tools.ask_gated'],
            // Secret-path deny backstop (plan-2-opencode-secret-deny-backstop): same shared
            // pack as reviewer. This agent's floor is `'*': ask`, so the deny is not even
            // subject to the same-as-floor-effect filter; it renders and denies regardless.
            backstopDenyPacks: ['core.safe_read.deny_secret_reads'],
        ),

        'reviewer' => aiPermissionAgentSpecReadonly(
            editSurface: 'none',
            render: aiPermissionRenderTaskAsk(),
            // AgentPermissionPolicyTest::testReviewerAgentsAllowReadOnlyReviewGitWithoutBroadBranchMutation
            // forbids broad 'git branch*' for reviewer-class agents (destructive branch
            // deletion risk); tighten via a pack pair, then reopen the narrow sub-patterns.
            // Agent-critic MAJOR (2026-07-07): sed -n/head/tail/nl/bat are unrestricted raw
            // readers with no enumerated-secret guard, while preview-file.sh (secret-blocking)
            // is already granted; repository-reviewer — the same review archetype — already
            // denies exactly these five. Reuse its atomic packs (deny_sed_n/deny_head_tail/
            // deny_nl/deny_bat) rather than the much broader deny_script_first_generics bundle
            // repository-reviewer uses, which would also strip reviewer's wc/sort/jq/yq/
            // git-branch-narrow-read/ai.php/lychee/actionlint/shellcheck grants it still needs.
            denyPacks: [
                'core.safe_read.deny_common_generics',
                'git.branch_wildcard_deny',
                'core.safe_read.deny_sed_n',
                'core.safe_read.deny_head_tail',
                'core.safe_read.deny_nl',
                'core.safe_read.deny_bat',
            ],
            allowPacks: [
                'verify.test_probes',
                'verify.install_coverage_allow',
                'git.pr_context_allow',
                'git.branch_narrow_read',
                'git.review_extra',
                'proof.validate_script',
                'proof.generate_check',
                'proof.markdown',
                'proof.security',
            ],
            // Slice D: 'php -l *' + phpunit-direct now sourced via language overlays instead
            // of the 'proof.php_lint'/'proof.phpunit_direct' universal packs (de-pollution).
            // Byte-stable: ground truth shows no composer-validate/paratest grant to reviewer.
            languageOverlays: ['php-lint', 'php-phpunit'],
            askPacks: ['verify.manual_ask', 'context.packaging'],
            // Secret-path deny backstop (plan-2-opencode-secret-deny-backstop): closes
            // reviewer's open MAJOR — its reader wrappers (preview-file/ai-search/rg-code/
            // fd-files/query-usage/git-forensics) were broad-`allow` and could open secret
            // files despite the prose Sensitive File Rule. Rendered AFTER those allows so
            // OpenCode's `.findLast()` resolves a secret path to deny. OpenCode-scoped.
            backstopDenyPacks: ['core.safe_read.deny_secret_reads'],
        ),

        'repository-reviewer' => aiPermissionAgentSpecReadonly(
            editSurface: 'none',
            render: aiPermissionRenderTaskAsk(),
            // Ground truth: this agent's bash '*' fallback is 'ask' (looser than the
            // 'deny' most readonly agents ship); N-3 requires pinning each agent's own
            // shipped baseline rather than assuming a universal floor.
            starBaseline: 'ask',
            // Script-first generics pack already denies 'git branch*'; reopen the narrow
            // sub-patterns same as 'reviewer'. Note: unlike 'reviewer', this agent does NOT
            // grant proof.php_lint/proof.phpunit_direct/proof.markdown/proof.security/
            // context.packaging — only a partial proof-tooling subset, kept precise via
            // atomic packs rather than a coarse bundle.
            denyPacks: ['core.safe_read.deny_script_first_generics'],
            allowPacks: [
                'git.branch_narrow_read',
                'git.review_extra',
                'git.pr_context_allow',
                'verify.scoped_allow',
                'doctor.scripts',
                'proof.validate_script',
                'proof.generate_check',
            ],
            askPacks: ['raw_tools.ask_gated', 'verify.manual_ask'],
            // Secret-path deny backstop (plan-2-opencode-secret-deny-backstop): same shared
            // pack as reviewer. Floor is `'*': ask`, so the deny renders regardless of filter.
            backstopDenyPacks: ['core.safe_read.deny_secret_reads'],
        ),

        'workflow-auditor' => aiPermissionAgentSpecReadonly(
            editSurface: 'none',
            render: aiPermissionRenderTaskAsk(),
            denyPacks: [
                'core.safe_read.deny_common_generics',
                'core.safe_read.deny_sed_n',
                'core.safe_read.deny_nl',
                'core.safe_read.deny_file_probe',
                'core.safe_read.deny_test_x',
                'git.branch_wildcard_deny',
                'git.deny_blame',
                'git.deny_rev_parse',
            ],
            allowPacks: ['verify.install_coverage_allow', 'proof.validate_script'],
            askPacks: ['verify.manual_ask'],
            // Secret-path deny backstop (plan-2-opencode-secret-deny-backstop): same shared
            // pack + rationale as reviewer; renders after this agent's reader allows.
            backstopDenyPacks: ['core.safe_read.deny_secret_reads'],
        ),

        // release-auditor (Slice A, docs/tickets/arch-todo-complete-permission-composition-
        // migration/plan.md): closest analog is 'reviewer' (readonly profile, edit: deny,
        // task: ask). Ground truth requires zero agent-unique exceptions — every shipped
        // grant/deny beyond the readonly-profile default is covered by an existing pack:
        //   - denyPacks (aiPermissionPackSetCommonReadDeny(), shared with architect/
        //     refactorer): tightens date/uuidgen/wc/sort/uniq/du-h (common_generics) and
        //     file (file_probe) to deny — release-auditor's shipped block omits all 7
        //     entirely (falls through to the '*' floor deny), unlike core:safe-read's
        //     default allow for them.
        //   - git.pr_context_allow: shipped grants gh-pr-context.sh (script-tiers:
        //     ai-deny-dangerous denies it by default for every agent).
        //   - verify.install_coverage_allow: shipped grants ai-install-coverage.sh (not in
        //     the readonly profile's ai-read baseline).
        //   - proof.validate_script / proof.generate_check: shipped grants
        //     validate-*.php/generate-*.php --check (not in core:shipped-cli-readonly).
        //   - verify.manual_ask: shipped ask-gates ai-verify.sh (ai-verify tier only ships
        //     with the verify/impl profiles, not readonly).
        // Every other shipped deny (ai-edit/ai-rollback/session-checkpoint/
        // install-mandatory-tools/ai-test-select/run-repo-tests/grep) and the two compound
        // 'git status --short...' read lines are already no-ops under the readonly-profile
        // default (either already denied by the '*' floor with no granting layer, or already
        // covered by the 'git status*'/'git branch*' glob — see core.php's own removal note
        // for that exact pair) — adding them as exceptions would be pure restatement and,
        // for ai-edit/ai-rollback/session-checkpoint, would collide with post-install's
        // existing exceptions (testNoExceptionPatternDuplicatedAcrossTwoOrMoreAgents).
        'release-auditor' => aiPermissionAgentSpecReadonly(
            editSurface: 'none',
            render: aiPermissionRenderTaskAsk(),
            denyPacks: aiPermissionPackSetCommonReadDeny(),
            allowPacks: [
                'git.pr_context_allow',
                'verify.install_coverage_allow',
                'proof.validate_script',
                'proof.generate_check',
            ],
            askPacks: ['verify.manual_ask'],
            // Secret-path deny backstop (plan-2-opencode-secret-deny-backstop): same shared
            // pack + rationale as reviewer; renders after this agent's reader allows.
            backstopDenyPacks: ['core.safe_read.deny_secret_reads'],
        ),

        // architecture-plan-writer (Slice C, docs/tickets/arch-todo-complete-permission-
        // composition-migration/plan.md): the narrowest composed agent — a write-only
        // markdown-plan writer with a bespoke render shape (task before edit, nested
        // external_directory mapping, doom_loop) via aiPermissionRenderArchitecturePlanWriter()
        // (Slice B). `cli_tools: 'none'` opts out of shipped-cli-readonly entirely (ground
        // truth: shipped block has zero ai.php/lychee/actionlint/shfmt/shellcheck grants).
        // Ground truth also grants only 4 of the 16 script-tiers:ai-read baseline scripts and
        // none of the 4 ai-context-ask scripts — both unconditional layers for every agent —
        // so the 12 unwanted ai-read scripts and all 4 context-ask scripts are denied back via
        // exceptions (agent-unique narrowing; no other agent shares this shape). `git.deny_blame`/
        // `core.safe_read.deny_file_probe`/`deny_nl`/`deny_sed_n`/`deny_eza`/`deny_test_x` are
        // reused packs (shared with other agents), not new exceptions. This agent's own design
        // ("do not widen scope") means core:safe-read's ~19-entry CLI toolkit (stat/uuidgen/wc/
        // sort/uniq/du-h/head/tail/jq/yq/scc/tokei/ast-grep/bat/fx/glow/difft/delta/ls-1-scripts)
        // is deliberately denied back too, matching its actual narrow shipped surface (rg/git
        // grep stay allowed — those ARE shipped) rather than accepted as a "low risk" widening.
        'architecture-plan-writer' => aiPermissionAgentSpecReadonly(
            editSurface: 'tickets',
            render: aiPermissionRenderArchitecturePlanWriter(),
            cliTools: 'none',
            denyPacks: [
                'git.deny_blame',
                'core.safe_read.deny_file_probe',
                'core.safe_read.deny_nl',
                'core.safe_read.deny_sed_n',
                'core.safe_read.deny_eza',
                'core.safe_read.deny_test_x',
                // Shared with researcher (jq/yq) — this agent has no askPacks layer, so a
                // denyPack (applied before exceptions) is sufficient with no override risk.
                'core.safe_read.deny_jq_yq',
                // Shared with post-install (head/tail); same no-askPacks reasoning.
                'core.safe_read.deny_head_tail',
                // Shared with reviewer (bat) — extracted from a duplicated inline exception.
                'core.safe_read.deny_bat',
                // Combo pack also denies date* (this agent wants date* allowed) — reopened
                // via an exception below (exceptions apply after deny_packs, later wins).
                'core.safe_read.deny_common_generics',
            ],
            exceptions: [
                // Re-open date* (deny_common_generics denies it, but shipped grants it) plus
                // the exact-match bare 'date' pattern (a separate literal, not covered by any
                // layer) and the mkdir allow (agent-unique, not covered by any layer/pack).
                aiPermissionBashAllow('date *'),
                aiPermissionBashAllow('date'),
                aiPermissionBashAllow('mkdir -p docs/tickets/*'),
                // Deny back the remaining core:safe-read CLI tools with no dedicated atomic
                // pack (agent-unique narrowing; not shared with any other composed agent).
                aiPermissionBashDeny('stat *'),
                aiPermissionBashDeny('scc *'),
                aiPermissionBashDeny('tokei *'),
                aiPermissionBashDeny('ast-grep *'),
                aiPermissionBashDeny('fx *'),
                aiPermissionBashDeny('glow *'),
                aiPermissionBashDeny('difft *'),
                aiPermissionBashDeny('delta *'),
                aiPermissionBashDeny('ls -1 scripts/ai/*.sh | sort'),
                // Also deny back the extra preview-file.sh JSON-variant that ai-read's
                // baseline would grant (shipped file only lists 2 of the 3 variants).
                aiPermissionBashDeny('env AI_OUTPUT=json bash scripts/ai/preview-file.sh *'),
                // NOTE: shipped file grants 'post-tool-use.sh': ask, but post-tool-use.sh is on
                // the TRUE immutable hard-deny floor (core.php:43), not just the ai-deny-
                // dangerous layer — no composed agent may override it (confirmed: none of the
                // other 14 composed agents grant it either). Composing this agent therefore
                // intentionally NARROWS it from ask to deny (safety-increasing, not a
                // widening) rather than violating aiPermissionAssertNoHardDenyWeakening.
                // Deny back the 4 script-tiers:ai-context-ask scripts (unconditional 'ask'
                // layer for every agent) — this agent has no context-packing need at all.
                aiPermissionBashDeny(aiPatternAiScript('pack-context.sh')),
                aiPermissionBashDeny(aiPatternAiScript('run-repomix-context.sh')),
                aiPermissionBashDeny(aiPatternAiScript('repomix-context-tree.sh')),
                aiPermissionBashDeny(aiPatternAiScript('repomix-scc-router.sh')),
                // Deny back the 12 of 16 script-tiers:ai-read baseline scripts this agent does
                // not grant (ground truth: only ai-search/preview-file/query-usage/
                // ai-diff-context are shipped as allow).
                aiPermissionBashDeny(aiPatternAiScript('ai-search-multi.sh')),
                aiPermissionBashDeny('AI_OUTPUT=json ' . aiPatternAiScript('ai-search-multi.sh')),
                aiPermissionBashDeny('env AI_OUTPUT=json ' . aiPatternAiScript('ai-search-multi.sh')),
                aiPermissionBashDeny(aiPatternAiScript('rg-code.sh')),
                aiPermissionBashDeny(aiPatternAiScript('fd-files.sh')),
                aiPermissionBashDeny(aiPatternAiScript('git-branch-origin.sh')),
                aiPermissionBashDeny(aiPatternAiScript('git-forensics.sh')),
                aiPermissionBashDeny(aiPatternAiScript('repo-stats.sh')),
                aiPermissionBashDeny(aiPatternAiScript('repo-tool-inventory.sh')),
                aiPermissionBashDeny(aiPatternAiScript('ai-file-freshness.sh')),
                aiPermissionBashDeny(aiPatternAiScript('check-file-refs.sh')),
                aiPermissionBashDeny(aiPatternAiScript('ai-doc-check.sh')),
                aiPermissionBashDeny(aiPatternAiScript('ai-structured.sh')),
                aiPermissionBashDeny(aiPatternAiScript('repomix-freshness.sh')),
            ],
        ),

        'config-maintainer' => aiPermissionAgentSpecVerify(
            editSurface: 'config',
            render: aiPermissionRenderTaskAsk(),
            denyPacks: [
                'core.safe_read.deny_common_generics',
                'core.safe_read.deny_nl',
                'core.safe_read.deny_file_probe',
                'core.safe_read.deny_sed_n',
                'git.deny_blame',
            ],
            // Bug fix (Slice 10 refactor pass): this agent's 'verify' profile already
            // includes script-tiers:ai-verify, which grants ai-verify(ask), scoped-allow x2,
            // ai-test-select(allow), and run-repo-tests(allow) automatically. The prior
            // composition duplicated all five as exceptions — harmless (later-wins
            // re-asserts the same effect) but pure noise. Not repeated here.
            allowPacks: [
                'git.stash_read',
                'verify.install_coverage_allow',
                'doctor.scripts',
                'proof.validate_script',
                'proof.security',
            ],
            // Slice D (docs/tickets/arch-todo-complete-permission-composition-migration/
            // plan.md): 'php -l *' now sourced via the 'php-lint' language overlay instead
            // of the 'proof.php_lint' universal pack (de-pollution — a non-PHP consumer
            // install no longer inherits this grant). Byte-stable: this agent's only PHP
            // exposure was ever 'php -l *' (verified: no phpunit/composer-validate grants).
            languageOverlays: ['php-lint'],
            askPacks: ['context.packaging', 'script.ai_write_ask'],
            exceptions: [
                // Ground truth grants these despite the 'verify' profile not including
                // them by default (git-mutating-ask and git stash list/show are impl-tier
                // defaults) — agent-specific grant, not a profile change.
                aiPermissionBashAsk(aiPatternGit('add*')),
                aiPermissionBashAsk(aiPatternGit('commit*')),
                aiPermissionBashAsk(aiPatternGit('restore *')),
                aiPermissionBashAsk(aiPatternGit('stash push*')),
                aiPermissionBashAsk(aiPatternGit('stash pop*')),
                aiPermissionBashAsk(aiPatternGit('stash apply*')),
                aiPermissionBashAsk(aiPatternGit('stash drop*')),
                aiPermissionBashAsk(aiPatternGit('checkout*')),
            ],
        ),

        'refactorer' => aiPermissionAgentSpecImpl(
            editSurface: 'code',
            render: aiPermissionRenderNoTask(),
            denyPacks: [
                ...aiPermissionPackSetCommonReadDeny(),
                // Ground truth: refactorer grants no package-manager mutations at all.
                // Extracted (docs/tickets/arch-todo-optional-agent-permission-composition-
                // 20260705T221434Z/plan.md) from 11 inline exceptions into this shared pack
                // because `docs` needs the identical 11-pattern deny set — leaving both as
                // inline exceptions would duplicate a pattern across 2+ agents
                // (testNoExceptionPatternDuplicatedAcrossTwoOrMoreAgents). Zero behavior
                // change for refactorer (verified via --check byte-stability).
                'package_manager.deny_all_mutations',
                'git.branch_wildcard_deny',
            ],
            allowPacks: [
                'git.stash_read',
                'doctor.scripts',
                // Refactorer needs generated/markdown/security proof tooling, but not the
                // broad `validate-*.php` wildcard from aiPermissionPackSetFullProof(); keep
                // validator grants exact in the agent-specific exceptions below.
                'proof.generate_check',
                'proof.markdown',
                'proof.security',
            ],
            // Slice D: php-lint/phpunit-direct/js-core now sourced via language overlays
            // instead of the (reconciled) aiPermissionPackSetFullProof() bundle's former
            // proof.php_lint/proof.phpunit_direct/proof.js_test_lint_typecheck members.
            // Byte-stable: js-core is an exact copy of proof.js_test_lint_typecheck (no
            // yarn/bun — refactorer never granted those, unlike implementer).
            languageOverlays: ['php-lint', 'php-phpunit', 'js-core'],
            askPacks: ['context.packaging', 'core.safe_read.raw_read_ask_gate'],
            exceptions: [
                // Refactorer only needs branch inspection; broad branch wildcard is blocked
                // by the shared git.branch_wildcard_deny pack, so reopen safe forms only.
                aiPermissionBashAllow(aiPatternGit('branch')),
                aiPermissionBashAllow(aiPatternGit('branch --list*')),
                // Keep validator access exact so future mutation-capable validators are not
                // automatically allowed by a wildcard.
                aiPermissionBashAllow('php tools/ai/validate-agent-assessment.php *'),
                aiPermissionBashAllow('php tools/ai/validate-agent-assessment-values.php'),
                aiPermissionBashAllow('php tools/ai/validate-adapter-drift.php *'),
                aiPermissionBashAllow('php tools/ai/validate-ai-config.php'),
                // Refactorer should not drive kit-level AI CLI workflows; use explicit
                // validation scripts instead.
                aiPermissionBashDeny('php tools/ai/ai.php placeholders*'),
                aiPermissionBashDeny('php tools/ai/ai.php verify*'),
                aiPermissionBashDeny('php tools/ai/ai.php preflight*'),
                aiPermissionBashDeny('php tools/ai/ai.php list'),
                aiPermissionBashDeny('php tools/ai/ai.php next*'),
                aiPermissionBashDeny('php tools/ai/ai.php freshness*'),
                aiPermissionBashDeny('php tools/ai/ai.php packs*'),
                aiPermissionBashDeny('php tools/ai/ai.php env-check*'),
                aiPermissionBashDeny('php tools/ai/ai.php install-docs --check'),
                aiPermissionBashDeny('bash scripts/ai/install-mandatory-tools.sh *'),
                // Ground truth: refactorer's git-mutating-ask grants are narrower than
                // the 'impl' profile default (no reset/fetch/merge/pull/checkout/switch/
                // tag/cherry-pick/revert).
                aiPermissionBashDeny(aiPatternGit('reset*')),
                aiPermissionBashDeny(aiPatternGit('fetch*')),
                aiPermissionBashDeny(aiPatternGit('merge*')),
                aiPermissionBashDeny(aiPatternGit('pull*')),
                aiPermissionBashDeny(aiPatternGit('checkout*')),
                aiPermissionBashDeny(aiPatternGit('switch*')),
                aiPermissionBashDeny(aiPatternGit('tag*')),
                aiPermissionBashDeny(aiPatternGit('cherry-pick*')),
                aiPermissionBashDeny(aiPatternGit('revert*')),
                // agent-critic (2026-07-07): the 'js-core' language overlay grants npm/pnpm
                // test/lint/typecheck as 'allow', but each of these executes an arbitrary
                // project-defined package.json script body, not a fixed binary — broader
                // trust than the fixed-binary commands (phpunit, shellcheck) sharing the
                // same allow tier. 'js-core' is currently referenced only by refactorer (no
                // other composition uses this overlay key), so downgrading here is a
                // refactorer-specific override of the overlay's allow, not a narrowing of a
                // tier shared with other agents; implementer's broader 'js-ts' overlay is
                // untouched. Covers plain 'npm test*'/'pnpm test*' too: both are shorthand
                // for running the same user-defined "test" package.json script, so they
                // carry the identical arbitrary-script-execution risk.
                aiPermissionBashAsk('npm test*'),
                aiPermissionBashAsk('npm run test*'),
                aiPermissionBashAsk('npm run lint*'),
                aiPermissionBashAsk('npm run typecheck*'),
                aiPermissionBashAsk('pnpm test*'),
                aiPermissionBashAsk('pnpm run test*'),
                aiPermissionBashAsk('pnpm run lint*'),
                aiPermissionBashAsk('pnpm run typecheck*'),
                // agent-critic (2026-07-07): defense-in-depth generated-file deny patterns
                // beyond this repo's own docs/ai/generated/**+docs/generated/**+*.generated.*
                // convention (edit-surfaces.php $denyTail), given refactorer's broad
                // src/**+app/**+packages/** edit access. Refactorer-specific (not added to
                // the shared $denyTail, which every edit surface/agent inherits) because no
                // evidence was found that other agents need this narrowing; confirmed via
                // `git ls-files` that no tracked path in this repo currently matches these
                // globs, so this is additive with zero blast radius here.
                aiPermissionEditDeny('**/generated/**'),
                aiPermissionEditDeny('**/__generated__/**'),
                aiPermissionEditDeny('**/*.gen.*'),
                // agent-critic (2026-07-07): 'proof.generate_check' grants the wildcard
                // 'php tools/ai/generate-*.php --check*' (trailing wildcard on the flag).
                // Every generate-*.php script gates check-mode with a strict
                // `in_array('--check', $argv, true)` (confirmed: generate-agent-permissions,
                // generate-agent-snippets, generate-ai-catalog, generate-ai-file-standards,
                // generate-repo-structure, generate-stack-registry) — none recognize a
                // `--check`-prefixed flag as check mode, so the wildcard previously let a
                // command like `... --check-and-repair` match this "read-only check" grant
                // while actually running the script's untested default/mutating path.
                // Narrowed here for refactorer only (deny the wildcard, reopen the exact bare
                // token) rather than in the shared 'proof.generate_check' pack itself, which
                // is also consumed by other agents' compositions outside this fix's scope.
                aiPermissionBashDeny('php tools/ai/generate-*.php --check*'),
                aiPermissionBashAllow('php tools/ai/generate-*.php --check'),
            ],
        ),

        'implementer' => aiPermissionAgentSpecImpl(
            editSurface: 'code',
            // Ground truth: implementer.md has no top-level `task:` scalar at all
            // (unlike most other agents) — do not add one.
            render: aiPermissionRenderNoTask(),
            allowPacks: [
                'git.stash_read',
                'verify.install_coverage_allow',
                'doctor.scripts',
                ...aiPermissionPackSetFullProof(),
                'impl.sg_allow',
            ],
            // Slice D: implementer is the one genuine byte-exact match for the FULL 'php'+
            // 'js-ts' overlay union (verified line-by-line: php overlay's 3 paratest-ask
            // lines + composer-validate exactly replace 'impl.composer_validate_allow' plus
            // the 3 inline paratest exceptions below; js-ts overlay's yarn/bun lines exactly
            // replace the 3 inline yarn/bun exceptions below) — so it uses the existing
            // coarse keys directly, not the 4 new atomic ones.
            languageOverlays: ['php', 'js-ts'],
            askPacks: ['context.packaging', 'core.safe_read.raw_read_ask_gate'],
            exceptions: [
                aiPermissionBashAsk(aiPatternAiTool('install * --apply')),
                aiPermissionBashAsk('php tools/ai/install-ai-kit.php *'),
                // paratest (3x) and yarn/bun (3x) exceptions removed here (Slice D): fully
                // redundant with the 'php'/'js-ts' language overlays above, which already
                // grant the exact same 6 patterns.
            ],
        ),

        'bootstrapper' => aiPermissionAgentSpecImpl(
            editSurface: 'code',
            render: aiPermissionRenderNoTask('double'),
            cliTools: 'execute',
            // Ground truth: internal .opencode/agents/bootstrapper.md (NOT installed to
            // consumer projects) matches the 'code' edit surface's allow set exactly
            // (src/app/packages/configs/scripts/tools/tests/docs), not 'install' as an
            // earlier handoff note guessed — corrected here via the ground-truth diff.
            allowPacks: [
                'git.stash_read',
                'proof.validate_script',
                'proof.generate_check',
                'verify.install_coverage_allow',
                'impl.sg_allow',
                'install.docs_allow',
            ],
            // Slice D: php-lint/phpunit-direct/composer-validate now sourced via language
            // overlays instead of the 'proof.php_lint'/'proof.phpunit_direct'/
            // 'impl.composer_validate_allow' universal packs (de-pollution). Byte-stable:
            // ground truth shows no paratest grant to bootstrapper.
            languageOverlays: ['php-lint', 'php-phpunit', 'php-composer-validate'],
            denyPacks: [
                'core.safe_read.deny_eza',
                'core.safe_read.deny_nl',
                'core.safe_read.deny_file_probe',
            ],
            askPacks: ['core.safe_read.raw_read_ask_gate'],
            exceptions: [
                // Bootstrapper allows date/uuidgen/wc (from safe-read defaults) but,
                // unlike the common-generics pack, tightens these beyond it.
                aiPermissionBashDeny('sort *'),
                aiPermissionBashDeny('uniq *'),
                aiPermissionBashDeny('du -h *'),
                // Ground truth grants full ai-verify.sh access (not ask-gated) — this is
                // the internal kit-maintenance agent installing/validating itself.
                aiPermissionBashAllow(aiPatternAiScript('ai-verify.sh')),
                aiPermissionBashAllow(aiPatternAiScript('ai-doc-check.sh', '--check*')),
                aiPermissionBashAllow('shellcheck *'),
                aiPermissionBashAllow('markdownlint-cli2 *'),
                // Bootstrapper-specific install/verification tooling grants (its whole
                // reason to exist: run the installer end to end).
                aiPermissionBashAllow('php tools/ai/install-ai-kit.php *'),
                aiPermissionBashAllow('bash tools/ai/install-ai-kit.sh *'),
                aiPermissionBashAllow('bash tools/ai/install-copilot-kit.sh *'),
                aiPermissionBashAllow('bash tools/ai/install-opencode-kit.sh *'),
                aiPermissionBashAllow('php tools/ai/verify-full-install.php *'),
                aiPermissionBashAllow('php tools/ai/full-install-validation.php *'),
                aiPermissionBashAllow(aiPatternAiTool('preflight*')),
                aiPermissionBashAllow(aiPatternAiTool('package-verify*')),
                aiPermissionBashAllow(aiPatternAiTool('adapter-plan*')),
                aiPermissionBashAllow(aiPatternAiTool('install*')),
                aiPermissionBashAllow(aiPatternAiTool('toolchain*')),
                aiPermissionBashAllow(aiPatternAiTool('run-script *')),
                aiPermissionBashAllow(aiPatternAiTool('hooks*')),
            ],
        ),

        'script-runner' => aiPermissionAgentSpecReadonly(
            // Ground truth: shipped script-runner.md has 'edit: "*": allow', which
            // contradicts the agent's own documented policy ("You cannot edit files
            // (edit: deny)") — a real, pre-existing bug. Corrected here via 'none'.
            editSurface: 'none',
            render: aiPermissionRenderScriptRunner(),
            // Script-first generics pack (repository-researcher/repository-reviewer) is
            // the closest match; script-runner is even narrower (denies rg/fd/ls/git-read
            // passthrough that the two "script-first" agents still allow) — see exceptions.
            denyPacks: [
                'core.safe_read.deny_script_first_generics',
                'core.safe_read.deny_rg',
                'core.safe_read.deny_git_grep',
                'git.deny_blame',
                'git.deny_rev_parse',
                'hard_stop.deny_chown',
            ],
            allowPacks: ['verify.install_coverage_allow'],
            askPacks: ['script.ai_write_ask'],
            exceptions: [
                aiPermissionBashAllow('pwd'),
                aiPermissionBashDeny('ls *'),
                aiPermissionBashDeny('fd *'),
                aiPermissionBashDeny(aiPatternGit('show*')),
                aiPermissionBashDeny(aiPatternGit('ls-files*')),
                aiPermissionBashAllow('python3'),
                aiPermissionBashAsk(aiPatternAiScript('ai-verify.sh')),
                aiPermissionBashAllow(aiPatternAiScript('ai-test-select.sh')),
                aiPermissionBashAsk(aiPatternAiScript('gh-pr-context.sh')),
                // NOTE: ground truth also grants 'bash scripts/ai/ai-task.sh *': ask, but
                // ai-task.sh is on the immutable hard-deny floor
                // (PermissionComposeTest::testTrulyUniversalDangerousScriptsRemainOnImmutableFloor
                // forbids any agent loosening it). Intentional tightening: script-runner
                // loses its prior ask-gated ai-task.sh access, matching every other agent.
                aiPermissionBashAllow(aiPatternAiTool('tool:list')),
                aiPermissionBashAllow(aiPatternAiTool('tool:list*')),
                aiPermissionBashAllow(aiPatternAiTool('tool:describe*')),
                aiPermissionBashAllow(aiPatternAiTool('tool:run *')),
                aiPermissionBashAsk(aiPatternAiTool('tool:run * --apply*')),
                aiPermissionBashAllow('ls scripts/ai'),
                aiPermissionBashAllow('ls scripts/ai/*'),
                aiPermissionBashAllow(aiPatternAiScript('sh-introspect.sh')),
                aiPermissionBashAllow(aiPatternAiScript('repo-tool-inventory.sh', '')),
                aiPermissionBashAllow(aiPatternAiScript('ship-audit.sh')),
                aiPermissionBashAsk(aiPatternAiScript('run-repomix-file.sh')),
                aiPermissionBashAsk(aiPatternAiScript('run-repo-tests.sh', '')),
                aiPermissionBashAsk(aiPatternAiScript('run-repo-tests.sh')),
                aiPermissionBashAsk(aiPatternAiScript('run-test-focused.sh')),
                // Narrow prune-shipped-targets subcommands: more specific literal patterns
                // than the wildcard floor pattern, so they coexist with (not weaken) the
                // immutable 'bash scripts/ai/prune-shipped-targets.sh *' deny.
                aiPermissionBashAllow(aiPatternAiScript('prune-shipped-targets.sh', '--list')),
                aiPermissionBashAllow(aiPatternAiScript('prune-shipped-targets.sh', '--list *')),
                aiPermissionBashAllow(aiPatternAiScript('prune-shipped-targets.sh', '--dry-run')),
                aiPermissionBashAllow(aiPatternAiScript('prune-shipped-targets.sh', '--dry-run *')),
                aiPermissionBashAllow(aiPatternAiScript('prune-shipped-targets.sh', '--help')),
                aiPermissionBashAllow(aiPatternAiScript('prune-shipped-targets.sh', '-h')),
                aiPermissionBashAsk(aiPatternAiScript('prune-shipped-targets.sh', '--apply')),
                aiPermissionBashAsk(aiPatternAiScript('prune-shipped-targets.sh', '--apply *')),
                // Hard stop for ad hoc / chained / mutation commands: script-runner allows
                // no chaining at all, unlike every other migrated agent.
                aiPermissionBashDeny('rm *'),
                aiPermissionBashDeny('mv *'),
                aiPermissionBashDeny('cp *'),
                aiPermissionBashDeny('chmod *'),
                // NOTE: no 'git reset*' exception needed — script-runner's 'readonly'
                // profile never grants core:git-mutating-ask in the first place, so this
                // pattern is already implicitly denied via the '*' catch-all (a no-op
                // restatement would not even render; see the renderer's floor-omission
                // logic in render-adapters.php).
                aiPermissionBashDeny(aiPatternGit('clean*')),
                aiPermissionBashDeny(AI_BASH_PATTERN_PIPE),
                aiPermissionBashDeny(AI_BASH_PATTERN_AND_CHAIN),
                aiPermissionBashDeny(AI_BASH_PATTERN_SEMICOLON_CHAIN),
                aiPermissionBashDeny(AI_BASH_PATTERN_COMMAND_SUBSTITUTION),
            ],
        ),

        'super-implementer' => aiPermissionAgentSpecImpl(
            // Reserved edit surface for this one internal, pinned-open power agent — see
            // edit-surfaces.php 'unrestricted'.
            editSurface: 'unrestricted',
            render: aiPermissionRenderNoTask(),
            verifyTier: 'verify-full',
            cliTools: 'execute',
            // N-3 pinned known exception: super-implementer ships bash '*': allow. The
            // immutable hard-deny floor's other specific patterns (python3, rm -rf, sudo,
            // ssh, scp, git push, ai-task, pre/post-tool-use, prune-shipped-targets,
            // watch-loop, common.sh) still apply — this is an intentional tightening from
            // the currently-shipped fully-open two-line file (bash: '*': allow with
            // nothing else), matching the ticket's "immutable floor applies to every
            // agent" design (only the '*' catch-all is agent-tunable).
            starBaseline: 'allow',
            allowPacks: ['impl.sg_allow'],
            askPacks: ['core.safe_read.raw_read_ask_gate'],
            exceptions: [
                // Sole intentional carve-out: super-implementer is the one power agent that
                // may commit without a prompt. Every OTHER composed agent inherits
                // 'git commit*': ask (impl profile via core:git-mutating-ask) or falls through
                // to its '*' deny/ask floor, so no other agent can commit silently. The
                // immutable hard-deny floor still blocks 'git push*' for this agent too, so
                // silent commits stay local-only. PermissionComposeTest::
                // testMutatingVcsCommandsAreNeverAllowed exempts super-implementer for exactly
                // this pattern (and only this pattern).
                aiPermissionBashAllow(aiPatternGit('commit*')),
            ],
        ),

        'post-install' => aiPermissionAgentSpecImpl(
            editSurface: 'install',
            render: aiPermissionRenderTaskAllow(),
            denyPacks: [
                'core.safe_read.deny_common_generics',
                'core.safe_read.deny_eza',
                'core.safe_read.deny_git_grep',
                'core.safe_read.deny_sed_n',
                'core.safe_read.deny_nl',
                'core.safe_read.deny_file_probe',
                'git.deny_blame',
                'hard_stop.deny_chown',
            ],
            allowPacks: [
                'git.stash_read',
                'verify.install_coverage_allow',
                'proof.validate_script',
                'doctor.scripts',
                'install.docs_allow',
            ],
            // Policy decision (continuation session): ask-gate rg/bat/jq/yq the same as the
            // other 4 impl-profile agents. head/tail/sed-n are excluded below via exceptions
            // (see note there) — post-install already denies them, stricter than ask, and
            // must not be loosened by this pack.
            askPacks: ['core.safe_read.raw_read_ask_gate'],
            exceptions: [
                // Ground truth: shipped post-install's edit block has scripts/ai/** allow but
                // NO tools/ai/** key, so it denies tools/ai/** edits. The 'install' edit
                // surface grants tools/ai/** allow, so re-deny it to preserve behavior.
                aiPermissionEditDeny('tools/ai/**'),

                // Generic CLI tools this agent does not grant (beyond the shared deny packs).
                // NOTE: 'sed -n *' is deny via the deny_sed_n pack above; re-deny it here too
                // because 'core.safe_read.raw_read_ask_gate' (askPacks, applied after
                // deny_packs) would otherwise loosen it to 'ask' — post-install's existing
                // deny posture for sed-n must not regress.
                aiPermissionBashDeny('sed -n *'),
                aiPermissionBashDeny('head *'),
                aiPermissionBashDeny('tail *'),

                // Post-install-specific install/verification tooling grants.
                aiPermissionBashAllow('php tools/ai/verify-install-placeholders.php*'),
                aiPermissionBashAllow(aiPatternAiTool('advisor*')),

                // Post-install-specific destructive-command gating. ('chown *': deny is
                // already covered by the 'hard_stop.deny_chown' pack above — not restated
                // here, review-pass cleanup.)
                aiPermissionBashAsk('rm *'),
                aiPermissionBashAsk(aiPatternGit('clean*')),

                // Ground truth: post-install denies these write scripts (impl profile's
                // ai-write tier defaults them to ask).
                aiPermissionBashDeny(aiPatternAiScript('ai-edit.sh')),
                aiPermissionBashDeny(aiPatternAiScript('ai-rollback.sh')),
                aiPermissionBashDeny(aiPatternAiScript('session-checkpoint.sh')),
            ],
        ),

        // --- Optional agents (docs/tickets/arch-todo-optional-agent-permission-
        // composition-20260705T221434Z/plan.md, Slice A). These ship only under
        // packages/ai-universal-rules/templates/optional/agents/ +
        // .opencode/agents-optional/ (opt-in packs, not the core 15-agent set) — see
        // Design Fork F1 in that plan: intentionally NOT added to
        // aiInstallerAgentProfiles() (tool-gateway visibility stays unchanged); a separate
        // PermissionComposeTest invariant (not the 15-key equality test) covers them.

        // infra-auditor: closest analog is release-auditor (readonly profile, edit:none,
        // task:ask). Ground truth's CLI/git preamble is narrower than core:safe-read's
        // default toolkit (shares the same reduced subset as bugfix/build-config/upgrade
        // below) and grants no shipped-cli-readonly pattern at all (no ai.php subcommands,
        // no lychee/actionlint/shfmt/shellcheck) — same 'cli_tools: none' opt-out as
        // architecture-plan-writer. Only 'composer validate*' is granted from the php
        // family (no lint/phpunit) — the atomic 'php-composer-validate' overlay alone.
        'infra-auditor' => aiPermissionAgentSpecReadonly(
            editSurface: 'none',
            render: aiPermissionRenderTaskAsk(),
            cliTools: 'none',
            denyPacks: [
                'core.safe_read.deny_extended_probe_tools',
                'git.branch_wildcard_deny',
            ],
            allowPacks: [
                'git.grep_allow',
                'verify.install_coverage_allow',
                'proof.validate_script',
            ],
            languageOverlays: ['php-composer-validate'],
            askPacks: ['verify.manual_ask'],
            exceptions: [
                // Ground truth: infra-auditor asks before raw grep (core:safe-read's
                // default is deny) — a single-agent deviation, not yet shared by 2+ agents.
                aiPermissionBashAsk('grep *'),
            ],
        ),

        // bugfix / build-config / upgrade share a near-identical impl-profile, 'code'-edit-
        // surface shape: same reduced CLI/git preamble as infra-auditor above (+ same
        // 'cli_tools: none' opt-out — none of the 3 grant any shipped-cli-readonly
        // pattern), the same narrowed git-mutating-ask (only add/commit) and
        // package-manager-ask (composer install/update/require + npm install/ci + pnpm
        // install + yarn install; no add/bun) subsets, and the same
        // php-lint+php-phpunit+php-composer-validate language-overlay trio (bootstrapper's
        // exact set). `upgrade` is NOT composed in this slice (flagged in the plan as
        // needing a full, not excerpted, ground-truth diff first) — only bugfix and
        // build-config land here.
        'bugfix' => aiPermissionAgentSpecImpl(
            editSurface: 'code',
            render: aiPermissionRenderNoTask(),
            cliTools: 'none',
            denyPacks: [
                'core.safe_read.deny_extended_probe_tools',
                'git.branch_wildcard_deny',
                'git.mutating_add_commit_only_deny',
                'package_manager.narrow_no_add_or_bun_deny',
            ],
            allowPacks: ['git.grep_allow', 'proof.validate_script'],
            languageOverlays: ['php-lint', 'php-phpunit', 'php-composer-validate'],
            // Intentional, documented tightening (matches the established policy already
            // applied to every other impl-profile agent — implementer/refactorer/
            // post-install/config-maintainer all ask-gate these 7 raw-read tools via the
            // same pack): shipped bugfix.md grants rg/jq/yq/head/tail/sed-n as bare `allow`;
            // `testRawReadToolsAreNeverAllowedInWriteProfileAgents` hard-enforces `ask` (or
            // stricter) for any impl-profile agent, so this is a required, safety-increasing
            // deviation from the literal hand-maintained file, not a silent widening.
            askPacks: ['core.safe_read.raw_read_ask_gate'],
            // Ground truth: bugfix denies ai-install-coverage.sh (unlike build-config,
            // which allows it) — already the impl-profile default (not part of ai-verify's
            // tier grants), so no exception is needed to preserve that deny.
        ),

        'build-config' => aiPermissionAgentSpecImpl(
            editSurface: 'code',
            render: aiPermissionRenderNoTask(),
            cliTools: 'none',
            denyPacks: [
                'core.safe_read.deny_extended_probe_tools',
                'git.branch_wildcard_deny',
                'git.mutating_add_commit_only_deny',
                'package_manager.narrow_no_add_or_bun_deny',
            ],
            allowPacks: [
                'git.grep_allow',
                'proof.validate_script',
                'verify.install_coverage_allow',
            ],
            languageOverlays: ['php-lint', 'php-phpunit', 'php-composer-validate'],
            // Same intentional raw-read-tool tightening as bugfix above (required by
            // testRawReadToolsAreNeverAllowedInWriteProfileAgents).
            askPacks: ['core.safe_read.raw_read_ask_gate'],
        ),

        // upgrade: verified via `diff` against `build-config`'s original shipped ground
        // truth (git show HEAD) BEFORE composing either — the two permission blocks were
        // byte-identical at HEAD (both allow ai-install-coverage.sh, both share the exact
        // same reduced CLI/git preamble, narrow git-mutating/package-manager subsets, and
        // php-lint+phpunit+composer-validate trio). Same composition as build-config.
        'upgrade' => aiPermissionAgentSpecImpl(
            editSurface: 'code',
            render: aiPermissionRenderNoTask(),
            cliTools: 'none',
            denyPacks: [
                'core.safe_read.deny_extended_probe_tools',
                'git.branch_wildcard_deny',
                'git.mutating_add_commit_only_deny',
                'package_manager.narrow_no_add_or_bun_deny',
            ],
            allowPacks: [
                'git.grep_allow',
                'proof.validate_script',
                'verify.install_coverage_allow',
            ],
            languageOverlays: ['php-lint', 'php-phpunit', 'php-composer-validate'],
            askPacks: ['core.safe_read.raw_read_ask_gate'],
        ),

        // docs: impl profile + 'docs' edit surface. Ground truth grants NO package-manager
        // mutation at all (shares refactorer's full-deny pack) and denies
        // ai-test-select/run-repo-tests back (impl profile's ai-verify tier would otherwise
        // allow both by default) — a genuine, single-agent deviation from every other
        // impl-profile agent composed so far. Also narrower than bugfix/build-config on
        // git rev-parse*/yq* (both denied here, unlike the other 3) — single-agent
        // exceptions, not yet shared by 2+ agents.
        'docs' => aiPermissionAgentSpecImpl(
            editSurface: 'docs',
            render: aiPermissionRenderNoTask(),
            cliTools: 'none',
            denyPacks: [
                'core.safe_read.deny_extended_probe_tools',
                'git.branch_wildcard_deny',
                'git.mutating_add_commit_only_deny',
                'package_manager.deny_all_mutations',
            ],
            allowPacks: ['git.grep_allow', 'proof.markdown'],
            // Same intentional raw-read-tool tightening as bugfix/build-config (required by
            // testRawReadToolsAreNeverAllowedInWriteProfileAgents).
            askPacks: ['core.safe_read.raw_read_ask_gate'],
            exceptions: [
                aiPermissionBashDeny(aiPatternGit('rev-parse*')),
                // NOTE: kept as an exception (NOT the 'core.safe_read.deny_yq' pack used by
                // agent-creator-static-validator below) because this agent's askPacks
                // (raw_read_ask_gate, which grants 'yq *': ask) apply AFTER denyPacks in
                // compose order — a denyPacks entry here would be silently overridden back
                // to 'ask'. Exceptions apply last, so only this mechanism actually keeps
                // 'yq *' at 'deny' for an agent that also carries raw_read_ask_gate. Not a
                // duplicate of static-validator's pack-based deny_yq (different composition
                // mechanism; the cross-agent duplicate check only scans literal `exceptions`
                // entries, and static-validator's yq denial is not one).
                aiPermissionBashDeny('yq *'),
                aiPermissionBashDeny(aiPatternAiScript('ai-test-select.sh')),
                aiPermissionBashDeny('bash scripts/ai/run-repo-tests.sh*'),
            ],
        ),

        // agent-creator-static-validator / agent-creator-semantic-verifier /
        // agent-creator-runtime-guardian: readonly profile, edit:none, task:ask, 'cli_tools:
        // none' (no ai.php/lychee/actionlint/shfmt/shellcheck grants, same opt-out as
        // architecture-plan-writer/infra-auditor above). All 3 share a narrower git/CLI
        // surface than every other composed readonly agent: only git diff*/log* (+ git
        // grep*/status* for 2 of the 3) stay allowed; git show*/ls-files*/blame*/branch*/
        // rev-parse* are denied back, and the full core:safe-read extended CLI toolkit
        // (stat/date/uuidgen/eza/nl/wc/sort/uniq/file/du-h/scc/tokei/ast-grep/bat/fx/glow/
        // difft/delta/command-v/test-f/test-x/test-d) is denied too (same
        // 'deny_extended_probe_tools' + 'deny_common_generics' pair infra-auditor/bugfix/
        // build-config/docs already use — 'deny_common_generics' is the only pack of the two
        // that reaches 'wc', which none of these 3 grant, unlike the other 4 agents).
        'agent-creator-static-validator' => aiPermissionAgentSpecReadonly(
            editSurface: 'none',
            render: aiPermissionRenderTaskAsk(),
            cliTools: 'none',
            denyPacks: [
                'core.safe_read.deny_extended_probe_tools',
                'core.safe_read.deny_common_generics',
                'ai_scripts.deny_context_and_doc_scripts',
                'core.safe_read.deny_git_grep',
                'git.deny_blame',
                'git.branch_wildcard_deny',
                'git.deny_rev_parse',
                'git.deny_show',
                'git.deny_ls_files',
                'core.safe_read.deny_yq',
                // Extracted to a pack (docs/tickets/arch-todo-optional-agent-permission-
                // composition-20260705T221434Z/plan.md, Slice C continuation): `agent-creator`
                // also denies this pattern, which would otherwise duplicate an inline
                // exception across 2+ agents.
                'agent_creator.deny_ai_diff_context',
            ],
            allowPacks: ['agent_creator.validate_spec_allow'],
            exceptions: [
                // Ground truth: only this family member grants 'cat *' (ask) — a
                // single-agent deviation, not shared by semantic-verifier/runtime-guardian.
                aiPermissionBashAsk('cat *'),
                // Ground truth: this is the only family member that also denies
                // 'git status*' (semantic-verifier/runtime-guardian keep it allowed).
                aiPermissionBashDeny(aiPatternGit('status*')),
            ],
        ),

        'agent-creator-semantic-verifier' => aiPermissionAgentSpecReadonly(
            editSurface: 'none',
            render: aiPermissionRenderTaskAsk(),
            cliTools: 'none',
            denyPacks: [
                'core.safe_read.deny_extended_probe_tools',
                'core.safe_read.deny_common_generics',
                'ai_scripts.deny_context_and_doc_scripts',
                'git.deny_blame',
                'git.branch_wildcard_deny',
                'git.deny_rev_parse',
                'git.deny_show',
                'git.deny_ls_files',
            ],
            allowPacks: ['agent_creator.validate_spec_allow'],
            // Readonly profile has no script-tiers:ai-verify tier by default (that tier
            // only ships with verify/impl profiles) — this agent needs ai-verify.sh
            // ask-gated explicitly, same as infra-auditor above.
            askPacks: ['verify.manual_ask'],
        ),

        // Ground truth: identical CLI/git/context-script surface to semantic-verifier
        // above, but additionally ask-gates ai-rollback.sh/session-checkpoint.sh (its own
        // enforcement-tier job — checkpoint/restore runtime state) that the other 2 family
        // members deny outright (matching the readonly profile's default, since 'impl'
        // profile's ai-write tier is not part of 'readonly'). Ground truth also grants
        // pre-tool-use.sh/post-tool-use.sh as 'ask', but both are on the TRUE immutable
        // hard-deny floor (core.php hard-deny; see architecture-plan-writer's own note above
        // for the identical precedent) — composing this agent intentionally NARROWS both
        // from ask to deny (safety-increasing, not a widening) rather than violating
        // aiPermissionAssertNoHardDenyWeakening.
        'agent-creator-runtime-guardian' => aiPermissionAgentSpecReadonly(
            editSurface: 'none',
            render: aiPermissionRenderTaskAsk(),
            cliTools: 'none',
            denyPacks: [
                'core.safe_read.deny_extended_probe_tools',
                'core.safe_read.deny_common_generics',
                'ai_scripts.deny_context_and_doc_scripts',
                'git.deny_blame',
                'git.branch_wildcard_deny',
                'git.deny_rev_parse',
                'git.deny_show',
                'git.deny_ls_files',
            ],
            allowPacks: ['agent_creator.validate_spec_allow'],
            // Extracted 'session-checkpoint.sh': ask to a pack (docs/tickets/arch-todo-
            // optional-agent-permission-composition-20260705T221434Z/plan.md, Slice C
            // continuation): `agent-creator-supervisor` needs the identical pattern too.
            askPacks: ['verify.manual_ask', 'agent_creator.ask_session_checkpoint'],
            exceptions: [
                // Ground truth: only this family member ask-gates ai-rollback.sh (the
                // other 4 deny it outright, matching the readonly profile's default).
                aiPermissionBashAsk(aiPatternAiScript('ai-rollback.sh')),
            ],
        ),

        // agent-creator: readonly profile, edit:none, task:ask, 'cli_tools: none' — same
        // narrowed CLI/git surface as the other 4 family members (git diff*/log*/grep*/
        // status* stay allowed; show*/ls-files*/blame*/branch*/rev-parse* denied). Unlike
        // static-validator/semantic-verifier/runtime-guardian, this agent keeps the
        // context-packaging family (pack-context/run-repomix-context/repomix-context-tree/
        // repomix-scc-router) at their unconditional 'ask' default (ground truth: all 4
        // show 'ask', not 'deny') — so it uses the smaller
        // 'agent_creator.deny_freshness_and_doc_check' pack instead of the 6-pattern
        // 'ai_scripts.deny_context_and_doc_scripts' bundle. Also the only family member
        // that does NOT grant 'php tools/ai/validate-agent-spec.php *' at all (ground
        // truth: absent) — 'agent_creator.validate_spec_allow' is deliberately NOT applied
        // here.
        'agent-creator' => aiPermissionAgentSpecReadonly(
            editSurface: 'none',
            render: aiPermissionRenderTaskAsk(),
            cliTools: 'none',
            denyPacks: [
                'core.safe_read.deny_extended_probe_tools',
                'core.safe_read.deny_common_generics',
                'agent_creator.deny_freshness_and_doc_check',
                'agent_creator.deny_ai_diff_context',
                'git.deny_blame',
                'git.branch_wildcard_deny',
                'git.deny_rev_parse',
                'git.deny_show',
                'git.deny_ls_files',
            ],
            allowPacks: ['git.grep_allow'],
            // Ground truth grants 'bash scripts/ai/ai-task.sh *': ask, but ai-task.sh is on
            // the TRUE immutable hard-deny floor (core.php) — no composed agent may
            // override it (same precedent as architecture-plan-writer/script-runner/the
            // other 4 family members). Composing this agent intentionally NARROWS it from
            // ask to deny (safety-increasing) rather than violating
            // aiPermissionAssertNoHardDenyWeakening; no exception is added for it (letting
            // it resolve via the floor is correct — adding 'ask' here would throw).
        ),

        // agent-creator-supervisor: readonly profile, edit:none, task:ask, 'cli_tools:
        // none'. Same narrowed CLI/git surface and freshness/doc-check denial as
        // agent-creator above (context-packaging family stays at its 'ask' default here
        // too). Differs from agent-creator by granting
        // 'agent_creator.validate_spec_allow' (ground truth: both
        // 'php tools/ai/validate-agent-spec.php *' AND the bare 'php tools/ai/validate-*.php
        // *' glob — the pack's specific pattern already covers both via
        // aiPermissionResolvePacks(), so only the one pack is needed) and by ask-gating
        // session-checkpoint.sh (shared 'agent_creator.ask_session_checkpoint' pack with
        // agent-creator-runtime-guardian above).
        'agent-creator-supervisor' => aiPermissionAgentSpecReadonly(
            editSurface: 'none',
            render: aiPermissionRenderTaskAsk(),
            cliTools: 'none',
            denyPacks: [
                'core.safe_read.deny_extended_probe_tools',
                'core.safe_read.deny_common_generics',
                'agent_creator.deny_freshness_and_doc_check',
                'git.deny_blame',
                'git.branch_wildcard_deny',
                'git.deny_rev_parse',
                'git.deny_show',
                'git.deny_ls_files',
            ],
            allowPacks: ['git.grep_allow', 'agent_creator.validate_spec_allow'],
            askPacks: ['agent_creator.ask_session_checkpoint'],
            // Ground truth grants 'ai-task.sh': ask and 'pre-tool-use.sh'/'post-tool-use.sh':
            // ask — all three intentionally narrowed to deny via the immutable hard-deny
            // floor (same precedent as agent-creator/architecture-plan-writer/script-runner
            // above); no exceptions added for any of the three (adding 'ask' would throw).
        ),

        // ui-builder (Slice D, docs/tickets/arch-todo-optional-agent-permission-
        // composition-20260705T221434Z/plan.md — composed on explicit user instruction,
        // resolving the plan's yes/no gate): impl profile (ai-write tier ask-gates ai-edit/
        // ai-rollback/session-checkpoint/install-mandatory-tools exactly matching ground
        // truth; ai-verify tier's ai-test-select/run-repo-tests:allow + ai-verify:ask also
        // match), 'ui' edit surface (only consumer — no scripts/**/tools/** grant, unlike
        // every other impl-profile agent), `cli_tools: none` (grants no ai.php/lychee/
        // actionlint/shfmt/shellcheck, same opt-out as the other 9 optional agents).
        // Architecturally unique posture (docs/tickets/.../plan.md's own flagged
        // deviation, preserved not normalized): shipped bash `'*': ask` (every other
        // composed agent, core or optional, is `'*': deny`) via `starBaseline: 'ask'`.
        // Because core:safe-read/core:git-read are UNCONDITIONAL layers (always applied
        // regardless of profile) and grant ~36 generic CLI/git patterns as `allow` by
        // default, and ui-builder's ground truth wants almost none of them explicitly
        // granted (only `ls *`/`rg *`/the git-status/git-diff subset, all already covered
        // by the default layers with zero extra grant needed), this agent needs a long,
        // agent-unique `exceptions` list asking each one back down to `ask` (matching what
        // its own `'*': ask` baseline already implies) — NOT a deny pack (deny would be
        // MORE restrictive than ground truth, a behavior change in the wrong direction).
        // Same precedent scale as architecture-plan-writer's own ~20-entry exceptions list
        // for an equally narrow, non-reusable posture. Mixed single/double-quote YAML style
        // in the original hand-authored file (`"*": ask` etc. vs single-quoted elsewhere)
        // is NOT preserved as mixed — the render system supports one quote style per agent;
        // `quote: 'single'` is chosen to match the majority of the file and every other
        // composed agent, a purely cosmetic normalization (see
        // `aiPermissionRenderUiBuilder()`, render-spec.php) — flagged here, not silently
        // done.
        'ui-builder' => aiPermissionAgentSpecImpl(
            editSurface: 'ui',
            render: aiPermissionRenderUiBuilder(),
            starBaseline: 'ask',
            cliTools: 'none',
            exceptions: [
                // Intentional, required tightening (matches the same policy already
                // applied to bugfix/build-config/docs/upgrade — see their own notes above):
                // ground truth grants 'rg *': allow, but
                // testRawReadToolsAreNeverAllowedInWriteProfileAgents hard-enforces `ask`
                // (or stricter) for any impl-profile agent. Safety-increasing deviation
                // from the literal hand-maintained file, not a silent widening.
                aiPermissionBashAsk('rg *'),
                // core:safe-read (unconditional layer) grants 'git grep *': allow by
                // default; ui-builder's ground truth omits it entirely — ask-gate it back
                // to match the agent's own '*': ask default (corrected after an initial
                // composition pass missed this — safe-read's default was not re-checked
                // against ui-builder specifically before the first --write).
                aiPermissionBashAsk(aiPatternGit('grep *')),
                // ai-read baseline default is 'allow' for all 16 ids; ui-builder's ground
                // truth omits exactly one (ai-search-multi.sh, all 3 command-shape
                // variants) — ask-gate it back to match the agent's own '*': ask default.
                aiPermissionBashAsk(aiPatternAiScript('ai-search-multi.sh')),
                aiPermissionBashAsk('AI_OUTPUT=json ' . aiPatternAiScript('ai-search-multi.sh')),
                aiPermissionBashAsk('env AI_OUTPUT=json ' . aiPatternAiScript('ai-search-multi.sh')),
                // Ground truth explicitly denies this (not merely omits it) — a real
                // deviation from the '*': ask default, not just a no-op restatement.
                aiPermissionBashDeny(aiPatternAiScript('ai-install-coverage.sh')),
                // core:safe-read's generic CLI toolkit (unconditional layer) grants all of
                // these as 'allow' by default; ui-builder's ground truth wants none of them
                // (only 'ls *'/'rg *' are kept, both already covered by the default layer
                // with no extra grant needed) — ask-gate the rest back.
                aiPermissionBashAsk('command -v *'),
                aiPermissionBashAsk('test -f *'),
                aiPermissionBashAsk('test -x *'),
                aiPermissionBashAsk('test -d *'),
                aiPermissionBashAsk('stat *'),
                aiPermissionBashAsk('date *'),
                aiPermissionBashAsk('uuidgen'),
                aiPermissionBashAsk('pwd'),
                aiPermissionBashAsk('fd *'),
                aiPermissionBashAsk('eza *'),
                aiPermissionBashAsk('sed -n *'),
                aiPermissionBashAsk('head *'),
                aiPermissionBashAsk('tail *'),
                aiPermissionBashAsk('nl *'),
                aiPermissionBashAsk('wc *'),
                aiPermissionBashAsk('sort *'),
                aiPermissionBashAsk('uniq *'),
                aiPermissionBashAsk('file *'),
                aiPermissionBashAsk('du -h *'),
                aiPermissionBashAsk('jq *'),
                aiPermissionBashAsk('yq *'),
                aiPermissionBashAsk('scc *'),
                aiPermissionBashAsk('tokei *'),
                aiPermissionBashAsk('ast-grep *'),
                aiPermissionBashAsk('bat *'),
                aiPermissionBashAsk('fx *'),
                aiPermissionBashAsk('glow *'),
                aiPermissionBashAsk('difft *'),
                aiPermissionBashAsk('delta *'),
                aiPermissionBashAsk('ls -1 scripts/ai/*.sh | sort'),
                // core:git-read (unconditional layer) grants all 8 of its patterns as
                // 'allow' by default; ui-builder's ground truth keeps only git status*/
                // git diff* (both already covered, no extra grant needed) — ask-gate the
                // other 6 back.
                aiPermissionBashAsk(aiPatternGit('log*')),
                aiPermissionBashAsk(aiPatternGit('show*')),
                aiPermissionBashAsk(aiPatternGit('ls-files*')),
                aiPermissionBashAsk(aiPatternGit('blame*')),
                aiPermissionBashAsk(aiPatternGit('branch*')),
                aiPermissionBashAsk(aiPatternGit('rev-parse*')),
            ],
        ),
    ];
}

/**
 * Canonical list of the 11 optional-agent filename stems (docs/tickets/arch-todo-optional-
 * agent-permission-composition-20260705T221434Z/plan.md). These ship only under
 * `packages/ai-universal-rules/templates/optional/agents/` + `.opencode/agents-optional/`
 * (opt-in packs), never in `aiInstallerAgentProfiles()` (Design Fork F1, LOCKED — that map
 * stays scoped to the 15 core agents' tool-gateway visibility). Single source of truth for
 * both `PermissionComposeTest`'s core-key-set exclusion and its optional-key-set coverage
 * assertion, so the 11-name list is never duplicated across two tests. Not every key here
 * has a composition entry yet (migrated incrementally, Slice A/C/D) — this list only bounds
 * which names are *recognized* as optional agents when one is composed.
 *
 * @return list<string>
 */
function aiPermissionOptionalAgentKeys(): array
{
    return [
        'agent-creator',
        'agent-creator-runtime-guardian',
        'agent-creator-semantic-verifier',
        'agent-creator-static-validator',
        'agent-creator-supervisor',
        'bugfix',
        'build-config',
        'docs',
        'infra-auditor',
        'ui-builder',
        'upgrade',
    ];
}
