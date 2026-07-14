# Architecture Plan — ai-monitoring domain/application/persistence refactor

- Ticket: `arch-todo-telemetry-evidence-pipeline-20260711-132347` (continuation; see
  `plan-1-telemetry-evidence-pipeline.md` and `plan-2-ai-run-ledger-removal.md` in
  this same folder)
- Source: external architecture scorecard review (2026-07-11) of the sibling
  `ai-monitoring` repo, cross-checked against its actual `src/` contents
- Generated: 2026-07-11
- Current branch (this repo): `fix/opencode-agent-body-parity` — **unrelated to this
  work**, same branch-scope mismatch already flagged in `plan-1`; this plan targets
  the sibling `ai-monitoring` repo, not this one, and should be implemented on its
  own branch there.
- Plan file: `docs/tickets/arch-todo-telemetry-evidence-pipeline-20260711-132347/plan-3-ai-monitoring-domain-refactor.md`
- Implementation target repo: `../ai-monitoring` (sibling to `awesome-ai-utmostcreator`)

> **Completion instruction:** When every `## Todo Plan` item and every
> `## Acceptance Criteria` item below is checked `[x]`, rename this file to
> `DONE-plan-3-ai-monitoring-domain-refactor.md` and move it into `archive/` under
> this ticket folder.

## Context

A 2026-07-11 external architecture scorecard rated `ai-monitoring` overall 68/100:
strong on scope discipline (91), contract safety (82), and transactional foundation
(84); weak on domain separation (72 — "orchestration and persistence are becoming
mixed"), worker/process architecture (35 — no daemon boundaries), build hygiene (55
— compiled tests ship in `dist/`), operational readiness (38), and UI readiness (15).

The scorecard's own proposed target tree splits `src/` into
`contracts/`/`domain/`/`application/`/`persistence/`/`content/`/`projections/`/
`workers/`/`cli.ts`. That tree bundles two very different kinds of work:

1. A **mechanical refactor** of already-built, already-tested code (Layer 4 event
   truth + disposition, Layer 7B outbox dispatch, Layer 6 settlement — built
   2026-07-11, 30/30 tests passing) into cleaner module boundaries. No new behavior.
2. **New subsystems** (`workers/*` daemons with supervision, `cli.ts`, `content/*`
   for the not-yet-built Layer 5 blob store) that are real design decisions, not
   refactors, and need answers (deployment target, supervision strategy) this repo
   does not have yet.

