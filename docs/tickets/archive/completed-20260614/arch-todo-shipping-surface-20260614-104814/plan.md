# Architecture Plan — Shipping-Surface Hardening

- Ticket: none
- Source: decomposition of `docs/tickets/arch-todo-repo-cleanup-shipping-surface-20260614-101701/plan.md` (Phases 1–2), with binding user decisions
- Generated: 20260614-104814
- Plan folder: docs/tickets/arch-todo-shipping-surface-20260614-104814/
- Status: Todo (unchecked) — write-only; reviewer reviews, edits applied, THEN implement
- Decomposition role: **Plan B of A–D**
- Rank: items 05, 10, 15, 20, 25
- Dependency: **Plan A committed** (baseline reorg landed)

## Context

After Plan A lands the `scripts/ai` reorg, the repo needs an explicit shipping-surface policy and the low/medium-risk cleanups that depend on it. Ground truth (verified pre-decomposition, repo wrappers / git plumbing — not raw grep/find/cat):

- `.gitattributes` EXISTS (eol rules only, no `export-ignore`) — append, do not recreate.
- Root planning docs (`todo-repo-review.md`, `todo-agents-rework.md`, `todo-agents-script-rework.md`, `todo-introspect-improvement.md`, `todo-scripts-refactor.md`, `todo-scripts-improvement.md`, plus `improvement-plan.md`, `readme-install.md`, `ai-search-todo-tests.md`) are mostly UNTRACKED — `git mv` fails; use plain `mv` for untracked, `git mv` only for tracked.
- `docs/ai/generated/` is gitignored with 0 tracked files; `ship-audit.sh` has limited value while that dir is untracked — record the caveat in the script header.
- `scripts/ai/internal/config/` does NOT exist yet (verified: Glob returns no files). This plan CREATES it as the new canonical home for exclude-dir lists (items 10a/10b must `mkdir -p` it first).
- Report scripts currently INLINE their excludes (verified: `AI_CONTEXT_HARD_EXCLUDES` in `scripts/ai/internal/repomix-scc-router/10-helpers.sh`; `DEFAULT_EXCLUDES` in `scripts/ai/internal/search/55-scope-args.sh`) — item 10c rewires these to read the new files.

## Problem

There is no single policy that classifies which top-level directories ship vs. stay repo-local/generated, no canonical exclude-dir lists for report scripts to share, no detector for forbidden paths leaking into a ship, no `export-ignore` rules, and stale root planning docs clutter the repo root. Acting with the wrong command shapes (`git mv`/`git rm` on untracked files) fails or partially executes.

## Target Outcome

A committed shipping-surface policy doc, two canonical exclude-dir lists wired into the report scripts, a `ship-audit.sh` forbidden-path detector (with an honest caveat), `export-ignore` rules appended to the existing `.gitattributes`, and the untracked root todo docs archived under `docs/tickets/archive/root-cleanup-<date>/` using command shapes matched to tracked status.

## In Scope

- 05: Add `docs/ai/architecture/shipping-surface.md` policy doc classifying every top-level dir as source / generated / repo-local / shipped.
- 10: Add canonical exclude lists `scripts/ai/internal/config/exclude-dirs.txt` and `scripts/ai/internal/config/source-exclude-dirs.txt`, and wire report scripts to read them.
- 15: Add `ship-audit.sh` forbidden-path detector (header caveat: limited value while `docs/ai/generated/` is untracked).
- 20: Append `export-ignore` rules to the EXISTING `.gitattributes` (do not recreate).
- 25: Archive untracked root todo docs via plain `mv` (and tracked ones via `git mv`) into `docs/tickets/archive/root-cleanup-<date>/`.

## Out Of Scope (Things To Avoid)

- The `verify-surface` gate (Plan C, item 85).
- Placeholder-doc expansion, package-mirror removal, any refactor (Plan C / backlog).
- `git mv` / `git rm` on untracked files — use plain `mv` / `rm`; classify tracked status first (`git ls-files --error-unmatch`).
- Recreating `.gitattributes` — append only; keep existing eol rules intact.
- Raw `grep` / `find` / `cat` in verification — use `scripts/ai/ai-search.sh`, `scripts/ai/bin/**` wrappers, `scripts/ai/preview-file.sh`.
- Over-promising `ship-audit.sh` value while the generated dir is untracked.

## Affected Paths

- `docs/ai/architecture/shipping-surface.md` — new policy doc.
- `scripts/ai/internal/config/exclude-dirs.txt`, `scripts/ai/internal/config/source-exclude-dirs.txt` — new canonical exclude lists.
- `scripts/ai/bin/verify/ship-audit.sh` — new forbidden-path detector (primary location, matching the role-tree convention established by Plan A; impl logic may live under `scripts/ai/internal/ship-audit/` per the shim+internal pattern).
- Report scripts that currently inline exclude lists — wired to read the new files.
- `.gitattributes` — appended with `export-ignore` rules.
- Root `todo-*.md` / `improvement-plan.md` / `readme-install.md` / `ai-search-todo-tests.md` — archived.
- `docs/tickets/archive/root-cleanup-<date>/` — archive destination.

## Contracts And Boundaries

