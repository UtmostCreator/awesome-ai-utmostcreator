# Architecture Plan — Consolidate Ticket Archiving Into `docs/tickets/archive/*`

- Ticket: none
- Source: user request, grounded against `docs/tickets/README.md`, `docs/tickets/MASTER-INDEX.md`,
  and the `architecture-plan-writer` agent's completion instructions
- Generated: 2026-07-09
- Plan file: docs/tickets/IDEAS/plan-ticket-archive-consolidation.md
- Type: process/convention change (touches the `architecture-plan-writer` agent template across
  all providers, `docs/tickets/README.md`, and optionally a one-time move of stale plan folders)
- Risk: medium (touches a canonical agent template rendered on 3 providers; a bulk folder move is
  a real-content-move operation, not a docs-only edit)

## Context

Two archiving conventions currently coexist in this repo's history, and only one is live:

1. **Live convention** (`docs/tickets/README.md` line 10, and the `architecture-plan-writer` agent
   template's completion instructions, e.g. `plan-29`/`plan-30`'s own headers): per-branch-folder
   archiving — `docs/tickets/{branch-name}/archive/DONE-plan-{n}-{short-desc}.md`. Confirmed still
   followed by the two most recent plans inspected (`plan-29`, `plan-30`).
2. **Removed convention** (referenced by `docs/tickets/MASTER-INDEX.md` lines 3-4, 13-14, 23, 110):
   a top-level `docs/tickets/archive/completed-20260614/` bucket. Confirmed **absent** from the
   working tree via `Glob docs/tickets/archive/**` (no results) — it was deleted by the
   `markdown-surface-reduction` plan's Phase 1 (`git rm -r docs/tickets/archive`, commit `0c0f219`,
   per that plan's own record in `docs/tickets/arch-todo-markdown-surface-reduction-20260614-232758/plan.md`).
   `MASTER-INDEX.md` was never updated after that removal and now references a path that does not
   exist.

The user wants a single top-level `docs/tickets/archive/*` convention where completed, stale, or
outdated tickets are moved, replacing (or absorbing) today's per-branch-folder convention.

## Problem

- Two conflicting historical conventions exist in doc text; only the per-branch one is currently
  implemented in tooling/agent instructions.
- The per-branch convention scatters archived plans across dozens of ticket folders
  (`docs/tickets/arch-todo-*/archive/DONE-plan.md`), making a repo-wide "what is actually done"
  view require reading every folder — exactly the discoverability problem `MASTER-INDEX.md` itself
  was built to solve, and which its own archive-path reference has now silently drifted from.
- `MASTER-INDEX.md` currently asserts a top-level archive path that does not exist on disk — a
  live doc-vs-reality drift bug independent of any redesign.

## Target Outcome

One documented, tool-consistent convention: completed, stale, or explicitly superseded ticket
plans move to a single top-level `docs/tickets/archive/{ticket-or-branch-slug}/` location instead
of a per-branch-folder `archive/` subdirectory, with `docs/tickets/README.md` and the
`architecture-plan-writer` agent's completion instructions (all 3 provider renders + template
source) updated to match, and `MASTER-INDEX.md`'s stale reference either fixed or reconciled with
the new location.

## In Scope

- Update `docs/tickets/README.md` "Convention" section: replace
  `docs/tickets/{branch-name}/archive/DONE-plan-{n}-{short-desc}.md` with
  `docs/tickets/archive/{branch-name}/DONE-plan-{n}-{short-desc}.md` (top-level `archive/` root,
  one subfolder per originating branch/ticket-slug, preserving the existing `DONE-` rename and
  numbering convention — only the archive root moves, not the file-naming contract).
- Update the canonical agent source
  `packages/ai-universal-rules/templates/core/agents/architecture-plan-writer.md`'s "Archive On
  Completion" section (the exact write-then-tombstone steps every current plan's completion
  instruction points to) to target the new top-level path, then re-render/hand-sync the three
  provider copies (`.opencode/agents/architecture-plan-writer.md`,
  `.github/agents/architecture-plan-writer.agent.md`, `.claude/agents/architecture-plan-writer.md`)
  exactly as `plan-30` did for the Mermaid-guidance addition (same 8-file-class change shape, one
  tier smaller since this only touches one agent, not two).
