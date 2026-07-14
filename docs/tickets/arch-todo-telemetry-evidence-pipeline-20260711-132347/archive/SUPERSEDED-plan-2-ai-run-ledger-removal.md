> **⛔ SUPERSEDED 2026-07-12 — READ FIRST.** The model-side ledger removal is done, but
> the approval-gated PHP artifact deletion below never landed (`schemas/ai/ai-run-ledger.schema.json`
> and its `ai_catalog_lib.php`/`packs.php`/`schema-ownership.md`/`repo.runtime.yaml`
> registrations still exist as of 2026-07-12; `generated/run_ledger.mmd` is already gone).
> The remaining live work was folded into **`plan-10-...` Phase 6** (this ticket folder). Do
> not action this file directly — use plan-10 Phase 6, which carries the current verified
> state and the `🛑⁉️` delete-approval gate.

# Architecture Plan — Remove `ai-run-ledger` from the project

- Ticket: none (self-directed; same dedicated ticket as plan-1)
- Source: user decision 2026-07-11 to de-link and remove the run-ledger
- Generated: 2026-07-11
- Current branch: `fix/opencode-agent-body-parity`
- Plan file: `docs/tickets/arch-todo-telemetry-evidence-pipeline-20260711-132347/plan-2-ai-run-ledger-removal.md`

> **⚠ BRANCH-SCOPE MISMATCH — READ FIRST:** Current branch
> `fix/opencode-agent-body-parity` is unrelated. Cut a dedicated branch before
> executing any deletion in this plan.

> **⚠ APPROVAL-GATED DELETIONS — READ FIRST:** This plan removes real committed
> files (a schema + its registrations). Per `docs/ai/approval-boundaries.md`
> ("File Rename And Delete Policy"), every file deletion must be preceded by the
> `🛑⁉️` attention marker, the exact path, and the concrete reason, then explicit
> human confirmation. Do NOT delete any file in the "Real artifacts" list without
> that per-file approval step. This plan documents the removal; it does not
> pre-authorize it.

> **Completion instruction:** When every `## Todo Plan` item and every
> `## Acceptance Criteria` item is checked `[x]`, rename this file to
> `DONE-plan-2-ai-run-ledger-removal.md` and move it into `archive/` under this
> ticket folder.

## Context

The `ai-run-ledger` (`ai-run-ledger.v2`) was a planned per-run rollup over the
per-event evidence log (`.ai-logs/tool-usage.jsonl`). Investigation established:

- Only **Slice A** (the schema `schemas/ai/ai-run-ledger.schema.json`) was ever
  built. The rollup renderer (Slice B) and run-close wiring (Slices C–E) do not
  exist.
- A cross-runtime run-ledger depends on reliably knowing when a run ended.
  Only Claude Code has a deterministic run-end hook; Copilot has none and OpenCode
  needs an unbuilt plugin. The fragile, unreliable part of the ledger is exactly
  this run-close inference.
- The per-event log (built, reliable, append-only) already answers the raw-facts
  questions. The ledger only added a per-run *summary/interpretation* whose honest
  value was low and whose fragility was high.

Decision: **remove the run-ledger from the project.** The per-event log stays.

## Problem

The run-ledger is referenced across design models, a real committed schema, and
several registration surfaces. Removal must be complete and must not (a) break the
generated-catalog contract, (b) delete unrelated work that merely shares the
`ai-run-ledger-rollup-slice-a` branch name, or (c) leave dangling model references.

## Target Outcome

The run-ledger concept and its schema are removed from the project, the
generated catalog/docs are regenerated consistently, and no dangling references or
stale generated artifacts remain — while the per-event evidence log and all
unrelated permission-budget work are left untouched.

## In Scope

- Removing the built schema and its four registration surfaces.
- Regenerating the generated catalog + docs that reference it.
- Deleting the stale generated telemetry diagram `run_ledger.mmd`.
- Archiving the superseded owner plan `arch-todo-ai-run-ledger-rollup-...`.

## Out Of Scope (Things To Avoid)

- Do **not** touch `.ai-logs/tool-usage.jsonl`, `scripts/ai/internal/lib/30-logging.sh`,
  or `schemas/ai/evidence-event.schema.json` — the per-event log stays.
- Do **not** delete or edit the permission-layer comments in
  `tools/ai/install/permission-layers/*.php` or the ticket
  `docs/tickets/ai-run-ledger-rollup-slice-a/` — these are governance/permission
  work that only shares the branch name; they are NOT ledger functionality.
- Do **not** hand-edit generated files (`packages/ai-universal-rules/catalog.json`,
  `docs/ai/catalog.md`); regenerate them from their source map.
- Do **not** delete any file without the `🛑⁉️` per-file approval step.

## Affected Paths

Real committed artifacts (removal / regeneration — approval-gated):

- `schemas/ai/ai-run-ledger.schema.json` — DELETE (the built Slice A schema).
- `tools/ai/ai_catalog_lib.php` (line ~448) — remove the schema's source-map entry.
- `tools/ai/install/packs.php` (line ~221) — remove the install-pack file entry.
- `docs/ai/schema-ownership.md` (line ~26) — remove the ownership-contract row.
- `packages/ai-universal-rules/catalog.json` (lines ~3663–3664) — REGENERATED, not
  hand-edited (via `php tools/ai/generate-ai-catalog.php`).
- `docs/ai/catalog.md` (line ~351) — REGENERATED alongside the above.

Design models (already de-linked 2026-07-11, no further deletion needed):

- `___ARCHITECTURE_2.0/telemetry/model/telemetry.yaml` — machine 7 `run_ledger`
  removed; 9B/10 re-pointed at settled accepted-event truth; validated
  (`gen_mermaid.sh --check` → 11 machines, 38 cross-links); `.mmd` regenerated.
