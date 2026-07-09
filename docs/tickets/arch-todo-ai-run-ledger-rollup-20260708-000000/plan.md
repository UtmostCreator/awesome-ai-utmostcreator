# Architecture Plan — AI Run Ledger (ai-run-ledger.v2): per-run rollup over the v3.0 per-event log

- Ticket: none (self-directed; new dedicated ticket)
- Source: architect design handoff (AI Run Ledger rollup layer + per-runtime run-close)
- Generated: 2026-07-08T00:00:00Z
- Current branch: `claude-agent-fleet-remediation`
- Plan file: docs/tickets/arch-todo-ai-run-ledger-rollup-20260708-000000/plan.md

> **⚠ BRANCH-SCOPE MISMATCH — READ FIRST:** This plan is authored while the current git
> branch is `claude-agent-fleet-remediation`, but this work is **UNRELATED to that
> branch's scope**. The AI Run Ledger rollup is NOT part of the claude-agent-fleet
> remediation effort. It was given its own dedicated ticket folder
> (`arch-todo-ai-run-ledger-rollup-20260708-000000`) deliberately, not placed under the
> current branch's folder. **Do the implementation on its own branch/ticket**, not on
> `claude-agent-fleet-remediation`. If you are on that branch when you pick this up,
> stop and cut a dedicated branch first.

> **Completion instruction:** When every `## Todo Plan` item and every
> `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan.md`
> and move it into `archive/` under this ticket folder
> (`docs/tickets/arch-todo-ai-run-ledger-rollup-20260708-000000/archive/DONE-plan.md`).
> See "Archive On Completion" below for the exact steps. Note: **Slice A is the smallest
> safe first step and the only slice a first implementer handoff should touch.** Slices
> B–E follow the dependency order and must each be confirmed in scope before implementation.

## Context

The **per-run** AI Run Ledger (`ai-run-ledger.v2`) is a **ROLLUP / derived projection**
over the already-landed **per-event** v3.0 log — it is **NOT a greenfield rewrite**. The
per-event log is the immutable source: `schemas/ai/evidence-event.schema.json`, the emitter
`scripts/ai/internal/lib/30-logging.sh`, and the sink `.ai-logs/tool-usage.jsonl`. The ledger
groups those events by `session_id` / `trace_id` and folds each group into one per-run record.

This plan **EXTENDS two existing plans and must not duplicate them**:

- `docs/tickets/arch-todo-runner-agnostic-logging-core-20260706/plan.md` (Slices 2–5).
- `v0.6-plan/arch-todo-v0-6-shipped-surface-program-20260704-000001/plan.md` (Phase 7 /
  Track O: 7.1 OpenCode audit plugin, 7.2 native opencode stats, 7.3 unified field contract,
  7.5 provenance).

### Evidence-Corrected Baseline

- Per-event v3.0 emitter **already landed** (`event_id` / `sequence` / `severity` / `_status`
  at `30-logging.sh:130-169`) — logging-core **Slice 1 DONE**; do **not** re-plan it.
- `AI_SESSION_DURABLE_LOG` toggle **already exists** (`30-logging.sh:84`) = ready
  disable/rollback seam for durable-sink work.
- `tools/ai/agent-log.php` is **MISSING** (referenced `30-logging.sh:86-87`) = logging-core
  **Slice 5 dead path**.
- `post-tool-use.sh` still emits `"1.1"` (`post-tool-use.sh:112`) vs core `"3.0"` =
  logging-core **Slice 4**.
- Rollup keys `SESSION_ID` / `TRACE_ID` / `TASK_ID` come from `40-session.sh:12-20`; the sink
  is `AI_EVENT_LOG=.ai-logs/tool-usage.jsonl` (`00-env.sh:17,21`).
- Runtime hooks:
  - **Copilot** — `preToolUse` / `postToolUse` only, **NO run-end hook** (cannot close a run
    deterministically).
  - **OpenCode** — advisory; lifecycle only via plugin (Phase 7.1, **unbuilt**).
  - **Claude** — `.claude/settings.json` has only 2 graphify `PreToolUse` hooks;
    `Stop` / `SessionEnd` / `PostToolUse` are **UNUSED** (the cleanest deterministic close).
