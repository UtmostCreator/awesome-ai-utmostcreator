# Architecture Plan — Agent-Creator Semantic Verifier Render Sync

- Ticket: none (branch: claude-agent-fleet-remediation)
- Source: architect design, agent-critic score 59/blocked on .claude/agents/agent-creator-semantic-verifier.md
- Generated: 2026-07-08 10:26:06 BST
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-7-agent-creator-semantic-verifier-render-sync.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-7-agent-creator-semantic-verifier-render-sync.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-7-agent-creator-semantic-verifier-render-sync.md`). See "Archive On Completion" below for the exact steps.

## Context

Commit `5900d5ee` already fixed most of `.claude/agents/agent-creator-semantic-verifier.md`'s gaps in the canonical template (`packages/ai-universal-rules/templates/optional/agents/agent-creator-semantic-verifier.md`), but the fixes were never propagated to the Claude render, and the canonical template itself still lacks a Script Access bullet for six granted scripts.

## Problem

BLOCKER — the "Static Validator hasn't returned exit 0, send it back" failure branch names no next agent (should route to `agent-creator-static-validator`). MAJOR — missing canonical Hard Rule ("if original request missing, don't infer intent, stop and request it") already present in template but dropped from this stale render. MAJOR — Script Access claims `ai-verify.sh` usable, contradicted by this file's own Bash Command Policy. MAJOR — "MATCHES WITH NOTES" verdict has no stated next step. MAJOR — "the supervisor" used instead of exact roster id `agent-creator-supervisor`. MINOR — six granted scripts (git-forensics.sh, repo-stats.sh, repo-tool-inventory.sh, check-file-refs.sh, ai-structured.sh, repomix-freshness.sh) never explained in Script Access — this gap exists in the canonical template itself, not just the render.

## Target Outcome

Render matches canonical template's already-fixed routing/hard-rule content; template gains the missing Script Access bullet for the six scripts; `ai-verify.sh` wording made runtime-agnostic.

## In Scope

1. Template edit: add the missing Script Access bullet documenting the six scripts.
2. Template edit: reword the `ai-verify.sh` bullet to be conditional on ask-tier support rather than asserting direct usability.
3. Regenerate `.claude/agents/agent-creator-semantic-verifier.md` from the corrected template — this alone closes the BLOCKER and 3 of 4 MAJORs since they're already fixed in canonical (commit `5900d5ee`) but never propagated.
4. Add a follow-up note (not a rewrite) to `docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/plan.md`'s agent-creator-semantic-verifier section recording that its "maintenance-only" verdict applied only to the template, and the Claude copy had drifted until this regeneration.

## Out Of Scope (Things To Avoid)

- Fixing the identical `ai-verify.sh` pattern in reviewer.md/release-auditor.md.
- Changing generator-managed permission.bash allowlist, `tools`/`disallowedTools`/`permissionMode`.
- Hand-editing `agent_assessment`.
- Touching the Copilot adapter unless independently confirmed to share the same drift.

## Affected Paths

- `packages/ai-universal-rules/templates/optional/agents/agent-creator-semantic-verifier.md`
- `.claude/agents/agent-creator-semantic-verifier.md` (regenerated)
- `docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/plan.md` (addendum only)

## Contracts And Boundaries

Edit only the canonical template; regenerate, never hand-type the Claude render.

## Todo Plan

- [x] P0: Add the missing Script Access bullet for git-forensics.sh/repo-stats.sh/repo-tool-inventory.sh/check-file-refs.sh/ai-structured.sh/repomix-freshness.sh to the canonical template.
- [x] P0: Reword the `ai-verify.sh` bullet to be runtime-conditional (usable only where ask-tier confirmation exists).
- [x] P0: Regenerate `.claude/agents/agent-creator-semantic-verifier.md` from the corrected template. Done via a narrowly-scoped one-off PHP script that called the same renderer functions as `tools/ai/render-adapters.php` but wrote only this one file — see Implementation Notes for why the broad `--write` was not run.
- [x] P1: Add a dated addendum note to `docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/plan.md`'s agent-creator-semantic-verifier section (do not rewrite existing prose).
- [x] P2: Re-run agent-critic to confirm all 6 findings closed. **Done** — a fresh agent-critic audit against `.claude/agents/agent-creator-semantic-verifier.md` scored 97/100, decision: approve, with only 3 MINOR findings (no BLOCKER, no MAJOR): a `settings.json` allowlist gap for `validate-agent-spec.php`, a fleet-wide global-vs-per-agent Bash scoping question routed to workflow-auditor, and a missing canonical-doc-reference line that is a pre-existing repo-wide pattern. None of these block this plan's original scope (the BLOCKER and 4 MAJORs from the original audit are all confirmed closed); the 3 MINOR findings are out-of-scope follow-ups, not required for this plan's closure.

## Acceptance Criteria

- [x] AC-01: The send-back branch names `agent-creator-static-validator` explicitly. Verified: `.claude/agents/agent-creator-semantic-verifier.md:130` — "If the Static Validator has not returned exit 0, next step is agent-creator-static-validator (send it back before judging)."
- [x] AC-02: The missing-user-request Hard Rule is present in the regenerated file. Verified: `.claude/agents/agent-creator-semantic-verifier.md:100`.
- [x] AC-03: The `ai-verify.sh` bullet no longer contradicts the Bash Command Policy. Verified: line 79 now reads "usable only on runtimes that support the `ask` approval tier ... on a runtime with no ask tier, treat it as unavailable unless a runtime-specific policy separately allowlists it," consistent with the same file's Bash Command Policy (lines 58-62), which does not list `ai-verify.sh` among Claude's approved commands.
- [x] AC-04: MATCHES WITH NOTES has a stated next step (`agent-creator-supervisor`). Verified: line 130 — "On MATCHES or MATCHES WITH NOTES, next step is agent-creator-supervisor for human approval and runtime guardrails."
- [x] AC-05: Routing uses `agent-creator-supervisor` verbatim, not "the supervisor." Verified: same line 130 citation above; no remaining "the supervisor" phrasing in the file.
- [x] AC-06: All six previously-unexplained scripts have a Script Access bullet. Verified: `.claude/agents/agent-creator-semantic-verifier.md:78` names all six (`git-forensics.sh`, `repo-stats.sh`, `repo-tool-inventory.sh`, `check-file-refs.sh`, `ai-structured.sh`, `repomix-freshness.sh`).

## Verification Plan

- Diff the regenerated file against the template for all six items (proves AC-01 through AC-06). **Ran**: `git diff -- .claude/agents/agent-creator-semantic-verifier.md` and targeted `Grep` reads — confirmed all six citations above.
- Re-run agent-critic to confirm all findings closed. **Ran**: fresh audit against `.claude/agents/agent-creator-semantic-verifier.md` scored 97/100, decision: approve, 0 BLOCKER, 0 MAJOR, 3 MINOR (settings.json allowlist gap for `validate-agent-spec.php`; fleet-wide global-vs-per-agent Bash scoping question routed to workflow-auditor; missing canonical-doc-reference line, a pre-existing repo-wide pattern). The 3 MINORs are out-of-scope follow-ups and do not block this plan's closure.
- Spot-check `tests/php/ClaudeAgentRendererTest.php` if present, to catch regressions in the render pipeline. **Ran**: `vendor/bin/phpunit tests/php/ClaudeAgentRendererTest.php` — 24 tests, 198 assertions, OK.

## Risks And Rollback

Risk — the `ai-verify.sh` wording fix must stay runtime-agnostic (no literal "Claude" in the shared template). Rollback: revert the two template edits and re-render from the prior committed state.

## Implementation Notes

All two In-Scope template edits and the regeneration landed as specified:

1. **Script Access bullet for the six scripts** (`packages/ai-universal-rules/templates/optional/agents/agent-creator-semantic-verifier.md:70`): added `- \`git-forensics.sh\` / \`repo-stats.sh\` / \`repo-tool-inventory.sh\` / \`check-file-refs.sh\` / \`ai-structured.sh\` / \`repomix-freshness.sh\` — inherited from the shared readonly-profile permission baseline, not agent-specific grants; this verifier's job is judging spec-versus-request fit, so none of these is expected to be invoked in normal operation.` This wording was adapted (not copied verbatim) from `agent-creator-static-validator.md`'s own near-identical bullet for the same "inherited baseline" gap (plan-4 precedent) — a >75% pattern overlap, so reuse was the right call per the reuse-check rule.
2. **`ai-verify.sh` reworded to be runtime-conditional** (same file, line 71): replaced the flat `` `ai-verify.sh` (`ask`) — only to sanity-check a claimed behavior; expect verification evidence. `` with wording that makes usability conditional on the runtime actually supporting an `ask` tier, and explicitly names "a runtime with no ask tier" rather than naming Claude — keeping the shared template runtime-agnostic per the plan's own Risk note.
3. **Regeneration of `.claude/agents/agent-creator-semantic-verifier.md`** — done via a one-off, narrowly-scoped PHP script (not committed; run from `/tmp/opencode` and deleted after use) that called the exact same renderer functions `tools/ai/render-adapters.php` uses (`aiInstallerRenderClaudeAgent`, the same placeholder map) but wrote only to this one target path. The full `php tools/ai/render-adapters.php --write` was deliberately **not** run: before any edit, `git status --short` showed 127 files already modified by other in-progress tickets on this branch, including many `.claude/agents/*.md` and `.github/agents/*.agent.md` files (e.g. `implementer.md`, `agent-creator-static-validator.agent.md`) with their own unrelated uncommitted content changes. The full `--write` pass has no per-agent scope and would have silently overwritten those files back to whatever their (possibly stale) source templates currently produce, clobbering the other tickets' work — the same risk plan-6's implementer identified and avoided the same way. The scoped script avoids this: a before/after `git status --short` hash comparison confirmed the set of modified files was unchanged (only the byte content of the already-modified target file changed), and `php tools/ai/render-adapters.php --check` (read-only) after the write confirmed `.claude/agents/agent-creator-semantic-verifier.md` no longer appears in the drift list, while the pre-existing unrelated drift entries (`implementer.md`, `implementer.agent.md`, `agent-creator-static-validator.agent.md`) were untouched.
4. **Addendum note** added to `docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/plan.md`'s `## agent-creator-semantic-verifier` section, dated 2026-07-08, appended after the existing three numbered improvements without altering any existing prose — records that the section's "maintenance-only" verdict covered only the template, that the Claude render had drifted until this plan's regeneration, and that the two new template gaps (missing six-script bullet, `ai-verify.sh` contradiction) were outside that earlier assessment's scope.

