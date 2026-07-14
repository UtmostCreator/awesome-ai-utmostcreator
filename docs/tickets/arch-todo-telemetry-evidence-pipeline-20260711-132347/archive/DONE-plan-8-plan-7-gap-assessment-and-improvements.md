# Architecture Plan — plan-7 (Layer 4 CINBOX hop) gap assessment + improvements

- Ticket: `arch-todo-telemetry-evidence-pipeline-20260711-132347` (same ticket as plan-1..plan-7)
- Source: read-only audit of `../ai-monitoring` working tree vs `plan-7-ai-monitoring-cinbox-inbox-hop.md`,
  performed 2026-07-11 (this session), against the canonical model
  `___ARCHITECTURE_2.0/telemetry/model/telemetry.yaml`.
- Generated: 2026-07-11
- Implementation target repo: `/home/utmostcreator/Projects/ai-monitoring` (sibling; NOT this repo)
- Plan file: `docs/tickets/arch-todo-telemetry-evidence-pipeline-20260711-132347/plan-8-plan-7-gap-assessment-and-improvements.md`

> **PURPOSE.** This plan (a) confirms which model layer plan-7 covers, (b) records — from a real
> file-by-file audit — exactly which plan-7 items are DONE vs MISSING in the working tree, and (c)
> proposes bounded IMPROVEMENTS on top of plan-7's own scope.

> **RECONCILED 2026-07-11 (second audit + reviewer pass).** The original audit below was written while
> plan-7 was still in flight and recorded its P1/P2 as PARTIAL/MISSING. A follow-up read-only audit of
> the `../ai-monitoring` working tree found **plan-7 is now fully implemented**: every gap G1-G6 is
> closed and improvements I2/I5 are already satisfied in code. The only genuine remaining improvement
> was **I1 (inbox worker attempt cap)**, which has now been **implemented and verified** in this pass
> (migration `0008`, `markInboxDeadLettered`, worker `recordFailure`, and a covering test; full DB
> suite 56/56). Each section below is corrected in place with `[x]` + file evidence and an
> `EVIDENCE (reconciled)` note. I3/I4 remain optional polish (see their entries).

> **Completion instruction:** With G1-G6 done, I1 landed + verified, and I2/I5 confirmed already
> satisfied, the only open items are the optional I3/I4. Once those are accepted or explicitly
> declined, rename to `DONE-plan-8-plan-7-gap-assessment-and-improvements.md` and move into `archive/`.

