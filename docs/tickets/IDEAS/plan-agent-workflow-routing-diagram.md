# Architecture Plan — Agent/Workflow Routing Mermaid Diagram

- Ticket: none
- Source: user request for a Mermaid diagram grouping agents by workflow usage; grounded against
  `docs/ai/architecture-diagrams.md`, `docs/ai/agents.md`, and
  `docs/tickets/claude-agent-fleet-remediation/plan-29`/`plan-30` (prior Mermaid work)
- Generated: 2026-07-09
- Plan file: docs/tickets/IDEAS/plan-agent-workflow-routing-diagram.md
- Type: `docs-sync` — additive diagram section, no code change
- Risk: low

## Context

`docs/ai/architecture-diagrams.md` (380 lines) already exists and already covers system
architecture: install pipeline, adapter render pipeline, permission source-of-truth/projection
model (Sections 4-6, added by `plan-29`/`plan-30`). It does **not** cover agent-to-workflow
routing — "which agent uses which workflow, grouped, so a user or agent can see correct usage at a
glance." That routing currently exists only as prose: `docs/ai/agents.md`'s "Which Agent To Start
With" table (18 rows) and "Common Handoffs" list (6 bullets). The architect and
architecture-plan-writer agent templates already carry Mermaid-diagram-authoring guidance
(`## Architecture Diagram (Mermaid)` / `## Architecture Diagram` sections, landed by `plan-30`) —
this plan is the first concrete use of that guidance for a routing diagram rather than a system
diagram.

## Problem

A newcomer (human or agent) deciding "which agent do I start with, and which workflow does it
run" must currently read two prose sections in `docs/ai/agents.md` and cross-reference workflow
names against `templates/workflows/*.md` by hand. No single visual groups agents by the workflow
family they belong to (planning agents, implementation agents, review/audit agents,
agent-governance agents) or shows the standard handoff chain.

## Target Outcome