**Deviation — Copilot render (`.github/agents/agent-creator-semantic-verifier.agent.md`) left untouched.** This file already carried its own pre-existing uncommitted diff (confirmed via `git diff`) that mirrors the same drift pattern as the pre-plan-7 `.claude` render — consistent with "independently confirmed to share the same drift" per Out Of Scope item 4. However, it is not listed in this plan's Affected Paths, so per the explicit instruction to touch only Affected Paths, it was left as-is. `php tools/ai/render-adapters.php --check` confirms it still reports drift (now including the two new template edits, since it predates them). This is flagged, not fixed, matching the plan's Out Of Scope boundary; a follow-up ticket would need to add it to an Affected Paths list explicitly before regenerating it with the same scoped-script approach used here.

**Deviation — agent-critic re-run (Todo P2) not performed.** This session's tool set has no Task/subagent-dispatch capability, so `agent-critic` could not be invoked directly — the same environment limitation plan-6 hit. Recommended as the next verification step once that capability is available. Because this item stays unchecked, the plan is **not archived** (see Handoff Notes).

**Addendum (2026-07-08, follow-up session):** Todo P2 has since been completed. A fresh agent-critic audit against `.claude/agents/agent-creator-semantic-verifier.md` scored 97/100, decision: approve, with 0 BLOCKER, 0 MAJOR, and only 3 MINOR findings (out-of-scope follow-ups — see the Todo Plan P2 entry and Verification Plan agent-critic line above). All Todo Plan items and all Acceptance Criteria are now checked `[x]`; the plan is archived per "Archive On Completion."

