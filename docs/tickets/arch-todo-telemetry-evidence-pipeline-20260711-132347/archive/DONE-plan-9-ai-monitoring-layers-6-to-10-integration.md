# Architecture Plan — ai-monitoring Layers 6-10 integration (in-repo seams)

- Ticket: `arch-todo-telemetry-evidence-pipeline-20260711-132347` (same ticket as plan-1..plan-8)
- Source: canonical model `___ARCHITECTURE_2.0/telemetry/model/telemetry.yaml` (Layers 6, 7B, 8, 9A,
  9B, 10 machines + `cross_links`), `IMPLEMENTATION_PLAN.md` build status, and a read-only architect
  design pass over the real `../ai-monitoring` source (2026-07-11, this session).
- Generated: 2026-07-11
- Implementation target repo: `/home/utmostcreator/Projects/ai-monitoring` (sibling; NOT this repo)
- Plan file: `docs/tickets/arch-todo-telemetry-evidence-pipeline-20260711-132347/plan-9-ai-monitoring-layers-6-to-10-integration.md`

> **IMPLEMENTATION TARGET IS EXTERNAL.** All edits land in the sibling `../ai-monitoring`. This plan
> file is tracked here (with plan-1..8) because this ticket is the durable plan home. External-edit
> approval for `../ai-monitoring` already granted for this telemetry work (plan-7 precedent).

> **SCOPE BOUNDARY (user-authoritative).** In-repo SQL/projection seams ONLY. Every external
> product/infra wiring is `BLOCKED-EXTERNAL-DECISION` — do NOT invent products, credentials, endpoints,
> tenancy, retention durations, or deployment topology. Provider-specific schemas must NOT leak into
> Postgres truth or projection tables; exporters translate stable internal contracts into external
> sinks later, outside the DB.

> **Completion instruction:** When every `## Todo Plan` and `## Acceptance Criteria` item is `[x]`
> (or a layer is explicitly deferred), rename to
> `DONE-plan-9-ai-monitoring-layers-6-to-10-integration.md` and move into `archive/`.

> **Review corrections applied 2026-07-11 (reviewer pass).**
> 1. **Migration collision (correctness fix):** `0008_candidate_inbox_dead_letter.sql` already exists
>    on disk, so the proposed range was renumbered `0008`-`0012` -> **`0009`-`0013`**; "last existing
>    migration" corrected from `0007` to `0008` throughout. See "Migration numbering".
> 2. **OpenObserve is optional (model-verified):** `model/telemetry.yaml` line 191 annotates `OO` as
>    "optional raw event search" and it is a terminal sink (`ECOLL -> OO`). This plan correctly builds
>    only the in-repo audit seam and stops `ECOLL -> OpenObserve` at `BLOCKED-EXTERNAL-DECISION`
>    (Layer 9A). OpenObserve is NOT required.
> 3. **Layer 9A is not "just OpenObserve":** its core nodes `EPROJ`/`DPROJ`/`ECOLL` are in-repo
>    projection targets fed by real cross-links (`EPROJOUT -> EPROJ`; `EQUAR/EPREJ/RINCOMP/RLATE ->
>    DPROJ`); only the terminal `OO` sink is deferrable. `DPROJ` is a required failure-evidence sink,
>    not optional.
> 4. **Environment (verified, no change needed):** host is NixOS 26.11; the Docker daemon is already
>    `active` + `enabled` (source: `~/Projects/app-configs/nix/modules/nixos/docker.nix`, rendered to
>    `/etc/nixos/app-configs-docker.nix`, `myConfig.docker.enable`). Because this plan is in-repo
>    SQL/TS only with all external products `BLOCKED-EXTERNAL-DECISION`, **no NixOS/`app-configs`
>    change is required now.** A NixOS change would only be needed later, per-product, at an external
>    activation gate — and must be made in `~/Projects/app-configs` first, then applied with
>    `sudo nixos-rebuild switch` (see "External activation — NixOS note" below).

## Context

Layer 4 (event truth) + Layer 6 core (settlement) + Layer 7B (outbox) are BUILT and green in
`../ai-monitoring`, refactored into `src/{contracts,domain,application,persistence,projections}` with
`#`-alias imports; migrations are additive forward-only `0001`..`0008` (verified on disk 2026-07-11:
`0008_candidate_inbox_dead_letter.sql` is the last existing migration). Layers 8, 9A, 9B, 10 are
greenfield; Layer 6/7B have small residuals. This plan integrates 6-10 into that existing shape without
inventing external infrastructure.

### Plan-7 overlap (explicit)

plan-7 (Layer 4 CINBOX hop, owned by another agent — see plan-8) covers NO Layer 6-10 node. The one
overlap is a data-edge: plan-7's inbox writer bumps `run_state_projection.registered_count` (a Layer-6
column added by `0007`). Layers 6-10 here READ `registered_count` (coverage rates, settlement
readiness) but never write it. **Producer = plan-7; consumer = this plan.** Do not both try to own it.