- `___ARCHITECTURE_2.0/internal_architecture/lifecycle-model/lifecycle/model/repo.runtime.yaml`
  — `PO_LEDGER` node annotated PLANNED-FOR-REMOVAL (kept until the schema file is
  actually deleted, so the lifecycle model stays accurate to what exists today).

Stale generated artifact:

- `___ARCHITECTURE_2.0/telemetry/generated/run_ledger.mmd` — DELETE (orphan; the
  generator no longer produces it after the model edit).

Ticket docs:

- `docs/tickets/arch-todo-ai-run-ledger-rollup-20260708-000000/plan.md` — archive
  as superseded (do not silently delete the record).

## Contracts And Boundaries

- The schema is registered via the schema-ownership recipe; removal must reverse the
  same four surfaces the addition touched (source map, pack, ownership doc,
  generated catalog+docs).
- `packages/ai-universal-rules/catalog.json` and `docs/ai/catalog.md` are generated
  from `tools/ai/ai_catalog_lib.php`; only the source map is hand-edited.
- `.ai/catalog.json` is a full install-time mirror and was already stale/unsynced
  before this work (documented in the owner plan); do not pull unrelated drift into
  this removal — flag it as a separate approved refresh if it must sync.

## Todo Plan

### P0 — Preconditions

- [ ] Cut a dedicated branch for the removal.
- [ ] Confirm the per-event log surfaces (`30-logging.sh`,
      `evidence-event.schema.json`, `.ai-logs/tool-usage.jsonl`) are explicitly
      out of scope and untouched.
- [ ] Confirm no unbuilt renderer references the schema (grep `run-ledger.php` —
      already verified absent).

### P1 — Remove registrations (source surfaces)

- [ ] Remove the `ai-run-ledger.schema.json` entry from `tools/ai/ai_catalog_lib.php`.
- [ ] Remove the `ai-run-ledger.schema.json` file entry from
      `tools/ai/install/packs.php`.
- [ ] Remove the `ai-run-ledger.schema.json` row from `docs/ai/schema-ownership.md`.

### P2 — Delete the schema file (🛑⁉️ approval-gated)

- [ ] Obtain explicit per-file deletion approval, then delete
      `schemas/ai/ai-run-ledger.schema.json`.
- [ ] Obtain approval, then delete the stale
      `___ARCHITECTURE_2.0/telemetry/generated/run_ledger.mmd`.

### P3 — Regenerate + reconcile

- [ ] Run `php tools/ai/generate-ai-catalog.php` to regenerate
      `packages/ai-universal-rules/catalog.json` and `docs/ai/catalog.md` without
      the ledger entry.
- [ ] Update `repo.runtime.yaml`: remove the `PO_LEDGER` node + its
      `PO_DURABLE → PO_LEDGER` edge + the schema provenance line, now that the file
      is gone.
- [ ] Archive `docs/tickets/arch-todo-ai-run-ledger-rollup-20260708-000000/plan.md`
      as superseded (tombstone pointing to this removal plan).

### P4 — Verify

- [ ] `php tools/ai/validate-schemas.php` passes (schema count decreases by 1).
- [ ] `php tools/ai/validate-ai-catalog.php` passes.
- [ ] `php tools/ai/validate-generated-artifacts.php` passes.
- [ ] `bash ___ARCHITECTURE_2.0/telemetry/scripts/gen_mermaid.sh --check` passes.
- [ ] Repo-wide search for `ai-run-ledger` returns only intentional references
      (permission-layer comments + the slice-a permission ticket, both out of
      scope) and this removal plan.

## Acceptance Criteria

- [ ] AC-01: `schemas/ai/ai-run-ledger.schema.json` no longer exists and no source
      surface (`ai_catalog_lib.php`, `packs.php`, `schema-ownership.md`) references it.
- [ ] AC-02: Regenerated `packages/ai-universal-rules/catalog.json` and
      `docs/ai/catalog.md` contain no `ai-run-ledger` entry, and generated-artifact
      validation passes.
- [ ] AC-03: `gen_mermaid.sh --check` passes for the telemetry model with no
      `run_ledger` machine, and `generated/run_ledger.mmd` is gone.
- [ ] AC-04: The per-event log surfaces are byte-for-byte untouched by this change.
- [ ] AC-05: No permission-layer comment or the `ai-run-ledger-rollup-slice-a`
      permission ticket was modified.

## Verification Plan

- Schema/catalog/artifact validators listed in P4.
- `gen_mermaid.sh --check` for the model.
- `git diff --stat` review to confirm only intended files changed and the per-event
  log + permission work are absent from the diff.

## Risks And Rollback

- **False-positive deletion risk:** several `ai-run-ledger` hits are permission-budget
  governance work sharing the branch name. Mitigation: explicit out-of-scope list;
  AC-05 asserts they are untouched.
- **Generated-file drift:** hand-editing the generated catalog instead of
  regenerating would desync it. Mitigation: P3 regenerates from the source map only.
- **Rollback:** the schema is additive and regenerable; restoring it = revert the
  P1–P3 commits. No data loss (the ledger never had a renderer or a data sink).

## Handoff Notes

- The telemetry-model de-linking (plan-1 scope) is DONE and verified this session;
  this plan-2 covers only the remaining real-artifact removal.
- implementer means implementer agent handoff for P1–P4 on a dedicated branch, with
  the `🛑⁉️` approval step enforced at P2.
- reviewer means reviewer agent handoff using OpenCode command: `/review-diff`,
  once the removal diff exists.
