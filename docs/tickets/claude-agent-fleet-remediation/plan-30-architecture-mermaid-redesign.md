# Architecture Plan — Architecture Mermaid Redesign: Implementation Handoff

- Ticket: none
- Source: implementation handoff executing plan-29-architecture-diagrams-permission-sot-honesty.md, plus recording of already-landed agent-guidance Mermaid work
- Generated: 2026-07-08T12:30:55Z
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-30-architecture-mermaid-redesign.md
- Type: implementation handoff executing plan-29 (NOT a re-plan; does not re-derive or contradict plan-29's design)
- Risk: low (docs-sync)
- Implementer/owner: implementer (docs-scoped)
- Architecture source of truth (TARGET): plan-28-permission-sot-and-render-parity-sync.md — locked: single composed model, NO second registry, `command-policy.tiers.yaml` stays a deliberately separate/deferred surface
- Baseline commit (working tree as of): 5e1f3f17

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-30-architecture-mermaid-redesign.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-30-architecture-mermaid-redesign.md`). See "Archive On Completion" in the agent policy for the exact write-then-tombstone steps (no shell `mv`/`rm`).

## Context

Two related Mermaid workstreams exist on this branch:

- (a) **Already landed** (verified via `git diff`, not yet committed): Mermaid-diagram guidance was added to the architect and architecture-plan-writer agents across all rendered surfaces:
  - `.opencode/agents/architect.md` (+11 lines: new `## Architecture Diagram (Mermaid)` section plus an HTML-comment reminder in the plan-tickets output template's `## Proposed Design` section)
  - `.opencode/agents/architecture-plan-writer.md` (+4 lines: new `## Architecture Diagram` section)
  - `.github/agents/architect.agent.md` (mirrored, +11 lines)
  - `.github/agents/architecture-plan-writer.agent.md` (mirrored, +4 lines)
  - `.claude/agents/architect.md` (mirrored, +11 lines)
  - `.claude/agents/architecture-plan-writer.md` (mirrored, +4 lines)
  - `packages/ai-universal-rules/templates/core/agents/architect.md` (template source, +11 lines)
  - `packages/ai-universal-rules/templates/core/agents/architecture-plan-writer.md` (template source, +4 lines)

  That is 8 files total: 4 carrying the architect's +11-line `## Architecture Diagram (Mermaid)` section (fenced ` ```mermaid ` blocks, quoted labels, ≈≤20 nodes/diagram, own-code only, `planned`/`unknown` markers, no invented edges), and 4 carrying the plan-writer's +4-line `## Architecture Diagram` section (same conventions, carry-over-from-architect rule, "No diagram — single-file change" escape hatch). This work is DONE — content confirmed present via direct `git diff` inspection — and is recorded here as completed, not re-implemented.
- (b) **Pending**: `docs/ai/architecture-diagrams.md` (208 lines, 5 Mermaid diagrams) still needs the corrections scoped by plan-29 — Section 4 (Adapter Render Pipeline) is idealized/misleading about the permission enforcement surface, Section 5 (Validation & Tool-Use Hooks) omits the enforcement layer, and no Section 6 (Current vs. Target permission projection model) exists yet. Plan-29 is fully written (119 lines) but zero of its Todo Plan items are checked.

## Problem

- Plan-29 is a complete, unexecuted plan: none of its P0-P2 Todo Plan items or AC-01..AC-09 acceptance criteria are checked.
- `docs/ai/architecture-diagrams.md` still shows the idealized/stale permission pipeline: Section 4 misleadingly implies one coherent permission source reaching all adapters (reality: three unlinked enforcement surfaces per plan-28), and Section 5 omits the enforcement layer (`.claude/settings.json` floor, `command-policy.tiers.yaml` compiled hook, `validate-ai-surface.yml` CI gate) entirely.
- No single implementation-tracking plan currently ties the already-landed agent-guidance Mermaid work to the still-pending diagram-doc execution, so there is a risk the landed work gets silently redone or the pending work gets silently dropped.

## Target Outcome

One implementation-ready plan (this file) that:

1. Records the landed agent-guidance Mermaid work as done, with its verification evidence (the `git diff` excerpts above), so it is not re-implemented.
2. Drives plan-29's diagram corrections through to completion, with the actual checking-off happening in plan-29 itself (this plan tracks and hands off the execution; plan-29 remains the source of the diagram-doc Todo/AC content).

## In Scope

- Recording the landed agent-guidance work (8 files, Mermaid-diagram sections in architect and architecture-plan-writer agents/templates) as done — no re-implementation.
- Executing plan-29's Todo Plan P0-P2 items verbatim:
  - Section 4 correction (separate `.claude/settings.json` + `claude-settings-merge.php` node; split/annotate drift coverage; Section 6 cross-reference).
  - New Section 6 with diagrams 6a (Current) and 6b (Target).
  - Section 5 enforcement-layer addition (settings floor, sh-hook third list, `validate-ai-surface.yml` CI node).
  - "Scope and Honesty Notes" / "Regenerating / Updating" updates.
  - `docs/ai/source-of-truth.md` registration as hand-authored-must-sync.
  - The one forward file-existence PHP check.
  - The "planned -> current" flip follow-up documentation note.

## Out Of Scope (Things To Avoid)

- Any second permission/tool registry or second agent-to-profile map (plan-28's locked prohibition).
- Folding `command-policy.tiers.yaml` into a single-source picture (plan-28 keeps it separate; unification deferred).
- Heavyweight Mermaid auto-generation (no auto-rendered diagrams from graphify or any other engine).
- Editing `docs/ai/architecture-diagrams.md` Sections 1-3 beyond a cross-reference to the new Section 6.
- Re-implementing the already-landed 8-file agent-guidance change (record only — do not touch these files again in this plan's execution).
- Contradicting plan-28's target model (single composed model, N projections, no second registry).

## Affected Paths

- `docs/ai/architecture-diagrams.md` (primary — Section 4 corrections, Section 5 additions, new Section 6, honesty-notes/regenerating updates)
- `docs/ai/source-of-truth.md` (shared with plan-28 AC-08 — coordinate to avoid a merge collision)
- At most one small PHP test-harness surface for the forward file-existence check
- Reference-only, already-landed, NOT re-touched by this plan's execution: `.opencode/agents/architect.md`, `.opencode/agents/architecture-plan-writer.md`, `.github/agents/architect.agent.md`, `.github/agents/architecture-plan-writer.agent.md`, `.claude/agents/architect.md`, `.claude/agents/architecture-plan-writer.md`, `packages/ai-universal-rules/templates/core/agents/architect.md`, `packages/ai-universal-rules/templates/core/agents/architecture-plan-writer.md`

## Contracts And Boundaries

(Carried forward verbatim from plan-29.)

- Format: Mermaid `graph` blocks matching the file's existing conventions; each diagram <=~20 nodes (file rule L17); own-code only, excluded paths per L16.
- Architecture (from plan-28, must not be contradicted): single composed model `aiPermissionComposeFromSpec` over `aiInstallerAgentProfiles`; N projections descend from it; NO second registry (locked); `command-policy.tiers.yaml` stays separate.
- Source-of-truth boundary: plan-28 is authoritative for the TARGET architecture; the CURRENT-state diagrams must match confirmed file reality (three lists present; `render-adapters.php`/`generate-claude-settings.php` absent).
- Docs-sync boundary: reuse `source-of-truth.md`'s existing generated-vs-hand-authored pattern; do not invent a new classification scheme.
- Sequencing boundary: plan-29 lands independently NOW; a documented follow-up flips "planned" markers to "current" (and activates the file-existence check's target-path exemptions) when plan-28 phases merge.

## Todo Plan

- [x] P0: Confirm the landed architect/architecture-plan-writer Mermaid guidance is committed and unmodified across all 8 files (`.opencode/agents/architect.md`, `.opencode/agents/architecture-plan-writer.md`, `.github/agents/architect.agent.md`, `.github/agents/architecture-plan-writer.agent.md`, `.claude/agents/architect.md`, `.claude/agents/architecture-plan-writer.md`, `packages/ai-universal-rules/templates/core/agents/architect.md`, `packages/ai-universal-rules/templates/core/agents/architecture-plan-writer.md`). Confirmed via `ai-search.sh changed-text "Architecture Diagram"` (12 matches across the 8 files) and `git diff --stat`; not re-touched by this slice.
- [x] P0: Correct Section 4 — add the separate `.claude/settings.json` + `claude-settings-merge.php` hand-maintained input (breaking the single-seam illusion), split/annotate drift coverage to show the `.claude`/`.github` byte-parity blind spot, and add the Section 6 cross-reference.
- [x] P0: Add NEW Section 6 with diagram 6a (Current — three unlinked clusters, no (1)-(2) edge, parity red-flag).
- [x] P0: Add diagram 6b (Target — one composed model -> N projections incl. generated settings floor + byte-parity gate + capability filter; every planned-only node marked "planned — see plan-28"; no second-registry node; command-policy.tiers.yaml separate/deferred).
- [x] P1: Add the missing enforcement layer to Section 5 (settings floor, sh-hook third list, `validate-ai-surface.yml` CI node), pushing detail into Section 6 if the <=20-node rule would break.
- [x] P1: Update "Scope and Honesty Notes" and "Regenerating / Updating" to cover the new content and state the hand-authored-must-sync maintenance rule.
- [x] P1: Register `architecture-diagrams.md` in `docs/ai/source-of-truth.md` as hand-authored-must-sync (coordinate with plan-28 AC-08's edit to the same file). Registered as a local (non-shipped) doc name to satisfy `validate-install-surface.php` Direction B; see plan-29 P1 note.
- [x] P2: Add the single small forward file-existence check (own-code paths in the diagram doc resolve; target-only paths exempted until plan-28 lands), reusing the existing PHP test harness (`tests/php/ArchitectureDiagramReferencesTest.php`).
- [x] P2: Document the "planned -> current" flip follow-up tied to plan-28 phase completion.

## Acceptance Criteria

(AC-01..AC-09 carried forward verbatim from plan-29; AC-10 new for this plan.)

- [x] AC-01: Section 4 renders a `.claude/settings.json` node fed by `claude-settings-merge.php` as a SEPARATE input to the Claude output (not from the `perm`/render-adapters seam), visibly labeled a known gap.
- [x] AC-02: Section 4's drift coverage visibly distinguishes the OpenCode+templates `--check` path from the un-gated `.claude`/`.github` bodies (a "no byte-parity gate (pre-plan-28)" annotation is present).
- [x] AC-03: New Section 6 exists with 6a (Current) showing three clusters and NO edge between the composed-body cluster and the settings-floor cluster, plus a parity red-flag annotation.
- [x] AC-04: Section 6b (Target) shows one composed model fanning to N projections including the generated settings floor and the byte-parity gate, with NO second-registry node and `command-policy.tiers.yaml` shown as a separate deferred node.
- [x] AC-05: Every target-only module named in any diagram (`render-adapters.php`, `generate-claude-settings.php`, generated settings floor, capability filter) carries a "planned" marker; grep of the doc finds no unlabeled current claim for these.
- [x] AC-06: Section 5 shows the three enforcement surfaces (or, if node-limited, at least the `validate-ai-surface.yml` CI node + a Section-6 cross-reference); no diagram exceeds ~20 nodes.
- [x] AC-07: "Scope and Honesty Notes" and "Regenerating / Updating" state the doc is hand-authored, mirrors architecture as of a named commit, and must be updated in the same slice as any render/permission-pipeline change; `architecture-diagrams.md` appears in `source-of-truth.md`'s classification.
- [x] AC-08: Exactly one small consistency mechanism is added (forward file-existence assertion; fallback caveat) — no auto-generation engine; the check passes with target paths exempted.
- [x] AC-09 (negative): No diagram depicts a second registry or a second agent->profile map; sections 1-3 content is unchanged except a Section-6 cross-reference.
- [x] AC-10: The landed agent-guidance Mermaid sections remain present and unmodified in all 8 files (`.opencode`/`.github`/`.claude` agents for architect and architecture-plan-writer, plus their 2 template sources) and `composer test:fast` (923/923 pass) plus the adapter-drift/permission/surface validators still pass (`markdownlint-cli2` not installed in this environment — noted, not run).

## Verification Plan

- AC-01..AC-04, AC-06, AC-09: inspect the rendered doc for the specified nodes/edges/annotations and absence of the second-registry node; confirm each Mermaid block <=~20 nodes.
- AC-05: grep the doc for each target-only path and confirm a "planned" marker adjacent to every occurrence.
- AC-07: read the two updated sections and the `source-of-truth.md` row.
- AC-08: confirm exactly one check added; run it and confirm it passes with `render-adapters.php`/`generate-claude-settings.php` exempted; confirm no diagram auto-generation was introduced.
- AC-10: `git diff` the 8 already-landed files and confirm the Mermaid-guidance sections are unchanged from the excerpts recorded in this plan's Context; run `composer test:fast`; run `php tools/ai/validate-adapter-drift.php`, `php tools/ai/generate-agent-permissions.php --check`, the surface validator (`validate-ai-surface` per `.github/workflows/validate-ai-surface.yml`), and the line-budget validator per `docs/ai/ai-file-standards.md`.
- Overall: `git diff --stat` shows only `docs/ai/architecture-diagrams.md`, `docs/ai/source-of-truth.md`, and at most one test file changed by this plan's own execution (the 8 agent-guidance files are pre-existing working-tree state, not new changes from this plan).

## Risks And Rollback

(R1-R4 carried forward verbatim from plan-29.)

- R1 (low): Section 5 may exceed the <=20-node rule if full three-list detail is added there; mitigation — push list detail into Section 6, keep Section 5 minimal (CI gate node + cross-reference).
- R2 (low, coordination): plan-28 AC-08 also edits `docs/ai/source-of-truth.md`; plan-29's registration touches the same file — flag so whichever lands second rebases cleanly (different rows/sections, not a blocker).
- R3 (low): the "planned" markers in 6b go stale once plan-28 merges; mitigation — this plan documents the follow-up flip tied to plan-28 completion.
- R4 (unknown, low): cheapest implementation of the file-existence check (PHPUnit vs shell) is an implementer decision; the design fixes the requirement (forward reference existence, target paths exempted), not the mechanism; `check-file-refs.sh` confirmed unsuitable (reverse orphan finder).
- Rollback: reverting the docs slice (no runtime/permission impact).

## Handoff Notes

- Plan-28 remains TARGET source of truth for the permission architecture; do not contradict its locked "no second registry" or "keep `command-policy.tiers.yaml` separate" decisions.
- The landed agent-guidance work (8 files) needs no re-implementation, only confirmation (P0 item above).
- The implementer executes plan-29's diagram corrections next; plan-29 itself remains the canonical source for the Section 4/5/6 Todo Plan and AC wording — this plan hands off execution and adds the AC-10 recording/regression check, it does not redefine plan-29's design.
- Recommended next step: implementer to execute the plan-29 diagram corrections (Section 4/5/6, source-of-truth.md registration, file-existence check) tracked against this plan-30 file.
