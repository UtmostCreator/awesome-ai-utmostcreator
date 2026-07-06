# Architecture Plan — Adopt SDD Ideas: AC-ID Traceability Triad + Requirement Quality-Gate Rubric (agnostic, capability extensions)

- Ticket: none (inferred from user instructions — see "Source Of This Plan"). External source project inspected read-only: `/home/utmostcreator/Projects/spec-driven-development`.
- Source: user instruction — "find anything useful and make it agnostic-free into this repo"; scope narrowed via two clarification answers to ideas #1 (traceability triad) + #2 (requirement quality-gate rubric), placed by extending existing capabilities.
- Generated: 2026-07-06T13:20:00Z (same session as plan-1/2/3)
- Plan file: docs/tickets/arch-todo-plan-writer-enrichment-and-browser-perms-20260706-104539/plan-4-sdd-traceability-and-requirement-quality.md
- Sibling plans: plan-1/2/3 in this folder — independent of all three (no shared files); may run in any order relative to them.

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-4-sdd-traceability-and-requirement-quality.md` and move it into `archive/` under this branch folder.

## Source Of This Plan

No ticket id; branch is `main`; **inferred from user instructions**. The user asked to mine the external `spec-driven-development` project for anything useful and fold provider-agnostic versions into this repo's instructions/agents/capabilities. A read-only external inspection produced a findings report with 4 candidate ideas; the user selected the two highest-value, lowest-overlap, fully-agnostic ones and chose to extend existing capabilities rather than add a new capability folder:

- **Idea #1 — AC-ID traceability triad**: each acceptance criterion gets a stable ID; each behavior test/verification names the AC ID it proves; review verifies every AC ID maps to a proof. (SDD source: `use-case-spec/SKILL.md` scenario IDs `S0/A1+`, `testing.instructions.md` `@useCase`/`@scenario` annotations, `uc-quality-review.agent.md` "N of N scenarios have tests".)
- **Idea #2 — requirement quality-gate rubric**: a reusable Measurable / Singular / Unambiguous / Testable / Unique-ID checklist with bad-vs-good examples, plus explicit requirement-conflict flagging. (SDD source: `requirements/SKILL.md` "Requirement Quality Checks" table + "Error Recovery" conflict-flagging.)

Ideas #3 (Gherkin AC structure) and #4 (spec-coverage review axis) were considered and **deliberately excluded** by the user for this slice — recorded under Out Of Scope, not lost.

## Context

Target capabilities already exist and own adjacent behavior; this plan extends them rather than duplicating:

- `docs/ai/capabilities/clarification-and-handoff/CAPABILITY.md` (81 ln, CAPABILITY-only, no support files) — already requires ACs be inferable and rejects material ambiguity; it is the natural home for the requirement quality rubric (#2).
- `docs/ai/capabilities/verify-change/CAPABILITY.md` (+ checklist/gotchas/examples/reference) — owns "name the focused test/assertion that proves the result"; natural home for the AC-ID→proof half of #1.
- `docs/ai/capabilities/review-diff/CAPABILITY.md` (+ support files) — owns diff review; natural home for the review-side "every AC ID has a proof" check of #1.
- `docs/ai/handoff-contract.md` (41 ln) — the minimum preserved payload already lists "acceptance criteria"; #1 adds the rule that ACs carry stable IDs so downstream can reference them.
- `packages/ai-universal-rules/templates/core/agents/architect.md` "Acceptance Criteria Discipline" (lines ~166-172) already classifies ACs (explicit/inferred/evidence-backed/unknown) and rejects vague ACs — #1 adds an ID convention; #2 adds the rubric it can reference.

## Problem

1. Our ACs have no stable ID scheme and no enforced AC↔proof↔review traceability. An AC can be written, a test can be written, and nothing links them — so "is every AC actually proven?" is not mechanically answerable at review time. (Overlap with existing: ~25%.)
2. We reject vague ACs but have no reusable, example-backed *rubric* for what makes a requirement/AC good, and no explicit requirement-conflict-flagging step. Each agent re-derives "good enough" ad hoc. (Overlap: ~30%.)

## Target Outcome

- A lightweight, provider-agnostic **AC-ID convention** (`AC-NN`, already used by the plan-writer format) is documented as the traceability anchor: verify-change requires each proof to name the AC ID(s) it covers; review-diff requires every AC ID in scope to have at least one named proof or an explicit gap note.
- A reusable **requirement/AC quality rubric** (Measurable / Singular / Unambiguous / Testable / Unique-ID) with bad-vs-good examples and a conflict-flagging step lives in `clarification-and-handoff` and is referenced (not copied) by the architect's Acceptance Criteria Discipline.
- No new capability folder; no stack-specific (Laravel/Gherkin/SonarQube) content; no import of SDD's "docs beat code" source-of-truth stance.

## In Scope

- Extend `clarification-and-handoff/CAPABILITY.md` with a new `## Requirement Quality Rubric` section: the 5-check table (Measurable/Singular/Unambiguous/Testable/Unique-ID) with one bad-vs-good example each, plus a one-line "flag requirement conflicts explicitly (name both IDs and the contradiction)" rule.
- Extend `verify-change/CAPABILITY.md` (and its `checklist.md`) with an AC-ID traceability rule: every verification claim must name the AC ID(s) it proves; unproven AC IDs are reported as `unknown`, not silently dropped.
- Extend `review-diff/CAPABILITY.md` (and its `checklist.md`) with an AC-coverage rule: enumerate the in-scope AC IDs and confirm each maps to a named proof or an explicit gap; a missing mapping is a review finding.
- Add a short pointer from `architect.md`'s "Acceptance Criteria Discipline" to the new rubric (reference only — do not copy the table into the agent).
- Add one line to `docs/ai/handoff-contract.md` clarifying that acceptance criteria in the payload carry stable `AC-NN` IDs so downstream agents can reference them.
- Keep every edit within the file's line budget (`docs/ai/ai-file-standards.md`: CAPABILITY ideal 120-220 / hard max 400; checklist hard max 180).

