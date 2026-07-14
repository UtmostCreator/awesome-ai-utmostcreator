# Architecture Plan — Telemetry pipeline hardening, drift repair, and first producer slice

- Ticket: `arch-todo-telemetry-evidence-pipeline-20260711-132347` (continuation).
- Generated: 2026-07-12; **updated 2026-07-12** (folded in plan-2's unfinished
  ledger-artifact removal as Phase 6; recorded predecessor-plan archive status).
- Author: architect pass over research + two reviewer audits (this session).
- **Predecessor plan status (verified 2026-07-12):**
  - `archive/DONE-plan-5`, `DONE-plan-8`, `DONE-plan-9` — complete, already archived.
  - `archive/DONE-plan-3`, `DONE-plan-6`, `DONE-plan-7` — **complete, archived 2026-07-12**
    (domain refactor, build-hygiene/review fixes, and CINBOX inbox hop all verified landed
    in `../ai-monitoring`).
  - `archive/SUPERSEDED-plan-1` — its ledger-fold half was cancelled (machine 7 removed);
    its non-ledger layers were delivered by plan-3/6/7 + DONE-plan-9. **Archived as
    superseded 2026-07-12**; no live actionable items remained.
  - **plan-2 (ledger-artifact removal) is NOT done** — the model-side removal happened,
    but the approval-gated PHP artifact deletion did not. Its remaining live work is
    absorbed into **Phase 6** below and plan-2 is superseded by this plan.
- Canonical model: `___ARCHITECTURE_2.0/telemetry/model/telemetry.yaml` (325 lines).
- Implementation target repo: **`/home/utmostcreator/Projects/ai-monitoring`** (sibling;
  NOT this repo). External-edit approval for `../ai-monitoring` already granted for this
  telemetry work (plan-7 / plan-9 precedent). This plan file is tracked here because this
  ticket is the durable plan home.

> **Completion instruction:** when every `## Todo Plan` and `## Acceptance Criteria` item
> is `[x]` (or explicitly deferred), rename to
> `DONE-plan-10-telemetry-hardening-and-producer-slice.md` and move into `archive/`.

---

## Context (grounded, evidence-cited)

This plan reconciles a prior optimistic chat assessment against two independent reviewer
audits and the actual repo state. Key grounded facts:

1. **Layers 8/9A/9B/10 are largely built, not "NEW".** 15 of 21 model node IDs
   (`QMET`, `OREVIEW`, `ODEC`, `EPROJ`, `DPROJ`, `ECOLL`, `SPROJ`, `GOLD`, `EFAIL`,
   `EREC`, …) are implemented with DB integration tests in `../ai-monitoring`
   (migrations `0010`–`0013`). `OO`/`LF`/`PHX` are documented `BLOCKED-EXTERNAL-DECISION`
   absences. This is already recorded in `archive/DONE-plan-9-*.md`, but
   `___ARCHITECTURE_2.0/telemetry/IMPLEMENTATION_PLAN.md:339-378` still marks those layers
   `NEW`/all-unchecked — that is the real drift.

2. **CI does not prove the DB core.** `../ai-monitoring/.github/workflows/ci.yml:18-47`
   runs `typecheck` + `test:schema` (unit) only; the entire `test/integration/**/*.test.ts`
   DB suite that proves Layers 6–10 is skip-gated and **never runs in CI**. Every
   append-only trigger, ECOMMIT identity constraint, outbox-idempotency, settlement-digest,
   and reopen-CAS property is currently proven only on a developer laptop.

3. **`../ai-monitoring/model/` is empty** despite `README.md`/`INFRASTRUCTURE.md` claiming
   `telemetry.yaml` is vendored there.

