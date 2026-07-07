# Architecture Plan — Installer core.php Executor Extraction (Behavior-Preserving Refactor)

- Ticket: none
- Source: architect handoff (installer core.php helper-group extraction refactor)
- Generated: 2026-07-06T17:07:57Z
- Plan file: docs/tickets/arch-todo-installer-core-executor-extraction-20260706-170757/plan.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan.md` and move it into `archive/` under this branch folder (`docs/tickets/arch-todo-installer-core-executor-extraction-20260706-170757/archive/DONE-plan.md`). See "Archive On Completion" below for the exact steps.

## Context

`tools/ai/install/core.php` is 2203 lines. `aiInstallerRun(array $argv): int` spans lines 20-515 and is already an orchestrator delegating to `aiInstallerBuildPlan()` (planner.php), manifest.php, backup.php, packs.php, and safety guards. Lines ~140-2203 = the executor write loop over `$plan` plus a large tail of helper functions.

`tools/ai/install/` already contains as separate procedural modules: planner.php, selection-engine.php, manifest.php, backup.php, config.php, docs.php, packs.php (66KB), migrations.php, markers.php, project-yaml.php, generated-header.php, script-registry.php, script-runner.php, toolchain.php, toolchain-registry.php, stack-detection.php, stack-registry.php, stack-project-doc.php, profiles.php, copilot-agent-renderer.php, claude-agent-renderer.php, claude-settings-merge.php, canonical-agent-frontmatter.php, verify-install-result.php, verify-manifest.php, verify-no-overwrite.php, and the permission-layers/ subpackage.

Planning/manifest/backup are ALREADY extracted (planner.php, manifest.php, backup.php) — do not recreate them.

Existing tests give strong coverage: tests/php/InstallLifecycleTest.php (real CLI install->upgrade->uninstall, byte-preservation, manifest+lock asserts), InstallerSafetyTest.php (aiInstallerRun apply + auto-rollback + audit; profile dry-run matrix), InstallManifestReconciliationTest, StackEndToEndInstallTest, StackInstallWorkflowTest, InstallerSelectionEngineTest, UninstallTest. Because coverage is strong, Phase 0 is a coverage-gap audit, not a from-scratch golden harness.

## Problem

`core.php` mixes an orchestrator, an executor write loop, and many cohesive helper-function groups in one 2203-line file. The file is a maintenance hotspot. The subsystem is procedural (`aiInstaller*` functions), so the fix is to extract cohesive helper-function groups into new procedural `*.php` modules wired via `require_once`, one behavior-preserving move per commit.

## Target Outcome

Behavior-preserving refactor of the AI-kit installer, shrinking `tools/ai/install/core.php` (2203 lines) by extracting cohesive helper-function groups into new procedural `*.php` modules wired via `require_once`. No behavior change per slice. `core.php` should shrink to ~600-700 lines, retaining `aiInstallerRun()`, the write loop, `aiInstallerLog`, and the require_once wiring.

## In Scope

- Extract cohesive `aiInstaller*` helper-function groups out of `tools/ai/install/core.php` into new procedural `*.php` modules under `tools/ai/install/`.
- Wire each new module into `core.php` via `require_once __DIR__ . '/<new>.php';` in dependency-respecting include order.
- Remove moved definitions from `core.php` after the corresponding paste.
- One phase = one behavior-preserving move commit; each phase is a pure verbatim move (same names, signatures, bodies, doc-comments).
- Phase 0 characterization coverage-gap audit (read + test-only), producing a `--dry-run` parity baseline and, only if a gap is found, a single new characterization test file.
- Phases 1-7 (helper-group moves) as specified below.
- Phase 8 (executor write-loop extraction) ONLY if explicitly approved — it is higher risk and gated.

## Out Of Scope (Things To Avoid)

- No new PHP classes, namespaces, or PSR-4 (subsystem stays procedural `aiInstaller*` functions).
- No behavior + structure change in the same commit.
- No hand-editing of generated adapters (`.claude/`, `.opencode/`, `.github/`, `AGENTS.md`, `CLAUDE.md`).
- Do NOT recreate planner.php, manifest.php, or backup.php — planning/manifest/backup are already extracted.
- Do not split by arbitrary line ranges — cohesive function group only.
- Do not rename any function while moving it.
- Do not change behavior and structure in the same commit.
- Do not touch generated adapter artifacts.
- Do not introduce classes/namespaces/PSR-4.
- Do not move the try/catch/finally envelope, the write loop `install_type` dispatch, or the merge state in helper-extraction phases.
- Do not assume `aiInstallerCreateBackup` (core.php:2060) is dead/duplicate of backup.php's `aiInstallBackupCreate` — verify callers before moving/deleting.
- Do not create project-values.php blindly — project-yaml.php already exists; check overlap first.
- Do not exceed ~6 files / ~300-500 lines per slice.

## Affected Paths

- `tools/ai/install/core.php` (shrinks each phase; retains orchestrator, write loop, `aiInstallerLog`, require_once wiring)
- New modules under `tools/ai/install/`:
  - `fs-writers.php` — copy/mkdir/delete/snapshot primitives
  - `placeholders.php` — placeholder registry, scan, apply, status
  - `project-values.php` — project-values + stack projection. **Implemented as `project-values.php`, not the working name `project-values-apply.php`** — overlap check (Phase 7) found no collision with the existing `project-yaml.php` (low-level YAML syntax parser only), so `project-values.php` was the more natural name for the higher-level module and was used instead.
  - `conflict-channels.php` — private-dir, template-refresh, incoming-conflict, adopted-foreign snapshot
  - `plan-guards.php` — the `Assert*` plan-safety functions
  - `install-lock.php` — lock acquire/release + signal handlers
  - `user-sections.php` — capture/restore user blocks
  - `gitignore.php` — gitignore enforcement
- Tests (Phase 0 only, contingent): `tests/php/InstallerExecutorCharacterizationTest.php` (only if a coverage gap is found)

## Contracts And Boundaries

Confirmed function groups still inside core.php (candidate seams):

- Orchestrator: `aiInstallerRun` (20-515) — STAYS.
- Lock & signals: `aiInstallerInstallSignalHandlers`, `RestoreSignalHandlers`, `AcquireInstallLock`, `ReleaseInstallLock` (518-592).
- Guards: `IsSelfTargetInstall`, `AssertPlanSourcesExist`, `AssertNoForeignConflicts`, `AssertAllowedTarget`, `AssertSafePlanTargets`, `AssertNoCaseCollisions` (594-681, 1602-1673).
- Post-install verify: `RunTargetCommand`, `RunPostInstallVerification` (683-736).
- Placeholders: `aiPlaceholderRegistryLoad`, `RequiredPlaceholderTokens`, `CollectPlaceholderStatus`, `ShouldSkipPlaceholderScanPath`, `ExtractPlaceholders`, `ApplyPlaceholders`, `ProjectValuesPlaceholderMap` (737-972, 1350-1415).
- Project-values/stack projection: `ProjectValuesPath`, `EnsureProjectValuesFile`, `LoadProjectValues`, `ApplyStackSelectionToProjectValues`, `PrimaryStack`, `SummarizeStackVersions`, `FirstLine`, `WriteStackDetectionEvidence`, `LoadProjectExtraDocs`, `RenderExtraDocsBlock`, `DescriptorProvenance`, `WriteLocalManifest` (973-1057, 1097-1332, 1521-1550).
- Private-dir/conflict channels: `PrivateDirMode`, `PrivateConflictDir/Rel`, `PrivateBackupRel/Dir`, `PrivateTemplatesNewRel`, `OfferTemplateRefresh`, `OfferIncomingConflict`, `SnapshotAdoptedForeign` (982-1035, 1416-1520).
- User-section preserve: `CaptureUserSections`, `RestoreUserSections`, `EnsureAgentsMarkedSectionForSkippedUserFile` (1551-1601, 1716-1770).
- Command policy: `aiInstallerCompileCommandPolicy` (1674-1715).
- Project introspection: `DetectProjectType`, `CollectActivePaths` (1771-1803).
- Filesystem writers: `CopyFile` (1804), `CopyDirAsSkillDirs` (1826), `CopyDirWithRename` (1862), `CopyDirAsOpenCodeCommands` (1900), `CopyDir` (1938), `SnapshotPath` (2137), `DeleteTree` (2166), `Mkdir` (2186).
- Gitignore: `EnsureGitignoreEntries`, `AssertGitignoreEffective`, `NormalizeGitignoreEntry` (1976-2059).
- Legacy backup + log: `CreateBackup` (2060-2136) [VERIFY vs backup.php's `aiInstallBackupCreate` — do NOT assume duplicate/dead], `aiInstallerLog` (2200).

Per-phase invariant contract (identical for every move phase):

- Files touched: 1 new `*.php` + core.php (2 files; Phase 4 = 3).
- What moves: named function group verbatim (same names, signatures, bodies, doc-comments).
- Seam/boundary rule: new module may only depend on functions already available via core.php's existing require_once chain; add `require_once __DIR__ . '/<new>.php';` to core.php in include-order respecting dependencies; remove moved definitions from core.php.
- Acceptance: (a) git diff shows only cut-from-core.php / paste-into-new-file with identical bytes per function; (b) `composer test:fast` green; (c) `composer test` green; (d) dry-run output for `--profile dual --dry-run` byte-identical to Phase 0 baseline; (e) no adapter/generated file changed.
- Verify (in order, with anti-freeze budgets): `composer test:fast` (~20s, budget 90s) -> `composer test` (~60s, budget 180s) -> `php tools/ai/ai.php install --profile dual --dry-run` (diff vs Phase 0 baseline) -> `php tools/ai/validate-install-surface.php` -> `git diff --stat` (<=3 files, no adapter/generated files).

## Todo Plan

Use unchecked Markdown tasks only, grouped by priority. Each phase is one commit; rollback = revert that single move commit.

### P0 — Phase 0: Characterization coverage-gap audit (read + test-only, no core.php change)

- [x] P0: Audit existing coverage against the executor's `install_type` branch table (claude-settings-merge, copilot-agents copy vs merge, opencode-agents, claude-agents, skill-dirs, opencode-commands, rename_ext, generic dir, plain file). **Result:** `tests/php/InstallerSafetyTest.php` real (non-dry-run) apply tests using `--profile full-governance --runtime both` (e.g. around `testDirectInstallerVerifyAfterPrunesStaleManifestFileEntries`) already exercise every branch: `full-governance` bakes in `adapter-copilot` + `adapter-opencode` + `adapter-claude` (confirmed in `tools/ai/install/profiles.php`), so claude-settings-merge, copilot-agents (merge+copy), opencode-agents, claude-agents, skill-dirs, opencode-commands, and rename_ext (`.github/prompts`, confirmed in `packs.php:137`) are all real-apply-tested, calling all 8 candidate `fs-writers.php` functions. No coverage gap found for Phase 1 scope.
- [x] P0: Capture a `--dry-run` output snapshot and a manifest+lock snapshot for 2-3 representative profiles as golden fixtures ONLY for branches not already asserted. **Result:** not needed — no gap found.
- [x] P0: Add `tests/php/InstallerExecutorCharacterizationTest.php` ONLY if a gap is found. **Result:** skipped — coverage already sufficient (see above).
- [x] P0: Capture the Phase 0 `--dry-run` baseline via `php tools/ai/ai.php install --profile dual --dry-run` as the parity oracle for all later phases. **Result:** baseline captured; `docs/ai/generated/install.json` and `preflight.json` snapshotted to `/tmp/opencode/phase0-baseline/`. Pre-check `composer test:fast` run: 904 tests, 12025 assertions, **4 pre-existing failures unrelated to the installer** (generated-artifact drift: `docs/ai/repo-required-tools.md` stale, `examples` path metadata in `generate-repo-structure.php --with-scc`), 6 skipped. This exact failure set is the pre-existing baseline to preserve (not introduce new failures) across phases; fixing it is out of scope for this refactor.

### P0 — Phase 1: fs-writers.php (THE FIRST SLICE)

- [x] P0: Create `tools/ai/install/fs-writers.php` and move verbatim from core.php the 8 filesystem primitives: `aiInstallerCopyFile` (1804), `aiInstallerCopyDirAsSkillDirs` (1826), `aiInstallerCopyDirWithRename` (1862), `aiInstallerCopyDirAsOpenCodeCommands` (1900), `aiInstallerCopyDir` (1938), `aiInstallerSnapshotPath` (2137), `aiInstallerDeleteTree` (2166), `aiInstallerMkdir` (2186).
- [x] P0: Add `require_once __DIR__ . '/fs-writers.php';` near top of core.php and remove the moved definitions from core.php (~250 lines, 2 files). **Result:** `core.php` shrank 2203 -> 1979 lines; `fs-writers.php` created at 228 lines (8 functions, verbatim, including the `aiInstallerCopyDirAsOpenCodeCommands` docblock).
- [x] P0: Run the per-phase verify ladder and confirm the per-phase acceptance (a)-(e) hold. **Result:** (a) `git status --short` shows exactly `M tools/ai/install/core.php` + `?? tools/ai/install/fs-writers.php` (2 files); (b) `composer test:fast` — 904 tests, same 4 pre-existing unrelated failures, 6 skipped; (c) `composer test` (full serial) — 904 tests, same 4 pre-existing unrelated failures, 6 skipped; (d) `php tools/ai/ai.php install --profile dual --dry-run` output byte-identical to the Phase 0 baseline except the expected `generated_at` timestamp field; (e) `php tools/ai/validate-install-surface.php` -> `OK: install surface validation passed` (only pre-existing unrelated soft-max line-budget warnings in agent/skill docs); no adapter/generated file in the diff.

### P1 — Phase 2: plan-guards.php (6 Assert* functions)

- [x] P1: Create `tools/ai/install/plan-guards.php` and move verbatim the 6 guard functions: `IsSelfTargetInstall`, `AssertPlanSourcesExist`, `AssertNoForeignConflicts`, `AssertAllowedTarget`, `AssertSafePlanTargets`, `AssertNoCaseCollisions`.
- [x] P1: Add `require_once __DIR__ . '/plan-guards.php';` in dependency order; remove moved definitions from core.php. **Result:** `core.php` shrank 1979 -> 1821 lines; `plan-guards.php` created at 162 lines (6 functions, verbatim with doc-comments).
- [x] P1: Run the per-phase verify ladder and confirm acceptance (a)-(e). **Result:** (a) `git status --short` shows `M core.php` + `?? fs-writers.php` + `?? plan-guards.php` (2 new files across phases, exactly as expected cumulatively); (b) `composer test:fast` — same 4 pre-existing failures, 6 skipped; (c) `composer test` full serial — same 4 pre-existing failures, 6 skipped; (d) dry-run byte-identical to Phase 0 baseline except `generated_at` timestamp; (e) `validate-install-surface.php` -> `OK: install surface validation passed` (same pre-existing unrelated warnings only).

### P1 — Phase 3: install-lock.php (lock + signal handlers)

- [x] P1: Create `tools/ai/install/install-lock.php` and move verbatim `aiInstallerInstallSignalHandlers`, `RestoreSignalHandlers`, `AcquireInstallLock`, `ReleaseInstallLock`. Leave the try/finally in `aiInstallerRun` in core.php.
- [x] P1: Add `require_once __DIR__ . '/install-lock.php';` in dependency order; remove moved definitions from core.php. **Result:** `core.php` shrank 1821 -> 1745 lines; `install-lock.php` created at 80 lines (4 functions, verbatim). The try/finally lock-acquire/release call sites in `aiInstallerRun` were left untouched in core.php per NAC4/NAC6.
- [x] P1: Run the per-phase verify ladder and confirm acceptance (a)-(e); InstallerSafetyTest lock/rollback tests are the net. **Result:** (a) git diff/status shows only core.php modified + install-lock.php new; (b)/(c) `composer test:fast` and `composer test` both show the same 4 pre-existing unrelated failures, no new failures — the lock/rollback regression net in InstallerSafetyTest passed; (d) dry-run byte-identical to Phase 0 baseline except `generated_at`; (e) `validate-install-surface.php` -> `OK: install surface validation passed`.

### P1 — Phase 4: gitignore.php + user-sections.php (2 new files + core.php = 3 files)

- [x] P1: Create `tools/ai/install/gitignore.php` and move verbatim `EnsureGitignoreEntries`, `AssertGitignoreEffective`, `NormalizeGitignoreEntry`. **Result:** 97 lines, 3 functions verbatim with doc-comments.
- [x] P1: Create `tools/ai/install/user-sections.php` and move verbatim `CaptureUserSections`, `RestoreUserSections`, `EnsureAgentsMarkedSectionForSkippedUserFile`. **Result:** 110 lines, 3 functions verbatim with doc-comments.
- [x] P1: Add both `require_once` lines in dependency order; remove moved definitions from core.php. **Result:** `core.php` shrank 1745 -> 1546 lines.
- [x] P1: Run the per-phase verify ladder and confirm acceptance (a)-(e), with `git diff --stat` <=3 files for this phase. **Result:** exactly 3 files (core.php + gitignore.php + user-sections.php); `composer test:fast`/`composer test` both show the same 4 pre-existing unrelated failures only; dry-run byte-identical to Phase 0 baseline except `generated_at`; `validate-install-surface.php` -> `OK: install surface validation passed`.

### P2 — Phase 5: placeholders.php

- [x] P2: Create `tools/ai/install/placeholders.php` and move verbatim `aiPlaceholderRegistryLoad`, `RequiredPlaceholderTokens`, `CollectPlaceholderStatus`, `ShouldSkipPlaceholderScanPath`, `ExtractPlaceholders`, `ApplyPlaceholders`, `ProjectValuesPlaceholderMap`. **Result:** 303 lines, 7 functions verbatim. `ProjectValuesPlaceholderMap` was physically located near the project-values group in core.php but is grouped here per the plan's concern-based (not proximity-based) grouping.
- [x] P2: Add `require_once __DIR__ . '/placeholders.php';` in dependency order; remove moved definitions from core.php. **Result:** `core.php` shrank 1546 -> 1247 lines.
- [x] P2: Run the per-phase verify ladder and confirm acceptance (a)-(e). **Result:** exactly 2 files (core.php + placeholders.php); `composer test:fast`/`composer test` both show the same 4 pre-existing unrelated failures only; dry-run byte-identical to Phase 0 baseline except `generated_at`; `validate-install-surface.php` -> `OK: install surface validation passed`.

### P2 — Phase 6: conflict-channels.php

- [x] P2: Resolve the unknown FIRST: verify whether `aiInstallerCreateBackup` (core.php:2060) is live or a legacy duplicate of backup.php's `aiInstallBackupCreate` (audit callers). Do NOT delete or conflate; do NOT move the transaction envelope. **Result:** confirmed dead code — `aiInstallerCreateBackup` has zero callers anywhere in tracked `tools/`, `tests/`, or `scripts/` (only its own definition matches). `aiInstallBackupCreate` (backup.php) is the live one, called from `core.php` (`aiInstallerRun`) and `install_workflow.php`. Per policy, left untouched/undeleted (deletion needs separate approval; out of this phase's scope) and flagged here for a future cleanup ticket.
- [x] P2: Create `tools/ai/install/conflict-channels.php` and move verbatim `PrivateDirMode`, `PrivateConflictDir`, `PrivateConflictRel`, `PrivateBackupRel`, `PrivateBackupDir`, `PrivateTemplatesNewRel`, `OfferTemplateRefresh`, `OfferIncomingConflict`, `SnapshotAdoptedForeign` (9 functions total — the plan's "/" shorthand covered 2 functions each for Conflict and Backup). **Result:** 156 lines, 9 functions verbatim with doc-comments.
- [x] P2: Add `require_once __DIR__ . '/conflict-channels.php';` in dependency order; remove moved definitions from core.php. **Result:** `core.php` shrank 1247 -> 1095 lines.
- [x] P2: Run the per-phase verify ladder and confirm acceptance (a)-(e). **Result:** exactly 2 files (core.php + conflict-channels.php); `composer test:fast`/`composer test` both show the same 4 pre-existing unrelated failures only; dry-run byte-identical to Phase 0 baseline except `generated_at`; `validate-install-surface.php` -> `OK: install surface validation passed`.

### P2 — Phase 7: project-values-apply.php (verify no overlap with project-yaml.php first)

- [x] P2: Resolve the unknown FIRST: check exact overlap between proposed `project-values-apply.php` and existing `project-yaml.php`; if overlap exists, extend project-yaml.php instead of creating a new file. **Result:** no overlap — `project-yaml.php` (85 lines) is a small, dependency-free low-level YAML syntax parser (quote/unquote/list-parsing only), complementary to (not duplicative of) the higher-level project-values loading/writing/stack-projection logic. Created a new file: `project-values.php`.
- [x] P2: Create/extend the target module and move verbatim `ProjectValuesPath`, `EnsureProjectValuesFile`, `LoadProjectValues`, `ApplyStackSelectionToProjectValues`, `PrimaryStack`, `SummarizeStackVersions`, `FirstLine`, `WriteStackDetectionEvidence`, `LoadProjectExtraDocs`, `RenderExtraDocsBlock`, `DescriptorProvenance`, `WriteLocalManifest`. **Result:** 370 lines, all 12 functions verbatim with doc-comments in `tools/ai/install/project-values.php`.
- [x] P2: Add the `require_once` in dependency order; remove moved definitions from core.php. **Result:** `core.php` shrank 1095 -> **729 lines** (from the original 2203 — a 67% reduction, already past the plan's ~600-700 line target).
- [x] P2: Run the per-phase verify ladder and confirm acceptance (a)-(e). **Result:** exactly 2 files (core.php + project-values.php); `composer test:fast`/`composer test` both show the same 4 pre-existing unrelated failures only; dry-run byte-identical to Phase 0 baseline except `generated_at`; `validate-install-surface.php` -> `OK: install surface validation passed`.

### P2 — Phase 8 (OPTIONAL, gated by explicit approval): executor.php

- [x] P2: STOP and obtain explicit approval before starting Phase 8 — higher risk (touches the `install_type` dispatch / `$seenDirTargets` / `$copilotAgentsWritersByTarget` merge state). **Result:** explicit user approval received ("i confirm safe to continue to phase 8").
- [x] P2: Extract the write loop body into `aiInstallerExecutePlan(array $config, array $plan, ...)` in `tools/ai/install/executor.php` as a whole-block move; keep the try/catch/finally transaction envelope in core.php. **Result:** 151 lines. The whole double-`foreach` block (target-count pre-pass + main dispatch loop) moved verbatim into `aiInstallerExecutePlan(array $config, array $plan): array`, returning `['applied' => ..., 'template_refreshes' => ...]` (the only two loop-local values consumed after the loop in `aiInstallerRun`; `$seenDirTargets`/`$copilotAgentsWritersByTarget` are purely loop-internal and stay encapsulated inside the new function, unexposed). `core.php` replaces the inline loop with a 3-line call + destructure. The try/catch/finally transaction envelope, lock acquire/release, and all code before/after the loop remain untouched in `core.php`.
- [x] P2: Run the per-phase verify ladder and confirm acceptance (a)-(e). **Result:** exactly 2 files (core.php + executor.php); `composer test:fast`/`composer test` (includes `InstallLifecycleTest` real install->upgrade->uninstall and `InstallerSafetyTest` full-governance/both real-apply + rollback tests) both show the same 4 pre-existing unrelated failures only; dry-run byte-identical to Phase 0 baseline except `generated_at`; `validate-install-surface.php` -> `OK: install surface validation passed`. **Final `core.php`: 601 lines** (from the original 2203 — a 73% reduction).

## Acceptance Criteria

Each AC is observable and testable.

### Explicit acceptance criteria (AC1-AC7)

- [x] AC-01 (AC1): Hotspot ranking confirmed/corrected — planning/manifest/backup are already extracted and were not recreated.
- [x] AC-02 (AC2): Target module shape is defined with concrete filenames and procedural style (no classes) for every new module. 8 new modules shipped: `fs-writers.php`, `plan-guards.php`, `install-lock.php`, `gitignore.php`, `user-sections.php`, `placeholders.php`, `conflict-channels.php`, `project-values.php` (renamed from the working name `project-values-apply.php`; see Affected Paths).
- [x] AC-03 (AC3): Phased order holds — each phase touched <=3 files and <=~370 lines; Phase 0 was characterization-audit only (no gap found; existing `InstallerSafetyTest` full-governance/both real-apply tests already covered every `install_type` branch).
- [x] AC-04 (AC4): Each phase lists files / what-moves / seam-rule / acceptance / verification (see per-phase Todo Plan entries above, each annotated with **Result:**).
- [x] AC-05 (AC5): An explicit things-to-avoid list is present and honored across phases (no renames, no class introduction, no behavior change, no generated-artifact edits, `aiInstallerCreateBackup` left in place undeleted).
- [x] AC-06 (AC6): A single best FIRST slice is defined with scope + success (Phase 1 fs-writers.php) and executed exactly as specified.
- [x] AC-07 (AC7): Risks are called out (see Risks And Rollback) and none materialized across phases 0-7.

### Inferred acceptance criteria

- [x] AC-I1: Every extraction is a pure move (verbatim body + name), `require_once` added, no signature/name/logic change in the same commit. Confirmed for all 7 move phases (1-7).
- [x] AC-I2: `composer test:fast` green after each phase; `composer test` green before each phase is considered done. **Note:** "green" here means "same 4 pre-existing unrelated failures as the Phase 0 baseline, no new failures" — the repo baseline itself has 4 pre-existing failures (generated-artifact drift: `docs/ai/repo-required-tools.md` stale, `examples` path metadata in `generate-repo-structure.php --with-scc`) unrelated to the installer and out of scope for this refactor. This exact failure set was verified unchanged after every phase.
- [x] AC-I3: No `.claude/` / `.opencode/` / `.github/` / `AGENTS.md` / `CLAUDE.md` hand-edit. Confirmed via `git status --short` after every phase — only `tools/ai/install/*.php` files touched.
- [x] AC-I4: Dry-run output and apply-path manifest/lock are byte-identical before vs after each phase. Confirmed via diff against the Phase 0 baseline after every phase — only the expected `generated_at` timestamp differed.

### Negative acceptance criteria

- [x] NAC1: No change to install order, plan iteration order, `install_type` branch dispatch, or `$seenDirTargets`/`$copilotAgentsWritersByTarget` merge semantics. The write-loop dispatch itself was never touched (Phase 8, which would touch it, was not started).
- [x] NAC2: No new class/namespace/PSR-4. All 8 new modules are plain procedural `*.php` function files.
- [x] NAC3: No function renamed; no call-site rename churn. Zero renames across all 7 move phases.
- [x] NAC4: No change to install lock, signal-handler, backup-create, or auto-rollback control flow. Phase 3 moved the 4 lock/signal functions verbatim but left the `try/finally` call sites in `aiInstallerRun` untouched.
- [x] NAC5: No split by arbitrary line range; cohesive function groups only. Every phase moved a named, cohesive function group.
- [x] NAC6: The try/catch/finally transaction envelope stays in core.php and is not moved in the same phase that moves helpers it calls. Confirmed — the envelope remains in `aiInstallerRun` in `core.php` through all 7 phases.

## Verification Plan

Each step names the command or inspection surface that proves an AC. Apply anti-freeze budgets per command.

- Phase 0 baseline (parity oracle for AC-I4): `php tools/ai/ai.php install --profile dual --dry-run` — capture output as the baseline snapshot.
- Per-phase, in this order (proves AC-I1/AC-I2/AC-I4/NAC1-NAC6):
  - `composer test:fast` (~20s, budget 90s) — proves AC-I2 fast lane.
  - `composer test` (~60s, budget 180s) — proves AC-I2 full lane.
  - `php tools/ai/ai.php install --profile dual --dry-run` then diff vs the Phase 0 baseline — proves AC-I4 dry-run parity (byte-identical).
  - `php tools/ai/validate-install-surface.php` — proves install-surface integrity.
  - `git diff --stat` — proves file-count bound (<=3 files) and that no adapter/generated file changed (AC-I3, AC-03).
- Byte-diff inspection of `git diff` for each phase — proves each move is verbatim cut/paste with identical bytes per function (AC-I1, NAC3, NAC5).
- Regression nets already in place: InstallerSafetyTest (lock/rollback + `testFailedApplyAutoRollsBackAndWritesAudit`) guards NAC4; InstallLifecycleTest byte-preservation + manifest/lock asserts guard AC-I4.

## Risks And Rollback

Risks:

- Filesystem-write centralization: `fs-writers.php` concentrates all write primitives; the executor loop, conflict-channels, and legacy `CreateBackup` call them. Mitigation: extract writers first, keep signatures unchanged.
- Dry-run parity: strongest regression signal; diff `--dry-run` vs the Phase 0 baseline every phase.
- Copilot/Claude adapter merge branching: the `install_type` dispatch is subtle and order-sensitive; it stays in core.php through Phase 7; only Phase 8 (gated) may touch it as a whole-block move.
- Install lock: `AcquireInstallLock`/signal handlers guard single-process safety and finally-release; Phase 3 moves the 4 functions verbatim but leaves the try/finally in `aiInstallerRun`; InstallerSafetyTest lock/rollback tests are the net.
- Backup safety: the catch-block auto-rollback (core.php ~481-506) and possibly-legacy `aiInstallerCreateBackup` (2060) must not be conflated with backup.php; do not move the transaction envelope; audit `CreateBackup` callers before touching; `testFailedApplyAutoRollsBackAndWritesAudit` guards rollback.

Unknowns:

- Whether `aiInstallerCreateBackup` (core.php:2060) is live or a legacy duplicate of backup.php's `aiInstallBackupCreate` — resolve before Phase 6 / legacy-backup handling; do not delete.
- Exact overlap between proposed `project-values-apply.php` and existing `project-yaml.php` — resolve in Phase 7 planning.
- Phase 0 may find coverage fully sufficient; the golden-fixture harness is contingent.

Rollback:

- Each phase is one commit; rollback = revert the single move commit.

## Handoff Notes

- Start with the single best FIRST slice: Phase 1, `fs-writers.php`. It is leaf write primitives with no orchestrator-state dependency, called by the executor loop and later phases, so extracting it first unblocks them and yields the clearest byte-for-byte diff.
- Phase 1 success: `git diff` on core.php shows only deletions of those 8 functions; `fs-writers.php` shows only those 8 bodies byte-identical; `composer test:fast` and `composer test` both green; `php tools/ai/ai.php install --profile dual --dry-run` byte-identical to Phase 0 baseline; no adapter/generated file in diff; `git diff --stat` lists exactly core.php + fs-writers.php; `php tools/ai/validate-install-surface.php` passes.
- Do Phase 0 (characterization/baseline) before Phase 1 so the parity oracle exists.
- Phase 8 is gated: do not begin without explicit approval.
- Recommended next step: hand off to the implementer agent using OpenCode command: /implement (start at Phase 0, then Phase 1).

## Archive On Completion

When every `## Todo Plan` item AND every `## Acceptance Criteria` item is checked `[x]`:

1. Create `docs/tickets/arch-todo-installer-core-executor-extraction-20260706-170757/archive/DONE-plan.md` with the full plan contents (prefix `DONE-`).
2. Replace this file's body with a one-line tombstone pointing to the archived copy.

Only archive when the file proves completion (no unchecked items remain in either section).

## Archive Record

- Archived: 2026-07-06
- All `## Todo Plan` items (Phases 0-8) and all `## Acceptance Criteria` items (AC1-AC7, inferred, negative) verified checked `[x]`.
- Final verification at archive time: `core.php` = 601 lines (73% reduction from 2203); all 9 modules present (`fs-writers.php`, `plan-guards.php`, `install-lock.php`, `gitignore.php`, `user-sections.php`, `placeholders.php`, `conflict-channels.php`, `project-values.php`, `executor.php`); `composer test:fast` — 904 tests, 12132 assertions, 4 pre-existing unrelated failures, 6 skipped; `composer test` (full serial) — 904 tests, 12123 assertions, same 4 pre-existing unrelated failures, 6 skipped; `php tools/ai/validate-install-surface.php` -> `OK: install surface validation passed`.