- **Enforcement-honesty invariant** = `docs/ai/security.md` invariant 6 (never claim
  "enforced" without proven hook invocation).

## Problem

There is a rich per-event log but no per-run view: no single record that answers "what happened
during this run/session" (which tools ran, what changed, verification status, provenance). The
missing piece is a **derived rollup** plus a **per-runtime way to know a run has ended** so the
rollup can close a record — and the runtimes differ sharply in how deterministically a run can
be closed (Claude deterministic; OpenCode via an unbuilt plugin; Copilot has no run-end hook at
all). The rollup must be built without fabricating fields the runtimes cannot actually source, and
without duplicating the two owner plans it extends.

## Target Outcome

- One additive, all-blocks-optional schema `ai-run-ledger.schema.json` (`schema_version "2.0"`),
  registered through the schema-ownership recipe.
- A read-only rollup renderer that folds `.ai-logs/tool-usage.jsonl` events into
  `ai-run-ledger.v2` records in `.ai-logs/run-ledger.jsonl`, populating only truly-sourced fields
  and nulling the rest per the field-source honesty matrix.
- Per-runtime run-close: deterministic for Claude (Stop/SessionEnd hook), plugin-marker for
  OpenCode (extends Phase 7.1), and an explicitly-labelled **inferred** synthetic close for
  Copilot (documented limitation, no deterministic claim — `security.md` invariant 6).

## In Scope

- Additive `ai-run-ledger.v2` schema + its registration in the four owner surfaces.
- A pure, read-only run-rollup renderer (`tools/ai/run-ledger.php` or `ai-log report --run`).
- Optional durable-sink reconciliation (behind the existing `AI_SESSION_DURABLE_LOG` toggle) OR
  on-demand deferral via that toggle.
- Consuming (not re-implementing) logging-core Slice 4 (Copilot event shape) and Slice 2
  (runner/model block) as cross-plan prerequisites.
- Per-runtime run-close wiring: Claude deterministic hook, OpenCode plugin marker (extends 7.1),
  Copilot documented-limitation + inferred synthetic fallback.

## Out Of Scope (Things To Avoid)

- **Fabricating** cost / tokens / credits / model / adherence / evals / turns /
  improvement_actions — these are **nullable/unknown only** across runtimes.
- **Re-planning logging-core Slice 1** (already landed) or **duplicating v0.6 Phase 7** items —
  reference / extend them, do not re-implement.
- **Claiming Copilot deterministic run-close** — Copilot has no run-end hook; document the
  limitation and use an inferred synthetic close (`security.md` invariant 6).
- **Editing generated files** or hand-editing rendered adapters / catalog copies where a
  generator owns them.
- **Storing inline prompts / diffs / secrets** — hashes + redacted previews + artifact refs only.
- **Adding new redaction / token / ID machinery** — reuse existing seams (`10-json`, `80-tokens`,
  `40-session`).

## Affected Paths

- `schemas/ai/ai-run-ledger.schema.json` (new — Slice A)
- `packages/ai-universal-rules/catalog.json`, `.ai/catalog.json`,
  `tools/ai/install/packs.php`, `docs/ai/schema-ownership.md` (edit — Slice A registration)
- `tools/ai/run-ledger.php` **or** the `ai-log report --run <session_id>` command (new — Slice B)
- `.ai-logs/run-ledger.jsonl` (rollup output sink — Slice B, runtime-generated, not committed)
- `tools/ai/agent-log.php` (new, minimal durable sink — Slice C; closes logging-core Slice 5 dead path)
- `scripts/ai/internal/**/post-tool-use.sh` + its tests (cross-plan prerequisite via logging-core
  Slice 4 — Slice D; do NOT re-implement here, ensure it lands)
- `.claude/settings.json` template (add Stop/SessionEnd hook — Slice E)
- OpenCode Phase 7.1 audit plugin (extend to emit run-close marker — Slice E)

## Contracts And Boundaries

