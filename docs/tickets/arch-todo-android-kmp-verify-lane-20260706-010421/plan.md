# Arch Todo: Android / KMP (Kotlin + Gradle) Safe Verification Lane (Non-Autowired)

- Ticket slug: `arch-todo-android-kmp-verify-lane-20260706-010421`
- Status: `P0 COMPLETE (standalone modules, green) — P1 WIRING NOW APPROVED but BLOCKED on concurrent session`
- Author: implementer agent (acting as architect for review + plan, then implementing P0)

> **2026-07-06 update — non-wire freeze LIFTED by user.** The user explicitly approved
> "lift freeze: wire Kotlin fully" so that selecting Kotlin as a project language installs
> AND activates the lane. The original AC-05 "do NOT wire it anywhere" constraint (§5) is
> therefore SUPERSEDED for the wiring work; the P0 modules themselves are unchanged and still
> green (27/27 bats, shellcheck clean). See the new "P1 Wiring (approved)" section below.
>
> **BLOCKED (not done):** the wiring edits target files the CONCURRENT session is actively
> writing — `scripts/ai/ai-verify.sh` (modified, uncommitted), `internal/ai-verify/53-language-dispatch.sh`
> (modified ~6 min before this note), `51-language-files.sh`, `50-tool-policy.sh`, and
> `tools/ai/install/script-registry.php` (modified, uncommitted). Editing them now would
> collide with in-flight uncommitted work (AGENTS.md: do not clobber unfamiliar pre-existing
> changes). Adding the `kotlin.json` stack descriptor additionally cascades into the
> approval-gated generated artifact `docs/ai/stack-registry.json` and the `kotlin` language
> overlay in `permission-layers/language-overlays.php`. Wiring must wait until the concurrent
> session's `ai-verify` + installer changes are committed, then run as a single coordinated
> slice (see "P1 Wiring (approved)").
- Risk: `medium` (adds new shipped shell-module surface + tests; no autowiring, no
  runtime/data change, no package installs, no PHP/JS file edits)
- Verification command of record: `composer test`
- Sibling ticket: `docs/tickets/arch-todo-safe-language-verify-scripts-20260706-003959`
  (the concurrent per-language PHP/JS/TS/Vue/HTML verification effort this lane extends)

---

## 1) Context

A separate/concurrent session is building a **safe per-language verification** architecture
under `scripts/ai/internal/ai-verify/` (sibling ticket above). It ships, so far standalone
and NOT-yet-wired, these modules: `50-tool-policy.sh` (`can_run_tool` /
`is_standalone_safe_tool` / `has_composer_bin`), `51-language-files.sh` (`language_pathspecs`
/ `scoped_language_files`), `54-reporting.sh` (`verify_report_dir` /
`write_verify_report_file`), and `36-plan-status.sh`. The design is: one shared engine
(`ai-verify.sh` + numbered internal modules) fanned out per language, every tool gated on
"already installed", reports under `${AI_LOG_DIR}/verify/`, events via `log_json`.

This ticket adds a **sixth lane: Kotlin / Android / KMP** for the AdvancedGym target repo
(`:app` + `:shared` active; `composeApp` inactive), delivered as an `ai-verify` proposal.

### 1a) Constraints from the requesting user (hard)

