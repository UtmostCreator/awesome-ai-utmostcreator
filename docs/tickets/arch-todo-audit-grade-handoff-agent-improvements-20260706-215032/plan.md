# Architecture Plan — Audit-Grade Handoff: Agent Improvements

- Ticket: none (inferred from user instructions — see "Source Of This Plan")
- Source: user asked to research the audit-grade handoff proposal, add the minimal safe layer now, and document the rest as an agent improvement plan
- Generated: 2026-07-06T21:50:32Z
- Plan file: docs/tickets/arch-todo-audit-grade-handoff-agent-improvements-20260706-215032/plan.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan.md` and move it into `archive/` under this branch folder.

## Source Of This Plan

No ticket id was supplied and the branch is `main`, so this plan is **inferred from user instructions**. The user reviewed an "evidence-first / audit-grade" handoff proposal (a ~250-line per-agent template) and asked to: (1) add the *minimal* layer that improves handoffs without breaking validation, and (2) capture the remaining good ideas here as a bounded improvement plan for later.

## Context

Handoff shape in this repo is canonical and routed, not missing:

- `docs/ai/handoff-contract.md` — single source of truth for handoff payload. Ships literally via `tools/ai/install/packs.php` (`merge_strategy: replace`); it has **no template render source**, so editing the doc directly is correct and carries no adapter-drift risk.
- `schemas/ai/ai-handoff.schema.json` — machine-checkable shape (`task`, `scope`, `evidence`, `next_step` required).
- Agent `## Final Output` sections (implementer/refactorer/reviewer) intentionally point back to the canonical doc instead of restating a full template. These agents are **rendered adapters** from `packages/ai-universal-rules/templates/core/agents/**`; edit the template, never the rendered `.opencode/`, `.claude/`, `.github/` copies.

Repo constraints that shaped the split (`docs/ai/ai-file-standards.md`):

- Agent files hard max 320 lines (implementer already 307) — inlining a 250-line template would fail validation.
- `adapter-contract.md`: adapters must not duplicate long procedure.
- Handoff payload sections are omittable **only when explicitly N/A**.

## Problem

The current handoff contract says "include failures with reasons" but does not force: naming failing checks exactly, capturing/declaring a pre-change baseline, stating change type + risk level, or a structured behavioural contract for behaviour-preserving work. That makes handoffs strong but not fully reviewer-trustworthy ("same 4 failures" is unauditable).

## Target Outcome

Handoffs are audit-grade (named failures, baseline honesty, risk/change-type, behavioural contract, reviewer focus) **without** bloating agent files or breaking validation. The minimal always-on layer lands in the canonical doc (done in this change); the heavier, optional enrichments are staged below for a later bounded slice.

## Already Done In This Change (minimal safe layer)

- [x] Appended `## Audit-Grade Evidence` subsection to `docs/ai/handoff-contract.md` (append-only; all existing sections intact; 41 -> 58 lines, within the 140 hard max). Covers: change type, risk level, named failing checks, baseline honesty, behavioural contract, reviewer focus. Every field is explicitly N/A-able.

## Todo Plan (deferred — future bounded slice)

### P1 — Thin agent pointers (template-sourced, re-rendered)

- [ ] P1-1: In `packages/ai-universal-rules/templates/core/agents/implementer.md` `## Final Output`, add ONE line: name failing checks and whether they predate the change; if no baseline was captured, say so. Point to `docs/ai/handoff-contract.md` "Audit-Grade Evidence".
  - How this is tested: adapter-drift validator + line-budget check (must stay <= 320).
- [ ] P1-2: In `packages/ai-universal-rules/templates/core/agents/refactorer.md` `## Final Output`, add the same one-line pointer plus `old->new / public vs internal` note under its "Behavior Preservation Boundary".
  - How this is tested: adapter-drift validator + line-budget check.
- [ ] P1-3: Re-render adapters (implementer/refactorer/super-implementer) via the install pipeline; confirm `.opencode/`, `.claude/`, `.github/` copies match template. Re-apply any out-of-band `## graphify` section if a full re-render touched AGENTS.md/CLAUDE.md.
  - How this is tested: `php tools/ai/validate-adapter-drift.php --fail-on-warn`.

### P2 — Optional schema enrichment

