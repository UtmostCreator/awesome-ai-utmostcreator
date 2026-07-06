# Architecture Plan — Plan-Writer Format Enrichment (DONE/REMAIN, exec order, task/subtask split, human test steps)

- Ticket: none (inferred from user instructions — see "Source Of This Plan" below)
- Source: user instruction to architect + architecture-plan-writer, captured verbatim in "Source Of This Plan"
- Generated: 2026-07-06T10:45:39Z
- Plan file: docs/tickets/arch-todo-plan-writer-enrichment-and-browser-perms-20260706-104539/plan-1-plan-writer-format-enrichment.md
- Sibling plan: plan-2-browser-webfetch-permission.md (execute AFTER this plan — see Recommended Execution Order)

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-1-plan-writer-format-enrichment.md` and move it into `archive/` under this branch folder (`docs/tickets/arch-todo-plan-writer-enrichment-and-browser-perms-20260706-104539/archive/DONE-plan-1-plan-writer-format-enrichment.md`).

## Source Of This Plan

No ticket id was supplied and the current branch is `main`, so this plan is **inferred from user instructions**. The user asked (paraphrased, scope-locked): enrich what the `architecture-plan-writer` produces so every future plan includes a `## DONE / REMAIN` count section, a blockers blockquote, files-to-read-for-similar-logic, affected paths + contracts/boundaries, a recommended execution order + first-safe-chunk, task/subtask splitting when multiple P-levels exist, a per-chunk testability explanation, a multi-project split + ordering rule (reading the project-interaction doc when N-project edits), a ticket-link-or-inferred marker, optional contract/UI proof checks, and a closing test plan with human `1.2.3.` steps.

The separate "grant browser/webfetch permission to edit-capable agents" request is a **different workstream** and is captured in the sibling plan `plan-2-browser-webfetch-permission.md`. Per the user's chosen order (A first, then B), execute THIS plan first.

## Context

The `architecture-plan-writer` agent + its OpenCode skill together define the required format of every plan file written under `docs/tickets/`. Their two source-of-truth files are:

- `packages/ai-universal-rules/templates/core/agents/architecture-plan-writer.md` (canonical agent, esp. the `## Required Plan File Format` fenced block at lines ~169-223)
- `.opencode/skills/architecture-plan-writer/SKILL.md` (repo-local adapter; its `## Required Sections` at lines ~53-57 must stay aligned with the agent by hand — see its own "Repo-Local Exception Note")

Both files already state they must be kept aligned by hand (skill lines 93-99). This plan enriches the required-format contract in the agent and mirrors the section list into the skill.

## Problem

The current `## Required Plan File Format` produces plans that lack several things the user now wants as standard: no completed/incomplete count roll-up, no explicit blockers block, no "files to read for similar logic", no recommended execution order / first-safe-chunk, no explicit task→subtask split per priority, no per-chunk test-coverage-or-human-test justification, no multi-project split+ordering rule, no ticket-link-or-inferred marker line, and no closing human `1.2.3.` test-steps block. Plans are therefore inconsistent and harder to execute and verify.

## Target Outcome

Every newly written plan file (and every updated one, going forward) contains the enriched sections below, produced deterministically from the agent's `## Required Plan File Format`, with the skill's section list kept in sync. No behavior of the write/dedup/archive/loop-guard machinery changes.

## In Scope

- Extend `## Required Plan File Format` in `packages/ai-universal-rules/templates/core/agents/architecture-plan-writer.md` to add/enrich these sections, in this order:
  - a header marker line stating `Ticket: {id + link}` OR `Ticket: none (inferred from user instructions)` (never blank)
  - `## Files To Read For Similar Logic` — architect-supplied paths of existing similar implementations, or `none found`
  - `## Affected Paths` (already present — keep) and `## Contracts And Boundaries` (already present — keep)
  - `## Recommended Execution Order` — ordered list of chunks + explicit "First safe chunk: ..." line
  - `## Todo Plan` — grouped P0/P1/P2, and when >1 priority band exists, each parent task split into nested `- [ ]` subtasks; each implementation chunk carries a one-line "How this is tested:" note (test coverage, or "human test — see Human Test Steps")
  - `## DONE / REMAIN` — per-checkbox-group counts using the user's exact shape `- [ ] 33` / `- [x] 12`, plus a `> blockers / issues / errors` blockquote (or `> none`). These counts MUST be produced by running the repo's existing plan-status script (`scripts/ai/internal/ai-verify/36-plan-status.sh`, invoked via `bash scripts/ai/ai-verify.sh .`, `VERIFY_PLAN_STATUS=1` is already the default) — never by manually counting checkboxes. The section text must say this explicitly and name the exact command.
  - `## Multi-Project Split And Order` — when a plan touches 2+ projects: one subsection per project, a suggested start-project + ordering, and a required read of `docs/ai/project/project-interaction.md`; when single-project, the section says `single project — N/A`
  - `## Acceptance Criteria` (already present — keep, still `- [ ] AC-NN:` observable+testable)
  - `## Optional Proof Checks` — optional contract proofs and UI/shared-UI proofs (omit-able)
  - `## Verification Plan` (already present — keep) and a `## Human Test Steps` block with numbered `1.` `2.` `3.` "open it / click it / scroll here" instructions when a human-observable surface exists, else `no human-observable surface`
  - `## Risks And Rollback` and `## Handoff Notes` (already present — keep)