A new Section 7 in `docs/ai/architecture-diagrams.md` ("Agent & Workflow Routing"), with one or two
small Mermaid diagrams (≤~20 nodes each, per the file's own existing rule) that: (a) group the 25
canonical agents into their workflow-family clusters, and (b) show the standard handoff chain
(researcher -> architect -> architecture-plan-writer -> implementer -> reviewer -> release-auditor)
as a left-to-right flow, cross-referencing `docs/ai/agents.md`'s existing prose table rather than
duplicating its "when to use" text.

## In Scope

- New Section 7 in `docs/ai/architecture-diagrams.md`, following the file's existing conventions
  (own-code-only framing adapted to "own-agent/workflow-only," ≤~20 nodes, `(planned)` markers for
  anything not yet built — e.g. the 7 still-missing workflows from `improvements-workflows.md`
  would be shown as `(planned)` nodes if included at all, or omitted entirely to keep the diagram
  honest about only what exists today).
- Diagram 7a: **Agent clusters by workflow family** — subgraph clusters for `planning`
  (`researcher`, `repository-researcher`, `architect`, `architecture-plan-writer`), `implementation`
  (`implementer`, `bugfix`, `refactorer`, `upgrade`, `build-config`, `ui-builder`, `config-maintainer`,
  `docs`), `review-and-audit` (`reviewer`, `repository-reviewer`, `release-auditor`, `workflow-auditor`,
  `infra-auditor`), and `agent-governance` (`agent-creator*` (5), `agent-critic`,
  `agent-fleet-assessor`) — each node labeled with its primary workflow where one exists (per
  `improvements-workflows.md`'s "P2 — partially covered" table), and unlabeled where none does yet.
- Diagram 7b: **Standard handoff chain** — the 6-bullet "Common Handoffs" list from
  `docs/ai/agents.md`, rendered as a directed graph (matches that doc's existing content, adds no
  new claims).
- A cross-reference sentence in `docs/ai/agents.md` pointing to the new Section 7 (mirrors how
  Section 6 in the same file is cross-referenced from Sections 4-5, per `plan-29`).
- Register the addition in the file's existing "Regenerating / Updating" maintenance note (already
  present per `plan-29`/`plan-30`; this just adds Section 7 to its scope, no new maintenance
  mechanism).

## Out Of Scope (Things To Avoid)

- Duplicating `docs/ai/agents.md`'s full "when to use" prose inside the diagram — nodes get short
  labels only, the prose stays the single source of truth for detailed guidance.
- Depicting the 7 still-missing workflows as if they exist (if shown at all, they must carry the
  same `(planned)` marker convention Sections 4-6 already use).
- Any change to Sections 1-6 of `architecture-diagrams.md` beyond the one cross-reference addition.
- Any new validator, auto-generation engine, or graphify-driven diagram rendering (matches the
  existing "no heavyweight auto-generation" boundary from `plan-29`).
- Editing the architect/architecture-plan-writer agent templates' own Mermaid-guidance sections —
  those already exist and are out of scope for this plan (this plan is a *use* of that guidance,
  not a change to it).

## Affected Paths

- `docs/ai/architecture-diagrams.md` (new Section 7)
- `docs/ai/agents.md` (one cross-reference sentence)

## Contracts And Boundaries

- Format: Mermaid `graph`/`flowchart` blocks matching the file's existing conventions; ≤~20 nodes
  per diagram; own-agent/workflow-only scope.
- Honesty rule (carried from `plan-29`): no node may claim a workflow exists that does not; use
  `(planned)` or omit entirely.
- Maintenance rule (carried from `plan-29`/`plan-30`): this doc is hand-authored-must-sync; Section
  7 must be updated in the same slice as any agent roster or workflow-file addition/removal.

## Todo Plan

- [ ] Draft diagram 7a (agent clusters by workflow family) and 7b (standard handoff chain).
- [ ] Add Section 7 to `docs/ai/architecture-diagrams.md`, following existing section numbering
      and the "Scope and Honesty Notes" pattern.
- [ ] Add the cross-reference sentence to `docs/ai/agents.md`.
- [ ] Confirm both diagrams render (Mermaid syntax check) and stay ≤~20 nodes each.

## Acceptance Criteria

- AC-01: Section 7 exists with exactly two diagrams (7a, 7b), each ≤~20 nodes.
- AC-02: Every agent named in 7a matches an entry in `docs/ai/agents.md`'s Live Agent Index (no
  invented agent names).
- AC-03: Every workflow named in 7a matches a real file in `templates/workflows/` (no invented or
  unmarked-planned workflow claims).
- AC-04: `docs/ai/agents.md` carries one new cross-reference sentence to Section 7, matching the
  existing Section-6 cross-reference pattern in `architecture-diagrams.md`.
- AC-05 (negative): Sections 1-6 of `architecture-diagrams.md` are unchanged except the one
  cross-reference edit.

## Verification Plan

- Manual/CI Mermaid syntax validation (whatever mechanism the repo already uses for the existing 6
  sections, if any exists — otherwise a manual render check).
- `ai-search.sh` diff confirms only the two named files changed.
- Cross-check every agent/workflow name in the new diagrams against `docs/ai/agents.md` and
  `templates/workflows/*.md` file listing.

## Risks And Rollback

- **Low**: additive docs-only change, same risk class as `plan-29` (already shipped without
  incident).
- **Rollback**: revert the Section 7 addition and the one cross-reference sentence.

## Handoff Notes

- This is the first concrete application of the Mermaid-diagram-authoring guidance already landed
  on the architect/architecture-plan-writer agents (`plan-30`) — reuse that guidance's conventions
  (quoted labels, ≤~20 nodes, `planned`/`unknown` markers, no invented edges) rather than
  reinventing diagram style.
- Recommended next step: `docs means docs agent handoff` (docs-sync workflow) to draft and land
  Section 7; no reviewer/release-auditor gate required (low-risk documentation, matching `plan-29`'s
  own risk classification).
