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
        //
        // NOTE (Slice D, docs/tickets/arch-todo-complete-permission-composition-migration/
        // plan.md): 'proof.php_lint', 'proof.phpunit_direct', and 'proof.js_test_lint_typecheck'
        // (formerly here) were RETIRED — every agent that referenced them (config-maintainer,
        // reviewer, refactorer, bootstrapper, implementer) now sources the same commands via the
        // 'php-lint'/'php-phpunit'/'js-core' atomic language overlays or the coarse 'php'/'js-ts'
        // overlays instead (language-overlays.php), so a non-PHP/non-JS consumer install no
        // longer inherits these grants as a universal pack. Confirmed via grep before removal:
        // no composition referenced these 3 pack names by string at retirement time.
        // Grants the wildcard `validate-*.php *` (read-only auditors mix-and-match this with
        // generate_check). One validator, validate-generated-artifacts.php, accepts --write/--fix
        // and regenerates committed artifacts; that mutation path is gated at the script itself
        // behind AI_ALLOW_ARTIFACT_WRITE=1 (see tools/ai/validate-generated-artifacts.php), so a
        // bare --write from a read-only agent is refused rather than mutating tracked files. The
        // grant can stay wildcard because the script, not the permission grammar, enforces safety.
        'proof.validate_script' => aiPermissionEntries('bash', [
            'php tools/ai/validate-*.php *' => 'allow',
        ]),
        'proof.generate_check' => aiPermissionEntries('bash', [
            'php tools/ai/generate-*.php --check*' => 'allow',
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
        // Extracted (agent-critic MAJOR fix, 2026-07-07): 'bat *' deny was a duplicated
        // inline exception on 'architecture-plan-writer' and 'reviewer' (bat is a raw file
        // reader with no secret-blocking guard, unlike preview-file.sh); atomic pack per this
        // file's single-pattern-reuse precedent (deny_eza/deny_rg/deny_test_x).
        'core.safe_read.deny_bat' => aiPermissionEntries('bash', [
            'bat *' => 'deny',
        ]),
        'core.safe_read.deny_rg' => aiPermissionEntries('bash', [
            'rg *' => 'deny',
        ]),
        // Extracted (Slice C, docs/tickets/arch-todo-complete-permission-composition-
        // migration/plan.md) from workflow-auditor's inline exception so
        // architecture-plan-writer can share the exact same tightening without violating
        // the no-duplicated-exception-pattern test.
        'core.safe_read.deny_test_x' => aiPermissionEntries('bash', [
            'test -x *' => 'deny',
        ]),
        // Extracted (Slice C) from researcher's + architecture-plan-writer's identical
        // jq/yq-deny exceptions (always co-occurring in ground truth, kept as one pack
        // rather than two atomic ones to match that real usage pattern).
        'core.safe_read.deny_jq_yq' => aiPermissionEntries('bash', [
            'jq *' => 'deny',
            'yq *' => 'deny',
        ]),
        // Extracted (Slice C) from post-install's + architecture-plan-writer's identical
        // head/tail-deny exceptions (always co-occurring in ground truth).
        'core.safe_read.deny_head_tail' => aiPermissionEntries('bash', [
            'head *' => 'deny',
            'tail *' => 'deny',
        ]),
        // Secret-path deny backstop for reader wrappers on OpenCode `'*': deny`-floor agents
        // (plan-2-opencode-secret-deny-backstop). The reader wrappers below are granted broad
        // `<reader> *: allow`, which reopens EVERY path for that wrapper — including secret files
        // — despite the agent's prose Sensitive File Rule. Under OpenCode's `.findLast()`
        // file-order resolution (render-adapters.php:98-107), a deny placed AFTER that allow flips
        // a secret-path invocation back to deny, making the guard a real permission-level backstop
        // instead of prompt-only. Two structural requirements make this work and are handled
        // outside this pack: (1) it is wired via the `backstop_deny_packs` compose lane so it
        // renders AFTER the `allow_packs`/reader allows (BLOCKER B), and (2) render-adapters.php
        // retains `backstop`-class denies through the same-as-floor-effect no-op filter (BLOCKER
        // A). OpenCode-scoped only: Copilot/Claude project allow-effect entries solely, so these
        // denies are inertly skipped there and those runtimes keep the prompt-level rule as an
        // honest, documented fallback. Bounded to path-argument reader wrappers; raw git
        // show/log/diff/blame revspec access is intentionally NOT covered here (a revspec is not a
        // filesystem path, so a `*.env*` glob would be both leaky and over-broad) and stays
        // prompt-only. Glob shape `<reader> *<secret>*` is standard `*`-any-chars matching,
        // verified not to false-positive on ordinary paths.
        'core.safe_read.deny_secret_reads' => aiPermissionEntries('bash', aiPermissionSecretReadDenyMap()),

        // Not a "safe read" tool (chown is a mutation), but grouped here for the same reason
        // as the other atomic single-pattern packs: script-runner and post-install both
        // explicitly tighten it beyond the '*' floor's default posture for their profile.
        'hard_stop.deny_chown' => aiPermissionEntries('bash', [
            'chown *' => 'deny',
        ]),
        // Governance remediation (docs/tickets/ai-run-ledger-rollup-slice-a/arch-todo-
        // permission-budget-and-delete-posture-20260709.md, P1 sub-item B): plain 'rm *'
        // (distinct from the immutable-floor 'rm -rf *') must route to ASK, not a silent
        // allow or a hard deny, for every agent that legitimately might need to remove a
        // file it created and no longer needs. Extracted into a named pack because
        // implementer, super-implementer, and post-install all need the identical entry
        // (testNoExceptionPatternDuplicatedAcrossTwoOrMoreAgents forbids repeating the same
        // exception pattern inline across 2+ agents).
        'hard_stop.ask_rm' => aiPermissionEntries('bash', [
            'rm *' => 'ask',
        ]),
        // Grant (2026-07-10): 'python3 *' was relocated out of the immutable hard-deny floor
        // to a NON-immutable 'deny' default in core:safe-read (see core.php's two NOTE blocks).
        // Read-only agents fall through to that deny; the write/edit-capable impl-tier agents
        // (implementer, refactorer, bootstrapper, post-install, super-implementer) route it to
        // 'ask' via this shared pack — never a silent 'allow', matching the AGENTS.md approval-
        // boundary posture for interpreter execution. Extracted into a named pack because 5
        // agents need the identical entry (testNoExceptionPatternDuplicatedAcrossTwoOrMoreAgents
        // forbids repeating the same exception pattern inline across 2+ agents). To make this
        // super-implementer-specific instead, remove this pack from the other agents' askPacks
        // and keep it only on super-implementer's askPacks list in compositions.php.
        'impl.ask_python3' => aiPermissionEntries('bash', [
            'python3 *' => 'ask',
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
        // NOTE (Slice D): 'impl.composer_validate_allow' (formerly here) was RETIRED —
        // bootstrapper now sources 'composer validate*' via the 'php-composer-validate'
        // atomic language overlay, and implementer via the coarse 'php' overlay. See the
        // same note above 'proof.validate_script' for the retirement rationale.
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

        // --- Optional-agent composition packs (docs/tickets/arch-todo-optional-agent-
        // permission-composition-20260705T221434Z/plan.md, Slice A): discovered while
        // ground-truth-diffing infra-auditor/bugfix/build-config/docs against the
        // readonly/impl profile defaults. `core:safe-read` grants a much wider generic CLI
        // toolkit than these 4 agents' shipped blocks show; this pack denies back exactly
        // the subset none of the 4 want (each keeps a different, smaller live subset —
        // rg/jq/wc/sed-n/head/tail vary per agent and stay agent-specific via the language/
        // pack combination chosen per composition, not folded into this pack).
        'core.safe_read.deny_extended_probe_tools' => aiPermissionEntries('bash', [
            'command -v *' => 'deny',
            'test -f *' => 'deny',
            'test -x *' => 'deny',
            'test -d *' => 'deny',
            'stat *' => 'deny',
            'date *' => 'deny',
            'uuidgen' => 'deny',
            'eza *' => 'deny',
            'nl *' => 'deny',
            'sort *' => 'deny',
            'uniq *' => 'deny',
            'file *' => 'deny',
            'du -h *' => 'deny',
            'scc *' => 'deny',
            'tokei *' => 'deny',
            'ast-grep *' => 'deny',
            'bat *' => 'deny',
            'fx *' => 'deny',
            'glow *' => 'deny',
            'difft *' => 'deny',
            'delta *' => 'deny',
        ]),

        // core:safe-read's shared 'ls -1 scripts/ai/*.sh | sort' bullet is a piped compound
        // command that contradicts docs/ai/agent-script-access.md's "never compose shell
        // pipes" guidance and matches no settings.json Bash(...) pattern (a fresh agent-critic
        // audit finding scoped to build-config, not a wider sweep — see
        // docs/tickets/claude-agent-fleet-remediation/plan-13-build-config-render-drift.md).
        // Single-agent use today (build-config only); kept as a named pack rather than an
        // inline exception because architecture-plan-writer/ui-builder already have their own
        // pre-existing exceptions for the same pattern at a different effect (deny/ask), and
        // reusing their exact (pattern, effect) tuple here would trip
        // testNoExceptionPatternDuplicatedAcrossTwoOrMoreAgents.
        'core.safe_read.deny_ls_pipe_sort' => aiPermissionEntries('bash', [
            'ls -1 scripts/ai/*.sh | sort' => 'deny',
        ]),

        // 'git grep *' is not part of core:git-read at all (only bare 'grep *' is, and it
        // defaults deny) — 4 optional agents (infra-auditor, bugfix, build-config, upgrade)
        // grant it explicitly.
        'git.grep_allow' => aiPermissionEntries('bash', [
            'git grep *' => 'allow',
        ]),

        // Ground truth: bugfix/build-config/upgrade grant ONLY 'git add*'/'git commit*' as
        // ask from core:git-mutating-ask's 14-pattern default set — deny back the other 13.
        'git.mutating_add_commit_only_deny' => aiPermissionEntries('bash', [
            'git restore *' => 'deny',
            'git reset*' => 'deny',
            'git stash push*' => 'deny',
            'git stash pop*' => 'deny',
            'git stash apply*' => 'deny',
            'git stash drop*' => 'deny',
            'git fetch*' => 'deny',
            'git merge*' => 'deny',
            'git pull*' => 'deny',
            'git checkout*' => 'deny',
            'git switch*' => 'deny',
            'git tag*' => 'deny',
            'git cherry-pick*' => 'deny',
            'git revert*' => 'deny',
        ]),

        // Ground truth: bugfix/build-config/upgrade keep composer install/update/require +
        // npm install/ci + pnpm install + yarn install as ask (core:package-manager-ask
        // default). The other 4 of that 11-pattern default set (pnpm add/yarn add/bun
        // install/bun add) were previously hard-denied here; governance remediation
        // (docs/tickets/ai-run-ledger-rollup-slice-a/arch-todo-permission-budget-and-delete-
        // posture-20260709.md, P1 sub-item E) requires installs to warn-then-ASK rather than
        // hard-deny, so these now match the rest of the set at 'ask'. Pack id/denyPacks
        // reference kept unchanged (list membership does not affect the composed effect —
        // see compose.php aiPermissionApplyLayer, which uses each entry's own `effect`
        // field) to keep this a minimal, single-file diff.
        'package_manager.narrow_no_add_or_bun_deny' => aiPermissionEntries('bash', [
            'pnpm add*' => 'ask',
            'yarn add*' => 'ask',
            'bun install*' => 'ask',
            'bun add*' => 'ask',
        ]),

        // Ground truth: refactorer (already composed) and docs (this ticket) previously
        // granted NO package-manager mutations at all — the full core:package-manager-ask
        // 11-pattern default set was hard-denied. Governance remediation (docs/tickets/
        // ai-run-ledger-rollup-slice-a/arch-todo-permission-budget-and-delete-posture-
        // 20260709.md, P1 sub-item E) requires installs to warn-then-ASK rather than
        // hard-deny — flipped to 'ask' here, which now matches the 'impl' profile's
        // core:package-manager-ask default (a harmless same-effect restatement, not a
        // behavior regression: FORBIDDEN_ALLOW_PATTERNS in AgentPermissionPolicyTest.php only
        // forbids 'allow' for these patterns, never requires 'deny'). Extracted here (N-8)
        // because refactorer previously carried these as 11 inline `exceptions`, which would
        // have collided with docs needing the identical 11 patterns
        // (testNoExceptionPatternDuplicatedAcrossTwoOrMoreAgents).
        'package_manager.deny_all_mutations' => aiPermissionEntries('bash', [
            'composer install*' => 'ask',
            'composer update*' => 'ask',
            'composer require*' => 'ask',
            'npm install*' => 'ask',
            'npm ci*' => 'ask',
            'pnpm install*' => 'ask',
            'pnpm add*' => 'ask',
            'yarn install*' => 'ask',
            'yarn add*' => 'ask',
            'bun install*' => 'ask',
            'bun add*' => 'ask',
        ]),

        // --- Agent-creator-family readonly-profile packs (docs/tickets/arch-todo-optional-
        // agent-permission-composition-20260705T221434Z/plan.md, Slice A continuation):
        // discovered while composing agent-creator-static-validator/semantic-verifier/
        // runtime-guardian. All 3 share this exact 6-script deny-back-from-default set
        // (4 script-tiers:ai-context-ask scripts tightened from 'ask' to 'deny', plus
        // ai-file-freshness/ai-doc-check tightened from the ai-read baseline's 'allow' to
        // 'deny') — architecture-plan-writer denies the same 6 patterns too, but as part of
        // a much larger, agent-unique 22-pattern exceptions list it does not otherwise
        // share with these 3 agents, so it is left as-is (not refactored) rather than
        // risking its already-verified byte-stable composition for a partial overlap.
        'ai_scripts.deny_context_and_doc_scripts' => aiPermissionEntries('bash', [
            'bash scripts/ai/pack-context.sh *' => 'deny',
            'bash scripts/ai/run-repomix-context.sh *' => 'deny',
            'bash scripts/ai/repomix-context-tree.sh *' => 'deny',
            'bash scripts/ai/repomix-scc-router.sh *' => 'deny',
            'bash scripts/ai/ai-file-freshness.sh *' => 'deny',
            'bash scripts/ai/ai-doc-check.sh *' => 'deny',
        ]),

        // core:git-read grants both by default; static-validator/semantic-verifier/
        // runtime-guardian all deny them back (narrower git surface than every other
        // composed agent). Kept atomic (not bundled with git.deny_blame/deny_rev_parse/
        // branch_wildcard_deny) since those 3 already exist as their own single-pattern
        // packs and are reused as-is alongside these 2 new ones.
        'git.deny_show' => aiPermissionEntries('bash', [
            'git show*' => 'deny',
        ]),
        'git.deny_ls_files' => aiPermissionEntries('bash', [
            'git ls-files*' => 'deny',
        ]),

        // agent-creator-static-validator and `docs` both deny 'yq *' alone (jq stays
        // allowed) — extracted here because leaving both as inline exceptions would
        // duplicate a pattern across 2+ agents (testNoExceptionPatternDuplicatedAcross
        // TwoOrMoreAgents). Distinct from the existing 'core.safe_read.deny_jq_yq' pack,
        // which denies both jq and yq together for agents that want neither.
        'core.safe_read.deny_yq' => aiPermissionEntries('bash', [
            'yq *' => 'deny',
        ]),

    ];
}

/**
 * Builds the `core.safe_read.deny_secret_reads` pattern map: for each content-exposing reader
 * wrapper and each secret-file glob, emit one `deny`.
 *
 * Pattern shape is `*<reader> *<secret>*`. The LEADING `*` deliberately absorbs any command
 * prefix (bare, `AI_OUTPUT=json `, `env AI_OUTPUT=json `) that agent frontmatter grants for the
 * JSON-capable readers, so one pattern covers all invocation variants instead of three — this
 * keeps the generated `permission.bash` block within each agent's line budget
 * (docs/ai/ai-file-standards.md). Because these are `deny` entries a slightly broad match fails
 * safe: the worst case is denying an unrelated command that literally contains the reader name
 * followed by a secret token, which is acceptable for a defense-in-depth backstop. The trailing
 * `*<secret>*` matches a secret token anywhere in the path argument (trailing `.env`, mid-path
 * `config/.env.local`) without false-positiving on ordinary source paths — verified by
 * shell-glob probe during implementation.
 *
 * Scope is the readers that print file CONTENT (the real secret-value exposure vector):
 * `preview-file.sh` (file contents), `ai-search.sh`/`ai-search-multi.sh`/`rg-code.sh` (matching
 * lines, which may include secret values), and `git-forensics.sh` (blame prints file lines).
 * `fd-files.sh` (filenames only) and `query-usage.sh` (token/byte counts only) do not print
 * secret content and are intentionally omitted. The secret globs match the existing edit-surface
 * secret vocabulary (edit-surfaces.php) plus the extra key/ssh shapes named in the agents' prose
 * Sensitive File Rule, so the two surfaces do not diverge.
 *
 * @return array<string,string> ordered pattern => 'deny' map
 */
function aiPermissionSecretReadDenyMap(): array
{
    // Content-exposing readers only. Leading `*` (added below) absorbs the AI_OUTPUT=json / env
    // invocation prefixes, so each reader needs one entry per glob, not one per prefix per glob.
    // Bounded to the three highest-value content printers to keep the generated block within
    // every affected agent's line budget: preview-file.sh (raw file contents) and
    // ai-search.sh/rg-code.sh (matching lines, which can echo secret values). `ai-search-multi`
    // funnels through the same safe MODEs as `ai-search`, and `git-forensics` blame is a lower
    // exposure surface; both stay covered by the prompt-level Sensitive File Rule.
    $readers = [
        'preview-file.sh',
        'ai-search.sh',
        'rg-code.sh',
    ];

    // Secret-file globs (trailing `*` where the extension may be followed by more path/args).
    // The eight highest-value secret shapes; drawn from edit-surfaces.php's deny vocabulary plus
    // the ssh key names in the agents' Sensitive File Rule. `.npmrc`/`id_ed25519` are lower-value
    // and left to the prompt-level rule to keep the pattern count within budget.
    $secretGlobs = [
        '*.env*',
        '*.pem',
        '*.key',
        '*.crt',
        '*id_rsa*',
        '*secrets.*',
        '*credentials.*',
        '*auth.json*',
    ];

    $commands = [];
    foreach ($readers as $reader) {
        // `*scripts/ai/<reader>` — leading star absorbs `bash `, `AI_OUTPUT=json bash `, and
        // `env AI_OUTPUT=json bash ` prefixes with a single pattern.
        $commands[] = '*scripts/ai/' . $reader;
    }

    $map = [];
    foreach ($commands as $command) {
        foreach ($secretGlobs as $glob) {
            $map[$command . ' ' . $glob] = 'deny';
        }
    }

    return $map;
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
