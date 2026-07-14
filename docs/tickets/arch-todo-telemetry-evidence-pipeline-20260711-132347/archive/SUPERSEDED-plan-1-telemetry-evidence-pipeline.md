# Architecture Plan — Telemetry evidence pipeline (model/telemetry.yaml)

- Ticket: none (self-directed; new dedicated ticket)
- Source: `___ARCHITECTURE_2.0/telemetry/model/telemetry.yaml` (single source of truth) and
  `___ARCHITECTURE_2.0/telemetry/IMPLEMENTATION_PLAN.md` (layer-by-layer design notes)
- Generated: 2026-07-11
- Current branch: `fix/opencode-agent-body-parity`
- Plan file: `docs/tickets/arch-todo-telemetry-evidence-pipeline-20260711-132347/plan-1-telemetry-evidence-pipeline.md`

> **⚠ SUPERSEDED IN PART — READ FIRST (2026-07-11):** The user decided to **remove**
> the run-ledger, not build it. The Layer 7 fold work described below (P1 Slice B,
> P2 fold-hardening) is **CANCELLED**. Machine 7 (`run_ledger`) has been removed from
> `telemetry.yaml` and layers 9B/10 re-pointed at settled accepted-event truth.
> Artifact removal is tracked in `plan-2-ai-run-ledger-removal.md` in this folder.
> The still-valid parts of this plan are the transactional-core layers (former P3 =
> Layers 4/5/7B) and the settlement/ops/projection/evaluation layers (former P4 =
> Layers 6, 8, 9A, 9B, 10) — but 9B/10 now read event truth, not a ledger. Treat the
> ledger-specific Todo items below as historical.

> **⚠ BRANCH-SCOPE MISMATCH — READ FIRST:** This plan is authored while the current git
> branch is `fix/opencode-agent-body-parity`, but this work is **UNRELATED to that
> branch's scope**. It was given its own dedicated ticket folder
> (`arch-todo-telemetry-evidence-pipeline-20260711-132347`) deliberately, not placed
> under the current branch's folder. Do the implementation on its own branch/ticket,
> not on `fix/opencode-agent-body-parity`. If you are on that branch when you pick this
> up, stop and cut a dedicated branch first.

> **Completion instruction:** When every `## Todo Plan` item and every
> `## Acceptance Criteria` item below is checked `[x]`, rename this file to
> `DONE-plan-1-telemetry-evidence-pipeline.md` and move it into `archive/` under this
> ticket folder
> (`docs/tickets/arch-todo-telemetry-evidence-pipeline-20260711-132347/archive/DONE-plan-1-telemetry-evidence-pipeline.md`).
> Note: **P1 (`ai-run-ledger.v2` Slice B) is the smallest safe first implementation
> step and the only slice a first implementer handoff should touch** — P0's
> blocking decisions are already recorded (see "P0 Decisions Recorded"); only its
> documentation task remains. P2, P3, and P4 follow the dependency order and must
> each be confirmed in scope before implementation.

## Context

`___ARCHITECTURE_2.0/telemetry/model/telemetry.yaml` defines a 12-section (1, 2, 3, 4,
5, 6, 7, 7B, 8, 9A, 9B, 10) evidence/telemetry pipeline: runtime signals from four AI
coding tools (Copilot VS Code, Copilot CLI, Claude Code, OpenCode) flow through
adapters into a durable candidate inbox, an append-only event store with a
transactional outbox, a protected content/blob path, run settlement and a
deterministic ledger fold, quarantine/operator review, audit and semantic
projections, and finally evaluation (promptfoo, Langfuse, Phoenix, OpenObserve).
`generated/*.mmd` diagrams are build artifacts of this model; they are never
hand-edited.

The user has confirmed that **layers 1, 2, and 3 (runtime signals, runtime adapters,
context provenance) are already present** — these map to capabilities the four
external runtimes already provide (OTel emission, lifecycle hooks, an OpenCode audit
plugin) rather than to code this repository must build.

Relevant existing repo-local evidence for adjacent (not identical) capability, found
during grounding:

- `schemas/ai/evidence-event.schema.json` — a real per-event schema for this repo's
  own AI-workflow tooling.
