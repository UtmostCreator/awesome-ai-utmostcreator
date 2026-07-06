# Architecture Plan — Copilot Placeholder/Unknown Fixes + Claude Permission Enforcement

- Ticket: none
- Source: architect design handoff (Project 1 of 5)
- Generated: 20260706-024750
- Plan folder: docs/tickets/arch-todo-copilot-claude-policy-enforcement-20260706-024750/
- Sequence: **Project 1 (FIRST)** in a five-plan effort. Execution order across the effort is 1 -> 2 -> 3 -> 4 -> 5. This plan is first because it has the highest user impact and unblocks Claude permission enforcement.
- Risk: MEDIUM

## Global Constraints

- Edit ONLY shipped template sources under `packages/ai-universal-rules/templates/**` and installer/generator PHP under `tools/ai/install/**`. `.claude/`, `.opencode/`, `.github/`, `AGENTS.md`, `CLAUDE.md` are GENERATED — never hand-edit; fix the template/generator so a re-install regenerates them.
- Logging is OUT OF SCOPE. Do not touch `docs/tickets/arch-todo-runner-agnostic-logging-core-20260706/**` or any dirty logging file.
- MUST-NOT-TOUCH dirty in-flight files (on main): README.md, docs/ai/script-registry.json, docs/ai/script-registry.md, docs/ai/scripts-reference.md, docs/ai/verification-matrix.md, install-ai-kit.sh, schemas/ai/evidence-event.schema.json, scripts/ai/MANIFEST.md, scripts/ai/ai-verify.sh, scripts/ai/common.sh, scripts/ai/internal/ai-verify/90-run.sh, scripts/ai/internal/lib/30-logging.sh, tests/scripts/ai/test-common.sh, tools/ai/install/script-registry.php, tools/ai/validate-ai-config.php, tools/ai/validate-install-surface.php (dirty — coordinate verify runs, do NOT commit edits), plus untracked logging additions. NOTE: `validate-install-surface.php` is dirty — run it for verification but do NOT edit it.
- No constraint-#1 exceptions apply to this plan (all files touched here have a template/generator source).

## Context

Two distinct enforcement defects share a root cause class of "installer/generator did not run or did not re-render":

1. **Copilot placeholder/unknown bugs** — the Copilot instruction template is correct, but the rendered output is stale and two path-glob placeholders have no data source.
2. **Claude permissions-not-enforced** — every Claude-side template and generator is verified correct, but the `adapter-claude` pack was never registered in the installed manifest, so the Claude settings-merge and agent rendering never ran via the kit.

## Problem

- Rendered `.github/copilot-instructions.md:~85` still shows `unknown` on the verify line even though the template uses `<PRIMARY_VERIFY_COMMAND>`, `.ai/project.yml:9` sets `primaryVerifyCommand="composer test"`, and `core.php:1353` overrides from project.yml. This is a STALE RENDER (a re-render problem), not a template-content problem.
- `<TEST_PATH_GLOB>` (testing.instructions.md:2) and `<FRONTEND_PATH_GLOB>` (frontend.instructions.md:2) are NOT in any placeholder map and NOT in project.yml; `core.php:363-364,448-449` treat them as post-install hand-edit, so they render as literal invalid globs in `applyTo`.
- Claude root cause: `packages/ai-universal-rules/templates/claude/settings.json` is CORRECT (full permissions). `tools/ai/install/claude-settings-merge.php` is CORRECT + idempotent (unions permissions, concatenates hooks, never clobbers graphify). `claude-agent-renderer.php` + `claude-agent-tool-registry.php` + `permission-layers/render-adapters.php` are CORRECT. THE ACTUAL DEFECT: the `adapter-claude` pack is NOT in the installed `.ai-install-manifest.json` (packs list has adapter-copilot, adapter-opencode, optional-agents-opencode-pack, optional-agents-copilot-pack — NO adapter-claude / NO optional-agents-claude-pack). So the Claude settings-merge and agent rendering never ran via the kit; the installed `.claude/settings.json` is graphify-hooks-only with NO permissions block, while `.claude/agents/*.md` bodies claim "Hard enforcement lives in .claude/settings.json permissions" (injected at render by `claude-agent-renderer.php:80-82`).

