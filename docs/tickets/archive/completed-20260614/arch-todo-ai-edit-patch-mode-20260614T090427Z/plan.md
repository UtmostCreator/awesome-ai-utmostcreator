# Plan: ai-edit.sh patch mode + count-matches fix + patch guard

Status: Todo
Created: 2026-06-14T09:04:27Z
Risk: medium (guarded-mutation script; new file-system write surface)
Owner path: scripts/ai/ai-edit.sh

## Context

A proposal requested six additions to `scripts/ai/ai-edit.sh`. Research against the
current tree found three already exist or are redundant:

- rollback mode: ALREADY EXISTS (`scripts/ai/ai-rollback.sh` + `internal/lib/90-snapshot.sh`)
- `--schema`: redundant with existing `--introspect` (`ai.edit/v1` contract)
- secret excludes: partially exist in `default_excludes` for rg-based modes

Three are genuine, evidence-backed wins (scope confirmed with user):

1. `patch` mode — accept a unified diff from file or stdin, validate, preview, apply transactionally.
2. Fix `rg -c` -> `rg --count-matches` (line 454) so `--max-replacements` is honest.
3. Patch-specific path/secret/binary guard (a patch can target any path).

## Source evidence

- `scripts/ai/ai-edit.sh:454` — `rg -c` undercount bug.
- `scripts/ai/ai-edit.sh:303-315` — `default_excludes` is the existing pattern source.
- `scripts/ai/ai-edit.sh:519-644` — mode dispatch `case` block.
- `scripts/ai/ai-edit.sh:248-268` — `finish()` reuse target (manifest/JSON/snapshot/verify).
- `tests/scripts/ai/test-ai-edit.sh` — test harness (temp git repos, tool-availability gates).

## Steps

1. Fix `rg -c` -> `rg --count-matches` at line 454. Parsing loop unchanged
   (`path:count` format identical). Comment updated to reflect match-count semantics.

2. Add patch helpers (near other helpers, before `case "$mode"`):
   - `patch_path=""`, `patch_file=""`, `patch_changed_files_json='[]'` state vars
     initialized alongside existing state (after line ~561).
   - `patch_materialize INPUT`: `-` reads stdin, else copies file; fails on missing/empty.
   - `patch_changed_paths`: derive target paths from `git apply --numstat`,
     handling rename `old => new` (take new), dequoting, dropping `/dev/null`.
   - `patch_guard_paths`: block absolute (`/*`), parent-escape (`../`, `*/../*`),
     `.git`/`.git/*`, and any path matching the secret/binary deny list
     (derived from `default_excludes` plus binary image/archive/db extensions).
   - `patch_plan`: materialize -> guard -> `git apply --check` (preflight) ->
     count files -> enforce `max_files` -> build `planned_json`
     (`{path, replacements:null, bytes:null, operation:"patch"}`).
   - `patch_apply`: `git apply --whitespace=warn "$patch_file"`.

3. Add `patch)` case to dispatch (after `sd)`):
   - `patch PATCH_FILE|- [root] [flags]`; require >=1 arg.
   - `parse_tail` for shared flags.
   - `patch_plan`; on apply: clean-tree guard + `snapshot_create pre-edit` + `patch_apply`,
     else `finish "dry_run" 0`.
   - Falls through to existing `save_diff_artifacts` / verify / `finish "applied"`.

4. Update `usage()`: add patch to Usage + Modes; note stdin via `-`.

5. Add tests to `tests/scripts/ai/test-ai-edit.sh`:
   - patch dry-run plans changed files, does not modify (needs git+jq only).
   - patch apply modifies file (needs git+jq; `git apply` always present with git).
   - patch with unsafe path (absolute / `.git/`) returns `blocked`.
   - patch that does not apply cleanly returns `blocked`.
   - patch targeting `.env` returns `blocked`.

## Things to avoid

- Do NOT add rollback mode (already exists; would regress the superior design).
- Do NOT add `--schema` (redundant with `--introspect`).
- Do NOT touch `bin/edit/ai-edit.sh` (generated delegating shim).
- Do NOT relocate the implementation or change common.sh sourcing.
- Do NOT weaken existing sd/ast-grep/comby paths or their tests.
- Keep the change within `scripts/ai/ai-edit.sh` + its test file.
- Do not use `git apply -R` based rollback (proposal's inferior idea).

## Acceptance criteria

- `rg --count-matches` replaces `rg -c`; existing sd tests still pass; a multi-match-per-line
  case now counts every match.
- `ai-edit.sh patch FILE . --dry-run` returns `status:dry_run` with planned paths, no mutation.
- `ai-edit.sh patch - . --apply < file` applies the diff and returns `status:applied`.
- Unsafe paths (absolute, `../`, `.git/`, secret/binary) return `status:blocked`.
- Non-applying patch returns `status:blocked` with error referencing the check log.
- `--introspect` still lists modes (now including `patch`); existing tests green.
- `bash tests/scripts/ai/test-ai-edit.sh` passes.

## Verification

- `bash tests/scripts/ai/test-ai-edit.sh` (focused)
- `bash -n scripts/ai/ai-edit.sh` (syntax)
- `shellcheck scripts/ai/ai-edit.sh` if available
- `bash scripts/ai/ai-edit.sh --introspect | jq .modes` (contract smoke)
