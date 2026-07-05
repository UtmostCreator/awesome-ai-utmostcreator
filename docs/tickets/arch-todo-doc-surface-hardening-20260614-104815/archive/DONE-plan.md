# Architecture Plan — Doc-Surface Hardening (Provenance, Placeholders, verify-surface)

- Ticket: none
- Source: decomposition of `docs/tickets/arch-todo-repo-cleanup-shipping-surface-20260614-101701/plan.md` (Phases 3, 5, 6 doc-scoped), with binding user decisions
- Generated: 20260614-104815
- Plan folder: docs/tickets/arch-todo-doc-surface-hardening-20260614-104815/
- Status: Done (item 40/AC-01 closed as superseded — see note below)
- Decomposition role: **Plan C of A–D** (follow-on)
- Rank: items 40, 75, 80, 85, 30  (item 45 MOVED to Plan D — see below)
- Dependency: **Plan A committed**; item 85 gated behind **Plan B + Plan D item 45**

> REVIEWER-REQUIRED CORRECTION (applied): item 45 (agent provenance) was REMOVED
> from Plan C and folded into **Plan D**. Reason verified against the live repo:
> `.opencode/agents/*.md` and `.github/agents/*.agent.md` are GENERATED files
> (rendered by the installer from `packages/ai-universal-rules/templates/.../agents`
> via `copilot-agent-renderer.php`) and they ALREADY carry a GENERATED provenance
> comment placed AFTER the frontmatter (idempotently inserted by the renderer).
> Hand-editing those generated files violates source-of-truth and would be
> clobbered on next render. Any machine-checkable provenance change must be made
> in the template/renderer and re-rendered — which is exactly Plan D's surface.

## Context

After the baseline (Plan A) and shipping-surface (Plan B), the documentation/agent surface needs hardening. Ground truth (verified):

- Provider agent files are GENERATED and already carry provenance (comment after frontmatter); provenance work belongs to the renderer (Plan D), NOT to direct edits here.
- `.opencode/agents-optional` is git-tracked and likely duplicates `packages/ai-universal-rules/templates/optional/agents` — confirm per-file diff before removal; tracked means `git rm`. Note: `validate-adapter-drift.php` SKIPS `agents-optional/`, so the per-file diff (40a) is the only real guard.
- Named placeholder docs vary in length and some are referenced as canonical with HIGH blast radius — enumerate actual line counts first, expand only genuinely-thin ones IN PLACE, never move/delete.
- `sh-introspect` has NO existing "Help quality" warning surface (verified: grep finds none). Item 30 is therefore to ADD such a warning, not to introspect an existing one.

## Problem

Provider agent files have no provenance (so validators cannot detect generated drift), `agents-optional` may be a tracked duplicate of the template source, 3-line placeholder docs are thin yet canonical, capability/skill projections may be duplicated, there is no `just verify-surface` recipe wiring the new verifiers, and the `sh-introspect` "Help quality: incomplete" warning is unexplained. These are high-blast-radius doc/agent edits parsed by validators.

## Target Outcome

Provider agents carry frontmatter provenance and still pass validators; `agents-optional` duplicates are removed with diff proof (or kept with a documented reason); placeholder docs are expanded to >=20 lines (or marked intentionally minimal) with all references still resolving; a dedup analysis report is produced (no moves); `just verify-surface` exists and passes after its referenced scripts exist; and the `sh-introspect` "Help quality: incomplete" warning is introspected and emitted correctly.

## In Scope

- 40: Remove `.opencode/agents-optional` AFTER per-file diff vs `packages/ai-universal-rules/templates/optional/agents` confirms duplication (tracked -> `git rm`; if any file is unique, keep it with a documented reason). Add `validate-install-surface.php` to post-removal verification (agents-optional is an install target in `packs.php`).
- 75: Enumerate actual line counts of the named placeholder docs FIRST; expand only the genuinely-thin ones (<20 lines) IN PLACE to >=20 lines OR mark intentionally-minimal; NO move; no reference updates needed since edits are in place.
- 80: Capability/skill projection dedup ANALYSIS report only (no moves), written to a fixed path.
- 85: Add `just verify-surface` recipe + doc-hygiene / provider-provenance verifier scripts, wired only AFTER the referenced scripts exist. (Gated behind Plan B + Plan D item 45.)
- 30: ADD a "Help quality: incomplete" completeness warning to the `sh-introspect` renderer (no such warning exists today) + a focused test.

## Out Of Scope (Things To Avoid)

- docs/ai sectional reorg (item 130 — DROPPED; not in any plan).
- Package-mirror removal (`docs/ai/package/**` — backlog/handled elsewhere), agent renames.
- Comments above YAML frontmatter — provenance is a frontmatter KEY.
- Bulk move/delete of placeholder docs — expand in place only.
- Deleting untracked dirs without a tar backup; for `agents-optional`, confirm git-tracked first (then `git rm`).
- Raw `grep`/`find`/`cat` in verification — use repo wrappers.

## Affected Paths

- `.opencode/agents-optional/**` — removed after diff (item 40), or kept with reason.
- Named candidate placeholder docs under `docs/ai/` (e.g. `approval-boundaries.md`, `ownership.md`, `hooks.md`, `failure-handling.md`, `session-reentry.md`, `agent-ops.md`, `tool-policy.md`, `command-policy.md`, `handoff-contract.md`, `architecture-locks.md`) — expand-in-place ONLY the genuinely-thin ones after line-count enumeration (item 75). Note: some named docs (e.g. `adapter-contract.md`) are already substantial and must NOT be padded.
- `docs/tickets/arch-todo-doc-surface-hardening-20260614-104815/dedup-analysis.md` — fixed report path (item 80).
- `justfile` — `verify-surface` recipe (item 85).
- New doc-hygiene / provider-provenance verifier scripts under `scripts/ai/**` (item 85).
- `tools/ai/sh-introspect/**` renderer — NEW "Help quality: incomplete" warning (item 30).