4. **Settlement is independent of RSTATE (verified this session).**
   `../ai-monitoring/src/application/settle-run.ts:50-68` computes `accepted_set_digest`
   by querying `telemetry.accepted_event` **directly** (`WHERE source_run_id=$1 AND
   commit_sequence <= $2 ORDER BY commit_sequence`), never reading the `run_state_projection`
   (RSTATE). RSTATE is only stamped as an *output* (line ~194). Consequence: the dotted-edge
   "non-authoritative projection" design (`telemetry.yaml:265,282,292`) cannot corrupt a
   settlement digest — a stale RSTATE is harmless to settlement correctness. This
   **invalidates** the pasted "P0.5" rationale ("prove the projection is crash-safe or
   settlement consumes a stale projection"): that coupling does not exist in the code.

5. **The real settlement gap is a caller-asserted watermark with no freshness guard.**
   `settle-run.ts:73` (`high_watermark` is caller-supplied) and the module header
   (`settle-run.ts:7-13`) admit no component compares the asserted watermark against
   `MAX(commit_sequence)` or registered-vs-accepted lineage completeness before writing the
   immutable settlement fact. A caller can seal an under-complete prefix.

6. **Terminology & OTel-durability "corrections" in the pasted assessment target the chat
   summary, not the repo.** `telemetry.yaml:81` already labels CINBOX "Durable … candidate
   inbox / pre-validation ingress" and `:86` labels ESTORE "Accepted append-only evidence
   store"; the header (`:9-17`) already grounds the Collector exclusion in domain/topology
   authority ("transport + observability projection ONLY … downstream of RSETTLED"), and
   explicitly acknowledges the Collector WAL ("Collector WAL reduces but does not eliminate
   projection loss"). No repo change is required for these two "corrections."

7. **TimescaleDB canonical-table conflict is real.** `../ai-monitoring` canonical tables
   carry time-column-free unique constraints — `UNIQUE(event_id)` (`0001_foundation.sql:129`),
   `UNIQUE(runtime_id, producer_instance_id, source_run_id, source_event_key)` (`:131`),
   partial `UNIQUE(lineage_root_id) WHERE disposition='accepted'` (`:137-139`),
   `settlement_fact UNIQUE(source_run_id, run_epoch)` (`:221`). A hypertable would force a
   partition/time column into every unique index, destroying global `event_id` uniqueness.
   So "never hypertable canonical tables; metrics-only" is well-founded — but there are
   **zero metric-producer tables today**, so building a `migrations/optional/timescaledb`
   split + CI job now is premature.

---

## Owners & boundaries

- **`../ai-monitoring` (external, approved):** all code/migration/CI/test changes land here.
- **This repo (`awesome-ai-utmostcreator`):** only `IMPLEMENTATION_PLAN.md` doc-sync and this
  ticket folder. `telemetry.yaml` needs **no** structural change for this plan.
- **Out of scope / do not invent:** external product wiring (OpenObserve/Langfuse/Phoenix
  endpoints, credentials, tenancy, retention durations), Timescale migration directories,
  Kafka/NATS, Kubernetes, LiteLLM. These stay `BLOCKED-EXTERNAL-DECISION` or deferred.

## Risk posture

- **Risk class: medium.** No production system exists yet; the dominant risk is *silent
  correctness rot* (untested DB contracts) and *doc-vs-code drift misleading future work*.
- **Rollback:** every phase is additive (CI job, tests, doc edits, a caller-side guard). No
  canonical schema or accepted-fact semantics change. Revert = drop the added CI job / test
  file / guard function; no data migration.
- **Success signal:** a fresh-Postgres CI run turns the Layer 6–10 DB properties from
  laptop-only into gate-enforced green, and `IMPLEMENTATION_PLAN.md` status matches
  `DONE-plan-9` reality.

---

## Todo Plan

### Phase 1 (P0) — CI-gate the existing PostgreSQL core — **DONE 2026-07-12**

- [x] Add a PostgreSQL 16 service container to `../ai-monitoring/.github/workflows/ci.yml`
      (GitHub Actions `services:` with health-check), as a **new job** alongside the existing
      typecheck/schema job (do not replace it). — new `db-test` job added; `actionlint` clean.
- [x] Run in the DB job, against a clean database: migrations `0001`→latest, `pnpm typecheck`,
      `pnpm test:schema`, `pnpm db:migrate`, `pnpm db:test` (the full
      `test/integration/**/*.test.ts` suite), and `scripts/gen_mermaid.sh --check` if a
      Python-with-pyyaml/jsonschema runtime is available (otherwise leave the model-gate as a
      separate follow-up and note it). — model-gate step deliberately deferred (noted in the
      job), all other steps wired.
- [x] Confirm the existing integration tests already cover, or add focused tests for, these
      **well-founded** failure paths (drawn from the pasted P0 list, minus the phantom
      projection-blocking scenario):
  - [x] candidate persists in CINBOX even when EGATE validation later fails — already covered
        (`gate.test.ts`).
  - [x] duplicate acceptance (same identity + same digest) adopts the existing event (EADOPT) —
        already covered (`commit.test.ts`).
  - [x] conflicting identity/different-digest routes to quarantine — already covered
        (`gate.test.ts`, `commit.test.ts`).
  - [x] outbox redelivery is idempotent **per `consumer_name`** — already covered
        (`dispatch.test.ts`).
  - [x] one consumer failing does not mark another consumer delivered — **was missing, added**
        (`dispatch.test.ts`).
  - [x] `accepted_set_digest` is stable across replay and row-insertion order — replay half
        **was missing, added** (`settlement.test.ts`); insertion-order half is structurally
        guaranteed by `ORDER BY commit_sequence`.
  - [x] clean-migration + documented rollback/idempotency behaves as claimed — idempotency
        covered; no rollback claim exists (migrations are intentionally forward-only).
- [x] **Exit condition:** all authoritative-state and recovery paths pass in CI against a
      fresh PostgreSQL instance. — verified locally against a reachable Postgres this session
      (59/59 integration tests, 65/65 schema tests, clean typecheck); the CI job itself has not
      yet had its first confirmed green run on a real GitHub push/PR — flagged as a follow-up.

### Phase 1.5 (P0.5, CORRECTED) — Close the *real* settlement-freshness gap — **DONE 2026-07-12**

> The pasted P0.5 test ("rebuild `terminally_rejected` → confirm settlement was blocked until
> catch-up") targets a coupling that does not exist: `settle-run.ts:50-68` reads
> `accepted_event` directly, never RSTATE. Do **not** encode that phantom coupling into a test.
> Target the actual gap instead.

- [x] Add a settlement-freshness guard (or an explicit, documented decision to keep it
      caller-asserted). Options to weigh in a short design note before coding:
  - compare the caller-asserted `high_watermark` against `MAX(commit_sequence)` for the
    `source_run_id` and reject / downgrade `completeness` when the caller names a prefix
    below the current max without a `settlement_basis` that justifies exclusion; or
  - require a registered-vs-accepted lineage-completeness assertion for
    `completeness=deterministic`.
  — **Option 1 implemented**: `assertWatermarkFreshness()` in `domain/settlement.ts`, called
  from `settle-run.ts`'s `settleRun()`; applies only when `completeness="deterministic"`
  (which already implies `settlement_basis="deterministic_close"`); rejects when the caller's
  `high_watermark` does not equal `MAX(commit_sequence)` for the run at settlement time. Option
  2 deferred: it needs CINBOX registered-lineage tracking, an acknowledged Layer 4 gap
  (nothing writes `candidate_inbox` yet) — building that now would be speculative
  infrastructure.
- [x] Add a test proving: settlement digests **exactly** the accepted-event prefix
      `<= high_watermark` regardless of RSTATE state, AND an under-complete watermark cannot be
      sealed as `completeness=deterministic` without the justifying basis. — existing test
      (lines 48-84) already proved the digest-correctness half; new test added
      (`settlement.test.ts`, "RSETTLE rejects an under-complete high_watermark...") proves the
      rejection half. Verified against a real Postgres this session.
- [x] Update `settle-run.ts:7-13` header + `IMPLEMENTATION_PLAN.md` Layer 6 scope note to
      state the chosen guarantee precisely (caller-asserted vs. guarded). — both updated.

### Phase 2 (P1) — Repair model/doc drift — **DONE 2026-07-12**

- [x] Update `___ARCHITECTURE_2.0/telemetry/IMPLEMENTATION_PLAN.md` Layers 8/9A/9B/10
      (current lines 364-406, shifted by Phase 1.5's Layer 6 edits) to match
      `archive/DONE-plan-9` reality, using a precise status vocabulary rather than a
      bare `[ ]`/`[x]`. Adopted enum:
      `PLANNED | IMPLEMENTED | LOCALLY_VERIFIED | CI_VERIFIED | CONFIGURED_NOT_CONNECTED |
      BLOCKED_EXTERNAL | DEFERRED`. — re-verified directly against `src/`/tests, not copied
      from the plan; nothing is `CI_VERIFIED` yet (the new `db-test` CI job has no confirmed
      green run on a real push/PR).
- [x] Record the known narrow gaps explicitly: `MON` `alert_event` write-path untested
      (confirmed); `ECOV`/`VIEW` have no `src/` reader wrapper (confirmed, views queried only
      by tests); `SCOLL` checkpoint-advance — **corrected, not a gap**: now integration-tested
      (`semantic-projection.test.ts`), stale claim fixed.
- [x] Resolve the `../ai-monitoring/model/` claim: vendored `telemetry.yaml` into
      `../ai-monitoring/model/telemetry.yaml` (byte-identical copy) with source path +
      SHA-256 digest (`9a68c66897e2c3913e297a4f4864e1df8de11f61425705ab25d9f31c0705b6a8`,
      cross-verified with `sha256sum` against both files) recorded in `README.md` and
      `INFRASTRUCTURE.md`.
- [x] (Optional, if cheap) add a CI check that fails when the vendored model digest diverges
      from the canonical `telemetry.yaml`. — **deferred, explicitly**: `../ai-monitoring`'s own
      GitHub Actions cannot see the `awesome-ai-utmostcreator` repo's file at all, so an
      automated cross-repo check cannot actually detect drift as scoped; manual `sha256sum`
      re-check after any canonical model edit is documented instead.

### Phase 3 (P2) — First runtime producer vertical slice

> Reframed from "long-term" to "next functional milestone": without producers, the system is a
> well-tested evidence DB with no evidence.

- [ ] Build the **OpenCode** producer first (hardest lifecycle: `session.idle` is not final;
      non-interactive `opencode run` process exit is stronger close evidence; you own the
      plugin). Emit the candidate envelope (`event_id`, `source_run_id`,
      `producer_instance_id`, `sequence_number`, `source_event_key`, `lineage_root_id`,
      `adapter_version`) into `CINBOX` with ack-after-durable-persist.
- [ ] Build the **Claude Code** producer second (strongest contrast: explicit lifecycle hooks,
      `InstructionsLoaded` context provenance, deterministic settlement basis).
- [ ] Prove the end-to-end slice for both:
      `runtime → adapter → CINBOX → gate → accepted/quarantine fact → projector → RSTATE →
      RSETTLED → semantic projection → Phoenix → promptfoo → immutable EREC`.
- [ ] Defer Copilot CLI (third) and Copilot VS Code (fourth) to a follow-up plan.

### Phase 4 (P3) — Protected content path, gated by capture scope

- [ ] Ship the first producer as **metadata-only** (event type, runtime/version, timestamps,
      model, token observations, tool name, path/path-digest, status, error class,
      `content_omitted_reason`) — no blob path required.
- [ ] Before enabling **full-content** capture (prompts, responses, tool args/outputs, file
      contents, shell output), implement Layer 5 `REDACT → DIGEST → BPUT → BLOB → BVERIFY →
      BAUDIT` with a storage-backend + encryption/retention decision.

### Phase 5 (external products) — deferred, decision-gated

- [ ] Keep **Phoenix** as the primary semantic backend; validate the end-to-end slice through it.
- [ ] Add **OpenObserve** for audit-event search only after real audit events exist.
- [ ] Treat **Langfuse** as a comparison experiment only after Phoenix shows a concrete
      capability gap (self-host is licence-free but operationally heavy: web+worker+Postgres+
      ClickHouse+Redis+S3). Do **not** enable it merely "because the free tier exists."
- [ ] Record a one-paragraph ADR for **TimescaleDB** as a future escape hatch for *derived,
      rebuildable metric tables only* — **no** migration-directory split or CI job until metric
      producers exist and exceed measured volume. Canonical tables never become hypertables
      (unique-constraint conflict, evidence above).

### Phase 6 (P1.b) — Finish the `ai-run-ledger` artifact removal (absorbs plan-2)

> **Target repo: THIS repo (`awesome-ai-utmostcreator`), not `../ai-monitoring`.** The model
> side of the ledger removal is done (`telemetry.yaml` machine 7 removed; layers 9B/10
> re-pointed at `RSETTLED`), but the approval-gated PHP artifact deletion never landed. This
> phase supersedes `plan-2-ai-run-ledger-removal.md`. Verified state 2026-07-12: the schema
> file and its registrations still exist; `generated/run_ledger.mmd` is **already gone**
> (that sub-item is done). Do this on a dedicated branch (current branch
> `fix/opencode-agent-body-parity` is unrelated).

**Remaining live work (source surfaces still referencing the schema):**

- [x] Remove the `ai-run-ledger.schema.json` entry from `tools/ai/ai_catalog_lib.php`
      (line ~448, hand-edited source map). — done 2026-07-12.
- [x] Remove the `ai-run-ledger.schema.json` file entry from `tools/ai/install/packs.php`. —
      done 2026-07-12.
- [x] Remove the `ai-run-ledger.schema.json` row from `docs/ai/schema-ownership.md` (line ~26). —
      done 2026-07-12.
- [x] Remove the `PO_LEDGER` node + its `PO_DURABLE → PO_LEDGER` edge + schema provenance line
      from `___ARCHITECTURE_2.0/internal_architecture/lifecycle-model/lifecycle/model/repo.runtime.yaml`,
      then regenerate its `generated/repo.runtime/*.mmd` (do not hand-edit the `.mmd`). — done
      2026-07-12; regenerated via `gen_mermaid.py --out generated/repo.runtime` (10 machines,
      12 cross-links, `PO_LEDGER`/`ai-run-ledger` absent from all `.mmd` output).
- [x] **🛑⁉️ approval-gated:** obtain explicit per-file deletion approval, then delete
      `schemas/ai/ai-run-ledger.schema.json`. (`generated/run_ledger.mmd` is already absent —
      no deletion needed.) — **approved and deleted 2026-07-12** via `git rm`;
      `validate-schemas.php` now reports 19 schemas (was 20).
- [x] Run `php tools/ai/generate-ai-catalog.php` to regenerate
      `packages/ai-universal-rules/catalog.json` and `docs/ai/catalog.md` without the entry. —
      **done by hand-edit 2026-07-12, not the generator**: the working tree has an unrelated,
      pre-existing mass deletion of `scripts/ai/*.sh` (23 files; present before this session,
      out of this slice's scope), so running the generator live would have also dropped those
      23 `ai-script` catalog entries, conflating an unrelated change into this diff. Reverted
      the contaminated regen and instead hand-removed only the `ai-run-ledger.schema.json`
      resource entry + `root:schema` count (2→1) from both `catalog.json` and `catalog.md`,
      matching what a clean-tree regen would produce for this change alone.
- [x] Tombstone `docs/tickets/arch-todo-ai-run-ledger-rollup-20260708-000000/plan.md` as
      superseded (pointer to this removal). — done 2026-07-12 (banner added in place; file not
      renamed/moved — only "tombstone as superseded" was requested, not an archive move).
- [x] Verify: `php tools/ai/validate-schemas.php` (schema count −1),
      `validate-ai-catalog.php`, `validate-generated-artifacts.php`, and
      `bash ___ARCHITECTURE_2.0/telemetry/scripts/gen_mermaid.sh --check` all pass; repo-wide
      search for `ai-run-ledger` returns only intentional permission-layer/governance
      references (out of scope) and this plan. — **verified 2026-07-12 to the extent this
      slice controls:** `gen_mermaid.sh --check` (telemetry) passes; `gen_mermaid.py --check`
      (repo.runtime) passes; `validate-schemas.php` now reports **19** schemas (was 20,
      confirming the −1). `validate-ai-catalog.php` and `validate-generated-artifacts.php`
      still fail, but on the exact same 23 pre-existing, unrelated `scripts/ai/*.sh`
      "missing resource" / drift errors present before this slice started (zero mention
      `ai-run-ledger`) — caused by an unrelated mass script deletion already in the working
      tree, out of this slice's scope; re-run once that is resolved. Repo-wide `ai-run-ledger`
      search re-confirmed clean post-deletion (only this plan, historical/comment references,
      and the explicitly-out-of-scope `ai-run-ledger-rollup-slice-a` permission ticket remain).
- [ ] **Do NOT touch** the per-event log surfaces (`30-logging.sh`,
      `evidence-event.schema.json`, `.ai-logs/tool-usage.jsonl`) or the
      `ai-run-ledger-rollup-slice-a` permission ticket — those share the name but are out of
      scope (plan-2 AC-04/AC-05).

---

## Things to avoid

- Do NOT add the phantom "projection rebuild blocks settlement" test — settlement does not
  read RSTATE (`settle-run.ts:50-68`).
- Do NOT restructure `telemetry.yaml` for the terminology/OTel-durability "corrections" — the
  model already states them correctly (`:9-17`, `:81`, `:86`).
- Do NOT scaffold `migrations/optional/timescaledb/`, an `analytics.timescaledb.enabled` flag,
  or a Timescale CI job now — zero metric rows exist; this violates the repo's
  "no speculative infrastructure" baseline.
- Do NOT convert any canonical table to a hypertable.
- Do NOT invent external product endpoints, credentials, tenancy, or retention durations.
- Do NOT let the Timescale CI job (if ever added) replace the plain-Postgres core job.

## Acceptance Criteria

- [x] `../ai-monitoring` CI runs the full DB integration suite against a fresh PostgreSQL 16 on
      every push/PR, and it passes. — job wired and locally proven equivalent (59/59
      integration tests green against a reachable Postgres); first real GitHub-hosted green run
      still unconfirmed (cannot execute Actions from this sandbox) — **follow-up**.
- [x] A settlement-freshness guarantee is either enforced by a guard+test or explicitly
      documented as caller-asserted in both `settle-run.ts` and `IMPLEMENTATION_PLAN.md`. —
      enforced by `assertWatermarkFreshness()` + test; documented in both files.
- [x] `IMPLEMENTATION_PLAN.md` Layers 8/9A/9B/10 status matches `DONE-plan-9` using the status
      enum; known narrow gaps (`MON`/`ECOV`/`VIEW`/`SCOLL`) are named. — done; `SCOLL` claim
      corrected (no longer a gap).
- [x] The `../ai-monitoring/model/` vendoring claim is true (vendored + digest) or removed. —
      vendored + digest recorded.
- [ ] At least the OpenCode producer lands the full metadata-only end-to-end slice with a test.
      — **not started this session**; Phase 3 (producer slice) is greenfield, multi-file
      feature work (new adapter/plugin code, envelope emission, CINBOX wiring, end-to-end
      proof) that does not fit a single bounded implementer slice — needs its own
      architect/plan-slice pass before implementation. Phases 4/5 remain untouched and gated
      behind Phase 3.
- [x] AC-ledger (Phase 6, absorbs plan-2 AC-01/02/03): `schemas/ai/ai-run-ledger.schema.json`
      no longer exists; no source surface (`ai_catalog_lib.php`, `packs.php`,
      `schema-ownership.md`, `repo.runtime.yaml`) references it; regenerated catalog + runtime
      diagrams contain no ledger entry; all four validators + `gen_mermaid.sh --check` pass. —
      **done 2026-07-12**: schema file deleted (approved); no source surface references it
      (confirmed by repo-wide search); catalog + runtime diagrams contain no ledger entry;
      `validate-schemas.php` and both `gen_mermaid` checks pass. `validate-ai-catalog.php` /
      `validate-generated-artifacts.php` still report errors, but only the 23 pre-existing
      unrelated `scripts/ai/*.sh` errors (see Phase 6 verify step) — zero `ai-run-ledger`
      errors from either.

## Verification scope

- Lowest sufficient proof first: `pnpm typecheck` → `pnpm test:schema` →
  `pnpm db:migrate && pnpm db:test` against the CI Postgres → `scripts/gen_mermaid.sh --check`
  for any model edit. Per-command timeouts per `docs/ai/execution-protocol.md`
  ("Long-Running Commands And Anti-Freeze Discipline").

## Recommended next step

Phases 1 / 1.5 / 2 / 6 are landed (`[x]` above, Phase 6 completed 2026-07-12). One track
remains:

- **Phase 3 (OpenCode producer slice)** — greenfield, multi-file feature work in
  `../ai-monitoring`; run a fresh `architect` / `plan-slice` pass first, then `implementer`.
  This was explicitly deferred this session: it is greenfield feature work (new
  adapter/plugin code, envelope emission, CINBOX wiring, end-to-end proof) that does not fit
  a single bounded implementer slice.

Separately, unrelated to this plan: the working tree carries a large pre-existing mass
deletion of `scripts/ai/*.sh` (23 files, staged/unstaged before this session started) that
currently fails `validate-ai-catalog.php` and `validate-generated-artifacts.php` with 23
"missing resource" / drift errors. That dirty state predates and is out of scope for this
plan, but should be resolved (either restored or intentionally committed) before those two
validators can be trusted again repo-wide.

The one open CI follow-up: confirm the first GitHub-hosted green run of the Postgres
integration job (Phase 1) once it can be executed outside this sandbox.
