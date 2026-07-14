# Architecture Plan — ai-monitoring CINBOX durable inbox hop (build-order step 4)

- Ticket: `arch-todo-telemetry-evidence-pipeline-20260711-132347` (same ticket as plan-1..plan-6)
- Source: `___ARCHITECTURE_2.0/telemetry/IMPLEMENTATION_PLAN.md` build order step 4 (`CINBOX -> EGATE`),
  the flagged CINBOX gap (§88, §185), and user design answers (2026-07-11)
- Generated: 2026-07-11
- Implementation target repo: `/home/utmostcreator/Projects/ai-monitoring` (sibling; NOT this repo)
- Plan file: `docs/tickets/arch-todo-telemetry-evidence-pipeline-20260711-132347/plan-7-ai-monitoring-cinbox-inbox-hop.md`

> **IMPLEMENTATION TARGET IS EXTERNAL.** All edits land in the sibling `../ai-monitoring`.
> This plan file is tracked here (with plan-1..6) because this ticket is the durable plan home.
> External-edit approval for `../ai-monitoring` already granted for this telemetry work.

> **Completion instruction:** When every `## Todo Plan` and `## Acceptance Criteria` item is
> `[x]`, rename to `DONE-plan-7-ai-monitoring-cinbox-inbox-hop.md` and move into `archive/`.

## Context

The foundation (Layers 4/6/7B) is built, refactored into
`contracts/domain/application/persistence/projections`, and green (31/31 tests, `#`-alias
imports). IMPLEMENTATION_PLAN build order step 4 (`CINBOX -> EGATE`) is the next unbuilt code
step. It is a **flagged real gap**, not merely deferred: `telemetry.candidate_inbox` and the
`telemetry_ingest` INSERT grant already exist (migration 0001/0002), but **nothing in `src/`
writes the inbox** — candidates route straight into EGATE, so the durable pre-validation hop,
`processing_lease`, ack-after-persist, and the `CINBOX -> RSTATE` registered projection are all
unimplemented.

### User design decisions (authoritative — do not re-litigate)

1. `submitCandidate` validates ONLY the minimal ingress envelope, inserts the FULL candidate
   into `candidate_inbox` in one committed txn, and returns an **ingress acknowledgment**.
2. The ingress ack means ONLY "candidate durably received" — it must NOT imply acceptance into
   ESTORE. Acceptance/adoption/quarantine/rejection is a SEPARATE, later result produced by EGATE.
3. A worker CLAIMS inbox rows via `processing_lease`, then passes the persisted candidate into EGATE.
4. **No parallel direct `submitCandidate -> EGATE` path.** One ingestion semantic only. Two paths
   would create dual semantics, weaken crash recovery, and let acked candidates bypass the
   authoritative `registered_count` source.

### Verified before planning (not assumed)

- `candidate_inbox` (0001:78-98) has `inbox_id` identity PK, full envelope + `payload jsonb`,
  `redaction_status`, `integrity_digest`, `ingress_timestamp`, `processing_lease timestamptz`,
  `UNIQUE(event_id)`. It has NO terminal-status column and NO claim index.
- `run_state_projection` (0004:44-55) has accepted/quarantined/correcting/terminally_rejected
  counts but NO `registered_count`.
- Current EGATE entry is `application/process-candidate.ts::submitCandidate(raw: unknown)` which
  runs schema validation then `commitAcceptedEvent` / quarantine directly (the path to replace).
- Migrations are forward-only and already applied through 0006 -> new work is a NEW migration
  `0007`, never an in-place edit of 0001/0004.

## Target Outcome

Producers hit an ingress function that durably persists a leased inbox row and returns an ack; a
worker claims leased rows (SKIP LOCKED), runs EGATE on the persisted candidate, records the
terminal disposition on the inbox row, and projects `registered_count`. The old direct
`submitCandidate -> EGATE` public path is removed. Zero behavior change to the EGATE decision
logic itself (adopt/quarantine/commit) — only its INPUT source and its CALLER change.

## Design

### New migration `0007_candidate_inbox_hop.sql` (additive only)