> **RE-VERIFIED 2026-07-12 (third audit, post plan-9 P0-P4 + a complexity-driven refactor pass).**
> Re-read every file this plan cites in `../ai-monitoring` after substantial unrelated work landed on
> top (plan-9's Layers 6-10, migrations `0009`-`0013`, and an extract-method refactor of
> `candidate-inbox.ts`'s `insertInboxCandidate`/`claimNextInboxRow`). **No drift, no regressions:**
> `markInboxDeadLettered`/`INBOX_MAX_ATTEMPTS=5`/the dead-letter claim-exclusion all survived the later
> refactor unchanged in behavior; `submitCandidate` still has zero code references (only comment
> prose); `runEgate` still has exactly one caller (`process-inbox.ts:76`); G6's `run-state.ts` comment
> is intact and was correctly extended (not overwritten) by plan-9's `run_state_digest` addition.
> `pnpm typecheck` clean; `candidate-inbox.test.ts` **7/7**; full suite **121/121** (this plan's
> recorded "56/56" is stale — expected growth from plan-9's later work landing in the same repo, not a
> discrepancy). The `⚠️ CROSS-PLAN MIGRATION-NUMBER COLLISION` warning below is **CONFIRMED RESOLVED**:
> plan-9 shifted to `0009`-`0013` exactly as this plan required (`0009_run_state_digest.sql` exists,
> `0008` stayed this plan's dead-letter migration, untouched). **I3/I4 — user decision obtained
> 2026-07-12: both ACCEPTED and implemented** (see their entries below; `pnpm typecheck` clean, full
> suite 121/121 after both). Every Todo Plan and Acceptance Criteria item is now `[x]` — this plan is
> moved to `archive/DONE-plan-8-...md` per its own completion instruction.

## Which layer is plan-7?

**plan-7 is Layer 4 — Canonical event truth (`event_truth` machine), specifically the CINBOX durable
inbox hop.** It builds the model's `CINBOX -> EGATE` edge ("persisted candidate, producer
acknowledged") and the `CAND -> CINBOX` edge ("persist candidate durably before ack"). Its nodes are
all Layer 4: `CINBOX`, `CAND`, `EGATE`. It also *writes* one Layer-6 projection column
(`run_state_projection.registered_count`) to satisfy the `CINBOX -> RSTATE "project: registered"`
dotted cross-link — but it does not own any Layer 6-10 node. This is a clean producer(plan-7) /
consumer(Layers 6-10) split on that single counter; see plan-9 for the consumer side.

## Audit method (verified, not assumed)

Files read in `../ai-monitoring` this session: `src/application/process-candidate.ts`,
`src/application/dispatch-outbox.ts`, `src/application/settle-run.ts`,
`src/persistence/candidate-inbox.ts`, `src/persistence/run-state.ts`,
`src/persistence/accepted-events.ts`, `src/persistence/pool.ts`,
`src/persistence/migrations/0001_foundation.sql`,
`src/persistence/migrations/0007_candidate_inbox_hop.sql`, `src/contracts/candidate.ts`,
`src/contracts/validate-candidate.ts`, `test/integration/gate.test.ts`,
`test/integration/dispatch.test.ts`, `test/integration/fixtures/candidate.ts`, `package.json`, and the
full `src/` + `test/` file listing.

## Status vs plan-7's Todo Plan

### plan-7 P0 (migration + persistence) — DONE

- [x] `0007_candidate_inbox_hop.sql` exists: adds `inbox_status`/`resolved_at`/`resolution`/
      `attempt_count`/`last_error`, the partial `candidate_inbox_claimable_idx`,
      `run_state_projection.registered_count`, and `GRANT UPDATE ON telemetry.candidate_inbox TO
      telemetry_ingest`. Additive, forward-only, one transaction — matches plan-7 exactly.
- [x] `persistence/candidate-inbox.ts` exists: `insertInboxCandidate` (idempotent
      `ON CONFLICT (event_id) DO NOTHING` + existing-row lookup on replay), `claimNextInboxRow`
      (`FOR UPDATE SKIP LOCKED`, lease via `now() + make_interval`, `attempt_count + 1`, expired-lease
      reclaim), `markInboxResolved`, `markInboxFailed`, and `toCandidate` row->envelope reconstruction.
- [x] `persistence/run-state.ts::projectRegistered(sourceRunId)` exists and bumps `registered_count`.

### plan-7 P1 (application ingress + worker) — DONE (reconciled)

- [x] `contracts/candidate.ts`: `IngressAck` union (`received` | `ingress_rejected`) AND a
      `validateIngressEnvelope()` minimal ingress guard are both present and correctly disjoint from
      `CommitOutcome`. (plan-7 only asked for the union + "minimal ingress guard, if needed" — both done.)
- [x] **DONE: `application/ingest-candidate.ts`** exists — the sole ingress entrypoint
      `ingestCandidate(raw): Promise<IngressAck>`: `validateIngressEnvelope` -> `insertInboxCandidate`
      -> `projectRegistered` (guarded on `inserted`) -> `{ kind: 'received', inbox_id, event_id }`.
      EVIDENCE (reconciled): `src/application/ingest-candidate.ts:22-38`.
- [x] **DONE: `application/process-inbox.ts`** exists — the worker loop
      `processInbox(maxBatch, leaseSeconds)` claims a leased row (own committed txn), runs `runEgate`
      on the persisted candidate outside the claim txn, and records the terminal disposition.
      EVIDENCE (reconciled): `src/application/process-inbox.ts` (`claimOne`/`processOne`/`processInbox`).
- [x] **DONE: `runEgate` extracted + public `submitCandidate(raw)` REMOVED.** `process-candidate.ts`
      now exports only `runEgate(candidate)`; `grep submitCandidate` across `src/` + `test/` returns
      ZERO code references (comments/fixture prose only), and `runEgate` has exactly one caller
      (`process-inbox.ts:58`). plan-7 rule 4 (no parallel direct path) satisfied.
      EVIDENCE (reconciled): `src/application/process-candidate.ts:39`; grep 2026-07-11.

### plan-7 P2 (tests + wiring + docs) — DONE (reconciled)

- [x] **DONE:** `test/integration/gate.test.ts` now drives EGATE through `ingestCandidate` +
      `processInbox` (imports `ingestCandidate` from `#application/ingest-candidate.js`, not
      `submitCandidate`). EVIDENCE (reconciled): `test/integration/gate.test.ts:17,38`.
- [x] **DONE:** `test/integration/candidate-inbox.test.ts` exists — proves ingress-ack `received`,
      ingress-reject with no row, idempotent replay (same `inbox_id`), lease claim + resolve,
      resolved-not-reclaimed, expired-lease crash recovery, and `registered_count` no-double-count.
      EVIDENCE (reconciled): `test/integration/candidate-inbox.test.ts` (7 tests, all pass; see
      Verification Plan). A dead-letter test was added by I1 in this pass.
- [x] **DONE:** `README.md` CINBOX-gap note updated to "built" by the plan-7 owner. (Note: README still
      carries other pre-refactor `src/db/*` paths — broader doc-sync, out of plan-7/plan-8 scope; flag
      separately if a sweep is wanted.)

## Remaining Plan-7 Gaps — ALL CLOSED (reconciled 2026-07-11)

Originally handed to the plan-7 owner as their own unfinished P1/P2. The follow-up audit confirms
every item is now landed in the working tree. No open plan-7 gaps remain.

- [x] G1: `application/ingest-candidate.ts` present (`ingestCandidate` = minimal validate -> insert ->
      project registered -> ingress ack), reusing `validateIngressEnvelope` +
      `insertInboxCandidate`/`projectRegistered`. EVIDENCE: `src/application/ingest-candidate.ts`.
- [x] G2: `application/process-inbox.ts` present (`processInbox` worker loop). It mirrors the
      `dispatch-outbox.ts` claim/BEGIN/COMMIT shape (~85% structural overlap) — kept as two small
      siblings sharing a documented pattern, and now also shares the dead-letter cap discipline (I1).
      EVIDENCE: `src/application/process-inbox.ts`.
- [x] G3: `runEgate(candidate): Promise<CommitOutcome>` extracted; public `submitCandidate(raw)`
      REMOVED (plan-7 rule 4). `process-inbox.ts` is its only caller; zero `submitCandidate` code refs.
      EVIDENCE: `src/application/process-candidate.ts:39`; grep 2026-07-11.
- [x] G4: `gate.test.ts` (and `dispatch.test.ts`) drive EGATE through `ingestCandidate` +
      `processInbox`, proving the full durable hop end to end. EVIDENCE: `test/integration/gate.test.ts:17,38`.
- [x] G5: `test/integration/candidate-inbox.test.ts` present, covering AC-01..AC-05/AC-07 (ingress ack
      `received`; idempotent replay -> same `inbox_id`; single-consumer lease claim; expired-lease
      reclaim; `registered_count` bump; resolution recorded; resolved row not re-claimed) — plus the
      new I1 dead-letter case. EVIDENCE: `test/integration/candidate-inbox.test.ts` (7 tests, all pass).
- [x] G6 (stale-doc sweep): the `run-state.ts` "always read zero" comment is already replaced —
      lines 15-18 now read *"registered_count (migration 0007) is the CINBOX -> RSTATE 'project:
      registered' target ... the authoritative count of durably received candidates per run."*
      EVIDENCE: `src/persistence/run-state.ts:15-18`. No further sweep needed.

## Proposed Improvements (opt-in) — evidence-based status (reconciled 2026-07-11)

Bounded, additive suggestions ON TOP of plan-7's scope. Each carries a verified status from the
second audit: **DONE-IN-THIS-PASS**, **ALREADY-SATISFIED**, or **OPTIONAL** (accept/decline).

- [x] **I1 — inbox worker attempt cap. STATUS: DONE-IN-THIS-PASS (implemented + verified).**
      Evidence for the gap: `INBOX_MAX_ATTEMPTS = 5` was declared in `process-inbox.ts` but never
      enforced; `markInboxFailed` unconditionally reset `inbox_status='received'`, so a row whose
      `runEgate` threw a real worker error (not a normal quarantine/reject) would re-claim **forever**
      (poison-pill). The sibling `dispatch-outbox.ts:47-51` already had the correct dead-letter-at-cap
      pattern; the inbox worker was the drift. An independent `/review-diff` pass flagged the identical
      gap (its finding F1), confirming it.
      Fix landed (mirrors the outbox failure model):
      - `src/persistence/migrations/0008_candidate_inbox_dead_letter.sql` — NEW forward-only migration:
        adds `dead_lettered_at timestamptz`, rebuilds `candidate_inbox_claimable_idx` to also exclude
        `dead_lettered_at IS NOT NULL`. Additive/idempotent, one txn (does NOT edit applied 0007).
      - `src/persistence/candidate-inbox.ts` — claim query now excludes `dead_lettered_at IS NOT NULL`;
        added `markInboxDeadLettered(inboxId, lastError)` (terminal `inbox_status='dead_lettered'`,
        sets `dead_lettered_at`, clears lease) alongside the existing retry `markInboxFailed`.
      - `src/application/process-inbox.ts` — added `recordFailure(inboxId, attemptCount, lastError)`:
        `markInboxFailed` while `attempt_count < INBOX_MAX_ATTEMPTS`, else `markInboxDeadLettered`;
        `INBOX_MAX_ATTEMPTS = 5` aligned with `DISPATCH_MAX_ATTEMPTS`; `processInbox` now also reports
        `deadLettered` (additive; no existing consumer read the result fields).
      - `test/integration/candidate-inbox.test.ts` — new test "a dead-lettered row is terminal and
        never re-claimed (attempt-cap, I1)": proves a dead-lettered row is not re-claimed, keeps its
        `attempt_count`, stays `resolved_at IS NULL`, and preserves `last_error` for operators.
      Verification (run by me against live Postgres on :5433): `pnpm typecheck` clean;
      `candidate-inbox.test.ts` 7/7; full `test/**/*.test.ts` **56/56** (was 55 + this new test).
      Recommendation kept: two small siblings sharing a documented pattern, no premature abstraction.
- [x] **I2 — registered-count honesty. STATUS: ALREADY-SATISFIED (no change needed).**
      `ingestCandidate` already calls `projectRegistered` ONLY on `inserted: true`, so an idempotent
      replay does not double-count. EVIDENCE: `src/application/ingest-candidate.ts:34-36`
      (`if (inserted) { await projectRegistered(...) }`); proven by
      `candidate-inbox.test.ts` "ingress bumps RSTATE registered_count once per new candidate"
      (replay leaves the count unchanged). Note: the bump runs in a separate txn from the inbox insert
      — an honest best-effort/dotted projection per the model, not a defect.
- [x] **I3 — resolution vocabulary as a type. STATUS: ACCEPTED + IMPLEMENTED 2026-07-12.**
      `markInboxResolved(inboxId, resolution: string)` tightened to
      `markInboxResolved(inboxId, resolution: CommitOutcome["kind"])`. Sole call site
      (`process-inbox.ts:77`, `outcome.kind`) already satisfied the tighter type — `pnpm typecheck`
      clean confirms it, no cast/workaround needed. EVIDENCE:
      `src/persistence/candidate-inbox.ts` (`markInboxResolved` signature + `CommitOutcome` import);
      `src/application/process-inbox.ts:77`. Full suite 121/121 after the change.
- [x] **I4 — worker-role reality check. STATUS: ACCEPTED + IMPLEMENTED 2026-07-12.**
      Added a one-line clarifying comment above `INBOX_MAX_ATTEMPTS` in `process-inbox.ts` noting the
      runtime connects as `telemetry_migrator` via the single `DATABASE_URL`, not `telemetry_ingest`
      (the role `0007` actually grants `UPDATE` to). Documentation only, no behavior change. EVIDENCE:
      `src/application/process-inbox.ts` (comment above `INBOX_MAX_ATTEMPTS`).
- [x] **I5 — observability seam for Layer 8. STATUS: ALREADY-SATISFIED (coordination note).**
      `process-inbox.ts` already records `resolution` equal to the `CommitOutcome.kind` string
      (`markInboxResolved(row.inbox_id, outcome.kind)`), so a downstream Layer 8 `QMET` / Layer 9A
      `DPROJ` view can group on it with no translation table. EVIDENCE:
      `src/application/process-inbox.ts:59`. No plan-7 work; forward-coordination for plan-9.

## Out Of Scope (Things To Avoid)

- Do NOT re-implement plan-7's DONE P0/P1/P2 items — they are built and correct (reconciled above).
- Do NOT keep a direct `submitCandidate -> EGATE` path (plan-7 rule 4) — already removed.
- Do NOT edit applied migrations `0001`-`0007` in place. (I1 added a NEW `0008` instead.)
- Do NOT add an append-only trigger to `candidate_inbox` (mutable working state) — a dead-lettered row
  is terminal working state, not an append-only fact.
- Do NOT `git add`/commit either repo. Nothing here is durable until a human reviews and commits.

## Affected Paths (in `../ai-monitoring`)

Plan-7 owner (G1-G6, DONE before this pass):

- `src/application/ingest-candidate.ts` — NEW (G1).
- `src/application/process-inbox.ts` — NEW (G2).
- `src/application/process-candidate.ts` — EDIT (G3: extract `runEgate`, remove public `submitCandidate`).
- `src/persistence/run-state.ts` — EDIT (G6 stale comment).
- `test/integration/gate.test.ts` — EDIT (G4).
- `test/integration/candidate-inbox.test.ts` — NEW (G5).
- `README.md` — EDIT (plan-7 P2 CINBOX-gap note).

This pass (I1, DONE + verified):

- `src/persistence/migrations/0008_candidate_inbox_dead_letter.sql` — NEW (dead_lettered_at + index).
- `src/persistence/candidate-inbox.ts` — EDIT (claim excludes dead-lettered; add `markInboxDeadLettered`).
- `src/application/process-inbox.ts` — EDIT (`recordFailure` cap decision; report `deadLettered`).
- `test/integration/candidate-inbox.test.ts` — EDIT (new dead-letter/attempt-cap test).

> **⚠️ CROSS-PLAN MIGRATION-NUMBER COLLISION (action for plan-9).** I1 above consumed migration number
> `0008`. plan-9's PC1 (`plan-9-...md`) planned migration range `0008`-`0012` and its Layer 6 seam uses
> `0008_run_state_digest.sql`. That number is now taken. **plan-9 must shift its range to `0009`-`0013`**
> (or the next free numbers) to avoid a forward-only runner collision. The runner applies files in
> filename order and records each `version`; two different `0008_*.sql` files would both try to claim
> version prefix `0008` and the second author's DDL could silently not re-run on an env that already
> applied the first. Flag to the plan-9 owner before either lands in the same database.

Optional, not done (accept/decline):

- `src/persistence/candidate-inbox.ts` / `src/contracts/candidate.ts` — I3 resolution type, if accepted.
- `src/application/process-inbox.ts` — I4 one-line worker-role note, if accepted.

## Acceptance Criteria (for this assessment plan)

- [x] AC-01: plan-7's covered layer is identified (Layer 4 CINBOX hop) with model-edge evidence.
- [x] AC-02: every plan-7 Todo item is classified DONE / PARTIAL / MISSING from a real file audit, with
      the specific file evidence for each — reconciled to all-DONE after the second audit.
- [x] AC-03: `## Remaining Plan-7 Gaps` (G1-G6) re-audited against the working tree — all closed with
      file evidence; no open plan-7 gaps remain.
- [x] AC-04: each `## Proposed Improvements` item (I1-I5) has a verified status — I1 DONE+verified,
      I2/I5 already satisfied, I3/I4 accepted and implemented 2026-07-12.

## Verification Plan

- Assessment part: the file audit above (done, reconciled 2026-07-11).
- I1 implementation (run by me against live Postgres on :5433, this pass):
  - `pnpm typecheck` — clean ("No errors found").
  - `node --env-file-if-exists=.env --import tsx --test test/integration/candidate-inbox.test.ts` — 7/7 pass
    (includes the new dead-letter/attempt-cap test).
  - `node --env-file-if-exists=.env --import tsx --test 'test/**/*.test.ts'` — **56/56 pass**, no regressions.
- Not committed (per constraint). Migration `0008` is applied to the local dev DB by the test
  `before()` hook via the forward-only runner; a fresh environment applies it on first `migrate()`.

## Handoff Notes

- Primary handoff: the **plan-7 owner** (another agent) — sections `Remaining Plan-7 Gaps` and
  `Proposed Improvements`.
- Sibling plan: **plan-9** (Layers 6-10 integration) consumes `registered_count` and the inbox
  `resolution` vocabulary — see I2/I3/I5 for the producer/consumer coordination points.
- reviewer means reviewer agent handoff using OpenCode command `/review-diff` on the `ai-monitoring`
  working tree once the plan-7 owner lands G1-G6.
