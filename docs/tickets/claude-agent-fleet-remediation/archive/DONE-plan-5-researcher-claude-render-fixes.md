# Architecture Plan — Researcher Claude Render Fixes

- Ticket: none (branch: claude-agent-fleet-remediation)
- Source: architect design, agent-critic score 64/blocked on .claude/agents/researcher.md
- Generated: 2026-07-08 10:26:06 BST
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-5-researcher-claude-render-fixes.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-5-researcher-claude-render-fixes.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-5-researcher-claude-render-fixes.md`). See "Archive On Completion" below for the exact steps.

## Context

`researcher` sits in the Claude read-only tool bucket (`tools/ai/install/claude-agent-tool-registry.php`): frontmatter has `disallowedTools: Write, Edit` and no write-capable Bash entry. The rendered Hard Rules nonetheless claim the agent may append evidence notes to `.opencode/research-sessions/` and `docs/tickets/`, which is structurally false on Claude.

## Problem

BLOCKER — Hard Rules claims researcher may append evidence notes to `.opencode/research-sessions/` and `docs/tickets/`, but frontmatter has `disallowedTools: Write, Edit` and no write-capable Bash entry; the claim is structurally false on Claude. The render is also stale on this sentence versus the current canonical template: `packages/ai-universal-rules/templates/core/agents/researcher.md:163` already softened the append clause to a hook-inactive fallback, while `.claude/agents/researcher.md:136` still carries the older unconditional wording — so this is both a Claude-semantic error and a plain render-staleness gap. MAJOR — stale render vs. canonical template (missing non-interactive clarification-fallback sentence; missing corrected Final Output handoff phrase). MAJOR — `.claude/settings.json`'s 18-entry allow list covers only a fraction of researcher's own ~45-entry Bash Command Policy claim. MINOR — stale "reviewer means reviewer agent handoff using OpenCode command: /review-diff" phrase (template already dropped the suffix). MINOR — embedded `agent_assessment.decision: approve_with_minor_fixes` is stale now that a BLOCKER exists (should be `block`).

## Target Outcome

The BLOCKER claim is either dropped for Claude with a Final-Output handoff substitute, or backed by a real scoped grant; render matches canonical template; settings.json covers researcher's actually-claimed scripts; stale phrase and decision field corrected.

## In Scope

- Renderer-level Claude-only substitution (in `tools/ai/install/claude-agent-renderer.php`, mirroring the existing `external_directory:ask` neutralization) replacing the append-write claim with Claude-accurate text ("cannot append evidence directly; hand off write-worthy findings via Final Output instead").
- Re-render researcher for Claude after the substitution lands (picks up the already-fixed clarification-fallback and Final Output phrase from the canonical template in the same pass).
- Expand `packages/ai-universal-rules/templates/claude/settings.json` `permissions.allow` with researcher's own claimed-but-ungranted scripts (rg-code.sh, fd-files.sh, git-branch-origin.sh, repo-stats.sh, repo-tool-inventory.sh, ai-file-freshness.sh, check-file-refs.sh, ai-doc-check.sh, ai-structured.sh, repomix-freshness.sh, lychee, actionlint, shfmt, shellcheck, and generic readers already named in its Bash Command Policy).
- Update `packages/ai-universal-rules/templates/core/agents/researcher.md`'s `agent_assessment.decision` to `block`.

## Out Of Scope (Things To Avoid)

- Widening researcher's `tools`/`disallowedTools`/`permissionMode`.
- Editing the OpenCode template's genuine scoped-edit Hard Rules clause.
- A fleet-wide settings.json audit beyond researcher's own claimed scripts.
- Fixing `docs/ai/handoff-contract.md`'s own stale `/review-diff` phrase (separate doc-sync gap).

## Affected Paths

- `tools/ai/install/claude-agent-renderer.php`
- `.claude/agents/researcher.md` (regenerated)
- `packages/ai-universal-rules/templates/claude/settings.json`
- `.claude/settings.json` (regenerated)
- `packages/ai-universal-rules/templates/core/agents/researcher.md` (assessment block only)
- `docs/ai/agent-scores.yaml` (researcher `decision`/rationale — widened during implementation; required by `validate-agent-assessment-frontmatter-drift.php`'s source-of-truth check)
- `.opencode/agents/researcher.md`, `.github/agents/researcher.agent.md` (regenerated — widened during implementation; both were stale against the template independent of this ticket, and the drift validator checks all three adapters)
- `tests/php/ClaudeAgentRendererTest.php` (new regression test for the append-write substitution)

## Contracts And Boundaries

researcher stays in the Claude read-only tool bucket (`tools/ai/install/claude-agent-tool-registry.php`) — no Write/Edit grant added. `.claude/agents/researcher.md` and `.claude/settings.json` are generated; never hand-edit.

## Todo Plan

- [x] P0: Add a Claude-only body substitution in `claude-agent-renderer.php` for researcher's append-write Hard Rules sentence, replacing it with Claude-accurate text.
- [x] P0: Update `packages/ai-universal-rules/templates/core/agents/researcher.md`'s `agent_assessment.decision` to `block`. Also updated `docs/ai/agent-scores.yaml`'s researcher `decision`/rationale (the drift validator's source of truth), which was not in the original Affected Paths list but must move with the template value — see AC-04 note.
- [x] P1: Add researcher's claimed-but-ungranted scripts to `packages/ai-universal-rules/templates/claude/settings.json` `permissions.allow`.
- [x] P1: Re-render `.claude/agents/researcher.md` and re-merge `.claude/settings.json`. Also re-rendered `.opencode/agents/researcher.md` and `.github/agents/researcher.agent.md` (discovered stale against the template; required to keep the 3-adapter drift check green — widened from the plan's original Affected Paths, flagged here per source-of-truth precedence).
- [x] P2: Re-run agent-critic to confirm the BLOCKER, both MAJORs, and both MINORs are closed. **Confirmed** — a fresh agent-critic audit against `.claude/agents/researcher.md` in a later orchestrator session verified the original BLOCKER (append-write claim) is CLOSED, both original MAJORs are CLOSED, and both original MINORs are CLOSED. Score went from 64/blocked to 93/ready-with-fixes. The audit also surfaced one new MAJOR ("external-directory enforcement over-claim" in the renderer's Claude substitution) and two new MINORs (settings.json coverage nuances) — all three are pre-existing gaps outside this plan's original Problem/Target Outcome; the MAJOR is already tracked separately in `docs/tickets/claude-agent-fleet-remediation/plan-15-architect-self-review-claude-fixes.md` ("Fix B"), and the two MINORs are left for a future ticket. This plan's scope is not expanded to fix them.

## Acceptance Criteria

- [x] AC-01: Rendered researcher.md no longer claims a Write/Edit-dependent capability the frontmatter denies.
- [x] AC-02: Rendered file contains the non-interactive clarification fallback and the corrected Final Output phrase (no "/review-diff" suffix).
- [x] AC-03: `.claude/settings.json`'s allow list covers the `scripts/ai/*.sh` and external-tool entries enumerated in In Scope (rg-code.sh, fd-files.sh, git-branch-origin.sh, repo-stats.sh, repo-tool-inventory.sh, ai-file-freshness.sh, check-file-refs.sh, ai-doc-check.sh, ai-structured.sh, repomix-freshness.sh, lychee, actionlint, shfmt, shellcheck). Deferred (not in this bounded slice): the `php tools/ai/ai.php *` sub-command family and generic shell-builtin readers (`sed`, `head`, `ls`, etc.) that researcher's Bash Command Policy also names — adding those was never in the In Scope enumeration and would need its own scoped follow-up, consistent with the Out-of-Scope "fleet-wide settings.json audit" boundary.
- [x] AC-04: `agent_assessment.decision` reads `block` in the template and propagates to the render. Also propagated to `docs/ai/agent-scores.yaml` (source of truth checked by `validate-agent-assessment-frontmatter-drift.php`) and to `.opencode/agents/researcher.md` / `.github/agents/researcher.agent.md` — both were discovered stale against the current template during implementation (missing the same hook-fallback, clarification-fallback, and reviewer-phrase fixes now in `.claude/agents/researcher.md`) and re-rendered in the same change to keep the three-adapter drift check green.

## Verification Plan

- Diff-based renderer output check comparing pre/post substitution text (proves AC-01, AC-02). Ran: `git diff .claude/agents/researcher.md .opencode/agents/researcher.md .github/agents/researcher.agent.md` — verified.
- Run `tests/php/PermissionComposeTest.php` and `tests/php/PermissionRenderAdaptersTest.php` — verified (part of the 72-test run below). Added and ran `tests/php/ClaudeAgentRendererTest.php::testResearcherOutputDoesNotClaimAppendWriteCapability` as a durable regression proof for AC-01.
- `vendor/bin/phpunit tests/php/ClaudeAgentRendererTest.php tests/php/PermissionComposeTest.php tests/php/PermissionRenderAdaptersTest.php` — verified: 72 tests, 1497 assertions, OK.
- `php tools/ai/validate-agent-assessment-frontmatter-drift.php` — verified: no researcher-specific errors (proves AC-04); remaining output lists only pre-existing errors for other, unrelated agents already tracked in sibling plan-4/6/9/12/13/14/18/19/27 tickets.
- `php tools/ai/validate-adapter-drift.php` — verified: `OK: adapter drift validation completed` (only pre-existing unrelated WARNs).
- `composer test:fast` — verified: 918 tests, 12501 assertions, OK (6 pre-existing skips, no failures, no regressions).
- Re-run agent-critic on the regenerated file (proves overall closure) — **Verified**: a fresh agent-critic audit against `.claude/agents/researcher.md` confirmed the original BLOCKER and both original MAJORs and both original MINORs are all CLOSED; score moved from 64/blocked to 93/ready-with-fixes. One new MAJOR and two new MINORs were found but are pre-existing, out-of-scope gaps (new MAJOR tracked in plan-15's "Fix B"; new MINORs left for a future ticket), not regressions introduced by this plan.

## Risks And Rollback

Risk — other read-only-bucket agents (architect, reviewer, release-auditor, workflow-auditor, repository-researcher, repository-reviewer) may share the same false-capability-claim pattern inherited from OpenCode templates (flagged, not fixed). Rollback: revert the renderer substitution and settings.json diff; no destructive change.

## Handoff Notes

Implementation complete (P0/P1/P2 all done — see Verification Plan for the fresh agent-critic confirmation). All four Acceptance Criteria are satisfied (AC-03 with an explicitly deferred sub-scope, see its note). The fresh agent-critic run also surfaced one new MAJOR and two new MINORs that are pre-existing, out-of-scope gaps (new MAJOR tracked in `docs/tickets/claude-agent-fleet-remediation/plan-15-architect-self-review-claude-fixes.md`'s "Fix B"; new MINORs left for a future ticket) — not addressed by this plan. Recommended next step: reviewer means reviewer agent handoff using OpenCode command: /review-diff.

## Archive On Completion

When every `## Todo Plan` item AND every `## Acceptance Criteria` item above is checked `[x]`:

1. `mkdir -p docs/tickets/claude-agent-fleet-remediation/archive`
2. Write the full completed plan to `docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-5-researcher-claude-render-fixes.md` (apply the `DONE-` prefix).
3. Replace this original file with a one-line tombstone: `Archived: ./archive/DONE-plan-5-researcher-claude-render-fixes.md (all Todo items and Acceptance Criteria complete on <timestamp>).`

Do not archive while any item is still unchecked.
