# Architecture Plan — Backlog & Deferred Program Index

- Ticket: none
- Source: decomposition of `docs/tickets/arch-todo-repo-cleanup-shipping-surface-20260614-101701/plan.md`, with binding user decisions
- Generated: 20260614-104819
- Plan folder: docs/tickets/arch-todo-backlog-deferred-program-20260614-104819/
- Status: Todo (unchecked) — backlog INDEX; tracks deferred/dropped items, not an implementation slice
- Decomposition role: backlog index (Plan G)
- Dependency: informational; each backlogged item needs its own architect pass when promoted

## Context

The mega-plan `arch-todo-repo-cleanup-shipping-surface-20260614-101701/plan.md` is decomposed into bounded plans A–F. This index records the remaining BACKLOGGED items (each requires its own architect pass before implementation) and the explicitly DROPPED items (do not implement). It is the single place to look up what was deferred, dropped, and where the active work lives.

## Problem

Without a single index, the decomposed program loses track of which items were deferred vs. dropped, their risk, and their gating constraints — creating a risk that dropped work is re-attempted or that high-risk backlog items are picked up without their own architect pass.

## Target Outcome

A durable index that (1) maps every active plan, (2) lists each backlogged item with risk and gating, (3) lists each dropped item with the reason it was dropped, and (4) directs implementers to open a dedicated architect pass before promoting any backlog item.

## Active Plans (decomposition of the mega-plan)

- Plan A — `arch-todo-land-scripts-ai-reorg-20260614-104813/plan.md` — rank 00, BLOCKING baseline.
- Plan B — `arch-todo-shipping-surface-20260614-104814/plan.md` — items 05/10/15/20/25.
- Plan C — `arch-todo-doc-surface-hardening-20260614-104815/plan.md` — items 45/40/75/80/85/30.
- Plan D — `arch-todo-agent-score-frontmatter-20260614-104816/plan.md` — item 35.
- Plan E — `arch-todo-ai-search-shortcut-modes-20260614-104817/plan.md` — item 100 (DEFERRED).
- Plan F — `arch-todo-ai-search-external-access-20260614-104818/plan.md` — item 105 (DEFERRED, security-gated).

## Already Done (verify only — NO plan created)

- Items 50/55/60/65 — ALREADY DONE in the working tree (covered by `arch-todo-remaining-rework-20260613-231930` Phases 5 & 7 + `arch-todo-restructure-scripts-ai-p3-p5-20260613-220424`). Plan A verifies they are green; do not re-implement.

## Backlogged Items (each needs its own architect pass when promoted)

- [ ] 90: Agent lifecycle groups + `NN-` renames + `.github/ai-agent-lifecycle.yaml` — **HIGH** risk (renames + new lifecycle config; reference-integrity heavy). Needs dedicated architect pass.
- [ ] 95: Agent output contracts + `.github/agent-permissions/` templates — **HIGH** risk; gated on the open question: inline-only vs generated YAML. Resolve OQ in the architect pass before implementing.
- [ ] 110: `DOGFOODING.md` — add dogfooding guide. Standard risk; standalone doc.
- [ ] 115: `compatibility.md` + CI matrix — compatibility doc plus CI matrix wiring. Medium risk (CI surface).
- [ ] 120: Rename `advisor/secret-scan.php` -> `secret_scan_lib.php` — reference-integrity change; update all call sites in the same change. Medium risk.
- [ ] 125: Context economy + release trust (SBOM / `composer audit` / PHAR) — release-trust tooling. Medium/high risk; pairs with release-safety.

## Dropped Items (DO NOT implement)

- 130: docs/ai sectional reorg — DROPPED. Highest blast radius, lowest payoff. Do not create a plan; do not implement.
- 70: `repo-tool-inventory.sh --security` + `validate-machine-output.sh` — DROPPED. Low value. Do not create a plan; do not implement.

## Out Of Scope (Things To Avoid)

- Implementing any backlogged item directly from this index without its own architect pass.
- Re-attempting either DROPPED item (130, 70).
- Re-implementing already-done items 50/55/60/65 (verify-only via Plan A).
- Promoting HIGH-risk items (90, 95, 125) without resolving their gating questions and a release-safety review where applicable.

## Acceptance Criteria

- [ ] AC-01: This index lists all active plans A–F with their ranks/items.
- [ ] AC-02: This index lists every backlogged item (90, 95, 110, 115, 120, 125) with risk and gating, each marked as needing its own architect pass.
- [ ] AC-03: This index lists both DROPPED items (130, 70) with the reason and a do-not-implement note.
- [ ] AC-04: This index records that items 50/55/60/65 are already done and verify-only.
- [ ] AC-05: The mega-plan carries a SUPERSEDED banner pointing to Plans A–D and this backlog index.

## Verification Plan

- `bash scripts/ai/preview-file.sh docs/tickets/arch-todo-backlog-deferred-program-20260614-104819/plan.md` — confirms the index content (AC-01..AC-04).
- `bash scripts/ai/preview-file.sh docs/tickets/arch-todo-repo-cleanup-shipping-surface-20260614-101701/plan.md` — confirms the SUPERSEDED banner is present and points to A–D + this index (AC-05).
- `AI_OUTPUT=json bash scripts/ai/ai-search.sh files "arch-todo" docs/tickets` — confirms all decomposed plan folders exist.

## Risks And Rollback

- Risk: a backlog item is implemented without its own architect pass. Mitigation: each item is explicitly marked "needs its own architect pass"; out-of-scope section forbids direct implementation.
- Risk: a DROPPED item is re-attempted. Mitigation: explicit DROPPED list with reasons.
- Rollback: this is an index doc; revert the file to undo.

## Handoff Notes

- Promote a backlog item only by opening a dedicated architect pass for it; do not implement from this index.
- DROPPED items (130, 70) are closed — do not reopen without a new explicit decision.
- The SUPERSEDED banner on the mega-plan is added alongside this index (see that file).
- implementer means implementer agent handoff using OpenCode command: /implement
