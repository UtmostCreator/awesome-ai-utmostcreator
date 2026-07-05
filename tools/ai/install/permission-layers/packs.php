<?php

declare(strict_types=1);

require_once __DIR__ . '/core.php';

/**
 * Named, reusable permission packs — small effect-homogeneous rule groups shared across
 * agent compositions (docs/tickets/arch-todo-permission-layer-composition-20260705T004618Z/plan.md,
 * Slice 10: Permission Pack Refactor).
 *
 * Why this file exists: `compositions.php`'s per-agent `exceptions` list was doing four
 * different jobs at once (inherited-baseline removals, role-capability grants, one-off agent
 * quirks) and by the 7th composed agent was silently recreating the ~70% cross-agent
 * duplication this whole ticket exists to remove (e.g. the same 6-pattern deny group
 * hand-copied into four agents). Packs are the reusable middle layer between the generic
 * core/profile/verify/language/stack layers and a genuinely agent-unique `exceptions` entry.
 *
 * Rules:
 * - Every pack is built with the existing `aiPermissionEntries()` helper (core.php) — no new
 *   allow_bash()/deny_bash()/ask_bash() wrapper functions are introduced (N-2 spirit: reuse
 *   the shape that already exists instead of duplicating it).
 * - Every pack is effect-homogeneous: entirely `allow`, entirely `deny`, or entirely `ask`. A
 *   "tighten broad, then reopen narrow" case is always two packs (one deny, one allow), never
 *   one mixed-effect pack — see 'git.branch_wildcard_deny' + 'git.branch_narrow_read' below.
 * - `exceptions` in compositions.php stays for genuine one-off, non-reusable agent quirks only.
 * - Pack membership recorded here reflects ground truth already proven correct in Slices 2–4;
 *   this file reorganizes it, it does not re-derive it.
 *
 * @return array<string,list<array{permission:string,pattern:string,effect:string}>>
 */
