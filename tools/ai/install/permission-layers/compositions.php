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

                // Researcher-specific script tightening: several scripts that the generic
                // readonly ai-read tier would allow are explicitly out of scope for research.
                aiPermissionBashDeny(aiPatternAiScript('ai-install-coverage.sh')),
                aiPermissionBashDeny(aiPatternAiScript('ai-test-select.sh')),
                // Established special case (no space before '*' — see script-tiers.php's own
                // run-repo-tests.sh handling in aiPermissionScriptCommandPatterns()).
                aiPermissionBashDeny('bash scripts/ai/run-repo-tests.sh*'),
            ],
        ),

        'architect' => aiPermissionAgentSpecReadonly(
            editSurface: 'none',
            render: aiPermissionRenderTaskAllow(),
            denyPacks: aiPermissionPackSetCommonReadDeny(),
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
        ),

        'reviewer' => aiPermissionAgentSpecReadonly(
            editSurface: 'none',
            render: aiPermissionRenderTaskAsk(),
            // AgentPermissionPolicyTest::testReviewerAgentsAllowReadOnlyReviewGitWithoutBroadBranchMutation
            // forbids broad 'git branch*' for reviewer-class agents (destructive branch
            // deletion risk); tighten via a pack pair, then reopen the narrow sub-patterns.
            denyPacks: ['core.safe_read.deny_common_generics', 'git.branch_wildcard_deny'],
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
                'git.deny_blame',
                'git.deny_rev_parse',
            ],
            allowPacks: ['verify.install_coverage_allow', 'proof.validate_script'],
            askPacks: ['verify.manual_ask'],
            exceptions: [
                // Agent-specific narrowing beyond the shared deny packs.
                aiPermissionBashDeny(aiPatternGit('branch*')),
            ],
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
                aiPermissionBashDeny('bat *'),
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
            denyPacks: aiPermissionPackSetCommonReadDeny(),
            allowPacks: [
                'git.stash_read',
                'doctor.scripts',
                ...aiPermissionPackSetFullProof(),
            ],
            // Slice D: php-lint/phpunit-direct/js-core now sourced via language overlays
            // instead of the (reconciled) aiPermissionPackSetFullProof() bundle's former
            // proof.php_lint/proof.phpunit_direct/proof.js_test_lint_typecheck members.
            // Byte-stable: js-core is an exact copy of proof.js_test_lint_typecheck (no
            // yarn/bun — refactorer never granted those, unlike implementer).
            languageOverlays: ['php-lint', 'php-phpunit', 'js-core'],
            askPacks: ['context.packaging', 'core.safe_read.raw_read_ask_gate'],
            exceptions: [
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
                // Ground truth: refactorer grants no package-manager mutations at all.
                aiPermissionBashDeny('composer install*'),
                aiPermissionBashDeny('composer update*'),
                aiPermissionBashDeny('composer require*'),
                aiPermissionBashDeny('npm install*'),
                aiPermissionBashDeny('npm ci*'),
                aiPermissionBashDeny('pnpm install*'),
                aiPermissionBashDeny('pnpm add*'),
                aiPermissionBashDeny('yarn install*'),
                aiPermissionBashDeny('yarn add*'),
                aiPermissionBashDeny('bun install*'),
                aiPermissionBashDeny('bun add*'),
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
    ];
}
