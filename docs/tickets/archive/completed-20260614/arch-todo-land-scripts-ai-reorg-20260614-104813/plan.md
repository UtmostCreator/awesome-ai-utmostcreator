# Architecture Plan — Land the In-Flight scripts/ai Reorg (Baseline Commit)

- Ticket: none
- Source: decomposition of `docs/tickets/arch-todo-repo-cleanup-shipping-surface-20260614-101701/plan.md` (Phase 0), with binding user decisions
- Generated: 20260614-104813
- Plan folder: docs/tickets/arch-todo-land-scripts-ai-reorg-20260614-104813/
- Status: Todo (unchecked) — write-only; reviewer reviews, edits applied, THEN implement
- Decomposition role: **Plan A of A–D** (BLOCKING baseline; blocks Plans B, C, D; is a prerequisite for deferred Plans E and F, which also require their own gates)
- Rank: 00

## Context

The working tree carries a large IN-FLIGHT, uncommitted reorg of `scripts/ai/` (verified via `git status --short`):

- New role/risk delegating shims under `scripts/ai/bin/{read,verify,context,edit,hooks,admin}/` (41 shims).
- Renames `scripts/ai/lib/* -> scripts/ai/internal/lib/*` (11 renames; `60-exec-guard.sh` is `RM`).
- Renames `scripts/ai/ai-search/* -> scripts/ai/internal/search/*` (18 renames).
- New manifest tests: `tests/php/AgentsManifestTest.php`, `tests/php/AiScriptAccessManifestTest.php`, `tests/php/ScriptsAiManifestTest.php`.
- New untracked support: `.github/ai-script-access.yaml`, `docs/ai/AGENTS-MANIFEST.md`, `tools/ai/validate-script-access.php`, and `scripts/ai/internal/{ai-diff-context,ai-edit,ai-verify,pre-tool-use,prune-shipped-targets,repomix-context-tree,repomix-scc-router,lib/exec-guard}/`.

This reorg is the prerequisite baseline for the corrected cleanup program (Plans B–F). Nothing dependent may run until it is committed so each later plan has a clean, revertible base.

Per binding user decisions, items 50/55/60/65 are ALREADY DONE in the working tree (covered by `arch-todo-remaining-rework-20260613-231930` Phases 5 & 7 plus `arch-todo-restructure-scripts-ai-p3-p5-20260613-220424`). This plan does NOT re-implement them — it only verifies they are green as part of the baseline.

## Problem

The reorg sits uncommitted in a very dirty worktree. Any dependent cleanup or refactor started before it lands will collide with the moves/shims, make scope unreadable, and remove the ability to roll back the reorg independently. The reorg must be captured as ONE baseline commit, proven green, and confirmed to be recorded as renames (not delete+add) so revert restores prior behavior cleanly.

## Target Outcome

The in-flight `scripts/ai` reorg is committed as a single baseline commit, the worktree is clean for `scripts/ai`, the reorg is recorded as renames, the three manifest tests + reference checker + fast suite are green, and already-done items 50/55/60/65 are confirmed green.

## In Scope

- Snapshot the diff before landing (`git status --short`, `git diff --name-status --find-renames HEAD`).
- Verify the reorg is captured as renames, not delete+add.
- Run the three manifest tests, `check-file-refs.sh`, and `composer test:fast`.
- Commit the in-flight `scripts/ai` reorg as ONE baseline commit.
- Confirm already-done items 50/55/60/65 are green (verification only).

## Out Of Scope (Things To Avoid)

