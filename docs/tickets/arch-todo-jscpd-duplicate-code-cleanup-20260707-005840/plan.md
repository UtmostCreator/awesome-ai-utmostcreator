# Architecture Plan — jscpd-Detected Duplicate Code Cleanup

- Ticket: none (branch `main`; descriptive folder used, per repo convention for undated tickets)
- Source: user-requested jscpd duplication scan (2026-07-07), triaged into true/false positives
- Generated: 2026-07-07T00:58:40Z

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria`
> item below is checked `[x]`, rename this file to `DONE-plan.md` and move it into `archive/`
> under this ticket folder
> (`docs/tickets/arch-todo-jscpd-duplicate-code-cleanup-20260707-005840/archive/DONE-plan.md`).

## Context

Ran this repo's opt-in jscpd duplication guardrail two ways for full evidence:

1. `VERIFY_JSCPD=1 JSCPD_PATHS=. bash scripts/ai/ai-verify.sh .` — the registered module
   (`scripts/ai/internal/ai-verify/35-jscpd.sh`), fetched via `npx --yes jscpd` (no local
   binary present). Result: `jscpd duplication = 12.45% >= 5% (WARN, advisory-only)`.
2. `npx --yes jscpd . --min-tokens 50 --reporters console,json --output /tmp/opencode/jscpd-report`
   (same tool, direct invocation) to recover per-clone detail the wrapper module deletes after
   each run (`mktemp -d` + `trap ... RETURN`). Result: 675 clones, 17,999/145,088 duplicated
   lines (12.41%), JSON at `/tmp/opencode/jscpd-report/jscpd-report.json` (not committed).

Triage split the 675 clones into two classes:

- **Expected/intentional** (the large majority): this is a template-authoring kit whose job is
  to render `packages/ai-universal-rules/templates/**` into installed per-runtime copies
  (`.claude/agents/*`, `.github/agents/*.agent.md`, `.opencode/agents(-optional)/*`, `.ai/*.json`
  mirrors of `packages/ai-universal-rules/*.json`, `docs/ai/**` vs its template source, etc.).
  Documented in `docs/ai/adapter-contract.md` ("Adapter Surfaces"). **Not in scope here.**
- **True positives** (real, non-generated source duplication) — the subject of this plan:
  1. A byte-identical `*CommandExists()` PHP helper (Windows WinGet-fallback + POSIX `command -v`
     check) copy-pasted across up to 5 `tools/ai/**` files.
  2. A `setUpBeforeClass()` repo-root resolution + `tearDown()`/`$tmpDirs`/`removeTree()` PHPUnit
     boilerplate block copy-pasted across **28** files under `tests/php/` (grep-confirmed;
     jscpd's pairwise clone list under-counts this because it only reports clone *pairs* above
     its token threshold, not the full N-way cluster).
  3. A `stripJsonCommentsForTest()` / `stripJsonc()` JSONC-comment-stripping helper duplicated
     between `tests/php/AgentPermissionPolicyTest.php` and `tests/php/GeneratedHeaderTest.php`.

Existing reuse precedent found and to be followed for (2)/(3): `tests/php/Support/ShIntrospectTestCase.php`
is already an abstract base `TestCase` hosting shared `setUpBeforeClass()` repo-root resolution for
a family of related test classes — this is the established pattern, not a new one.

## Problem

Real, non-generated logic is duplicated in a way that increases maintenance risk (a bug fix or
Windows-compat change to the command-exists check must currently be applied in up to 5 places by
hand; the same is true for the JSONC-stripping test helper and, at larger scale, the per-test
teardown boilerplate).

## Target Outcome

- The `tools/ai/advisor/registry.php` ⇔ `tools/ai/commands/helpers.php` byte-identical
  `*CommandExists()` duplicate is eliminated: one canonical implementation, both existing public
  function names preserved as thin delegating wrappers so **zero caller files need to change**
  (see Contracts And Boundaries).
- A fully scoped, sequenced follow-up plan exists (P1-P3 below) for the remaining true positives,
  each bounded to a safe file-count per slice, so no single future PR exceeds the project's
  "pause beyond ~6 files" guidance.
- No behavior change to any existing passing test or verification command.

## In Scope (this plan)

- P0 (implemented in this pass): dedup `aiAdvisorCommandExists` (`tools/ai/advisor/registry.php`)
  and `aiCliCommandExists` (`tools/ai/commands/helpers.php`).
- P1-P3 (planned, NOT implemented in this pass — separate future slices): dedup the remaining
  command-exists variants, the 28-file PHPUnit teardown boilerplate, and the JSONC-stripper test
  helper.

## Out Of Scope (Things To Avoid)

- Do NOT touch any file under `packages/ai-universal-rules/templates/**` vs. its rendered/installed
  counterpart (`.claude/agents/**`, `.github/agents/**`, `.opencode/agents*/**`, `.ai/*.json`,
  `docs/ai/**` mirrors, `.github/workflows/**`, `.github/hooks/scripts/**`, `.claude/settings.json`,
  `.vscode/settings.json`) — that duplication is the intentional adapter-rendering pipeline, not a
  code smell. Re-flag any accidental drift there separately; do not "fix" it here.
- Do NOT rename `aiAdvisorCommandExists`, `aiCliCommandExists`, `aiInstallerCommandExists`, `hasBin`,
  or `commandExists` at any of their ~22 call sites across `scanner.php`, `install_workflow.php`,
  `verify.php`, `install_preflight.php`, `core.php` — that is a much larger, separate refactor
  (rename-and-update-all-call-sites) not requested and not needed to fix the duplication.
- Do NOT change `tools/ai/secret-scan.php`'s standalone/dependency-free property (P1 note): it may
  be intentionally self-contained (no `require`) so it can run before any autoload/bootstrap exists;
  confirm before adding a `require` to it.
- Do NOT silently change `tools/ai/install/toolchain.php`'s Windows UNC-cwd wrapping behavior
  (  `aiInstallerSafeCwdEnter`/`Leave` around the `where` call) when it is later folded into the
  shared helper (P1) — that wrapping is load-bearing for WSL/UNC paths, not incidental.
- Do NOT attempt all 28 files from the `removeTree`/`$tmpDirs` cluster (P2) in one PR; batch it
  into ~5-6-file slices as specified in the Todo Plan.
- Do NOT touch the 675-clone jscpd JSON report or treat percentage-only evidence as proof of a
  fixable defect without reading the actual duplicated fragment first (as done here).

## Affected Paths

- P0 (this pass): `tools/ai/advisor/registry.php`, `tools/ai/commands/helpers.php`, plus one new
  shared file (exact path decided in Todo Plan).
- P1 (future): `tools/ai/install/toolchain.php`, `tools/ai/secret-scan.php`,
  `tools/ai/validation/watchdog-runner.php`.
- P2 (future, batched): 28 files under `tests/php/` (see grep list in Verification Plan) +
  `tests/php/Support/` (new or extended shared base).
- P3 (future): `tests/php/AgentPermissionPolicyTest.php`, `tests/php/GeneratedHeaderTest.php`.

## Contracts And Boundaries

- P0's shared helper is a pure function of `(string $command): bool` with no side effects beyond
  the existing Windows `PATH`/`$_SERVER`/`$_ENV` mutation the original code already performs when
  it finds a WinGet-installed binary — that mutation is preserved exactly, not added or removed.
- P0 must not change either `aiAdvisorCommandExists()`'s or `aiCliCommandExists()`'s public
  signature or return semantics — both remain callable exactly as before from `scanner.php`,
  `install_workflow.php`, and `verify.php` with no edits to those three caller files.
- P0's new shared file must not require Composer autoload (this repo's `tools/ai/*.php` files are
  plain, dependency-light PHP scripts included via `require_once`, not a PSR-4 package).

## Todo Plan

### P0 — Dedup `registry.php` ⇔ `helpers.php` `*CommandExists()` (implement now)

- [x] P0.1: Add a new shared file `tools/ai/command-exists.php` with one canonical
      `aiCommandExists(string $command): bool` function containing exactly the existing
      WinGet-fallback + POSIX logic (byte-identical body, function renamed only). **VERIFIED:**
      created; body diffed against both originals — logic byte-identical, only the function name
      changed.
- [x] P0.2: In `tools/ai/advisor/registry.php`, `require_once` the new file and replace
      `aiAdvisorCommandExists()`'s body with a one-line delegation to `aiCommandExists()`.
      **VERIFIED:** `require_once __DIR__ . '/../command-exists.php';` added; function body is
      now `return aiCommandExists($command);`.
- [x] P0.3: In `tools/ai/commands/helpers.php`, `require_once` the new file and replace
      `aiCliCommandExists()`'s body with a one-line delegation to `aiCommandExists()`.
      **VERIFIED:** `require_once __DIR__ . '/../command-exists.php';` added; function body is
      now `return aiCommandExists($command);`.
- [x] P0.4: Confirm every existing include-order path that loads `registry.php` or `helpers.php`
      can also resolve the new `require_once` path. **VERIFIED:** only real-code entrypoint is
      `tools/ai/ai.php` (requires both `advisor/scanner.php` → `registry.php`, and
      `commands/helpers.php`, in the same process); `__DIR__ . '/../command-exists.php'` resolves
      correctly from both `tools/ai/advisor/` and `tools/ai/commands/` and PHP's `require_once`
      realpath-dedupes the double inclusion. `php tools/ai/ai.php advisor --check` (exercises the
      `registry.php` path via `scanner.php`) ran clean.
- [x] P0.5: Run the focused verification in this plan's Verification Plan and record results.
      **VERIFIED:** see Verification Plan section below — all green.

### P1 — Remaining command-exists variants (plan only; separate future slice)

- [ ] P1.1: Decide whether `tools/ai/install/toolchain.php`'s `aiInstallerCommandExists()` can
      delegate to `aiCommandExists()` while preserving its `aiInstallerSafeCwdEnter()`/`Leave()`
      wrapping and its `ast-grep.exe`/`sg`/`sg.exe` and `repomix.cmd`/`repomix.exe` alias
      recursion (both must stay intact).
- [ ] P1.2: Decide whether `tools/ai/secret-scan.php`'s `hasBin()` should delegate too, or must
      stay standalone/dependency-free (confirm via its call context before deciding).
- [ ] P1.3: Decide whether `tools/ai/validation/watchdog-runner.php`'s `commandExists()` (simpler,
      no WinGet fallback) should delegate to `aiCommandExists()` (a behavior improvement on
      Windows) or stay as-is (smaller diff, no behavior change) — get explicit approval either way
      since it changes runtime behavior on Windows.

### P2 — PHPUnit `removeTree`/`$tmpDirs`/repo-root-in-`setUpBeforeClass` boilerplate (plan only; batch into multiple future slices, ~5-6 files per slice)

- [ ] P2.1: Confirm the exact shared shape across the 28 files (grep-listed in Verification Plan)
      is uniform enough for one abstract base class/trait, following the existing
      `tests/php/Support/ShIntrospectTestCase.php` precedent (do not invent a second pattern).
- [ ] P2.2: Batch 1 (smallest/cleanest matches first) — migrate a first small group of files to
      the shared base; run full suite; confirm zero assertion changes.
- [ ] P2.3: Repeat in further batches of ~5-6 files until all 28 are migrated or a file is found
      to have a real behavioral difference (in which case, leave it un-migrated and note why).

### P3 — `stripJsonCommentsForTest()` / `stripJsonc()` dedup (plan only; small future slice)

- [ ] P3.1: Move the shared JSONC-stripping logic into `tests/php/Support/` (new small helper,
      following the same precedent as P2) and have both `AgentPermissionPolicyTest.php` and
      `GeneratedHeaderTest.php` call it.

## Acceptance Criteria

- [x] AC-01: `tools/ai/command-exists.php` exists with one `aiCommandExists()` implementation
      whose body is unchanged logic from the original two duplicates (verified by direct diff/read).
- [x] AC-02: `aiAdvisorCommandExists()` and `aiCliCommandExists()` still exist, are still callable
      with the same signature, and both now delegate to `aiCommandExists()`.
- [x] AC-03: No caller file (`scanner.php`, `install_workflow.php`, `verify.php`) needed any edit.
      **VERIFIED:** re-ran the tracked-file search for all three call names post-edit — all
      caller lines byte-identical to the pre-edit search.
- [x] AC-04: `composer test` (or the narrower `--filter` covering advisor/helpers PHP tests) passes
      with no new failures attributable to this change. **VERIFIED:** focused
      `vendor/bin/phpunit --filter "UninstallTest|RegistryProjectionTest|RestoreWorkflowTest|InstallerSelectionEngineTest|ToolGatewayTest|ScriptRegistryInvariantTest"`
      → 64 tests, 1225 assertions, OK. Full `composer test:fast` → 902 tests, 12374 assertions,
      OK (5 pre-existing skips, unrelated).
- [x] AC-05: `php -l` (lint) passes clean on all 3 touched/new PHP files. **VERIFIED:** all three
      report "No syntax errors detected".
- [x] AC-06: P1/P2/P3 remain fully unchecked in this plan (explicitly deferred, not silently
      dropped) unless the user asks to continue into them in this same session. **VERIFIED:**
      confirmed unchecked below.

## Verification Plan

- `AI_OUTPUT=json bash scripts/ai/ai-search.sh tracked "aiAdvisorCommandExists" tools --fixed` /
  `... "aiCliCommandExists" tools --fixed` — re-run after the edit to confirm caller call sites
  (`scanner.php`, `install_workflow.php`, `verify.php`) are byte-unchanged.
- `php -l tools/ai/command-exists.php tools/ai/advisor/registry.php tools/ai/commands/helpers.php`
- Focused PHPUnit run covering any test file that already exercises `registry.php`/`helpers.php`
  or the advisor/CLI commands that call the wrapped functions (identify via
  `AI_OUTPUT=json bash scripts/ai/ai-search.sh tracked "registry.php\|helpers.php" tests/php --fixed`
  before picking a `--filter`).
- Fallback/full proof if no narrower test exists: `composer test:fast`.
- Evidence for the P2 grep-list (28 files, for the future batched slice):
  `AI_OUTPUT=json bash scripts/ai/ai-search.sh tracked "function removeTree" tests/php --fixed`.

## Risks And Rollback

- **Low risk (P0):** pure extract-function refactor, no signature/behavior change, no caller
  edits. Rollback: `git checkout -- tools/ai/command-exists.php tools/ai/advisor/registry.php
  tools/ai/commands/helpers.php` (or revert the commit) — fully reversible, no migration/data
  concern.
- **P1 risk (future):** the Windows UNC-cwd wrapping and recursive alias logic in
  `toolchain.php` are load-bearing; a careless delegation could silently drop them. Flagged
  explicitly in Out Of Scope/Todo so a future implementer does not skip that check.
- **P2 risk (future):** 28-file test refactor is the largest risk surface here purely by file
  count; batching (~5-6 files/slice) with a full-suite run after each batch bounds the blast
  radius per slice.

## Handoff Notes

- This pass implements **P0 only**. P1/P2/P3 are fully scoped but intentionally left as future
  bounded slices per the "pause beyond ~6 files" guidance in `AGENTS.md`.
- reviewer means reviewer agent handoff using OpenCode command: `/review-diff`.
