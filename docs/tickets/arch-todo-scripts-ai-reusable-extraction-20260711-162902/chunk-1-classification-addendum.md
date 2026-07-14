# Chunk 1 — Classification Verification Addendum

- Parent plan: `docs/tickets/arch-todo-scripts-ai-reusable-extraction-20260711-162902/plan.md`
- Status: Chunk 1 read-only verification complete. Zero files changed under `scripts/ai/**`.
- Scope verified: the 3 flagged `[inferred]` scripts (`ai-install-coverage.sh`,
  `session-checkpoint.sh`, `sh-introspect.sh`) plus the 3 additional scripts affected by the
  user's Chunk-4 override decision (`ai-edit.sh`, `ai-rollback.sh`, `all_in_one.sh`).
- User decisions locked in for this ticket (recorded here for traceability):
  1. Follow the top-of-plan "SOURCE OF TRUTH" override, not the body classification table:
     `ai-edit.sh`, `ai-rollback.sh`, `sh-introspect.sh`, and `ai-verify.sh` + 5 wrappers +
     `internal/ai-verify/**` all move to the new repo (in addition to the REUSABLE bucket).
  2. `all_in_one.sh` moves and is renamed to `all-f-into-one.sh` in the new repo.
  3. `install-mandatory-tools.sh` stays in this repo unchanged (item 5 in the ticket's mandatory
     list refers to it, not to `ai-verify.sh`); it is not part of this migration.
  4. First pass to `/home/utmostcreator/Projects/agent-repo-tools` is a **fresh copy, no git
     history preservation** — history rewiring is deferred to when the toolkit is installed back
     into this project as a vendored dependency (Chunk 3/5's mechanism).

## Per-Script Findings

### `ai-install-coverage.sh` — confirmed KIT-SPECIFIC, stays

Full source is 25 lines: an `--introspect`/`--help` early guard plus one line of real logic:
`php tools/ai/validate-install-surface.php --strict`. That PHP tool validates this kit's own
install-surface coverage. Not part of the move list; MANIFEST.md's original classification
confirmed as-is.

### `session-checkpoint.sh` — reclassified REUSABLE (was BORDERLINE/pending)

Full source (39 lines) is a thin wrapper: `agent_session_init`, `snapshot_create`, `log_json` —
all from `internal/lib/{40-session,90-snapshot}.sh`, already-confirmed REUSABLE shared
dependencies. Writes to `.ai-logs/snapshots/`, a generic local evidence path, not
`.ai-install-manifest.json`/`policies/ai/policy.yaml`/`docs/tickets/**`. No kit-specific coupling
found. This closes the plan's open assumption and upgrades the classification to REUSABLE — this
matches the `NEW SOURCE REPO STRUCTURE` already listing `libexec/session-checkpoint` and
`hooks/agent/session-checkpoint`, so no structural change is needed there.

### `sh-introspect.sh` — confirmed BORDERLINE mechanism, moves per override, but see cross-cutting finding below

Full source (15 lines) is a thin delegate to `tools/ai/sh-introspect.php` (repo-root `tools/ai/`,
**not** under `scripts/ai/`). That PHP entrypoint is itself only a 62-line loader that
`require_once`s **18 numbered submodules** under `tools/ai/sh-introspect/`
(`00-constants.php` … `75-render-help.php`). `sh-introspect.sh`'s own bash logic has zero
kit-specific path coupling — the coupling is that its entire implementation lives outside
`scripts/ai/**` in a PHP tool tree the current `NEW SOURCE REPO STRUCTURE` does not mention at
all. See "Cross-cutting finding" below — this is not unique to `sh-introspect.sh`.

### `ai-edit.sh` / `ai-rollback.sh` + `internal/ai-edit/{10-helpers,30-parse,40-plan-apply,90-main}.sh` — reclassified REUSABLE in mechanism (was KIT-SPECIFIC), moves per override

Read all 5 files in full (830 lines total). Mechanism is generic: `ast-grep`/`comby`/`sd`/`patch`
modes, scope/bounds flags, dry-run/apply/verify safety gates, session manifests written to
`$REPO_ROOT/.ai-sessions/<id>/` via `internal/lib/90-snapshot.sh` (already-confirmed REUSABLE).
**Zero** references to `policies/ai/policy.yaml`, `.ai-install-manifest.json`, `docs/tickets/**`,
or `packages/ai-universal-rules/**` in any of the 5 files. The original plan body's rationale
("this kit's own governance model for AI-driven edits") is not supported by the actual source —
it is a generic snapshot-based guarded-edit tool. This is independent confirmation that the
user's override decision is evidence-backed, not merely a preference override.

One real coupling found: `ai-edit.sh --verify` (`internal/ai-edit/90-main.sh:173`) invokes
`"$SCRIPT_DIR/ai-verify.sh"` by sibling relative path. Since `ai-verify.sh` is also moving under
the override decision, this stays intact as long as both land in the same `libexec/` directory in
the new repo (already the case in `NEW SOURCE REPO STRUCTURE`).

### `all_in_one.sh` — reclassified REUSABLE (was KIT-SPECIFIC), moves + renames per override

Full source (151 lines) read. **`#!/bin/zsh`, not bash** — the only zsh script in scope; every
other target-repo script is `#!/usr/bin/env bash`. Mechanism: walks the CWD tree, prunes
`.git`/`node_modules`/`.venv`/`dist`/`build`/`.next`, writes one combined text file with
triple-backtick-wrapped `START FILE`/`END FILE` blocks, no-op macOS notification elsewhere. Zero
references to install/policy/catalog/`docs/tickets` anywhere. The original plan's inferred
rationale ("admin-role scripts... orchestrate the install/verify/catalog pipeline end-to-end") is
not supported by the actual source — it does none of that; it is a plain file-concatenation
utility comparable in spirit to repomix. Confirms the override is evidence-backed.

**Open item for Chunk 4 execution:** decide whether to port `all-f-into-one.sh` as zsh unchanged
(the toolkit would then not be 100%-bash) or rewrite it in bash for consistency with the rest of
`libexec/**`. Not decided here — flagging for the Chunk 4 execution step.

## Cross-Cutting Finding (new — not identified in the original plan body)

**Every REUSABLE-bucket script, `common.sh` itself, and all generated `bin/<role>/` shims share
an `--introspect`/`--help` early-guard pattern that hard-depends on `tools/ai/sh-introspect.php`
plus its 18 numbered PHP submodules under `tools/ai/sh-introspect/` — a repo-root `tools/ai/`
directory, not anything under `scripts/ai/`.**

Confirmed via `scripts/ai` grep for `sh-introspect.php`: 79 matches across every top-level
REUSABLE script, `common.sh` (lines 39/61), `internal/search/00-bootstrap.sh` and
`10-contract.sh`, and every file under `scripts/ai/bin/**` (the generated P4 shim tree). The
guard pattern is: `if [[ -f "$_ai_introspect_tool" ]] && command -v php ...; then exec ...; fi` —
when the PHP tool is absent, the check silently falls through and `--introspect`/`--help`
degrades to being treated as an ordinary positional argument instead of emitting the documented
JSON/help contract.

**The `NEW SOURCE REPO STRUCTURE` in the parent plan has no `tools/`-equivalent PHP directory
anywhere** — it is bash-only (`bin/`, `libexec/`, `lib/`, `hooks/`, `share/`). This dependency is
completely unaccounted for in the target repo design. Without a decision, every migrated script's
`--introspect`/`--help` guard will silently no-op once vendored into `agent-repo-tools`.

**This needs a decision before Chunk 4 file-moving proceeds for real** (not blocking a first
fresh-copy pass into the empty target repo, since the user's history/rewiring answer already
defers full correctness to a later "install back into this project" step, but the resulting files
in `agent-repo-tools` will not have working `--introspect`/`--help` until one of these is picked):

1. Port `tools/ai/sh-introspect.php` + its 18 submodules into the new repo (e.g. under a new
   `tools/`), making PHP a soft runtime dependency for `--introspect`/`--help` only (all other
   functionality stays PHP-free bash). Does not contradict the plan's "no forced npm/Composer
   install mechanism" rule (that rule is about the *install* channel, not an optional introspect
   feature), but is a new fact worth stating explicitly in the new repo's README.
