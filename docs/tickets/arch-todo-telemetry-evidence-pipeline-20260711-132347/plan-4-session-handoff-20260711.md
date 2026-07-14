# Session Handoff — Telemetry evidence pipeline (2026-07-11)

- Ticket: `arch-todo-telemetry-evidence-pipeline-20260711-132347` (continuation;
  see `plan-1`, `plan-2`, `plan-3` in this same folder)
- Purpose: this is a **status/handoff snapshot**, not a Todo plan — it records
  exactly where this multi-turn session left off so the next session (or a fresh
  agent) does not have to re-derive it from conversation history.
- Implementation target repo: `../ai-monitoring` (sibling to
  `awesome-ai-utmostcreator`), plus `___ARCHITECTURE_2.0/telemetry/` in this repo
  (the model + implementation plan).
- Current branch in **this** repo: `fix/opencode-agent-body-parity` — **unrelated
  to this work** (same mismatch flagged since `plan-1`). All changes below are
  untracked working-tree edits (`git status --short` shows `??` for both
  `___ARCHITECTURE_2.0/` and this ticket folder). `ai-monitoring` has no commits
  beyond an empty `README.md` (`git log --oneline` → one "initial commit").

## Scope (what this session touched)

1. Fixed `.env` auto-loading + no-hang DB-unreachable behavior in `ai-monitoring`
   (`db:migrate`/`db:test`/`test` scripts, `pool.ts`).
2. Added `ai-monitoring/.github/workflows/ci.yml` (typecheck + `test:schema`, no DB).
3. Built Layer 4 remainder (`EDISP`/`ELIMIT`/`ECORR`/`EPREJ`) + completed `EGATE`
   (migration `0003_disposition.sql`).
4. Built Layer 7B (`DISPATCH`/`STATEPROJ`/`EPROJOUT` + the `RSTATE` table,
   migration `0004_run_state.sql`).
5. Built Layer 6 core (`RSETTLE`→`RSETTLED`, `RINCOMP`, `ROPEN`/`RREOPEN`/`RLATE`,
   migration `0005_settlement.sql`).
6. Architecture review: validated an external scorecard, fixed build hygiene
   (`tsconfig.build.json`), persisted a domain/application/persistence refactor
   plan (`plan-3`, **not started**).
7. Audited model-vs-implementation coverage (exact node/cross-link counts).
8. Removed all stale/dead "ledger" content from `IMPLEMENTATION_PLAN.md` and
   `model/telemetry.yaml`, regenerated `generated/*.mmd`, deleted the orphaned
   `generated/run_ledger.mmd`.

## Clarified constraints (user-confirmed during this session)

- No new external infra/product decisions may be invented (blob storage backend,
  alerting sink, Langfuse/Phoenix/OpenObserve/promptfoo credentials) — ask first.
- Worker daemons + CLI (Phase 2 of the architecture review) are **explicitly
  deferred** until a real deployment target exists — do not scope-creep into this.