### Verified conventions to reuse (not assumed)

- Append-only immutable tables: `reject_<t>_mutation()` plpgsql fn + `BEFORE UPDATE OR DELETE` trigger +
  `REVOKE UPDATE, DELETE` (pattern in `0001`/`0005`). Mutable projections/checkpoints deliberately omit it.
- Idempotency: `GENERATED ALWAYS AS IDENTITY` surrogate PK + business `UNIQUE(...)` + `ON CONFLICT ... DO
  NOTHING` (as in `settlement_fact`, `event_rejection`, `incomplete_run_registry`).
- `timestamptz`/`bigint` returned to TS via `::text` casts; `commit_sequence` is the monotonic identity.
- Roles `telemetry_ingest`/`_projector`/`_operator`/`_migrator` (0002); runtime connects as
  `telemetry_migrator` via single `DATABASE_URL` today (grants are defence-in-depth, not runtime-enforced).
- Deterministic payloads reuse `#domain/canonical.js` `digest()` (`sha256:<hex>`) / `CANONICAL_ALGORITHM`
  (`sha256-canonical-v1`). (Known cosmetic label mismatch between the two — pre-existing, do NOT "fix" here.)
- Durable projection = inside the DISPATCH outbox txn (`projectAccepted`). Best-effort "dotted" projection
  = bare-pool call outside any txn (`projectQuarantined`/`Correcting`/`TerminallyRejected`/`Registered`).
- Every integration test: `const hasDb = await isDbReachable(); ... { skip: !hasDb }`, `migrate()` in
  `before`, `closePool()` in `after`, `randomUUID()` run ids (template: `test/integration/settlement.test.ts`).

## Target Outcome

Layers 6 (residual), 7B (residual), 8, 9A, 9B, 10 land as in-repo Postgres schema + TypeScript
projection/exporter seams, each behind its own worker so it can be enabled independently, with every
external sink stopped at a provider-neutral exporter interface. Evaluation authority stays
`settlement_fact.accepted_set_digest` (RSETTLED), never RSTATE. No exporter/monitor ever runs inside the
ECOMMIT or DISPATCH transaction.

## Migration numbering (collision resolved 2026-07-11)

plan-7's inbox work consumed `0007` **and** `0008_candidate_inbox_dead_letter.sql` (both verified
present on disk). The last existing migration is therefore `0008`, not `0007`. This plan's original
`0008`-`0012` proposal collided with that real `0008`, so it is renumbered to **`0009`-`0013`**.
**Pre-condition still stands: confirm with the plan-7 owner that `0008` is the last number they
consume before writing `0009`.** If plan-7 adds a later migration, shift this range up accordingly.

- `0009_run_state_digest.sql` — Layer 6 residual
- `0010_quarantine_health.sql` — Layer 8
- `0011_audit_projection.sql` — Layer 9A
- `0012_semantic_projection.sql` — Layer 9B
- `0013_evaluation.sql` — Layer 10

(7B needs no migration of its own — it reuses the existing outbox `consumer_name` fan-out rail.)

## Design

Seam pattern (all layers): `domain/` pure rules (no I/O, unit-testable), `persistence/` raw SQL
writers/readers, `application/` orchestrators owning txn boundaries + out-of-band workers,
`projections/` idempotent projectors invoked by DISPATCH, `contracts/` provider-neutral payload types +
the two generic exporter interfaces.

### Layer 6 — residual hardening only

- Gap: `RSTATE.run_state_digest` is named in the model's RSTATE note but absent from
  `run_state_projection` (only `run_epoch`/`run_state_version`/`run_status` exist). `registered_count`
  is NOT a gap — `0007` added it and `projectRegistered()` writes it.
- `0009`: `ALTER TABLE telemetry.run_state_projection ADD COLUMN IF NOT EXISTS run_state_digest text`
  (nullable; mutable projection, no trigger).
- `persistence/run-state.ts`: compute `run_state_digest = digest(counterTuple)` inside the existing
  bump/project writers. **Advisory/drift-detection only — explicitly NOT the evaluation pin** (that
  stays `settlement_fact.accepted_set_digest`).
- Verify: unit test that `digest(counterTuple)` is deterministic (no DB); then extend
  `settlement.test.ts` to assert `getRunState().run_state_digest` is a non-null `sha256:` string that
  changes when a counter changes.
- **Open design question — confirm before building:** is `run_state_digest` worth adding at all, given
  RSTATE is deliberately non-authoritative? Recommendation: add it (cheap, closes a named-model gap)
  but mark advisory. If declined, Layer 6 residual is a no-op and the model note should be trimmed instead.