- `scripts/ai/internal/lib/30-logging.sh` — a real per-event emitter.
- `.ai-logs/tool-usage.jsonl` — a real append-only sink.
- `docs/tickets/arch-todo-ai-run-ledger-rollup-20260708-000000/plan.md` — an
  in-progress (Slice A only) per-run rollup ledger built **on top of** the per-event
  log above, for this repo's own OpenCode-centric tooling.

That existing work is narrower in scope than `telemetry.yaml` (single-runtime,
no durable inbox, no blob store, no transactional outbox, no run-epoch fence, no
promptfoo/Langfuse/Phoenix/OpenObserve sinks) and must not be silently duplicated —
see "Risks And Rollback".

## Problem

Layers 4 through 10 of the model (canonical event truth, protected content path, run
settlement, run-level ledger truth, transactional outbox, quarantine/operator
health, audit and semantic projections, evaluation) have no implementation anywhere
in this repository. Building them requires a persistence/runtime stack decision that
does not yet exist for this pipeline, and the model itself is silent on that
decision by design (it is a domain model, not a deployment spec).

## P0 Decisions Recorded (2026-07-11)

- **Stack decision:** Extend the existing `ai-run-ledger.v2` effort rather than
  stand up new event-store/queue/blob infrastructure.
- **Reuse-vs-build finding (evidence-based):** the transactional core (Layer 4
  `ECOMMIT` saga boundary, Layer 5 `BLOB`, Layer 7B `OUTBOX`) is ~0–40% overlap
  with existing assets = genuine new-build; the ledger fold (Layer 7) is ~50%
  overlap with the `ai-run-ledger.v2` rollup concept = extendable. See the
  reuse-vs-build table this finding is based on in the handoff that produced this
  update; the underlying evidence is `schemas/ai/evidence-event.schema.json` and
  `docs/tickets/arch-todo-ai-run-ledger-rollup-20260708-000000/plan.md`.
- **Corrected first step:** `ai-run-ledger.v2` **Slice B** (the rollup renderer,
  planned as `tools/ai/run-ledger.php`) is **UNBUILT** — verified file-absent via
  `scripts/ai/fd-files.sh`; only Slice A (the schema
  `schemas/ai/ai-run-ledger.schema.json`) is done. Layer 7 fold-hardening cannot
  start by "extending the rollup" because no rollup projection exists yet — Slice
  B *is* the fold's first honest form. Corrected sequence:
  1. Build `ai-run-ledger.v2` Slice B (read-only renderer folding
     `.ai-logs/tool-usage.jsonl` → `.ai-logs/run-ledger.jsonl`, applying the
     Field-Source Honesty Matrix from that plan).
  2. Harden it into `FSNAP` (immutable fold-input snapshot) → `FOLD` (deterministic
     provenance-preserving fold) → `LSTORE` (validated versioned ledger).
  3. Add `LPART`/`LOBS`/`LINF` observed-vs-inferred partitioning.
  4. Add `LFENCE`/`LFINAL` atomic finalization fence and `LTERM`.
- **Honesty ceiling (inherited from `ai-run-ledger.v2`):** only `tool_calls[]`,
  `diff`, and `privacy` are fully sourced across all four runtimes today; every
  other ledger/evaluation field must stay `null` rather than be fabricated.
  Layers 7–10 are honest-but-sparse until runtimes expose more fields — this is a
  hard contract, not a temporary gap to code around.
- **Branch:** cut a dedicated branch before implementation; the current branch
  (`fix/opencode-agent-body-parity`) is unrelated to this work, matching the
  branch-scope-mismatch banner at the top of this plan.
- **Layers 4/5/7B (the previously-planned "minimal honest-ingest core") are
  DEFERRED**, not the first slice — see updated Target Outcome below.

## Target Outcome

A bounded, dependency-ordered implementation path from the model to a running
pipeline, starting with `ai-run-ledger.v2` Slice B (the unbuilt rollup renderer)
and hardening it into telemetry Layer 7's deterministic fold, then deferring the
transactional core (Layers 4, 5, 7B) and remaining settlement/ops/projection/
evaluation layers (6, 8, 9A, 9B, 10) until the fold is built and verified —
without duplicating the existing `ai-run-ledger.v2` effort.