## Out Of Scope (Things To Avoid)

- Do NOT create a new capability folder (user chose "extend existing").
- Do NOT import SDD idea #3 (mandatory Gherkin Given/When/Then AC shape) or #4 (spec-coverage-as-review-axis beyond the AC-ID mapping already in scope) — deferred, not adopted.
- Do NOT import any stack-specific content: no Laravel/Artisan/Pint/Blade/Eloquent, no SonarQube/`mcp_sonarqube`, no Cypress, no PlantUML, no `@useCase`/`@scenario` PHP-docblock syntax. Use provider-neutral wording ("name the AC ID the proof covers").
- **Do NOT import SDD's "docs are the source of truth, spec wins over code" stance** — it directly conflicts with our code-first `docs/ai/source-of-truth.md` ("stale markdown must not override code evidence"). AC traceability is a FORWARD contract (ACs drive and are proven by work), never an inversion of our conflict-resolution order. This is the single biggest agnostic-safety trap in the source material.
- Do NOT re-open the already-decided spec-kit install-time-rendering question (`arch-todo-speckit-comparison-adoption-...` AC-28 recorded it as deliberately not adopted).
- Do NOT copy the rubric table into multiple files — it lives in ONE capability; others reference it.
- Do NOT retro-edit existing plans or unrelated docs.

## Affected Paths