- Mirror the new section list into `.opencode/skills/architecture-plan-writer/SKILL.md` `## Required Sections` so the repo-local skill stays aligned by hand.
- Add a short rule to the agent's Required Flow / a new sub-rule: query the user for preferred change ordering ONLY when the plan affects 2+ projects OR is wide/many-file; skip the question for single-project, high-clarity edits.
- Add a rule: the closing test plan stays minimal — add test coverage only when the behavior is critical or coverage already exists; do not add clutter tests.
- Extend `scripts/ai/internal/ai-verify/36-plan-status.sh` with a section-scoped counting mode (bounded to one `## Heading` through the next `## `) so it can report `## Todo Plan` and `## Acceptance Criteria` counts separately, WITHOUT ever scanning `## DONE / REMAIN` itself as source data — this is required, not optional: the existing whole-file scan (`plan_status_checklist_counts`) double-counts a plan's own DONE/REMAIN summary lines as if they were checklist items (confirmed empirically against this very plan file: whole-file scan reported `checked=2, unchecked=20` against a true `0 checked / 18 unchecked`, because the DONE/REMAIN section's 4 count-lines were themselves counted). Add the new section-scoped function(s) alongside the existing whole-file check; do not remove or change the existing `check_plan_status` gate behavior or its tests.

## Out Of Scope (Things To Avoid)

- Do NOT change any write/dedup/archive/loop-guard/naming logic in the agent or skill (only the required-format contract + section list + the two new rules above).
- Do NOT touch the browser/webfetch permission work — that is `plan-2-...`.
- Do NOT retro-edit any existing plan files under `docs/tickets/**`; this changes the template contract for future plans only.
- Do NOT add a rendered adapter copy of the agent under `.github/**` or `.claude/**` — this agent is repo-local with no template render pipeline (confirmed: no rendered copies exist).
- Do NOT invent a new required section not listed above ("while we're here" additions are forbidden).
- Do NOT hand-roll a brand-new counting script; the repo already has one (`36-plan-status.sh`) — extend it with a section-scoped mode instead of duplicating the logic elsewhere.
- Do NOT change the existing whole-file `check_plan_status` gate's reported behavior, its default-on posture, or break `tests/shell/ai-verify-plan-status.bats` — only add new section-scoped helper function(s) alongside it.
- Do NOT let the DONE/REMAIN section's own numeric summary lines be counted as if they were real checklist items — the new section-scoped mode must be bounded strictly to `## Todo Plan` / `## Acceptance Criteria` so `## DONE / REMAIN` is structurally excluded, not filtered by pattern-matching.

## Affected Paths

- `packages/ai-universal-rules/templates/core/agents/architecture-plan-writer.md` (canonical — the `## Required Plan File Format` block + Required Flow)
- `.opencode/skills/architecture-plan-writer/SKILL.md` (repo-local mirror — `## Required Sections`)
- `scripts/ai/internal/ai-verify/36-plan-status.sh` — add section-scoped counting mode (Todo Plan / Acceptance Criteria), additive only
- `tests/shell/ai-verify-plan-status.bats` — add coverage for the new section-scoped mode; existing tests must keep passing unmodified
- (read-only reference) `docs/ai/project/project-interaction.md` — referenced BY the new format, not edited here
- (read-only reference) `docs/ai/ai-file-standards.md` — line-budget check for both edited agent/skill files

## Files To Read For Similar Logic

Existing plan files already model most enriched sections — read these before drafting the new format wording so the template matches real usage (~80% overlap with target shape; extend, do not reinvent):

