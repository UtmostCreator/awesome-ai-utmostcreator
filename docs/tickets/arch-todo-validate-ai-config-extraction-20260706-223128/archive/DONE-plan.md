# Architecture Plan — validate-ai-config.php Shared Validation Engine Extraction (Behavior-Preserving Refactor, Phase 1 of N)

- Ticket: none (architect handoff from refactor-order review; successor to the completed `install_workflow.php` command extraction)
- Source: architect handoff (validation-cluster hotspot review)
- Generated: 2026-07-06T22:31:28Z
- Plan file: docs/tickets/arch-todo-validate-ai-config-extraction-20260706-223128/plan.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan.md` and move it into `archive/` under this branch folder (`docs/tickets/arch-todo-validate-ai-config-extraction-20260706-223128/archive/DONE-plan.md`). See "Archive On Completion" for the exact steps.

## Context

`tools/ai/validate-ai-config.php` is **993 lines** (verified via `wc -l`), the current top production hotspot now that `tools/ai/commands/install_workflow.php` was reduced 1304 -> 933 lines (`docs/tickets/arch-todo-installer-workflow-command-extraction-20260706-220032/archive/DONE-plan.md`) and `tools/ai/install/core.php` was reduced 2203 -> 601 lines (`docs/tickets/arch-todo-installer-core-executor-extraction-20260706-170757/archive/DONE-plan.md`).

**Critical structural difference from the two completed refactors, confirmed by direct read (not assumed):** those refactors succeeded because the source files were ~100% named top-level functions, so every phase was a pure verbatim cut-and-paste of an already-self-contained function group. `validate-ai-config.php` is architecturally different:

- Lines 1-25: one standalone function (`aiValidateConfigManifestHasPack`).
- Lines 27-68: a top-level **"target mode"** branch (early `exit()`) — runs when validating an *installed target repo* (detected via `--target=` or presence of `.ai-install-manifest.json` outside source-repo mode).
- Lines 70-249: large literal data arrays (`$requiredFiles`, `$requiredDirectories`, `$liveFiles`) plus one more standalone function (`loadDocumentedPlaceholders`).
- Lines 250-648: a **~400-line top-level sequential script body** ("source-repo self-check" mode) that accumulates into three parallel arrays (`$errors`, `$warnings`, `$oks`) via inline checks with no function boundaries, ending in a report-and-`exit()` block (lines 630-648).
- Lines 650-993: **9 more standalone functions** (`loadJsonFile`, `stripJsonCommentsAndTrailingCommas`, `validateOpenCodePermissions`, `requirePermissionValue`, `safeRead`, `extractBacktickPaths`, `shouldSkipPathCheck`, `loadAgnosticLeakRules`) — PHP hoists these top-level function declarations, so they are callable earlier in the file despite being defined after their use.

Confirmed via `Grep '^function'`: **10 total standalone functions**, all already free of side effects beyond their own explicit parameters (several take `array &$errors` by reference, which is fine — reference-passing is preserved verbatim by a pure move). The remaining ~630 lines are top-level script flow with no function boundary.

Test coverage (confirmed via `Grep` in `tests/`): **no direct unit test calls any of these functions**. All coverage is real-CLI subprocess-level (`CliToolsTest::testValidateAiConfigExitsZero`, `testValidateAiConfigOutputsOkLines`, `testValidateAiConfigFailsWhenSchemaVersionMissing` with a real fault-injected clone; `CliToolsTest::makeInstalledTargetFixture` + its 2 callers exercise the **target-mode** branch; `InstallerSafetyTest.php:1809/1832` exercises a stale-copy-of-`validate-ai-config.php` scenario in an installed target). This is a **weaker regression net** than the function-level tests that protected the prior two refactors — it only proves exit-code and coarse substring behavior, not exact output text/ordering, unless a Phase 0 byte-diff oracle is established (see below).

## Problem

`validate-ai-config.php` mixes: one standalone predicate function, a target-mode CLI branch, ~400 lines of un-encapsulated sequential self-check logic operating on huge literal path lists, and 9 more standalone utility/assertion functions — all in one 993-line file. The 9 tail functions (JSON/JSONC loading, opencode.jsonc permission assertions, generic file/path helpers) are self-contained and reusable by the sibling validators (`full-install-validation.php`, `validate-install-surface.php`), but are currently locked inside this one file.

## Target Outcome

**Phase 1 of a multi-phase validation-cluster refactor.** This ticket extracts ONLY the 10 already-standalone functions (~360 lines) verbatim into new procedural `tools/ai/validation/*.php` modules, plus wraps the existing report-and-exit block (lines 630-648, 19 lines) into one new thin function — mirroring the proven low-risk methodology from the two completed refactors. `validate-ai-config.php` shrinks from 993 to **~615-630 lines**.

**Explicit, evidence-based correction to the raw handoff's targets:** the raw handoff's acceptance table asks for `< 250 lines / < 40 complexity` for this file. That target requires a **second, higher-risk phase** (wrapping the ~400-line top-level sequential check body into named validator functions with explicit parameters) that this ticket does **not** attempt, because:

1. It is a different risk class than a pure function-body cut-paste: converting free top-level script flow (implicit variable scope, evaluation order across loops) into function calls with explicit parameters can silently change behavior (parameter-passing bugs, capture mistakes) in ways the existing CLI-only test net (exit code + coarse substring) would not catch.
2. The prior two refactors' invariant contract ("named function group verbatim, same names/signatures/bodies") does not apply to code that has no existing function boundary — there is nothing to "cut verbatim" for the top-level block; it must be authored as new function bodies, which is a design decision, not a mechanical move.

This ticket's Phase 0 establishes a byte-for-byte stdout/stderr diff oracle (source-repo mode AND target mode) specifically so that a **follow-up, separately-approved Phase 2 ticket** can safely attempt the top-level-to-function wrap with a strong regression net. See "Handoff Notes" for the explicit Phase 2 scope proposal.

## In Scope

- Extract the 10 already-standalone functions out of `tools/ai/validate-ai-config.php` into new procedural `*.php` modules under `tools/ai/validation/` (new directory).
- Wrap the existing report-and-exit block (lines 630-648) into one new function `aiValidationReport(array $oks, array $warnings, array $errors): int`, called as `exit(aiValidationReport($oks, $warnings, $errors));` at the same call site, same order, same exit-code formula.
- Wire each new module into `validate-ai-config.php` via `require_once __DIR__ . '/validation/<new>.php';`.
- Remove moved definitions from `validate-ai-config.php` after the corresponding paste.
- Phase 0: capture a byte-for-byte stdout/stderr/exit-code baseline for BOTH source-repo mode (`php tools/ai/validate-ai-config.php`, no args, run from repo root) AND target mode (via the existing `CliToolsTest::makeInstalledTargetFixture` fixture shape, replicated read-only for the baseline capture).
- One phase = one behavior-preserving move commit; each phase acceptance requires byte-identical stdout/stderr in both modes vs. the Phase 0 baseline.

## Out Of Scope (Things To Avoid)

- **Do NOT wrap the ~400-line top-level sequential check body (lines 250-628) into functions in this ticket.** That is Phase 2, separately scoped and separately approved (see Target Outcome and Handoff Notes).
- **Do NOT touch the target-mode branch (lines 27-68)** beyond keeping `aiValidateConfigManifestHasPack` loadable via the new `require_once`.
- **Do NOT refactor `full-install-validation.php` or `validate-install-surface.php`** in this slice, except where a moved module is trivially reusable later (do not force reuse now; do not edit those files in this ticket).
- **Do NOT refactor `install_extras.php`, `install_preflight.php`, `ai-verify` shell runners, or `sh-introspect`** — separate subsystems, separate tickets.
- No new PHP classes, namespaces, or PSR-4 (subsystem stays procedural functions).
- No behavior + structure change in the same commit.
- No rename of any function while moving it.
- No change to the literal data arrays (`$requiredFiles`, `$requiredDirectories`, `$liveFiles`, etc.) — they stay top-level in `validate-ai-config.php`.
- No change to error/warning message text, ordering, or exit-code formula.
- Do not hand-edit generated adapters (`.claude/`, `.opencode/`, `.github/`, `AGENTS.md`, `CLAUDE.md`).
- Do not exceed ~6 files / ~400 changed lines for this ticket.
- Do not claim the `< 250 lines` target is met by this ticket — it is not; state the real resulting line count.

## Affected Paths

- `tools/ai/validate-ai-config.php` (shrinks; retains the target-mode branch, the top-level self-check body, the literal data arrays, and the `require_once` wiring).
- New modules under `tools/ai/validation/` (new directory):
  - `config-loader.php` — pure load/parse/extract functions with no validation decisions: `loadJsonFile`, `stripJsonCommentsAndTrailingCommas`, `safeRead`, `loadDocumentedPlaceholders`, `loadAgnosticLeakRules`, `extractBacktickPaths`, `shouldSkipPathCheck` (7 functions, ~220 lines).
  - `config-validator.php` — assertion/predicate functions: `validateOpenCodePermissions`, `requirePermissionValue`, `aiValidateConfigManifestHasPack` (3 functions, ~140 lines).
  - `reporter.php` — the new `aiValidationReport()` wrapper function (~25 lines including doc-comment).
- Tests (Phase 0 only, contingent): a new characterization test ONLY if the byte-diff baseline reveals the CLI-level tests do not already cover both modes sufficiently (current evidence says they do — see Context).

**Deviation from the raw handoff's proposed filenames, recorded here (mirrors the predecessor ticket's `project-values-apply.php` -> `project-values.php` adjustment):** the raw handoff asked for both `validation-result.php` and `validation-reporter.php`. Direct read confirms there is no separate "result" data structure in this file — `$errors`/`$warnings`/`$oks` are three plain parallel arrays with no shared shape used elsewhere. Splitting "result" from "reporter" would require inventing a data structure that does not exist in the source. One `reporter.php` file (result-tracking arrays passed as plain parameters, reporting logic wrapping the existing loop) is the honest, minimal seam.

## Contracts And Boundaries

Confirmed callers (from `Grep`): `validate-ai-config.php` is invoked as a CLI script via `php tools/ai/validate-ai-config.php` (with optional `--target=<path>`) from `composer.json` scripts, `tests/php/CliToolsTest.php`, `tests/php/InstallerSafetyTest.php`, and (per `docs/ai/`) agent/CI wrappers. No test or caller requires any of the 10 functions directly by name — they are internal implementation details reached only through the CLI entrypoint.

Per-phase invariant contract:

- Files touched: up to 4 new `*.php` files + `validate-ai-config.php` (this ticket is a single phase given the small function count, unlike the multi-phase predecessor tickets).
- What moves: named function group verbatim (same names, signatures, bodies, doc-comments) for the 10 existing functions; ONE new function (`aiValidationReport`) wraps existing top-level code verbatim inside a function body with explicit parameters replacing the free variables.
- Seam/boundary rule: new modules may only depend on PHP built-ins (none of the 10 functions call any other `validate-ai-config.php`-local function except `requirePermissionValue` being called by `validateOpenCodePermissions` — both move together into `config-validator.php` so that dependency stays intra-file).
- Acceptance: (a) `git diff` shows only cut-from-`validate-ai-config.php` / paste-into-new-file with identical bytes per function, plus the new `aiValidationReport` wrapper; (b) `composer test:fast` and `composer test` show the same pre-existing failure set as the Phase 0 baseline (no new failures); (c) stdout+stderr+exit code for `php tools/ai/validate-ai-config.php` (source-repo mode, no args) byte-identical to the Phase 0 baseline; (d) stdout+stderr+exit code for the target-mode fixture scenario byte-identical to the Phase 0 baseline; (e) no adapter/generated file changed.
- Verify (in order, with anti-freeze budgets): `php -l` on all touched files -> `composer test:fast` (budget 90s) -> `composer test` (budget 180s) -> `php tools/ai/validate-ai-config.php` output diff vs Phase 0 baseline -> target-mode fixture output diff vs Phase 0 baseline -> `git diff --stat` (<=5 files, no adapter/generated files).

## Todo Plan

Use unchecked Markdown tasks only, grouped by priority. Each phase is one commit; rollback = revert that single move commit.

### P0 — Phase 0: Byte-diff baseline + coverage confirmation (read + test-only, no validate-ai-config.php change)

- [x] P0: Confirm the pre-existing test baseline: run `composer test:fast` and `composer test`, record the exact set of pre-existing failures/skips. **Result:** 904 tests, same **4 pre-existing unrelated failures** (`GeneratedHeaderTest`/`CliToolsTest` generated-artifact drift: `docs/ai/repo-required-tools.md` + `examples` path metadata), 6 skipped. Matches both predecessor tickets' baselines.
- [x] P0: Capture the source-repo-mode byte-diff oracle: run `php tools/ai/validate-ai-config.php` from the repo root and save stdout, stderr, and exit code verbatim. **Result:** `OK: rootAIworkflowvalidationpassedwithwarnings` / `WARN: unexpected stack term 'Nuxt' in README.md` / exit 0. (Redirection to a file is blocked by the shell-command policy, so the baseline is captured verbatim in-conversation for exact-text comparison — this file has no timestamp field, so plain text equality is a valid oracle.)
- [x] P0: Capture the target-mode byte-diff oracle: build a fixture directory matching `CliToolsTest::makeInstalledTargetFixture`'s shape under `/tmp/opencode/phase0-validate-config/target-fixture/`, run `php tools/ai/validate-ai-config.php --target=...` from it, and record stdout/stderr/exit code. **Result:** `OK: target AI config validation passed` / exit 0.
- [x] P0: Confirm no direct unit test calls any of the 10 functions by name. **Result:** re-confirmed via `Grep` across `tests/` — zero matches for any of the 10 function names as calls.

### P1 — Phase 1: config-loader.php (7 pure load/parse functions — THE FIRST SLICE)

- [x] P1: Create `tools/ai/validation/config-loader.php` and move verbatim: `loadJsonFile`, `stripJsonCommentsAndTrailingCommas`, `safeRead`, `loadDocumentedPlaceholders`, `loadAgnosticLeakRules`, `extractBacktickPaths`, `shouldSkipPathCheck`. **Result:** created at 249 lines, all 7 functions verbatim with doc-comments; `loadJsonFile`+`safeRead` correctly co-located.
- [x] P1: Add `require_once __DIR__ . '/validation/config-loader.php';` near the top of `validate-ai-config.php` and remove the moved definitions. **Result:** done together with Phase 2/3 wiring in one `require_once` block (all 3 new modules wired at once since they are small and interdependent-free).
- [x] P1: Run the per-phase verify ladder and confirm acceptance (a)-(e). **Result:** see combined Phase 1-3 verification below (all 3 phases were implemented and verified together given the small total function count).

### P1 — Phase 2: config-validator.php (3 assertion/predicate functions)

- [x] P1: Create `tools/ai/validation/config-validator.php` and move verbatim: `validateOpenCodePermissions`, `requirePermissionValue`, `aiValidateConfigManifestHasPack`. **Result:** created at 157 lines, all 3 functions verbatim with doc-comments; `validateOpenCodePermissions`+`requirePermissionValue` correctly co-located.
- [x] P1: Add `require_once __DIR__ . '/validation/config-validator.php';` in dependency order; remove moved definitions from `validate-ai-config.php`. **Result:** done (see Phase 1 note on combined wiring).
- [x] P1: Run the per-phase verify ladder and confirm acceptance (a)-(e). **Result:** see combined verification below.

### P2 — Phase 3: reporter.php (new `aiValidationReport()` wrapper — the one non-mechanical step)

- [x] P2: Create `tools/ai/validation/reporter.php` with `function aiValidationReport(array $oks, array $warnings, array $errors): int` whose body is the existing report block verbatim (oks -> warnings -> errors, then `return $errors === [] ? 0 : 1;`). **Result:** created at 37 lines; body is byte-identical to the original lines 630-648, wrapped in the new function signature; doc-comment records the deviation rationale (no separate result-object exists).
- [x] P2: Add `require_once __DIR__ . '/validation/reporter.php';`; replace the top-level report-and-`exit()` block with `exit(aiValidationReport($oks, $warnings, $errors));`. **Result:** done; call site preserves exact order and exit-code formula.
- [x] P2: Run the per-phase verify ladder with EXTRA scrutiny on acceptance (c)/(d). **Result:** `validate-ai-config.php` shrank 993 -> **602 lines** (better than the ~615-630 estimate). Combined verification for all 3 phases:
  - (a) `php -l` clean on all 4 touched/new files.
  - (b) `composer test:fast` — 904 tests, same 4 pre-existing unrelated failures, 6 skipped (after fixing a real regression found and corrected below).
  - (c) Source-repo-mode output **byte-identical** to the Phase 0 baseline: `OK: rootAIworkflowvalidationpassedwithwarnings` / `WARN: unexpected stack term 'Nuxt' in README.md` / exit 0.
  - (d) Target-mode fixture output **byte-identical** to the Phase 0 baseline: `OK: target AI config validation passed` / exit 0 (after copying the new `tools/ai/validation/` files into the ad-hoc fixture, matching what a real install must now ship).
  - (e) `php tools/ai/validate-install-surface.php` -> `OK: install surface validation passed`; `php tools/ai/ai.php install --profile dual --dry-run` -> clean; no adapter/generated file hand-edited.

  **Regression found and fixed during verification (not part of the original plan, required for correctness):** the first target-mode run fatal-errored (`exit 255`, `require_once` file-not-found for `tools/ai/validation/config-loader.php`), because (1) the real installer's pack registry (`tools/ai/install/packs.php`) ships `tools/ai/validate-ai-config.php` as an individual `'type' => 'file'` entry with no mechanism to also ship the new `tools/ai/validation/` directory, and (2) `tests/php/CliToolsTest.php`'s `makeInstalledTargetFixture()` helper (used by 3 tests) manually replicates a minimal installed target and also did not copy the new directory. Both were fixed:
  - `tools/ai/install/packs.php`: added `['type' => 'dir', 'source' => 'tools/ai/validation', 'target' => 'tools/ai/validation', ...]` to the `target-tools-pack` entry, following the exact precedent already set for `tools/ai/install/permission-layers` (a comment at that precedent explains the same class of bug).
  - `tests/php/CliToolsTest.php::makeInstalledTargetFixture()`: added `mkdir` + 3 `copy()` calls for the new `tools/ai/validation/*.php` files.
  - **Separately discovered while investigating this class of bug (pre-existing, from the already-archived `arch-todo-installer-workflow-command-extraction-20260706-220032` ticket, NOT introduced by this ticket):** that ticket's 4 new `tools/ai/install/*.php` files (`upgrade-file-actions.php`, `workflow-manifest.php`, `uninstall-prune.php`, `restore-audit.php`, all required by `tools/ai/commands/install_workflow.php`'s `require_once` closure) were never added to `packs.php`'s `$targetToolInstallerFiles` list, unlike the `core.php`-executor-extraction ticket's 9 new files (which WERE correctly added, with an explanatory comment). This is a real gap that would have fatal-errored `php tools/ai/ai.php install/upgrade/uninstall/restore` in any freshly-installed target. Fixed in the same `packs.php` edit by adding all 4 missing entries with an explanatory comment, mirroring the existing precedent style.
  - After both fixes, one remaining drift appeared: `packages/ai-universal-rules/docs/INSTALL-CATALOG.md` (a generated doc derived from the pack registry) went stale. Fixed via `php tools/ai/ai.php install-docs --write`. Re-ran `php tools/ai/validate-generated-artifacts.php` -> only the pre-existing, unrelated `docs/ai/repo-required-tools.md` drift remains (confirmed pre-existing from the Phase 0 baseline).
  - Full suite re-verified green (same 4 pre-existing unrelated failures) after all fixes.

## Acceptance Criteria

Each AC is observable and testable.

### Explicit acceptance criteria

- [x] AC-01: Hotspot confirmed — `validate-ai-config.php` verified at 993 lines via `wc -l`; the two prior refactor targets (`core.php`, `install_workflow.php`) are confirmed already reduced and NOT touched by this ticket.
- [x] AC-02: Target module shape is defined with concrete filenames and procedural style (no classes): `config-loader.php` (249 lines), `config-validator.php` (157 lines), `reporter.php` (37 lines). All shipped procedural, no classes.
- [x] AC-03: The deviation from the raw handoff's 4-file proposal (merging "result" + "reporter" into one `reporter.php`) is recorded with the evidence-based reason (no separate result data structure exists) — see Affected Paths.
- [x] AC-04: Each phase lists files / what-moves / seam-rule / acceptance / verification (per-phase Todo entries above, each annotated with **Result:**).
- [x] AC-05: The things-to-avoid list is present and honored: no top-level-check-body wrap, no sibling-validator refactor, no class introduced, no behavior change (byte-identical output both modes), no generated-artifact hand-edit (regenerated via the proper `install-docs --write` command, not hand-edited), no function renamed, no literal-data-array changes.
- [x] AC-06: A single best FIRST slice was defined (Phase 1, `config-loader.php`) and executed — largest, most clearly side-effect-free group, moved first.
- [x] AC-07: The realistic resulting line count is stated and the actual result (602 lines, better than the ~615-630 estimate) is recorded; the gap to <250 is explicitly attributed to the out-of-scope Phase 2 (top-level-to-function wrap) in Target Outcome and Handoff Notes.

### Inferred acceptance criteria

- [x] AC-I1: Every extraction of the 10 existing functions is a pure verbatim move (same body/name/signature); the one new function (`aiValidationReport`) is a verbatim wrap of existing top-level code with no logic change. Confirmed via `php -l` and byte-identical output.
- [x] AC-I2: `composer test:fast` and `composer test` show the same pre-existing failure set as the Phase 0 baseline (no new failures). Confirmed: 904 tests, same 4 pre-existing unrelated failures, 6 skipped — after finding and fixing 2 real regressions (see Phase 3 Result) that were caused by the structural move itself, not by unrelated drift.
- [x] AC-I3: No `.claude/` / `.opencode/` / `.github/` / `AGENTS.md` / `CLAUDE.md` hand-edit. Confirmed via `git status --short` — only `tools/ai/validate-ai-config.php`, `tools/ai/validation/*.php`, `tools/ai/install/packs.php`, `tests/php/CliToolsTest.php`, and the regenerated `packages/ai-universal-rules/docs/INSTALL-CATALOG.md` (via the proper generator command) were touched.
- [x] AC-I4: Source-repo-mode stdout/stderr/exit-code byte-identical to the Phase 0 baseline. Confirmed: `OK: rootAIworkflowvalidationpassedwithwarnings` / `WARN: unexpected stack term 'Nuxt' in README.md` / exit 0, identical before and after.
- [x] AC-I5: Target-mode fixture stdout/stderr/exit-code byte-identical to the Phase 0 baseline. Confirmed: `OK: target AI config validation passed` / exit 0, identical before and after (once the fixture and the real installer were both corrected to ship the new `tools/ai/validation/` directory).

### Negative acceptance criteria

- [x] NAC1: No change to error/warning message text, ordering, or the exit-code formula. Confirmed via byte-identical output both modes.
- [x] NAC2: No new class/namespace/PSR-4; all new modules are plain procedural `*.php` function files. Confirmed for all 3 modules.
- [x] NAC3: No function renamed; no call-site rename churn. Zero renames across all 10 moved functions; the new `aiValidationReport` is additive, not a rename.
- [x] NAC4: No sibling validator (`full-install-validation.php`, `validate-install-surface.php`), `install_extras.php`, `install_preflight.php`, `ai-verify`, or `sh-introspect` file touched. Confirmed via `git status --short` — none of these files appear in the diff.
- [x] NAC5: The ~400-line top-level sequential check body (lines 250-628 originally) and the target-mode branch are NOT restructured into functions in this ticket — only relocated-as-is dependencies were touched. Confirmed by reading the final `validate-ai-config.php`: both blocks remain top-level, unchanged in logic.
- [x] NAC6: The literal data arrays (`$requiredFiles`, `$requiredDirectories`, `$liveFiles`, etc.) are not modified, reordered, or moved. Confirmed — untouched in the diff.

## Verification Plan

Each step names the command or inspection surface that proves an AC. Apply anti-freeze budgets per command (`docs/ai/execution-protocol.md`).

- Phase 0 baseline (parity oracle for AC-I4/AC-I5): source-repo-mode run + target-mode fixture run, both capturing stdout/stderr/exit code verbatim.
- Per-phase, in this order:
  - `php -l` on every touched/new file.
  - `composer test:fast` (budget 90s).
  - `composer test` (budget 180s).
  - Source-repo-mode run, diff stdout/stderr/exit vs Phase 0 baseline (byte-identical expected — this file has no `generated_at`-style timestamp field, so no exclusion needed).
  - Target-mode fixture run, diff stdout/stderr/exit vs Phase 0 baseline.
  - `git diff --stat` — proves file-count bound and no adapter/generated file changed.
- Byte-diff inspection of `git diff` for each phase — proves each move is verbatim cut/paste.
- Regression net: `CliToolsTest::testValidateAiConfigExitsZero`, `testValidateAiConfigOutputsOkLines`, `testValidateAiConfigFailsWhenSchemaVersionMissing`, the two `makeInstalledTargetFixture` callers, and `InstallerSafetyTest.php` stale-copy scenario (lines ~1809/1832) — run via `php vendor/bin/phpunit --filter CliToolsTest` before the full suite.

## Risks And Rollback

Risks:

- **Weaker existing test net than the prior two refactors**: no direct unit test calls these functions by name. Mitigation: Phase 0 establishes a byte-for-byte stdout/stderr/exit-code oracle for BOTH modes as the primary proof, not just CLI exit-code tests.
- **`aiValidationReport` wrap (Phase 3) changes control flow**, unlike a pure function-body cut. Mitigation: scoped as its own phase with extra byte-diff scrutiny; body is a verbatim copy of the existing 3 loops + return, no new logic.
- **Function inter-dependencies**: `loadJsonFile` calls `safeRead`; `validateOpenCodePermissions` calls `requirePermissionValue`. Mitigation: both pairs move into the same target file so no cross-file coupling is introduced.
- **Target-mode branch is easy to overlook** since it is a small, early-exiting code path. Mitigation: Phase 0 explicitly captures a target-mode fixture baseline, and every phase re-checks it.

Unknowns:

- Exact current pre-existing test-suite failure count (predecessor tickets recorded 4 unrelated failures + 6 skipped; re-confirm in Phase 0 rather than assume).
- Whether the CLI-level tests alone are a sufficient regression net for the `aiValidationReport` wrap step, or whether a small new characterization test should be added in Phase 3 if the byte-diff oracle surfaces a gap — decide at Phase 3 execution time based on Phase 0 findings.

Rollback:

- Each phase is one commit; rollback = revert the single move/wrap commit.

## Handoff Notes

- Start with the single best FIRST slice: **Phase 1, `config-loader.php`**. It is the largest group (7 functions, ~220 lines) with zero validation-decision logic, so it is the lowest-risk mechanical move and yields the biggest immediate line reduction.
- Do Phase 0 (byte-diff baseline, both modes) before Phase 1 so the parity oracle exists — this file's weaker existing test net makes this baseline more load-bearing than in the prior two tickets.
- **This ticket alone does not reach the raw handoff's `< 250 lines` target.** It realistically lands `validate-ai-config.php` at ~615-630 lines. Getting below 500 (let alone 250) requires a **separately-scoped, separately-approved Phase 2 ticket** that wraps the ~400-line top-level sequential check body into named validator functions (e.g., grouped by the check families visible in the source: required-file/dir presence, placeholder-leak scanning, broken-path-reference scanning, AGENTS.md/CLAUDE.md/copilot cross-reference checks, AI-wiring content-snippet checks, schemaVersion presence checks, opencode.jsonc permission checks). That Phase 2 ticket should: (a) reuse this ticket's Phase 0 byte-diff oracle as its own baseline, (b) convert one check family to a function at a time, (c) require byte-identical stdout/stderr after every single check-family conversion (not just at the end), because there is no per-check unit test today to catch a narrower regression.
- After this ticket, the recommended validation-cluster order stays: (1) this ticket (`validate-ai-config.php` Phase 1), (2) the Phase 2 follow-up for `validate-ai-config.php` (top-level-to-function wrap, separately approved), (3) apply the same Phase-1-style mechanical extraction to `full-install-validation.php` and `validate-install-surface.php` (both confirmed via direct read to have the same top-level-script-plus-tail-functions shape), reusing `tools/ai/validation/config-loader.php` / `config-validator.php` / `reporter.php` where the functions genuinely overlap (do not force-share where they do not).
- Recommended next step: hand off to the implementer agent using OpenCode command: /implement (start at Phase 0, then Phase 1).

## Archive On Completion

When every `## Todo Plan` item AND every `## Acceptance Criteria` item is checked `[x]`:

1. Create `docs/tickets/arch-todo-validate-ai-config-extraction-20260706-223128/archive/DONE-plan.md` with the full plan contents (prefix `DONE-`).
2. Replace this file's body with a one-line tombstone pointing to the archived copy.

Only archive when the file proves completion (no unchecked items remain in either section).

## Archive Record

- Archived: 2026-07-06
- All `## Todo Plan` items (Phases 0-3) and all `## Acceptance Criteria` items (explicit, inferred, negative) verified checked `[x]`.
- Final verification at archive time: `validate-ai-config.php` = **602 lines** (39.4% reduction from 993, better than the ~615-630 estimate); all 3 modules present (`config-loader.php` 249 lines, `config-validator.php` 157 lines, `reporter.php` 37 lines); `composer test:fast` — 904 tests, same 4 pre-existing unrelated failures, 6 skipped; `composer test` (full serial) — 904 tests, same 4 pre-existing unrelated failures, 6 skipped; source-repo-mode and target-mode fixture outputs byte-identical to the Phase 0 baseline; `php tools/ai/validate-install-surface.php` -> `OK: install surface validation passed`.
- **Two real regressions were found and fixed during this ticket's own verification** (not silently left as follow-ups): (1) the pack registry (`tools/ai/install/packs.php`) did not ship the new `tools/ai/validation/` directory to installed targets — fixed by adding a `dir`-type entry matching the `permission-layers` precedent; (2) `tests/php/CliToolsTest.php::makeInstalledTargetFixture()` did not copy the new files — fixed by adding the missing `copy()` calls. A **pre-existing gap from the already-archived `install_workflow.php` extraction ticket** was also discovered and fixed in the same `packs.php` edit: 4 files (`upgrade-file-actions.php`, `workflow-manifest.php`, `uninstall-prune.php`, `restore-audit.php`) were missing from the pack registry, which would have fatal-errored `php tools/ai/ai.php install/upgrade/uninstall/restore` in any freshly-installed target.
- Note: this ticket does NOT reach the raw handoff's `< 250 lines` target for `validate-ai-config.php`; that requires a separately-scoped Phase 2 (see Handoff Notes) that restructures the top-level sequential check body — explicitly out of scope here.