The user confirmed (2026-07-11): do (1) as a persisted plan first (this file); defer
(2) entirely until a deployment target exists (see `plan-1`'s own "do not invent
systems" precedent and `AGENTS.md`'s "Do not invent systems, services, persistence
layers, or infrastructure that are not present").

`pnpm build` hygiene (compiled tests in `dist/`, `dist/src/...` nesting) was already
fixed directly (small, ~2-file, zero-risk: `tsconfig.build.json` + a `package.json`
script change) — not part of this plan's scope.

## Problem

`ai-monitoring/src/db/*.ts` mixes three concerns in the same functions: pure
business decisions (e.g. `classifyDisposition`, `assertHonestCompleteness`), use-case
orchestration (e.g. `disposeQuarantinedEvent` calling both a decision and two DB
writes), and raw SQL persistence. This makes the pure decision logic untestable
without a database and makes the persistence boundary unclear, which is what the
scorecard's "domain separation: 72/100" finding is pointing at.

## Target Outcome

`ai-monitoring/src/` reorganized into `contracts/` (schema + types + validation),
`domain/` (pure decisions, zero I/O, zero `pg` imports), `application/` (use-case
orchestrators that call domain + persistence), `persistence/` (SQL only, no
decisions), and `projections/` (the two existing outbox-delivered projectors) — with
**zero behavior change**, proved by the existing 30 tests passing with only import
path updates, not assertion changes.

## In Scope

- Moving/splitting the 12 existing `src/db/*.ts` + `src/canonical.ts` +
  `src/types/candidate.ts` + `src/schemas/candidate.schema.json` files into
  `contracts/`, `domain/`, `application/`, `persistence/`, `projections/`.
- Updating every import path this touches, including in `test/`.
- Renaming `test/schema/` → `test/unit/` and `test/db/` → `test/integration/` to
  match the new taxonomy (tests move, content/assertions unchanged).
- Resolving the two genuine judgment calls this split surfaces (see Todo Plan P1)
  explicitly in this plan, not leaving them ambiguous for the implementer.

## Out Of Scope (Things To Avoid)

- Do **not** create `src/content/*` (Layer 5 blob store) — not built yet, still
  blocked on a storage-backend decision; an empty scaffold folder would be a
  premature, misleading placeholder.
- Do **not** create `src/workers/*` or `src/cli.ts` — explicitly deferred by the
  user until a deployment target exists (Phase 2, not this plan).
- Do **not** add `test/concurrency/` or `test/recovery/` with new test scenarios —
  that requires genuinely new tests (e.g. proving two concurrent `dispatchPending()`
  callers don't double-process), which is new verification work, not a rename.
- Do **not** change any threshold, policy constant, or SQL query semantics
  (`MAX_CORRECTION_ATTEMPTS = 3`, `DISPATCH_MAX_ATTEMPTS = 5`, the honesty mapping in
  `assertHonestCompleteness`, the accepted-set-digest field tuple, etc.) — this is a
  pure reorganization.
- Do **not** touch `src/db/migrations/*.sql` file *contents* — only their location.
- Do **not** add a lint/import-boundary enforcement tool in this plan unless it is
  trivial (see AC-04/AC-05, which use `grep` instead) — introducing a new lint
  dependency is a separate decision.
- Do **not** attempt this on the current branch (`fix/opencode-agent-body-parity` in
  this repo is irrelevant; cut a dedicated branch in `ai-monitoring` first).

## Affected Paths (in `../ai-monitoring`)

- `src/db/pool.ts`, `quarantine.ts`, `run-state.ts`, `migrate.ts`, `migrations/*.sql`,
  `stateproj.ts`, `eprojout.ts`, `commit.ts`, `gate.ts`, `disposition.ts`,
  `dispatch.ts`, `settlement.ts` (moved/split)
- `src/canonical.ts`, `src/types/candidate.ts`, `src/schemas/candidate.schema.json`
  (moved)
- `test/db/*.test.ts`, `test/schema/*.test.ts` (moved + import paths updated)
- New: `src/contracts/`, `src/domain/`, `src/application/`, `src/persistence/`,
  `src/projections/`

## Contracts And Boundaries

- **Import direction is one-way:** `domain/` imports nothing from `application/` or
  `persistence/`; `application/` may import `domain/` and `persistence/`;
  `persistence/` imports nothing from `domain/` or `application/`. Enforced by
  grep-based checks (AC-04/AC-05), not a lint rule, in this phase.
- **`domain/` has zero `pg` imports and zero `getPool()`/`.query(` calls** — it must
  be testable without a database. Same for `contracts/`.
- **Transaction boundaries do not move.** `commitAcceptedEvent`'s single
  `BEGIN…COMMIT` (accepted event + outbox, one txn) and `DISPATCH`'s single
  transaction (claim + project + mark `delivered_at`) must remain intra-function —
  splitting persistence from application must not split either of these across two
  separate `pool.connect()` calls, which would break the append-only/atomicity
  guarantees the existing tests prove.
- **Judgment call — `commitAcceptedEvent` (ECOMMIT/EADOPT) stays one cohesive
  persistence-layer unit**, renamed to `persistence/accepted-events.ts`, **not**
  further split into domain+persistence. Rationale: the digest-compare that decides
  `adopted` vs `identity_digest_conflict` is an identity/integrity check inseparable
  from the SQL row it's comparing against (it must run inside the same lookup query
  context) — unlike `ELIMIT`'s correction-attempt-cap, which is a policy threshold
  applied to data already in hand and cleanly separable. This is decided here, not
  left open for the implementer.
- **Judgment call — `DISPATCH`'s backoff policy is a business rule, not
  persistence.** `backoffSeconds()` and `DISPATCH_MAX_ATTEMPTS` move to
  `application/dispatch-outbox.ts`; only the raw claim/mark-delivered/record-failure
  SQL moves to `persistence/outbox.ts`.
- **`canonical.ts` stays one file** (`domain/canonical.ts`, `canonicalize` +
  `digest` together) — not split into separate `canonical.ts`/`digest.ts` per the
  scorecard's literal proposal; at 51 lines, further fragmentation adds navigation
  cost without a corresponding clarity gain.
- No schema/migration changes. No new tables. No new dependencies.

## Todo Plan

### P0 — Straight moves (rename only, zero logic change, lowest risk)

- [ ] Move `src/db/pool.ts` → `src/persistence/pool.ts` (keep `withTransaction`
      together with the pool — do not split into a separate `transactions.ts` for a
      ~15-line function; revisit only if more transaction-composition helpers are
      added later).
- [ ] Move `src/db/quarantine.ts` → `src/persistence/quarantine.ts` (already pure
      persistence — no split needed).
- [ ] Move `src/db/run-state.ts` → `src/persistence/run-state.ts` (already pure
      upserts — no split needed).
- [ ] Move `src/db/migrate.ts` → `src/persistence/migrate.ts` and
      `src/db/migrations/*.sql` → `src/persistence/migrations/*.sql`.
- [ ] Move `src/db/stateproj.ts` → `src/projections/run-state-projector.ts`.
- [ ] Move `src/db/eprojout.ts` → `src/projections/event-audit-projector.ts`.
- [ ] Move `src/canonical.ts` → `src/domain/canonical.ts` (unchanged content).
- [ ] Move `src/schemas/candidate.schema.json` → `src/contracts/candidate.schema.json`.
- [ ] Move `src/types/candidate.ts` → `src/contracts/candidate.ts`.
- [ ] Update every import path touched by the moves above (including in `test/`).
- [ ] Run `pnpm typecheck` + `pnpm test` after this batch, before starting P1.

### P1 — Logic-splitting extractions (medium risk; judgment calls already resolved above)

- [ ] Extract the Ajv validator (`getValidator`/`cachedValidator`) out of
      `src/db/gate.ts` into `src/contracts/validate-candidate.ts` as a pure
      `validateCandidate(raw: unknown)` function (reads the schema file once, no DB
      calls).
- [ ] Split `src/db/disposition.ts`: move `classifyDisposition` +
      `createCorrectedCandidate` (already pure) into `src/domain/disposition.ts`
      unchanged; move `rejectPermanently` + `disposeQuarantinedEvent`
      (DB-touching) into `src/application/apply-disposition.ts`, importing the
      domain functions.
- [ ] Split `src/db/settlement.ts`: move `assertHonestCompleteness` (already pure)
      into `src/domain/settlement.ts`; move `computeAcceptedSetDigest` /
      `settleRun` / `getSettlementFact` / `getLatestSettlementFact` /
      `recordIncomplete` / `reopenRun` / `classifyLateEvent` (all DB-touching) into
      `src/application/settle-run.ts`, importing the domain function.
- [ ] Rename `src/db/commit.ts` → `src/persistence/accepted-events.ts` unchanged
      (see the resolved judgment call above — no further split).
- [ ] Rebuild `src/db/gate.ts`'s `submitCandidate` as
      `src/application/process-candidate.ts`, calling
      `contracts/validate-candidate.ts`, then `persistence/accepted-events.ts`, then
      `persistence/quarantine.ts` + `persistence/run-state.ts` on the quarantine
      paths.
- [ ] Create `src/application/correct-candidate.ts`: a thin composition — given a
      corrected `CandidateEnvelope` (from `apply-disposition.ts`'s `"corrected"`
      outcome), calls `process-candidate.ts` to re-submit it. This is new glue code
      (a few lines), not a move of existing logic — nothing today automatically
      re-submits a corrected candidate.
- [ ] Split `src/db/dispatch.ts`: extract `claimNextPending` / `markDelivered` /
      `recordFailure` (pure SQL) into `src/persistence/outbox.ts`; keep
      `backoffSeconds` / `DISPATCH_MAX_ATTEMPTS` / the routing-by-`consumer_name`
      decision / the batch loop in `src/application/dispatch-outbox.ts`.
- [ ] Run `pnpm typecheck` + `pnpm test` after **each** extraction above, not just
      once at the end of P1.

### P2 — Test alignment + final verification (low risk, mechanical)

- [ ] Rename `test/schema/` → `test/unit/` (content/assertions unchanged, only
      import paths).
- [ ] Rename `test/db/` → `test/integration/` (content/assertions unchanged, only
      import paths).
- [ ] Update every remaining test import path to the new
      `domain/`/`application/`/`persistence/`/`contracts/`/`projections/` locations.
- [ ] Run the full verification ladder (see below) and confirm every AC below.

## Acceptance Criteria

- [ ] AC-01: All 30 existing tests pass after the move, with only import path
      changes — no assertion bodies changed (diff test files before/after and
      confirm only `import` lines differ).
- [ ] AC-02: `pnpm typecheck` passes clean after the move.
- [ ] AC-03: `pnpm build` (using the existing `tsconfig.build.json` from the prior
      build-hygiene fix) still produces no test files and no `dist/src/...` nesting.
- [ ] AC-04: `grep -rl "from \"\.\./application\|from \"\.\./persistence"
      src/domain src/contracts` (or equivalent) returns no matches — import
      direction discipline holds for `domain/`/`contracts/`.
- [ ] AC-05: `grep -rl "getPool\|\.query(" src/domain src/contracts` returns no
      matches — `domain/`/`contracts/` have zero DB calls.
- [ ] AC-06: Both judgment calls in "Contracts And Boundaries" are implemented
      exactly as resolved here (verified by reading the final file layout), not
      resolved differently mid-implementation.

## Verification Plan

- After every P0/P1 step: `pnpm typecheck`, then `pnpm test` (needs
  `pnpm db:up` + `.env` per `ai-monitoring/README.md`) — proves each individual
  move/extraction is safe before moving to the next, rather than one big-bang move
  at the end.
- After P2: full ladder — `pnpm typecheck`, `pnpm test:schema`, `pnpm test`,
  `pnpm build`, plus the two `grep` checks for AC-04/AC-05.
- No new migration or schema verification needed — this plan makes no DB schema
  changes.

## Risks And Rollback

- **Losing uncommitted work during file moves:** `ai-monitoring` currently has no
  commits beyond an empty `README.md` (everything else is untracked working tree,
  confirmed 2026-07-11), so there is no git history to lose, but a careless
  multi-file move could still silently drop content. Mitigation: move one module at
  a time (per the P0/P1 step list), verify with `pnpm typecheck` + `pnpm test`
  after each, not as one large batch.
- **Inconsistent judgment-call resolution:** if the two open design questions
  (ECOMMIT split, DISPATCH backoff placement) were left ambiguous, different
  implementers could resolve them differently mid-refactor. Mitigation: both are
  decided explicitly in "Contracts And Boundaries" above.
- **Import path churn breaking something silently:** TypeScript's `NodeNext` module
  resolution requires exact `.js` extensions in relative imports (see existing
  pattern in every current file, e.g. `from "./pool.js"`) — a move that forgets to
  update an extension will fail at `pnpm typecheck`, not silently at runtime, so
  this is a low-severity, easily-caught risk given the verification-after-each-step
  discipline above.
- **Rollback:** since this is a pure reorganization with a green test suite as the
  regression gate at every step, rollback is "revert the working-tree changes for
  the module currently being moved" — no data/schema rollback is ever needed.

## Handoff Notes

- implementer means implementer agent handoff, working through Todo Plan P0 → P1 →
  P2 in this file, verifying with the existing 30 tests after each individual
  extraction (not once at the end), on a dedicated branch in `ai-monitoring` (not
  the current `fix/opencode-agent-body-parity` branch in this repo, which is
  unrelated).
- Phase 2 (worker daemons, `cli.ts`, `content/*` blob store) is explicitly deferred
  and NOT part of this plan — do not scope-creep into it. Revisit only once a real
  deployment target exists.
- reviewer means reviewer agent handoff using OpenCode command: `/review-diff`,
  once P0/P1/P2 are complete and every AC is checked.
