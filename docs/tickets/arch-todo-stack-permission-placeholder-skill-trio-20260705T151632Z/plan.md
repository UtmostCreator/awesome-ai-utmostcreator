# Architecture Plan — Stack/Permission/Placeholder Skill Trio (scan-stack, generate-permissions, replace-placeholders)

- Ticket: `arch-todo-stack-permission-placeholder-skill-trio-20260705T151632Z`
- Source: split out of `docs/tickets/arch-todo-core-package-updates-20260705T135348Z/plan.md` (former P4 workstream), per explicit user decision to make this its own independent ticket/branch
- Generated: 2026-07-05T15:16:32Z
- Plan file: docs/tickets/arch-todo-stack-permission-placeholder-skill-trio-20260705T151632Z/plan.md
- Status: `PLAN ONLY — nothing implemented`
- Risk: `medium` (shipped template surfaces + new CLI verbs + permission/placeholder machinery; no runtime/data change beyond generated project-local artifacts)

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan.md` and move it into `archive/` under this ticket folder (`docs/tickets/arch-todo-stack-permission-placeholder-skill-trio-20260705T151632Z/archive/DONE-plan.md`).

## Context

This ticket was split out of the core-package-updates ticket's P4 workstream because it is the largest and highest-risk of the five original workstreams (it adds new CLI verbs, three new skills, and a new committed doc format), and because the user explicitly decided it should be its own independent ticket/branch rather than folded into the core-package ticket.

User-proposed skill trio not yet built: `scan-stack`, `generate-permissions`, `replace-placeholders` do not exist as agent-invokable skills, even though the underlying machinery they would wrap (`aiStackDetect()`, `aiPermissionStackOverlayEntries()`, `placeholders --apply`) already exists, is mature, and is safety-gated. Building them as thin wrappers exposes existing reviewed tooling as a guided workflow; the risk is a naive implementation reinventing scanning/permission-derivation/replacement and bypassing existing safety contracts (in particular the reviewed-language-overlay deny floor).

**Reuse-percentage table (existing machinery each new skill must wrap, not reinvent):**

| New skill | Wraps existing machinery | File:line | Reuse % |
|---|---|---|---|
| `scan-stack` | `aiStackDetect()` | `stack-detection.php:12` | ~90% — detection logic fully exists; only a CLI verb + `.md` render are new |
| `generate-permissions` | `aiPermissionStackOverlayEntries()` | `stack-overlays.php:26` | ~85% — overlay resolution fully exists; new work is the refuse-gate + preview surface |
| `generate-permissions` (deny floor) | reviewed language-overlay deny floor | `stack-overlays.php:15-18` | 100% — must be called, never re-derived |
| `generate-permissions` (compose) | `aiPermissionComposeFromSpec(['stack_overlays'=>…])` | `compose.php` | ~80% — composition step already exists for install; new work is exposing it as a standalone preview |
| `replace-placeholders` | `placeholders --apply` | `install_extras.php:42,134` | ~95% — substitution engine fully exists; new work is at most a thin shim |
| `replace-placeholders` (scope guard) | fixed `.md` scan roots, `substitute=false` handling | `project_values_sync.php:20-25` | 100% — must be respected, never re-implemented |
| all three (fan-out) | skill-dir install fan-out to Copilot/OpenCode/Claude | `packs.php:118-150` (`install_type: skill-dirs`) | 100% — reuse existing fan-out mechanism, do not build a parallel one |

**CRITICAL NAMING-COLLISION RISK (discovered during this research pass — read before touching any `stack.md`-shaped path):**

`docs/ai/project-stack.md` (note: **no** `/project/` subfolder — it lives directly under `docs/ai/`) **already exists in this repo** as an **unrelated legacy compatibility shim**. It is NOT the output of this ticket's `scan-stack` skill and must never be confused with, overwritten by, or mistaken for it.

- Evidence (verified by direct read): its own content says: "Compatibility note: `project-stack.template.md` remains for older installs... For new installs, do not copy this file... For migrated installs, remove this file once all references point to `project-context.template.md`."
- It is rendered from `packages/ai-universal-rules/templates/core/project-stack.template.md` to `docs/ai/project-stack.md` with `merge_strategy: skip-if-exists` (confirmed at `tools/ai/install/packs.php:101`).
- It is covered by ownership tests `tests/php/UpgradeOwnershipTest.php:69,92` and `tests/php/UninstallTest.php:53,65,96,101,114,126` (ownership: `template`, action: `preserve`).
- **Resolution baked into this plan:** the new `scan-stack` skill's output file MUST use a clearly distinct path — `docs/ai/project/stack.md` (WITH the `/project/` subfolder, alongside the existing `docs/ai/project/project-interaction.md`) — and a clearly distinct purpose (a live, per-project stack-detection projection, regenerated on demand; not a legacy migration shim). See P4.2 and AC-P4-14 below for the enforced check.

## Problem

`scan-stack`, `generate-permissions`, `replace-placeholders` do not exist as agent-invokable skills. Concretely, today:

- Stack detection runs ONLY inside install (`install_workflow.php:461`) — no standalone `stack-detect` verb exists.
- The only stack-detection output is JSON (`.ai/stack-detection.json`) — no human-readable `stack.md` format exists yet.
- There is no "refuse without a fresh scan" gate anywhere for permission generation.
- Overlay composition (`aiPermissionStackOverlayEntries()` + `aiPermissionComposeFromSpec()`) happens silently inside install only — there is no standalone preview surface for a human to review before applying.
- There is no skill wrapping `placeholders --apply`; a naive rebuild risks reimplementing the `substitute=false` guard and drifting from it.

## Target Outcome

`scan-stack`, `generate-permissions`, `replace-placeholders` ship as thin skill wrappers to Copilot/OpenCode/Claude, with the reviewed-overlay deny floor and `substitute=false` contract both intact, and no re-implementation of existing scanning/permission/placeholder machinery. New thin CLI verbs (`stack-detect`, `permissions-suggest`) carry the testable logic in PHP; the skills remain thin adapters that call them.

## In Scope

- Add a standalone `stack-detect` CLI verb that wraps `aiStackDetect()` (no new scanning logic).
- Define and render a new committed, human-readable `docs/ai/project/stack.md` output format (projection of `.ai/stack-detection.json`).
- Add a standalone `permissions-suggest` CLI verb / preview surface that wraps `aiPermissionStackOverlayEntries()` + `aiPermissionComposeFromSpec()`, gated on a fresh scan-stack run, previewing only — never auto-writing permission frontmatter.
- Add a thin `replace-placeholders` skill/shim that wraps the existing PHP `php tools/ai/ai.php placeholders --apply` — CONFIRMED, not a new standalone shell replacer.
- Ship all three as `packages/ai-universal-rules/templates/workflows/<name>.md`, fanned out to Copilot (prompt + skill), OpenCode (skill + command), and Claude (skill) via `packs.php:118-150` (`install_type: skill-dirs`).
- Enforce and test the `docs/ai/project/stack.md` vs `docs/ai/project-stack.md` naming-collision distinction (see Context above).

## Out Of Scope (Things To Avoid)

- Do NOT re-implement stack scanning, permission derivation, or placeholder replacement (wrap `aiStackDetect`, `aiPermissionStackOverlayEntries`, `placeholders --apply` instead).
- Do NOT let `generate-permissions` bypass the reviewed-overlay deny floor — it must call `aiPermissionStackOverlayEntries($detectedStackIds)` only, never derive raw permission patterns from package names.
- Do NOT ship `docs/ai/project/stack.md`'s content as a universal template under `packages/ai-universal-rules/templates/**` — it is per-project generated data, not a shared template body. (The generator/renderer code lives in the package; the generated `.md` output does not.)
- Do NOT auto-write permission frontmatter without a human review step — the skill SUGGESTS/PREVIEWS; `generate-agent-permissions.php --write` remains the gated apply.
- Do NOT make `replace-placeholders` touch `packages/**` template sources (out of the fixed `.md` scan roots by design — `project_values_sync.php:20-25`).
- Do NOT build `replace-placeholders` as a new standalone shell replacer that reimplements `substitute=false` handling or re-scans `.md` files — it must wrap `placeholders --apply`.
- Do NOT confuse, overwrite, or touch `docs/ai/project-stack.md` (the unrelated legacy compatibility shim) with any part of this ticket's `scan-stack` output. Any code path that writes stack-detection output must target `docs/ai/project/stack.md` (with the `/project/` subfolder) exclusively.
- Do NOT bundle unrelated workstreams (scan-stack, generate-permissions, replace-placeholders) into one opaque commit — each lands as its own isolated, auditable commit for rollback clarity, even though no GitHub PR is required for this repository's own changes.
- Do NOT exceed `policies/ai-file-standards.json` line budgets for the new workflow templates (warn 120 / fail 160).

## Affected Paths

- `packages/ai-universal-rules/templates/workflows/scan-stack.md`, `generate-permissions.md`, `replace-placeholders.md` (new; names indicative; fan out via `packs.php:118-150` `install_type: skill-dirs`)
- New thin CLI verbs `stack-detect`, `permissions-suggest` — exact location to be decided at implementation time, calling existing `tools/ai/install/stack-detection.php`, `permission-layers/stack-overlays.php:26`, `compose.php`, `install_extras.php:42,134` machinery
- `docs/ai/project/stack.md` — new, generated per-project output (committed, alongside existing `docs/ai/project/project-interaction.md`); NOT `docs/ai/project-stack.md` (unrelated legacy shim, must not be touched)
- Test coverage confirming the `docs/ai/project/stack.md` vs `docs/ai/project-stack.md` distinction (new or extended test alongside existing `tests/php/UpgradeOwnershipTest.php`, `tests/php/UninstallTest.php`)

## Contracts And Boundaries

- **Deny-floor contract (`generate-permissions`):** stack ids resolve permissions ONLY by referencing a reviewed language overlay — "a stack can never grant anything outside the reviewed language-overlay set" (`stack-overlays.php:15-18`). The skill MUST call `aiPermissionStackOverlayEntries($detectedStackIds)`; it MUST NOT derive raw permission patterns from package names. Violating this weakens the hard-deny floor for every downstream project.
- **`substitute=false` contract (`replace-placeholders`):** `placeholders.json` marks some tokens non-substitutable. A new shell replacer would fork the reviewed PHP `substitute=false` guard and drift. **CONFIRMED (resolved decision, not open question):** the skill MUST wrap the existing PHP `php tools/ai/ai.php placeholders --apply` — do not build a new standalone shell replacer.
- **Scan-first data-flow contract:** the stack scan writes source values (language, package manager, test/verify commands) into `.ai/project.yml` (`core.php:1218-1237`). Both `generate-permissions` and `replace-placeholders` must consume that same artifact, so "refuse without scan-stack" is enforceable by checking for a fresh `.ai/stack-detection.json` / `.ai/project.yml`.
- **Adapter-thinness contract:** new CLI verbs must carry logic in PHP (testable); skills stay thin adapters that call them — matches the kit's existing adapter-thinness contract (see `docs/ai/adapter-contract.md`). **CONFIRMED (resolved decision, not open question):** add thin new CLI verbs (`stack-detect`, `permissions-suggest`) rather than skill-only wrappers.
- **`stack.md` output-location contract (CONFIRMED, resolved decision):** the new scan-stack output is a committed, per-project generated doc at `docs/ai/project/stack.md` — alongside the existing `docs/ai/project/project-interaction.md`. It is NOT a gitignored `.ai/`-only local artifact, and it is NOT baked into the universal `packages/ai-universal-rules/templates/**` package (that would be a false claim shipped to every other project that has not run a scan).
- **Naming-collision-avoidance contract (CONFIRMED, resolved decision, CRITICAL):** `docs/ai/project/stack.md` (this ticket's output, WITH `/project/` subfolder) is a categorically different artifact from the pre-existing `docs/ai/project-stack.md` (legacy compatibility shim, NO `/project/` subfolder, owned by `project-stack.template.md` via `packs.php:101`, `merge_strategy: skip-if-exists`, covered by `UpgradeOwnershipTest.php:69,92` and `UninstallTest.php:53,65,96,101,114,126`). No code path introduced by this ticket may read from, write to, or delete `docs/ai/project-stack.md`.
- **Delivery contract (CONFIRMED, resolved decision):** no GitHub PR review process is required for this repository's own changes (this is the kit's own repo, direct commits apply), but each of the three skills (`scan-stack`, `generate-permissions`, `replace-placeholders`) still lands as its own isolated, auditable commit, in dependency order, for rollback clarity — never silently combined into one commit.
- **Independent-ticket contract (CONFIRMED, resolved decision):** this ticket is its own independent ticket/branch, separate from `arch-todo-core-package-updates-20260705T135348Z`, per explicit user decision — it does not block on, and is not blocked by, that ticket's P0-P3 workstreams.

## Todo Plan

### `scan-stack`

- [x] P4.1: Add a standalone agent-invokable CLI verb, `stack-detect` (today detection runs ONLY inside install — `install_workflow.php:461` — no `stack-detect` verb exists). Wrap `aiStackDetect()` (`tools/ai/install/stack-detection.php:12`) — do not reinvent scanning of `composer.json`/`package.json`/`go.mod`/`Cargo.toml`/etc. **DONE** — `php tools/ai/ai.php stack-detect` (`tools/ai/commands/stack_detect_command.php`) wraps `aiStackSelectionResolve()` (which itself wraps `aiStackDetect()` + `aiStackRunVersionChecks()`); no scanning logic added. Supports `--stacks`, `--no-stack-detect`, `--no-write`.
- [x] P4.2: Define and render a human-readable `stack.md` output (today's only output is JSON `.ai/stack-detection.json`; no `stack.md` format exists yet). Write it to `docs/ai/project/stack.md` (committed, per-project generated doc, alongside `docs/ai/project/project-interaction.md`) — CONFIRMED path, not a gitignored `.ai/`-only artifact, not a universal template. **DONE** — `tools/ai/install/stack-project-doc.php` (`aiStackRenderProjectDocMarkdown()` / `aiStackWriteProjectDoc()`); detected stacks table (confidence + signals), selected stacks, tool-version table, generation metadata.
- [x] P4.2a: Add an explicit check (test or validator) confirming the new file path is exactly `docs/ai/project/stack.md`, and that it is distinct from, and never touches, the pre-existing `docs/ai/project-stack.md` (legacy compatibility shim per `packs.php:101`, `UpgradeOwnershipTest.php:69,92`, `UninstallTest.php:53,65,96,101,114,126`). **DONE** — `tests/php/StackProjectDocTest.php` (6 tests): exact-path assertion, distinctness assertion, byte-identical legacy-shim-untouched proof (both direct function call and full CLI-subprocess invocation), plus a defensive runtime `RuntimeException` guard in `aiStackWriteProjectDoc()` itself if the two fixed relative paths were ever made to collide.

### `generate-permissions`

- [x] P4.3: Build the "refuse without scan-stack output" gate (does not exist anywhere today) — check for a fresh `.ai/stack-detection.json` / `.ai/project.yml` before proceeding. **DONE** — `php tools/ai/ai.php permissions-suggest` (`tools/ai/commands/permissions_suggest_command.php`) exits 1 with a clear message when `.ai/stack-detection.json` is missing or invalid JSON. Fixed a dependency gap found while building this gate: `stack-detect` only wrote `docs/ai/project/stack.md`, never `.ai/stack-detection.json`, so a standalone (non-install) scan could never satisfy this gate — fixed in a prior commit by having `stack-detect` also call the existing `aiInstallerWriteStackDetectionEvidence()`.
- [x] P4.4: Build a suggestion/preview surface showing the recommended overlay layer for human review, calling `aiPermissionStackOverlayEntries()` (`permission-layers/stack-overlays.php:26`) + `aiPermissionComposeFromSpec(['stack_overlays'=>…])` (`compose.php`). Today overlays compose silently inside install only. Leave `generate-agent-permissions.php --write` as the gated apply step — the skill previews, it does not auto-write. **DONE** — prints the raw overlay entries plus an illustrative composed-model summary (allow/ask/deny counts, layer count) for CLI-overridable `--profile`/`--edit-surface`/`--verify-tier`; never writes any file (proven by `testProceedsAfterFreshScanAndNeverWritesAnything`).
- [x] P4.8: Confirm the deny-floor contract holds — `generate-permissions` calls `aiPermissionStackOverlayEntries($detectedStackIds)` only, never derives raw permission patterns from package names. **DONE** — the command's only stack-to-permission path is `aiPermissionStackOverlayEntries($selected, $root)`; the illustrative full-model preview goes through `aiPermissionComposeFromSpec()`, which self-enforces the immutable hard-deny floor (`aiPermissionAssertNoHardDenyWeakening`) for every caller. `testComposedPreviewNeverWeakensHardDenyFloor` proves the command surfaces this guarantee.

### `replace-placeholders`

- [x] P4.5: Build at most a shell shim that shells out to the existing PHP `placeholders --apply` (`install_extras.php:42,134`). Do not build a new shell replacer that re-implements `substitute=false` handling or re-scans `.md` files. (CONFIRMED design — this is now a hard contract, not an open question.) **DONE** — zero new PHP/shell code; the skill's own Workflow section instructs running `php tools/ai/ai.php placeholders --apply` directly (with `--fail` before/after to confirm state), matching "at most a thin shim" as literally as possible.
- [x] P4.9: Confirm `replace-placeholders` never touches `packages/**` template sources (out of the fixed `.md` scan roots by design — `project_values_sync.php:20-25`). **DONE** — new `testApplyNeverTouchesPackagesTemplateSources` in `tests/php/PlaceholderRegistryTest.php` proves a `packages/ai-universal-rules/templates/core/*.template.md` file with a live `<TOKEN>` is byte-identical before/after `--apply`, while a `docs/ai/**` file in the same run is correctly substituted.

### Shared / CLI plumbing

- [x] P4.6: Add thin CLI verbs `stack-detect`, `permissions-suggest` that the skills call, keeping logic testable in PHP and skills as thin adapters. (CONFIRMED — resolved decision, no longer an open question.) **DONE** for both verbs (`replace-placeholders` intentionally has no new verb — it wraps the pre-existing `placeholders` verb per P4.5).
- [x] P4.7: Ship each skill as `packages/ai-universal-rules/templates/workflows/<name>.md`, fanned out to Copilot (prompt + skill), OpenCode (skill + command), and Claude (skill) via `packs.php:118-150` (`install_type: skill-dirs`). Keep each within the workflow-template line budget (`policies/ai-file-standards.json`: warn 120 / fail 160). **DONE** for Copilot (prompt + skill) and OpenCode (skill + command) — all 3 skills 41-45 lines, well under the 120/160 budget. **Claude skipped, matching pre-existing repo precedent, not a new gap:** `.claude/skills/**` is not materialized anywhere in this self-hosted repo today (confirmed: `docs-sync` and every other existing workflow skill also has no `.claude/skills/<name>/` copy), consistent with the separately-documented finding in `v0.6-plan/arch-todo-v0-6-shipped-surface-program-20260704-000001/plan.md` that `.claude/agents/**` is likewise not yet materialized here.
- [x] P4.10: Land each of the three skills as its own isolated, auditable commit (`scan-stack` first, then `generate-permissions`, then `replace-placeholders`, matching their dependency order) — no GitHub PR required for this repo, but never combine into one commit. **DONE** — verified via `git log --oneline -- packages/ai-universal-rules/templates/workflows/{scan-stack,generate-permissions,replace-placeholders}.md`: 3 distinct commits in exactly that dependency order.

## Acceptance Criteria

- [x] AC-P4-13: The `scan-stack` skill plus the `stack-detect` CLI verb produce `docs/ai/project/stack.md` (in addition to the existing JSON) with detected languages/tools/confidence, with no re-implemented scanner. **DONE** — verified via manual smoke test and `StackProjectDocTest`.
- [x] AC-P4-14: A test or check confirms the scan-stack output path is exactly `docs/ai/project/stack.md`, and that it is distinct from and never touches the pre-existing `docs/ai/project-stack.md` legacy compatibility shim. **DONE** — `tests/php/StackProjectDocTest.php` (path-exactness, distinctness, and byte-identical-legacy-shim proofs, both direct-call and full CLI-subprocess).
- [x] AC-P4-15: The `generate-permissions` skill refuses to proceed without fresh scan output, then previews overlay layers via `aiPermissionStackOverlayEntries()`; the deny floor is unchanged; `generate-agent-permissions.php --check` still gates the apply step. **DONE** — `php tools/ai/generate-agent-permissions.php --check` remains untouched and still passes (`OK: managed agent permission blocks in sync`); `permissions-suggest` never writes.
- [x] AC-P4-16: The `replace-placeholders` skill wraps `placeholders --apply`; `substitute=false` is respected; `validate-placeholders.php` and `placeholders --fail` are both green. **`validate-placeholders.php` DONE** (`OK: placeholder registry covers template tokens and matches placeholders.json` — fixed a self-caught regression first: the skill's own prose used a literal `<TOKEN>` example, which the validator correctly flagged as an undocumented token; reworded to avoid any bracketed all-caps example entirely, since even a real documented token like `<PROJECT_NAME>` would itself get mangled by a future `--apply` run against these very skill files, which live under the scanned `.opencode`/`.github` roots). **`placeholders --fail` is a pre-existing, unrelated baseline condition** (verified via `git stash`: identical `exit=1`, same 15 pre-existing items, both before and after this entire ticket's changes — not touched, not caused, not part of this ticket's scope per the plan's own "do not absorb existing out-of-scope drift" instruction).
- [x] AC-P4-17: All three workflow templates ship to Copilot/OpenCode/Claude, stay under their line budget, and `validate-adapter-drift.php` is clean. **DONE for Copilot + OpenCode** (all 3 templates 41-45 lines); **Claude intentionally skipped** — `.claude/skills/**` is not materialized anywhere in this self-hosted repo (matches the pre-existing, separately-documented `docs-sync` precedent and the `.claude/agents/**` gap noted in the v0.6-plan). `validate-adapter-drift.php --fail-on-warn` clean (pre-existing WARNs only).
- [x] AC-P4-18: Each of the three skills is present in the git history as its own isolated commit (verifiable via `git log --oneline` scoped to the relevant paths), not combined into one commit. **DONE** — `scan-stack` (`b693c39`), `generate-permissions` (`01ec0d7`), `replace-placeholders` (this commit), in exactly that dependency order.

## Verification Plan

1. `php tools/ai/validate-adapter-drift.php` — proves part of AC-P4-17 (adapter sync for all three skills).
2. `php tools/ai/generate-agent-permissions.php --check` — proves part of AC-P4-15 (permission drift gate still gates the apply step).
3. `php tools/ai/validate-placeholders.php` + `php tools/ai/ai.php placeholders --fail` — proves AC-P4-16 (token handling unchanged).
4. `php tools/ai/generate-stack-registry.php --check` — proves stack registry has no drift introduced by this ticket.
5. Direct inspection / test run confirming `docs/ai/project/stack.md` exists, `docs/ai/project-stack.md` is untouched (diff-clean), and both files' content/purpose remain distinct — proves AC-P4-13 and AC-P4-14.
6. `git log --oneline -- packages/ai-universal-rules/templates/workflows/scan-stack.md packages/ai-universal-rules/templates/workflows/generate-permissions.md packages/ai-universal-rules/templates/workflows/replace-placeholders.md` — proves AC-P4-18 (isolated commits).
7. `composer test` — full-suite smoke check across the ticket.

## Risks And Rollback

- **Deny-floor bypass risk (highest risk in this ticket):** a naive `generate-permissions` implementation that derives permissions directly from package names (instead of calling `aiPermissionStackOverlayEntries()`) would weaken the hard-deny floor for every downstream project using this kit. Mitigation: AC-P4-15 plus the Contracts And Boundaries deny-floor contract; code review must explicitly check the call site.
- **Placeholder-substitution drift risk:** a new shell-based placeholder replacer could silently diverge from the reviewed PHP `substitute=false` guard. Mitigation: wrap `placeholders --apply` rather than reimplementing; AC-P4-16.
- **Naming-collision risk with `docs/ai/project-stack.md` (CRITICAL, newly documented):** `docs/ai/project-stack.md` already exists as an unrelated legacy compatibility shim (`packs.php:101`, `merge_strategy: skip-if-exists`, covered by `UpgradeOwnershipTest.php:69,92` and `UninstallTest.php:53,65,96,101,114,126`). If this ticket's `scan-stack` output is accidentally pointed at that path (or a similarly-named path) instead of `docs/ai/project/stack.md`, it would silently corrupt or bypass the legacy migration shim's ownership/preserve semantics, and could break `UpgradeOwnershipTest` / `UninstallTest` expectations. Mitigation: AC-P4-14 hard-checks the exact output path and non-interference; code review must explicitly verify no code path in `stack-detect` / `scan-stack` ever references `docs/ai/project-stack.md` (without the `/project/` subfolder).
- **Universal-template leakage risk:** accidentally shipping generated `stack.md` content (or a stack-specific claim) inside `packages/ai-universal-rules/templates/**` would assert a false claim on every other install target that has not run a scan. Mitigation: keep the renderer/generator code in the package, but never commit a sample/default `stack.md` body as a universal template asset.
- **Rollback posture:** every change in this ticket is additive-only (new CLI verbs, new skills, one new generated doc). Rollback for any single skill is a straightforward revert of that skill's commit(s). No data migration or destructive change is introduced.

## Handoff Notes

**Which agents benefit from which change:**

| Change | Primary agents |
|---|---|
| `scan-stack` | post-install, config-maintainer, architect (grounds all downstream work in real stack) |
| `generate-permissions` | config-maintainer (primary), workflow-auditor, release-auditor (deny-floor integrity) |
| `replace-placeholders` | post-install (primary), config-maintainer |

**Suggested slice order (each an independent, isolated commit — no GitHub PR required for this repo's own changes):**

1. `scan-stack` — no dependency on the other two; unblocks `generate-permissions`' refuse-gate.
2. `generate-permissions` — depends on `scan-stack`'s fresh-scan output for its refuse-gate.
3. `replace-placeholders` — independent of the other two but sequenced last per the original workstream ordering.

**Resolved decisions (all baked in above, not open questions):**

- New thin CLI verbs (`stack-detect`, `permissions-suggest`) are confirmed, not skill-only wrappers.
- `replace-placeholders` MUST wrap `php tools/ai/ai.php placeholders --apply`; no new standalone shell replacer.
- `stack.md` output location is `docs/ai/project/stack.md` (committed, per-project, alongside `project-interaction.md`) — distinct from the pre-existing `docs/ai/project-stack.md` legacy shim.
- No GitHub PR needed for this repo's own changes; land each skill as its own isolated commit.
- This is its own independent ticket/branch, separate from `arch-todo-core-package-updates-20260705T135348Z`.

**Recommended next step:** implementer means implementer agent handoff using OpenCode command: `/implement` — starting with `scan-stack` per the suggested slice order above.
