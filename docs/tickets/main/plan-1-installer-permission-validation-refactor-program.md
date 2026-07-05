# Architecture Plan — Installer/Validation/Permission Refactor Program

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria`
> item below is checked `[x]`, rename this file to
> `DONE-plan-1-installer-permission-validation-refactor-program.md` and move it into `archive/`
> under this branch folder
> (`docs/tickets/main/archive/DONE-plan-1-installer-permission-validation-refactor-program.md`).
> See "Archive On Completion" in `.opencode/skills/architecture-plan-writer/SKILL.md` for the
> exact steps.

- Ticket: none (user-provided "Refactor Plan — AI Agent Shipping Repo" pasted 2026-07-05)
- Source: user-supplied refactor plan, persisted faithfully as a scope-locked program plan (no
  redesign performed by this write-up)
- Generated: 20260705 (session date per environment: 2026-07-05)
- Plan folder: `docs/tickets/main/`
- Risk: **high** (installer core and permission compiler are the repo's external-mutation and
  agent-safety boundary)
- Status at plan time: **planning only — no implementation has started.** The user explicitly
  requested "create only a plan for now no changes must be done now."

## Cross-Ticket Record (reuse check — required before any Phase 4 work starts)

Per repo policy (`>=75%` overlap must be flagged and reused, not duplicated), the following
existing tickets were checked and materially overlap this program:

| Existing ticket | Overlaps which phase below | Status | Overlap |
|---|---|---|---|
| `docs/tickets/arch-todo-permission-layer-composition-20260705T004618Z/plan.md` | Phase 4 (permission compiler) | Slices 1, 2, 4 (profile-map) **DONE**; Slices 3, 5, 6, 7 **deferred**; Slices 8 (harness adapter abstraction) and 9 (dangerous-gap validator checks) **not started, explicitly recommended next** | **~90%** — this ticket already IS the deterministic permission compiler (composed model, provenance via layer list, per-agent profile map, `--check`/`--write` generator, drift test). Its own Slice 8 is verbatim this program's PR9/PR10 (adapter seam + regenerate-from-compiler). |
| `docs/tickets/arch-todo-dynamic-stack-permission-selection-20260705T011906Z/plan.md` | Phase 4 (stack/language overlays) | All 6 slices **DONE** | **~70%** — stack overlay composition already lands cleanly into the same `aiPermissionComposeFromSpec()` seam this program's Phase 4 would otherwise re-propose. |
| `docs/tickets/arch-todo-agent-permission-rethink-20260613T154104Z/` (+ `todo-remaining-work.md`) | Phase 4 origin decisions | Locked decisions carried forward by the ticket above | Background only — do not re-open. |

**Binding consequence:** This program does **not** re-plan a permission compiler from scratch.
Phase 4 below is narrowed to "resume `arch-todo-permission-layer-composition-...` at Slice 8,
then Slice 9" and is explicitly cross-referenced rather than duplicated. No new
`tools/ai/install/permission-compiler/` tree is created; the existing
`tools/ai/install/permission-layers/*` + `compose.php` + `generate-agent-permissions.php` remain
canonical, per that ticket's own Negative Criteria (N-2: no new standalone registry/system).

No existing ticket was found (searched `docs/tickets/*` for `core.php`, "God file",
"ValidationEngine", installer-core decomposition) covering Phase 1 (installer core god-file
split), Phase 2 (validation engine unification), Phase 5 (shell-layer thinning), or Phase 6 (test
splitting). Those phases are genuinely net-new scope for this program.

## Context

This repo (`awesome-ai-utmostcreator`) ships agents, permissions, hooks, docs, and scripts into
other repositories via `tools/ai/ai.php install`. The user supplied an external refactor-priority
analysis identifying the installer core, validation pipeline, manifest/registry sources, and
permission compiler as the highest blast-radius, highest refactor-pressure areas, ranked ahead of
generated JSON/Markdown surfaces (which should be regenerated, not hand-edited).

Verified against the live repository (2026-07-05):

- `tools/ai/install/core.php`: **2195 lines** (matches the plan's cited ~2169; grew from ongoing
  uncommitted work — see Worktree State below).
- `tools/ai/commands/install_workflow.php`: **1304 lines** (matches exactly).
- `tools/ai/commands/install_extras.php`: **868 lines** (matches exactly).
- Validators are indeed spread across many independent top-level scripts under `tools/ai/`:
  `validate-ai-config.php`, `full-install-validation.php`, `validate-install-surface.php`,
  `validate-agent-spec.php`, `validate-command-policy.php`, `validate-script-access.php`,
  `validate-generated-artifacts.php`, `validate-context-budgets.php`,
  `verify-install-placeholders.php`, plus `tools/ai/install/verify-manifest.php`,
  `verify-install-result.php`, `verify-no-overwrite.php`, `permission-layers/verify-tiers.php`.
- Registry-like surfaces are indeed numerous: `tools/ai/install/packs.php`,
  `script-registry.php`, `stack-registry.php`, `manifest.php`, plus generated
  `docs/ai/stack-registry.json`, `.ai-install-manifest.json`, and
  `packages/ai-universal-rules/manifest.json`/`manifest.yml` (existence of the latter two is
  **unknown** — not directly verified in this pass; confirm in Phase 3 discovery before acting).

## Worktree State At Plan Time (must not be silently absorbed or overwritten)

`git status --short` at plan time shows **uncommitted, unrelated-looking WIP already touching
files this program targets**:

- Modified: `tools/ai/install/core.php` (+155 lines), `tools/ai/commands/install_workflow.php`
  (+21), `tools/ai/install/script-registry.php` (+8), `tools/ai/install/config.php` (+25),
  `tools/ai/generate-agent-snippets.php`, `tests/php/InstallerSafetyTest.php`, several
  `.opencode/agents/*.md` / `.github/agents/*.agent.md` / template files, `docs/ai/*.md`,
  `.gitignore`.
- Untracked: `tools/ai/install/permission-layers/*`, `tools/ai/install/stack-registry.php`,
  `stack-detection.php`, `tools/ai/commands/stack_selection.php`,
  `tools/ai/generate-agent-permissions.php`, `tools/ai/generate-stack-registry.php`,
  `packages/ai-universal-rules/stacks/*`, `schemas/ai/stack-*.schema.json`, several
  `tests/php/Stack*Test.php` and `tests/php/PermissionComposeTest.php`,
  `docs/ai/stack-registry.json`, `docs/ai/index.md`, and several `docs/tickets/arch-todo-*` plan
  folders.
- This matches (and is explained by) the two "DONE"/"partially-done" tickets in the Cross-Ticket
  Record above — it is very likely the landed-but-uncommitted output of that prior work, not
  unrelated drift.
- **Rule for every phase below:** no slice may run a bulk regenerate, revert, or `git checkout --`
  over these paths without first reconciling (commit, or explicit confirmation the WIP is
  intentional prior work) — this is the same coordination gate the existing permission-layer
  ticket already calls out for its own Slice 3.

## Problem

1. `tools/ai/install/core.php` is a god file (2195 lines) mixing planning, copy, merge, backup,
   manifest, and output concerns — the single highest external-mutation blast radius in the repo.
2. Validation logic is duplicated/spread across many independent scripts with no shared rule
   engine, risking one validator passing while another fails on the same condition.
3. Multiple registry/manifest-like surfaces exist with unclear single-source-of-truth boundaries,
   risking silent drift between generated artifacts and their sources.
4. The permission-compiler need (Phase 4 in the user's plan) is **largely already solved** by an
   in-flight ticket; re-planning it from scratch would violate the repo's `>=75%` reuse rule.
5. Shell hooks (`pre-tool-use`, `ai-verify`, `exec-guard`) may still own policy decisions rather
   than consuming a compiled PHP policy, which is harder to test and reason about.
6. Several test files exceed 500+ lines (`InstallerSafetyTest.php` at time of last check: verify
   current size in Phase 6 discovery — user's plan cites 2610 lines) and likely mix multiple
   behavioral concerns.

## Target Outcome

A staged, behavior-preserving refactor program that, in priority order:

1. Adds a golden install-behavior safety net (Phase 0/PR1) before any structural change.
2. Decomposes `tools/ai/install/core.php` into named, testable modules with `core.php` reduced to
   a `<200`-line orchestration facade (or removed) — Phase 1.
3. Unifies validation into one `ValidationEngine` with per-concern `Rule` classes, with old
   validator scripts becoming thin wrappers — Phase 2.
4. Declares one canonical source per registry concept (packs, scripts, stacks, agent specs,
   permissions, installed state) with generated-output drift checks — Phase 3.
5. **Resumes** (does not re-plan) the existing permission-compiler ticket at its own Slice 8/9 —
   Phase 4.
6. Thins shell hooks down to executing compiled policy rather than deciding it — Phase 5.
7. Splits oversized test files into behavior-focused suites only after the above seams exist —
   Phase 6.

Each phase lands as its own set of small, reviewed, individually-verified PRs — never as one
combined mutation.

## In Scope

- Discovery, module-boundary design, and staged extraction plan for `tools/ai/install/core.php`,
  `install_workflow.php`, `install_extras.php` into the module tree below (Phase 1).
- A single `tools/ai/validation/ValidationEngine.php` + `Rule` contract + reporters, with existing
  validator scripts converted to thin wrappers/subcommands (Phase 2).
- A one-canonical-source decision table for packs/scripts/stacks/agent-specs/permissions/installed
  state, plus a drift validator that fails on stale generated output (Phase 3).
- Resuming `arch-todo-permission-layer-composition-20260705T004618Z` at Slice 8 (harness adapter
  abstraction) then Slice 9 (dangerous-gap validator checks) — **cross-referenced, not
  reproduced** (Phase 4).
- A design for moving decision logic out of `scripts/ai/internal/pre-tool-use/*`,
  `ai-verify/*`, and `exec-guard/*` into consumers of compiled PHP policy, with JSON fixtures
  (Phase 5).
- A split plan for `InstallerSafetyTest.php`, `test-common.sh`, `test-ai-search.sh`,
  `CliToolsTest.php` into behavior-focused suites, executed only after Phase 1/2 seams exist
  (Phase 6).
- A golden install matrix (blank/existing-Copilot/existing-OpenCode/full/minimal/reinstall/
  uninstall/permission-generation/adapter-parity) as a single runnable command, landed first
  (Phase 0 / PR1).

## Out Of Scope (Things To Avoid)

- Do not re-design or re-plan the permission compiler; it already exists
  (`tools/ai/install/permission-layers/*`, `compose.php`, `generate-agent-permissions.php`). Any
  work here is confined to resuming the existing ticket's own remaining slices.
- Do not hand-refactor generated JSON/Markdown surfaces (`.ai/*`, `docs/ai/*.json`,
  `packages/*/catalog.json`, rendered `.opencode/**`/`.github/**`/`AGENTS.md`/`CLAUDE.md`).
  Regenerate from stabilized sources only, per `docs/ai/adapter-contract.md`.
- Do not touch the currently uncommitted WIP files listed above without an explicit reconciliation
  step first (commit, or confirmed-intentional-carryover note) — see Worktree State.
- Do not attempt all 6 phases / 13 PRs in one implementation pass. Each PR in the Todo Plan below
  is its own bounded implementer slice with its own verification and its own reviewer pass.
- Do not delete `command-policy.tiers.yaml` or any file proven live by a consumer chain (same
  caution the existing permission ticket already documents for that exact file) without an
  explicit approval-gated consumer check.
- Do not weaken `core:hard-deny`, the bash `'*'` floor, or any existing test assertion while
  extracting modules — extraction must be behavior-preserving unless a step is explicitly marked
  as an intentional migration with its own before/after diff.
- Do not install dependencies, run destructive git operations, or perform deploys as part of this
  program.

## Affected Paths

- `tools/ai/install/core.php`, `tools/ai/commands/install_workflow.php`,
  `tools/ai/commands/install_extras.php` (Phase 1 decomposition targets)
- New (Phase 1): `tools/ai/install/Application/*`, `Domain/*`, `Planning/*`, `Filesystem/*`,
  `Manifest/*`, `Verification/*`, `Rendering/*` (exact namespaces/paths confirmed during Phase 1
  discovery against this repo's actual PHP autoload/namespace conventions — **unknown**, must be
  checked against `composer.json` before creating files, since this repo may use flat
  `require`-based files rather than PSR-4 namespaces; see Contracts And Boundaries)
- `tools/ai/validate-ai-config.php`, `full-install-validation.php`, `validate-install-surface.php`,
  `validate-agent-spec.php`, `validate-command-policy.php`, `validate-script-access.php`,
  `validate-generated-artifacts.php`, `validate-context-budgets.php`,
  `verify-install-placeholders.php` (Phase 2, converted to thin wrappers)
- New (Phase 2): `tools/ai/validation/ValidationEngine.php`, `ValidationResult.php`, `Rule.php`,
  `Rules/*`, `Reporters/*`
- `tools/ai/install/packs.php`, `script-registry.php`, `stack-registry.php`, `manifest.php`,
  `.ai-install-manifest.json`, `docs/ai/stack-registry.json`, `docs/ai/script-registry.json`
  (Phase 3 source-of-truth documentation + drift check; existence of
  `packages/ai-universal-rules/manifest.json`/`manifest.yml` and `.ai/manifest.lock.json` /
  `.ai/catalog.json` is **unknown** and must be confirmed by a read-only inventory step before
  Phase 3 design decisions are finalized)
- `docs/tickets/arch-todo-permission-layer-composition-20260705T004618Z/plan.md` (Phase 4 —
  resumed, not duplicated; this program's own plan file only cross-links it)
- `scripts/ai/internal/pre-tool-use/*`, `scripts/ai/internal/ai-verify/*`,
  `scripts/ai/internal/lib/exec-guard/*`, `scripts/ai/internal/search/*` (Phase 5)
- `tests/php/InstallerSafetyTest.php`, `tests/shell/test-common.sh`, `tests/shell/test-ai-search.sh`
  (or actual paths confirmed at Phase 6 time), `tests/php/CliToolsTest.php` (Phase 6)

## Contracts And Boundaries

- **PHP module style is unknown and must be confirmed before Phase 1 file creation.** The user's
  plan proposes a PSR-4-style `Application/Domain/Planning/Filesystem/Manifest/Verification/
  Rendering` namespace tree. This repo's existing `tools/ai/install/*.php` files are flat,
  function-based/global-scope PHP files (for example `aiInstallerScriptRegistry()`,
  `aiPermissionComposeFromSpec()` — no observed class/namespace convention in the files read so
  far). Phase 1's first discovery step must read `composer.json` autoload config and 2-3
  representative `tools/ai/install/*.php` files to decide: (a) keep the existing flat
  function-file convention and rename the proposed namespaces to flat file groups
  (`tools/ai/install/planning/target-planner.php` style), or (b) introduce classes/namespaces as
  a deliberate, separately-approved convention change. Do not introduce OOP/namespaces silently
  if the existing convention is procedural — that would be a convention change requiring explicit
  approval, not a transparent refactor.
- Every extraction step in Phase 1 must be individually behavior-preserving: same CLI inputs
  produce the same file-system outputs, manifest, and exit codes before and after each step,
  proven by the Phase 0 golden install matrix.
- `ValidationEngine` (Phase 2) must give every validator's failure the same
  code/message shape regardless of which legacy script or new subcommand triggered it.
- Phase 3's "one canonical source per concept" table must be produced from actual repository
  inventory (which files exist, which are read vs. written) — not assumed from the user's list,
  since some named files' existence is unverified (see Affected Paths).
- Phase 4 is bounded to: read `arch-todo-permission-layer-composition-20260705T004618Z/plan.md`
  Slice 8 and Slice 9 verbatim, confirm they still reflect current repo state, and hand off
  implementation there — this program's plan file does not restate their contracts.
- Phase 5's rule: shell may execute policy; shell must not define policy. Every hook decision
  point identified must resolve to "reads compiled PHP/JSON policy" as its target end-state.
- Phase 6 test splits must preserve every existing assertion; no test may be deleted or weakened,
  only relocated, unless the assertion is proven redundant with a specific line-level citation.

## Todo Plan

### P0 — Discovery and safety net (must land before any structural change)

- [ ] P0-1: Confirm PHP module convention for Phase 1 (read `composer.json`, sample
  `tools/ai/install/*.php` files) and record the decision (flat files vs. namespaced classes) as
  an addendum to this plan before creating any new Phase 1 files.
- [ ] P0-2: Reconcile or explicitly accept the current dirty worktree (see Worktree State) before
  any Phase 1-3 or Phase 5-6 slice touches an already-modified file.
- [ ] P0-3 (PR1): Build the golden install matrix as a single runnable command covering: blank
  repo install, existing-Copilot-repo merge, existing-OpenCode-repo permission preservation, full
  install, minimal install, reinstall (idempotency), uninstall/rollback (owned-files-only),
  permission-generation stability, and adapter parity (Copilot/OpenCode/Claude). Compare generated
  outputs by checksum or normalized JSON diff. Low risk — new test/tooling files only, no
  production installer code touched.
- [ ] P0-4: Add or confirm a generated-artifact drift check runs as part of the same matrix
  command (may already partially exist via `validate-generated-artifacts.php` /
  `validate-adapter-drift.php` — confirm before adding a new one, per the reuse rule).

### P1 — Installer core decomposition (Phase 1, one PR per step, in this order)

- [ ] P1-1 (PR2): Extract an `InstallPlan` DTO (or equivalent flat-file structure per P0-1's
  decision) as the stable object boundary every later step builds on.
- [ ] P1-2 (PR3): Extract manifest read/write/reconcile responsibility out of `core.php` +
  `manifest.php` into a dedicated module; prove no ownership/overwrite regression via the golden
  matrix.
- [ ] P1-3 (PR4): Extract backup/rollback/foreign-file-protection responsibility (highest safety
  value) into a dedicated module.
- [ ] P1-4 (PR5): Extract conflict-planning/overwrite-policy decisions into a dedicated,
  independently testable module.
- [ ] P1-5: Extract target-planning ("what to install") separately from file-writing ("how to
  write").
- [ ] P1-6: Centralize filesystem mutation into one writer responsibility.
- [ ] P1-7 (PR6): Reduce `core.php` to a `<200`-line orchestration facade (or remove it entirely
  if every responsibility has a new home); reduce `install_workflow.php` to CLI orchestration
  only.

### P1 — Validation engine unification (Phase 2)

- [ ] P1-8 (PR7): Create `tools/ai/validation/ValidationEngine.php` + `Rule` contract +
  `ValidationResult` + text/JSON/GitHub reporters.
- [ ] P1-9 (PR8): Migrate each existing validator script into a `Rule` implementation, converting
  the original script into a thin wrapper/subcommand that calls the shared engine. One script at
  a time, each with its own before/after parity check (same pass/fail on the same fixtures).

### P2 — Registry/manifest source-of-truth stabilization (Phase 3)

- [ ] P2-1: Run a read-only inventory confirming which of the named registry/manifest files
  (`packages/ai-universal-rules/manifest.json`, `manifest.yml`, `.ai/manifest.lock.json`,
  `.ai/catalog.json`, `packages/ai-universal-rules/catalog.json`) actually exist in this repo
  today, and which are read vs. generated.
- [ ] P2-2: Produce the "one canonical source per concept" table from that inventory (packs,
  scripts, stacks, agent specs, permissions, installed state), each with a generated-output list.
- [ ] P2-3: Add or confirm a drift validator fails when a generated output is stale relative to
  its declared canonical source; add a "generated file header names its source + generator" check
  where missing.

### P3 — Permission compiler (Phase 4 — resume existing ticket, do not duplicate)

- [ ] P3-1 (PR9): Re-read `arch-todo-permission-layer-composition-20260705T004618Z/plan.md` in
  full immediately before starting, confirm Slice 8/9 preconditions still hold against current
  repo state, then implement that ticket's own Slice 8 (harness adapter abstraction:
  `aiPermissionRenderAdapters()` seam, OpenCode adapter byte-identity round-trip against the
  shipped `researcher.md` block as landing proof).
- [ ] P3-2 (PR10): Implement that ticket's own Slice 9 (dangerous-gap validator checks against the
  composed model) once Slice 8 lands.
- [ ] P3-3: Update this program's Cross-Ticket Record row to reflect the new status once P3-1/P3-2
  land; do not fork a second implementation of the same seam.

### P4 — Shell layer thinning (Phase 5)

- [ ] P4-1: Inventory current decision logic inside `scripts/ai/internal/pre-tool-use/*.sh`,
  `ai-verify/*.sh`, `exec-guard/*` to identify which parts are policy (should move to PHP) vs.
  glue (should stay in shell).
- [ ] P4-2 (PR11): Move identified policy decisions to be consumed from compiled PHP/JSON output;
  add JSON fixtures for hook decisions; ensure every shell CLI entrypoint keeps stable
  `--json`/`--help`/exit-code contracts throughout.

### P5 — Test architecture split (Phase 6, after P1/P2 seams exist)

- [ ] P5-1: Re-measure current line counts for `InstallerSafetyTest.php`, `CliToolsTest.php`, and
  the named shell test files (repo-verify actual current sizes before splitting, since sizes may
  have changed since the user's plan was written).
- [ ] P5-2 (PR12): Split each oversized file into behavior-focused suites (installer plan,
  conflict, backup/rollback, manifest, idempotency, foreign-file-protection; search
  contract/text-mode/struct-mode/git-mode; hook policy/pipe-command policy/post-tool-use) without
  deleting or weakening any existing assertion.

### P6 — Docs/markdown consolidation (Phase 6 tail, lowest priority)

- [ ] P6-1 (PR13): Once P1-P4 registries/compilers are stable, identify Markdown/template
  duplication that can become a projection of those stabilized sources, and file it as its own
  follow-up ticket rather than doing it inside this program (keeps this program bounded to
  code/behavior, not prose).

## Acceptance Criteria

- [ ] AC-1: A single command runs the full golden install matrix (Phase 0) and its outputs are
  stable across repeated runs on an unchanged codebase.
- [ ] AC-2: `tools/ai/install/core.php` is either deleted or reduced to under 200 lines acting
  purely as an orchestration facade, with no behavior regression proven by the golden matrix.
- [ ] AC-3: `tools/ai/commands/install_workflow.php` is reduced to CLI orchestration only (target:
  roughly under 300 lines per the user's plan; exact target confirmed once P1's module
  convention is decided).
- [ ] AC-4: Every legacy validator script (`validate-ai-config.php`, `full-install-validation.php`,
  `validate-install-surface.php`, etc.) becomes a thin wrapper over `ValidationEngine`, producing
  identical pass/fail results on existing fixtures/tests.
- [ ] AC-5: The same validation failure produces the same code/message regardless of entrypoint.
- [ ] AC-6: A documented one-canonical-source table exists for packs/scripts/stacks/agent-specs/
  permissions/installed-state, backed by a drift check that fails on stale generated output.
- [ ] AC-7: Phase 4 lands entirely inside the existing permission-layer-composition ticket (its
  own Slice 8 then Slice 9); this program creates no parallel permission-compiler implementation.
- [ ] AC-8: Shell hooks under `pre-tool-use/`, `ai-verify/`, `exec-guard/` consume compiled
  PHP/JSON policy for identified decision points, each backed by a JSON fixture test.
- [ ] AC-9: No test file identified in Phase 6 exceeds roughly 500 lines post-split (fixture-heavy
  exceptions noted explicitly), and no existing assertion is deleted or weakened.
- [ ] AC-10: Every PR in the Todo Plan above lands as its own reviewed, individually-verified
  change — no combined multi-phase commit.
- [ ] AC-11: The hard-deny floor, bash `'*'` fallback posture, and all currently-passing tests
  remain green (or improve) throughout every phase; no phase silently weakens an existing safety
  check.

## Verification Plan

- Phase 0 (PR1): the golden-matrix command itself is the verification artifact; run it against an
  unmodified checkout first to establish the baseline before any Phase 1+ change.
- Phase 1 (PR2-PR6): after each extraction step, run the golden install matrix plus
  `composer test:fast` (or `composer test` when serial ordering matters, per
  `docs/ai/execution-protocol.md`); `php -l` every new/changed file.
- Phase 2 (PR7-PR8): run each migrated validator's existing test coverage plus a before/after
  parity run (old script vs. new thin wrapper) on the same fixtures.
- Phase 3: run `php tools/ai/validate-generated-artifacts.php` (or the equivalent drift check)
  after declaring canonical sources; confirm no currently-passing validator flips red.
- Phase 4 (PR9-PR10): use the exact verification commands already specified in
  `arch-todo-permission-layer-composition-20260705T004618Z/plan.md`'s own "Verification Plan"
  section for Slice 8 and Slice 9 — do not re-derive new ones.
- Phase 5 (PR11): run the shell test suite covering `pre-tool-use`/`ai-verify`/`exec-guard`
  (`docs/ai/execution-protocol.md` budgets: focused shell tests ~60s, full shell harness ~360s
  budget) plus new JSON-fixture tests.
- Phase 6 (PR12): run the full existing test suite for each split file before and after, confirming
  identical pass/fail counts, only reorganized across more files.
- Whole program: `composer test` full serial run as the final gate before considering the program
  complete; report any pre-existing unrelated baseline failures separately from new regressions
  (the two related tickets both note ~10 pre-existing unrelated baseline failures at last check —
  reconfirm this count is unchanged before attributing any new failure to this program).

## Risks And Rollback

| Risk | Mitigation / rollback |
|---|---|
| Attempting multiple phases in one implementation pass | Hard-gated by this plan's per-PR Todo Plan structure; implementer agent stops at ~6 files / one bounded slice per hard rules |
| Silent collision with existing uncommitted WIP (permission-layers/stack-registry files) | P0-2 reconciliation step before any Phase 1/3/5/6 slice touches those exact files |
| Duplicating the permission compiler already built in another ticket | Phase 4 is scoped to "resume", not "re-plan"; Cross-Ticket Record + AC-7 make this an explicit gate |
| Introducing a namespace/OOP convention this repo doesn't use | P0-1 discovery step required before any Phase 1 file is created |
| Behavior regression during `core.php` decomposition | Golden install matrix (Phase 0) runs before/after every extraction step; each step is its own revertible PR |
| Registry inventory assumptions wrong (some named files may not exist) | P2-1 is read-only inventory before any Phase 3 design decision is finalized |
| Test-split phase causes silent assertion loss | AC-9 + Phase 6 verification requires identical pass/fail counts pre/post split |
| Rollback | Every PR is its own git-revertible commit; no phase performs an irreversible operation (no deploys, no destructive git, no dependency installs) |

## Handoff Notes

- This file is a **plan only**; per the user's explicit instruction, no implementation has been
  started. All Todo Plan and Acceptance Criteria items are unchecked.
- Before implementing **any** item, re-run `git status --short` — the worktree state recorded
  above will have changed and must be re-verified, not assumed.
- Recommended execution order: P0 (all four items) first, in full, before any other phase — it is
  explicitly the lowest-risk, highest-value entry point per the user's own plan.
- Phase 4 must not begin without first re-reading
  `docs/tickets/arch-todo-permission-layer-composition-20260705T004618Z/plan.md` end to end, since
  it may have changed since this cross-reference was written.
- Recommended next step: implementer means implementer agent handoff using OpenCode command:
  `/implement` — start with **P0-3 (PR1, the golden install matrix)**, since it is additive-only,
  touches no production installer/permission code, and establishes the safety net every later
  phase depends on.