- `docs/ai/capabilities/clarification-and-handoff/CAPABILITY.md` — new `## Requirement Quality Rubric` section (#2)
- `docs/ai/capabilities/verify-change/CAPABILITY.md` + `docs/ai/capabilities/verify-change/checklist.md` — AC-ID→proof rule (#1)
- `docs/ai/capabilities/review-diff/CAPABILITY.md` + `docs/ai/capabilities/review-diff/checklist.md` — AC-ID coverage rule (#1)
- `packages/ai-universal-rules/templates/core/agents/architect.md` — one reference pointer in Acceptance Criteria Discipline
- `docs/ai/handoff-contract.md` — one clarifying line on AC-NN IDs
- (read-only reference) `docs/ai/source-of-truth.md`, `docs/ai/ai-file-standards.md`

## Files To Read For Similar Logic

Read before editing — most target sections already exist and must be extended in-place (~30% overlap; extend, do not add parallel sections):

- `docs/ai/capabilities/clarification-and-handoff/CAPABILITY.md` lines 56-60 ("Multiple Plausible Interpretations") — the nearest existing AC-quality logic to extend from.
- `packages/ai-universal-rules/templates/core/agents/architect.md` lines 166-172 ("Acceptance Criteria Discipline") — already rejects vague ACs; the rubric is what it should point at.
- `docs/ai/capabilities/verify-change/CAPABILITY.md` "Verification Expectations" + its `checklist.md` — where "name the proof" already lives; add "name the AC ID" beside it.
- `docs/ai/capabilities/review-diff/CAPABILITY.md` "Workflow"/"Verification Expectations" + its `checklist.md` — where AC-coverage enumeration belongs.
- `docs/ai/handoff-contract.md` — the payload list that already includes "acceptance criteria".
- External (read-only, do NOT copy verbatim): `/home/utmostcreator/Projects/spec-driven-development/.github/skills/aiup-core/requirements/SKILL.md` lines 70-89 (rubric + conflict-flagging) and `.github/agents/uc-quality-review.agent.md` lines 45-83 (AC-coverage report shape) — mine the *idea*, rewrite agnostic.

## Contracts And Boundaries

- Contract: `AC-NN` is the single AC-ID convention (already emitted by the plan-writer's `## Acceptance Criteria` format from plan-1). This plan makes verify-change + review-diff consume that same ID — no new ID scheme.
- Contract: the requirement quality rubric lives in exactly one capability (`clarification-and-handoff`); architect + others reference it. No duplication (per `docs/ai/ai-file-standards.md` adapter/duplication rules).
- Boundary: capability docs are canonical; keep additions terse and within line budgets. These are `docs/ai/**` canonical edits + one `packages/` template edit (architect) — the architect template has a render pipeline, so its rendered adapters (`.opencode`/`.github`/`.claude` architect copies) may need regeneration; confirm in Task 4 whether the one-line pointer is within the rendered body or only the template.
- Boundary: no code, no scripts, no generated artifacts, no tests change (this is documentation-contract only).

## Recommended Execution Order

First safe chunk: **Task 1** (add the rubric to `clarification-and-handoff`) — single-file, additive, no cross-references depend on it yet, self-verifiable by re-reading. It also unblocks Task 4's reference pointer (the pointer needs the rubric section to exist first).

1. Task 1 — add the Requirement Quality Rubric section (#2) to `clarification-and-handoff`.
2. Task 2 — add the AC-ID→proof rule to verify-change (CAPABILITY + checklist) (#1).
3. Task 3 — add the AC-ID coverage rule to review-diff (CAPABILITY + checklist) (#1).
4. Task 4 — add the architect reference pointer + handoff-contract AC-ID line; regenerate architect adapters if needed.
5. Task 5 — verification (line budgets, adapter drift, doc-check).

Tasks 2 and 3 touch disjoint capability folders and may run in parallel with each other after Task 1. Task 4 depends on Task 1 (pointer target must exist).

## Multi-Project Split And Order

single project — N/A. All edits are inside `awesome-ai-utmostcreator`. The external `spec-driven-development` project is inspected READ-ONLY only (idea-mining); no edit is made there, and no `project-interaction.md` external-edit approval is needed because nothing is written outside this repo.

## Todo Plan

- [ ] P0 — Task 1: Add Requirement Quality Rubric to clarification-and-handoff (#2)
  - [ ] P0.1: Add a `## Requirement Quality Rubric` section with the 5-check table (Measurable / Singular / Unambiguous / Testable / Unique-ID), one agnostic bad-vs-good example per row (no stack-specific examples).
  - [ ] P0.2: Add a one-line rule: "Flag requirement/AC conflicts explicitly — name both IDs and the contradiction — before proceeding."
  - How this is tested: re-read the section (AC-01); inspection-only, no runtime.
- [ ] P1 — Task 2: AC-ID → proof rule in verify-change (#1)
  - [ ] P1.1: In `verify-change/CAPABILITY.md` "Verification Expectations", add: every verification claim names the AC ID(s) it proves; an AC ID with no executed proof is reported `unknown`, never silently dropped.
  - [ ] P1.2: Add the matching line to `verify-change/checklist.md`.
  - How this is tested: re-read both files (AC-02); parity check that wording matches.
- [ ] P1 — Task 3: AC-ID coverage rule in review-diff (#1)
  - [ ] P1.3: In `review-diff/CAPABILITY.md` workflow/expectations, add: enumerate in-scope AC IDs; confirm each maps to a named proof or an explicit gap; a missing mapping is a review finding.
  - [ ] P1.4: Add the matching line to `review-diff/checklist.md`.
  - How this is tested: re-read both files (AC-03).
- [ ] P2 — Task 4: Architect pointer + handoff-contract line
  - [ ] P2.1: In `architect.md` "Acceptance Criteria Discipline", add a one-line reference to the new rubric (do NOT copy the table).
  - [ ] P2.2: In `docs/ai/handoff-contract.md`, clarify that acceptance criteria carry stable `AC-NN` IDs.
  - [ ] P2.3: Determine whether the architect one-liner falls inside the rendered adapter body; if so, regenerate `.opencode`/`.github`/`.claude` architect copies via the installer render path.
  - How this is tested: AC-04 (pointer present + no orphan reference), AC-05 (adapter parity if regenerated).
- [ ] P2 — Task 5: Verification
  - [ ] P2.4: Confirm every edited file stays within `docs/ai/ai-file-standards.md` budgets.
  - [ ] P2.5: Run doc-check + adapter-drift validators; report status honestly.
  - How this is tested: AC-06, AC-07 (command evidence).

## DONE / REMAIN

Counts below are produced by the repo's plan-status script (`scripts/ai/internal/ai-verify/36-plan-status.sh` via `bash scripts/ai/ai-verify.sh .`, `VERIFY_PLAN_STATUS=1` default), section-scoped to `## Todo Plan` and `## Acceptance Criteria` only — **never hand-counted**. Until plan-1's Task 5 ships the section-scoped mode, compute with the equivalent bounded-section `awk` scan (same logic as `plan_status_checklist_counts()`, bounded between `## Todo Plan`/`## Acceptance Criteria` and the next `## `). Re-run and update on every plan edit — do not estimate by eye. (These initial values were produced by running that bounded-section scan against this file, not by counting.)

- [ ] 16 (open Todo Plan items — tasks + subtasks)
- [x] 0 (completed Todo Plan items)
- [ ] 7 (open Acceptance Criteria)
- [x] 0 (completed Acceptance Criteria)

> blockers / issues / errors: none at plan time. Open unknown to close in Task 4: whether the architect reference pointer lands inside the rendered adapter body (needs regeneration) or only in the template. Record the finding + exact regenerate command/status here.

## Acceptance Criteria

- [ ] AC-01: `clarification-and-handoff/CAPABILITY.md` contains a `## Requirement Quality Rubric` section with all 5 checks, one bad-vs-good example each, and a conflict-flagging rule; no stack-specific examples. Verified by reading the section.
- [ ] AC-02: `verify-change` CAPABILITY + checklist require every verification claim to name the AC ID(s) it proves and to report unproven AC IDs as `unknown`. Verified by reading both files.
- [ ] AC-03: `review-diff` CAPABILITY + checklist require enumerating in-scope AC IDs and mapping each to a named proof or explicit gap. Verified by reading both files.
- [ ] AC-04: `architect.md` "Acceptance Criteria Discipline" references the rubric by pointer (not a copied table) and the reference resolves. Verified by reading the line + confirming the target section exists.
- [ ] AC-05: If the architect edit touched rendered adapter bodies, `.opencode`/`.github`/`.claude` architect copies are regenerated and adapter-drift parity is green; if it did not, this AC is recorded N/A with evidence. Verified by validator status.
- [ ] AC-06: Every edited file stays within `docs/ai/ai-file-standards.md` hard-max line budgets. Verified by line count.
- [ ] AC-07 (negative): No edited file imports SDD's "docs beat code" stance, and `docs/ai/source-of-truth.md` remains code-first and unchanged. Verified by reading the diff + confirming source-of-truth.md is untouched.

## Optional Proof Checks

- Optional contract proof: grep the diff for forbidden stack tokens (`Laravel`, `Artisan`, `Pint`, `Blade`, `Eloquent`, `SonarQube`, `Cypress`, `PlantUML`, `@useCase`, `@scenario`) and confirm zero hits — proves the agnostic constraint held.
- UI / shared-UI proof: none — documentation-contract change only, no rendered UI surface.

## Verification Plan

- AC-01/AC-02/AC-03/AC-04: `bash scripts/ai/preview-file.sh <file>` on each edited capability/checklist/agent/handoff file and read the added sections.
- AC-05: `php tools/ai/validate-adapter-drift.php` (add `--fail-on-warn` to gate) after any architect regeneration; report exact status.
- AC-06: line-count each edited file vs `docs/ai/ai-file-standards.md`.
- AC-07: `git diff` review + confirm `docs/ai/source-of-truth.md` is absent from the changed-file list; optionally `rg -n "docs.*source of truth|spec wins" <edited files>` returns nothing.
- Optional agnostic grep: `rg -ni "laravel|artisan|pint|blade|eloquent|sonarqube|cypress|plantuml|@usecase|@scenario"` across the edited files → expect no matches.

## Human Test Steps

No runtime/browser surface. A human confirms the outcome by reading:

1. Open `docs/ai/capabilities/clarification-and-handoff/CAPABILITY.md`, scroll to `## Requirement Quality Rubric`, and confirm the 5-check table + conflict-flagging rule are present and stack-neutral.
2. Open `docs/ai/capabilities/verify-change/CAPABILITY.md` and `.../review-diff/CAPABILITY.md` and confirm each now references naming/mapping AC IDs to proofs.
3. Open `packages/ai-universal-rules/templates/core/agents/architect.md`, find "Acceptance Criteria Discipline", and confirm it points to the rubric without duplicating the table.
4. Confirm `docs/ai/source-of-truth.md` is unchanged (still code-first) — the agnostic-safety guarantee.

## Risks And Rollback

- Risk: accidentally importing SDD's docs-supremacy stance, contradicting our source-of-truth order. Mitigation: explicit Out-Of-Scope rule + negative AC-07 + the optional agnostic grep.
- Risk: rubric duplicated across files causing drift. Mitigation: single-home rule (Task 1) + reference-only pointers (Task 4); AC-04 checks pointer, not copy.
- Risk: architect template edit silently drifts rendered adapters. Mitigation: Task 4.3 + AC-05 gate regeneration/parity.
- Risk: line-budget overflow on capability files. Mitigation: keep the rubric to one compact table; AC-06 gate.
- Rollback: all edits are additive documentation to canonical `docs/ai/**` + one template; `git checkout -- <files>` reverts cleanly; if architect adapters were regenerated, re-run the installer render or revert the rendered copies. No code/migration/deploy. Risk level: low.

## Handoff Notes

- Fully agnostic documentation-contract change extending 3 existing capabilities + architect + handoff-contract; the external project is idea-source only and is never modified.
- First safe chunk: Task 1. Tasks 2/3 may run in parallel after it; Task 4 depends on Task 1.
- The two dropped SDD ideas (#3 Gherkin AC shape, #4 spec-coverage review axis) are recorded under Out Of Scope for a possible future slice — do not silently pull them in.
- `implementer means implementer agent handoff using OpenCode command: /implement`.
- Given the architect-template + adapter regeneration touchpoint, review in fresh context afterward: `reviewer means reviewer agent handoff using OpenCode command: /review-diff`.
