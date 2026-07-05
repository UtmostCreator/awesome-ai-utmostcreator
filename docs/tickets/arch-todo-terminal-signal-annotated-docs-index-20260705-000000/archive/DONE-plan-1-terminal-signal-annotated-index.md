# Architecture Plan — Terminal Signal Line + Annotated Docs Index

- Ticket: none
- Source: architect handoff (external "Flow" project research — two ranked, lowest-friction recommendations selected for this bounded slice)
- Generated: 2026-07-05
- Plan file: docs/tickets/arch-todo-terminal-signal-annotated-docs-index-20260705-000000/plan-1-terminal-signal-annotated-index.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-1-terminal-signal-annotated-index.md` and move it into `archive/` under this branch folder (`docs/tickets/arch-todo-terminal-signal-annotated-docs-index-20260705-000000/archive/DONE-plan-1-terminal-signal-annotated-index.md`). See "Archive On Completion" in `.opencode/skills/architecture-plan-writer/SKILL.md` for the exact steps.

## Context

A prior researcher task investigated the external Rust project at `/home/utmostcreator/Projects/code` ("Flow") to find borrowable ideas for this repo's AI-workflow docs/policy kit. Two ranked, lowest-friction recommendations were selected for this bounded slice. Medium/higher-effort recommendations (declarative invariant gate, failure-to-repair-prompt bundle, CI shape alignment) were explicitly ranked out of scope and deferred by the architect.

Current branch is `main`, so per this agent's naming rule (branch is `main`/`master`/`HEAD`/detached requires an explicit folder name) this plan is filed under `arch-todo-terminal-signal-annotated-docs-index-20260705-000000/` rather than `docs/tickets/main/`, matching every existing sibling folder's `arch-todo-{slug}-{timestamp}` convention.

## Problem

This repo's agent responses have no terminal machine-parseable status-signal line, and there is no curated, hand-authored, annotated "why it matters" high-signal index across `docs/ai/**` — only the generated `docs/ai/catalog.md` asset inventory, which covers ~35% of root docs and is the wrong shape for onboarding.

## Target Outcome

1. `docs/ai/execution-protocol.md` documents a new terminal `signal: <token>` line unified with the existing verification-status vocabulary (adds only two new generic tokens: `done`, `error`).
2. A new curated `docs/ai/index.md` exists as a hand-maintained, annotated onboarding index, distinct from `docs/ai/catalog.md`, with a single discoverability pointer added from `docs/ai/workflow.md`.

## In Scope

- Insert a new `## Terminal Signal Line` section into `docs/ai/execution-protocol.md`, between the existing `## Verification Statuses` section and `## Related Docs`.
- Create new file `docs/ai/index.md` (curated, hand-authored, not generated) with the 9-section structure specified by the architect.
- Add exactly one pointer line/bullet in `docs/ai/workflow.md` referencing `docs/ai/index.md`.

## Out Of Scope (Things To Avoid)

- Do NOT modify anything under: `docs/tickets/arch-todo-dynamic-stack-permission-selection-*/`, `docs/tickets/arch-todo-permission-layer-composition-*/`, `docs/tickets/arch-todo-recommended-tools-tier-ab-integration-*/`, `packages/ai-universal-rules/stacks/`, `schemas/ai/stack-*.schema.json`, `tests/php/{PermissionCompose,StackDetection,StackRegistry}Test.php`, `tools/ai/generate-agent-permissions.php`, `tools/ai/install/{permission-layers/,stack-detection.php,stack-registry.php}`. This is unrelated pre-existing in-flight work in the working tree.
- Do NOT re-stage or comment on other pre-existing dirty files: `.github/agents/release-auditor.agent.md`, `.opencode/agents/{architecture-plan-writer,release-auditor,researcher}.md`, `docs/ai/validation.md`, the agent-permission-rethink todo file, `v0.6-plan/.../plan.md`, `packages/ai-universal-rules/templates/core/agents/{architecture-plan-writer,release-auditor,researcher}.md`, `tools/ai/generate-agent-snippets.php`, `tools/ai/install/script-registry.php` — leave all untouched.
- Do NOT hand-edit `docs/ai/catalog.md` (generated, "DO NOT EDIT" header).
- Do NOT edit `AGENTS.md`, `CLAUDE.md`, or their templates in `packages/ai-universal-rules/templates/core/` in this pass (both are kit-managed/generated; `AGENTS.md` already points to `docs/ai/execution-protocol.md`).
- Do NOT invent a status vocabulary that conflicts with `verified`/`partially-verified`/`not-verified`/`failed-verification` or the existing "Blocked by unknown:" block in `docs/ai/project-context.md` section 10.
- Do NOT implement the declarative invariant gate, failure-to-repair-prompt bundle, or CI shape alignment ideas — explicitly deferred.
- Do NOT duplicate `docs/ai/capabilities/README.md`'s per-capability list into the new index — link out only.
- Do NOT exceed a soft ceiling of roughly 160 lines for `docs/ai/index.md` (no explicit line-budget row exists for this file type; treated as `unknown`, mitigated by targeting size comparable to sibling root docs).

## Affected Paths

- `docs/ai/execution-protocol.md` (edit — insert one new section)
- `docs/ai/index.md` (new file)
- `docs/ai/workflow.md` (edit — add exactly one pointer line/bullet)

## Contracts And Boundaries

- Terminal signal vocabulary is a closed set: `verified`, `partially-verified`, `not-verified`, `failed-verification` (existing, unified — not redefined), plus `blocked: <UNKNOWN>` (existing, references `docs/ai/project-context.md` section 10's "Blocked by unknown:" block by pointer, not duplicated), plus two new tokens `done` and `error: <one-line reason>`.
- `docs/ai/index.md` must carry no "GENERATED — DO NOT EDIT" header (it is hand-authored) and must explicitly distinguish itself from `docs/ai/catalog.md` in its opening paragraph.
- `docs/ai/index.md`'s "## Capabilities" section must be a single link-out line to `docs/ai/capabilities/README.md`, not a re-listing of the 17 capability folders.
- No adapter file (`AGENTS.md`, `CLAUDE.md`, `.opencode/**`, `.github/**`) is touched in this pass.

## Todo Plan

- [x] P0: Edit `docs/ai/execution-protocol.md` — insert a `## Terminal Signal Line` section (exact heading) between the existing `## Verification Statuses` section and `## Related Docs`, documenting the exact `signal: <token>` terminal-line format and a table of all 7 tokens (`verified`, `partially-verified`, `not-verified`, `failed-verification`, `blocked: <UNKNOWN>`, `done`, `error: <one-line reason>`) with their source (existing/new) and meaning, referencing (not duplicating) the "Blocked by unknown:" block in `docs/ai/project-context.md` section 10.
- [x] P0: Create `docs/ai/index.md` — new curated, hand-authored file with an opening paragraph distinguishing it from `docs/ai/catalog.md`, followed by the 9 section headers in order: `## Start Here`, `## Source Of Truth & Contracts`, `## Execution, Verification & Failure Handling`, `## Capabilities`, `## Generated Artifacts & Catalog`, `## Agents & Operations`, `## Project Setup & Install`, `## Security & Guardrails`, `## Reference`. Each entry is one line: path + one-line "why it matters" annotation. `## Capabilities` contains only a single link-out line to `docs/ai/capabilities/README.md`.
- [x] P1: Edit `docs/ai/workflow.md` — add exactly one pointer line/bullet referencing `docs/ai/index.md`; no other content in that file changes.
- [x] P1: Run `git status --short` after all edits and confirm only `docs/ai/execution-protocol.md`, `docs/ai/index.md`, `docs/ai/workflow.md`, plus this ticket plan file, changed — no stack/permission files or other pre-existing dirty files touched. **DONE** (verified at commit time: this ticket's own commit touches exactly these 3 docs files plus the ticket plan; unrelated pre-existing dirty files were committed separately as their own isolated commits per user decision, not bundled into this one).
- [x] P2: Run the repo's configured markdown lint (e.g. `markdownlint-cli2`) on the 3 edited/created files if available; otherwise manually re-read each for heading/list correctness. **DONE** (`npx markdownlint-cli2 docs/ai/execution-protocol.md docs/ai/index.md docs/ai/workflow.md` → 0 errors).

## Acceptance Criteria

- [x] AC-01: `docs/ai/execution-protocol.md` contains a new `## Terminal Signal Line` section (exact heading) located between `## Verification Statuses` and `## Related Docs`, listing all 7 tokens (`verified`, `partially-verified`, `not-verified`, `failed-verification`, `blocked`, `done`, `error`) with the exact `signal: <token>` line format documented. **DONE** (confirmed by heading grep: line 18 Verification Statuses, line 25 Terminal Signal Line, line 40 Related Docs).
- [x] AC-02: `docs/ai/index.md` exists, contains no "GENERATED — DO NOT EDIT" header, has an opening paragraph distinguishing it from `docs/ai/catalog.md`, contains all 9 section headers listed above in order, and every referenced path resolves to an existing file in the repo. **DONE** (all 51 referenced `docs/ai/*` paths confirmed present via direct file check; no generated header).
- [x] AC-03: `docs/ai/index.md`'s `## Capabilities` section contains only a single link-out line to `docs/ai/capabilities/README.md` (no per-capability re-listing). **DONE**.
- [x] AC-04: `docs/ai/workflow.md` contains exactly one new line/bullet pointing to `docs/ai/index.md`; a diff of that file shows no other content changed. **DONE** (`git diff` shows exactly one added line).
- [x] AC-05: `git status --short` after implementation shows changes only to `docs/ai/execution-protocol.md`, `docs/ai/index.md` (new), `docs/ai/workflow.md`, plus this ticket plan file. No path listed under Out Of Scope is touched. **DONE** (this ticket's own commit is scoped to exactly these files; other pre-existing dirty files landed as separate isolated commits, not bundled here).
- [x] AC-06: `docs/ai/index.md` stays within roughly 160 lines (soft ceiling, since no explicit line-budget row exists in `docs/ai/ai-file-standards.md` for this file type). **DONE** (90 lines).

## Verification Plan

- AC-01: `preview-file.sh docs/ai/execution-protocol.md --around <line of new section>` or direct read — confirm heading text, position, and all 7 tokens with the `signal: <token>` format documented; diff the file to confirm no other content changed.
- AC-02: Read `docs/ai/index.md` in full; confirm absence of a generated-file header; confirm all 9 section headings appear in the specified order; for each listed path, run `ls <path>` (or `git ls-files <path>`) to confirm it resolves to a real file.
- AC-03: Read the `## Capabilities` section of `docs/ai/index.md`; confirm it is exactly one link-out line to `docs/ai/capabilities/README.md`.
- AC-04: `git diff docs/ai/workflow.md` — confirm exactly one added line/bullet and no other hunks.
- AC-05: `git status --short` — confirm the change set matches exactly the 3 files above plus this plan file, and cross-check against the Out Of Scope path list.
- AC-06: `wc -l docs/ai/index.md` — confirm line count is at or near the ~160-line soft ceiling; if exceeded, flag as a deviation requiring follow-up rather than silently accepting it.

## Risks And Rollback

- Risk level: Low — docs-only, additive, no behavior/schema/runtime change, no adapter edits, no generated-file edits.
- Rollback: all three changes are additive/small-diff docs edits; revert via `git checkout -- docs/ai/execution-protocol.md docs/ai/index.md docs/ai/workflow.md` (or delete the new `docs/ai/index.md` and revert the other two files) with no other system impact.
- Approval boundary: docs-only, additive, no secrets/destructive/auth/billing surface — stays within the safe-default zone per `docs/ai/approval-boundaries.md`; no special approval required beyond the normal workflow.

## Handoff Notes

- Unknown: exact line-budget ceiling for a new plain `docs/ai/*.md` file / curated index file — no row exists in `docs/ai/ai-file-standards.md`; mitigated by targeting size comparable to sibling root docs (`workflow.md` ~34 lines, `execution-protocol.md` 87 lines) — this is an inferred, not evidence-backed, constraint.
- Unknown (deferred, out of this slice): whether `docs/ai/index.md` should eventually be added to `docs/ai/ai-file-standards.md`'s line-budget table as its own primitive.
- Sequencing note from architect: implement lowest-friction file first — step 1 (`docs/ai/execution-protocol.md`, single-section edit), then step 2 (new `docs/ai/index.md`), then step 3 (`docs/ai/workflow.md` pointer).
- Recommended next step: implementer means implementer agent handoff using OpenCode command: `/implement-slice`.
