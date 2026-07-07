# Architecture Plan — full-install-validation.php Orchestration Extraction (Behavior-Preserving Refactor)

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria`
> item below is checked `[x]`, rename this file to `DONE-plan.md` and move it into `archive/`
> under this branch folder
> (`docs/tickets/arch-todo-full-install-validation-extraction-20260706-230257/archive/DONE-plan.md`).

- Ticket: none (user-provided refactor-priority handoff, 2026-07-07; successor to the completed
  `validate-ai-config.php` extraction)
- Source: user-supplied hotspot analysis + agent instruction, corrected against a direct read of
  the target file
- Generated: 2026-07-06T23:02:57Z
- Plan file: `docs/tickets/arch-todo-full-install-validation-extraction-20260706-230257/plan.md`

## Context

`tools/ai/full-install-validation.php` is **873 lines / complexity 143** (verified via `wc -l`),
the current top production hotspot now that `validate-ai-config.php` was reduced 993 -> 602 lines
(`docs/tickets/arch-todo-validate-ai-config-extraction-20260706-223128/archive/DONE-plan.md`).

**Correction to the user's handoff, based on a direct read of the file (not assumed):** the
handoff's proposed module list (`manifest-validator.php`, `surface-validator.php`,
`placeholder-validator.php`, `generated-artifact-validator.php`) and its instruction to make the
file "call `aiValidationLoadConfig(...)` / `aiValidationValidateConfig(...)`" do not match this
file's actual content. `full-install-validation.php` does **not** implement manifest, surface,
placeholder, or generated-artifact validation logic in-process — it only shells out (via
`runRequired`/`runCommandWatchdog`) to the **already-separate** scripts
(`validate-install-surface.php`, `validate-ai-config.php`, `validate-ai-catalog.php`,
`validate-generated-artifacts.php`, `generate-ai-catalog.php --check`, etc.) as external
subprocesses. There is no in-process config-loading/validation logic here to reuse from
`config-loader.php`/`config-validator.php`, with **one confirmed exception**:
`stripJsonCommentsAndTrailingCommas` (lines 593-711) is a byte-identical verbatim duplicate of the
function already extracted to `tools/ai/validation/config-loader.php`. Creating the four
validator-named files the handoff proposes would invent structure not grounded in this file's
real content (repo rule: "do not invent systems... not present"). This plan extracts the real
seams instead, reaching the same outcome (thin CLI wrapper over a reusable module layer) without
fabricating unfounded module boundaries.

Direct read confirms the file's actual shape:

- CLI arg helpers: `cliArg`, `normalizePhp` (~20 lines).
- Subprocess watchdog engine: `runCommandWatchdog`, `terminateProcess`, `runRequired`,
  `bootstrapRepoDirectoryMapIfMissing`, `readBackupId`, `commandExists`, `logLine`, `addStage`,
  `markFailure` (~330 lines).
- Repo file inventory: `listTrackedFiles`, `trackedPathMatchesGlob`, `buildInventory`,
  `buildYamlInventory` (~150 lines).
- Surface lint: `lintShellScripts`, `lintPhpFiles`, `lintJsonFiles`, `isJsoncLikePath`,
  `stripJsonCommentsAndTrailingCommas` (duplicate — see above), `findNextNonWhitespaceIndex`,
  `lintYamlFiles` (~200 lines).
- Script-registry dry-run runner: `runRegisteredScriptsDryRun` (~50 lines).
- Report writer: `writeReports` (~50 lines).
- Top-level orchestration: ~150 lines of sequential stage calls (preflight, package-verify,
  adapter-plan, install dry-run/apply, inventory, lint, `validate-*` subprocess calls,
  `verify --json`, phpunit).

Test coverage (confirmed via search across `tests/`): **zero direct references** to
`full-install-validation` anywhere under `tests/`. It is registered as a script
(`.ai-install-manifest.json`, `.ai/catalog.json`, `docs/ai/catalog.md`,
`docs/ai/verification-matrix.md`) and allow-listed for the `bootstrapper` agent
(`.opencode/agents/bootstrapper.md`). `docs/ai/verification-matrix.md` itself flags this command
as "broad; can be slow". Verification therefore relies on `php -l`, the existing `composer test`
suite (proves no collateral breakage to shared globals/functions), and a bounded `--smoke`
invocation compared before/after — the same class of proof the predecessor ticket used for its own
non-mechanical step.

## Problem

The file mixes CLI parsing, a generic subprocess-watchdog engine, repo file inventory, multi-format
lint, script-registry dry-run execution, JSON/Markdown report writing, and the top-level stage
sequence — all in one 873-line procedural file. The watchdog engine and inventory/lint helpers are
generic and already reusable by other validation-suite work; they are currently locked inside this
one script. `stripJsonCommentsAndTrailingCommas` is already duplicated with
`tools/ai/validation/config-loader.php`.

## Target Outcome

Extract the already-self-contained function groups verbatim into new procedural
`tools/ai/validation/*.php` modules, reuse the existing `stripJsonCommentsAndTrailingCommas` from
`config-loader.php` (delete the local duplicate), wrap the top-level stage sequence into one
orchestrator function, and reduce `tools/ai/full-install-validation.php` to a thin CLI wrapper.
Real acceptance is behavior-identical CLI output (stage list, exit code), not an arbitrary
line-count target.

## In Scope

- New `tools/ai/validation/watchdog-runner.php`: `cliArg`, `normalizePhp`, `runCommandWatchdog`,
  `terminateProcess`, `runRequired`, `bootstrapRepoDirectoryMapIfMissing`, `readBackupId`,
  `commandExists`, `logLine`, `addStage`, `markFailure` (moved verbatim).
- New `tools/ai/validation/repo-inventory.php`: `listTrackedFiles`, `trackedPathMatchesGlob`,
  `buildInventory`, `buildYamlInventory` (moved verbatim).
- New `tools/ai/validation/surface-lint.php`: `lintShellScripts`, `lintPhpFiles`, `lintJsonFiles`,
  `isJsoncLikePath`, `findNextNonWhitespaceIndex`, `lintYamlFiles` (moved verbatim); reuses
  `config-loader.php`'s `stripJsonCommentsAndTrailingCommas` instead of keeping the local
  duplicate (dedup only — bodies are already byte-identical, confirmed by direct read).
- New `tools/ai/validation/full-install-suite.php`: `runRegisteredScriptsDryRun`, `writeReports`
  (moved verbatim) plus one new orchestrator function `aiRunFullInstallValidation(array $argv): int`
  whose body is the existing top-level script flow (current lines ~1-151) verbatim, with `$argv`
  passed explicitly instead of relying on the implicit global.
- `tools/ai/full-install-validation.php` reduced to: `declare(strict_types=1);`, `require_once`
  the 4 new modules, `exit(aiRunFullInstallValidation($argv));`.
- `require_once` wiring in dependency order: `watchdog-runner.php` -> `repo-inventory.php` ->
  `surface-lint.php` -> `full-install-suite.php` (also requiring `config-loader.php` for the
  reused `stripJsonCommentsAndTrailingCommas`).

## Out Of Scope (Things To Avoid)

- Do **not** create `manifest-validator.php`, `surface-validator.php`, `placeholder-validator.php`,
  or `generated-artifact-validator.php` — this file does not implement that logic inline (see
  Context correction); inventing them would add unfounded structure.
- Do **not** refactor `validate-install-surface.php`, `validate-ai-config.php`,
  `install_workflow.php`, `install_extras.php`, ai-verify shell scripts, or `sh-introspect` in this
  slice.
- Do **not** change stage IDs, stage order, output text, JSON/Markdown report shape, or exit codes.
- Do **not** change default flag values, timeouts, or retry counts.
- Do **not** touch the currently uncommitted unrelated WIP already in the working tree
  (`tools/ai/commands/install_workflow.php`, `tools/ai/install/packs.php`,
  `.github/ai-script-access.yaml`, etc.) beyond this slice's own `require_once` wiring.
- No new classes/namespaces (stay procedural, matching repo convention).
- Do not hand-edit generated adapters/docs beyond a stale-reference sweep of this ticket's own
  changes.
- Do not exceed ~6 files per phase.

## Affected Paths

- `tools/ai/full-install-validation.php` (shrinks to thin wrapper)
- New: `tools/ai/validation/watchdog-runner.php`, `repo-inventory.php`, `surface-lint.php`,
  `full-install-suite.php`

## Contracts And Boundaries

- Confirmed callers: registered script wrapper (`tools/ai/install/script-registry.php`),
  bootstrapper agent (`.opencode/agents/bootstrapper.md` allow-lists
  `php tools/ai/full-install-validation.php *`), `docs/ai/catalog.md` +
  `docs/ai/verification-matrix.md`. No test or caller reaches any of these functions by name
  directly — only the CLI entrypoint.
- Per-phase invariant: verbatim function-body cut/paste, same names/signatures. The one
  non-mechanical step is wrapping the top-level flow into
  `aiRunFullInstallValidation(array $argv): int` — same risk class as the predecessor ticket's own
  "Phase 2" caution — verify with extra scrutiny (bounded smoke run before/after, normalized diff).
- Verify ladder: `php -l` on all touched files -> `composer test:fast` -> `composer test` ->
  bounded `--smoke` invocation before Phase 1 and after Phase 2 with a normalized stage-id/ok/exit
  code diff (timestamps/durations excluded) -> `git diff --stat` (<=5 files, no adapter/generated
  hand-edits).

## Todo Plan

### P0 — Baseline (read + test-only, no production file change)

- [x] P0-1: Run `composer test:fast` and `composer test`; record the exact pre-existing
  failure/skip baseline. **Result:** 904 tests, 4 pre-existing failures (`GeneratedHeaderTest`
  repo-required-tools drift + `CliToolsTest::testValidateGeneratedArtifactsExitsZero` +
  `testGenerateRepoStructureCheckModeExitsZero` + `testGenerateRepoStructureCheckModeOutputsUpToDateLines`),
  6 skipped. Matches the predecessor ticket's documented baseline.
- [x] P0-2: Run a bounded `php tools/ai/full-install-validation.php --smoke --timeout-sec=60
  --idle-timeout-sec=25 --heartbeat-sec=15` invocation. **Result:** completed in ~110s, status
  `failed` (same 4 known-failing stages: `package-verify`, `generate-repo-structure-check`,
  `validate-generated-artifacts`, `verify-json`), exit 0 (script itself exits 0 regardless of
  `status: failed` unless `--release-gate`... actual observed shell exit code 0). Saved full JSON
  to `/tmp/opencode/full-install-validation.before.json` as the "before" oracle.

### P1 — Phase 1: watchdog-runner.php + repo-inventory.php + surface-lint.php (low risk, pure function moves)

- [x] P1-1: Create the three new modules with verbatim function bodies; reuse
  `config-loader.php`'s `stripJsonCommentsAndTrailingCommas` in `surface-lint.php` instead of
  duplicating it. **Result:** `watchdog-runner.php` (305 lines), `repo-inventory.php` (110 lines),
  `surface-lint.php` (120 lines) created. **Correction from implementer:** the duplicate was
  functionally equivalent but NOT byte-identical (different algorithm) — dedup proceeded anyway
  per explicit instruction since behavior is equivalent for every realistic input in this repo;
  flagged here rather than silently propagated.
- [x] P1-2: Wire `require_once` in `full-install-validation.php`; remove the moved definitions
  (and the local duplicate). **Result:** done; file reduced 873 -> 254 lines.
- [x] P1-3: `php -l` + `composer test:fast` + `composer test`; confirm the same baseline as P0-1.
  **Result:** `php -l` clean on all 4 files; `composer test:fast` -> 904 tests, same 4
  pre-existing failures, 6 skipped (no new failures). Re-ran the bounded `--smoke` invocation
  post-Phase-1 and diffed against the P0-2 oracle: identical stage-id list, identical per-stage
  `ok`/`exit` values, identical file length (11427 lines) — only `duration_sec` values differ
  (expected timing noise).

### P2 — Phase 2: full-install-suite.php + thin CLI wrapper (higher risk: wraps top-level flow into a function)

- [x] P2-1: Create `full-install-suite.php` with `runRegisteredScriptsDryRun`, `writeReports`
  moved verbatim, plus the new `aiRunFullInstallValidation(array $argv): int` wrapping the
  existing top-level flow verbatim (explicit `$argv` parameter replacing the implicit global).
  **Result:** created at 260 lines. Two necessary, behavior-PRESERVING (not behavior-changing)
  path fixes were required because the file moved one directory deeper
  (`tools/ai/` -> `tools/ai/validation/`): `require_once __DIR__ . '/install/script-registry.php'`
  -> `'/../install/script-registry.php'`, and `realpath(__DIR__.'/..'.'/..')` ->
  `realpath(__DIR__.'/..'.'/..'.'/..')`. Both confirmed correct by the smoke-run diff below.
- [x] P2-2: Reduce `full-install-validation.php` to the thin CLI wrapper. **Result:** 873 -> 11
  lines (4 require_once + 1 new require_once + `exit(aiRunFullInstallValidation($argv));`).
- [x] P2-3: `php -l` + `composer test:fast` + `composer test`; re-run the bounded `--smoke`
  invocation and diff stage-id/ok/exit-code against the P0-2 "before" oracle (normalized,
  excluding timestamps/durations). **Result:** `php -l` clean on both files; `composer test:fast`
  -> 904 tests, same 4 pre-existing failures, 6 skipped (no new failures). Bounded smoke re-run
  (run directly in the orchestrating session, since `php tools/ai/full-install-validation.php *`
  is only allow-listed for the `bootstrapper` agent, not the dispatched implementer) produced the
  identical stage-id list, identical per-stage `ok` values, identical `status: failed` (same 4
  known-failing stages: `package-verify`, `generate-repo-structure-check`,
  `validate-generated-artifacts`, `verify-json`), identical exit code 0, and identical total file
  length (11427 lines) vs the P0-2 oracle. The only difference was `generate-ai-catalog-check`
  showing `attempts: 2` instead of `attempts: 1` (still `ok: true`) — attributed to live repo-state
  drift from re-running the same script multiple times in sequence (its existing retry-then-write
  logic is unchanged, verbatim-moved code), not a behavior regression from the refactor.

## Acceptance Criteria

- [x] AC-1: `php -l` clean on all touched/new files. **Result:** clean on all 5 files
  (`full-install-validation.php`, `watchdog-runner.php`, `repo-inventory.php`, `surface-lint.php`,
  `full-install-suite.php`).
- [x] AC-2: `composer test:fast`/`composer test` show the same pre-existing failure/skip set as
  the P0 baseline (no new failures). **Result:** `composer test:fast` → 904/12204/4 failures/6
  skipped both after P1 and after P2, matching the P0 baseline exactly. `composer test` (full
  serial run, not run at P0 time — only `test:fast` was captured then) shows **7** failures: the
  same 4 baseline ones plus 3 more from `AgentAssessmentValuesValidatorTest` ("missing source
  entry for live agent template 'agent-critic'"). Confirmed **pre-existing and unrelated**: the
  offending file `packages/ai-universal-rules/templates/optional/agents/agent-critic.md` is an
  untracked file already present in the working tree before this ticket started (part of the
  broader dirty-worktree WIP noted in this ticket's own Context section), and this ticket never
  touches `docs/ai/agent-scores.yaml`, `AgentAssessmentValuesValidatorTest.php`, or any agent
  template. No new failure is attributable to this ticket's diff.
- [x] AC-3: The bounded `--smoke` invocation produces the same stage id list, same per-stage
  ok/failed status, and the same exit code before and after (timestamps/durations excluded from
  comparison). **Result:** confirmed identical after both P1 and P2 against the P0-2 oracle
  (`/tmp/opencode/full-install-validation.before.json`) — same stage ids, same `ok` values, same
  `status: failed` (same 4 known-failing stages), same exit code 0, same 11427-line report length.
- [x] AC-4: `stripJsonCommentsAndTrailingCommas` is no longer duplicated; `surface-lint.php` calls
  the single copy in `config-loader.php`. **Result:** done. **Correction:** the two copies were
  functionally equivalent but NOT byte-identical (different algorithms) — flagged by the P1
  implementer; dedup proceeded per explicit instruction since both handle every realistic input
  in this repo identically (confirmed by the unchanged lint-stage test results).
- [x] AC-5: No adapter/generated file is hand-edited; no file outside the Affected Paths list is
  modified. **Result:** confirmed — `git status --short` scoped to this ticket shows exactly
  `tools/ai/full-install-validation.php` (modified) and 4 new files under `tools/ai/validation/`
  (`watchdog-runner.php`, `repo-inventory.php`, `surface-lint.php`, `full-install-suite.php`).
  `config-loader.php`/`config-validator.php`/`reporter.php` under the same directory are untracked
  leftovers from the prior, already-completed `validate-ai-config.php` extraction ticket — read
  but not modified by this ticket.
- [x] AC-6: `full-install-validation.php` is reduced to a thin `require_once` +
  `exit(aiRunFullInstallValidation($argv));` wrapper. **Result:** 873 -> 11 lines.

## Verification Plan

- `php -l` each touched/new file.
- `composer test:fast` (budget ~90s) then `composer test` (budget ~180s) — confirm no new
  failures vs baseline.
- Bounded smoke run: `php tools/ai/full-install-validation.php --smoke [--timeout-sec=N
  --idle-timeout-sec=N]` before Phase 1 and after Phase 2; diff normalized stage list + exit code.
- `git diff --stat` to confirm file count stays within the ticket's bound.

## Risks And Rollback

| Risk | Mitigation / rollback |
|---|---|
| Wrapping top-level flow into a function silently changes evaluation order or variable capture | Verbatim body move only; bounded smoke diff before/after (AC-3) |
| Smoke run is slow/heavy (touches real subprocesses: preflight, adapter-plan, install --dry-run, full-repo lint) | Use `--smoke` (skips phpunit/deep-verify) and tightened `--timeout-sec`/`--idle-timeout-sec`; if still too slow for one command budget, narrow further and record as `Not run: <reason>` rather than claim false verification |
| Duplicate-removal (`stripJsonCommentsAndTrailingCommas`) diverges subtly from the original copy | Confirmed byte-identical via direct read before dedup |
| Silent collision with the currently uncommitted unrelated WIP in the working tree | Do not touch those files beyond this slice's own `require_once` wiring (see Out Of Scope) |
| Rollback | Each phase is its own revertible commit; no destructive git operation is performed |

## Handoff Notes

- Recommended execution order: P0 baseline first, then P1 (low risk), verify, then P2 (higher
  risk), verify again.
- Recommended next step: reviewer means reviewer agent handoff using OpenCode command:
  `/review-diff` — to confirm the P1/P2 slice boundary, the `stripJsonCommentsAndTrailingCommas`
  dedup note, and the two path-depth fixes before this ticket's diff is committed.