## In Scope

- Building `ai-run-ledger.v2` Slice B (the unbuilt rollup renderer) as the
  corrected first implementation step.
- Hardening Slice B's output into telemetry Layer 7 (`FSNAP`/`FOLD`/`LSTORE`,
  `LPART`/`LOBS`/`LINF`, `LFENCE`/`LFINAL`/`LTERM`).
- Implementing Layers 4, 5, and 7B (the transactional core) after Layer 7 is
  verified — this is now deferred, not the first slice.
- Implementing Layers 6, 8, 9A, 9B, and 10 after the transactional core is verified.
- Wiring the model's `cross_links` between machines as each layer lands.
- Keeping `model/telemetry.yaml` the single source of truth and
  `scripts/gen_mermaid.sh --check` green after every model edit.

## Out Of Scope (Things To Avoid)

- Do not scaffold new event-store/queue/blob infrastructure — the P0 stack
  decision is to extend `ai-run-ledger.v2`, not build new.
- Do not hand-edit `generated/*.mmd`; regenerate via `scripts/gen_mermaid.sh`.
- Do not duplicate `docs/tickets/arch-todo-ai-run-ledger-rollup-20260708-000000`;
  this plan extends it (see "P0 Decisions Recorded").
- Do not build runtime-specific onboarding for all four external runtimes
  (Copilot VS Code, Copilot CLI, Claude Code, OpenCode) in this slice; the
  remaining P0 task covers field-mapping documentation only, not new adapter code.
- Do not attempt Layer 7 fold-hardening before `ai-run-ledger.v2` Slice B exists —
  Slice B is the fold's first honest form, not something to build in parallel or
  skip.
- Do not implement Layers 4, 5, 6, 7B, 8, 9A, 9B, or 10 before the Slice B → Layer 7
  fold is built and verified.
- Do not fabricate any ledger/evaluation field the Field-Source Honesty Matrix
  marks `null` for the current runtime (see "P0 Decisions Recorded").
- Do not widen this plan's scope to redesign the model itself; model changes go
  through the architect + this model's own validation workflow, not this plan.

## Affected Paths

- `___ARCHITECTURE_2.0/telemetry/model/telemetry.yaml` (source of truth; edits only
  if the design needs correction during implementation)
- `___ARCHITECTURE_2.0/telemetry/generated/*.mmd` (regenerated, never hand-edited)
- New implementation paths: `unknown` until the P0 stack decision is recorded
- `docs/tickets/arch-todo-ai-run-ledger-rollup-20260708-000000/` (read for reuse
  evaluation, not edited by this plan)

## Contracts And Boundaries

- Accepted events are append-only: never edited or replaced (`ESTORE`).
- The commit is a saga boundary, not one distributed commit: a single event-store
  transaction commits the accepted event **and** the outbox record; derived state is
  projected from those facts, not written inside the commit (`ECOMMIT`, `OUTBOX`).
- The deterministic fold reads **only** the immutable snapshot, never live stores
  (`FSNAP`, `FOLD`).
- Reopen and finalization use a run-epoch fence via compare-and-set (`ROPEN`,
  `LFENCE`); a finalization loses to a concurrent reopen that already incremented
  the epoch.
- Producers are acknowledged only after a candidate is persisted durably (`CINBOX`).
- Observed vs inferred provenance is preserved end to end (`OBS`/`INF`,
  `LOBS`/`LINF`, `SPROJ`).
- The background blob auditor is out-of-band and never an input to the
  deterministic fold (`BAUDIT`).

## Todo Plan

### P0 — Remaining pre-work

> Stack/reuse/first-slice decisions are already recorded above under
> "P0 Decisions Recorded"; this is what's left.

- [ ] Document, per external runtime (Copilot VS Code, Copilot CLI, Claude Code,
      OpenCode), which candidate-envelope fields (`event_id`, `source_run_id`,
      `producer_instance_id`, `sequence_number`, `source_event_key`,
      `lineage_root_id`, `correction_attempt`, `corrected_from`) are already
      available from existing signals/hooks and which are missing, using
      `schemas/ai/evidence-event.schema.json` and
      `scripts/ai/internal/lib/30-logging.sh` as a reference pattern where
      applicable.