- Plan A baseline must be committed first; this plan CREATES the `scripts/ai/internal/config/` location (it does not yet exist).
- **Archive-vs-mid-review ordering:** the root planning docs were content-corrected in the prior todo-cleanup pass and the tracked ones (`improvement-plan.md`, `readme-install.md`, `ai-search-todo-tests.md`) currently show unstaged modifications. Before archiving (item 25), those in-review content edits must be committed (or explicitly abandoned) FIRST; for tracked docs commit the content change, THEN `git mv` in a separate step so the rename is clean and not mixed with a content edit.
- Tracked vs untracked status decides the command: tracked -> `git mv`; untracked -> plain `mv`. Verify each target with `git ls-files --error-unmatch` before moving.
- `exclude-dirs.txt` (shipping/report exclusion) and `source-exclude-dirs.txt` (source-only exclusion, e.g. SCC) are distinct lists; report scripts must reference the correct one.
- `ship-audit.sh` is a detector, not a release gate yet; its header records the untracked-generated-dir caveat.
- New `.sh` under `scripts/ai/` may interact with the registry↔packs 1:1 invariant; if `ship-audit.sh` is registered, update registry+packs in the same slice (a `bin` dir entry already ships the role tree per Plan A).

## Todo Plan

- [ ] 05: Add `docs/ai/architecture/shipping-surface.md` classifying every top-level directory as source / generated / repo-local / shipped.
- [ ] 10a: `mkdir -p scripts/ai/internal/config/`, then add `scripts/ai/internal/config/exclude-dirs.txt` (ship/report exclusions: `docs/ai/generated`, `docs/tickets`, `.ai-logs`, `vendor`, `dist`, `node_modules`, etc.).
- [ ] 10b: Add `scripts/ai/internal/config/source-exclude-dirs.txt` (source-only exclusions, e.g. advisor-context / SCC).
- [ ] 10c: Wire the report scripts to read both exclude lists instead of inlining the lists.
- [ ] 15: Add `scripts/ai/ship-audit.sh` (forbidden-path detector) with a header caveat that it has limited value while `docs/ai/generated/` is untracked; exit non-zero on forbidden paths.
- [ ] 20: Append `export-ignore` rules to the EXISTING `.gitattributes` for `docs/ai/generated`, `docs/tickets`, `.ai-logs`, `vendor`, `dist`, `node_modules`; keep existing eol rules.
- [ ] 25-pre: Confirm the root planning docs' in-review content edits are committed (or abandoned) before archiving; commit tracked-doc content changes separately, THEN rename, so each `git mv` is a clean rename.
- [ ] 25a: Classify each root todo/plan doc's tracked status with `git ls-files --error-unmatch`.
- [ ] 25b: Archive untracked root docs via plain `mv` and tracked root docs via `git mv` into `docs/tickets/archive/root-cleanup-<date>/`.

## Acceptance Criteria

- [ ] AC-01: `docs/ai/architecture/shipping-surface.md` exists and classifies every top-level directory as source / generated / repo-local / shipped.
- [ ] AC-02: Both `exclude-dirs.txt` and `source-exclude-dirs.txt` exist under `scripts/ai/internal/config/` and are referenced by the report scripts (provable via `ai-search.sh tracked`).
- [ ] AC-03: `scripts/ai/ship-audit.sh` runs and exits non-zero when a forbidden path is present (and zero when clean); its header records the untracked-generated-dir caveat.
- [ ] AC-04: `.gitattributes` contains `export-ignore` lines for `docs/ai/generated`, `docs/tickets`, `.ai-logs`, `vendor`, `dist`, `node_modules`, with the original eol rules intact.
- [ ] AC-05: No untracked root `todo*.md` (or the named root planning docs) remain at the repo root; they exist under `docs/tickets/archive/root-cleanup-<date>/`.
- [ ] AC-06: `check-file-refs.sh` exits 0 and `composer test:fast` passes after the changes.

## Verification Plan

- `bash scripts/ai/preview-file.sh .gitattributes` — confirms appended `export-ignore` lines and intact eol rules (AC-04).
- `AI_OUTPUT=json bash scripts/ai/ai-search.sh tracked "exclude-dirs.txt" . --fixed` — confirms exclude lists are referenced by report scripts (AC-02).
- `bash scripts/ai/ship-audit.sh` — confirms the detector runs and signals forbidden paths (AC-03).
- `AI_OUTPUT=json bash scripts/ai/ai-search.sh files "*todo*.md" . --max-depth 1` (root-scoped) — confirms no root-level todo/plan md remain (AC-05); do not match `docs/tickets/**` references.
- `bash scripts/ai/preview-file.sh docs/ai/architecture/shipping-surface.md` — confirms top-level classification exists (AC-01).
- `bash scripts/ai/bin/verify/check-file-refs.sh` — reference integrity after edits (AC-06).
- `composer test:fast` — regression smoke (AC-06).

## Risks And Rollback

- Risk (low-medium): `git mv`/`git rm` on untracked files fails or partially executes. Mitigation: classify tracked status first; plain `mv` for untracked.
- Risk: archiving loses an untracked doc (no git recovery). Mitigation: move into the archive folder (never delete); verify presence post-move.
- Risk: ship-audit gives false confidence while generated dir is untracked. Mitigation: caveat in header; do not gate releases on it yet.
- Risk: wiring report scripts to the wrong exclude list. Mitigation: two distinct lists with clear names; verify references with `ai-search.sh tracked`.
- Rollback: revert the additive commit; restore archived docs from the archive folder back to root.

## Handoff Notes

- Do NOT start until Plan A is committed and green.
- Use command shapes matched to tracked status; never `git mv`/`git rm` an untracked file.
- Append to `.gitattributes`; do not recreate it.
- Keep `ship-audit.sh` honest — record the caveat; do not present it as a release gate.
- implementer means implementer agent handoff using OpenCode command: /implement
