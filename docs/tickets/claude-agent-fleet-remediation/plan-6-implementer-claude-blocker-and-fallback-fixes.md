# Architecture Plan — Implementer Claude Blocker And Fallback Fixes

- Ticket: none (branch: claude-agent-fleet-remediation)
- Source: architect design, agent-critic score 75/blocked (forced by BLOCKER) on .claude/agents/implementer.md
- Generated: 2026-07-08 10:26:06 BST
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-6-implementer-claude-blocker-and-fallback-fixes.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-6-implementer-claude-blocker-and-fallback-fixes.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-6-implementer-claude-blocker-and-fallback-fixes.md`). See "Archive On Completion" below for the exact steps.

## Context

`.claude/agents/implementer.md` has an Edit/Write grant with no enforced deny for `.git/**`, `vendor/**`, `node_modules/**`, `dist/**`, `build/**`, `coverage/**`, `.cache/**` in `.claude/settings.json`, while its OpenCode sibling denies all of these. This concretely permits writing into `.git/hooks/*` (code execution on next commit). This plan addresses the template-level prose fixes only; the settings.json enforcement fix is explicitly out of scope and tracked separately.

## Problem

BLOCKER — Edit/Write grant has no enforced deny for `.git/**`, `vendor/**`, `node_modules/**`, `dist/**`, `build/**`, `coverage/**`, `.cache/**` in `.claude/settings.json` (OpenCode sibling denies all of these); concretely permits writing into `.git/hooks/*` (code execution on next commit). MAJOR — `docs/ai/AI-GUARDRAILS.md` missing from Canonical References (peer refactorer.md has it). MAJOR — missing the non-interactive clarification fallback that architect.md/reviewer.md carry. MINOR — filler word "relevant" used twice with no added meaning.

## Target Outcome

Canonical References cites `AI-GUARDRAILS.md`; Instruction Specificity has the non-interactive fallback; filler word removed; a Hard Rules bullet restates the template's own permission.edit deny list as Claude-side advisory prose; the BLOCKER's real fix (settings.json) is explicitly flagged as a separate, shared, config-maintainer/workflow-auditor-owned ticket, not duplicated here.

## In Scope

- Edit `packages/ai-universal-rules/templates/core/agents/implementer.md`:
  1. add `docs/ai/AI-GUARDRAILS.md` to the Canonical References line, also fixing the "relevant" filler word on that line;
  2. append the non-interactive fallback sentence to Instruction Specificity;
  3. fix the second "relevant" instance in Capability Routing intro;
  4. add one Hard Rules bullet listing the 7 denied paths as an advisory guard (partial BLOCKER mitigation only, not closure).

## Out Of Scope (Things To Avoid)

- Editing `.claude/settings.json` (fleet-wide, shared file — belongs in a separate ticket owned by config-maintainer, coordinated by workflow-auditor, covering implementer, bugfix, docs, refactorer, build-config, upgrade, config-maintainer, agent-creator, architecture-plan-writer, post-install).
- Editing `tools/ai/install/claude-agent-renderer.php`.
- Touching `agent_assessment` fields.

## Affected Paths

- `packages/ai-universal-rules/templates/core/agents/implementer.md` (canonical source only — propagates to `.claude/agents/implementer.md`, `.opencode/agents/implementer.md`, `.github/agents/implementer.agent.md` on next regeneration)

## Contracts And Boundaries

Edit only the canonical template; never hand-edit the three generated copies.

## Todo Plan

- [x] P0: Add `docs/ai/AI-GUARDRAILS.md` to the Canonical References doc list and simultaneously fix the "relevant" filler wording on that same line (one combined edit).
- [x] P0: Append the non-interactive clarification fallback sentence to the Instruction Specificity section, modeled on architect.md.
- [x] P0: Add the advisory Hard Rules bullet listing the 7 denied paths (`.git/**`, `vendor/**`, `node_modules/**`, `dist/**`, `build/**`, `coverage/**`, `.cache/**`) as a partial BLOCKER mitigation, sourced only from paths the template's own permission.edit map already declares.
- [x] P1: Fix the second "relevant" filler instance in the Capability Routing intro sentence.
- [ ] P2: Regenerate the three rendered copies and diff-confirm only these four changes landed. **Not done** — `.claude/agents/implementer.md`, `.opencode/agents/implementer.md`, and `.github/agents/implementer.agent.md` already carry large unrelated uncommitted edits from other in-progress tickets (confirmed via `git diff --stat` on all three before touching anything), and `php tools/ai/render-adapters.php` has no per-agent scope — a `--write` run would also silently rewrite `.github/agents/agent-creator-static-validator.agent.md` (unrelated pre-existing drift) and would overwrite the other tickets' uncommitted `implementer` edits rather than layer cleanly on top of them. Regenerating safely requires either a per-agent-scoped render flag or committing/isolating the other tickets' work first — out of reach for this bounded slice per the explicit "leave unrelated changes alone" constraint. See Implementation Notes.

## Acceptance Criteria

- [x] AC-01: Canonical References names `docs/ai/AI-GUARDRAILS.md`. (In the canonical template — the only Affected Path; verified `docs/ai/AI-GUARDRAILS.md` exists in the repo.)
- [x] AC-02: Instruction Specificity contains a non-interactive fallback sentence matching the architect.md/reviewer.md pattern.
- [x] AC-03: Neither instance of "relevant" remains as a vague filler word. Verified: `rg -n "relevant" packages/ai-universal-rules/templates/core/agents/implementer.md` returns no matches.
- [x] AC-04: A new Hard Rules bullet names exactly the 7 paths already in the template's own permission.edit deny map — no more, no fewer.
- [x] AC-05 (negative): This plan's implementation must NOT be reported as closing the BLOCKER — readiness stays blocked until the separate settings.json fix lands. Confirmed in this handoff's report and in Implementation Notes below; the advisory Hard Rules bullet is documented as a partial mitigation only.

## Verification Plan

- `git diff` on the four edits — confirm scope matches exactly the four Todo Plan items (proves AC-01 through AC-04). **Ran**: `git diff -- packages/ai-universal-rules/templates/core/agents/implementer.md` — confirmed exactly 4 hunks, each matching one Todo Plan item.
- Regenerate and diff the three rendered copies for equivalence (proves propagation without unrelated drift). **Not run** — see P2 note above; blocked by pre-existing unrelated dirty state in all three rendered copies plus a renderer tool with no per-agent scoping.
- Re-run agent-critic and confirm the BLOCKER is still reported (proving this plan didn't falsely close it) while the two MAJORs and MINOR are resolved (proves AC-05). **Not run** — no agent-critic dispatch tool is available in this session's tool set (no Task/subagent-dispatch capability); this is an environment limitation, not a skipped check. Recommended for the next session that has agent-critic dispatch available.

## Risks And Rollback

Risk — a reader could mistake the advisory Hard Rules bullet for a real fix; the plan file and PR description must state explicitly the BLOCKER remains open pending the shared settings.json ticket. Rollback: revert the four template edits.

## Implementation Notes

All four In-Scope template edits landed exactly as specified, in a single combined edit each per the Todo Plan wording:

1. Canonical References (line 268 pre-edit): `Load only relevant project docs: ...` → `Load only what this slice touches: ..., \`docs/ai/AI-GUARDRAILS.md\`, ...` — added the doc reference and replaced the filler "relevant" in the same edit.
2. Instruction Specificity: appended `Where the runtime cannot present interactive questions, state each assumption inline, mark it \`unknown\`, and stop on high-impact ambiguity instead of guessing.` — this is the exact fallback-sentence shape used by `architect.md`'s own Instruction Specificity section (`packages/ai-universal-rules/templates/core/agents/architect.md:189`), adapted to implementer's own scoring scale (target/outcome/scope/contract/verification/risk, 90/70/50 thresholds) rather than copied verbatim, since architect's clarity axes and thresholds differ.
3. Capability Routing intro: `Load relevant capabilities only: ...` → `Load capabilities scoped to this slice: ...` — second filler instance fixed.
4. Hard Rules: added `- Never write to \`.git/**\`, \`vendor/**\`, \`node_modules/**\`, \`dist/**\`, \`build/**\`, \`coverage/**\`, or \`.cache/**\`; these paths are already denied by this agent's own \`permission.edit\` map.` — the 7 paths were copied verbatim from the plan's own Problem statement, and cross-checked against the template's `permission.edit` deny block (lines 33-39 of the template), which also denies additional paths (`docs/ai/generated/**`, `*.lock`, `*.pem`, `.env*`, etc.) that AC-04 explicitly excludes ("no more, no fewer" than the 7 named).

**Deviation — P2 (regenerate the three rendered copies) not performed.** Before making any edit, `git status --short` confirmed the working tree already had large unrelated uncommitted changes across dozens of files, including all three of this plan's would-be regeneration targets: `.claude/agents/implementer.md`, `.opencode/agents/implementer.md`, `.github/agents/implementer.agent.md` (plus `.claude/settings.json` and 20+ other `.claude`/`.github`/`.opencode` agent files). `git diff` on each showed unrelated in-flight edits (e.g. `.claude/agents/implementer.md`'s Bash Command Policy paragraph reworded; `.github/agents/implementer.agent.md`'s `agent_assessment.decision` and External Boundary Rule wording already changed; `.opencode/agents/implementer.md`'s `decision` field changed) that belong to other in-progress tickets in this same `claude-agent-fleet-remediation` branch folder, not to this plan. The only regeneration tool found, `php tools/ai/render-adapters.php --write`, has no per-agent scoping — running it would have (a) overwritten those other tickets' uncommitted `implementer` edits instead of layering cleanly on top of them, and (b) also silently rewritten `.github/agents/agent-creator-static-validator.agent.md`, an unrelated file with pre-existing drift. Per the explicit constraint to leave unrelated uncommitted work alone and touch only this plan's Affected Paths (which lists only the canonical template), P2 was left undone rather than risk clobbering other tickets' work. `php tools/ai/render-adapters.php --check` was run read-only to confirm the *expected* drift shape post-edit: it reports exactly `.claude/agents/implementer.md` and `.github/agents/implementer.agent.md` as drifted (plus the pre-existing unrelated `agent-creator-static-validator.agent.md` drift), with no new/unexpected entries — consistent with only the template having changed.

**Deviation — agent-critic re-run not performed.** This session's tool set has no Task/subagent-dispatch capability, so `agent-critic` could not be invoked directly. Recommended as the next verification step once that capability is available.

### Verification Evidence

- `git diff -- packages/ai-universal-rules/templates/core/agents/implementer.md` — 4 hunks, one per Todo Plan item; no unrelated lines touched. Verified.
- `rg -n "relevant" packages/ai-universal-rules/templates/core/agents/implementer.md` — no matches (exit 1). Verified — proves AC-03.
- `test -f docs/ai/AI-GUARDRAILS.md` — exists. Verified — proves the AC-01 reference resolves to a real file.
- `vendor/bin/phpunit tests/php/ClaudeAgentRendererTest.php tests/php/CopilotAgentRendererTest.php tests/php/AgentPermissionPolicyTest.php` — 183 tests, 764 assertions, OK (5 pre-existing skips, no failures). Verified — these tests read and render the live `implementer.md` template (Claude/Copilot renderer output, permission-policy ask-entry coverage), so a template-level regression would have failed here.
- `php tools/ai/render-adapters.php --check` (read-only) — confirms drift is limited to the two expected not-yet-regenerated files plus one pre-existing unrelated file; no new/unexpected drift introduced by this edit. Not run in `--write` mode (see Deviation above).
- Not run: `composer test:fast` (full suite) — the change is markdown-only prose in one agent template already covered by the three targeted renderer/policy test files above; a full-suite run was judged unnecessary for this bounded slice and risks colliding with the unrelated dirty tree's own uncommitted state during any test that shells out to `git`. Recommended if a reviewer wants broader confirmation.
- Not run: re-run of `agent-critic` on the rendered `.claude/agents/implementer.md` — no subagent-dispatch tool available in this session (see Deviation above).

## Handoff Notes

Recommended next step: implementer to apply the four template edits and regenerate; separately, flag the settings.json BLOCKER fix as its own shared ticket for config-maintainer, coordinated by workflow-auditor, covering all 10 write-capable Claude agents at once.

**Status update (this implementation pass):** the four template edits (P0×3, P1) are complete and verified per the evidence above. P2 (regenerate the three rendered copies) and the agent-critic re-run remain outstanding — see Implementation Notes for why. This plan is **not archived**; it stays open until a follow-up pass with (a) a clean/isolated tree for the three rendered `implementer` files or a per-agent-scoped render option, and (b) agent-critic dispatch access, completes P2 and the final re-audit. The BLOCKER (`settings.json` enforcement) remains explicitly open regardless, per AC-05 — this pass did not touch `.claude/settings.json`.