### Verification Evidence

- `git diff -- packages/ai-universal-rules/templates/optional/agents/agent-creator-semantic-verifier.md` — 1 hunk, both template edits landed together as adjacent bullets; no unrelated lines touched. Verified.
- `git diff -- .claude/agents/agent-creator-semantic-verifier.md` — confirmed the rendered file now carries exactly the two new-in-this-plan lines plus the pre-existing (already-uncommitted, template-already-fixed) routing/hard-rule content from before this session. Verified.
- `Grep` for the AC-01/AC-02/AC-04/AC-05 phrases in the regenerated file — all four found at the expected lines (100, 130). Verified — see Acceptance Criteria citations above.
- `Grep` for `ai-verify.sh` / the six-script bullet in the regenerated file — both present (lines 78-79), and the file's own Bash Command Policy (lines 58-62) does not list `ai-verify.sh` as Claude-approved, so the two no longer contradict. Verified — proves AC-03, AC-06.
- `vendor/bin/phpunit tests/php/ClaudeAgentRendererTest.php` — 24 tests, 198 assertions, OK. Verified.
- `vendor/bin/phpunit tests/php/AgentPermissionPolicyTest.php tests/php/CopilotAgentRendererTest.php` — 159 tests, 566 assertions, OK (5 pre-existing skips, no failures). Ran as an additional spot-check beyond the plan's own Verification Plan, since both renderer/policy suites read the live template.
- `php tools/ai/render-adapters.php --check` (read-only) — confirms `.claude/agents/agent-creator-semantic-verifier.md` is no longer in the drift list; remaining drift entries (`implementer.md`, `implementer.agent.md`, `agent-creator-static-validator.agent.md`, and now also `agent-creator-semantic-verifier.agent.md` — the last newly drifted only because the Copilot side was intentionally not regenerated) are all pre-existing/out-of-scope, not introduced by this change. Verified.
- Before/after `git status --short` file-count and content hash comparison — confirmed the scoped regeneration script touched only the one target file's bytes and added no new modified-file entries to the working tree. Verified.
- Not run: `composer test:fast` (full suite) — the change is markdown-only prose in one agent template plus its single regenerated Claude render, already covered by the three targeted renderer/policy test files above; a full-suite run was judged unnecessary for this bounded slice, matching plan-6's own judgment call for an equivalent-sized change. Recommended if a reviewer wants broader confirmation.
- Not run: re-run of `agent-critic` — no subagent-dispatch tool available in this session (see Deviation above).

## Handoff Notes

Recommended next step: implementer to apply the two template edits and trigger regeneration, then agent-critic for re-audit.

**Status update (this implementation pass):** all four completable Todo Plan items (P0×3, P1) and all six Acceptance Criteria are complete and verified per the evidence above. Todo P2 (re-run agent-critic) remains outstanding — no subagent-dispatch tool was available in this session. This plan is **not archived**; per its own completion instruction and the general archive rule, archival requires every Todo item checked, and P2 is not. The next session with agent-critic dispatch access should re-run it against `.claude/agents/agent-creator-semantic-verifier.md`, confirm all 6 original findings closed, check off P2, and then perform the Archive On Completion steps (write the full completed plan to `docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-7-agent-creator-semantic-verifier-render-sync.md` and replace this file with a one-line tombstone).
