# Architecture Plan — Shell lint cleanup (bin/ shims + internal/ modules)

- Ticket: none
- Source: ai-verify shell-lint reproduction (this session), 3-slice decomposition
- Generated: 20260614-230848
- Plan folder: docs/tickets/arch-todo-shell-lint-cleanup-bin-internal-20260614-230848/
- Status: **Todo** (unchecked)
- Rank: Slice 3 of 3
- Risk: **LOW** (lint-only; no behavior change; idiom is a false-positive)

## Context

Reproduced the shell-lint failures directly with bounded-scope runs (full `ai-verify .` times out
> 240s with no incremental output — do NOT run it whole; scope the linters). Findings:

- **shellcheck: 98 findings = SC1007 ×85 + SC2016 ×13.**
  - SC1007 ×85: ALL in the 43 generated `bin/` shims, every one from the identical idiom
    `_ai_shim_dir="$(CDPATH= cd -- ...)"` / `_ai_root="$(CDPATH= cd -- ...)"` (bin/.../ai-verify.sh:12-13
    pattern). `CDPATH=` is a deliberate empty-value command prefix (correct bash; neutralizes CDPATH
    for `cd`). shellcheck SC1007 mis-reads the `= ` as an accidental assignment-space. **False positive.**
  - SC2016 ×13: concentrated in 2 internal modules —
    `internal/repomix-context-tree/40-build-pack.sh` (~12 hits, lines 25-30, 223-246) and
    `internal/search/45-results-rg.sh:85`. Single-quoted `$...` is almost certainly intentional
    (literal `$` in jq/awk/rg format strings). Needs per-line confirmation.
- **shfmt: 0 real `.sh` diffs.** The only shfmt "diffs" were stray `*.orig` files (merge/backup
  leftovers) that were deleted during this session. Real `.sh` files are shfmt-clean.
- **semgrep: not the source of these 56; the user's "56" = the shellcheck SC1007/SC2016 set on
  bin/ + internal/.** Confirm by re-running scoped semgrep in step 1.

CRITICAL: the `bin/` shims carry `# GENERATED DELEGATING SHIM — DO NOT EDIT`, but research found
**no generator script exists** in tools/ or scripts/ — they were authored once (commit aad531a) and
are maintained as committed static artifacts. So the SC1007 fix is applied to the shim files
directly (and to the README/MANIFEST "do not edit" wording if needed), since there is no generator
to re-run. The idiom is identical across all 43, so the fix is mechanical and uniform.

## Goal / Acceptance Criteria

- AC-1: Scoped shellcheck on `scripts/ai/bin scripts/ai/internal` reports 0 SC1007 and 0 SC2016
  (either by fixing the idiom or by a justified inline `# shellcheck disable=` directive).
- AC-2: No behavior change — every shim still resolves `_ai_root` identically and execs the same
  canonical impl. Prove by running 2-3 shims `--help`/no-arg and diffing resolved paths.
- AC-3: SC2016 lines are confirmed intentional and silenced with a narrow per-block
  `# shellcheck disable=SC2016` + a one-line comment explaining the literal `$`, OR refactored if
  genuinely wrong.
- AC-4: Stray `*.orig` files removed and ignored (add `*.orig` to .gitignore if not present).
- AC-5: shfmt on real `.sh` stays clean.
- AC-6: Scoped `ai-verify` (or direct linters) over the two dirs passes within a bounded timeout.

## Steps

1. Re-confirm the exact failing set with scoped, time-boxed runs (NOT full ai-verify):
   - `find scripts/ai/bin scripts/ai/internal -name '*.sh' -print0 | xargs -0 shellcheck -x -e SC1091`
   - scoped semgrep over the same two dirs to confirm it contributes nothing new.
2. SC1007 fix (43 shims, uniform): the smallest fix that satisfies shellcheck without behavior
   change. Preferred: replace `CDPATH= cd --` with `cd --` guarded by `CDPATH=''` set on its own
   line, OR add `# shellcheck disable=SC1007` immediately above each of the two lines with a comment
   that `CDPATH=` is an intentional empty-prefix. Decide ONE approach and apply uniformly.
   - Since these are "generated" but have no generator, also update bin/README.md:14 + MANIFEST.md
     wording only if the chosen fix changes what "do not edit" means (e.g. note the canonical idiom).
3. SC2016 fix: read the ~13 lines; if `$` is a literal in a jq/awk/printf format, add a scoped
   `# shellcheck disable=SC2016` for the block with an explanatory comment.
4. Delete any `*.orig` strays; ensure `.gitignore` covers `*.orig`.
5. Verify (below) with bounded timeouts.

## Things To Avoid

- Do NOT run `ai-verify .` whole (times out > 240s, no incremental output). Scope to the two dirs
  and use per-command timeouts (shellcheck/shfmt ~120s budget).
- Do NOT change shim runtime behavior. The `CDPATH=` prefix MUST keep neutralizing CDPATH for `cd`;
  any rewrite must be path-resolution-equivalent.
- Do NOT mass-disable shellcheck globally or weaken SHELLCHECK_ARGS for the whole repo.
- Do NOT hand-tune each shim differently — keep the 43 shims byte-uniform in the fixed region.
- Do NOT touch the canonical root scripts' behavior; this is lint-only on bin/ + internal/.

## Verification

- `find scripts/ai/bin scripts/ai/internal -name '*.sh' -print0 | xargs -0 shellcheck -x -e SC1091`
  → exit 0, zero SC1007/SC2016 (time-box 120s).
- `find scripts/ai/bin scripts/ai/internal -name '*.sh' -print0 | xargs -0 shfmt -d` → no diff.
- Behavior proof: pick `scripts/ai/bin/verify/ai-verify.sh --help` and one more shim; confirm they
  still delegate (resolved `_ai_root` unchanged). Compare against root impl `--help`.
- `git status` shows only intended shim/internal edits + .gitignore; no `*.orig` tracked.

## Rollback

Revert the lint edits; purely cosmetic/idiom changes with no functional effect, so rollback is
safe and isolated.