## Target Outcome

- Installing/re-installing the kit produces a `.claude/settings.json` that contains BOTH a permissions block AND the graphify hooks.
- The rendered Copilot verify line reads `composer test` (no `unknown`), sourced from project.yml.
- `<TEST_PATH_GLOB>` and `<FRONTEND_PATH_GLOB>` render as valid, non-breaking `applyTo` values.
- Claude agent-body enforcement claims are true after install (permissions actually present).

## In Scope

- Register the `adapter-claude` pack (and, if it exists and is intended, `optional-agents-claude-pack`) in the pack registry so install runs the Claude settings-merge and agent rendering.
- Fix the stale-render path so `<PRIMARY_VERIFY_COMMAND>` / `<PRIMARY_TEST_COMMAND>` render from project.yml.
- Give `<TEST_PATH_GLOB>` and `<FRONTEND_PATH_GLOB>` a valid non-breaking default (safe default or wired placeholder).
- Reconcile the renderer-injected agent-body enforcement wording only if a residual mismatch remains after the pack is registered.

## Out Of Scope (Things To Avoid)

- Any hand-edit of rendered `.github/instructions/*` or `.claude/settings.json` (they are generated).
- Any change to `claude-settings-merge.php` or `claude-agent-renderer.php` — verified correct, do not touch their logic.
- Deleting or altering graphify hooks in the merge.
- Triggering a full `AGENTS.md` / `CLAUDE.md` replace re-render that clobbers the out-of-band `## graphify` section (see `docs/ai/adapter-contract.md`, "Out-Of-Band Local Additions").
- Any logging files or the dirty must-not-touch list above.
- Widening scope into the other four plans (Claude clarification, handoffs, preview-file, migration posture).

## Affected Paths

- `tools/ai/install/packs.php` — register `adapter-claude` (and possibly `optional-agents-claude-pack`) in the default/selectable set. Verify whether `optional-agents-claude-pack` exists first.
- `tools/ai/install/core.php` — add `<TEST_PATH_GLOB>` / `<FRONTEND_PATH_GLOB>` to the placeholder map (sourced from new optional project.yml keys OR ship safe defaults); ensure `<PRIMARY_VERIFY_COMMAND>` / `<PRIMARY_TEST_COMMAND>` re-render from project.yml.
- `packages/ai-universal-rules/templates/instructions/testing.instructions.md` — safe non-breaking `applyTo` default.
- `packages/ai-universal-rules/templates/instructions/frontend.instructions.md` — same.
- NO change to `claude-settings-merge.php` or `claude-agent-renderer.php` (verified correct).

## Contracts And Boundaries

- The Claude settings-merge contract (union permissions, concatenate hooks, never clobber graphify, idempotent) must remain unchanged; this plan only ensures the pack that invokes it is registered.
- `applyTo` frontmatter must remain valid YAML that parses to a real glob.
- Placeholder rendering must remain idempotent and must not reintroduce `unknown` on re-install.
- Do not alter the `merge_strategy: replace` posture of `AGENTS.md` / `CLAUDE.md` in this plan.

## Todo Plan

