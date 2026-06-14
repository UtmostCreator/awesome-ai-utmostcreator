> **SUPERSEDED — DO NOT IMPLEMENT FROM THIS FILE (kept for history; not deleted).**
> This mega-plan is now DECOMPOSED into bounded, separately-reviewed plans. Its scope
> is split across the following; implement from those, not from here:
>
> - Plan A — `docs/tickets/arch-todo-land-scripts-ai-reorg-20260614-104813/plan.md` (rank 00, BLOCKING baseline; supersedes Phase 0)
> - Plan B — `docs/tickets/arch-todo-shipping-surface-20260614-104814/plan.md` (items 05/10/15/20/25; supersedes Phases 1–2)
> - Plan C — `docs/tickets/arch-todo-doc-surface-hardening-20260614-104815/plan.md` (items 45/40/75/80/85/30; supersedes Phases 3/5/6 doc-scoped)
> - Plan D — `docs/tickets/arch-todo-agent-score-frontmatter-20260614-104816/plan.md` (item 35)
> - Backlog index G — `docs/tickets/arch-todo-backlog-deferred-program-20260614-104819/plan.md` (deferred items 90/95/110/115/120/125 + DEFERRED Plans E/F; DROPPED items 130 + 70)
>
> Notes on this decomposition (binding user decisions):
> - Items 50/55/60/65 are ALREADY DONE in the working tree — Plan A verifies them green; not re-implemented.
> - Item 130 (docs/ai sectional reorg) and item 70 (repo-tool-inventory --security + validate-machine-output.sh) are DROPPED — see backlog index G.
> - agent_score uses the FULL rubric (role_clarity, scope_control, permission_safety, output_contract, evidence_required, verification_strength, handoff_quality -> score + confidence + decision).

# Architecture Plan — Repo Cleanup & Shipping-Surface Hardening

- Ticket: none
- Source: architect design handoff (corrected supersede of todo-repo-review.md)
- Generated: 20260614-101701
- Plan folder: docs/tickets/arch-todo-repo-cleanup-shipping-surface-20260614-101701/

> This plan SUPERSEDES the stale root file `todo-repo-review.md` (a P0-P11 plan).
> Research, reviewer, and architect found ~7 of 12 original priorities stale,
> already-done, or operationally broken. Original command shapes (`git mv`,
> `git rm`, "untrack advisor-context", "create .gitattributes",
> `run-full-install-validation.php`) are corrected or dropped below. The
> superseded file is archived in Phase 1, not edited in place.

## Context

Ground truth verified before writing this plan (repo wrappers / git plumbing, not raw grep/find/cat):

- Branch `main` (`git branch --show-current`).
- Worktree is VERY dirty with a large IN-FLIGHT uncommitted reorg of `scripts/ai/` (`git status --short`):
  - new role/risk shims under `scripts/ai/bin/{read,verify,context,edit,hooks,admin}/`
  - renames `scripts/ai/lib/* -> scripts/ai/internal/lib/*`
  - renames `scripts/ai/ai-search/* -> scripts/ai/internal/search/*`
  - deletion of `scripts/ai/lib/60-exec-guard.sh` (moved) and heavy edits to `pre-tool-use.sh`
  - new tests `tests/php/AgentsManifestTest.php`, `tests/php/AiScriptAccessManifestTest.php`, `tests/php/ScriptsAiManifestTest.php`
  - new untracked `.github/ai-script-access.yaml`, `docs/ai/AGENTS-MANIFEST.md`, `tools/ai/validate-script-access.php`
