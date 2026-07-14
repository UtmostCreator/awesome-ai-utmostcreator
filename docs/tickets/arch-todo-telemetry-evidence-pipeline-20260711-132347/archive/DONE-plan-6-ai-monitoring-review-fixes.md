# Architecture Plan — ai-monitoring review fixes (build hygiene + review findings)

- Ticket: `arch-todo-telemetry-evidence-pipeline-20260711-132347` (same dedicated ticket as plan-1..plan-5)
- Source: reviewer findings (2026-07-11) + external 74/100 scorecard (build-hygiene regression)
- Generated: 2026-07-11
- Implementation target repo: `/home/utmostcreator/Projects/ai-monitoring` (sibling; NOT this repo)
- Plan file: `docs/tickets/arch-todo-telemetry-evidence-pipeline-20260711-132347/plan-6-ai-monitoring-review-fixes.md`

> **IMPLEMENTATION TARGET IS EXTERNAL.** All edits land in the sibling
> `../ai-monitoring` repo. This plan file is tracked here (with plan-1..5) only
> because this ticket folder is the durable plan home for all telemetry work.
> External-edit approval for `../ai-monitoring` was granted for this task.

> **BOTH REPOS UNTRACKED WHERE RELEVANT.** `___ARCHITECTURE_2.0/` is untracked in
> this repo; `ai-monitoring` has one `initial commit` (empty README) and the entire
> build is untracked working-tree. `ai-monitoring/dist/` is GITIGNORED
> (`.gitignore:2`) — the duplicate-layout problem is local build output, not a
> tracked-file issue. Do not commit either repo.

> **`rm -rf` IS HARD-DENIED for the acting agent** (per plan-4 constraint). The
> `clean` script added below uses `rm -rf dist`, which is correct for the script's
> own runtime but the agent CANNOT execute `pnpm build`/`pnpm clean` itself. The
> USER must run the clean+build verification steps (P7). The agent's edits are
> source-only (package.json, migrations, ts, docs).

> **Completion instruction:** When every `## Todo Plan` and `## Acceptance
> Criteria` item is `[x]`, rename to `DONE-plan-6-ai-monitoring-review-fixes.md`
> and move into `archive/` under this ticket folder.

## Context

Two inputs drive this plan:

1. **Reviewer findings (plan handoff):** one major (role separation built in SQL
   but never used at runtime — every module connects as `telemetry_migrator`), two
   minor (dispatch catch block swallows `err`; no dispatch failure-path test), two
   notes (6 near-duplicate append-only trigger functions; `.env.example` uses
   role-name-as-password defaults).
2. **External 74/100 scorecard:** build-hygiene regression — `dist/` contains TWO
   output layouts (`dist/{db,types,canonical.js}` AND stale `dist/src/**` +
   `dist/test/**`), and the deployable artifact is missing copied `.sql` migrations.

### Verified before planning (not assumed)

- `dist/` EXISTS with the duplicate layout: `dist/db/`, `dist/types/`,
  `dist/canonical.js` **and** `dist/src/{db,types,canonical.js}` **and**
  `dist/test/**`. Root cause: the old `tsconfig.json` has `rootDir: "."` +
  `outDir: "dist"` and was used to build before `tsconfig.build.json`
  (`rootDir: "src"`) existed; `tsc` never deletes outputs dropped from its input
  set, so both trees coexist. `dist/` is gitignored.
- `migrate.ts:14-15` reads `migrations/` via `import.meta.url` → compiled
  `dist/db/migrate.js` will look for `dist/db/migrations/*.sql`, which is **ABSENT**
  (`tsc` does not copy `.sql`). The scorecard's copy-step finding is real.
- **Scorecard MISSED a second runtime asset:** `gate.ts:31-32` reads
  `../schemas/candidate.schema.json` via `import.meta.url` → compiled build also
  needs `dist/schemas/candidate.schema.json`. This plan copies BOTH assets, not
  just migrations.
- 6 trigger functions (`reject_accepted_event_mutation`,
  `reject_settlement_fact_mutation`, `reject_event_rejection_mutation`,
  `reject_incomplete_run_registry_mutation`, `reject_late_event_registry_mutation`,
  `reject_run_reopen_log_mutation`) differ only in the table name in the RAISE
  message. Spread across migrations 0001/0003/0005.

### Migration-immutability constraint (critical for P2)