- [ ] Cut a dedicated branch for this work (current branch
      `fix/opencode-agent-body-parity` is unrelated).

### P1 — `ai-run-ledger.v2` Slice B: build the rollup renderer

> Corrected first implementation slice; extends
> `docs/tickets/arch-todo-ai-run-ledger-rollup-20260708-000000`, does not
> duplicate it.

- [ ] Build a read-only PHP tool (`tools/ai/run-ledger.php` or equivalent) that
      reads `.ai-logs/tool-usage.jsonl`, groups events by `session_id`/`trace_id`,
      and folds each group into one `ai-run-ledger.v2` record.
- [ ] Apply the Field-Source Honesty Matrix from the owner plan: populate only
      `tool_calls[]`, `diff`, and `privacy` (fully sourced today); leave every
      other field `null` rather than fabricated.
- [ ] Write records to `.ai-logs/run-ledger.jsonl` (pure projection, no new
      capture; never mutates the source log).
- [ ] Verify against a 2-session fixture: 2 grouped records, nullable fields
      null, `tool_calls[]` populated, each record schema-valid against
      `schemas/ai/ai-run-ledger.schema.json`.

### P2 — Harden Slice B into telemetry Layer 7 (`run_ledger`)

- [ ] Add `FSNAP`: pin an immutable fold-input snapshot (run epoch, event-count,
      and version watermarks) before folding, instead of reading
      `.ai-logs/tool-usage.jsonl` live on every render.
- [ ] Add `FOLD`: make the fold deterministic and provenance-preserving, reading
      only the pinned `FSNAP` (never the live log) — this generalizes P1's
      renderer, it does not replace it.
- [ ] Add `LPART`/`LOBS`/`LINF`: partition ledger claims into observed vs
      inferred, consistent with the Field-Source Honesty Matrix.
- [ ] Add `LGATE`: ledger integrity + honesty gate (duplicates, conflicts, close
      classification; observed claims derive only from observed evidence).
- [ ] Add `LCLASS`/`LHOLD`/`LFINWAIT`/`LDEAD`: failure classification, transient
      hold, finalization wait, and deadline handling.
- [ ] Add `LSTORE`: validated versioned run ledger (observed/inferred kept
      separate), replacing the flat `.ai-logs/run-ledger.jsonl` sink from P1.
- [ ] Add `LFENCE`/`LFINAL`: atomic finalization fence (CAS on `run_epoch`/
      `ledger_version`; a finalization loses to a concurrent reopen).
- [ ] Add `LTERM`: finalized terminal ledger version (eval-eligible; version and
      digest pinned).
- [ ] Add `LQUAR`/`LPREJ`: ledger quarantine and permanent rejection registry for
      classified failures.

### P3 — Transactional core (Layers 4, 5, 7B) — DEFERRED until P2 is verified

- [ ] Layer 4: implement `CINBOX` durable encrypted candidate inbox (encryption,
      access policy, retention, `redaction_status`, `ingress_timestamp`,
      `processing_lease`; ack only after persist).
- [ ] Layer 4: implement `EGATE` honesty + idempotency gate (schema/source/
      provenance/privacy validation; unique `event_id` + stable logical identity).
- [ ] Layer 4: implement `EADOPT` (idempotent adoption) and `ECOMMIT` (saga-boundary
      single-txn commit of accepted event + outbox record; identity UNIQUE
      constraints; pinned blob-verification facts).
- [ ] Layer 4: implement `ESTORE`, `EQUAR`, `EDISP`, `ELIMIT`, `ECORR`, `EPREJ`.
- [ ] Layer 5: implement `REDACT` → `DIGEST` → `BPUT` → `BLOB` → `BVERIFY`, plus the
      out-of-band `BAUDIT`.
- [ ] Layer 7B: implement `OUTBOX`, `DISPATCH`, `STATEPROJ`, `EPROJOUT`.
- [ ] Wire the P3 `cross_links`: `EGATE→REDACT`, `BVERIFY→ECOMMIT|EQUAR`,
      `ECOMMIT→OUTBOX`, `STATEPROJ→RSTATE` (stub target until Layer 6 lands),
      `EPROJOUT→EPROJ` (stub target until Layer 9A lands).