- **Rollup is a pure projection.** It reads the immutable per-event log and never mutates it; the
  ledger is fully **regenerable** from `.ai-logs/tool-usage.jsonl`.
- **Grouping key:** events fold by `session_id` / `trace_id` (from `40-session.sh:12-20`).
- **Field-source honesty:** populate a ledger block only when a real source exists for the current
  runtime; otherwise the leaf is `null`. See the Field-Source Honesty Matrix below — this is a hard
  contract, not a suggestion.
- **Schema additivity:** every block is **OPTIONAL** (none in `required[]`), each block is
  `additionalProperties:false`, no-source leaves are nullable. Old and partial records both validate.
- **Ledger block names MUST match the v0.6 Phase 7.3 unified field contract** — do not invent a
  divergent naming scheme.
- **Run-close labelling:** Claude close is labelled deterministic; OpenCode close comes from the
  plugin marker; Copilot close is labelled `run_close: inferred` (NOT deterministic).

## Ownership Reconciliation

Include verbatim — this table is the anti-duplication contract with the two owner plans:

| Piece | Owner plan | This plan's action |
|---|---|---|
| v3.0 per-event emitter | logging-core Slice 1 | DONE — consume, don't touch |
| Lazy session/trace seed + runner{} section | logging-core Slice 2 | dependency; ledger runner/model block reads it |
| Span integration in run_guarded | logging-core Slice 3 | optional enrichment for tool_calls[] latency |
| post-tool-use.sh → v3.0 reconciliation | logging-core Slice 4 | prerequisite for Copilot ledger completeness |
| agent-log.php durable sink + ai-log CLI | logging-core Slice 5 | this plan builds/extends it to also emit the ledger; or defers with the toggle |
| OpenCode audit plugin (lifecycle) | v0.6 Phase 7.1 | this plan extends 7.1 to emit a run-close marker feeding the rollup |
| Unified field contract / provenance | v0.6 Phase 7.3 / 7.5 | ledger block names MUST match the 7.3 contract |

## Field-Source Honesty Matrix

Include verbatim. **Only `tool_calls[]`, `diff`, and `privacy` are fully sourced across all
runtimes; everything else is nullable/partial.** Fabrication of any `null` cell is forbidden.

| Ledger block | Real source today | Copilot | OpenCode | Claude |
|---|---|---|---|---|
| run lifecycle | events + run-close signal | inferred | plugin lifecycle (7.1) | Stop/SessionEnd (deterministic) |
| model id | cost.model nullable / 7.5 | null | opencode stats/plugin (7.2) | null unless provided |
| cost/tokens/credits | none | null | opencode stats (7.2; turn-not-command) | null |
| context | events | partial | partial | partial |
| instructions adherence | none | null | null | null |
| turns[] | none | null | plugin turn events (7.1) | null |
| tool_calls[] | events (real) | yes | yes | yes |
| diff | artifact path+sha256 (Slice 5) | yes | yes | yes |
| verification | partial (test events) | partial | partial | partial |
| quality | derived | partial | partial | partial |
| evals | none | null | null | null |
| failure_taxonomy | partial (failure.*) | partial | partial | partial |
| improvement_actions | none | null | null | null |
| privacy | real (redaction seam) | yes | yes | yes |

Note: only `tool_calls[]`, `diff`, `privacy` fully sourced across all runtimes; everything else
nullable/partial.

## Todo Plan

Spine: **A → B → (C ∥ D) → E**. Slice A is the smallest safe first step. Use unchecked Markdown
tasks only.

### Slice A — Schema + registration (LOW risk) — smallest safe first step

- [x] P0: Add `schemas/ai/ai-run-ledger.schema.json` with `schema_version "2.0"`. ALL blocks from
  the Field-Source Honesty Matrix are present, but **every block is OPTIONAL** (none in
  `required[]`). Each block is `additionalProperties:false`; leaves for no-source fields are
  nullable.