- `ALTER TABLE telemetry.candidate_inbox ADD COLUMN IF NOT EXISTS inbox_status text NOT NULL
  DEFAULT 'received'` — lifecycle: `received` -> `processing` -> `resolved` (or `failed`).
- `ALTER TABLE telemetry.candidate_inbox ADD COLUMN IF NOT EXISTS resolved_at timestamptz`.
- `ALTER TABLE telemetry.candidate_inbox ADD COLUMN IF NOT EXISTS resolution text` (the EGATE
  outcome kind: `inserted`/`adopted`/`quarantined`/`identity_digest_conflict`), nullable until resolved.
- `ALTER TABLE telemetry.candidate_inbox ADD COLUMN IF NOT EXISTS attempt_count integer NOT NULL DEFAULT 0`.
- `ALTER TABLE telemetry.candidate_inbox ADD COLUMN IF NOT EXISTS last_error text`.
- Partial claim index: `CREATE INDEX IF NOT EXISTS candidate_inbox_claimable_idx ON
  telemetry.candidate_inbox (inbox_id) WHERE inbox_status IN ('received','processing') AND resolved_at IS NULL`.
- `ALTER TABLE telemetry.run_state_projection ADD COLUMN IF NOT EXISTS registered_count integer NOT NULL DEFAULT 0`.
- `candidate_inbox` is NOT append-only (it is mutable working state, like `run_state_projection`);
  do not add an append-only trigger. Grants: `telemetry_ingest` already has INSERT/SELECT; add
  `UPDATE (inbox_status, processing_lease, resolved_at, resolution, attempt_count, last_error)` to
  whichever role the worker connects as (currently `telemetry_migrator` via single DATABASE_URL —
  keep single-connection reality, matching plan-6's role note; document, do not invent per-role wiring).

### New `persistence/candidate-inbox.ts`

- `insertInboxCandidate(candidate)` -> `{ inbox_id }`. Committed INSERT (idempotent on
  `event_id` via `ON CONFLICT (event_id) DO NOTHING RETURNING`, returning existing `inbox_id` on
  replay so a producer retry gets the same ack).
- `claimNextInboxRow(client, leaseSeconds)` -> leased row or null: `SELECT ... WHERE inbox_status
  IN ('received','processing') AND (processing_lease IS NULL OR processing_lease < now()) AND
  resolved_at IS NULL ORDER BY inbox_id FOR UPDATE SKIP LOCKED LIMIT 1`, then set
  `inbox_status='processing', processing_lease = now() + interval, attempt_count = attempt_count + 1`.
  Lease reclaim: an expired lease on a still-unresolved row is re-claimable (crash recovery).
- `markInboxResolved(inbox_id, resolution)` and `markInboxFailed(inbox_id, lastError)`.
- Row -> `CandidateEnvelope` reconstruction from the persisted columns/payload.

### New `application/ingest-candidate.ts` (the ONLY ingress)

- `ingestCandidate(raw: unknown): Promise<IngressAck>`:
  1. minimal ingress-envelope validation (presence + type of the identity/routing fields required
     to persist honestly: `event_id`, `source_run_id`, `integrity_digest`, envelope keys) — NOT the
     full JSON-Schema gate (that stays in EGATE, run by the worker).
  2. `insertInboxCandidate` (committed).
  3. project `registered_count` via a new best-effort `projectRegistered(source_run_id)`.
  4. return `{ kind: 'received', inbox_id, event_id }` — an ingress ack, explicitly NOT an
     acceptance result.
- A candidate that fails even minimal ingress validation is rejected at ingress (cannot be
  persisted honestly) and returns `{ kind: 'ingress_rejected', reason }` WITHOUT an inbox row.

### New `application/process-inbox.ts` (the worker loop, replaces the direct EGATE caller)

- `processInbox(maxBatch, leaseSeconds)`: claim -> reconstruct candidate -> call the existing EGATE
  decision (`runEgate` extracted from today's `submitCandidate` body) -> `markInboxResolved` with
  the outcome kind (or `markInboxFailed` + backoff on unexpected error). Concurrency-safe via SKIP
  LOCKED + lease; idempotent (a resolved row is never re-claimed).

### Refactor `application/process-candidate.ts`

- Rename the pure EGATE decision to `runEgate(candidate: CandidateEnvelope): Promise<CommitOutcome>`
  (the current body minus the entry name). `process-inbox.ts` calls it.
- REMOVE the public `submitCandidate(raw)` direct path. Update the two current callers/tests
  (`test/integration/gate.test.ts`, `test/integration/dispatch.test.ts`) to go through
  `ingestCandidate` + `processInbox` instead (proving the full hop end to end).

### `contracts/`

- Add `IngressAck` union and, if needed, a minimal `ingressEnvelope` guard. No schema file change
  required (the full `candidate.schema.json` stays the EGATE gate).

## Out Of Scope (Things To Avoid)

- Do NOT keep a direct `submitCandidate -> EGATE` path (user rule 4).
- Do NOT edit applied migrations 0001-0006 in place — new `0007` only.
- Do NOT add an append-only trigger to `candidate_inbox` (mutable working state).
- Do NOT build Layer 5 REDACT/BLOB here (`redaction_status` stays `pending`; separate plan behind
  a storage-neutral `BlobStore` interface per the user's Layer 5 answer).
- Do NOT implement encryption/retention policy for the inbox (deployment concern, deferred).
- Do NOT invent per-worker role connections (keep single `DATABASE_URL`, document the gap — plan-6 precedent).
- Do NOT `git add`/commit either repo.
- Agent CANNOT run `rm -rf`/`pnpm build` (hard-denied); build-hygiene proof stays a USER step if needed.

## Affected Paths (in `../ai-monitoring`)

- `src/persistence/migrations/0007_candidate_inbox_hop.sql` — NEW.
- `src/persistence/candidate-inbox.ts` — NEW.
- `src/persistence/run-state.ts` — EDIT (add `projectRegistered`).
- `src/application/ingest-candidate.ts` — NEW.
- `src/application/process-inbox.ts` — NEW.
- `src/application/process-candidate.ts` — EDIT (extract `runEgate`, remove public `submitCandidate`).
- `src/contracts/candidate.ts` — EDIT (add `IngressAck` + minimal ingress guard).
- `test/integration/gate.test.ts` — EDIT (drive via ingest + processInbox).
- `test/integration/dispatch.test.ts` — EDIT (same).
- `test/integration/candidate-inbox.test.ts` — NEW (ingress ack, lease claim, crash/lease-reclaim,
  registered_count, resolution recording, idempotent replay).
- `README.md` — EDIT (remove/repoint the CINBOX-gap note; document the inbox hop).

## Contracts And Boundaries

- Ingress ack != acceptance. `IngressAck.kind = 'received'` only. Acceptance is a later EGATE result.
- One ingestion path. `ingestCandidate` is the sole producer entry; EGATE runs only from the worker.
- Inbox insert is one committed txn; ack only after commit (ack-after-persist).
- Worker claim is SKIP LOCKED + lease; resolved rows never re-claimed; expired-lease unresolved rows
  ARE re-claimable (crash recovery), bounded by `attempt_count`.
- `registered_count` is best-effort dotted projection (like quarantined_count), NOT in the inbox txn's
  critical path for correctness — but it is now the authoritative registered source.
- EGATE decision logic (`runEgate`) is byte-for-byte the current logic; only input source + caller change.
- `domain/` and `contracts/` stay DB-free (AC-04/AC-05 discipline from plan-3 must still hold).

## Todo Plan

### P0 — migration + persistence
- [ ] Write `0007_candidate_inbox_hop.sql` (additive columns, claim index, `registered_count`, grants).
- [ ] `persistence/candidate-inbox.ts`: insert (idempotent), claim (lease + SKIP LOCKED), resolve/fail, row->envelope.
- [ ] `persistence/run-state.ts`: add `projectRegistered(source_run_id)`.
- [ ] `pnpm typecheck` + `pnpm test` green after P0.

### P1 — application ingress + worker
- [ ] `application/ingest-candidate.ts`: minimal validation -> insert -> project registered -> ingress ack.
- [ ] `application/process-inbox.ts`: claim -> runEgate -> resolve/fail; batch loop, backoff on failure.
- [ ] `application/process-candidate.ts`: extract `runEgate`; REMOVE public `submitCandidate`.
- [ ] `contracts/candidate.ts`: `IngressAck` union + minimal ingress guard.
- [ ] `pnpm typecheck` + `pnpm test` green after each extraction.

### P2 — tests + wiring + docs
- [ ] Rewire `test/integration/gate.test.ts` + `dispatch.test.ts` to ingest + processInbox.
- [ ] NEW `test/integration/candidate-inbox.test.ts`: ingress ack; idempotent replay (same inbox_id);
      lease claim single-consumer; expired-lease reclaim after simulated crash; registered_count bump;
      resolution recorded (inserted/quarantined); resolved row not re-claimed.
- [ ] README CINBOX-gap note updated to "built".
- [ ] Full ladder: `pnpm typecheck`, `pnpm test`, `pnpm test:schema`; confirm all AC.

## Acceptance Criteria

- [ ] AC-01: `ingestCandidate` persists a committed `candidate_inbox` row and returns an ingress ack
      whose kind is `received` (never an acceptance/quarantine result). **Test-proven.**
- [ ] AC-02: A producer replay of the same `event_id` returns the SAME `inbox_id` and does not create
      a second inbox row (idempotent ack). **Test-proven.**
- [ ] AC-03: `processInbox` claims a leased row (SKIP LOCKED), runs EGATE, and records `inbox_status`,
      `resolved_at`, `resolution` on the row; a resolved row is never re-claimed. **Test-proven.**
- [ ] AC-04: An unresolved row whose `processing_lease` has expired is re-claimable by a later worker
      pass (crash recovery), bounded by `attempt_count`. **Test-proven.**
- [ ] AC-05: `run_state_projection.registered_count` reflects ingress and is the authoritative
      registered source; `CINBOX -> RSTATE` registered projection wired. **Test-proven.**
- [ ] AC-06: No public direct `submitCandidate -> EGATE` path remains; all EGATE runs originate from
      the worker on persisted inbox rows (grep: no non-worker caller of `runEgate`). **Verified.**
- [ ] AC-07: EGATE decision behavior unchanged — the existing gate/dispatch assertions still pass with
      only their ingestion driver changed (adopt/quarantine/commit outcomes identical). **Test-proven.**
- [ ] AC-08 (negative): migrations 0001-0006 unedited; `candidate_inbox` has no append-only trigger;
      no Layer 5/encryption work; `domain/`+`contracts/` stay DB-free; neither repo committed. **Verified.**

## Verification Plan

- Agent runs: `pnpm typecheck`, `pnpm test` (live Postgres reachable on :5433, ~0.4s), `pnpm test:schema`
  after each P0/P1/P2 step. AC-04/AC-05 layering greps for `domain/`+`contracts/`.
- New failure/concurrency proof for lease reclaim uses lease-expiry simulation (set `processing_lease`
  into the past), same DB-skip-when-unreachable pattern as siblings.

## Risks And Rollback

- **Risk — removing the direct path breaks existing tests:** intended; rewire tests to the hop in the
  SAME change so the suite proves end-to-end ingestion, not two semantics.
- **Risk — lease reclaim double-processing:** mitigated by resolved rows being unclaimable +
  `attempt_count` cap + EGATE idempotency (EADOPT/quarantine already idempotent per identity/lineage).
- **Risk — ingress ack mistaken for acceptance:** the `IngressAck` type is deliberately disjoint from
  `CommitOutcome`; documented in code + README.
- **Rollback:** source edits revert by restoring prior content; `0007` is a new file (delete to revert);
  it is purely additive so an already-migrated DB is unaffected by reverting the source.

## Handoff Notes

- implementer works P0 -> P1 -> P2, verifying with the 31+ tests after each step.
- Layer 5 (`BlobStore` interface, REDACT/BPUT/BVERIFY) is the next plan after this, per the user's
  storage-neutral-interface answer — NOT scoped here.
- reviewer means reviewer agent handoff using OpenCode command `/review-diff` on the ai-monitoring
  working tree once P0/P1/P2 are complete and every AC is checked.