### P4 — Settlement, ops, projections, evaluation (after P3 verified)

- [ ] Layer 6: implement `RSTATE`, `RSETTLE`, `RWAIT`, `RDEAD`, `RINCOMP`,
      `RREOPEN`, `ROPEN`, `RLATE`.
- [ ] Layer 8: implement `QMET`, `MON`, `OP`, `OREVIEW`, `ODEC`.
- [ ] Layer 9A: implement `EPROJ`, `DPROJ`, `ECOLL`, and the `OO` (OpenObserve) sink.
- [ ] Layer 9B: implement `SPROJ`, `SCOLL`, and the `LF` (Langfuse) / `PHX`
      (Phoenix) sinks.
- [ ] Layer 10: implement `GOLD`, `PF` (promptfoo), `MANUAL`, `EFAIL`, `EREC`,
      `ECOV`, `VIEW`.
- [ ] Wire the remaining `cross_links` (settlement→snapshot, quarantine/rejection→
      metrics/ops/audit, ledger→semantic projection, terminal ledger→evaluation).
- [ ] Add a CI gate that fails the build on `scripts/gen_mermaid.sh --check` errors.

## Acceptance Criteria

`AC-01`/`AC-02` apply now (P0/P1). `AC-09`/`AC-10` prove the corrected first slice
(P1/P2). `AC-03`–`AC-08` apply to the deferred transactional core and later layers
(P3/P4) and are not yet in scope for the current slice.

- [ ] AC-01: The P0 stack decision (extend `ai-run-ledger.v2`, not build new
      infrastructure) is recorded in this plan before any P1+ code is written.
      Recorded under "P0 Decisions Recorded" (2026-07-11); this AC tracks that the
      record stays accurate as implementation proceeds, not a one-time check.
- [ ] AC-02: `model/telemetry.yaml` continues to pass `scripts/gen_mermaid.sh --check`
      after every model edit made during implementation.
- [ ] AC-09: Given a fixture with 2 sessions in `.ai-logs/tool-usage.jsonl`, the
      Slice B renderer (P1) produces 2 grouped `ai-run-ledger.v2` records; nullable
      fields are `null` (no fabrication), `tool_calls[]`/`diff`/`privacy` are
      populated, and each record validates against
      `schemas/ai/ai-run-ledger.schema.json`.
- [ ] AC-10: A test proves the Layer 7 `FOLD` (P2) reads only the pinned `FSNAP`
      and performs no live queries against `.ai-logs/tool-usage.jsonl` during a
      fold execution.
- [ ] AC-03: An integration test proves `CINBOX` never yields a false producer ack
      when persistence fails before the candidate is durably stored.
- [ ] AC-04: A test proves the `ECOMMIT`/`OUTBOX` write is atomic — no outbox record
      exists for any event absent from `ESTORE`, and vice versa.
- [ ] AC-05: Tests prove the blob path never commits without `BVERIFY` confirming a
      digest match, and that `BAUDIT` never influences `ECOMMIT` or `FOLD`.
- [ ] AC-06: A test proves `FOLD` reads only `FSNAP` and performs no live-store
      queries during a fold execution. (Superseded by AC-10 for the P2 slice;
      kept here for the full Layer 7 scope once P3/P4 land.)
- [ ] AC-07: A concurrency test proves the `run_epoch` CAS fence prevents any
      double-terminal ledger version under a concurrent reopen.
- [ ] AC-08: `ECOV` (Layer 10 coverage report) accounts for attempted, finalized,
      incomplete, rejected, and late-material runs with no survivorship bias,
      proved by a fixture run set covering each terminal outcome.

## Verification Plan

- **P1 (Slice B):** run the renderer against a 2-session fixture of
  `.ai-logs/tool-usage.jsonl`; assert 2 grouped records, nullable fields null,
  `tool_calls[]`/`diff`/`privacy` populated, each record schema-valid; proves AC-09.
  `composer test:fast`.