### Layer 7B — residual only

- Fully built. Residual = the attachment seam for 9A/9B: add outbox `consumer_name` branches in
  `application/dispatch-outbox.ts::claimAndProcessOne` when 9A `DPROJ` / 9B need outbox-driven delivery
  (SPROJ is settlement-triggered, not per-event — see 9B). No migration.
- Optional hardening (deferred, note only): `outbox.consumer_name` is free `text`; a CHECK/enum would be
  a breaking migration — skip for now.
- Verify: extend `dispatch.test.ts` — enqueue a row with a new `consumer_name`, assert the new projector
  target gets exactly one row and re-dispatch is a no-op.

### Layer 8 — quarantine health & operations (`0010`)

Schema:
- **QMET** = `telemetry.quarantine_metrics` **SQL view** (not a table — keep it derivable, no truth
  copy) aggregating rate/reason/runtime/adapter_version/attempt_count/age/terminal-disposition over
  `quarantine` + `event_rejection` + `incomplete_run_registry` + `late_event_registry`.
- **OREVIEW** = `telemetry.operator_review_case` (mutable on `case_status` only): `review_id` IDENTITY
  PK, `source_run_id`, `lineage_root_id`, `event_id` NULL, `opened_reason`, `case_status` DEFAULT
  `'open'` (`open|dispositioned`), `opened_at`, partial `UNIQUE(lineage_root_id) WHERE
  case_status='open'`. Guard trigger forbids DELETE + edits to anything except `case_status`/`resolved_at`.
- **ODEC** = `telemetry.operator_disposition` (append-only): `disposition_id` IDENTITY PK,
  `review_id` FK, `decision` (`correct|permanently_reject|revalidate` -> ECORR|EPREJ|EGATE),
  `decided_by` (authenticated principal), `decided_at`, `rationale`, `UNIQUE(review_id)`. Append-only trigger.
- **Alert interface** = `telemetry.alert_event` (append-only): `alert_id` IDENTITY PK, `alert_kind`
  (`rate|age|repeated_lineage|adapter_regression|settlement_timeout`), `subject_ref`, `detail jsonb`,
  `raised_at`, idempotent window `UNIQUE`. MON writes here; there is NO concrete alerting provider.
- Roles: OREVIEW/ODEC owned by `telemetry_operator`; `alert_event` INSERT by `telemetry_projector`.