2. Reimplement the introspector natively in bash for the new repo (bigger, separate engineering
   effort; not scoped by this ticket).
3. Drop the `--introspect`/`--help` early-guard convention for the moved scripts and rely only on
   each script's own curated `usage()` function where one exists (several REUSABLE scripts, e.g.
   `ai-rollback.sh`, already have one; others do not).
4. Copy the files as-is for now (guards degrade silently) and decide later, accepting the
   documented-contract gap until an explicit follow-up.

No option is selected here — this is intentionally left open per this repo's "do not invent
behavior" rule; the human/architect should pick one before Chunk 4's real population pass, or
accept option 4 explicitly for the first fresh-copy pass.

## Updated Classification Summary Deltas

| Script | Plan's original bucket | Verified bucket | Moves in Chunk 4? |
| --- | --- | --- | --- |
| `ai-install-coverage.sh` | KIT-SPECIFIC `[inferred]` | KIT-SPECIFIC `[confirmed]` | No |
| `session-checkpoint.sh` | BORDERLINE `[inferred]`, stay | REUSABLE `[confirmed]` | Yes (already in target tree) |
| `sh-introspect.sh` | BORDERLINE `[confirmed via MANIFEST]`, stay | BORDERLINE `[confirmed]`, moves per override | Yes (user override) — needs cross-cutting decision above |
| `ai-edit.sh` / `ai-rollback.sh` + `internal/ai-edit/**` | KIT-SPECIFIC `[inferred]` | REUSABLE mechanism `[confirmed]`, moves per override | Yes (user override) |
| `all_in_one.sh` | KIT-SPECIFIC `[inferred]` | REUSABLE mechanism `[confirmed]`, moves per override, renamed `all-f-into-one.sh` | Yes (user override) |
| `ai-verify.sh` + wrappers + `internal/ai-verify/**` | BORDERLINE `[confirmed]`, stay | unchanged (not re-read this pass; original 419-line read stands) — moves per override | Yes (user override) — still carries the 2 embedded kit-specific calls (`check_plan_status`, `is_ai_kit_source_repo`) that need handling at Chunk 4 execution time (strip, no-op, or hookify) |

AC-02 (re-verify `ai-install-coverage.sh`, `session-checkpoint.sh`, `sh-introspect.sh` to
`[confirmed]`) is satisfied by this addendum.