- **P2 (Layer 7 fold):** assert `FOLD` reads only the pinned `FSNAP` fixture with
  live-store access unavailable/mocked during the fold call; proves AC-10.
- Run `scripts/gen_mermaid.sh --check` after every `model/telemetry.yaml` edit
  (schema + semantic checks: duplicate IDs, dangling edges/cross-links, orphan
  nodes, unknown classes) — proves AC-02.
- **P3/P4 (deferred):** add a focused unit test per gate node (`EGATE`, `BVERIFY`,
  `RSETTLE`, `LGATE`, `LFENCE`, etc.) before wiring it into the pipeline; add one
  integration test per saga boundary (`ECOMMIT`/`OUTBOX`) proving atomicity; add
  one concurrency test for the run-epoch CAS fence (`ROPEN`/`LFENCE`).
- No build command is defined yet for the transactional-core service (P3); record
  it once that phase starts, and add it to this plan's Verification Plan at that
  point.

## Risks And Rollback

- **Sequencing risk (realized once, now corrected):** this plan originally assumed
  the `ai-run-ledger.v2` rollup renderer existed and could be "hardened" directly
  into Layer 7. Grounding (`scripts/ai/fd-files.sh` search for
  `tools/ai/run-ledger.php`) proved Slice B is unbuilt — only the schema (Slice A)
  is done. Mitigation: P1 now builds Slice B first; P2 hardens it. Rollback: no
  code was written under the old assumption, so there is nothing to unwind.
- **Duplicate-system risk:** building a bespoke event store here could duplicate
  `schemas/ai/evidence-event.schema.json` + `.ai-logs/` + the in-progress
  `ai-run-ledger.v2` effort. Mitigation: this plan extends `ai-run-ledger.v2`
  directly (P1/P2) rather than building a parallel store; the transactional core
  (P3) remains genuinely new-build per the recorded ~0–40% overlap finding.
  Rollback: P1/P2 work lives inside the existing ledger's file surface
  (`.ai-logs/run-ledger.jsonl`, `schemas/ai/ai-run-ledger.schema.json`) and can be
  reverted without touching the append-only source log.
- **Wrong stack choice (P3, still pending):** committing to a store/queue/blob
  technology before validating it against the model's saga-boundary and
  epoch-fence contracts risks costly rework. Mitigation: prototype the
  `ECOMMIT`/`OUTBOX` atomicity contract against the chosen stack before building
  the rest of P3.
- **Scope creep across four runtimes:** onboarding all of Copilot VS Code, Copilot
  CLI, Claude Code, and OpenCode in this slice would blow the bounded-slice budget.
  Mitigation: P0's runtime-field documentation task is read-only; new adapter code
  per runtime is out of scope for this plan and deferred to a follow-up ticket.

## Handoff Notes

- P0's reuse-vs-build decision is now recorded (extend `ai-run-ledger.v2`); the
  architect review previously recommended before P1 has been superseded by that
  explicit decision plus the Slice-B-is-unbuilt grounding finding above.
- implementer means implementer agent handoff for P1 (`ai-run-ledger.v2` Slice B —
  the rollup renderer) on a dedicated branch, per the "P0 Decisions Recorded"
  section; this is the actual next bounded slice, not P0.
- P2 (Layer 7 fold hardening) should not be handed off until P1's AC-09 is proven
  green, since P2 builds directly on P1's output shape.
- reviewer means reviewer agent handoff using OpenCode command: `/review-diff`,
  once P1 code exists to review.

## Foundation Verification & CI Status (2026-07-11, addendum)

The build pivoted from the ledger-fold path above to a **TypeScript + PostgreSQL**
greenfield in the sibling `ai-monitoring` repo (see the SUPERSEDED banners in this
folder and in `IMPLEMENTATION_PLAN.md`). Status of that foundation:

- Env/no-hang hardening landed: `db:migrate`/`db:test`/`test` auto-load `.env`
  (`--env-file-if-exists`); pg pool has `connectionTimeoutMillis: 3000` +
  `isDbReachable()` skip. Re-verified clean-shell: 5/5 db:test, 11/11 with DB up,
  6 pass / 5 skip / 0 fail with DB down (no hang).