- The `plan-3` domain/application/persistence refactor should be **persisted as a
  plan first**, not implemented directly (user's explicit choice) — it is
  persisted; implementation has not started.
- `rm -rf` is a hard-denied command for the acting agent in this environment —
  any cleanup requiring it must be done by the user or asked for per-command.

## Current state — what is BUILT and VERIFIED (live PostgreSQL, not just typecheck)

`pnpm typecheck`: clean. `pnpm test`: **30/30 pass** (re-confirmed at the end of
this session, re-run 6+ times earlier in the session with no flakes after two
real bugs were found and fixed — see "Bugs found and fixed" below).

| Model node(s) | Where | Status |
|---|---|---|
| `CINBOX` (table only) | `telemetry.candidate_inbox` | Table exists, **no writer** — see gap below |
| `CAND` | `src/types/candidate.ts` + `candidate.schema.json` | Built |
| `EGATE` | `src/db/gate.ts` (`submitCandidate`) | Built + tested (schema validation now real, was previously test-only) |
| `EADOPT`/`ECOMMIT`/`ESTORE` | `src/db/commit.ts` | Built + tested |
| `EQUAR` | `src/db/quarantine.ts` | Built + tested |
| `EDISP`/`ELIMIT`/`ECORR` | `src/db/disposition.ts` | Built + tested |
| `EPREJ` | `telemetry.event_rejection` (migration `0003`) | Built + tested |
| `DIGEST` | `src/canonical.ts` | Built + tested |
| `OUTBOX`/`DISPATCH`/`STATEPROJ`/`EPROJOUT` | `src/db/dispatch.ts`/`stateproj.ts`/`eprojout.ts` | Built + tested |
| `RSTATE` | `telemetry.run_state_projection` (migration `0004`) | Built + tested, mutable/rebuildable |
| `RSETTLE`/`RSETTLED`/`RWAIT`/`RDEAD` | `src/db/settlement.ts` | Built + tested, **caller-driven not automatic** — see below |
| `RINCOMP`/`ROPEN`/`RREOPEN`/`RLATE` | `src/db/settlement.ts` + migration `0005` | Built + tested |

**Not built:** Layer 5 (`REDACT`/`BPUT`/`BLOB`/`BVERIFY`/`BAUDIT` — needs a storage
backend decision), Layer 8 (`QMET`/`MON`/`OP`/`OREVIEW`/`ODEC`), Layer 9A/9B (needs
external products), Layer 10 (`GOLD`/`PF`/`MANUAL`/`EFAIL`/`EREC`/`ECOV`/`VIEW`).

## Confirmed coverage numbers (from the model/plan audit this session)

- Full model (59 nodes, incl. owner-attested Layers 1-3): **34/59 = 57.6%**
- In-repo build scope only (excl. Layers 1-3): **24/49 = 49.0%**
- Cross-links (38 total, `model/telemetry.yaml`): **4 fully wired + 1 partial
  (`EPROJOUT → EPROJ`, stub target) = ~11.8%**. 10 of the 38 (Layer1→2, Layer2→CAND,
  Layer3→CAND) are not itemized as checkboxes in `IMPLEMENTATION_PLAN.md` at all.

## Known gaps (open, not yet actioned — priority-ish order)

1. **`RSETTLED` is missing from the canonical `model/telemetry.yaml`** in this
   repo, but **already exists as a real node** in `ai-monitoring/model/telemetry.yaml`
   (the vendored copy) with a working shape and `RSETTLE → RSETTLED` wiring — this
   is a ready-made answer to backport, not something to design from scratch. The
   canonical model's `RSETTLE` gate currently has no outgoing edge for its `"yes"`
   branch because of this gap. Documented in `IMPLEMENTATION_PLAN.md` under
   "RSETTLED — the ledger's replacement (built)".
2. **`CINBOX` has no writer anywhere in `ai-monitoring/src/`.** Candidates route
   directly through `EGATE` into `accepted_event`/`quarantine`, bypassing the
   durable pre-validation inbox, its `processing_lease`, and true
   ack-after-persist semantics. Fixing this changes `EGATE`'s entrypoint contract
   — needs its own bounded review, not a drive-by fix.
3. **`RSTATE`'s `run_state_digest` field (in the model's node note) is not
   implemented** in `run_state_projection` — only `run_epoch`/`run_state_version`/
   `run_status` exist. Either implement it or remove it from the model note.
4. **`plan-3` (domain/application/persistence refactor) is persisted but not
   started.** 29 unchecked Todo/AC items across P0 (straight moves) / P1
   (logic-splitting, judgment calls already resolved) / P2 (test alignment). The
   existing 30 tests are the regression gate.
5. **Stale `dist/` output** in `ai-monitoring` still has orphaned `dist/src/**`/
   `dist/test/**` files from before the `tsconfig.build.json` fix — `tsc` doesn't
   clean outputs no longer in its input set. Needs `rm -rf dist && pnpm build`,
   which the acting agent cannot run (hard-denied). New builds via the fixed
   config are correct; only the leftover clutter needs a one-time manual clean.

## Bugs found and fixed this session (for context, not remaining work)

- `migrate.ts` had no advisory lock — concurrent test-file processes could race
  on a brand-new migration's `CREATE TABLE IF NOT EXISTS`. Fixed with
  `pg_advisory_lock`/`unlock` around the whole migration run.