- `docs/ai/generated/` is gitignored AND has 0 tracked files (`git ls-files docs/ai/generated/` -> empty). `advisor-context.md` (~2MB) exists untracked. So "untrack giant generated file" is ALREADY DONE — do not re-add it.
- Root planning docs (`todo-repo-review.md`, `todo-agents-rework.md`, `todo-agents-script-rework.md`, `todo-introspect-improvement.md`, `todo-scripts-refactor.md`, `todo-scripts-improvement.md`, plus `improvement-plan.md`, `readme-install.md`, `ai-search-todo-tests.md`) are UNTRACKED -> `git mv` fails, use plain `mv`.
- `docs/ai/package/**` exists, UNTRACKED, and DRIFTS from `packages/ai-universal-rules/docs/**` (per handoff: BROWSE.md 32842 vs 32991; INSTALL-CATALOG 3896 vs 4059) -> `git rm` fails, use plain `rm` after per-file diff.
- `.opencode/agents/*.md` and `.github/agents/*.agent.md` start with YAML frontmatter and lack provenance. Repo convention for generated docs is the comment `<!-- GENERATED — DO NOT EDIT: rendered by ai-kit installer from <template> -->`, but agent files carry frontmatter, so provenance must go INSIDE frontmatter.
- Named 3-line placeholder docs (`approval-boundaries.md`, `ownership.md`, `hooks.md`, `failure-handling.md`, `session-reentry.md`, `agent-ops.md`, `tool-policy.md`, `command-policy.md`, `handoff-contract.md`, `architecture-locks.md`) are referenced as canonical from `execution-protocol.md`, `agents.md`, `source-of-truth.md`, capabilities, and `docs/ai/installed-files.md` -> HIGH blast radius.
- `repomix-scc-router.sh` is still 967 lines at its original path; a 30-line generated shim was added under `bin/context/` -> any P9-style refactor conflicts with the in-flight reorg.
- `tools/ai/install/core.php` is ~2012 lines (valid refactor target). `run-full-install-validation.php` is MISSING (drop that row). `validate-install-surface.php` and `verify-full-install.php` exist.
- `.gitattributes` EXISTS (eol rules only, no `export-ignore`) -> append, do not recreate.

## Problem

The original `todo-repo-review.md` is stale and partly operationally wrong: it directs `git mv`/`git rm` on untracked files (which fail), re-does already-done work (advisor-context untrack), recreates an existing `.gitattributes`, references a missing validation script, and sequences a shell refactor that collides with the uncommitted reorg. Acting on it would produce failed commands, irreversible deletions of untracked content, and a broken worktree. A corrected, evidence-anchored, phase-gated plan is needed that lands the in-flight reorg first and applies the right command shapes per file's tracked status.

## Target Outcome

A corrected, phase-ordered cleanup and shipping-surface hardening plan that:

- lands the in-flight `scripts/ai` reorg as a committed baseline before any dependent refactor,
- uses command shapes matched to each file's tracked/untracked status,
- protects high-blast-radius canonical/placeholder docs via reference maps and expand-in-place,
- removes only verified-redundant content with diff + sweep + backup,
- and proves each phase with repo wrappers and existing validators.

## In Scope

- Supersede `todo-repo-review.md` with this single corrected Todo plan and archive the stale file.
- Phase 0: land/commit the in-flight `scripts/ai` reorg (blocking baseline).
- Phase 1: command-corrected low-risk cleanups (source-only SCC exclusion reframe; archive untracked root planning docs via plain `mv`; append `export-ignore` to existing `.gitattributes`).
- Phase 2: shipping-surface policy doc + canonical exclude-dir lists + ship-audit script + GENERATED convention on truly generated docs.
- Phase 3: reference-mapped, high-blast-radius work (frontmatter provenance; remove the `docs/ai/package` mirror after diff+sweep+backup; expand-in-place placeholder docs).
- Phase 4 (deferred): docs/ai sectional reorg only after a full reference map exists.
- Phase 5: capability/skill projection dedup ANALYSIS only.
- Phase 6 (gated): `just verify-surface` after referenced scripts exist; pick ONE canonical `check-file-refs.sh`; then shell refactor (post-Phase-0 only); then `core.php` PHP refactor.

## Out Of Scope (Things To Avoid)