- [x] P0: Register the schema per the schema-ownership recipe in three of the four surfaces:
  `tools/ai/ai_catalog_lib.php` (hand-edited source map — `packages/ai-universal-rules/catalog.json`
  and `docs/ai/catalog.md` are **generated** from it and were regenerated via
  `php tools/ai/generate-ai-catalog.php`, not hand-edited), `tools/ai/install/packs.php`
  (`policy-pack`, `required: false`, mirrors `evidence-event.schema.json`'s `skip-if-exists`), and
  `docs/ai/schema-ownership.md` (`public-doc-contract` row — no runtime producer/consumer until
  Slice B ships the renderer).
  - **`.ai/catalog.json` NOT synced — flagged, not silently skipped.** It is a full
    install-time mirror of `packages/ai-universal-rules/catalog.json` (`packs.php:385,462`,
    `merge_strategy: replace`). It was **already stale before this slice** (missing ~150 lines:
    new Claude agents, `install` command/workflow entries, etc. from the unrelated
    `claude-agent-fleet-remediation` branch work already dirty in this worktree). A full sync
    would pull that entire unrelated drift into this bounded slice's diff. Needs an explicit,
    separately-approved full self-install refresh, not a Slice-A hand-edit.
- [x] P0: Ensure ledger block names match the v0.6 Phase 7.3 unified field contract at the
  block/array level (`run`, `tool_calls[]`, `diff`, `verification`, `failure_taxonomy[]`, `privacy`,
  etc. — verbatim from the plan's own Field-Source Honesty Matrix). Note: leaf-level field names
  inside `tool_calls[]` follow the user's original `ai-run-ledger.v2` shape (`tool_name`,
  `exit_code`, `authorization_decision`) rather than the nested `evidence-event.schema.json` v3.0
  leaf shape (`tool.name`, `execution.exit_code`, `authorization.decision`); flagged for reviewer
  attention if stricter leaf-level Phase 7.3 alignment is wanted before Slice B builds the renderer
  that bridges the two.
- [x] P0: Verify Slice A (see Verification Plan) — see caveats below; `ai-doc-check.sh --check`
  fails on a pre-existing, unrelated `validate-catalog-drift` finding plus one drift line this
  slice's `packs.php` addition contributes (not yet regenerated — same generated-file/out-of-scope
  reasoning as `.ai/catalog.json` above).

### Slice B — Run-rollup renderer (LOW-MED risk) — depends on A

- [ ] P0: Build a **read-only** PHP tool (`tools/ai/run-ledger.php` or `ai-log report --run
  <session_id>`) that reads `.ai-logs/tool-usage.jsonl`, groups events by `session_id` /
  `trace_id`, and folds each group into one `ai-run-ledger.v2` record applying the field-source
  matrix (populate only sourced fields; else `null`).
- [ ] P0: Write records to `.ai-logs/run-ledger.jsonl`. **Pure projection — no new capture.**
- [ ] P0: Verify Slice B (see Verification Plan).

### Slice C — Durable sink reconciliation (MED risk, needs reviewer) — parallel with D after B

- [ ] P1: Build a minimal **append-only** `tools/ai/agent-log.php` (closes logging-core Slice 5
  dead path), gated by the existing `AI_SESSION_DURABLE_LOG` toggle (default **keep OFF** until
  proven). Store diffs as an **artifact path + sha256**, never inline. OR defer with the toggle and
  run Slice B on-demand.
- [ ] P1: Verify Slice C (see Verification Plan).

### Slice D — Copilot reconciliation dependency (MED risk, needs reviewer) — parallel with C after B

- [ ] P1: Ensure **logging-core Slice 4** (`post-tool-use.sh` → v3.0) lands so Copilot events match
  the rollup shape. **Do NOT re-implement Slice 4 here** — ensure it lands (cross-plan prerequisite).
- [ ] P1: Because Copilot has NO run-end hook, **document it as a limitation**; implement the
  synthetic run-close fallback: the rollup treats the last event per `session_id`
  (idle-timeout / next-session boundary) as run-close, labelled `run_close: inferred` (NOT
  deterministic; `security.md` invariant 6).
- [ ] P1: Verify Slice D (see Verification Plan).