1. **Do NOT touch PHP or JS files.** The concurrent session owns `50-tool-policy.sh`,
   `51-language-files.sh`, `90-run.sh`, and every PHP-lane file. This ticket must NOT edit
   them (edit would also collide with the concurrent writer's working tree).
2. **Do NOT wire it anywhere.** No `--language kotlin` flag in `ai-verify.sh`, no
   `source` line added to `ai-verify.sh`, no registry/pack entry, no agent permission, no
   capability/command/skill/hook reference. Human-invocable / test-invocable only.

### 1b) Review of the external Android/KMP proposal — adopt / reconcile / reject

The proposal (a graded tool list + a proposed `scripts/ai/internal/ai-verify/{10..90}`
tree with its own `ai_verify_run`, `run_gradle_required`, `run_gradle_if_exists`,
`log_warn`, `$failures`) is technically sound but architecturally duplicative. Decisions:

**Adopt as-is:**

- The **P0 default gate set**: `:app:assembleDebug`, `:shared:check`, `:app:lintDebug`,
  `:shared:compileKotlinMetadata`, `:shared:compileDebugKotlinAndroid`.
- **Three net-new repo-reality guards** (no existing equivalent): active-module guard,
  inactive-`composeApp` drift guard, version-catalog direct-version guard.
- The **Gradle task-existence guard** before running a task (verify scripts drift).
- **Mode split**: default/fast local gate vs `VERIFY_FULL` (connectedAndroidTest, allTests,
  security) vs `VERIFY_IOS` (iOS compile lanes) — iOS never a Linux/Windows hard default.
- **Detekt / Ktlint / Konsist / dependency-verification are project-local, deferred (P1/P2).**
  Not run from PATH; not part of P0.

**Reconcile (repo-fit corrections — these are the flags):**

- **FLAG-A — No parallel engine.** The proposal's own `ai_verify_run` + `run_gradle_required`
  + `run_gradle_if_exists` + `log_warn` + `$failures` is ~85-95% a re-implementation of
  `40-step-runner.sh: run_step` (watchdog, `VERIFY_TIMEOUT`, `$failures` tally) and
  `common.sh: log_warn`. **Decision:** the Kotlin lane uses the existing `run_step` and
  `log_warn`; `run_gradle_if_exists` becomes a thin guard *around* `run_step`, not a new
  runner. (Reuse ~90%.)
- **FLAG-B — Reuse scope, not raw `git diff`.** The proposal's inactive-`composeApp` guard
  hand-rolls three `git diff`/`ls-files` calls. `scoped_changed_files_by_pathspec`
  (`90-run.sh`) already does exactly this, de-duplicated and scope-aware. **Decision:** the
  drift guard calls `scoped_changed_files_by_pathspec "$AI_VERIFY_SCOPE" 'composeApp/**'`
  when that engine function is available, falling back to a self-contained `git` form only
  when this module is exercised in isolation (tests). (Reuse ~90%.)
- **FLAG-C — Kotlin is a language case, not a new tree.** `language_pathspecs` in the
  sibling ticket already switches on language. The RIGHT long-term home for a `kotlin` case
  is inside that file. But this ticket may NOT edit it (constraint 1). **Decision:** ship a
  standalone `60-kotlin-files.sh` that mirrors the sibling's `language_pathspecs`/
  `scoped_language_files` contract exactly, so a later, unblocked slice can fold the
  `kotlin` case into `51-language-files.sh` with a trivial move. The standalone file
  documents this as its explicit migration target.
- **FLAG-D — Tool policy is a `can_run_tool` case, not `gradle_task_exists` from scratch.**
  The proposal invents `gradle_task_exists` calling `./gradlew tasks --all`. That is the
  correct check, but its home is the tool-policy layer. **Decision:** ship a standalone
  `61-gradle-policy.sh` with `has_gradle_wrapper` + `gradle_task_exists` that mirrors
  `50-tool-policy.sh` conventions (fixed, documented, no PATH fallback for framework tools),
  explicitly documented as folding into `can_run_tool`'s `*)` arm later.
- **FLAG-E — Reports/events reuse the sibling's `54-reporting.sh` + `log_json`.** No new
  evidence directory, no `write_verify_event`. Same FLAG-1/FLAG-2 posture as the sibling.

**Reject / defer:**

- A whole new `10-scope.sh`/`20-gradle-tasks.sh`/.../`90-run.sh` Kotlin tree (FLAG-A/B/C/D).
- Running Detekt/Ktlint/Konsist now, or from PATH, or as a hard gate (proposal §6, §7 agree
  these are P1/P2 project-local).
- Gradle dependency-verification metadata, Compose compiler metrics, screenshot/benchmark
  modules — all P2-P4 in the proposal; out of scope here.
- Any actual `--language kotlin` wiring, wrapper script (`ai-verify-kotlin.sh`), registry
  entry, or agent permission — deferred to a later, separately-approved, UNBLOCKED slice
  (blocked today by constraint 2 + the concurrent writer owning `ai-verify.sh`).

### 1c) Reuse decision (per `>=75%` rule)

- Overlap with `40-step-runner.sh` (`run_step`): ~95% → REUSE, do not re-implement.
- Overlap with `90-run.sh` (`scoped_changed_files_by_pathspec`): ~90% → REUSE when present.
- Overlap with `51-language-files.sh` conventions: ~90% → MIRROR (cannot edit; constraint 1).
- Overlap with `50-tool-policy.sh` conventions: ~85% → MIRROR (cannot edit; constraint 1).
- Overlap with `54-reporting.sh`: ~100% for report/event needs → REUSE as-is.
- Net-new logic with 0% existing overlap: active-module guard, composeApp drift guard,
  version-catalog guard, gradle task-existence guard. These justify new modules.

---

## 2) Problem

There is no Kotlin/Android/KMP verification lane, and the repo's per-language verify design
does not yet know about Gradle-based repos (active/inactive module reality, version-catalog
discipline, task-existence drift). AdvancedGym needs a safe, install-free, non-mutating gate
that proves `:app` + `:shared` still build/lint/compile and that `composeApp` stays inactive.

---

## 3) Target Outcome

- Standalone, test-covered Kotlin-lane modules under `scripts/ai/internal/ai-verify/` that:
  - discover Kotlin/Gradle files scope-aware (`60-kotlin-files.sh`),
  - answer "can this Gradle task run safely right now?" (`61-gradle-policy.sh`),
  - enforce the three net-new repo-reality guards (`62-android-guards.sh`),
  - run the P0 Android/KMP default gate via the existing `run_step` (`63-kotlin-dispatch.sh`).
- **Zero wiring**: not sourced by `ai-verify.sh`, no `--language kotlin`, no registry/pack,
  no agent/permission/capability/command/skill/hook reference.
- **Zero PHP/JS edits**: the concurrent session's files are untouched.
- Each module carries the same "NOT YET sourced by ai-verify.sh" header contract the sibling
  modules use, and names its future fold-in target.

---

## 4) In Scope

New files only (no edits to existing tracked files):

- `scripts/ai/internal/ai-verify/60-kotlin-files.sh` — `kotlin_language_pathspecs`,
  `scoped_kotlin_files` (mirrors `51-language-files.sh`; fold-in target: a `kotlin` case in
  `language_pathspecs`).
- `scripts/ai/internal/ai-verify/61-gradle-policy.sh` — `has_gradle_wrapper`,
  `gradle_task_exists` (mirrors `50-tool-policy.sh`; fold-in target: `can_run_tool` `*)` arm).
- `scripts/ai/internal/ai-verify/62-android-guards.sh` — `check_active_modules`,
  `check_inactive_compose_app_drift`, `check_version_catalog_required` (net-new; reuses
  `scoped_changed_files_by_pathspec` when present, else self-contained git fallback).
- `scripts/ai/internal/ai-verify/63-kotlin-dispatch.sh` — `ai_verify_kotlin` running the P0
  default gate via existing `run_step` + `gradle_task_exists`, with `VERIFY_FULL` /
  `VERIFY_IOS` branches (mirrors `53-language-dispatch.sh` intent from the sibling plan).
- `tests/shell/ai-verify-kotlin-files.bats`
- `tests/shell/ai-verify-gradle-policy.bats`
- `tests/shell/ai-verify-android-guards.bats`
- `tests/shell/ai-verify-kotlin-dispatch.bats`

---

## 5) Out Of Scope (Things To Avoid)

- **Do NOT edit** `50-tool-policy.sh`, `51-language-files.sh`, `54-reporting.sh`,
  `90-run.sh`, `ai-verify.sh`, or any PHP/JS file (constraint 1 + concurrent writer owns them).
- **Do NOT wire** the new modules: no `source` line in `ai-verify.sh`, no `--language kotlin`,
  no `ai-verify-kotlin.sh` wrapper, no `script-registry.php` / `packs.php` entry, no
  regenerated `script-registry.json`, no agent/permission/capability/command/skill/hook.
- **Do NOT install packages or mutate config.** No `npx`, no Gradle plugin edits, no
  `gradle/verification-metadata.xml`, no `curl | bash`. Gradle tasks are only ever *run*
  when the wrapper + task already exist.
- **Do NOT run Detekt/Ktlint/Konsist/dependency-verification** in this slice (P1/P2, project-
  local, and out of this repo entirely — this repo has no Gradle project).
- **Do NOT re-implement** `run_step`, `log_warn`, `log_json`, `scoped_changed_files_by_pathspec`,
  or `verify_report_dir` (FLAG-A/B/E).
- **Do NOT make iOS compilation a default hard gate** — `VERIFY_IOS=1` only.
- **Do NOT invent a new evidence directory** — reuse `${AI_LOG_DIR}/verify/` via the sibling's
  `54-reporting.sh` when a report is written.
- **Do NOT exceed the bounded slice** (~6 files/slice). P0 lands the four modules + their
  tests across two slices if needed.

---

## 6) Affected Paths

New only — see §4. No existing tracked file is modified by this ticket.

## 7) Contracts And Boundaries

- **Module header contract:** every new `6x-*.sh` starts with the same "sourced by
  ai-verify.sh / NOT an entrypoint / NOT YET sourced (deferred wiring) / exercised only by
  tests" header the sibling modules use, plus its named fold-in target.
- **Dispatch contract:** `ai_verify_kotlin` honors `AI_VERIFY_SCOPE`, `VERIFY_FULL`,
  `VERIFY_IOS`, `VERIFY_TIMEOUT`, mutates the global `$failures` via `run_step` exactly like
  every other lane, and never runs a Gradle task that `gradle_task_exists` says is absent.
- **Guard contract:** guards increment `$failures` on violation and print a `FAIL:`/`OK:`
  line to match the existing `check_*` style (`36-plan-status.sh`).
- **Safety contract:** no tool from PATH except `./gradlew` (the project's own wrapper);
  `ALLOW_INACTIVE_MODULE_CHANGES=1` is the documented, explicit escape hatch for the
  composeApp drift guard (quarantine/reactivation work only).
- **Permission contract:** absent by design (constraint 2).

---

## 8) Todo Plan

### P0-a — Kotlin file discovery + Gradle policy (2 modules + 2 tests)

- [ ] Add `60-kotlin-files.sh` (`kotlin_language_pathspecs`, `scoped_kotlin_files`).
- [ ] Add `61-gradle-policy.sh` (`has_gradle_wrapper`, `gradle_task_exists`).
- [ ] Add `tests/shell/ai-verify-kotlin-files.bats` (pathspecs correct; scope honored;
      existing-file filter; unknown handling).
- [ ] Add `tests/shell/ai-verify-gradle-policy.bats` (wrapper presence; task-exists true/false;
      no PATH `gradle` fallback).

### P0-b — Android reality guards + dispatch (2 modules + 2 tests)

- [ ] Add `62-android-guards.sh` (`check_active_modules`, `check_inactive_compose_app_drift`,
      `check_version_catalog_required`), reusing `scoped_changed_files_by_pathspec` when present.
- [ ] Add `63-kotlin-dispatch.sh` (`ai_verify_kotlin`) using `run_step` + `gradle_task_exists`,
      with `VERIFY_FULL` / `VERIFY_IOS` branches; default lane = the five P0 tasks.
- [ ] Add `tests/shell/ai-verify-android-guards.bats` (active-module pass/fail; composeApp
      drift fail + `ALLOW_INACTIVE_MODULE_CHANGES=1` escape; version-catalog pass/fail).
- [ ] Add `tests/shell/ai-verify-kotlin-dispatch.bats` (stub-mode: only expected tasks
      selected; missing task skips cleanly; iOS skipped unless `VERIFY_IOS=1`).

### P1 — Wiring (APPROVED 2026-07-06; BLOCKED on concurrent session committing first)

Goal: selecting Kotlin as a project language INSTALLS and ACTIVATES this lane. This is one
coordinated slice because the edits are cross-file-coupled and every partial step breaks a
test. Execute only AFTER `git status` shows `ai-verify.sh`, `53-language-dispatch.sh`,
`51-language-files.sh`, `50-tool-policy.sh`, and `script-registry.php` are clean/committed by
the concurrent session, to avoid clobbering in-flight work.

Ordered steps (all currently BLOCKED — files owned by active concurrent session):

- [ ] Add `kotlin` case to `53-language-dispatch.sh`: `ai_verify_language` `case` arm +
      `run_kotlin_language_files()` delegating to the existing `ai_verify_kotlin`
      (`63-kotlin-dispatch.sh`). (BLOCKED: `53-*` modified ~6 min ago by concurrent session.)
- [ ] Add `kotlin` case to `51-language-files.sh: language_pathspecs` (fold in the body of
      `60-kotlin-files.sh: kotlin_language_pathspecs`). (BLOCKED.)
- [ ] Source `60..63-*.sh` from `ai-verify.sh` and accept `--language kotlin`. (BLOCKED:
      `ai-verify.sh` modified/uncommitted.)
- [ ] Add `ai-verify-kotlin.sh` wrapper (≤10 lines, `exec` delegation) + `script-registry.php`
      entry + pack membership. (BLOCKED: `script-registry.php` modified/uncommitted.)
- [ ] Add `packages/ai-universal-rules/stacks/kotlin.json` descriptor (detect `*.kt`, `*.kts`,
      `*.gradle.kts`, `settings.gradle.kts`; `language_overlays: []` unless a `kotlin` overlay
      is added). REQUIRES regenerating the committed generated artifact
      `docs/ai/stack-registry.json` via `php tools/ai/generate-stack-registry.php --write`
      (approval-gated generated file). (BLOCKED on the install-wiring ticket's mapping helper.)
- [ ] Optional: add a `kotlin` language overlay in
      `permission-layers/language-overlays.php` (else keep `language_overlays: []`).
- [ ] Flip `kotlin` from no-op to active in the install-wiring ticket's
      `aiVerifyLanguagesForStacks()` mapping
      (`docs/tickets/arch-todo-install-language-select-and-verify-wiring-20260706-014933`).
- [ ] Update AC-05 below: it now INVERTS — Kotlin MUST be wired; add positive wiring tests
      (`bash scripts/ai/ai-verify.sh --language kotlin .` dispatches the Kotlin lane).
- [ ] Detekt/Ktlint(or Spotless)/Konsist as project-local, baseline-first, advisory tools.

Verification for P1: `bash scripts/ai/ai-verify.sh --language kotlin .` (AI_KOTLIN_TEST_MODE
or a stubbed `./gradlew`) selects the Kotlin lane; `generate-stack-registry.php --check` is
clean; `composer test` green.

## 9) Acceptance Criteria

- [ ] AC-01: `60-kotlin-files.sh` emits correct pathspecs for Kotlin/Gradle
      (`*.kt`, `*.kts`, `*.gradle.kts`, `*.gradle`, `gradle/libs.versions.toml`) and, via
      `scoped_kotlin_files`, honors `AI_VERIFY_SCOPE` and filters to existing files.
- [ ] AC-02: `gradle_task_exists` returns true/false correctly against a stubbed
      `./gradlew tasks` and never falls back to a PATH `gradle`.
- [ ] AC-03: `check_active_modules` fails when `:app`/`:shared` missing or `composeApp`
      active; `check_inactive_compose_app_drift` fails on composeApp changes unless
      `ALLOW_INACTIVE_MODULE_CHANGES=1`; `check_version_catalog_required` fails on inline
      versioned deps outside the catalog.
- [ ] AC-04: `ai_verify_kotlin` in stub mode selects exactly the five P0 tasks by default,
      adds full/iOS tasks only under `VERIFY_FULL`/`VERIFY_IOS`, and never runs an absent task.
- [ ] AC-05: NONE of the new modules is sourced by `ai-verify.sh`; NO `--language kotlin`,
      wrapper, registry, pack, permission, capability, command, skill, or hook references them
      — proven by a repo-wide search returning zero wiring matches.
- [ ] AC-06: No PHP/JS/existing-tracked file is modified (`git diff --name-only` shows only
      new files under `scripts/ai/internal/ai-verify/6x-*.sh` and `tests/shell/*kotlin*|*gradle*|*android*`).
- [ ] AC-07: New bats tests pass and `composer test` is green.

## 10) Verification Plan

1. Focused: run each new `.bats` file directly (bats), 60s budget each.
2. Wiring proof: `rg -n 'kotlin|gradle|composeApp|assembleDebug' scripts/ai/ai-verify.sh
   .opencode .github tools/ai/install` MUST return zero matches referencing the new modules.
3. No-edit proof: `git status --short` shows the concurrent session's files unchanged by me.
4. Broad: `composer test` (or `composer test:fast`) as the final gate.

Apply per-command timeouts from `docs/ai/execution-protocol.md`.

## 11) Risks And Rollback

- Risk: divergence from the sibling `51`/`50` conventions before fold-in. Mitigation: mirror
  their headers/signatures exactly and name the fold-in target in each file.
- Risk: someone wires these prematurely. Mitigation: AC-05 search guard + explicit headers.
- Risk: this repo has no Gradle project, so Gradle tasks never actually run here. That is
  intended — the lane targets the AdvancedGym repo; here the modules are proven via stubs.
- Rollback: delete the four new modules + four new tests. No data/state/config migration.
- Success signal: bats green, `composer test` green, wiring/no-edit searches clean.

## 12) Handoff Notes

- P0 (this ticket) lands the four standalone modules + tests, NOT wired.
- Recommended next step after P0: reviewer agent handoff using OpenCode command `/review-diff`.
- P1 (fold-in + wrapper + project-local tools) is a later slice, unblocked only once the
  concurrent sibling ticket lands and constraint 2 is lifted.