- Do NOT start the P9-style shell refactor before Phase 0 lands.
- Do NOT bulk-move or delete placeholder or canonical docs without a reference map; prefer expand-in-place.
- Do NOT use `git mv` / `git rm` on untracked files (they will fail); use plain `mv` / `rm`.
- Do NOT place provenance comments above the YAML frontmatter of agent files; provenance goes INSIDE the frontmatter.
- Do NOT use raw `grep` / `find` / `cat` for verification; use `scripts/ai/ai-search.sh`, `scripts/ai/bin/**` wrappers, and `scripts/ai/preview-file.sh`.
- Do NOT delete `docs/ai/package` without per-file diff + reference sweep + tar backup (untracked = no git recovery).
- Do NOT re-add the already-done "untrack `docs/ai/generated/advisor-context.md`" step (gitignored + 0 tracked).
- Do NOT add a `run-full-install-validation.php` verification row (file is missing).
- Do NOT recreate `.gitattributes`; append to it.
- Do NOT widen scope beyond cleanup + shipping-surface hardening; new features, unrelated refactors, and other tickets are out of scope.

## Affected Paths

- `todo-repo-review.md` (and sibling untracked root `todo-*.md` / plan docs) — archived via plain `mv` in Phase 1.
- `.gitattributes` — appended with `export-ignore` rules (Phase 1).
- `scripts/ai/**` (in-flight reorg) — committed in Phase 0; shell refactor only in Phase 6.
- `scripts/ai/internal/config/exclude-dirs.txt`, `scripts/ai/internal/config/source-exclude-dirs.txt` — new canonical exclude lists (Phase 2).
- `docs/ai/architecture/shipping-surface.md` — new policy doc (Phase 2).
- `.opencode/agents/*.md`, `.github/agents/*.agent.md` — frontmatter provenance (Phase 3.1).
- `docs/ai/package/**` — removed after diff+sweep+backup; canonical recorded in `source-of-truth.md` (Phase 3.2).
- `docs/ai/{approval-boundaries,ownership,hooks,failure-handling,session-reentry,agent-ops,tool-policy,command-policy,handoff-contract,architecture-locks}.md` — expand-in-place (Phase 3.3).
- `tools/ai/install/core.php` — PHP refactor target (Phase 6).
- `justfile` — `verify-surface` recipe (Phase 6).
- `docs/tickets/archive/root-cleanup-<date>/` — archive destination (Phase 1).
- `docs/tickets/...` (this folder) — durable planning output.

## Contracts And Boundaries

- The committed `scripts/ai` reorg is the baseline contract for all later phases; nothing dependent runs until it lands.
- Tracked vs untracked status decides the command: tracked -> `git mv`/`git rm`; untracked -> plain `mv`/`rm`. All targets named here are untracked unless proven otherwise with `git ls-files --error-unmatch`.
- `packages/ai-universal-rules/docs/**` is the canonical docs source; `docs/ai/package/**` is a drifting mirror to be removed (decision recorded in `source-of-truth.md`).
- Agent file provenance is a frontmatter KEY (e.g. `source:` / `generated_from:`), validated by `validate-ai-config.php` + `validate-adapter-drift.php`; verify ONE file before bulk.
- Placeholder docs are canonical references with high blast radius; expand-in-place preserves existing reference paths.
- `ship-audit.sh` has limited value while `docs/ai/generated/` is untracked — record this caveat, do not over-promise.
- Unknown: exact reference graph for placeholder/canonical docs until a reference map is produced in Phase 3/4.

## Todo Plan