The 5 migrations are **already applied to a live Postgres** (plan-4: 30/30 tests
passed against live DB; `schema_migrations` records 0001–0005 as done). A forward-
only runner (`migrate.ts`) will NOT re-run an edited 0001/0003/0005 on an already-
migrated database. Therefore the trigger-dedup (P2) is done as a NEW migration
`0006_dedupe_triggers.sql` that `CREATE OR REPLACE`s a single generic function and
re-points all 6 triggers — NOT by editing the historical migrations in place. The
`last_error` column (P3) is added by the SAME new migration for the same reason.

## Confirmed items (ranked)

### P1 — Build hygiene: deterministic clean + build + asset copy (scorecard, HIGH)
**File:** `ai-monitoring/package.json` scripts.
**Changes:**
- Add `"clean": "rm -rf dist"`.
- Change `"build"` to `"pnpm clean && tsc -p tsconfig.build.json && pnpm copy:assets"`.
- Add `"copy:assets"`: copies `src/db/migrations/*.sql` → `dist/db/migrations/` AND
  `src/schemas/*.json` → `dist/schemas/` (both runtime-read via `import.meta.url`).
- Keep `typecheck` on `tsconfig.json` (checks `src`+`test`), unchanged.

**Why:** Guarantees a single deterministic `dist/` layout with the non-TS runtime
assets the compiled code actually reads. `tsconfig.build.json` already has the
correct `rootDir: "src"` + test exclusion (verified) — the ONLY missing pieces are
the pre-build clean and the asset copy. Do NOT rewrite the tsconfigs wholesale (the
scorecard's suggested rewrite drops `verbatimModuleSyntax`/`isolatedModules`/
`noImplicitOverride` the code already relies on — a regression risk for no benefit).

### P2 — Dedupe the 6 append-only triggers (note) via NEW migration (MEDIUM)
**File:** NEW `ai-monitoring/src/db/migrations/0006_dedupe_triggers.sql`.
**Change:** `CREATE OR REPLACE FUNCTION telemetry.reject_append_only_mutation()`
using `TG_TABLE_SCHEMA`/`TG_TABLE_NAME` in the RAISE message; `DROP TRIGGER` +
`CREATE TRIGGER ... EXECUTE FUNCTION telemetry.reject_append_only_mutation()` for
all 6 append-only tables; then `DROP FUNCTION` the 6 old per-table functions.
**Why:** ~48 lines of boilerplate → one function. New migration, not in-place edit,
because 0001/0003/0005 are already applied (see constraint above). Idempotent-safe
(`CREATE OR REPLACE`, `DROP ... IF EXISTS`).

### P3 — Dispatch error visibility (minor) (MEDIUM)
**Files:** `ai-monitoring/src/db/dispatch.ts`; the same NEW migration
`0006_dedupe_triggers.sql` (add `outbox.last_error text` column).
**Changes:**
- `dispatch.ts` catch block: `console.error` the `err` (currently unused), and pass
  a truncated error string into `recordFailure`.
- `recordFailure`: write `last_error` on both the backoff and dead-letter UPDATEs.
- Migration 0006: `ALTER TABLE telemetry.outbox ADD COLUMN IF NOT EXISTS last_error text;`
**Why:** A dead-lettered/failing row is currently undiagnosable — `err` is caught
and discarded. Additive column (backward compatible, matches the 0003 precedent of
additive `ALTER`s).

### P4 — Dispatch failure-path test (minor) (MEDIUM)
**File:** `ai-monitoring/test/db/dispatch.test.ts` (add a test).
**Change:** Force a projector failure and assert failure handling. The cleanest
trigger already exists: `dispatch.ts` THROWS on an unknown `consumer_name`. Insert
a committed event, then insert an extra outbox row with a bogus `consumer_name`,
drain, and assert `attempt_count` incremented + `next_attempt_at` moved out +
(after enough attempts) `dead_lettered_at` set + `last_error` populated.
**Why:** The entire retry/backoff/dead-letter path (`recordFailure`,
`backoffSeconds`, `DISPATCH_MAX_ATTEMPTS`) has zero coverage. DB test → skips when
Postgres unreachable, same `isDbReachable()` pattern as siblings.

### P5 — Role-separation doc-accuracy fix (major → doc fix per user) (HIGH)
**Files:** `ai-monitoring/README.md`; `ai-monitoring/docs/decisions/0001-stack.md`.
**Changes:**
- README "What is built" table: remove the unqualified "Role separation | migration
  0002" row claim; restate it as schema-level-only.
- README "Known gaps": ADD a bullet stating role separation exists in SQL
  (grants/revokes) but is NOT connection-enforced at runtime — every worker connects
  via a single `DATABASE_URL` (`telemetry_migrator`); the append-only triggers, not
  role grants, are the effective mutation guard today.
