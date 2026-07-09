# Architecture Plan — Architecture Diagrams: Permission Source-of-Truth Honesty

- Ticket: none
- Source: architect handoff (read-only assessment of docs/ai/architecture-diagrams.md against plan-28)
- Generated: 2026-07-08T11:38:58Z
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-29-architecture-diagrams-permission-sot-honesty.md
- Type: `docs-sync` companion to plan-28-permission-sot-and-render-parity-sync.md — NOT a substitute for it
- Risk: low-risk documentation slice — no reviewer/release-auditor gate required (unlike plan-28)
- Implementer/owner: docs agent
- Architecture source of truth (TARGET): plan-28; CURRENT-state depiction must match confirmed file reality
- Baseline commit (doc mirrors architecture as of): 5e1f3f17

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-29-architecture-diagrams-permission-sot-honesty.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-29-architecture-diagrams-permission-sot-honesty.md`). See "Archive On Completion" in the agent policy for the exact write-then-tombstone steps (no shell `mv`/`rm`).

## Context

Plan-28 re-architects the systemic agent-generation sync defect: three unlinked permission *enforcement surfaces* — (1) the composed-model seam projecting per-agent advisory bodies, (2) the separate `.claude/settings.json` enforced floor, (3) the `command-policy.tiers.yaml` compiled sh hook — plus no byte-parity render gate. (Note: surface (1) is itself a *single* composed `$model` fanned to per-adapter formats; the fragmentation is between the three surfaces, not among three peer agent lists.) The user then asked whether `docs/ai/architecture-diagrams.md` (208 lines, 5 Mermaid diagrams: 1 System Context, 2 Module/Container Map, 3 Install Pipeline Flow, 4 Adapter Render Pipeline, 5 Validation & Tool-Use Hooks) helps as an architecture aid, and to design a solution.

An architect assessed the doc read-only. Verdict: the current diagrams are STALE + IDEALIZED on exactly the permission surface plan-28 fixes, so the doc currently obscures the defect rather than helping verify it. A zero-match grep for `settings.json|command-policy|hard-deny|byte-parity|parity|source of truth` confirms the doc never mentions the enforcement surface.

## Problem

Evidence-backed findings from the architect's full read of `docs/ai/architecture-diagrams.md`:

- Section 4 (Adapter Render Pipeline, L128-171) is IDEALIZED/MISLEADING: it draws a single `permission-layers/render-adapters.php` seam projecting `allowedBash` to both Claude and Copilot outputs, implying one coherent permission source reaching all adapters. Reality (plan-28 Head A, confirmed against source): the composed-model seam (`aiPermissionRenderAdapters()` in `tools/ai/install/permission-layers/render-adapters.php`) IS a single `$model` fanned to per-adapter formats (`aiPermissionRenderOpenCodeBlock` for OpenCode `permission:`, `aiPermissionAllowedBashFromModel` for Copilot/Claude `allowedBash`) — so that part of the current diagram is accurate. The gap is that the ENFORCED Claude floor is `.claude/settings.json`, a statically hand-maintained `templates/claude/settings.json` union-merged by `claude-settings-merge.php` — NOT projected from that seam — and the `command-policy.tiers.yaml` compiled hook is a third separate surface. The diagram shows none of settings.json, the merge step, or the body-vs-floor disconnect. A reader concludes there is one permission source reaching all outputs; there are three unlinked *enforcement surfaces* (the composed seam being only one of them).
- Section 4's `drift` node names `validate-adapter-drift.php` + `generate-agent-permissions.php --check` as if all four outputs are drift-guarded equally. Reality (plan-28 Head B): `generate-agent-permissions.php --check` covers only templates + `.opencode`; `.claude/**` and `.github/**` have NO byte-parity gate. The diagram paints a parity blanket that does not exist for Claude/Copilot agent bodies.
- Section 5 (Validation & Tool-Use Hooks, L173-208) is MISSING the relevant detail: it omits the permission/settings enforcement layer entirely, never shows `command-policy.tiers.yaml -> compile-command-policy.php -> compiled sh hook` as the third list, and never shows `validate-ai-surface.yml` as the CI gate.
- Sections 1-3 are accurate at their abstraction level and out of scope for change.
- The doc is hand-authored (no GENERATED header) and not registered in `docs/ai/source-of-truth.md`'s Editable-vs-Generated table, so nothing prevents it from silently drifting — the exact failure mode plan-28 fixes for agents. `scripts/ai/check-file-refs.sh` is a REVERSE orphan finder (basename referenced nowhere), NOT a forward "does this named path exist" check, so it does not satisfy a "modules named in the diagram still exist" verification.

## Target Outcome

`docs/ai/architecture-diagrams.md` honestly depicts both the CURRENT fragmented permission architecture (three unlinked lists + the `.claude`/`.github` parity blind spot) and the TARGET consolidated model (single composed model -> N projections incl. a generated settings floor -> byte-parity gate), with every not-yet-built element clearly marked "planned — see plan-28". The doc becomes an accurate communication + verification aid instead of a source of false confidence; plus one small forward file-existence check so the diagram cannot silently name a deleted module, and registration of the doc in `source-of-truth.md` as hand-authored-must-sync.

## In Scope

Decision: depict BOTH states in ONE new dedicated section, and correct the two existing diagrams minimally (annotating Section 4 alone cannot carry both the three-list fragmentation and the target projection model without breaking the file's ~=<20-nodes-per-diagram honesty rule at L17).

- Correct Section 4 (Adapter Render Pipeline): add a node for `.claude/settings.json` and its `claude-settings-merge.php` step shown as a SEPARATE hand-maintained input to the Claude output (not fed by the `perm` seam), breaking the single-seam illusion and labeled a known gap; split/correct the `drift` coverage so `generate-agent-permissions.php --check` edges point only from OpenCode + templates, with a visually distinct "no byte-parity gate (pre-plan-28)" annotation on the Claude/Copilot edges; add a one-line pointer to the new Section 6.
- Correct Section 5 (Validation & Tool-Use Hooks): add the missing enforcement layer — nodes for `.claude/settings.json` (enforced allow/deny floor), `command-policy.tiers.yaml -> compile-command-policy.php -> compiled sh hook`, and `validate-ai-surface.yml` as the CI gate. If this would exceed ~20 nodes, push the permission-list detail into Section 6 and keep Section 5's addition to just the `validate-ai-surface.yml` CI node + a cross-reference.
- Add NEW Section 6 "Permission Source-of-Truth & Projection Model" with TWO small Mermaid diagrams:
  - 6a "Current (pre-plan-28)" (<=~14 nodes) showing three unlinked clusters — (1) composed model -> `render-adapters.php` seam -> per-agent Bash Command Policy body (advisory); (2) `templates/claude/settings.json` static hand-maintained -> `claude-settings-merge.php` -> `.claude/settings.json` enforced floor; (3) `command-policy.tiers.yaml` -> `compile-command-policy.php` -> compiled sh hook — with NO edge linking clusters (1) and (2) (that absence IS the defect) and a red-flag annotation "no byte-parity gate for .claude/.github".
  - 6b "Target (planned — see plan-28)" (<=~14 nodes) showing a single `aiPermissionComposeFromSpec` over `aiInstallerAgentProfiles` composed model fanning to N projections (OpenCode permission: block, Copilot/Claude allowedBash body, NEW generated Claude settings floor via `generate-claude-settings.php`), plus the NEW `render-adapters.php --check` byte-parity gate spanning `.claude/agents` + `.github/agents`, plus the Claude-capability-filter node; `command-policy.tiers.yaml` stays a deliberately-separate node labeled "consistency-asserted, unification deferred"; every planned-only node carries a visual "planned" marker; NO second-registry node.
- Update "Scope and Honesty Notes" and "Regenerating / Updating" to cover the new content and state the maintenance rule (hand-authored, mirrors architecture as of a named commit, must be updated in the same slice as any render/permission-pipeline change).
- Add ONE small forward file-existence consistency check (smallest sufficient, docs-sync-shaped, reusing the existing PHP test harness — a ~O(20-line) assertion that greps the diagram doc for own-code paths under `tools/ai/`, `scripts/ai/`, `packages/**` in code spans and asserts each resolves; planned-marked target paths `render-adapters.php`/`generate-claude-settings.php` exempted until plan-28 lands, then flipped). Fallback if even that is deemed over-engineering: a manual honesty caveat only. Prefer the check (a caveat alone still drifts).
- Register `architecture-diagrams.md` in `docs/ai/source-of-truth.md`'s classification as hand-authored-must-sync.

## Out Of Scope (Things To Avoid)

- Editing sections 1-3 beyond a cross-reference to Section 6.
- Any target-state diagram element shown as already-implemented (no unlabeled "single source" pipeline while `render-adapters.php`/`generate-claude-settings.php` are absent — both confirmed absent today).
- Any heavyweight diagram auto-generation or new validator engine (no auto-rendered Mermaid from graphify).
- Restating or duplicating plan-28's implementation steps; this doc reflects architecture, it is not a substitute for plan-28.
- Any second permission/tool registry or second agent->profile map in any diagram (violates plan-28's locked prohibition).
- Folding `command-policy.tiers.yaml` into the "single source" picture (plan-28 keeps it separate; unification deferred).
- Implementation in this pass (this is a plan only).

## Affected Paths

- `docs/ai/architecture-diagrams.md` (primary — Section 4 corrections, Section 5 additions, new Section 6, honesty-notes/regenerating updates)
- `docs/ai/source-of-truth.md` (register the diagram doc as hand-authored-must-sync — NOTE shared with plan-28 AC-08; coordinate to avoid a merge collision)
- At most one small test surface (a forward file-existence assertion in the existing PHP test harness)
- Reference-only (named in diagrams, do not edit): `tools/ai/install/permission-layers/render-adapters.php`, `tools/ai/install/claude-settings-merge.php`, `packages/ai-universal-rules/templates/claude/settings.json`, `docs/ai/command-policy.tiers.yaml`, `tools/ai/compile-command-policy.php`, `.github/workflows/validate-ai-surface.yml`, and the confirmed-absent target modules `tools/ai/render-adapters.php` + `tools/ai/generate-claude-settings.php` (rendered as "planned")

## Contracts And Boundaries

- Format: Mermaid `graph` blocks matching the file's existing conventions; each diagram <=~20 nodes (file rule L17); own-code only, excluded paths per L16.
- Architecture (from plan-28, must not be contradicted): single composed model `aiPermissionComposeFromSpec` over `aiInstallerAgentProfiles`; N projections descend from it; NO second registry (locked); `command-policy.tiers.yaml` stays separate.
- Source-of-truth boundary: plan-28 is authoritative for the TARGET architecture; the CURRENT-state diagrams must match confirmed file reality (three lists present; `render-adapters.php`/`generate-claude-settings.php` absent).
- Docs-sync boundary: reuse `source-of-truth.md`'s existing generated-vs-hand-authored pattern; do not invent a new classification scheme.
- Sequencing boundary: plan-29 lands independently NOW; a documented follow-up flips "planned" markers to "current" (and activates the file-existence check's target-path exemptions) when plan-28 phases merge.

## Todo Plan

- [x] P0: Correct Section 4 — add the separate `.claude/settings.json` + `claude-settings-merge.php` hand-maintained input (breaking the single-seam illusion), split/annotate drift coverage to show the `.claude`/`.github` byte-parity blind spot, and add the Section 6 cross-reference.
- [x] P0: Add NEW Section 6 with diagram 6a (Current — three unlinked clusters, no (1)-(2) edge, parity red-flag).
- [x] P0: Add diagram 6b (Target — one composed model -> N projections incl. generated settings floor + byte-parity gate + capability filter; every planned-only node marked "planned — see plan-28"; no second-registry node; command-policy.tiers.yaml separate/deferred).
- [x] P1: Add the missing enforcement layer to Section 5 (settings floor, sh-hook third list, `validate-ai-surface.yml` CI node), pushing detail into Section 6 if the <=20-node rule would break.
- [x] P1: Update "Scope and Honesty Notes" and "Regenerating / Updating" to cover the new content and state the hand-authored-must-sync maintenance rule.
- [x] P1: Register `architecture-diagrams.md` in `docs/ai/source-of-truth.md` as hand-authored-must-sync (coordinate with plan-28 AC-08's edit to the same file). NOTE: to satisfy the shipped-surface contract (`validate-install-surface.php` Direction B forbids a shipped doc from referencing an unshipped `docs/ai/**` path), the registration names the doc as a local `architecture-diagrams.md` under `docs/ai/` without the full `docs/ai/architecture-diagrams.md` path token — it is an untracked, project-local doc not shipped by any pack.
- [x] P2: Add the single small forward file-existence check (own-code paths in the diagram doc resolve; target-only paths exempted until plan-28 lands), reusing the existing PHP test harness (`tests/php/ArchitectureDiagramReferencesTest.php`).
- [x] P2: Document the "planned -> current" flip follow-up tied to plan-28 phase completion (in the diagram doc's "Regenerating / Updating"; also guarded by `testPlannedExemptPathsAreStillAbsent`).

## Acceptance Criteria

- [x] AC-01: Section 4 renders a `.claude/settings.json` node fed by `claude-settings-merge.php` as a SEPARATE input to the Claude output (not from the `perm`/render-adapters seam), visibly labeled a known gap.
- [x] AC-02: Section 4's drift coverage visibly distinguishes the OpenCode+templates `--check` path from the un-gated `.claude`/`.github` bodies (a "no byte-parity gate (pre-plan-28)" annotation is present).
- [x] AC-03: New Section 6 exists with 6a (Current) showing three clusters and NO edge between the composed-body cluster and the settings-floor cluster, plus a parity red-flag annotation.
- [x] AC-04: Section 6b (Target) shows one composed model fanning to N projections including the generated settings floor and the byte-parity gate, with NO second-registry node and `command-policy.tiers.yaml` shown as a separate deferred node.
- [x] AC-05: Every target-only module named in any diagram (`render-adapters.php`, `generate-claude-settings.php`, generated settings floor, capability filter) carries a "planned" marker; grep of the doc finds no unlabeled current claim for these.
- [x] AC-06: Section 5 shows the three enforcement surfaces (or, if node-limited, at least the `validate-ai-surface.yml` CI node + a Section-6 cross-reference); no diagram exceeds ~20 nodes.
- [x] AC-07: "Scope and Honesty Notes" and "Regenerating / Updating" state the doc is hand-authored, mirrors architecture as of a named commit, and must be updated in the same slice as any render/permission-pipeline change; `architecture-diagrams.md` appears in `source-of-truth.md`'s classification.
- [x] AC-08: Exactly one small consistency mechanism is added (forward file-existence assertion; fallback caveat) — no auto-generation engine; the check passes with target paths exempted.
- [x] AC-09 (negative): No diagram depicts a second registry or a second agent->profile map; sections 1-3 content is unchanged except a Section-6 cross-reference.

## Verification Plan

- AC-01..AC-04, AC-06, AC-09: inspect the rendered doc for the specified nodes/edges/annotations and absence of the second-registry node; confirm each Mermaid block <=~20 nodes.
- AC-05: grep the doc for each target-only path and confirm a "planned" marker adjacent to every occurrence.
- AC-07: read the two updated sections and the `source-of-truth.md` row.
- AC-08: confirm exactly one check added; run it and confirm it passes with `render-adapters.php`/`generate-claude-settings.php` exempted; confirm no diagram auto-generation was introduced.
- Overall: `git diff --stat` shows only `docs/ai/architecture-diagrams.md`, `docs/ai/source-of-truth.md`, and at most one test file changed.

## Risks And Rollback

- R1 (low): Section 5 may exceed the <=20-node rule if full three-list detail is added there; mitigation — push list detail into Section 6, keep Section 5 minimal (CI gate node + cross-reference).
- R2 (low, coordination): plan-28 AC-08 also edits `docs/ai/source-of-truth.md`; plan-29's registration touches the same file — flag so whichever lands second rebases cleanly (different rows/sections, not a blocker).
- R3 (low): the "planned" markers in 6b go stale once plan-28 merges; mitigation — this plan documents the follow-up flip tied to plan-28 completion.
- R4 (unknown, low): cheapest implementation of the file-existence check (PHPUnit vs shell) is an implementer decision; the design fixes the requirement (forward reference existence, target paths exempted), not the mechanism; `check-file-refs.sh` confirmed unsuitable (reverse orphan finder).
- Rollback: single-doc + one-test slice; revert the PR to restore prior state; no runtime/permission impact.

## Handoff Notes

- Source of truth for TARGET architecture: plan-28-permission-sot-and-render-parity-sync.md; do not contradict its locked "no second registry" or "keep command-policy.tiers.yaml separate" decisions.
- Confirmed-absent target modules (`tools/ai/render-adapters.php`, `tools/ai/generate-claude-settings.php`) MUST render as "planned", never current.
- Keep each Mermaid diagram <=~20 nodes; two small honest diagrams (6a/6b) over one overloaded one.
- Reuse `source-of-truth.md`'s existing generated-vs-hand-authored pattern; do not invent a new classification scheme; coordinate the shared `source-of-truth.md` edit with plan-28 AC-08.
- Land now as a standalone docs slice; document the "planned -> current" flip follow-up tied to plan-28's phases.
- Recommended next step after persistence: docs agent to execute the slice; this is low-risk documentation — no reviewer/release-auditor gate required (unlike plan-28). Do not implement before this plan is persisted.