- [ ] P0: Run `git status --short` and `git diff --name-status --find-renames HEAD` to snapshot the in-flight reorg before landing it.
- [ ] P0: Run `bash scripts/ai/bin/verify/check-file-refs.sh` (pick the canonical path) and confirm it passes for the reorg.
- [ ] P0: Run the three new manifest tests — `AgentsManifestTest`, `AiScriptAccessManifestTest`, `ScriptsAiManifestTest` — and confirm green.
- [ ] P0: Run `composer test:fast` and confirm green.
- [ ] P0: Commit the in-flight `scripts/ai` reorg as one baseline commit (rollback = `git revert`); confirm `git status --short` is clean for `scripts/ai`.
- [ ] P1: Reframe advisor-context handling as source-only SCC exclusion (NOT untrack — already done); add it to the source-exclude list instead.
- [ ] P1: Archive untracked root planning docs into `docs/tickets/archive/root-cleanup-<date>/` via plain `mv` (NOT `git mv`), including `todo-repo-review.md`.
- [ ] P1: Append `export-ignore` rules to the existing `.gitattributes` (do NOT recreate) for `docs/ai/generated`, `docs/tickets`, `.ai-logs`, `vendor`, `dist`, `node_modules`.
- [ ] P2: Add `docs/ai/architecture/shipping-surface.md` classifying every top-level dir as shipped vs not-shipped.
- [ ] P2: Add canonical exclude lists `scripts/ai/internal/config/exclude-dirs.txt` and `scripts/ai/internal/config/source-exclude-dirs.txt`, and wire report scripts to read them.
- [ ] P2: Add `ship-audit.sh` (record the "limited value while generated dir untracked" caveat in its header).
- [ ] P2: Apply the existing `GENERATED — DO NOT EDIT` convention to docs that are TRULY generated (not placeholders, not agent frontmatter files).
- [ ] P3.1: Add provenance INSIDE YAML frontmatter for provider agent files; verify ONE file against `validate-ai-config.php` + `validate-adapter-drift.php` before bulk apply.
- [ ] P3.2: Per-file diff `docs/ai/package/**` against `packages/ai-universal-rules/docs/**`; run an ai-search reference sweep; create a tar backup; then plain `rm -r docs/ai/package`; record canonical decision in `source-of-truth.md`.
- [ ] P3.3: EXPAND-IN-PLACE the named placeholder docs (>=20 lines each, or explicitly mark as intentionally minimal); do NOT move or delete them.
- [ ] P4: DEFER docs/ai sectional reorg until a full reference map of cross-doc links exists.
- [ ] P5: Run capability/skill projection dedup ANALYSIS only (no moves/deletes in this phase).
- [ ] P6: Add `just verify-surface` ONLY after the scripts it references exist.
- [ ] P6: Pick ONE canonical `check-file-refs.sh` path and converge references to it.
- [ ] P6: Perform the shell refactor (e.g. split `repomix-scc-router.sh`) — POST-Phase-0 only, reconciling with the in-flight reorg shims.
- [ ] P6: Refactor `tools/ai/install/core.php` (~2012 lines); DROP the `run-full-install-validation.php` row; verify with `validate-install-surface.php` + `verify-full-install.php`.

## Acceptance Criteria

- [ ] AC-01: Phase 0 — `git status --short` shows the `scripts/ai` reorg committed and clean; `check-file-refs.sh`, the three manifest tests, and `composer test:fast` all pass.
- [ ] AC-02: Phase 1 — no untracked root `todo*`/plan `.md` remain (verified via `scripts/ai/bin/read/` wrappers / `ai-search.sh files`); they exist under `docs/tickets/archive/root-cleanup-<date>/`.
- [ ] AC-03: Phase 1 — `.gitattributes` contains `export-ignore` lines for `docs/ai/generated`, `docs/tickets`, `.ai-logs`, `vendor`, `dist`, `node_modules`, with the original eol rules intact.
- [ ] AC-04: Phase 2 — `docs/ai/architecture/shipping-surface.md` exists and classifies every top-level directory; both exclude lists exist and are referenced by the report scripts (provable via `ai-search.sh tracked`).
- [ ] AC-05: Phase 3.1 — provider agent files carry frontmatter provenance and still pass `validate-ai-config.php` + `validate-adapter-drift.php`.
- [ ] AC-06: Phase 3.2 — `docs/ai/package` is removed, an ai-search reference sweep returns no remaining references, and a tar backup + `source-of-truth.md` canonical note exist.
- [ ] AC-07: Phase 3.3 — each named placeholder doc is expanded to >=20 lines OR explicitly marked intentionally minimal; all original reference paths still resolve.
- [ ] AC-08: Phase 6 — `just verify-surface` exists and passes; the `core.php` refactor keeps `validate-install-surface.php` + `verify-full-install.php` green; no `run-full-install-validation.php` reference was introduced.
- [ ] AC-09: Already-done/dropped items are NOT re-added: no step untracks advisor-context, recreates `.gitattributes`, references `run-full-install-validation.php`, or uses `git mv`/`git rm` on untracked targets.