- `0001-stack.md`: add a short note under the roles bullet with the same caveat.
**Why:** User chose the doc-accuracy fix over implementing per-worker role routing
(consistent with plan-4's "defer worker daemons until a real deployment target").
The gap is defense-in-depth documentation accuracy, not an active hole — triggers
already enforce append-only regardless of connecting role.

### P6 — `.env.example` password placeholders (note) (LOW)
**File:** `ai-monitoring/.env.example`.
**Change:** Replace `TELEMETRY_INGEST_PASSWORD=telemetry_ingest` (and projector/
operator) with `changeme-<role>` placeholders; add a one-line comment that these are
example-only and must be set per environment.
**Why:** Role-name-as-password is a weak-by-example default. Currently moot (roles
never connected to per P5) but cheap to harden.

## Out Of Scope (Things To Avoid)

- Do NOT implement per-worker role connection routing (user chose doc fix).
- Do NOT edit already-applied migrations 0001/0003/0005 in place — use NEW 0006.
- Do NOT rewrite `tsconfig.json`/`tsconfig.build.json` wholesale (scorecard's
  suggested rewrite drops strictness flags the code relies on). Only package.json
  scripts change for build hygiene.
- Do NOT perform the larger `src/` domain/projections/workers refactor (that is
  plan-3, separately persisted, not started; the scorecard re-raised it but it is
  out of scope here).
- Do NOT implement Layer 5 content path (scorecard's largest gap — separate work).
- Do NOT `git add`/commit either repo.
- The agent must NOT run `rm -rf`/`pnpm build`/`pnpm clean` (hard-denied) — the USER
  runs P7 verification.

## Affected Paths

- `ai-monitoring/package.json` — EDIT (scripts: clean/build/copy:assets).
- `ai-monitoring/src/db/migrations/0006_dedupe_triggers.sql` — NEW (trigger dedup +
  `outbox.last_error` column).
- `ai-monitoring/src/db/dispatch.ts` — EDIT (log err, write last_error).
- `ai-monitoring/test/db/dispatch.test.ts` — EDIT (add failure-path test).
- `ai-monitoring/README.md` — EDIT (role-separation caveat).
- `ai-monitoring/docs/decisions/0001-stack.md` — EDIT (role-separation caveat).
- `ai-monitoring/.env.example` — EDIT (password placeholders).

## Contracts And Boundaries

- Migrations are forward-only and immutable once applied; new behavior = new file.
- `outbox` additive column only (no drop/rename); backward compatible.
- Append-only trigger semantics must be preserved exactly by the generic function
  (same BEFORE UPDATE OR DELETE, same RAISE EXCEPTION effect).
- `dispatch.ts` transactional exactly-once semantics must not change — only error
  observability is added.
- Doc edits must not claim runtime role enforcement that does not exist.

## Todo Plan

### P1 — package.json build hygiene
- [x] Add `clean`, `copy:assets` scripts; chain them into `build`. Done —
      `package.json:12-14`; `copy:assets` copies BOTH `src/db/migrations/*.sql` and
      `src/schemas/*.json`.

### P2 — trigger dedup (new migration)
- [x] Create `0006_dedupe_triggers.sql`: generic `reject_append_only_mutation()`,
      re-point all 6 triggers, drop the 6 old functions. Done — grep-confirmed 6
      DROP FUNCTION + 6 re-pointed triggers + 1 generic fn. RAISE text keeps the
      `append-only` substring so existing `/append-only/` test assertions still pass.

### P3 — dispatch error visibility
- [x] `0006`: `ALTER TABLE telemetry.outbox ADD COLUMN IF NOT EXISTS last_error text`.
      Done — `0006:80`.
- [x] `dispatch.ts`: log `err`; write `last_error` in `recordFailure` (both branches).
      Done — `console.error` on failure + `errorText()` truncation + `last_error`
      written in both the backoff and dead-letter UPDATEs.

### P4 — dispatch failure-path test
- [x] Add unknown-consumer failure test asserting attempt_count / next_attempt_at /
      dead_lettered_at / last_error progression. Done — drains `DISPATCH_MAX_ATTEMPTS`
      times against a bogus-consumer outbox row, asserts progression + dead-letter +
      last_error, and that a dead-lettered row is not retried further.

### P5 — role-separation doc fix
- [x] README: restate role separation as schema-only; add Known-gaps bullet. Done —
      table row qualified + new Known-gaps bullet.
- [x] `0001-stack.md`: add the same caveat. Done — runtime-caveat blockquote added.

### P6 — .env.example placeholders
- [x] Replace role-name-as-password defaults with `changeme-*` + comment. Done.

### P7 — Verification (USER runs; agent cannot rm -rf/build)
- [x] `pnpm typecheck` clean. Done — ran by agent, exit 0 (after fixing a TS7022 the
      typecheck itself caught in the new test's inline query typing — real catch).
- [ ] `pnpm clean && pnpm build` (or `pnpm build`) → `tree dist` shows ONE layout
      (`dist/{canonical.js,db/,types/,schemas/}`, `dist/db/migrations/*.sql` present,
      NO `dist/src/**`, NO `dist/test/**`). **USER STEP — agent hard-denied on
      `rm -rf`/`pnpm build`.**
- [ ] `pnpm db:up && pnpm db:migrate` applies `0006` (or skips cleanly if DB down).
      **USER STEP.**
- [ ] `pnpm test` (or `pnpm db:test`) — including the new failure-path test — passes
      against live Postgres, or skips cleanly when DB unreachable. **USER STEP.**

## Acceptance Criteria

- [x] AC-01: `package.json` has `clean` (`rm -rf dist`) and `copy:assets` (copies
      `src/db/migrations/*.sql` AND `src/schemas/*.json`), both chained into `build`.
      **Verified** (`package.json:12-14`).
- [ ] AC-02: A clean `pnpm build` yields exactly ONE dist layout with
      `dist/db/migrations/*.sql` and `dist/schemas/candidate.schema.json` present and
      NO `dist/src/**` / `dist/test/**` (USER-verified in P7). **OPEN — USER STEP**
      (agent cannot `rm -rf`/build). Scripts are correct; runtime proof pending.
- [x] AC-03: `0006_dedupe_triggers.sql` exists; all 6 append-only tables use ONE
      generic function; the 6 old functions are dropped; append-only behavior
      unchanged (UPDATE/DELETE still rejected). **Verified statically** (grep: 6 drops,
      6 re-pointed triggers, 1 generic fn; RAISE keeps `append-only` substring so the
      3 existing `/append-only/` test assertions remain green). Live-DB apply is P7.
- [x] AC-04: `outbox.last_error` column added (additive); `dispatch.ts` logs `err`
      and persists `last_error` on failure/dead-letter. **Verified** in source.
- [x] AC-05: A dispatch failure-path test exists and asserts attempt/backoff/
      dead-letter/last_error behavior (skips when DB unreachable). **Verified** —
      test added, typechecks; live-DB run is P7.
- [x] AC-06: README no longer claims runtime role separation; Known-gaps documents
      the schema-only-not-connection-enforced reality; `0001-stack.md` matches.
      **Verified** in source.
- [x] AC-07: `.env.example` no longer uses role-name-as-password defaults. **Verified.**
- [x] AC-08 (negative): migrations 0001/0003/0005 unedited; no tsconfig rewrite; no
      per-worker role routing; no plan-3 refactor; no Layer 5; neither repo committed.
      **Verified** — 0001/0003/0005 still hold their original trigger fns (untouched);
      tsconfigs unchanged; only the 7 planned files edited + 1 new migration.

## Verification Plan

- **Agent can do now (source-level):** `pnpm typecheck` (allowed), grep-level checks
  that 0001/0003/0005 are unchanged, that `dist/src` is not referenced in source,
  that the 6 old trigger functions are dropped in 0006 and the generic one created.
- **USER must run (agent hard-denied on rm -rf/build):** the P7 clean-build +
  `tree dist` + live-DB migrate/test sequence. Report results back to close AC-02.

## Risks And Rollback

- **Risk — 0006 trigger swap window:** dropping/recreating triggers is fast DDL in a
  txn; the migration wraps it in BEGIN/COMMIT so append-only enforcement is never
  absent to a concurrent writer mid-migration. Mitigation: single transaction.
- **Risk — copy:assets portability:** `mkdir -p`/`cp` are Linux-only (fine here per
  plan-4's Linux-only note); flagged if this ever targets Windows.
- **Risk — build not re-verifiable by agent:** the duplicate-dist fix cannot be
  agent-confirmed (no rm -rf/build). Mitigation: P7 is explicitly a USER step; AC-02
  stays open until the user reports `tree dist`.
- **Rollback:** all source edits revert by restoring prior file content; 0006 is a
  new file (delete to revert); `dist/` is gitignored regenerable output.

## Handoff Notes

- Agent implements P1–P6 as source edits, runs `pnpm typecheck`, and hands P7
  (clean build + live-DB) to the user because `rm -rf`/`build` are hard-denied.
- Recommended next step after P7 passes: reviewer agent via `/review-diff` on the
  ai-monitoring working tree.