function aiPermissionPacks(): array
{
    return [
        // Generic CLI-tool defaults that core:safe-read grants but a handful of agents ground-truth
        // do not (architect, reviewer, config-maintainer, refactorer).
        'core.safe_read.deny_common_generics' => aiPermissionEntries('bash', [
            'date *' => 'deny',
            'uuidgen' => 'deny',
            'wc *' => 'deny',
            'sort *' => 'deny',
            'uniq *' => 'deny',
            'du -h *' => 'deny',
        ]),

        // The two "script-first" agents (repository-researcher, repository-reviewer) deny nearly
        // every generic CLI tool core:safe-read would otherwise allow, by design.
        'core.safe_read.deny_script_first_generics' => aiPermissionEntries('bash', [
            'command -v *' => 'deny',
            'test -f *' => 'deny',
            'test -x *' => 'deny',
            'test -d *' => 'deny',
            'stat *' => 'deny',
            'date *' => 'deny',
            'uuidgen' => 'deny',
            'pwd' => 'deny',
            'eza *' => 'deny',
            'sed -n *' => 'deny',
            'head *' => 'deny',
            'tail *' => 'deny',
            'nl *' => 'deny',
            'wc *' => 'deny',
            'sort *' => 'deny',
            'uniq *' => 'deny',
            'file *' => 'deny',
            'du -h *' => 'deny',
            'jq *' => 'deny',
            'yq *' => 'deny',
            'scc *' => 'deny',
            'tokei *' => 'deny',
            'ast-grep *' => 'deny',
            'bat *' => 'deny',
            'fx *' => 'deny',
            'glow *' => 'deny',
            'difft *' => 'deny',
            'delta *' => 'deny',
            'git branch*' => 'deny',
            'php tools/ai/ai.php placeholders*' => 'deny',
            'php tools/ai/ai.php verify*' => 'deny',
            'php tools/ai/ai.php preflight*' => 'deny',
            'php tools/ai/ai.php list' => 'deny',
            'php tools/ai/ai.php next*' => 'deny',
            'php tools/ai/ai.php freshness*' => 'deny',
            'php tools/ai/ai.php packs*' => 'deny',
            'php tools/ai/ai.php env-check*' => 'deny',
            'php tools/ai/ai.php install-docs --check' => 'deny',
            'lychee *' => 'deny',
            'actionlint*' => 'deny',
            'shfmt -d *' => 'deny',
            'shellcheck *' => 'deny',
        ]),

        // Ground truth: script-first agents ask before raw search/read tools instead of denying
        // or freely allowing them.
        'raw_tools.ask_gated' => aiPermissionEntries('bash', [
            'grep *' => 'ask',
            'find *' => 'ask',
            'cat *' => 'ask',
            'sed *' => 'ask',
            'awk *' => 'ask',
        ]),

        // Manual verification probe: agents without the 'verify'/'impl' profile (which already
        // include script-tiers:ai-verify) need this granted explicitly.
        'verify.manual_ask' => aiPermissionEntries('bash', [
            'bash scripts/ai/ai-verify.sh *' => 'ask',
        ]),

        'verify.test_probes' => aiPermissionEntries('bash', [
            'bash scripts/ai/ai-test-select.sh *' => 'allow',
            'bash scripts/ai/run-repo-tests.sh*' => 'allow',
        ]),

        'verify.scoped_allow' => aiPermissionEntries('bash', [
            'AI_VERIFY_SCOPE=changed VERIFY_SECRETS=0 bash scripts/ai/ai-verify.sh *' => 'allow',
            'env AI_VERIFY_SCOPE=changed VERIFY_SECRETS=0 bash scripts/ai/ai-verify.sh *' => 'allow',
        ]),

        // Not part of any ai-read/ai-verify script tier; four agents grant it explicitly
        // (reviewer, workflow-auditor, config-maintainer, implementer).
        'verify.install_coverage_allow' => aiPermissionEntries('bash', [
            'bash scripts/ai/ai-install-coverage.sh *' => 'allow',
        ]),

        // gh-pr-context is dangerous-by-default (script-tiers:ai-deny-dangerous); reviewer-class
        // agents legitimately need it for PR review context.
        'git.pr_context_allow' => aiPermissionEntries('bash', [
            'bash scripts/ai/gh-pr-context.sh *' => 'allow',
        ]),

        // PR/review-focused git read commands beyond core:git-read's baseline.
        'git.review_extra' => aiPermissionEntries('bash', [
            'git merge-base*' => 'allow',
            'git range-diff*' => 'allow',
            'git diff-tree*' => 'allow',
            'git cherry' => 'allow',
            'git cherry -v*' => 'allow',
            'git for-each-ref*' => 'allow',
            'git config --get-regexp ^alias\\\\.' => 'allow',
        ]),

        'git.stash_read' => aiPermissionEntries('bash', [
            'git stash list*' => 'allow',
            'git stash show*' => 'allow',
        ]),

        // Reviewer-class broad-git-branch* tightening (AgentPermissionPolicyTest forbids broad
        // git branch* for reviewer-class agents — destructive branch deletion risk). Always used
        // as a pair: deny the wildcard, then reopen only the narrow, safe sub-patterns.
        'git.branch_wildcard_deny' => aiPermissionEntries('bash', [
            'git branch*' => 'deny',
        ]),
        'git.branch_narrow_read' => aiPermissionEntries('bash', [
            'git branch' => 'allow',
            'git branch -vv' => 'allow',
            'git branch --show-current' => 'allow',
            'git branch --sort=*' => 'allow',
        ]),

        'doctor.scripts' => aiPermissionEntries('bash', [
            'bash -n scripts/*.sh' => 'allow',
            'bash -n scripts/**/*.sh' => 'allow',
            'bash -n scripts/doctor.sh' => 'allow',
            'bash scripts/doctor.sh' => 'allow',
            'bash scripts/doctor.sh *' => 'allow',
        ]),

        // Split into atomic packs (not one coarse "proof.php_tools" bundle): ground truth shows
        // agents mix and match these independently — e.g. workflow-auditor/config-maintainer only
        // want proof.validate_script, repository-reviewer wants validate_script+generate_check but
        // not php_lint/phpunit_direct. A coarse bundle would force partial-match agents back into
        // duplicated exceptions, recreating the exact problem this refactor removes.
        'proof.php_lint' => aiPermissionEntries('bash', [
            'php -l *' => 'allow',
        ]),
        'proof.phpunit_direct' => aiPermissionEntries('bash', [
            'vendor/bin/phpunit *' => 'allow',
            './vendor/bin/phpunit *' => 'allow',
            'phpunit *' => 'allow',
        ]),
        'proof.validate_script' => aiPermissionEntries('bash', [
            'php tools/ai/validate-*.php *' => 'allow',
        ]),
        'proof.generate_check' => aiPermissionEntries('bash', [
            'php tools/ai/generate-*.php --check*' => 'allow',
        ]),

        // Shared by implementer + refactorer; yarn/bun variants are not part of this pack (only
        // implementer's ground truth grants those — kept as an agent-specific inline addition).
        'proof.js_test_lint_typecheck' => aiPermissionEntries('bash', [
            'npm test*' => 'allow',
            'npm run test*' => 'allow',
            'npm run lint*' => 'allow',
            'npm run typecheck*' => 'allow',
            'pnpm test*' => 'allow',
            'pnpm run test*' => 'allow',
            'pnpm run lint*' => 'allow',
            'pnpm run typecheck*' => 'allow',
        ]),

        'proof.markdown' => aiPermissionEntries('bash', [
            'markdownlint-cli2 *' => 'allow',
        ]),

        'proof.security' => aiPermissionEntries('bash', [
            'semgrep *' => 'allow',
        ]),

        'context.packaging' => aiPermissionEntries('bash', [
            'repomix *' => 'ask',
            'files-to-prompt *' => 'ask',
            'code2prompt *' => 'ask',
        ]),

        // --- N-8 sweep packs (2026-07-05, docs/tickets/arch-todo-permission-packs-handoff-*
        // P1): single-pattern reuse discovered across 2+ agents while composing bootstrapper,
        // script-runner, and super-implementer. Kept atomic (one pack per pattern) rather than
        // bundled, matching this file's existing precedent (proof.php_lint/phpunit_direct/
        // validate_script/generate_check) — ground truth shows agents mix and match these
        // independently, not as a fixed bundle.
        'core.safe_read.deny_file_probe' => aiPermissionEntries('bash', [
            'file *' => 'deny',
        ]),
        'core.safe_read.deny_nl' => aiPermissionEntries('bash', [
            'nl *' => 'deny',
        ]),
        'core.safe_read.deny_sed_n' => aiPermissionEntries('bash', [
            'sed -n *' => 'deny',
        ]),
        'core.safe_read.deny_eza' => aiPermissionEntries('bash', [
            'eza *' => 'deny',
        ]),
        'core.safe_read.deny_rg' => aiPermissionEntries('bash', [
            'rg *' => 'deny',
        ]),
        // Not a "safe read" tool (chown is a mutation), but grouped here for the same reason
        // as the other atomic single-pattern packs: script-runner and post-install both
        // explicitly tighten it beyond the '*' floor's default posture for their profile.
        'hard_stop.deny_chown' => aiPermissionEntries('bash', [
            'chown *' => 'deny',
        ]),
        'core.safe_read.deny_git_grep' => aiPermissionEntries('bash', [
            'git grep *' => 'deny',
        ]),
        'git.deny_blame' => aiPermissionEntries('bash', [
            'git blame*' => 'deny',
        ]),
        'git.deny_rev_parse' => aiPermissionEntries('bash', [
            'git rev-parse*' => 'deny',
        ]),
        // ai-write tier's four scripts (ai-edit/ai-rollback/session-checkpoint/
        // install-mandatory-tools), all ask — for agents that need this grant without the
        // full 'impl' profile (which already includes it via script-tiers:ai-write).
        'script.ai_write_ask' => aiPermissionEntries('bash', [
            'bash scripts/ai/ai-edit.sh *' => 'ask',
            'bash scripts/ai/ai-rollback.sh *' => 'ask',
            'bash scripts/ai/session-checkpoint.sh *' => 'ask',
            'bash scripts/ai/install-mandatory-tools.sh *' => 'ask',
        ]),
        'impl.sg_allow' => aiPermissionEntries('bash', [
            'sg *' => 'allow',
        ]),
        'impl.composer_validate_allow' => aiPermissionEntries('bash', [
            'composer validate*' => 'allow',
        ]),
        'install.docs_allow' => aiPermissionEntries('bash', [
            'php tools/ai/ai.php install-docs*' => 'allow',
        ]),

        // Slice 9 check-3 policy decision (docs/tickets/arch-todo-permission-packs-handoff-*
        // plan.md): raw read tools were `allow` by default (core:safe-read) for every
        // impl-profile agent — a real, cross-cutting exposure the Slice 9 landing pass
        // deliberately left as a ratchet test rather than fixing. Explicit follow-up decision:
        // ask-gate them instead of silently allowing. Prefer the `preview-file.sh`/
        // `rg-code.sh`/`fd-files.sh` wrappers first; this pack is the approval fallback for
        // when a raw tool is genuinely needed.
        'core.safe_read.raw_read_ask_gate' => aiPermissionEntries('bash', [
            'rg *' => 'ask',
            'bat *' => 'ask',
            'jq *' => 'ask',
            'yq *' => 'ask',
            'head *' => 'ask',
            'tail *' => 'ask',
            'sed -n *' => 'ask',
        ]),
    ];
}

/**
 * Resolve a list of pack names into a flat list of entries, in registration order across
 * packs (later pack in the list wins for a duplicate pattern, same as any other layer).
 * Throws on an unknown pack name (same discipline as aiPermissionNamedLayer()).
 *
 * @param list<string> $packNames
 * @return list<array{permission:string,pattern:string,effect:string}>
 */
function aiPermissionResolvePacks(array $packNames): array
{
    $packs = aiPermissionPacks();
    $entries = [];
    foreach ($packNames as $packName) {
        if (!array_key_exists($packName, $packs)) {
            throw new InvalidArgumentException(sprintf('Unknown permission pack: %s', $packName));
        }
        foreach ($packs[$packName] as $entry) {
            $entries[] = $entry;
        }
    }

    return $entries;
}