- [ ] P2-1: Add OPTIONAL fields to `schemas/ai/ai-handoff.schema.json`: `risk_level` (enum low/medium/high), `change_type` (enum), `known_failures` (array). Keep `required` unchanged so existing handoffs still validate.
  - How this is tested: `php tools/ai/validate-schemas.php` + a fixture that omits the new fields still passes.
- [ ] P2-2: Update `docs/ai/schema-ownership.md` row for `ai-handoff.schema.json` if the shape note changes.
  - How this is tested: doc-check.

### P2 — Reviewer alignment (optional)

- [ ] P2-3: In `packages/ai-universal-rules/templates/core/agents/reviewer.md`, add a one-line expectation that the reviewer confirms named failing checks are pre-existing (uses the new baseline-honesty field).
  - How this is tested: line-budget + adapter-drift.

## Out Of Scope (Things To Avoid)

- Do NOT inline the full ~250-line template into any agent file (breaks the 320-line hard max and the "adapters stay thin" rule).
- Do NOT edit rendered agent copies under `.opencode/`, `.claude/`, `.github/` directly — edit the template source and re-render.
- Do NOT make the new handoff fields `required` in the JSON schema (would invalidate existing handoffs).
- Do NOT touch the pre-existing unrelated worktree changes (AGENTS.md, security instructions, installer plan) — they are outside this task.
- Do NOT add tables-for-everything or 10 mandatory H2 sections; keep the omittable-if-N/A rule.
- Do NOT invent new required sections beyond those listed above.

## Affected Paths

- `docs/ai/handoff-contract.md` (done)
- `packages/ai-universal-rules/templates/core/agents/{implementer,refactorer,reviewer}.md` (deferred P1/P2)
- rendered adapters under `.opencode/agents/`, `.claude/agents/`, `.github/agents/` (deferred, via re-render only)
- `schemas/ai/ai-handoff.schema.json`, `docs/ai/schema-ownership.md` (deferred P2)

## Contracts And Boundaries

- `docs/ai/handoff-contract.md` is the canonical handoff shape; `ai-handoff.schema.json` is the machine contract. New fields must be additive/optional on both.
- Agents are thin adapters over the canonical doc — routing only, no duplicated procedure.

## Recommended Execution Order

1. P1-1, P1-2 (template edits) — First safe chunk.
2. P1-3 (re-render + drift check).
3. P2-1, P2-2 (schema, optional).
4. P2-3 (reviewer alignment, optional).

First safe chunk: P1-1 (single-line implementer pointer) — smallest, lowest risk, provable by adapter-drift + line-budget checks.

## Multi-Project Split And Order

single project — N/A.

## Acceptance Criteria

- [ ] AC-01: `docs/ai/handoff-contract.md` contains an `## Audit-Grade Evidence` section covering change type, risk level, named failing checks, baseline honesty, behavioural contract, reviewer focus; all existing sections still present; file <= 140 lines. (Met by this change.)
- [ ] AC-02: (deferred) implementer + refactorer templates carry a one-line pointer to the new section; both files stay <= 320 lines; adapter-drift validator passes.
- [ ] AC-03: (deferred) any new schema fields are optional; a handoff omitting them still validates.

## Verification Plan

Run for this change (minimal layer):

```text
wc -l docs/ai/handoff-contract.md
bash scripts/ai/ai-doc-check.sh --check
```

Run for deferred slices when executed:

```text
php tools/ai/validate-adapter-drift.php --fail-on-warn
php tools/ai/validate-schemas.php
```

## Human Test Steps

1. Open `docs/ai/handoff-contract.md` and confirm the new `## Audit-Grade Evidence` section reads clearly and sits between `## Verification` and `## Risks And Assumptions`.
2. Confirm no earlier section was removed or reworded.

## Risks And Rollback

- Risk: low. The change is append-only to a doc with no render source and no content-parity validator.
- Rollback: revert the single edit to `docs/ai/handoff-contract.md`; delete this ticket folder.

## Handoff Notes

- Minimal layer is complete and self-contained. Deferred P1/P2 items are optional enrichments; each is independently shippable as its own bounded slice.
- Recommended next step: reviewer — reviewer means reviewer agent handoff using OpenCode command: /review-diff.