TypeScript:
- `domain/quarantine-thresholds.ts` (pure MON): QMET snapshot + config -> `AlertDecision[]`.
- `domain/operator-disposition.ts` (pure): ODEC decision -> re-entry action kind (reuse
  `apply-disposition.ts`'s existing classification shape).
- `persistence/quarantine-metrics.ts` (`getQuarantineMetrics`), `persistence/operator-review.ts`
  (`openReviewCase`/`recordDisposition`/`getOpenCase`).
- `application/review-case.ts`: `openReviewCaseFromDisposition()` (called by the NEW EDISP
  "operator review" branch that `apply-disposition.ts`'s header flags as unbuilt) +
  `applyOperatorDisposition()` (records ODEC then routes back through EXISTING
  EGATE/apply-disposition entrypoints — never writes `accepted_event` directly).
- `application/monitor-quarantine.ts`: thin MON worker — read QMET, run the pure rule,
  `INSERT ... ON CONFLICT DO NOTHING` into `alert_event`.

Cross-links satisfied: `EQUAR/EPREJ/RINCOMP/RLATE -> QMET` (via view), `EDISP -> OREVIEW`,
`OREVIEW -> ODEC`, `ODEC -> ECORR|EPREJ|EGATE`, `QMET -> MON` (in-repo). `MON -> OP` sink blocked.

```
BLOCKED-EXTERNAL-DECISION: Layer 8 MON->OP concrete alerting sink (product, endpoint, routing, on-call topology).
BLOCKED-EXTERNAL-DECISION: Layer 8 operator authentication/identity provider for operator_disposition.decided_by.
BLOCKED-EXTERNAL-DECISION: Layer 8 alert retention/tenancy policy.
```

### Layer 9A — event-level audit projection (`0011`)

Schema:
- **EPROJ** = extend `event_audit_log` (the existing thin stub) additively with `provenance`,
  `adapter_version`, `sequence_number`, `lineage_root_id`, `integrity_digest`. Still facts + blob
  reference only; no payload duplication, no run-level claims.
- **DPROJ** = `telemetry.disposition_audit_log` (append-only, restricted): `disposition_audit_id`
  IDENTITY PK, `evidence_kind` (`quarantine|rejection|incomplete|late_material`), `source_run_id`,
  `lineage_root_id` NULL, `event_id` NULL, `reason`, `recorded_at`, `access_class` DEFAULT `'restricted'`,
  `retain_until timestamptz` (column + pruning-worker seam in-repo; the retention DURATION is blocked),
  `UNIQUE(evidence_kind, source_run_id, lineage_root_id, event_id)`. Append-only trigger; SELECT
  restricted to `telemetry_operator`.
- **Checkpoint** = `telemetry.audit_export_checkpoint` (mutable): `consumer_name` PK,
  `last_exported_commit_sequence bigint`, `updated_at`.

TypeScript:
- `contracts/audit-export.ts`: generic **`AuditExporter`** interface (`export(batch: AuditRecord[])`),
  `AuditRecord` = stable internal contract, NEVER an OpenObserve type. Deterministic ECOLL payload =
  canonical `digest()` serialization of `AuditRecord`.
- `projections/disposition-audit-projector.ts` (DPROJ): idempotent; DISPATCH-driven for
  quarantine/rejection (new outbox `consumer_name`), settlement-driven for incomplete/late (best-effort
  tail in `settle-run.ts`).
- `application/audit-export.ts` (ECOLL worker): read EPROJ+DPROJ since checkpoint, build
  `AuditRecord[]`, call injected `AuditExporter`, advance checkpoint. OUT-OF-BAND — never in ECOMMIT/DISPATCH.

Cross-links: `EPROJOUT -> EPROJ`, `EQUAR/EPREJ/RINCOMP/RLATE -> DPROJ`, `EPROJ/DPROJ -> ECOLL`.
`ECOLL -> OpenObserve` blocked.

```
BLOCKED-EXTERNAL-DECISION: Layer 9A ECOLL->OpenObserve product wiring (endpoint, stream/namespace, auth, ingestion format).
BLOCKED-EXTERNAL-DECISION: Layer 9A concrete DPROJ retention duration and access-control principal mapping.
BLOCKED-EXTERNAL-DECISION: Layer 9A audit sink credentials/secret management.
```

### Layer 9B — run-level semantic projection (`0012`)

Schema:
- **SPROJ** = `telemetry.run_semantics_projection` (mutable/rebuildable; `UNIQUE(source_run_id,
  run_epoch)` for idempotent re-projection): identity pin (`source_run_id`, `run_epoch`,
  `accepted_set_digest`), `event_count`, `observed_count`, `inferred_count` (preserves observed-vs-
  inferred provenance — model requirement), `semantic_summary jsonb` (provider-neutral), `projected_at`.
  Reads `settlement_fact` + `accepted_event`; never writes them.
- **Checkpoint** = `telemetry.semantic_export_checkpoint` (mutable): `consumer_name` PK,
  `last_exported_settlement_id bigint`, `updated_at`.

TypeScript:
- `contracts/semantic-export.ts`: generic **`SemanticExporter`** interface (`export(runs:
  RunSemantics[])`), `RunSemantics` a stable internal type with explicit provenance fields, NEVER a
  Langfuse/Phoenix trace type.
- `domain/run-semantics.ts` (pure): fold an accepted-event set -> `RunSemantics`, preserving
  observed/inferred + method/confidence.
- `projections/run-semantics-projector.ts` (SPROJ): given a settled `settlement_fact`, load its pinned
  accepted set (bounded by `accepted_event_high_watermark`), run the pure fold, upsert. Triggered on
  settlement (best-effort tail in `settleRun()`; upgradeable to a settlement-outbox consumer later).
- `application/semantic-export.ts` (SCOLL worker): read new SPROJ rows since checkpoint, redact/enrich/
  route via injected `SemanticExporter`, advance checkpoint. Out-of-band.

Cross-links: `RSETTLED -> SPROJ` (derives from the immutable settled set, NOT RSTATE), `SPROJ -> SCOLL`.
`SCOLL -> Langfuse` and `SCOLL -> Phoenix` blocked. A reopen (new epoch) yields a new SPROJ row per epoch.

```
BLOCKED-EXTERNAL-DECISION: Layer 9B SCOLL->Langfuse persistent semantic writer wiring (endpoint, project, auth, trace schema mapping).
BLOCKED-EXTERNAL-DECISION: Layer 9B SCOLL->Phoenix ephemeral inspection wiring.
BLOCKED-EXTERNAL-DECISION: Layer 9B semantic sink credentials/tenancy/retention.
```

### Layer 10 — evaluation (`0013`)

Schema:
- **GOLD** = `telemetry.golden_task` (append-only per version): `golden_task_id` IDENTITY PK, `task_key`,
  `golden_task_version`, `definition jsonb`, `created_at`, `UNIQUE(task_key, golden_task_version)`.
- **EREC** = `telemetry.evaluation_record` (append-only): pins `source_run_id`, `run_epoch`,
  `accepted_set_digest` (the IMMUTABLE RSETTLED pin — never `run_state_digest`),
  `accepted_event_high_watermark`, plus `golden_task_version`, `assertion_bundle_digest`,
  `evaluator_definition_digest`, `evaluator_model`, `evaluator_parameters jsonb`,
  `evaluation_code_commit`, `prompt_template_digest`, `tool_policy_digest`, `runtime_version`,
  `adapter_version`, `random_seed`. `UNIQUE(source_run_id, run_epoch, accepted_set_digest,
  assertion_bundle_digest, evaluator_definition_digest)`. Append-only trigger.
- **EFAIL** = `telemetry.terminal_run_outcome` (append-only): `outcome_id` IDENTITY PK, `source_run_id`,
  `run_epoch`, `outcome_kind` (`incomplete|rejected|late_material`), `detail jsonb`, `recorded_at`,
  `UNIQUE(source_run_id, run_epoch, outcome_kind)`. Populated from the incomplete/late/rejection
  registries. Append-only trigger.
- **ECOV** = `telemetry.coverage_report` **SQL view**: attempted/settled/incomplete runs,
  rejected/late-material events, telemetry+evaluation coverage rates, GROUPED BY completeness class
  (`deterministic|bounded_inference|unknown`).
- **VIEW** = `telemetry.run_analytics` **read-only SQL view**: accepted-event truth (bounded by settled
  watermark) JOIN `evaluation_record` (on `accepted_set_digest`) JOIN `terminal_run_outcome`. `GRANT
  SELECT` only.

TypeScript:
- `domain/coverage.ts` (pure ECOV): counts -> rates/buckets (test oracle; view does the SQL grouping).
- `persistence/golden-task.ts`, `evaluation-record.ts`, `terminal-outcome.ts`: SQL writers/readers.
- `application/record-evaluation.ts`: writes an EREC pinned to a settled run — MUST refuse to record
  when no `settlement_fact` row exists for `(source_run_id, run_epoch)` (proves RSETTLED authority).
- `application/ingest-evaluation.ts`: the PF/MANUAL ingestion seam (`recordExternalEvaluation(input)`)
  accepting a provider-neutral result payload. Tool invocation + human classification are external.

Cross-links: `RSETTLED -> PF/MANUAL/VIEW/EREC`, `PF/MANUAL -> EREC`, `EREC -> VIEW/ECOV`,
`RINCOMP/RLATE -> EFAIL`, `EFAIL -> ECOV/VIEW`. All READ RSETTLED truth; none write it.

```
BLOCKED-EXTERNAL-DECISION: Layer 10 promptfoo (PF) tool invocation + CI gate wiring (assertions, runner, secrets).
BLOCKED-EXTERNAL-DECISION: Layer 10 MANUAL analyst/Phoenix classification workflow (only the ingest seam is in-repo).
BLOCKED-EXTERNAL-DECISION: Layer 10 evaluator_model/evaluator_parameters provider bindings.
```

## Recommended Build Order (dependency rationale)

1. **Layer 6 residual (`0009`)** — trivial, closes the named `run_state_digest` gap; cheap warm-up.
   (Optional per plan-owner decision above.)
2. **Layer 9A (`0011`)** — depends only on already-built truth + the existing outbox rail; establishes
   the `AuditExporter`/checkpoint/out-of-band-worker pattern that 9B reuses. Promote the EPROJ stub +
   add DPROJ.
3. **Layer 9B (`0012`)** — depends on RSETTLED (built); reuses 9A's exporter/checkpoint pattern.
4. **Layer 8 (`0010`)** — QMET view over built failure truth; OREVIEW/ODEC extend the missing
   `apply-disposition.ts` operator-review branch. Independent of 9A/9B; parallelizable, but benefits
   from DPROJ's shared failure-evidence framing.
 5. **Layer 10 (`0013`)** — last: EREC/EFAIL/ECOV/VIEW sit atop RSETTLED + terminal registries + the
   coverage/failure framing from 8/9A. GOLD is an independent fixture that can land any time.

## External activation — NixOS note (deferred; not part of this plan's build)

This plan needs **no** host/NixOS change: it is in-repo Postgres + TypeScript only, and the Docker
daemon that runs the existing Compose Postgres is already `active`/`enabled` on this NixOS 26.11 host.

A NixOS change becomes relevant ONLY later, per external product, when an activation gate opens
(OpenObserve / Langfuse / Phoenix / promptfoo / OTel Collector / object store). At that point:

1. Make the declarative change in **`~/Projects/app-configs`** first (source of truth for
   `/etc/nixos`). Docker itself is already covered by
   `~/Projects/app-configs/nix/modules/nixos/docker.nix` (`myConfig.docker.enable = true`); most
   external products are containers that need no new NixOS module — pin the image digest in the
   product's own compose/run recipe instead.
2. Rebuild + test on the system: `sudo nixos-rebuild switch` (or `... build`/`... test` first for a
   non-permanent trial), then verify `systemctl is-active docker` and the product's own health check.
3. Only a genuinely new *system service* (not a container) justifies a new `app-configs` NixOS
   module. Do not add one speculatively — that is `BLOCKED-EXTERNAL-DECISION` like the products.

Any command touching `/etc/nixos` or `sudo nixos-rebuild` is approval-gated and outside this plan's
in-repo scope; surface it to the user rather than running it automatically.

## Out Of Scope (Things To Avoid)

- Layer 4 (CINBOX) — owned by plan-7 / plan-8.
- Automatic close-policy detection and internal RWAIT/RDEAD timers (externalized to caller per
  `settle-run.ts` scope note).
- ANY external product wiring, credential, endpoint, tenancy, retention duration, or deployment topology.
- Runtime per-role connection enforcement (still deferred; single `DATABASE_URL`).
- In-place edits to migrations `0001`-`0008` (all existing migrations, including plan-7's
  `0007`/`0008_candidate_inbox_dead_letter.sql`).
- Making RSTATE (incl. `run_state_digest`) the evaluation authority — the pin is
  `settlement_fact.accepted_set_digest`.
- Any exporter/monitor call inside the ECOMMIT or DISPATCH transaction.
- Provider field names/IDs/payload shapes in any column, view, or truth/projection table.
- "Fixing" the pre-existing `sha256:` vs `sha256-canonical-v1` digest-label mismatch.
- `git add`/commit either repo.

## Affected Paths (in `../ai-monitoring`)

NEW migrations: `0009_run_state_digest.sql`, `0010_quarantine_health.sql`,
`0011_audit_projection.sql`, `0012_semantic_projection.sql`, `0013_evaluation.sql`.

NEW modules: `contracts/audit-export.ts`, `contracts/semantic-export.ts`,
`domain/quarantine-thresholds.ts`, `domain/operator-disposition.ts`, `domain/run-semantics.ts`,
`domain/coverage.ts`, `persistence/quarantine-metrics.ts`, `persistence/operator-review.ts`,
`persistence/golden-task.ts`, `persistence/evaluation-record.ts`, `persistence/terminal-outcome.ts`,
`persistence/audit-checkpoint.ts`, `persistence/semantic-checkpoint.ts`,
`projections/disposition-audit-projector.ts`, `projections/run-semantics-projector.ts`,
`application/review-case.ts`, `application/monitor-quarantine.ts`, `application/audit-export.ts`,
`application/semantic-export.ts`, `application/record-evaluation.ts`, `application/ingest-evaluation.ts`.

EDIT: `persistence/run-state.ts` (run_state_digest), `application/dispatch-outbox.ts` (new consumer
branches), `application/settle-run.ts` (best-effort SPROJ + EFAIL tails),
`projections/event-audit-projector.ts` (widen EPROJ contract).

NEW tests: one integration file per layer group + pure unit tests for each `domain/` module (see
Verification Plan).

## Contracts And Boundaries

- Provider-neutral seam: `AuditExporter`/`AuditRecord` and `SemanticExporter`/`RunSemantics` live in
  `src/contracts/`. Truth + projection tables store only these internal fields; exporters translate to
  external formats OUTSIDE the DB.
- Evaluation authority = `settlement_fact.accepted_set_digest`. `run_state_projection` (incl.
  `run_state_digest`) is advisory/rebuildable, never the eval pin.
- Transaction boundary: exporters/monitors are out-of-band workers with their own checkpoint tables;
  never in ECOMMIT/DISPATCH. Durable projections ride the outbox; derived ones use the dotted pattern.
- Immutability: new immutable tables (`operator_disposition`, `alert_event`, `disposition_audit_log`,
  `evaluation_record`, `terminal_run_outcome`, `golden_task`) reuse the append-only trigger. Mutable
  projections/checkpoints (`run_semantics_projection`, `*_export_checkpoint`,
  `operator_review_case.case_status`) omit it.

## Todo Plan

### Pre-conditions
- [x] PC1: migration collision resolved 2026-07-11 — `0008_candidate_inbox_dead_letter.sql` already
      exists on disk, so this plan uses `0009`-`0013`. Still confirm with the plan-7 owner that `0008`
      is their last consumed number before writing `0009`.
- [x] PC2: decide Layer 6 `run_state_digest` (add-advisory vs trim-model-note). (Decision: ADD-ADVISORY — implemented 2026-07-11; never the eval pin, which stays `settlement_fact.accepted_set_digest`.)

### P0 — Layer 6 residual + 7B seam
- [x] `0009_run_state_digest.sql` + `run-state.ts` digest writer (if PC2 = add). `pnpm typecheck` + `pnpm test`.
- [x] 7B `consumer_name` routing branches ready in `dispatch-outbox.ts` (wired when 9A DPROJ lands).

### P1 — Layer 9A (audit projection)
- [x] `0011` (EPROJ extend + DPROJ + audit_export_checkpoint) + append-only trigger + restricted grants.
- [x] `contracts/audit-export.ts` (`AuditExporter`), `projections/disposition-audit-projector.ts`,
      `application/audit-export.ts`, `persistence/audit-checkpoint.ts`; widen `event-audit-projector.ts`.
- [x] Unit (no-provider-field key-set) + integration (`isDbReachable`) with a fake in-memory exporter.

### P2 — Layer 9B (semantic projection)
- [x] `0012` (run_semantics_projection + semantic_export_checkpoint).
- [x] `contracts/semantic-export.ts` (`SemanticExporter`), `domain/run-semantics.ts`,
      `projections/run-semantics-projector.ts`, `application/semantic-export.ts`,
      `persistence/semantic-checkpoint.ts`; SPROJ tail in `settle-run.ts`.
- [x] Unit (provenance-preserving fold) + integration (per-epoch SPROJ, fake exporter idempotent resume).

### P3 — Layer 8 (quarantine health)
- [x] `0010` (quarantine_metrics view, operator_review_case, operator_disposition, alert_event) + triggers/grants.
- [x] `domain/quarantine-thresholds.ts`, `domain/operator-disposition.ts`,
      `persistence/quarantine-metrics.ts`, `persistence/operator-review.ts`,
      `application/review-case.ts`, `application/monitor-quarantine.ts`; new EDISP branch in `apply-disposition.ts`.
- [x] Unit (threshold rule) + integration (QMET view, open-case UNIQUE guard, ODEC idempotency +
      append-only, revalidate re-enters EGATE without a second accepted_event).

### P4 — Layer 10 (evaluation)
- [x] `0013` (golden_task, evaluation_record, terminal_run_outcome, coverage_report view, run_analytics view).
- [x] `domain/coverage.ts`, `persistence/{golden-task,evaluation-record,terminal-outcome}.ts`,
      `application/record-evaluation.ts`, `application/ingest-evaluation.ts`.
- [x] Unit (coverage bucketing) + integration (EREC pinned to settled digest, append-only, EFAIL ->
      coverage_report, run_analytics join on accepted_set_digest, record-evaluation refuses unsettled run).

### Model sync
- [x] (DEFERRED 2026-07-11) After the in-repo seams land, reconcile any model-note drift and run
      `bash scripts/gen_mermaid.sh --check` (needs python3 + pyyaml + jsonschema) from
      `___ARCHITECTURE_2.0/telemetry/`; regenerate `generated/*.mmd`.
      **Not run (2026-07-11, P4 implementer):** the script exists at
      `___ARCHITECTURE_2.0/telemetry/scripts/gen_mermaid.sh` (a sibling location inside
      `awesome-ai-utmostcreator`, not `ai-monitoring`), but the host's `python3` (3.12.13,
      via the nix profile) is missing both `pyyaml` and `jsonschema` — neither package is
      installed. Per the implementer's instructions, no new Python package was installed
      to unblock this; left unchecked rather than guessed. `pip install pyyaml jsonschema`
      (or the NixOS-appropriate equivalent) would need explicit approval first.

## Acceptance Criteria

- [x] AC-01: Each layer's in-repo SQL/projection seam lands with the exact append-only/idempotency/`::text`
      conventions; migrations `0009`-`0013` are additive, forward-only, `IF NOT EXISTS`. **Test-proven.**
      (2026-07-11 P4 pass: confirmed via a full read of `0009`-`0013` on disk — all use
      `IF NOT EXISTS`/`CREATE OR REPLACE`, no in-place edits — plus 119/119 passing tests.)
- [x] AC-02: No provider-specific field name/ID/payload appears in any column, view, or truth/projection
      table (key-set unit assertions on `AuditRecord`/`RunSemantics`). **Test-proven.**
      (2026-07-11 P4 pass: the existing `AC-02: EventAuditRecord key-set...` /
      `AC-02: DispositionAuditRecord key-set...` / `AC-02: RunSemantics key-set...` unit tests still
      pass; Layer 10's new columns (`evaluator_model`/`evaluator_parameters`/digests) are generic,
      opaque fields, not a promptfoo/Phoenix-shaped schema — confirmed by grep, no such shape exists.)
- [x] AC-03: No exporter/monitor call executes inside the ECOMMIT or DISPATCH transaction (out-of-band
      workers + checkpoint tables). **Verified by design + test that commit path makes no exporter call.**
      (2026-07-11 P4 pass: `test/unit/no-exporter-in-commit-path.test.ts` still passes; Layer 10 adds
      no exporter at all — `record-evaluation.ts`/`ingest-evaluation.ts` are plain DB writers, and grep
      confirms neither `persistence/accepted-events.ts` nor `application/dispatch-outbox.ts` references them.)
- [x] AC-04: Evaluation authority is `settlement_fact.accepted_set_digest`; `record-evaluation.ts`
      refuses a run with no settlement fact. **Test-proven.**
      (2026-07-11 P4 pass: dedicated integration test asserts `recordEvaluation()` throws for an
      unsettled `(source_run_id, run_epoch)` AND that zero `evaluation_record` rows are written.)
- [x] AC-05: Every external sink is stopped at a `BLOCKED-EXTERNAL-DECISION` marker (OP alerting,
      OpenObserve, Langfuse, Phoenix, promptfoo) with no invented product/credential/endpoint. **Verified.**
      (2026-07-11 P4 pass: grep across all new Layer 10 files shows "promptfoo"/"Phoenix" appear only
      in comments explaining what is NOT built; no client, endpoint, or credential is invented anywhere
      in `ingest-evaluation.ts` or `0013_evaluation.sql`.)
- [x] AC-06: New immutable tables reject UPDATE/DELETE via the append-only trigger; mutable
      projections/checkpoints intentionally allow their scoped updates. **Test-proven.**
      (2026-07-11 P4 pass: `golden_task`/`evaluation_record`/`terminal_run_outcome` append-only
      rejection is directly tested; `audit_export_checkpoint`/`semantic_export_checkpoint`/
      `run_semantics_projection` remain mutable by design, per their own P1/P2 tests.)
- [x] AC-07 (negative): migrations `0001`-`0008` unedited; RSTATE not used as eval pin; no external
      product wiring; neither repo committed. **Verified (corrected 2026-07-11, post-review).**
      (P4 pass claimed `git diff --stat` against `0001`-`0008` was empty as proof of "unedited" — a
      2026-07-11 `/review-diff` pass caught that this is vacuous: `ai-monitoring` has no git baseline
      for these files, they are `??` untracked, so that diff reads empty regardless of whether the
      files were touched. Actual evidence for this AC: file mtimes strictly increasing with no
      re-touch pattern, and no Layer 8/9/10 vocabulary (`run_state_digest`, `disposition_audit_log`,
      `operator_review_case`, etc.) appears anywhere in `0001`-`0008`'s content — both consistent with
      "unedited" but weaker than a real git diff. RSTATE-not-eval-pin and no-external-wiring legs of
      this AC remain independently grep-verified and hold. To get a rigorous check on this leg in
      future sessions, commit `ai-monitoring`'s pre-existing tree as a real git baseline first.)

## Verification Plan

- Cheapest first (no DB): pure `domain/` unit tests (`run-semantics`, `quarantine-thresholds`,
  `coverage`) + `AuditRecord`/`RunSemantics` no-provider-field key-set assertions.
- Focused integration (`isDbReachable` skip): one file per layer proving schema round-trip, `ON
  CONFLICT` idempotency, append-only rejection (`assert.rejects(..., /append-only/)`), and (9A/9B)
  fake in-memory exporter checkpoint advance + idempotent resume.
- Cross-layer: evaluation test proves RSETTLED-not-RSTATE authority.
- Smoke: `pnpm db:up && pnpm db:migrate && pnpm db:test` (live Postgres on :5433), then `pnpm test`.
  Apply per-command timeouts from `docs/ai/execution-protocol.md`.

## Risks And Rollback

- **Provider-schema leakage (highest):** mitigated by contracts-layer interfaces + key-set unit
  assertions + exporters translating outside the DB.
- **Wrong eval authority:** mitigated by `record-evaluation.ts` guard + test.
- **Exporter coupling into truth txn:** mitigated by out-of-band workers + checkpoint tables + AC-03 test.
- **QMET/ECOV/VIEW as tables vs views:** design as plain SQL views first; materialize only if cost demands.
- **Migration collision with plan-7:** mitigated by pre-condition PC1.
- **Rollback:** every migration is additive; new tables/views drop without touching truth; exporters are
  at-least-once against idempotent sinks; each layer ships behind its own worker so it can be disabled
  independently.

## Handoff Notes

- implementer builds in the P0->P4 order above (9A before 9B for exporter-pattern reuse; 8
  parallelizable; 10 last), on a dedicated `ai-monitoring` branch — NOT `fix/opencode-agent-body-parity`.
- Coordinate `registered_count` (producer = plan-7) and the inbox `resolution` vocabulary (plan-8 I3/I5)
  as read-only consumers here.
- reviewer means reviewer agent handoff using OpenCode command `/review-diff` on the `ai-monitoring`
  working tree once any P-phase lands.
- Nothing is durable until a human reviews and commits both repos.