- Service CI gate added: `ai-monitoring/.github/workflows/ci.yml` runs
  `pnpm typecheck` + `pnpm test:schema` (no DB) on push/PR to `main`. Locally
  re-proven green (typecheck 0 errors; schema 6/6) before commit.
- Still open: the `scripts/gen_mermaid.sh --check` **model** CI gate in this repo
  (P4 / Validation & CI) is unbuilt — the ai-monitoring service CI does not cover it.

## Layer 4 Remainder + Layer 7B + Layer 6 Core (2026-07-11, addendum)

Continued the `ai-monitoring` build (not this repo) across three sequential bounded
slices in one session, each independently typechecked and DB-verified against the
live Postgres 16 container (port 5433) before moving to the next:

1. **Layer 4 remainder** — `EDISP`/`ELIMIT`/`ECORR`/`EPREJ` (migration
   `0003_disposition.sql`, `src/db/disposition.ts`) plus completing the previously
   half-built `EGATE` (`src/db/gate.ts`): the Ajv schema validator only existed in a
   test file before this, and `CommitOutcome.quarantined` was a dead type variant no
   caller ever produced.
2. **Layer 7B** — `DISPATCH`/`STATEPROJ`/`EPROJOUT` (migration `0004_run_state.sql`,
   `src/db/dispatch.ts`/`stateproj.ts`/`eprojout.ts`), including the `RSTATE` table
   (`run_state_projection`) that Layer 6 also depends on.
3. **Layer 6 core** — `RSETTLE`→`RSETTLED` wiring, `RINCOMP`, `ROPEN`/`RREOPEN`/`RLATE`
   (migration `0005_settlement.sql`, `src/db/settlement.ts`).

**Verification:** `pnpm typecheck` clean throughout; `pnpm test` went from 11/11 to
30/30, re-run 6 consecutive times clean after fixing two real bugs the live-DB proof
surfaced (not typecheck-only): a missing advisory lock in `migrate.ts` (concurrent
test-file processes could race on a brand-new migration's `CREATE TABLE IF NOT
EXISTS`) and an uncast `timestamptz` (`settled_at`) that returned a `Date` object at
runtime against a `string`-typed field, breaking a strict-equality assertion.

**Explicit scope limit (not silently glossed over):** `RSETTLE`/`RDEAD` are
caller-driven controllers, not automatic close-signal/sequence-gap detectors — no
component in this repo observes a live producer close signal. This is a legitimate,
documented design choice (see `README.md` "Known gaps"), not a claim that the
model's full automatic close policy is implemented.

**Deferred, each needing an infra/product decision or being out of this repo's
scope, not attempted this session:**

- Layer 5 (`REDACT`/`BPUT`/`BLOB`/`BVERIFY`/`BAUDIT`) — needs a blob storage-backend
  decision (e.g. local-fs vs. S3/GCS) and an encryption/retention policy.
- Layer 8 ops (`QMET`/`MON`/`OP`/`OREVIEW`/`ODEC`) — `MON`/`OP` need an
  alerting-sink decision.
- Layer 9A/9B external sinks (OpenObserve, Langfuse, Phoenix) — external products,
  no accounts/credentials configured.
- Layer 10 evaluation (`PF`=promptfoo, `MANUAL`) — external tool / human process.
- Layer 2 adapters — external runtimes, out of this repo.

**Newly discovered gap in the "complete" foundation:** `telemetry.candidate_inbox`
(CINBOX) has no writer anywhere in `src/` — candidates route directly through
`EGATE` into `accepted_event`/`quarantine`. Flagged in `IMPLEMENTATION_PLAN.md` and
`README.md`, not silently fixed as part of this unrelated slice (it would change
`EGATE`'s entrypoint/ack semantics, which needs its own bounded review).

reviewer means reviewer agent handoff using OpenCode command: `/review-diff`, for
the three slices above (`ai-monitoring` migrations 0003-0005 + `src/db/disposition.ts`,
`gate.ts`, `quarantine.ts`, `run-state.ts`, `dispatch.ts`, `stateproj.ts`,
`eprojout.ts`, `settlement.ts`, and the `migrate.ts` advisory-lock fix), before any
further layer is attempted.