## Verification Plan

- `git status --short` — proves Phase 0 baseline is committed/clean and detects unintended scope (AC-01).
- `git diff --name-status --find-renames HEAD` — proves the reorg is captured as renames, not moves+deletes (AC-01).
- `bash scripts/ai/bin/verify/check-file-refs.sh` — proves reference integrity for the reorg (AC-01, AC-08).
- `php vendor/bin/phpunit --filter 'AgentsManifestTest|AiScriptAccessManifestTest|ScriptsAiManifestTest'` — proves the three manifest tests pass (AC-01).
- `composer test:fast` — broad regression smoke for Phase 0 and later code-touching phases (AC-01).
- `AI_OUTPUT=json bash scripts/ai/ai-search.sh files "todo" .` — confirms no untracked root todo/plan md remain at repo root (AC-02).
- `bash scripts/ai/preview-file.sh .gitattributes` — confirms appended export-ignore lines and intact eol rules (AC-03).
- `AI_OUTPUT=json bash scripts/ai/ai-search.sh tracked "exclude-dirs.txt" . --fixed` — confirms exclude lists are referenced by report scripts (AC-04).
- `php tools/ai/validate-ai-config.php` and `php tools/ai/validate-adapter-drift.php` — confirm agent frontmatter provenance is valid (AC-05).
- `AI_OUTPUT=json bash scripts/ai/ai-search.sh tracked "docs/ai/package" . --fixed` — confirms no remaining references after mirror removal (AC-06).
- `bash scripts/ai/preview-file.sh docs/ai/approval-boundaries.md` (and each named placeholder) — confirms expand-in-place or explicit minimal marker (AC-07).
- `php tools/ai/validate-install-surface.php` and `php tools/ai/verify-full-install.php` — confirm the `core.php` refactor keeps install surface green (AC-08).

## Risks And Rollback

- Risk: starting the shell/PHP refactor before Phase 0 lands collides with the uncommitted reorg. Mitigation: Phase 0 is BLOCKING; Phase 6 gated behind it. Rollback: `git revert` the reorg baseline.
- Risk: `git mv`/`git rm` on untracked files fails or partially executes. Mitigation: classify tracked status first (`git ls-files --error-unmatch`); use plain `mv`/`rm` on untracked targets.
- Risk: deleting untracked `docs/ai/package` is irreversible (no git recovery). Mitigation: per-file diff + reference sweep + tar backup BEFORE removal; record canonical in `source-of-truth.md`.
- Risk: editing high-blast-radius placeholder/canonical docs breaks many references. Mitigation: reference map first; expand-in-place over move/delete; verify reference resolution after.
- Risk: provenance placed above frontmatter breaks agent config parsing. Mitigation: provenance as a frontmatter KEY; verify ONE file before bulk.
- Risk: ship-audit gives false confidence while generated dir is untracked. Mitigation: document the caveat in the script header; do not gate releases on it yet.
- Rollback (Phase 0): `git revert` the reorg commit.
- Rollback (Phase 1-2 additive): revert the specific commit; archived docs and appended `.gitattributes` lines are easily reverted.
- Rollback (Phase 3.2 deletion): restore from the tar backup created before removal.

## Handoff Notes

- Execute strictly in phase order; Phase 0 is blocking and must be green before any Phase 6 refactor.
- Treat every "already-done / drop / reframe / correct" note as binding; do not regress to the original `todo-repo-review.md` command shapes.
- Verify with repo wrappers (`scripts/ai/ai-search.sh`, `scripts/ai/bin/**`, `scripts/ai/preview-file.sh`) and existing PHP validators; never raw `grep`/`find`/`cat`.
- Classify tracked status before any move/delete; untracked targets use plain `mv`/`rm`.
- Confirm existing tests pass before changing implementation code in Phase 6.
- implementer means implementer agent handoff using OpenCode command: /implement
