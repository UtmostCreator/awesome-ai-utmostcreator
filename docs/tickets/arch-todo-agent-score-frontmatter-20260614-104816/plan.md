# Architecture Plan — agent_score Full Rubric in Agent Frontmatter

- Ticket: none
- Source: decomposition of `docs/tickets/arch-todo-repo-cleanup-shipping-surface-20260614-101701/plan.md` + `todo-agents-rework.md` scoring model, with binding user decisions (rubric FROZEN)
- Generated: 20260614-104816
- Plan folder: docs/tickets/arch-todo-agent-score-frontmatter-20260614-104816/
- Status: Todo (unchecked) — write-only; reviewer reviews, edits applied, THEN implement
- Decomposition role: **Plan D of A–D** (standalone; also owns agent provenance moved from Plan C item 45)
- Rank: item 35 (+ item 45 provenance, moved here from Plan C)
- Dependency: **Plan A committed**

## Context

Manifest-level agent scoring (step 3a) already landed in the working tree (the new `AgentsManifestTest` + `docs/ai/AGENTS-MANIFEST.md` support it). This plan covers the projection-to-frontmatter step: carry the FULL `agent_score` rubric into the canonical agent TEMPLATE schema + renderer and re-render through the approved installer.

The rubric is FROZEN to the full rubric from `todo-agents-rework.md` (verified at `todo-agents-rework.md:38-51`, top-level key `agent_assessment:`), NOT the earlier two-number `design_readiness`/`execution_trust` scheme.

Frozen rubric fields (per `todo-agents-rework.md:38-51`, exact):

- `score` (0-100)
- `confidence` (0-100)
- `role_clarity` (0-15)
- `scope_control` (0-15)
- `permission_safety` (0-15)
- `output_contract` (0-15)
- `evidence_required` (0-15)
- `verification_strength` (0-15)
- `handoff_quality` (0-10)
- `risk_level` (low | medium | high | critical)
- `decision` (approve | approve_with_minor_fixes | needs_refactor | block)

> REVIEWER-REQUIRED CORRECTION (applied): `risk_level` was missing from the
> frozen list; added to match the cited source exactly.
>
> PROVENANCE (folded in from Plan C item 45): provider agent files
> (`.opencode/agents/*.md`, `.github/agents/*.agent.md`) are GENERATED and
> already carry a provenance comment AFTER the frontmatter, inserted idempotently
> by the renderer (`copilot-agent-renderer.php` + the OpenCode agent renderer).
> Any provenance change is a renderer/template change re-rendered through the
> installer — same surface as the rubric work — so it is owned here, NOT by
> hand-editing generated files in Plan C.

## Problem

The agent frontmatter does not yet carry the full operational-readiness rubric, so per-agent scoring is not projected to the agent files themselves and cannot be validated for agreement with the manifest. The rubric must be added to the canonical template schema + renderer (single source) and re-rendered through the approved installer so generated agent files carry it consistently.

## Target Outcome

The canonical agent template schema and renderer carry the full frozen rubric; re-rendering through the approved installer produces valid agent files whose frontmatter includes the rubric; validators pass; and the manifest scoring and the frontmatter rubric agree.

## In Scope

- 35: Add the FULL `agent_assessment` rubric to the canonical agent TEMPLATE schema + renderer.
- 45 (folded in): if a machine-checkable provenance KEY is wanted beyond the existing post-frontmatter comment, add it in the template/renderer and re-render (do NOT hand-edit generated agent files).
- Re-render generated agent surfaces through the approved installer.
- Confirm manifest scoring (already landed) and frontmatter rubric agree.

## Out Of Scope (Things To Avoid)

- Numbered lifecycle folders (backlog item 90).
- `.github/agent-permissions/*.yaml` (backlog item 95).
- Any agent rename.
- The two-number `design_readiness`/`execution_trust` scheme (explicitly NOT used).
- Hand-editing generated agent files directly instead of editing the template + re-rendering.

## Affected Paths

- Canonical agent template schema under `packages/ai-universal-rules/templates/**` (and any schema file the renderer validates against).
- The agent renderer in `tools/ai/**` that projects template -> agent frontmatter.
- Generated agent surfaces (`.opencode/agents/**`, `.github/agents/**`) — updated only via re-render through the installer.
- This plan folder (durable planning output).

## Contracts And Boundaries

- Template schema + renderer are the single source; generated agent files are produced by re-rendering, never hand-edited.
- Re-render runs through the approved installer command (`php tools/ai/ai.php install ... --apply` or the documented installer entrypoint), which is approval-gated.
- The frozen rubric fields and ranges must match `todo-agents-rework.md:42-50` exactly.
- Frontmatter rubric must agree with the already-landed manifest scoring (3a).
- This change touches generated agent surfaces via the installer — treat as approval-gated generated-artifact work.

## Todo Plan

- [ ] 35a: Add the full frozen rubric fields (`score`, `confidence`, `role_clarity`, `scope_control`, `permission_safety`, `output_contract`, `evidence_required`, `verification_strength`, `handoff_quality`, `risk_level`, `decision`) to the canonical agent TEMPLATE schema.
- [ ] 35b: Update the agent renderer to project those rubric fields into agent frontmatter.
- [ ] 35c: Re-render generated agent surfaces through the approved installer (approval-gated).
- [ ] 35d: Confirm the frontmatter rubric agrees with the already-landed manifest scoring (3a).

## Acceptance Criteria

- [ ] AC-01: The canonical agent template schema + renderer carry all frozen rubric fields with the correct ranges (`role_clarity`/`scope_control`/`permission_safety`/`output_contract`/`evidence_required`/`verification_strength` 0-15, `handoff_quality` 0-10, `score`/`confidence` 0-100, `risk_level` enum low|medium|high|critical, `decision` enum), matching `todo-agents-rework.md:38-51` exactly.
- [ ] AC-02: Re-rendering through the approved installer produces valid agent files whose frontmatter includes the rubric.
- [ ] AC-03: `validate-ai-config.php`, `validate-adapter-drift.php`, and `validate-install-surface.php` all pass after re-render.
- [ ] AC-04: Manifest scoring (3a) and the frontmatter rubric agree (no drift between manifest and rendered frontmatter).
- [ ] AC-05: `composer test:fast` passes.

## Verification Plan

- `php tools/ai/validate-ai-config.php` — confirms agent frontmatter (incl. rubric) is valid (AC-01, AC-03).
- `php tools/ai/validate-adapter-drift.php` — confirms rendered agent surfaces match their template source (AC-02, AC-03).
- `php tools/ai/validate-install-surface.php` — confirms install surface stays green after re-render (AC-03).
- `composer test:fast` — regression smoke; covers manifest/frontmatter agreement tests (AC-04, AC-05).

## Risks And Rollback

- Risk (medium): touching generated agent surfaces via the installer can drift many files at once. Mitigation: edit template + renderer only; re-render via approved installer; validate drift.
- Risk: rubric field/range mismatch vs `todo-agents-rework.md`. Mitigation: AC-01 pins exact fields and ranges to lines 38-51 (incl. `risk_level`).
- Risk: frontmatter disagrees with manifest scoring. Mitigation: AC-04 requires explicit agreement check.
- Rollback: revert the template + renderer commit and re-render through the approved installer to restore prior agent frontmatter.

## Handoff Notes

- Do NOT start until Plan A is committed.
- Edit the canonical template + renderer, then re-render — never hand-edit generated agent files.
- Use the frozen full rubric only; do not reintroduce the two-number scheme.
- Re-render is approval-gated installer work; confirm approval before `--apply`.
- implementer means implementer agent handoff using OpenCode command: /implement