- `settled_at` was typed `string` but returned as a `Date` object at runtime
  (uncast `timestamptz`), breaking a strict-equality test. Fixed with `::text`
  casts in every settlement-fact query.
- `test/db/dispatch.test.ts`'s "re-dispatch is a no-op" assertion depended on an
  empty *global* outbox queue, which is flaky in this persisted dev database
  (undelivered rows accumulated across many earlier test sessions before
  `DISPATCH` existed). Fixed by asserting on the specific commit's own outbox
  rows instead of the global dispatch count.

## Environment notes for the next session

- Docker daemon is **not directly reachable** from this sandbox shell (`sudo` is
  denied, no `docker` socket permission) — but a Postgres container from an
  earlier session was still running on `localhost:5433` throughout this session,
  so all `pnpm test`/`pnpm db:test` runs above are real live-DB proof, not
  typecheck-only. **If that container is not running in the next session**,
  `pnpm db:up` (via `docker compose`) must be started first, or DB tests will
  correctly skip (not fail/hang) per the `isDbReachable()` guard.
- `ai-monitoring/.env` exists (gitignored) and is auto-loaded by `db:migrate`/
  `db:test`/`test` scripts — no manual `export` needed.

## Verification commands (copy-paste ready, one per line)

```
cd /home/utmostcreator/Projects/ai-monitoring && pnpm typecheck
cd /home/utmostcreator/Projects/ai-monitoring && pnpm test:schema
cd /home/utmostcreator/Projects/ai-monitoring && pnpm db:up
cd /home/utmostcreator/Projects/ai-monitoring && pnpm db:migrate
cd /home/utmostcreator/Projects/ai-monitoring && pnpm test
cd /home/utmostcreator/Projects/ai-monitoring && pnpm build
cd /home/utmostcreator/Projects/awesome-ai-utmostcreator/___ARCHITECTURE_2.0/telemetry && bash scripts/gen_mermaid.sh --check
```

## Likely files for follow-up work

- `../ai-monitoring/src/db/*.ts` (all 12 modules — Phase 1 refactor target per `plan-3`)
- `../ai-monitoring/model/telemetry.yaml` (has the `RSETTLED` node to backport)
- `___ARCHITECTURE_2.0/telemetry/model/telemetry.yaml` (needs `RSETTLED` node + `run_state_digest` decision)
- `___ARCHITECTURE_2.0/telemetry/IMPLEMENTATION_PLAN.md` (tracks all of the above)
- `docs/tickets/arch-todo-telemetry-evidence-pipeline-20260711-132347/plan-3-ai-monitoring-domain-refactor.md` (ready to implement)

## Unresolved questions for the user/next session

- Backport `RSETTLED` from `ai-monitoring/model/telemetry.yaml` into the canonical
  `___ARCHITECTURE_2.0/telemetry/model/telemetry.yaml`? (small, ~1 file, already
  has a proven shape to copy)
- Start `plan-3` (domain/application/persistence refactor) P0, or prioritize a
  new model layer (5/8/9A/9B/10) instead?
- Storage-backend decision for Layer 5 (local-fs vs. S3/GCS) — still blocking any
  blob-path work.
- Should `CINBOX` get a real writer (Layer 4 entrypoint redesign), given it
  changes `EGATE`'s ack contract?

## Recommended next step

No single obviously-correct next step — genuinely branches on user priority (see
"Unresolved questions" above). If forced to rank: (1) backport `RSETTLED` to the
canonical model (smallest, closes the "source of truth is incomplete" gap,
~15 min), then (2) `plan-3` P0 (straight-move refactor steps, lowest risk, already
scoped). implementer means implementer agent handoff for either, on a dedicated
`ai-monitoring` branch (not `fix/opencode-agent-body-parity` in this repo).
reviewer means reviewer agent handoff using OpenCode command: `/review-diff`,
once any of the above lands.

## Downstream-confirmation flag

Nothing has been committed, pushed, or deployed in either repo. A human must
review and commit before any of this is durable beyond the local working tree.