- Any feature work or refactor (shell or PHP).
- Any shipping-surface work (Plan B): no `shipping-surface.md`, no exclude lists, no `ship-audit.sh`, no `.gitattributes` edits, no archiving of root todo docs.
- Touching or archiving the root `todo-*.md` planning docs (that is Plan B's item 25).
- Re-implementing items 50/55/60/65 (already done — verify only).
- Hand-editing any generated manifest or hash.
- **DO NOT stage unrelated dirty files in the baseline commit.** The worktree
  carries modifications outside the reorg that MUST stay unstaged:
  - `docs/ai/repo-required-tools.md`, `docs/ai/validation.md` (modified)
  - `improvement-plan.md`, `readme-install.md`, `ai-search-todo-tests.md`
    (tracked, content-corrected in the prior todo-cleanup pass — commit separately)
  - the 6 untracked root `todo-*.md` planning docs
  - the new `docs/tickets/arch-todo-*` plan folders (this decomposition set)
  Never use `git add -A` / bare `git commit -a`. Stage an explicit path-set only.

## Affected Paths

- `scripts/ai/**` (in-flight reorg: shims under `bin/**`, renames into `internal/**`, edited facades).
- `tests/php/{AgentsManifestTest,AiScriptAccessManifestTest,ScriptsAiManifestTest}.php` and related edited tests.
- `.github/ai-script-access.yaml`, `docs/ai/AGENTS-MANIFEST.md`, `tools/ai/validate-script-access.php` (new support, untracked).
- `tools/ai/install/{packs.php,script-registry.php}`, `tools/ai/sh-introspect*` (edited as part of the reorg).
- This plan folder (durable planning output).

## Contracts And Boundaries

- The committed `scripts/ai` reorg is the baseline contract for ALL later plans (B, C, D, E, F); none run until it lands.
- The reorg must be recorded as renames so `git revert` restores prior behavior; root names remain as shims/facades for the public contract.
- Items 50/55/60/65 are treated as already-landed in the working tree; this plan asserts their tests are green and does not modify them.
- Generated manifests/hashes are regenerated only by official generators; never hand-edited (no regeneration is expected for a straight commit-as-baseline).

## Todo Plan

- [ ] P0: Snapshot the in-flight reorg with `git status --short`.
- [ ] P0: Snapshot rename detection with `git diff --name-status --find-renames HEAD`; confirm the `lib/*` and `ai-search/*` moves show as `R` (renames), not paired `D`/`A`.
- [ ] P0: Run `bash scripts/ai/bin/verify/check-file-refs.sh` and confirm exit 0.
- [ ] P0: Run `php vendor/bin/phpunit --filter 'AgentsManifestTest|AiScriptAccessManifestTest|ScriptsAiManifestTest'` and confirm green.
- [ ] P0: Run `composer test:fast` and confirm green.
- [ ] P0: Confirm already-done items 50/55/60/65 are green within `composer test:fast` (no edits — verification only); record the exact covering test names/classes in the completion report so AC-06 is observable, not a judgment call.
- [ ] P0: Stage ONLY the reorg path-set (explicit, no `git add -A`): `scripts/ai/`, `tests/php/AgentsManifestTest.php`, `tests/php/AiScriptAccessManifestTest.php`, `tests/php/ScriptsAiManifestTest.php` + the reorg-edited tests, `.github/ai-script-access.yaml`, `docs/ai/AGENTS-MANIFEST.md`, `tools/ai/validate-script-access.php`, and the reorg-edited `tools/ai/install/packs.php`, `tools/ai/install/script-registry.php`, `tools/ai/sh-introspect*`.
- [ ] P0: Run `git status --short` after staging and CONFIRM the do-NOT-stage files (validation.md, repo-required-tools.md, improvement-plan.md, readme-install.md, ai-search-todo-tests.md, root `todo-*.md`, new `docs/tickets/arch-todo-*` folders) are still unstaged.
- [ ] P0: Commit the staged reorg as ONE baseline commit; then confirm `git status --short` shows `scripts/ai` clean.

## Acceptance Criteria

- [ ] AC-01: After the baseline commit, `git status --short` shows no remaining uncommitted `scripts/ai/**` changes (working tree clean for `scripts/ai`) AND the unrelated dirty files (validation.md, repo-required-tools.md, improvement-plan.md, readme-install.md, ai-search-todo-tests.md, root `todo-*.md`, new `docs/tickets/arch-todo-*` folders) remain unstaged / out of the baseline commit.
- [ ] AC-02: `git diff --name-status --find-renames HEAD` (run before the commit) shows the `lib/*` and `ai-search/*` migrations as renames (`R`), not delete+add pairs.
- [ ] AC-03: `AgentsManifestTest`, `AiScriptAccessManifestTest`, and `ScriptsAiManifestTest` all pass.
- [ ] AC-04: `bash scripts/ai/bin/verify/check-file-refs.sh` exits 0.
- [ ] AC-05: `composer test:fast` passes.
- [ ] AC-06: Items 50/55/60/65 are confirmed green (verification only) and explicitly reported as already-done, with no edits made to their implementation.

## Verification Plan

- `git status --short` — proves baseline is committed and `scripts/ai` is clean (AC-01).
- `git diff --name-status --find-renames HEAD` — proves the reorg is captured as renames, not moves+deletes (AC-02).
- `php vendor/bin/phpunit --filter 'AgentsManifestTest|AiScriptAccessManifestTest|ScriptsAiManifestTest'` — proves the three manifest tests pass (AC-03).
- `bash scripts/ai/bin/verify/check-file-refs.sh` — proves reference integrity for the reorg (AC-04).
- `composer test:fast` — broad regression smoke; also covers items 50/55/60/65 (AC-05, AC-06).

## Risks And Rollback

- Risk (medium): committing a half-staged reorg leaves dangling references. Mitigation: snapshot + rename check + `check-file-refs.sh` before committing; commit the full reorg in one commit.
- Risk: a move shows as delete+add, making revert lossy. Mitigation: AC-02 explicitly verifies `--find-renames` reports `R`; restage if needed so renames are detected.
- Risk: assuming 50/55/60/65 are done when a test is red. Mitigation: AC-06 requires green evidence before claiming done; if red, stop and report (do not silently fix here).
- Rollback: `git revert` the single baseline commit. Root names are preserved as shims/facades, so a revert restores prior behavior without consumer changes.

## Handoff Notes

- This is the BLOCKING baseline. Plans B, C, D, E, F all depend on this commit landing green.
- Commit the reorg as ONE baseline commit (binding user decision); do not split it across phases.
- Do NOT touch root `todo-*.md` docs here — archiving them is Plan B (item 25).
- Treat any red test in 50/55/60/65 as a stop-and-report, not an in-scope fix.
- implementer means implementer agent handoff using OpenCode command: /implement