- `docs/tickets/arch-todo-agent-permission-rethink-20260613T154104Z/plan-phase2-scripts-migration.md` — multi-phase plan with priority bands and verification mapping
- `docs/tickets/arch-todo-terminal-signal-annotated-docs-index-20260705-000000/archive/DONE-plan-1-terminal-signal-annotated-index.md` — a completed plan showing the archive/DONE end-state
- `packages/ai-universal-rules/templates/core/agents/architect.md` lines 200-246 — the architect's mandatory handoff payload (its list of what it hands to the plan writer is the upstream contract these sections must be able to hold)
- `scripts/ai/internal/ai-verify/36-plan-status.sh` — the exact prior-art script to extend: `plan_status_checklist_counts()` (whole-file scan, keep as-is) is the pattern to copy for a new bounded-section variant; `is_plan_status_target()` already scopes to `docs/tickets/*/plan*.md` excluding `archive/`
- `tests/shell/ai-verify-plan-status.bats` — existing test shape to extend for the new section-scoped mode

## Contracts And Boundaries

- Contract: the agent's `## Required Plan File Format` fenced block is the single source of truth for plan structure; the skill's `## Required Sections` is a hand-kept mirror. Both must list the same sections after this change.
- Boundary: this agent/skill are repo-local (constraint-#1 exception, user-approved); they are edited directly, never regenerated. No install/render step is triggered.
- Boundary: line budgets from `docs/ai/ai-file-standards.md` — agent files hard max 320, `SKILL.md` hard max 350. Current sizes: agent 256 lines, skill 99 lines. The enrichment must stay under budget.
- Contract: `scripts/ai/internal/ai-verify/36-plan-status.sh` is the single source of truth for computing DONE/REMAIN counts. Section-scoped counting is bounded strictly between one `## Heading` and the next `## ` — this structurally excludes `## DONE / REMAIN` from ever being scanned as source data, rather than relying on pattern exclusion.

## Recommended Execution Order

First safe chunk: **Task 1** (edit the canonical agent's Required Plan File Format) — it is the source of truth; everything else mirrors it. It is a single-file, additive, low-risk documentation edit that is trivially self-verifiable by re-reading the block.

1. Task 1 — enrich the canonical agent format (source of truth).
2. Task 2 — add the two new Required-Flow rules to the same agent file.
3. Task 3 — mirror the section list into the skill.
4. Task 4 — verification + alignment check.
5. Task 5 — extend `36-plan-status.sh` with the section-scoped counting mode that DONE/REMAIN now depends on.

Rationale for order: agent-before-skill guarantees the mirror copies a finished contract; rules-in-same-file (Task 2) batch naturally with Task 1 but are listed separately so their acceptance is tracked distinctly. Task 5 only needs Task 1's final section names (`## Todo Plan`, `## Acceptance Criteria`) to be settled — it touches disjoint files from Tasks 2-4 (a shell script + its bats test, not the agent/skill markdown) and may run in parallel with them once Task 1 lands.

## Todo Plan

- [ ] P0 — Task 1: Enrich the canonical agent's `## Required Plan File Format`
  - [ ] P0.1: In `architecture-plan-writer.md`, replace the fenced format block's section list with the enriched ordered list from "In Scope" (header ticket-marker line; Files To Read For Similar Logic; Recommended Execution Order + First-safe-chunk; Todo Plan with nested subtasks + per-chunk "How this is tested:"; DONE / REMAIN counts `- [ ] 33`/`- [x] 12` + blockers blockquote; Multi-Project Split And Order; Optional Proof Checks; Human Test Steps `1. 2. 3.`).
  - [ ] P0.2: Keep every existing section (Context, Problem, Target Outcome, In Scope, Out Of Scope, Affected Paths, Contracts And Boundaries, Acceptance Criteria, Verification Plan, Risks And Rollback, Handoff Notes) and the top completion instruction unchanged in meaning.
  - How this is tested: re-read the block (AC-01, AC-02); no runtime behavior — inspection-only.
- [ ] P0 — Task 2: Add the two new Required-Flow rules to the agent
  - [ ] P0.3: Add rule — "Ask the user for preferred change ordering ONLY when the plan affects 2+ projects OR is wide/many-file; skip for single-project high-clarity edits."
  - [ ] P0.4: Add rule — "Keep the closing test plan minimal: add test coverage only when behavior is critical or coverage already exists; never add clutter tests. Always add human `1. 2. 3.` steps when a human-observable surface exists."
  - How this is tested: re-read the agent's Required Flow / rules section (AC-03).
- [ ] P1 — Task 3: Mirror the section list into the skill
  - [ ] P1.1: Update `.opencode/skills/architecture-plan-writer/SKILL.md` `## Required Sections` to list the same enriched sections in the same order.
  - [ ] P1.2: Confirm the skill's "Repo-Local Exception Note" still correctly points at the agent template as the alignment source.
  - How this is tested: diff the two section lists for parity (AC-04).
- [ ] P2 — Task 4: Verify and confirm alignment + budget
  - [ ] P2.1: Run the repo's AI-config / doc validators that cover agent + skill files.
  - [ ] P2.2: Confirm both edited files remain within `docs/ai/ai-file-standards.md` line budgets.
  - How this is tested: AC-05, AC-06 (command evidence).
- [ ] P1 — Task 5: Extend `36-plan-status.sh` with section-scoped DONE/REMAIN counting
  - [ ] P1.3: Add a bounded-section counting function (same shape as `plan_status_checklist_counts()`, scanning only between one `## Heading` and the next `## `) and apply it to `## Todo Plan` and `## Acceptance Criteria` separately; do not scan `## DONE / REMAIN`.
  - [ ] P1.4: Do not modify the existing whole-file `check_plan_status`/`plan_status_checklist_counts` behavior, its default-on posture, or its JSON evidence shape — add alongside, not replace.
  - [ ] P1.5: Add bats coverage in `tests/shell/ai-verify-plan-status.bats` for the new section-scoped mode, including a case proving DONE/REMAIN's own count-lines are excluded (regression test for the double-counting bug found during this plan's own authoring).
  - How this is tested: AC-07, AC-08, AC-09 (command + test evidence).

## DONE / REMAIN

Counts below are produced by the repo's plan-status script (`scripts/ai/internal/ai-verify/36-plan-status.sh` via `bash scripts/ai/ai-verify.sh .`, `VERIFY_PLAN_STATUS=1` default), section-scoped to `## Todo Plan` and `## Acceptance Criteria` only — **never hand-counted**. Until Task 5 lands, compute this by running the equivalent bounded-section `awk` scan directly against this file (same logic as `plan_status_checklist_counts()`, bounded between `## Todo Plan`/`## Acceptance Criteria` and the next `## `); after Task 5 lands, invoke the script itself. Re-run and update on every plan edit — do not estimate or count by eye.

- [ ] 16 (open Todo Plan items — tasks + subtasks)
- [x] 0 (completed Todo Plan items)
- [ ] 9 (open Acceptance Criteria)
- [x] 0 (completed Acceptance Criteria)

> blockers / issues / errors: none at plan time. (Update this blockquote with any blocker, failed command, or permission denial encountered during implementation, including the exact command + status.)

## Multi-Project Split And Order

single project — N/A. All edits are inside this repository (`awesome-ai-utmostcreator`). No `docs/ai/project/project-interaction.md` external map read is required for this plan.

## Acceptance Criteria

- [ ] AC-01: The agent file's `## Required Plan File Format` fenced block contains, in order, the enriched section headings: ticket-marker line, `## Files To Read For Similar Logic`, `## Recommended Execution Order` (with a "First safe chunk" line), `## Todo Plan` (with nested subtasks + per-chunk "How this is tested:"), `## DONE / REMAIN` (with `- [ ] N`/`- [x] N` shape + blockers blockquote), `## Multi-Project Split And Order`, `## Optional Proof Checks`, `## Human Test Steps` (numbered `1. 2. 3.`). Verified by reading the block.
- [ ] AC-02: Every previously-required section and the top completion instruction still appear in the block (no section dropped). Verified by diffing old vs new section list.
- [ ] AC-03: The agent's Required Flow / rules contain the ordering-question rule (ask only for 2+ project or wide changes) and the minimal-test-plan + human-steps rule. Verified by reading those lines.
- [ ] AC-04: `.opencode/skills/architecture-plan-writer/SKILL.md` `## Required Sections` lists the same enriched sections in the same order as the agent (parity). Verified by side-by-side comparison.
- [ ] AC-05: The repo AI-config/doc validators pass for the two edited files. Verified by validator exit status.
- [ ] AC-06: Both edited files remain within their `docs/ai/ai-file-standards.md` hard-max line budgets (agent ≤ 320, skill ≤ 350). Verified by line count.
- [ ] AC-07: `36-plan-status.sh` exposes a section-scoped counting mode that reports `## Todo Plan` and `## Acceptance Criteria` checked/unchecked counts separately, bounded to each section only. Verified by invoking it against a sample plan file with known counts.
- [ ] AC-08: The section-scoped mode never includes `## DONE / REMAIN`'s own count-lines in its output (regression proof for the double-counting bug found while authoring this plan: the existing whole-file scan reported `checked=2, unchecked=20` against this file when the true section-scoped counts were `0/12` Todo Plan + `0/6` Acceptance Criteria). Verified by the new bats test case.
- [ ] AC-09: The existing whole-file `check_plan_status` gate, its default-on posture, and `tests/shell/ai-verify-plan-status.bats`'s existing test cases are unchanged and still pass. Verified by running the existing bats suite.

## Optional Proof Checks

- Optional contract proof: after editing, generate a throwaway sample plan mentally/manually from the new format and confirm all enriched sections can be filled from a normal architect handoff payload (architect.md lines 204-211) without inventing data. (Optional — skip if AC-01..AC-04 already pass.)
- UI / shared-UI proof: none — this plan changes markdown template contracts only; no rendered UI surface.

## Verification Plan

- AC-01/AC-02/AC-03: `AI_OUTPUT=json bash scripts/ai/preview-file.sh packages/ai-universal-rules/templates/core/agents/architecture-plan-writer.md --range 169:256` and read the format block + rules.
- AC-04: preview both section lists and compare — `bash scripts/ai/preview-file.sh .opencode/skills/architecture-plan-writer/SKILL.md --range 53:57`.
- AC-05: run the AI-config / doc validators the repo already uses (for example `php tools/ai/validate-ai-config.php` and `bash scripts/ai/ai-doc-check.sh --check`); report exact command + status honestly, including any failure.
- AC-06: line count each edited file and compare against `docs/ai/ai-file-standards.md` hard maxima.
- AC-07/AC-08: `bash tests/scripts/ai/run-all-tests.sh` filtered to `ai-verify-plan-status.bats` (or run bats directly on that file), including the new section-scoped + DONE/REMAIN-exclusion cases; alternatively invoke the new function directly against a fixture plan file and compare output to expected counts.
- AC-09: run the full existing `tests/shell/ai-verify-plan-status.bats` suite unmodified-case-by-case and confirm all previously-passing cases still pass.

## Human Test Steps

This change has no runtime/browser surface, but a human can confirm the outcome:

1. Open `packages/ai-universal-rules/templates/core/agents/architecture-plan-writer.md` and scroll to `## Required Plan File Format`.
2. Confirm you can see the new headings: `## Files To Read For Similar Logic`, `## Recommended Execution Order`, `## DONE / REMAIN`, `## Multi-Project Split And Order`, `## Optional Proof Checks`, `## Human Test Steps`.
3. Open `.opencode/skills/architecture-plan-writer/SKILL.md`, scroll to `## Required Sections`, and confirm it lists the same sections in the same order.
4. Run `bash scripts/ai/ai-verify.sh .` against a repo with an in-progress plan file and confirm the plan-status check reports counts; then run the new section-scoped mode directly against this very plan file and confirm it reports the Todo Plan and Acceptance Criteria counts separately, matching what is written in this file's own `## DONE / REMAIN` section.

## Risks And Rollback

- Risk: skill/agent drift if only one file is edited. Mitigation: Task 3 + AC-04 enforce parity.
- Risk: exceeding line budgets. Mitigation: AC-06 gate; keep enrichment terse and reference existing sections instead of duplicating prose.
- Risk: Task 5 touches a shared verification script (`36-plan-status.sh`) used by the repo's default verification gate. Mitigation: additive-only change (new function, new mode), explicit Out-Of-Scope + AC-09 gate requiring the existing whole-file check and its tests stay unchanged and passing.
- Risk: without Task 5, DONE/REMAIN counts silently corrupt on every re-run (confirmed empirically: this file's own whole-file scan reported `checked=2, unchecked=20` instead of the true `0/12` + `0/6`, an error that would compound further with each future update). Mitigation: Task 5 is P1, not deferred to a later plan; AC-07/AC-08 gate it explicitly.
- Rollback: agent/skill edits are additive documentation to two markdown files; `git checkout -- <file>` on each reverts cleanly. The script edit is additive (new function only); revert via `git checkout -- scripts/ai/internal/ai-verify/36-plan-status.sh tests/shell/ai-verify-plan-status.bats`. No migration, no generated output, no install step. Risk level: low.

## Handoff Notes

- This is a documentation-contract change to two repo-local markdown files, plus an additive extension to one existing shell verification script and its bats test; no rendered adapters, no install/render step.
- Execute Task 1 → 2 → 3 → 4 in order; Task 5 may run in parallel with Tasks 2-4 once Task 1 settles the final section names. First safe chunk is Task 1.
- DONE/REMAIN counts in every future plan (including updates to this one) MUST come from the plan-status script's section-scoped mode once Task 5 lands — never manual counting. This plan's own counts above were verified with the equivalent bounded-section `awk` scan as a stand-in until Task 5 ships.
- After this plan is fully checked, proceed to `plan-2-browser-webfetch-permission.md`.
- `implementer means implementer agent handoff using OpenCode command: /implement`.