### Slice E — Deterministic run-close wiring (MED-HIGH risk, needs release-auditor) — depends on B, benefits from C

- [ ] P2: **Claude** — add a `Stop` / `SessionEnd` hook to the `.claude/settings.json` template that
  invokes the rollup for the ending session (deterministic close).
- [ ] P2: **OpenCode** — extend the v0.6 Phase 7.1 audit plugin to emit a `session.idle` /
  `session.error` run-close marker feeding the rollup.
- [ ] P2: **Copilot** — documented limitation + synthetic inferred fallback (carried over from
  Slice D).
- [ ] P2: Verify Slice E (see Verification Plan).

## Acceptance Criteria

Slice A ACs are checkable now; later-slice ACs become checkable when their slice is implemented.

### Slice A

- [x] AC-01: `schemas/ai/ai-run-ledger.schema.json` exists with `schema_version "2.0"`, every block
  present, every block OPTIONAL (none in `required[]`), each `additionalProperties:false`, no-source
  leaves nullable. Verified: `validate-schemas.php` reports "20 schema(s) under schemas/ai are
  well-formed and addressable" (was 19).
- [ ] AC-02: **Partially met.** The schema is registered in `tools/ai/ai_catalog_lib.php`
  (source map), `packages/ai-universal-rules/catalog.json` + `docs/ai/catalog.md` (regenerated via
  `php tools/ai/generate-ai-catalog.php`), and `tools/ai/install/packs.php`.
  **`.ai/catalog.json` is NOT synced** — blocked by pre-existing unrelated drift on this branch
  (see Slice A Todo notes above). Unchecked until that sync happens as its own approved step.
- [ ] AC-03: **Partially met.** `validate-schemas.php` ✅, `validate-ai-catalog.php` ✅ ("AI catalog
  metadata validation passed"), `validate-ai-config.php` ✅ (passes with one pre-existing unrelated
  warning: `unexpected stack term 'Nuxt' in README.md`), `validate-generated-artifacts.php` ✅.
  `bash scripts/ai/ai-doc-check.sh --check` **fails** on its `validate-catalog-drift` sub-check
  (`docs/ai/generated/install-catalog.json`, `docs/ai/generated/install-catalog.md`,
  `packages/ai-universal-rules/docs/INSTALL-CATALOG.md`) — confirmed by inspection to be caused by
  (a) pre-existing unrelated drift already dirty in this worktree before this slice
  (`adapter-copilot`/`adapter-claude` item counts, from the `claude-agent-fleet-remediation` branch
  work) plus (b) this slice's own `policy-pack` item-count change (3→4), not yet regenerated.
  Fixing (a) requires `php tools/ai/ai.php install-docs --write`, which is a generated-file
  regeneration that would also resolve unrelated drift outside this slice's scope — flagged for the
  user/reviewer rather than run silently.  `composer test:fast` — 929 tests, 3 pre-existing failures
  (`AdapterRenderDriftTest` x2, `AgentPermissionDriftTest` x1), all touching `.claude/agents/*`,
  `.github/agents/*.agent.md`, `.opencode/agents-optional/build-config.md` — files already modified
  before this slice started (confirmed via `git status --short` at session start); this slice
  introduced zero new test failures.

### Slice B

- [ ] AC-04: Given a fixture with **2 sessions**, the renderer produces **2 ledger records** with
  correct grouping; nullable fields are `null`, `tool_calls[]` is populated, and each record
  validates against the schema. `composer test:fast` is green.

### Slice C

- [ ] AC-05: The durable sink round-trips; diffs are stored as artifact path + sha256 (asserted);
  with `AI_SESSION_DURABLE_LOG` off there is **no write**. `composer test` is green.

### Slice D

- [ ] AC-06: `test-post-tool-use.sh` and `tests/shell/post-tool-use.bats` are green with `"3.0"`;
  the Copilot ledger record shows `run_close: inferred`.

### Slice E

- [ ] AC-07: The Claude `Stop` hook fires the rollup on a fixture session (record labelled
  deterministic); the OpenCode plugin emits a run-close marker; `validate-adapter-drift.php
  --fail-on-warn` and `composer test` are green.