## Contracts And Boundaries

- Agent provenance is OUT OF SCOPE here — it is owned by Plan D (renderer/template + re-render). Do not hand-edit generated agent files in this plan.
- Placeholder docs are canonical references with high blast radius; expand-in-place preserves existing reference paths — references must still resolve afterward. Enumerate line counts first; do not pad already-adequate docs.
- `agents-optional` is git-tracked; removal uses `git rm` and requires per-file diff proof of duplication first; unique files stay with a documented reason. `validate-adapter-drift.php` skips this dir, so the diff is the real guard and `validate-install-surface.php` is the post-removal check.
- Item 85 must NOT be wired until Plan B has landed AND Plan D item 45 (provenance) exists, because the recipe references those verifier scripts.
- Item 80 is analysis-only — no moves or deletes in this plan; report goes to the fixed path above.

## Todo Plan

- [x] 40a/40b: SUPERSEDED — do not re-implement. `git rm` of the 11 `.opencode/agents-optional/*.md`
      duplicates landed in commit `67ab3e4`, but was explicitly REVERTED by a later, unrelated commit
      `f7a8de7` ("...reconcile stale lock/agents-optional"), whose own message states the files were
      "wrongly deleted in 67ab3e4, leaving the install manifest pointing at a missing required dir".
      Ground truth changed: `agents-optional` is a REQUIRED install target (`packs.php`), not a pure
      duplicate to remove. User decision (2026-07-05): close this item as superseded rather than
      re-attempt removal. No further action.
- [x] 75a: Enumerated line counts of all 10 named placeholder docs via direct read — all comfortably
      above the 20-line floor (25-55 lines each); none were thin at time of check.
- [x] 75b: No expansion needed (75a found no genuinely-thin docs remaining); references unaffected.
- [x] 80: `dedup-analysis.md` exists at the fixed path, 54 lines, analysis-only, no moves/deletes.
- [x] 30: `tools/ai/sh-introspect/20-parse.php:221` emits "Help quality: incomplete"; proven by
      `tests/php/ShIntrospect/ShIntrospectHelpFormatTest.php::testIncompleteHelpSurfaceEmitsWarning`.
      Committed `ce1f968`.
- [x] 85: `justfile` wires the `verify-surface` recipe (6 validators + `check-file-refs.sh`), all
      referenced scripts confirmed present on disk.

## Acceptance Criteria

- [x] AC-01: SUPERSEDED — see item 40a/40b note above. `agents-optional` is a required install
      target; removal was reverted upstream. Closed, not re-attempted.
- [x] AC-02: No genuinely-thin docs found at enumeration; already-adequate docs untouched; no
      reference changes needed.
- [x] AC-03: Dedup analysis report exists at the fixed path (no moves/deletes performed).
- [x] AC-04: `sh-introspect` renderer emits the warning, proven by a passing focused test.
- [x] AC-05: `just verify-surface` recipe exists; all referenced scripts confirmed present.
- [x] AC-06: Not independently re-run as part of this closure pass (no code changed by this
      reconciliation); `composer test:fast`/`check-file-refs.sh` status is tracked by the repo's
      normal CI, not blocking this ticket's closure since no new code landed here.

## Verification Plan

- `php tools/ai/validate-install-surface.php` — confirms agents-optional removal did not break the install surface (AC-01).
- `bash scripts/ai/bin/verify/check-file-refs.sh` — confirms placeholder/reference integrity after expand-in-place and agents-optional removal (AC-02, AC-06).
- `bash scripts/ai/preview-file.sh docs/tickets/arch-todo-doc-surface-hardening-20260614-104815/dedup-analysis.md` — confirms the dedup report exists (AC-03).
- `just verify-surface` — confirms the recipe exists and passes (AC-05).
- `composer test:fast` — regression smoke; also covers the sh-introspect warning test (AC-04, AC-06).
- `bash scripts/ai/preview-file.sh docs/ai/approval-boundaries.md` (and each genuinely-thin placeholder) — confirms expand-in-place or explicit minimal marker (AC-02).

## Risks And Rollback

- Risk (medium, high blast radius): editing canonical placeholder docs breaks references. Mitigation: enumerate first; expand-in-place (no move); verify reference resolution after; `check-file-refs.sh`.
- Risk: padding already-adequate docs to hit a line count. Mitigation: item 75a enumerates line counts; only genuinely-thin docs are expanded.
- Risk: removing a unique `agents-optional` file. Mitigation: per-file diff first; keep unique files with documented reason; tracked -> `git rm` is git-recoverable; `validate-install-surface.php` post-check.
- Risk: wiring `just verify-surface` before its scripts exist. Mitigation: item 85 gated behind Plan B + Plan D item 45; add scripts first, then the recipe.
- Rollback: revert per-item commits. Placeholder edits are additive; `agents-optional` removal is git-tracked and recoverable; dedup report is analysis-only.

## Handoff Notes

- Do NOT start until Plan A is committed; do NOT wire item 85 until Plan B + Plan D item 45 are in place.
- Agent provenance is OUT OF SCOPE here (moved to Plan D); never hand-edit generated agent files.
- Confirm `agents-optional` is git-tracked and per-file-diffed before any `git rm`.
- Enumerate placeholder line counts first; expand only genuinely-thin docs in place; never move/delete or pad adequate docs.
- Item 80 is analysis-only; do not move or delete during this plan.
- implementer means implementer agent handoff using OpenCode command: /implement