- [x] P0-1: Register the `adapter-claude` pack (verify whether `optional-agents-claude-pack` exists and is intended before registering it) in `tools/ai/install/packs.php` so install runs `claude-settings-merge` and the installed `.claude/settings.json` gains a permissions block unioned onto the graphify hooks. **Found already registered** in `packs.php` (lines ~155-171) and wired into the `claude`/`full-governance`/`creator`/`full`/`agents-only` profiles in `profiles.php`; `optional-agents-claude-pack` does not exist and was correctly not fabricated (confirmed via `php tools/ai/ai.php packs` — `registry_errors: []`, `adapter-claude` present in `available_packs`). No code change was required; no edit made.
- [x] P0-2: Fix the stale-render path in `tools/ai/install/core.php` so `<PRIMARY_VERIFY_COMMAND>` / `<PRIMARY_TEST_COMMAND>` render from project.yml (`unknown` -> `composer test`). **Proved stale-render-only per the improvement's corrected guidance**: a full re-render of a git-tracked copy of this repo (with its real `.ai/project.yml`) produces `Use \`composer test\` as the main verification command...` at line 90; a fresh target with no project.yml correctly falls back to `unknown`. `core.php:1361` already emits `$values['primaryVerifyCommand']`. No `core.php` edit was made for this item — the checked-in repo's `.github/copilot-instructions.md` is simply an unregenerated stale artifact, not a generator defect (see Handoff Notes).
- [x] P1-1: Replace the invalid `<TEST_PATH_GLOB>` default in `testing.instructions.md` with a valid non-breaking `applyTo` (safe default or wired placeholder in `core.php`). **Found already implemented** as an uncommitted, pre-existing edit to `core.php`'s placeholder map (`'<TEST_PATH_GLOB>' => '**/*.test.*,**/*.spec.*,**/tests/**,**/test/**,**/__tests__/**'`); verified it renders a valid, comma-separated glob matching the convention already used by `.github/instructions/php.instructions.md` etc., and that the YAML frontmatter parses. No template edit needed/made (template still holds the token; substitution happens in `core.php`).
- [x] P1-2: Same for `<FRONTEND_PATH_GLOB>` in `frontend.instructions.md`. Same pre-existing `core.php` fix (`**/*.tsx,**/*.jsx,**/*.vue,**/*.svelte,**/frontend/**,**/client/**,**/web/**,**/ui/**`), verified rendered/parses. No template edit needed/made.
- [x] P2-1: Reconcile the agent-body "enforcement lives in settings.json permissions" claim now that P0-1 makes it true; adjust the renderer-injected wording only if a residual mismatch remains. **No residual mismatch found**: `claude-agent-renderer.php`'s injected wording is accurate now that `adapter-claude` is registered and (per the isolated repair test below) correctly merges permissions when applied. Per scope, `claude-agent-renderer.php` was not touched. The live source repo's own un-reconciled `.claude/settings.json` is an operational/deployment gap, not a generator defect — see Handoff Notes.

## Acceptance Criteria

- [x] AC-01: After `php tools/ai/ai.php install --dry-run` (and a real re-install where applicable), the installed `.claude/settings.json` contains BOTH a `permissions.allow` block AND the graphify hooks. Verified in an isolated `/tmp` git copy of this repo (not the real repo — protected paths were not touched here): `install-ai-kit.php --profile full-governance --adopt --force --allow-core-overwrite` on a copy whose `.claude/settings.json` started as graphify-hooks-only produced a merged file with both `permissions` and the original `hooks.PreToolUse`/graphify content intact.
- [x] AC-02: The rendered Copilot instructions verify line reads `composer test` with NO `unknown` on that line. Verified in the same isolated copy: line 90 renders `Use \`composer test\` as the main verification command...`.
- [x] AC-03: `<TEST_PATH_GLOB>` and `<FRONTEND_PATH_GLOB>` render as valid `applyTo` values whose frontmatter parses. Verified via a fresh install into `/tmp` — both files render concrete, quoted glob-list values (no literal `<...>` token, no `unknown`).
- [ ] AC-04: `composer test` is green. NOT fully green: `composer test` (896 tests) has 2 pre-existing, out-of-scope failures (`CliToolsTest::testValidateGeneratedArtifactsExitsZero`, `GeneratedHeaderTest::testValidateGeneratedArtifactsPasses`) both caused by `docs/ai/repo-required-tools.md` drift against untracked script additions from a sibling in-flight plan (`scripts/ai/ai-verify-*.sh`, unrelated to this plan's file list); confirmed unrelated to any file this plan touched.
- [x] AC-05: `php tools/ai/validate-install-surface.php`, `php tools/ai/validate-placeholders.php`, and `php tools/ai/validate-adapter-drift.php` all report clean. All three exit 0 (`validate-adapter-drift.php` and `validate-install-surface.php` emit only pre-existing, unrelated soft-max/reference WARNs).
- [x] AC-06: The out-of-band `## graphify` section in `AGENTS.md` / `CLAUDE.md` is not clobbered by any re-render performed for this plan. Confirmed: `git diff` shows zero changes to `AGENTS.md`/`CLAUDE.md` in the real repo, and both still contain `## graphify`.

## Verification Plan

- AC-01: `php tools/ai/ai.php install --dry-run` then inspect the produced `.claude/settings.json` for `permissions.allow` and graphify hooks; `php tools/ai/validate-install-surface.php` (run — do not edit; it is dirty).
- AC-02: re-render, then search the rendered `copilot-instructions.md` verify line for `composer test` and confirm no `unknown`; `php tools/ai/validate-placeholders.php`.
- AC-03: `php tools/ai/validate-adapter-drift.php`; confirm frontmatter parses.
- AC-04: `composer test` (and `composer test:fast` for the ClaudeSettingsMergeTest and renderer tests during iteration).
- AC-05: run the three validators listed.
- AC-06: inspect trailing `## graphify` section presence in `AGENTS.md` / `CLAUDE.md` after any re-render.

## Risks And Rollback

- Risk: registering `optional-agents-claude-pack` when it does not exist or is not intended could add unwanted agents. Mitigation: verify existence and intent first (P0-1 gate); register only `adapter-claude` if the optional pack is absent/unintended.
- Risk: a full re-render could clobber the out-of-band `## graphify` section. Mitigation: check for the trailing section before/after and re-apply per `docs/ai/adapter-contract.md`.
- Rollback: revert `packs.php` and `core.php` template/generator edits; the merge logic was never changed, so reverting the pack registration returns to the prior (defective but stable) install shape.
- Success signal: re-install yields a `.claude/settings.json` with both permissions and graphify hooks, and Copilot verify line shows `composer test`.

## Handoff Notes

- Recommended next step: hand off to the implementer agent using OpenCode command: /implement (implementer means implementer agent handoff).
- Coordinate verification runs because several validators are dirty in-flight; run them for evidence but do not commit edits to them.
- Implementation session (20260706): investigated `tools/ai/install/packs.php`, `tools/ai/install/profiles.php`, and `tools/ai/install/core.php` first per the IMPROVEMENTS-section guidance before editing. Found `adapter-claude` already registered and wired, and found the `<TEST_PATH_GLOB>`/`<FRONTEND_PATH_GLOB>` `core.php` placeholder-map fix already present as an uncommitted change referencing this exact ticket ID — left it in place unmodified after verifying it meets AC-03/AC-11/AC-12. No edits were made to `packs.php`, `core.php`, `testing.instructions.md`, or `frontend.instructions.md` in this session; all required behavior was proven via isolated `/tmp` copies (never the real repo's protected `.claude/**`/`.github/**`/`AGENTS.md`/`CLAUDE.md`).
- Deferred (existing-install repair automation, IMPROVEMENT P0-1b/AC-07): the real repair mechanism already exists and was proven working — `php tools/ai/install-ai-kit.php --profile <original-profile> --adopt --force --allow-core-overwrite` correctly adds `adapter-claude` to an existing manifest and merges `.claude/settings.json` permissions without clobbering graphify hooks. What is genuinely missing is *automatic* profile rediscovery: a bare `php tools/ai/ai.php install` (no `--profile`) defaults to profile `dual` (`install_extras.php:296`) rather than reading the existing `.ai-install-manifest.json`'s `profile` field, and `upgrade` only reconciles files already present in the manifest (it never adds a missing pack's files). Building auto-profile-rediscovery or upgrade-adds-missing-packs would require editing `tools/ai/commands/install_extras.php` / `install_workflow.php`, which are outside this plan's file scope and change default-command behavior for all profiles — flagged as non-trivial/higher-risk per the plan's own stop condition, so it was not implemented. Recommend a small follow-up ticket scoped to those two files only.
- Deferred (project.yml override for `<TEST_PATH_GLOB>`/`<FRONTEND_PATH_GLOB>`, IMPROVEMENT AC-13): the existing `core.php` fix ships safe fixed defaults only (matching the original plan's "safe default OR wired placeholder" bar) and deliberately does not add new `project.yml` keys, per the smaller-blast-radius rationale already recorded in that diff's own comment. Wiring true `project.yml` override support would require adding new keys to `.ai/project.yml`/`project-yaml.php` and documentation (`PLACEHOLDERS.md`, `docs/ai/project-context-placeholders.md`), all outside this plan's assigned file list — left as a follow-up rather than widened scope.
- The real, currently-checked-in repo state (`.ai-install-manifest.json` missing `adapter-claude`; `.claude/settings.json` graphify-hooks-only with no `permissions`; `.github/copilot-instructions.md` verify line showing `unknown`) is an **un-reconciled operational state**, not a code defect — the generator now produces the correct output when run. Actually applying the repair (`--adopt` reinstall) to this real repo was intentionally not performed in this session because it would write to protected/generated paths (`.claude/**`, `.github/**`, `AGENTS.md`, `CLAUDE.md`) outside this plan's edit scope; that repair is a separate, explicit-approval operational action.
- AC-04 (`composer test` green): 2 pre-existing failures (`docs/ai/repo-required-tools.md` drift) are unrelated to any file in this plan's scope — see Acceptance Criteria above for detail.

## IMPROVEMENTS:

## Verdict

**Good plan, but it has one major blind spot: registering `adapter-claude` may fix fresh installs but not existing installs unless the installer reconciles the existing `.ai-install-manifest.json`.**

| Area                    |  Score |
| ----------------------- | -----: |
| Root-cause diagnosis    | 82/100 |
| Scope safety            | 88/100 |
| Claude enforcement fix  | 70/100 |
| Copilot placeholder fix | 84/100 |
| Verification strength   | 74/100 |
| Overall                 | 79/100 |

Main issue: the plan treats `packs.php` registration as sufficient, but the actual broken state is already installed. Existing manifests may continue omitting `adapter-claude`.

---

## Main criticisms

### 1. Pack registration may not repair existing installs

Problem statement says:

```text
adapter-claude is NOT in the installed .ai-install-manifest.json
```

Changing only:

```text
tools/ai/install/packs.php
```

may fix new installs, but not existing installs if the installer respects the current manifest.

Add this invariant:

```md
Existing-install repair invariant:
Registering `adapter-claude` is not enough. Re-install must reconcile an existing `.ai-install-manifest.json` that lacks required selected adapter packs, or the Claude merge/render step will still not run.
```

Add action:

```md
P0-1b: Verify whether re-install reads the existing `.ai-install-manifest.json` as authoritative. If yes, add a safe installer reconciliation path so selected/required adapter packs missing from older manifests are added on re-install without clobbering unrelated manifest state.
```

---

### 2. Define whether Claude is default, selected, or repair-only

The plan says “register `adapter-claude`,” but not whether it should be:

| Option                            | Meaning                                 |                              Risk |
| --------------------------------- | --------------------------------------- | --------------------------------: |
| Default pack                      | Always installed                        | May add Claude files unexpectedly |
| Selectable pack                   | Available only when selected            |   Existing install remains broken |
| Required if Claude surface exists | Repairs broken Claude installs          |                          Best fit |
| Repair-only migration             | Adds only when prior Claude files exist |         Safest for existing users |

Best contract:

```md
`adapter-claude` must be installed when the Claude adapter is selected or when existing `.claude/**` generated surfaces are present from a previous install. Do not install optional Claude agents unless the optional pack exists and is explicitly selected/intended.
```

---

### 3. The `unknown` verify line is probably not a source bug

The plan already says:

- template uses `<PRIMARY_VERIFY_COMMAND>`
- `.ai/project.yml` has `composer test`
- `core.php` overrides from project.yml

So P0-2 should not start by editing `core.php`.

Better:

```md
P0-2: Prove whether the Copilot verify-line issue is stale render only. Re-render from current sources and project.yml. Edit `core.php` only if the re-render still emits `unknown`.
```

This prevents unnecessary generator edits.

---

### 4. Placeholder defaults need clear precedence

For `<TEST_PATH_GLOB>` and `<FRONTEND_PATH_GLOB>`, define precedence:

```text
project.yml explicit value
→ installer default
→ never literal placeholder
→ never unknown
```

Add contract:

```md
Placeholder defaults must be used only when project.yml omits the key. Project-local values must override defaults.
```

---

### 5. Avoid broad `applyTo` defaults

Do not use `**/*` unless there is no safer valid option.

Better defaults:

```yaml
TEST_PATH_GLOB: "**/{test,tests,spec,__tests__}/**/*,**/*.{test,spec}.{js,jsx,ts,tsx},**/*Test.php"
FRONTEND_PATH_GLOB: "**/*.{js,jsx,ts,tsx,vue,css,scss,html}"
```

But only if Copilot `applyTo` supports comma-separated globs in your renderer/validator. If not, use the safest single glob:

```yaml
TEST_PATH_GLOB: "**/*Test.php"
FRONTEND_PATH_GLOB: "**/*.{js,jsx,ts,tsx,vue}"
```

Add AC:

```md
AC-X: The chosen `applyTo` values use the same glob syntax already accepted by existing Copilot instruction frontmatter.
```

---

## Corrected action plan

Replace the current Todo Plan with this stronger version:

```md
## Todo Plan

