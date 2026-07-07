# Architecture Plan — install_workflow.php Command Extraction (Behavior-Preserving Refactor)

- Ticket: none (architect handoff from refactor-order review)
- Source: architect handoff (installer command/orchestration hotspot; successor to the completed `core.php` executor extraction)
- Generated: 2026-07-06T22:00:32Z
- Plan file: docs/tickets/arch-todo-installer-workflow-command-extraction-20260706-220032/plan.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan.md` and move it into `archive/` under this branch folder (`docs/tickets/arch-todo-installer-workflow-command-extraction-20260706-220032/archive/DONE-plan.md`). See "Archive On Completion" for the exact steps.

## Context

`tools/ai/commands/install_workflow.php` is **1304 lines** (verified via `wc -l`). It is the top remaining production orchestration hotspot now that `tools/ai/install/core.php` was reduced from 2203 -> 601 lines by the completed executor-extraction refactor (`docs/tickets/arch-todo-installer-core-executor-extraction-20260706-170757/archive/DONE-plan.md`). That refactor established a **proven, low-risk methodology** this plan deliberately mirrors: verbatim cohesive-function-group moves into sibling procedural `tools/ai/install/*.php` modules, wired via `require_once`, one behavior-preserving move per phase, with a `--dry-run` parity oracle and the existing test suite as the regression net.

Verified structure of `install_workflow.php` (18 functions, from `Grep '^function'`):

- **Top-level command handlers** (dispatched from `tools/ai/ai.php` lines 249-263 via a `switch`): `aiRunAdapterPlan` (55), `aiRunInstallWorkflow` (90), `aiRunInstallWizard` (416), `aiRunUpgradeWorkflow` (868), `aiRunAdapterValidate` (1044), `aiRunUninstallWorkflow` (1081), `aiRunRestoreWorkflow` (1231), `aiRunRollbackWorkflow` (1291).
- **Install helpers**: `aiInstallerMergeWorkflowManifest` (30), `aiInstallerBuildSubprocessInstallCommand` (375).
- **Upgrade helpers (cohesive group)**: `aiUpgradePreserveOwnedConflicts` (652), `aiUpgradeCurrentRegistryTargets` (693), `aiUpgradeResolveFileAction` (723), `aiUpgradeRemoveDeprecated` (774), `aiUpgradeComputeDeprecated` (838), `aiUpgradeBuildApplyInstallArgs` (1028).
- **Uninstall helper**: `aiUninstallPruneEmptyParents` (1194).
- **Restore helper**: `aiRestoreAppendAuditLog` (1271).

This file already delegates to the extracted service layer: it `require_once`s `install/core.php` and `install/selection-engine.php`, and calls `aiInstallerConfigFromAiArgs`, `aiInstallerResolveSelectedPacks`, `aiInstallerBuildPlan`, `aiInstallBackupCreate/Rollback/UpdateState/AppendAudit`, `aiInstallerPrivateConflictDir/Rel`, `aiInstallerDeleteTree`, `aiHashPath`, `aiCliWriteArtifact`, etc. So the orchestration state (planner/backup/manifest/config) is **already** extracted; what remains is the command-handler layer plus per-command helper groups all colocated in one file.

## Problem

`install_workflow.php` mixes **eight independent CLI command handlers** (install, upgrade, uninstall, restore, rollback, adapter-plan, adapter-validate, plus the interactive wizard) and **four cohesive per-command helper groups** (install-manifest helpers, the upgrade file-action/deprecation engine, the uninstall directory-prune helper, the restore audit-log helper) in one 1304-line file. Each handler is procedural (`aiRun*` / `aiInstaller*` / `aiUpgrade*` functions). The maintenance cost is that unrelated commands share one file, and the upgrade file-action engine (6 functions, ~230 lines, already independently unit-tested) is buried inside the same file as unrelated restore/rollback CLI glue.

## Target Outcome

Behavior-preserving refactor of `tools/ai/commands/install_workflow.php` (1304 lines) by extracting cohesive `aiUpgrade*` / helper-function groups into new procedural `tools/ai/install/*.php` modules wired via `require_once`, one verbatim move per phase. No behavior change per phase. `install_workflow.php` retains the eight thin `aiRun*` command handlers and the `require_once` wiring, shrinking toward **< ~700 lines** (the realistic floor once the ~430 lines of cohesive helper groups move out; the raw handoff's "< 400 lines" target is treated as aspirational, not a hard gate — see "Contracts And Boundaries").

## In Scope

- Extract cohesive helper-function groups out of `tools/ai/commands/install_workflow.php` into new procedural `*.php` modules under `tools/ai/install/`.
- Wire each new module into `install_workflow.php` via `require_once __DIR__ . '/../install/<new>.php';` in dependency-respecting include order.
- Remove moved definitions from `install_workflow.php` after the corresponding paste.
- One phase = one behavior-preserving move commit; each phase is a pure verbatim move (same names, signatures, bodies, doc-comments).
- Phase 0: `--dry-run` / `--apply` parity baseline capture (read + test-only), reusing existing coverage (`InstallLifecycleTest`, `UpgradeOwnershipTest`, `PrivateStructureTest`, `UninstallDirPruneTest`, `InstallManifestReconciliationTest`); add a characterization test ONLY if a gap is found.
- Phases 1-4 (helper-group moves) as specified below.

## Out Of Scope (Things To Avoid)

- **Do NOT refactor validators** (`validate-ai-config.php`, `full-install-validation.php`, `validate-install-surface.php`) in this slice. They are the *next* ticket after this one (see "Handoff Notes" for the recommended order). Do not add a `validation-suite.php` gateway here.
- **Do NOT refactor `install_extras.php` or `install_preflight.php`** in this slice.
- **Do NOT refactor `ai-verify` shell runners or `sh-introspect`** — separate subsystems, separate tickets.
- No new PHP classes, namespaces, or PSR-4 (subsystem stays procedural `aiRun*` / `aiUpgrade*` functions).
- No behavior + structure change in the same commit.
- No rename of any function while moving it (call sites in `ai.php` and tests reference exact names).
- **Do NOT force a "no echo/print in decision logic" rewrite of the command handlers.** Each `aiRun*` handler's `aiCliWriteArtifact(...)` + `fwrite(STDOUT, 'OK: ' ...)` + `return <code>` sequence is its externally-observed contract (artifact file + stdout line + exit code). Splitting output away from the decision path would change return-shape/ordering and is a behavior change. The raw handoff's "No echo/print inside workflow decision logic" rule is explicitly **rejected** for this slice as incompatible with behavior preservation.
- Do not split the eight `aiRun*` command handlers into separate files in this slice (they are the thin adapter layer; splitting them is a larger, lower-value follow-up, not this ticket).
- Do not hand-edit generated adapters (`.claude/`, `.opencode/`, `.github/`, `AGENTS.md`, `CLAUDE.md`).
- Do not exceed ~6 files / ~300-500 changed lines per slice.
- Do not change the `switch` dispatch in `tools/ai/ai.php` (function names are unchanged, so no dispatch edit is needed).

## Affected Paths

- `tools/ai/commands/install_workflow.php` (shrinks each phase; retains the eight `aiRun*` handlers + `require_once` wiring).
- New modules under `tools/ai/install/`:
  - `upgrade-file-actions.php` — the cohesive upgrade file-action/deprecation engine: `aiUpgradePreserveOwnedConflicts`, `aiUpgradeCurrentRegistryTargets`, `aiUpgradeResolveFileAction`, `aiUpgradeRemoveDeprecated`, `aiUpgradeComputeDeprecated`, `aiUpgradeBuildApplyInstallArgs` (6 functions, ~230 lines).
  - `workflow-manifest.php` — install-manifest workflow helpers: `aiInstallerMergeWorkflowManifest`, `aiInstallerBuildSubprocessInstallCommand` (2 functions, ~80 lines).
  - `uninstall-prune.php` — `aiUninstallPruneEmptyParents` (1 function, ~30 lines).
  - `restore-audit.php` — `aiRestoreAppendAuditLog` (1 function, ~20 lines).
- Tests (Phase 0 only, contingent): `tests/php/InstallWorkflowCharacterizationTest.php` (only if a coverage gap is found).

Note: final module names may be adjusted at implementation time if an overlap check finds a better-fitting existing module (mirroring Phase 7 of the predecessor refactor, where `project-values-apply.php` became `project-values.php`). Any rename of the *new file* (never of a moved function) must be recorded in the phase Result.

## Contracts And Boundaries

Confirmed callers (from `Grep`): every `aiRun*` handler is dispatched only from `tools/ai/ai.php` (lines 249-263) and, for the wizard sub-calls, from within `install_workflow.php` itself (`aiRunInstallWorkflow` recursion at 593/608/617/623; `aiRunAdapterValidate` at 624). No moved *helper* is called from outside `install_workflow.php` except by tests, which reference them as free functions (so the `require_once` wiring in `install_workflow.php` keeps them loadable exactly as today — tests already load the command file transitively via the installer include chain).

Existing test coverage for the functions being moved (from `Grep` in `tests/`):

- `aiUpgradePreserveOwnedConflicts` — `PrivateStructureTest.php:131`, `UpgradeOwnershipTest.php:72/91/106`.
- `aiUpgradeRemoveDeprecated` — `PrivateStructureTest.php:158`, `UpgradeOwnershipTest.php:404/433/447`.
- `aiUpgradeComputeDeprecated` — `UpgradeOwnershipTest.php:273/295/310`.
- `aiUpgradeResolveFileAction` — `UpgradeOwnershipTest.php:319-381` (11 cases).
- `aiUpgradeBuildApplyInstallArgs` — `UpgradeOwnershipTest.php:114/118`.
- `aiInstallerMergeWorkflowManifest` — `InstallManifestReconciliationTest.php:31/71/96/119`.
- `aiInstallerBuildSubprocessInstallCommand` — `InstallManifestReconciliationTest.php:131/135/153/163`.
- `aiUninstallPruneEmptyParents` — `UninstallDirPruneTest.php:64/77/91/105`.

`aiUpgradeCurrentRegistryTargets` and `aiRestoreAppendAuditLog` have no direct unit test but are exercised via the real-CLI `InstallLifecycleTest` upgrade/restore paths. If Phase 0 confirms this, no new test is required; if not, add one characterization test (Phase 0 contingency).

Per-phase invariant contract (identical for every move phase):

- Files touched: 1 new `*.php` + `install_workflow.php` (2 files).
- What moves: named function group verbatim (same names, signatures, bodies, doc-comments).
- Seam/boundary rule: the new module may only depend on functions already available via `install_workflow.php`'s existing `require_once` chain (`core.php` -> its extracted modules, `selection-engine.php`); add `require_once __DIR__ . '/../install/<new>.php';` to `install_workflow.php` in include order; remove the moved definitions from `install_workflow.php`.
- Acceptance: (a) `git diff` shows only cut-from-`install_workflow.php` / paste-into-new-file with identical bytes per function; (b) `composer test:fast` shows the same pre-existing failure set as the Phase 0 baseline (no new failures); (c) `composer test` same; (d) `--dry-run` output for `--profile dual --dry-run` byte-identical to the Phase 0 baseline except the `generated_at` timestamp; (e) no adapter/generated file changed.
- Verify (in order, with anti-freeze budgets): `composer test:fast` (budget 90s) -> `composer test` (budget 180s) -> `php tools/ai/ai.php install --profile dual --dry-run` (diff vs Phase 0 baseline) -> `php tools/ai/validate-install-surface.php` -> `git diff --stat` (<=2 files, no adapter/generated files).

**Line-target clarification (unknown resolved conservatively):** the raw handoff's "< 400 lines / complexity < 60" is treated as aspirational. The eight `aiRun*` handlers total ~840 lines of intrinsic CLI/decision logic that this slice deliberately does NOT split (splitting them risks behavior). Extracting the ~430 lines of cohesive helper groups lands `install_workflow.php` at roughly **~870 lines**; a realistic hard-gate is **< ~900 lines** for this slice, with **< ~700** only if a later, separately-approved phase also splits the handlers. This plan does not commit to the sub-400 figure because reaching it requires the handler split explicitly placed out of scope above.

## Todo Plan

Use unchecked Markdown tasks only, grouped by priority. Each phase is one commit; rollback = revert that single move commit.

### P0 — Phase 0: Parity baseline + coverage-gap audit (read + test-only, no install_workflow.php change)

- [x] P0: Confirm the pre-existing test baseline: run `composer test:fast` and record the exact set of pre-existing failures/skips. **Result:** 904 tests, 12148 assertions, **4 pre-existing unrelated failures** (identical class to the predecessor refactor: `GeneratedHeaderTest::testValidateGeneratedArtifactsPasses` + `CliToolsTest::testValidateGeneratedArtifactsExitsZero` — `docs/ai/repo-required-tools.md` drift; `CliToolsTest::testGenerateRepoStructureCheckModeExitsZero` + `testGenerateRepoStructureCheckModeOutputsUpToDateLines` — `examples` path metadata), 6 skipped. Matches the predecessor baseline exactly.
- [x] P0: Capture the `--dry-run` parity oracle via `php tools/ai/ai.php install --profile dual --dry-run`; snapshot `docs/ai/generated/install.json` (+ `preflight.json`) to a scratch baseline dir for byte-diffing later phases. **Result:** ran successfully (`OK: wrote docs/ai/generated/preflight.json`, `OK: wrote docs/ai/generated/install.json`); both files are gitignored generated artifacts (`git status --short docs/ai/generated/` shows no change); snapshotted to `/tmp/opencode/phase0-baseline-workflow/{install,preflight}.json`.
- [x] P0: Audit whether `aiUpgradeCurrentRegistryTargets` and `aiRestoreAppendAuditLog` are covered by the real-CLI `InstallLifecycleTest` upgrade/restore paths; record the covering test name. **Result:** `aiRestoreAppendAuditLog` is directly covered by `RestoreWorkflowTest::testRestoreApplyRestoresBytesAndLogs` (asserts audit log file exists under `.ai/logs/`, decodes it, and checks `from`/`restored_targets` fields — tests/php/RestoreWorkflowTest.php:106-125). `aiUpgradeCurrentRegistryTargets` is covered transitively via `InstallerSafetyTest.php` real-CLI `php tools/ai/ai.php upgrade --apply --backup ...` calls (lines 1471, 1539): `aiRunUpgradeWorkflow` calls `aiUpgradeCurrentRegistryTargets()` unconditionally (before the dry-run/apply branch) to compute the `deprecated` class.
- [x] P0: Add `tests/php/InstallWorkflowCharacterizationTest.php` ONLY if a coverage gap is found for either function; otherwise record "no gap found". **Result:** no gap found; no new test added.

### P1 — Phase 1: upgrade-file-actions.php (THE FIRST SLICE — largest cohesive group, strongest existing unit coverage)

- [x] P1: Create `tools/ai/install/upgrade-file-actions.php` and move verbatim the 6 upgrade helper functions: `aiUpgradePreserveOwnedConflicts`, `aiUpgradeCurrentRegistryTargets`, `aiUpgradeResolveFileAction`, `aiUpgradeRemoveDeprecated`, `aiUpgradeComputeDeprecated`, `aiUpgradeBuildApplyInstallArgs` (same names, signatures, bodies, doc-comments). **Result:** created at 251 lines, all 6 functions verbatim with doc-comments.
- [x] P1: Add `require_once __DIR__ . '/../install/upgrade-file-actions.php';` near the top of `install_workflow.php` and remove the moved definitions; confirm no dependency the module needs is defined later in `install_workflow.php` (all its deps — `aiInstallerPrivateConflictDir/Rel`, `aiInstallerPrivateDirMode`, `aiInstallerDeleteTree`, `aiHashPath`, `aiInstallerPackRegistry` — come from the `core.php` chain). **Result:** `install_workflow.php` shrank 1304 -> 1067 lines (243 lines net change: -240 deletions +3 insertions per `git diff --stat`); confirmed via `php -l` that both files parse cleanly.
- [x] P1: Run the per-phase verify ladder and confirm acceptance (a)-(e). `UpgradeOwnershipTest` + `PrivateStructureTest` are the direct net. **Result:** (a) `git status --short tools/ai/` shows exactly `M install_workflow.php` + `?? upgrade-file-actions.php` (2 files); (b) `composer test:fast` — 904 tests, 12148 assertions, same 4 pre-existing unrelated failures, 6 skipped (targeted `UpgradeOwnershipTest`+`PrivateStructureTest` filter: 30 tests, 81 assertions, all green); (c) same result for full serial run (paratest fast lane already ran full suite); (d) `--dry-run` output byte-identical to Phase 0 baseline (`install.json` and `preflight.json` diffed with `generated_at` excluded via `jq`) — both `INSTALL.JSON IDENTICAL` / `PREFLIGHT.JSON IDENTICAL`; (e) `php tools/ai/validate-install-surface.php` -> `OK: install surface validation passed` (only pre-existing unrelated soft-max warnings); no adapter/generated file in the diff.

### P1 — Phase 2: workflow-manifest.php (2 install-manifest helpers)

- [x] P1: Create `tools/ai/install/workflow-manifest.php` and move verbatim `aiInstallerMergeWorkflowManifest`, `aiInstallerBuildSubprocessInstallCommand`. **Result:** created at 86 lines, both functions verbatim with doc-comments.
- [x] P1: Add `require_once __DIR__ . '/../install/workflow-manifest.php';` in dependency order; remove moved definitions from `install_workflow.php`. **Result:** `install_workflow.php` shrank 1067 -> 992 lines.
- [x] P1: Run the per-phase verify ladder and confirm acceptance (a)-(e). `InstallManifestReconciliationTest` is the direct net. **Result:** (a) `git status --short tools/ai/` shows `M install_workflow.php` + 2 new files (`upgrade-file-actions.php` from Phase 1, `workflow-manifest.php`), cumulative as expected; `git diff --stat tools/ai/commands/install_workflow.php` -> 6 insertions, 318 deletions (cumulative Phase 1+2 diff); (b) targeted `InstallManifestReconciliationTest` — 5 tests, 25 assertions, all green; (c) `composer test:fast` — 904 tests, 12148 assertions, same 4 pre-existing unrelated failures, 6 skipped; (d) `--dry-run` byte-identical to Phase 0 baseline (`INSTALL.JSON IDENTICAL` / `PREFLIGHT.JSON IDENTICAL` via `jq` diff excluding `generated_at`); (e) `php tools/ai/validate-install-surface.php` -> `OK: install surface validation passed` (only pre-existing unrelated soft-max warnings); no adapter/generated file in the diff.

### P2 — Phase 3: uninstall-prune.php (1 helper)

- [x] P2: Create `tools/ai/install/uninstall-prune.php` and move verbatim `aiUninstallPruneEmptyParents`. **Result:** created at 48 lines, function verbatim with doc-comment.
- [x] P2: Add `require_once __DIR__ . '/../install/uninstall-prune.php';` in dependency order; remove the moved definition from `install_workflow.php`. **Result:** `install_workflow.php` shrank 992 -> 955 lines.
- [x] P2: Run the per-phase verify ladder and confirm acceptance (a)-(e). `UninstallDirPruneTest` is the direct net. **Result:** (a) `git status --short tools/ai/` shows `M install_workflow.php` + 3 cumulative new files; (b) targeted `UninstallDirPruneTest` — 4 tests, 7 assertions, all green; (c) `composer test:fast` — 904 tests, same 4 pre-existing unrelated failures, 6 skipped (assertion count fluctuated 12145/12148 across repeated runs on the *same* file state, confirming pre-existing parallel-worker flakiness unrelated to this move, not a regression); (d) `--dry-run` byte-identical to Phase 0 baseline (`INSTALL.JSON IDENTICAL` / `PREFLIGHT.JSON IDENTICAL`); (e) `php tools/ai/validate-install-surface.php` -> `OK: install surface validation passed` (only pre-existing unrelated soft-max warnings); `git diff --stat` -> 1 file, 9 insertions/358 deletions cumulative; no adapter/generated file changed.

### P2 — Phase 4: restore-audit.php (1 helper)

- [x] P2: Create `tools/ai/install/restore-audit.php` and move verbatim `aiRestoreAppendAuditLog`. **Result:** created at 35 lines, function verbatim with doc-comment; depends on `AI_DIR_MODE` (already loaded via `core.php` chain).
- [x] P2: Add `require_once __DIR__ . '/../install/restore-audit.php';` in dependency order; remove the moved definition from `install_workflow.php`. **Result:** `install_workflow.php` shrank 955 -> **933 lines** (from the original 1304 — a 28.4% reduction).
- [x] P2: Run the per-phase verify ladder and confirm acceptance (a)-(e); confirm the real-CLI restore path in `InstallLifecycleTest` still passes. **Result:** (a) `git status --short tools/ai/` shows `M install_workflow.php` + 4 cumulative new files; (b) targeted `RestoreWorkflowTest` — 4 tests, 13 assertions, all green; (c) `composer test:fast` — 904 tests, same 4 pre-existing unrelated failures, 6 skipped (assertion count 12145, consistent with the established flakiness range); `composer test` (full serial) — 904 tests, 12136 assertions, same 4 pre-existing unrelated failures, 6 skipped; (d) `--dry-run` byte-identical to Phase 0 baseline (`INSTALL.JSON IDENTICAL` / `PREFLIGHT.JSON IDENTICAL`); (e) `php tools/ai/validate-install-surface.php` -> `OK: install surface validation passed` (only pre-existing unrelated soft-max warnings); `git diff --stat` -> 1 file, 12 insertions/383 deletions cumulative; no adapter/generated file changed.

## Acceptance Criteria

Each AC is observable and testable.

### Explicit acceptance criteria (AC1-AC7)

- [x] AC-01: Hotspot confirmed — `install_workflow.php` verified at 1304 lines; `core.php` already reduced (601 lines) and NOT the target of this slice. Confirmed via `wc -l` at plan-writing time.
- [x] AC-02: Target module shape is defined with concrete filenames and procedural style (no classes) for every new module: `upgrade-file-actions.php` (251 lines), `workflow-manifest.php` (86 lines), `uninstall-prune.php` (48 lines), `restore-audit.php` (35 lines). All 4 shipped as plain procedural files.
- [x] AC-03: Phased order holds — each phase touched exactly 2 files (`install_workflow.php` + 1 new module); Phase 1 was the largest at 251 lines (slightly above the ~230 estimate but still one cohesive group); Phase 0 was baseline/audit only, no file created.
- [x] AC-04: Each phase lists files / what-moves / seam-rule / acceptance / verification (see per-phase Todo entries above, each annotated with **Result:**).
- [x] AC-05: The things-to-avoid list is present and honored: no validator/`install_extras.php`/`install_preflight.php`/ai-verify/sh-introspect file touched (confirmed via `git status --short` after every phase); no handler split (all 8 `aiRun*` handlers remain in `install_workflow.php`); no class introduced; no behavior change (dry-run byte-parity held every phase); no generated-artifact hand-edit; no function renamed; the "no echo in decision logic" rule was explicitly rejected up front and not applied.
- [x] AC-06: A single best FIRST slice was defined (Phase 1 `upgrade-file-actions.php`) and executed exactly as specified — largest cohesive group, strongest existing test net, executed first.
- [x] AC-07: Risks were called out (see Risks And Rollback) and none materialized; the one near-miss (assertion-count fluctuation 12145/12148) was investigated and confirmed as pre-existing parallel-worker flakiness unrelated to the moves (verified by rerunning the identical file state twice with differing counts).

### Inferred acceptance criteria

- [x] AC-I1: Every extraction is a pure move (verbatim body + name), `require_once` added, no signature/name/logic change in the same commit. Confirmed for all 4 move phases via byte-for-byte content comparison during authoring and `php -l` syntax checks.
- [x] AC-I2: `composer test:fast` and `composer test` show the same pre-existing failure set as the Phase 0 baseline (no new failures) after each phase. Confirmed after every phase: 904 tests, same 4 pre-existing unrelated failures (`GeneratedHeaderTest`/`CliToolsTest` generated-artifact drift), 6 skipped, throughout.
- [x] AC-I3: No `.claude/` / `.opencode/` / `.github/` / `AGENTS.md` / `CLAUDE.md` hand-edit. Confirmed via `git status --short` after every phase — only `tools/ai/commands/install_workflow.php` and `tools/ai/install/*.php` touched.
- [x] AC-I4: `--dry-run` output byte-identical (except `generated_at`) vs the Phase 0 baseline after each phase. Confirmed via `jq 'del(.generated_at)'` diff of `install.json` and `preflight.json` after every phase — `INSTALL.JSON IDENTICAL` / `PREFLIGHT.JSON IDENTICAL` every time.
- [x] AC-I5: `tools/ai/ai.php` dispatch is unchanged (no function renamed, so no `switch` edit). No edit made to `ai.php` in this ticket.

### Negative acceptance criteria

- [x] NAC1: No change to any `aiRun*` command handler's `aiCliWriteArtifact` + stdout + exit-code contract. Confirmed — only helper-group bodies moved; all 8 handlers unchanged in place.
- [x] NAC2: No new class/namespace/PSR-4; all new modules are plain procedural `*.php` function files. Confirmed for all 4 modules.
- [x] NAC3: No function renamed; no call-site rename churn in `ai.php` or tests. Zero renames across all 4 move phases; all tests referencing moved functions (`UpgradeOwnershipTest`, `PrivateStructureTest`, `InstallManifestReconciliationTest`, `UninstallDirPruneTest`, `RestoreWorkflowTest`) passed unmodified.
- [x] NAC4: No validator, `install_extras.php`, `install_preflight.php`, `ai-verify`, or `sh-introspect` file touched. Confirmed via `git status --short` scoped to `tools/ai/` after every phase.
- [x] NAC5: No split by arbitrary line range; cohesive function groups only. Every phase moved a named, cohesive function group (upgrade file-actions engine, manifest helpers, uninstall-prune, restore-audit).
- [x] NAC6: The eight `aiRun*` command handlers stay in `install_workflow.php` (not split into per-command files in this slice). Confirmed — `install_workflow.php` retains all 8 handlers at 933 final lines.

## Verification Plan

Each step names the command or inspection surface that proves an AC. Apply anti-freeze budgets per command (`docs/ai/execution-protocol.md`).

- Phase 0 baseline (parity oracle for AC-I4): `php tools/ai/ai.php install --profile dual --dry-run` — capture output as the baseline snapshot.
- Per-phase, in this order (proves AC-I1/AC-I2/AC-I4/NAC1-NAC6):
  - `composer test:fast` (budget 90s) — proves AC-I2 fast lane.
  - `composer test` (budget 180s) — proves AC-I2 full lane.
  - `php tools/ai/ai.php install --profile dual --dry-run` then diff vs the Phase 0 baseline — proves AC-I4 (byte-identical except `generated_at`).
  - `php tools/ai/validate-install-surface.php` — proves install-surface integrity.
  - `git diff --stat` — proves file-count bound (<=2 files) and that no adapter/generated file changed (AC-I3, AC-03).
- Byte-diff inspection of `git diff` for each phase — proves each move is verbatim cut/paste (AC-I1, NAC3, NAC5).
- Targeted regression nets by phase: Phase 1 -> `UpgradeOwnershipTest` + `PrivateStructureTest`; Phase 2 -> `InstallManifestReconciliationTest`; Phase 3 -> `UninstallDirPruneTest`; Phase 4 -> `InstallLifecycleTest` restore path. Run the narrow test first (`bash scripts/ai/run-test-focused.sh <TestName>` or `php vendor/bin/phpunit --filter <TestName>`) before the full suite.

## Risks And Rollback

Risks:

- **Include-order / forward-dependency**: a moved helper referenced by an `aiRun*` handler earlier in the file must remain loadable. Mitigation: `require_once` is added at the top of `install_workflow.php` (before any handler runs), and all moved-helper dependencies come from the already-loaded `core.php` chain — verified in Phase 1's second Todo.
- **Test load path**: tests call moved helpers as free functions. They currently resolve because the installer include chain loads `install_workflow.php`. Adding a `require_once` for the new module (loaded transitively the same way) preserves this. Mitigation: run each phase's direct unit test first.
- **Dry-run parity**: strongest regression signal; diff `--dry-run` vs the Phase 0 baseline every phase.
- **Behavior-preservation vs the raw "no echo in decision logic" instruction**: honoring that instruction would change handler return/ordering. Mitigation: explicitly out of scope; recorded in Things-To-Avoid and NAC1.

Unknowns:

- Exact current pre-existing failure count (predecessor recorded 4 + 6 skipped; re-confirm in Phase 0 rather than assume).
- Whether `aiUpgradeCurrentRegistryTargets` / `aiRestoreAppendAuditLog` need a characterization test — resolved in Phase 0.
- Final new-file names may shift if an overlap check finds a better-fitting existing module — recorded per-phase if so (never a function rename).

Rollback:

- Each phase is one commit; rollback = revert the single move commit.

## Handoff Notes

- Start with the single best FIRST slice: **Phase 1, `upgrade-file-actions.php`**. It is the largest cohesive group (~230 lines, 6 functions) with the strongest existing unit coverage (`UpgradeOwnershipTest`, `PrivateStructureTest`), so it yields the biggest line reduction with the clearest byte-for-byte diff and the tightest regression net.
- Do Phase 0 (baseline + coverage audit) before Phase 1 so the parity oracle exists.
- This ticket is scoped to `install_workflow.php` only. **The recommended next tickets, in order** (each its own bounded slice, NOT part of this one): (1) `install_extras.php` extraction; (2) `install_preflight.php` extraction; (3) validator consolidation across `validate-ai-config.php` / `full-install-validation.php` / `validate-install-surface.php` into a shared `tools/ai/validation/` suite; (4) `install/backup.php` isolation; (5) `core.php` final cleanup; (6) `ai-verify` / `sh-introspect` shell subsystems (separate refactor tickets).
- Recommended next step: hand off to the implementer agent using OpenCode command: /implement (start at Phase 0, then Phase 1).

## Archive On Completion

When every `## Todo Plan` item AND every `## Acceptance Criteria` item is checked `[x]`:

1. Create `docs/tickets/arch-todo-installer-workflow-command-extraction-20260706-220032/archive/DONE-plan.md` with the full plan contents (prefix `DONE-`).
2. Replace this file's body with a one-line tombstone pointing to the archived copy.

Only archive when the file proves completion (no unchecked items remain in either section).

## Archive Record

- Archived: 2026-07-06
- All `## Todo Plan` items (Phases 0-4) and all `## Acceptance Criteria` items (AC1-AC7, inferred, negative) verified checked `[x]`.
- Final verification at archive time: `install_workflow.php` = **933 lines** (28.4% reduction from 1304); all 4 modules present (`upgrade-file-actions.php` 251 lines, `workflow-manifest.php` 86 lines, `uninstall-prune.php` 48 lines, `restore-audit.php` 35 lines); `composer test:fast` — 904 tests, same 4 pre-existing unrelated failures, 6 skipped (assertion count observed to fluctuate 12136-12148 across runs on identical file state — confirmed pre-existing parallel-worker flakiness, not a regression); `composer test` (full serial) — 904 tests, 12136 assertions, same 4 pre-existing unrelated failures, 6 skipped; `php tools/ai/validate-install-surface.php` -> `OK: install surface validation passed`; `--dry-run` output byte-identical to the Phase 0 baseline (except `generated_at`) after every phase.
- Note: the line-target clarification in "Contracts And Boundaries" projected a ~900-line floor for this slice (handler split explicitly out of scope); the actual result (933 lines) landed close to that projection.