- Fix `docs/tickets/MASTER-INDEX.md`'s stale `docs/tickets/archive/completed-20260614/` references
  (lines 3-4, 13-14, 23, 110) to either point at the new `docs/tickets/archive/` location (if the
  historical entries are re-created there) or be marked historical/no-longer-present (if not) —
  resolve as a maintainer decision, not silently.
- Define "completed, stale, or outdated" for this convention: `completed` = every `## Todo Plan`
  and `## Acceptance Criteria` item checked `[x]` (existing rule, unchanged); `stale` = the plan's
  `Status:` line or `MASTER-INDEX.md` classifies it `rejected`/`superseded`/`do-not-implement`;
  `outdated` = a newer plan in the same folder explicitly supersedes it (e.g. `plan-30` recording
  `plan-29` as executed).
- A one-time, approval-gated move of the currently-scattered per-branch `archive/` subfolders
  (confirmed present today: `arch-todo-stack-permission-placeholder-skill-trio-*/archive/`,
  `arch-todo-terminal-signal-annotated-docs-index-*/archive/`,
  `arch-todo-full-install-validation-extraction-*/archive/`,
  `arch-todo-install-editions-*/archive/`, `arch-todo-doc-surface-hardening-*/archive/`,
  `arch-todo-post-install-verify-placeholders-grant-*/archive/`,
  `arch-todo-installer-core-executor-extraction-*/archive/`,
  `arch-todo-dynamic-stack-permission-selection-*/archive/`,
  `arch-todo-agent-permission-rethink-*/archive/`,
  `arch-todo-installer-workflow-command-extraction-*/archive/`,
  `arch-todo-validate-ai-config-extraction-*/archive/`, `arch-todo-core-package-updates-*/archive/`,
  `arch-todo-complete-permission-composition-migration/archive/`,
  `claude-agent-fleet-remediation/archive/` — 13+ folders found) into
  `docs/tickets/archive/{branch-slug}/`, using `git mv` (direct rename, not delete+recreate) so
  history is preserved.

## Out Of Scope (Things To Avoid)

- Deleting any archived plan content — this is a location change only (`git mv`), never a deletion.
- Changing the `DONE-plan-{n}-{short-desc}.md` renaming/numbering contract itself.
- Re-litigating which plans are "done" — reuse each folder's existing `Status:`/checkbox state as
  the source of truth; do not re-assess plan correctness in this slice.
- Moving folders that have NO `archive/` subfolder yet (active, non-archived plans stay exactly
  where they are — this only relocates already-archived content).
- Touching `MASTER-INDEX.md`'s Priority Queue or per-plan status judgments beyond the stale
  path reference itself.
- Implementation in this pass — this is a plan only.

## Affected Paths

- `docs/tickets/README.md` (convention section)
- `packages/ai-universal-rules/templates/core/agents/architecture-plan-writer.md` (canonical
  source — "Archive On Completion" section)
- `.opencode/agents/architecture-plan-writer.md`, `.github/agents/architecture-plan-writer.agent.md`,
  `.claude/agents/architecture-plan-writer.md` (rendered mirrors)
- `docs/tickets/MASTER-INDEX.md` (stale path fix)
- `docs/tickets/archive/` (new top-level folder; currently absent, confirmed via `Glob`)
- The 13+ existing per-branch `archive/` subfolders (moved via `git mv`, contents unchanged)

## Contracts And Boundaries

- **Non-destructive**: every move is `git mv`, preserving git history; no `rm` of archived content.
- **Adapter parity**: the "Archive On Completion" instruction change must land identically (modulo
  provider syntax) in the template source and all three rendered copies in the same slice, per
  `docs/ai/adapter-contract.md`'s rule that adapters must not drift from their template source.