- [ ] P0-1: Register `adapter-claude` in `tools/ai/install/packs.php` as an installable adapter pack.
- [ ] P0-2: Verify whether existing installs with `.claude/**` but missing `adapter-claude` in `.ai-install-manifest.json` are repaired on re-install. If not, add a safe manifest reconciliation path for required/selected adapter packs.
- [ ] P0-3: Verify whether `optional-agents-claude-pack` exists and is intended. Register it only if it exists and is explicitly selected/intended; do not add optional Claude agents by default.
- [ ] P0-4: Prove whether the Copilot verify-line `unknown` issue is stale-render only. Re-render from current source and `.ai/project.yml`; edit `core.php` only if the re-render still emits `unknown`.
- [ ] P1-1: Add `<TEST_PATH_GLOB>` to the placeholder map with project.yml override support and a valid safe default.
- [ ] P1-2: Add `<FRONTEND_PATH_GLOB>` to the placeholder map with project.yml override support and a valid safe default.
- [ ] P1-3: Update `testing.instructions.md` and `frontend.instructions.md` only as needed so their `applyTo` frontmatter renders valid globs and never literal placeholders.
- [ ] P2-1: Reconcile the Claude agent-body enforcement wording only if, after P0, rendered Claude agents still claim enforcement that settings do not provide.
```

---

## Stronger acceptance criteria

Add these:

| AC    | Requirement                                                                                                                                      |
| ----- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| AC-07 | Existing install with `.claude/**` and missing `adapter-claude` is repaired on re-install or dry-run repair evidence proves it would be repaired |
| AC-08 | Fresh install includes `adapter-claude` only under the intended adapter-selection/default-pack policy                                            |
| AC-09 | `optional-agents-claude-pack` is not registered unless existence and intent are verified                                                         |
| AC-10 | Re-render proves whether Copilot `unknown` was stale-only before any `core.php` change                                                           |
| AC-11 | No rendered Copilot instruction contains literal `<TEST_PATH_GLOB>` or `<FRONTEND_PATH_GLOB>`                                                    |
| AC-12 | No rendered Copilot instruction contains `applyTo: unknown`                                                                                      |
| AC-13 | Placeholder precedence is project.yml value → installer default → fail validation; never literal placeholder                                     |
| AC-14 | Re-install preserves graphify hooks while adding Claude permissions                                                                              |
| AC-15 | No generated file is hand-edited                                                                                                                 |

---

## Better verification commands

Add these checks.

### Pack and manifest checks

```bash
rg -n "adapter-claude|optional-agents-claude" tools/ai/install/packs.php
rg -n "adapter-claude" .ai-install-manifest.json
```

After dry-run or re-install evidence:

```bash
rg -n '"permissions"|"allow"|"hooks"|graphify' .claude/settings.json
```

Expected:

```text
permissions present
graphify hooks still present
```

### Placeholder checks

```bash
php tools/ai/validate-placeholders.php
```

Then:

```bash
rg -n "<TEST_PATH_GLOB>|<FRONTEND_PATH_GLOB>|unknown" \
  .github/copilot-instructions.md \
  .github/instructions 2>/dev/null
```

Expected:

```text
no placeholder literals
no unknown on verify/applyTo lines
```

### Verify command check

```bash
rg -n "composer test|unknown" .github/copilot-instructions.md
```

Expected:

```text
verify line contains composer test
verify line does not contain unknown
```

### Frontmatter parse check

```bash
php tools/ai/validate-adapter-drift.php
```

Also add a targeted assertion if available:

```bash
rg -n "applyTo:" .github/instructions
```

Expected:

```text
valid concrete globs
no placeholder tokens
```

### Graphify preservation check

Before and after any render:

```bash
rg -n "^## graphify|graphify" AGENTS.md CLAUDE.md
git diff -- AGENTS.md CLAUDE.md
```

Expected:

```text
graphify section preserved
no unrelated AGENTS.md / CLAUDE.md replacement
```

---

## Fix the rollback section

Current rollback says reverting `packs.php` returns to defective stable shape. That is true, but incomplete if manifest reconciliation is added.

Use:

```md
Rollback:

- Revert `packs.php`, `core.php`, and any placeholder template edits.
- Revert any installer manifest-reconciliation change if added.
- Do not manually edit generated `.claude/settings.json` or `.github/**`.
- Re-run install/dry-run validation to confirm generated outputs return to prior shape.
```

---

## Implementation guardrails

Give implementer this:

```md
Implement only Project 1.

Do not hand-edit generated files:

- `.claude/**`
- `.github/**`
- `.opencode/**`
- `AGENTS.md`
- `CLAUDE.md`

Primary priority:

1. Ensure `adapter-claude` is registered and actually runs for existing installs that already have Claude surfaces.
2. Preserve graphify hooks while adding Claude permissions.
3. Fix Copilot placeholder literals for `<TEST_PATH_GLOB>` and `<FRONTEND_PATH_GLOB>`.
4. Prove the Copilot verify-line `unknown` issue is stale-render only before editing `core.php`.

Do not edit:

- `claude-settings-merge.php`
- `claude-agent-renderer.php`
  unless verification proves a residual mismatch after pack registration.

Do not register `optional-agents-claude-pack` unless it exists and is intentionally selected.
```

---

## Final posture

**Proceed after tightening the manifest-repair and stale-render parts.**

Revised risk:

| Version                                          |  Score |
| ------------------------------------------------ | -----: |
| Original plan                                    | 79/100 |
| With existing-manifest repair check              | 88/100 |
| With placeholder precedence + stale-render proof | 92/100 |

The most important change is this: **do not only register the Claude pack; prove that re-install repairs existing manifests that omitted it.**