## Verification Plan

- **Slice A (proves AC-01..AC-03):** `php tools/ai/validate-schemas.php`,
  `php tools/ai/validate-ai-catalog.php`, `php tools/ai/validate-ai-config.php`,
  `php tools/ai/validate-generated-artifacts.php`, `bash scripts/ai/ai-doc-check.sh --check`.
- **Slice B (proves AC-04):** run the renderer against a 2-session fixture of
  `.ai-logs/tool-usage.jsonl`; assert 2 grouped records, nullable fields null, `tool_calls[]`
  populated, each record schema-valid; `composer test:fast`.
- **Slice C (proves AC-05):** sink round-trip test + artifact path/sha256 assertion + toggle-off =
  no-write assertion; `composer test`.
- **Slice D (proves AC-06):** `test-post-tool-use.sh` + `tests/shell/post-tool-use.bats` green with
  `"3.0"`; assert the Copilot ledger record carries `run_close: inferred`.
- **Slice E (proves AC-07):** fire the Claude `Stop` hook on a fixture session and assert a
  deterministic-labelled ledger record; assert the OpenCode plugin emits a run-close marker;
  `php tools/ai/validate-adapter-drift.php --fail-on-warn`; `composer test`.

## Risks And Rollback

- **Slice A (LOW):** additive files only — rollback = revert the new schema + registration edits.
- **Slice B (LOW-MED):** read-only projection — rollback = delete the tool; the immutable event log
  is untouched.
- **Slice C (MED):** gated by `AI_SESSION_DURABLE_LOG` (default OFF) — rollback = revert the new
  `agent-log.php` file; toggle stays off until proven.
- **Slice D (MED):** logging-core Slice 4 has its own rollback; the Copilot fallback is doc +
  inference flag only (no deterministic claim to unwind).
- **Slice E (MED-HIGH):** additive template hook entries — rollback = remove the entry to disable.

**Migration posture = expand-contract.** Hooks only emit markers; the rollup reads independently;
the ledger is fully regenerable from the immutable per-event log, so any run-close wiring can be
removed without data loss.

## Dependencies

- **Order: A → B → (C ∥ D) → E.**
  - A is standalone.
  - B needs A.
  - C and D run in parallel after B.
  - E needs B (and benefits from C).
- **Cross-plan prerequisites (do NOT re-implement here):**
  - logging-core **Slice 4** (Copilot event shape) — required for Slice D completeness.
  - logging-core **Slice 2** (runner/model block) — read by the ledger runner/model block.

## Handoff Notes

- **Smallest safe first step = Slice A only.** After this plan is persisted, the next step is an
  implementer scoped to **Slice A ONLY** — do not let the implementer wander into B–E.
- This work is on a **different branch/ticket** than the current `claude-agent-fleet-remediation`
  branch (see the branch-scope mismatch banner at the top). Cut a dedicated branch before
  implementing.
- Two owner plans are extended, not duplicated: logging-core (Slices 2–5) and v0.6 Phase 7
  (7.1/7.2/7.3/7.5). Honor the Ownership Reconciliation table.
- Never claim Copilot deterministic run-close (`security.md` invariant 6); the inferred synthetic
  close is the honest ceiling for Copilot.
- Recommended next step: implementer means implementer agent handoff using OpenCode command:
  `/implement`, scoped to Slice A.

## Archive On Completion

When every `## Todo Plan` item AND every `## Acceptance Criteria` item above is checked `[x]`,
archive this plan so active and finished plans stay separated:

```text
docs/tickets/arch-todo-ai-run-ledger-rollup-20260708-000000/plan.md
  -> docs/tickets/arch-todo-ai-run-ledger-rollup-20260708-000000/archive/DONE-plan.md
```

Steps: (1) `mkdir -p` the `archive/` folder, (2) write the full plan to `archive/DONE-plan.md`,
(3) replace this file with a one-line tombstone pointing to the archived copy. Do not archive while
any Todo item or Acceptance Criterion is still unchecked.