- **Reuse the existing DONE-rename contract**: only the archive root path changes
  (`docs/tickets/{branch}/archive/` -> `docs/tickets/archive/{branch}/`); the `DONE-plan-{n}-{desc}.md`
  filename shape is unchanged, so every existing plan file that already references its own future
  archive path (dozens of plans have this exact sentence in their header) becomes stale text unless
  a decision is made: either (a) treat it as historical and not worth bulk-editing every existing
  plan file's header sentence, or (b) bulk-fix via a scripted find-and-replace. **Recommendation**:
  (a) — only new plans get the new path in their completion instruction; old plans' now-slightly-
  wrong header sentence is harmless once they are already archived (the sentence describes a
  historical action, not a live requirement) and bulk-editing dozens of unrelated files is
  disproportionate scope creep for this slice.

## Todo Plan

- [ ] P0: Update `docs/tickets/README.md`'s "Convention" section to the new top-level path.
- [ ] P0: Update the canonical `architecture-plan-writer.md` template's "Archive On Completion"
      section; re-render/hand-sync the 3 provider copies (mirroring `plan-30`'s proven approach).
- [ ] P1: Fix or explicitly annotate `MASTER-INDEX.md`'s stale `archive/completed-20260614/`
      references (maintainer decision: recreate under new path, or mark historical-only).
- [ ] P2 (approval-gated): `git mv` each confirmed existing `archive/` subfolder into
      `docs/tickets/archive/{branch-slug}/`, one commit, verifying no file collisions.
- [ ] P2: Confirm no validator or test hardcodes the old per-branch archive path (search
      `tests/php/**` and `tools/ai/**` for `docs/tickets` + `archive` path assumptions before the
      move).

## Acceptance Criteria

- AC-01: `docs/tickets/README.md` states the new top-level convention and no longer describes the
  per-branch-only path as canonical.
- AC-02: All three rendered `architecture-plan-writer` agent copies and their template source
  agree byte-for-byte (modulo provider frontmatter) on the new "Archive On Completion" path.
- AC-03: `MASTER-INDEX.md` contains no reference to a `docs/tickets/archive/` path that does not
  exist on disk after this slice (either the path exists, or the reference is clearly marked
  historical).
- AC-04: Every `git mv`'d folder's content is byte-identical before/after (diff of file contents,
  not just presence) and `git log --follow` on a sampled moved file shows continuous history.
- AC-05 (negative): No plan file's own body text is bulk-edited as part of this slice (per the
  "Recommendation (a)" boundary above) unless a maintainer explicitly approves scope (b).

## Verification Plan

- `git status --short` before and after the move to confirm no unintended files are touched.
- Diff each moved folder's file list before/after (`git show HEAD:path` vs. new path) to confirm
  content parity.
- `php tools/ai/validate-adapter-drift.php` after the agent-template edit, to confirm the
  `architecture-plan-writer` render still passes drift checks.
- Grep (via `ai-search.sh`) for `docs/tickets/.*archive` path patterns across `tools/ai/**` and
  `tests/php/**` before the P2 move, to catch any hardcoded assumption.

## Risks And Rollback

- **Medium**: bulk `git mv` across 13+ folders in one pass has real blast radius if any script or
  test hardcodes a per-branch archive path; mitigation is the pre-move grep in the Verification
  Plan and doing the move as its own isolated, reviewable commit.
- **Low**: the agent-template edit is the same class of change already proven safe by `plan-30`
  (8-file Mermaid-guidance sync); this slice is a subset (1 agent, not 2).
- **Rollback**: `git revert` the move commit (safe — `git mv` is a rename, trivially reversible)
  and the agent-template commit independently.

## Handoff Notes

- This plan intentionally does NOT redesign `architecture-plan-writer`'s broader behavior — it
  changes exactly one path string in its "Archive On Completion" section, per the user's request
  to "change how we archive tickets."
- Recommended next step: `architect means architect agent handoff` to confirm the P2 bulk-move
  approach (or defer P2 and ship P0/P1 alone as the safer first increment) — then
  `architecture-plan-writer means architecture-plan-writer agent handoff` to persist any
  refinement, then `implementer means implementer agent handoff` for P0/P1 first, P2 as a separate
  reviewed slice.
