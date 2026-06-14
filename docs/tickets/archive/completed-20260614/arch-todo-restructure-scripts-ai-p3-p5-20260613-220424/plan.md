# Architecture Plan — Restructure scripts/ai by Role/Risk (P3–P5)

- Ticket: none
- Source: continuation of `docs/tickets/arch-todo-restructure-scripts-ai-by-role-risk-20260613-214236/plan.md`
- Generated: 20260613-220424
- Plan folder: docs/tickets/arch-todo-restructure-scripts-ai-p3-p5-20260613-220424/

## Context

P0–P2 are implemented and verified (additive, no file moves):

- `scripts/ai/MANIFEST.md` — full role/risk inventory of all 42 top-level scripts, 11 `lib/*` modules, 18 `ai-search/*` modules, plus a document-only "P2 target path mapping".
- `tests/php/ScriptsAiManifestTest.php` — 5 tests; asserts inventory completeness and that P2 stays document-only (`scripts/ai/bin` and `scripts/ai/internal` must not exist).

This plan covers the approval-gated file-moving phases P3–P5. The user has approved
executing file moves but requires this reviewed architect plan before implementation.

## Hard Constraints (collected from repository evidence)

1. **Sibling sourcing.** Every public script sources
   `source "$(dirname "${BASH_SOURCE[0]}")/common.sh"` and reaches PHP tools via
   `../../tools/ai/...`. `common.sh` resolves `lib/` and
   `tools/ai/sh-introspect.php` via `$(dirname "${BASH_SOURCE[0]}")`. Relocating a
   file breaks these relative paths unless resolution is re-anchored to the
   `scripts/ai` root.
2. **Bidirectional registry enforcement.** `tools/ai/validate-install-surface.php`
   (≈685–732) enforces a 1:1 match between `scripts-pack` entries in
   `tools/ai/install/packs.php` and entries in
   `tools/ai/install/script-registry.php`. New/moved `.sh` must appear in both.
3. **Unregistered-script error.** `validate-install-surface.php` (≈660) errors if a
   doc references `scripts/ai/<name>.sh` not in the registry. The regex does NOT
   match subdirectory paths (`scripts/ai/bin/read/x.sh` is not matched).
4. **Generated hash-bearing manifests.** `.ai/catalog.json`,
   `.ai-install-manifest.json`, `.ai/manifest.lock.json` carry hashes.
   `docs/ai/script-registry.json` is generated via
   `php tools/ai/ai.php registry:export --output docs/ai/script-registry.json`.
   Canonical source is `tools/ai/install/script-registry.php`. Regeneration is
   approval-gated generated-artifact work; never hand-edit hashes.
5. **~100+ references** to `scripts/ai` across `.ai/catalog.json`,
   `.ai-install-manifest.json`, tooling (`ai_catalog_lib.php`, `packs.php`, install
   checks), and tests (`ShIntrospectIndexTest` globs `scripts/ai/*.sh` and asserts
   top-level coverage; 16 `assertCount` references in tests).

## Chosen Strategy: Delegating-Shim-at-Root

Keep every `scripts/ai/<name>.sh` as the **registered, shipped artifact** (becoming
a thin shim) and relocate the real implementation to `scripts/ai/bin/<role>/`. Move
internals to `scripts/ai/internal/{lib,search}/` with matching facade source-path
updates in the same slice.

Rationale vs. real-move-with-path-rewrite:

- Preserves the root public contract the registry, packs, catalog `$rootScriptMap`,
  ~100+ references, hashed manifests, and `*.sh` test globs depend on.
- Keeps per-slice blast radius tiny; each slice is independently revertible.
- Avoids editing every consumer reference and every generated manifest for each move.
- The shim re-anchors `common.sh`/`../../tools/ai` resolution to the `scripts/ai`
  root, so relocated implementations resolve dependencies correctly.

Shim contract: each root `scripts/ai/<name>.sh` resolves the `scripts/ai` root, then
`exec`s its relocated implementation, preserving all args, env, stdin/stdout, exit
codes, and the `--introspect`/`--help` contract (which must continue to report the
*implementation's* own contract, not the shim's).

## In Scope

- P3: relocate internal-only modules (`ai-search/*` then `lib/*`) into
  `scripts/ai/internal/{search,lib}/`, updating facade source paths in the same slice.
- P4: relocate public implementations into `scripts/ai/bin/<role>/`, one role group
  per slice, leaving a delegating shim at the root name.
- P5: finalize MANIFEST, retire P2 "must not exist" assertions, full consistency sweep.
- Registry + packs + manifest regeneration via official generators per slice.

## Out Of Scope (Things To Avoid)

- Do not hand-edit hashes in any generated manifest.
- Do not break the 1:1 registry↔packs invariant within a slice.
- Do not change a script's observable behavior, args, exit codes, or
  `--introspect`/`--help` output.
- Do not move more than one role group per P4 slice.
- Do not remove the root script names (they remain the public contract as shims).
- Do not edit unrelated docs/tests outside the migrating group.

## Slice Breakdown

### P3 — internal modules (lowest risk; not registered as public commands)

- [x] P3a: Relocate `scripts/ai/ai-search/*.sh` → `scripts/ai/internal/search/*.sh`;
      DONE — 18 pure renames; `ai-search.sh` `_search_dir` + shellcheck directives
      updated; `ScriptsAiManifestTest` glob + assertions updated; MANIFEST table
      updated; `repo-required-tools.md` regenerated; stale comment in
      `script-registry.php` fixed. Verified: `--introspect` (22 modes), live search,
      doctor, all validators, `composer test:fast` (697 tests).
      update `ai-search.sh` (and `00-bootstrap.sh`) module resolution to the new path
      anchored at the `scripts/ai` root. Verify `ai-search.sh --introspect`, doctor
      mode, and a representative search across changed/tracked modes.
- [x] P3b: Relocate `scripts/ai/lib/*.sh` → `scripts/ai/internal/lib/*.sh`; update
      DONE — 11 renames; `common.sh` `_AI_COMMON_LIB_DIR` + shellcheck directives
      updated; `tools/ai/install/packs.php` dir entry updated; `sh-introspect`
      `15-index.php` glob updated; `repo-required-tools.md` regenerated; install
      catalog regenerated via `install-docs --write` (no tracked-manifest delta —
      lib ships as a `dir` entry). Verified: common-source test (4/4), validators,
      `composer test:fast` (697).
      `common.sh` `_AI_COMMON_LIB_DIR` to the new path. Verify multiple
      common.sh-sourcing scripts still load and `--introspect` works.
- [x] P3c: Update `ScriptsAiManifestTest` private-module globs to the new internal
      DONE — manifest test globs point at `internal/{lib,search}`; P0/P1/P2
      "must-not-exist" assertions replaced with P3-aware assertions; MANIFEST
      private-module table + "Migration status" section updated.
      paths and remove/replace the "internal must not exist" assertion; update
      MANIFEST private-module table.

Things to avoid in P3: do not touch `bin/` yet; do not alter public root names.

P3 acceptance:

- [ ] Internal modules live under `scripts/ai/internal/{search,lib}/`.
- [ ] `ai-search.sh` and all common.sh-sourcing scripts run unchanged (behavior + `--introspect`).
- [ ] `validate-install-surface`, `validate-ai-catalog`, `validate-catalog-drift`,
      `validate-generated-artifacts`, `ai-doc-check --check`, `composer test:fast` pass.

### P4 — public role-group addressability via additive delegating shims (REVISED: Option B)

DESIGN DECISION (architect, evidence-backed): use **Option B "inverted shim"**, NOT
the original "real-move + re-anchor" (Option A). Rationale: nearly every public
script anchors `common.sh` (sibling), PHP tools (`../../tools`), and self-introspect
(`exec php sh-introspect.php "${BASH_SOURCE[0]}"`) relative to its OWN location
(e.g. `scripts/ai/preview-file.sh:8-19`). Moving the real file breaks all three and
forces per-script re-anchoring + registry/packs/manifest churn for ~30 scripts.
Option B keeps the canonical impl at the root path (untouched) and adds additive
`scripts/ai/bin/<role>/<name>.sh` thin shims that `exec` the root impl. This delivers
role/risk addressability with near-zero blast radius, zero registry changes, and
byte-identical `--introspect` contracts.

Non-goal: the canonical file STAYS at the root path. `scripts/ai/bin/<role>/` is a
delegating alias tree, not the new home of the implementation.

#### Verbatim shim template (`scripts/ai/bin/<role>/<name>.sh`)

```bash
#!/usr/bin/env bash
# GENERATED DELEGATING SHIM — DO NOT EDIT.
# Role/risk alias for the canonical implementation at scripts/ai/<name>.sh.
set -euo pipefail

_ai_shim_dir="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
_ai_root="$(CDPATH= cd -- "$_ai_shim_dir/../.." && pwd)"
_ai_impl="$_ai_root/<name>.sh"

# Introspection/help must report the IMPLEMENTATION's contract, not the shim's.
if [[ "${1:-}" == "--introspect" || "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    _ai_tool="$_ai_root/../../tools/ai/sh-introspect.php"
    if [[ -f "$_ai_tool" ]] && command -v "${PHP_BIN:-php}" >/dev/null 2>&1; then
        if [[ "${1:-}" == "--introspect" ]]; then
            exec env AI_OUTPUT=json "${PHP_BIN:-php}" "$_ai_tool" "$_ai_impl"
        fi
        exec "${PHP_BIN:-php}" "$_ai_tool" --format=help "$_ai_impl"
    fi
fi

exec bash "$_ai_impl" "$@"
```

#### Registry / packs / manifest recipe (minimal)

- Registry (`script-registry.php`): NO change. `bin/` shims are intentionally
  UNREGISTERED convenience aliases. `source_path`/`installed_path` stay at root.
- Packs (`packs.php`): ship the whole `bin/` tree with ONE `dir` entry
  (`['type'=>'dir','source'=>'scripts/ai/bin','target'=>'scripts/ai/bin',
  'merge_strategy'=>'replace','required'=>true]`). A `dir` source does not match the
  `.sh` 1:1 suffix test (`validate-install-surface.php:697/700`), so the registry↔packs
  invariant stays green — same mechanism as `internal/lib`.
- Do NOT add individual `bin/<role>/<name>.sh` `file` entries (would force registry
  1:1 churn).
- Manifests: `script-registry.json` is unchanged (`registry:export` is a no-op check).
  When the `bin` dir is added to packs, regenerate via `php tools/ai/ai.php
  install-docs --write` then `php tools/ai/repo-tool-inventory.php` if affected, then
  `php tools/ai/validate-generated-artifacts.php`. Never hand-edit hashes.

#### P4 ordered slices

- [x] P4.0: Fix `internal/search` packs gap — DONE. Added `scripts/ai/internal/search`
      `dir` entry to scripts-pack (packs.php). Regenerated INSTALL-CATALOG.md (51->52
      items) + repo-required-tools via official generators. All validators + 697 tests
      pass. No registry change.
- [x] P4.1: PROOF CHUNK — DONE. Added `scripts/ai/bin/read/preview-file.sh` shim
      (verbatim template). Source-repo-only (not in packs). Verified shim==root:
      `--introspect`, `--help`, and functional output all BYTE-IDENTICAL to root.
      shellcheck consistent with repo CDPATH idiom; 697 tests + doc-check pass. P3c
      guard test replaced with P4-aware `testP4BinShimsAreDelegatingAliasesOfRootImpls`.
- [x] P4.2: DONE — added 13 more `scripts/ai/bin/read/*.sh` shims (14 total for the read
      group); added the single `scripts/ai/bin` `dir` packs entry; regenerated manifests
      (INSTALL-CATALOG 52->53 items). All 14 shims introspect-identical to roots;
      validators + 697 tests + doc-check pass. MANIFEST P4 alias contract documented.
- [x] P4.3: DONE — added shims for all remaining role groups under
      `scripts/ai/bin/{context,verify,edit,admin,hooks}/` (27 shims: context 10,
      verify 7, edit 2, admin 4, hooks 4). 41 total shims = all 41 public scripts.
      All introspect-identical to roots; no registry change (bin already shipped via
      the `bin` dir entry). Validators + 697 tests + doc-check pass.
- [x] P4.4: DONE — `script-registry.php` has NO `source_path`/`installed_path` change
      (only the P3 comment fix); `docs/ai/script-registry.json` unchanged;
      `registry:export --check` = OK (no drift); 0 `bin/` refs in the registry.

Per-slice acceptance:

- [ ] `diff <(bash scripts/ai/bin/<role>/<name>.sh --introspect) <(bash scripts/ai/<name>.sh --introspect)` is empty (contract-transparent alias).
- [ ] Shim functional run output matches the root run.
- [ ] `validate-install-surface` 1:1 invariant green; `ShIntrospectIndexTest` green (`bin/` not indexed, top-level unchanged).
- [ ] `git diff --name-status` shows only `scripts/ai/bin/**` additions (+ at most the packs `dir` line and regenerated manifests for the shipping slice).
- [ ] No registry entry or existing `--introspect` contract changed.

### P5 — finalize

- [x] P5a: DONE — all 41 public scripts have role/risk shims; `common.sh` correctly has
      none (sourced facade). MANIFEST "Migration status" marks P3/P4/P5 complete.
- [x] P5b: DONE — no orphan shims; full validator sweep (7 validators) + full serial
      `composer test` (697 tests) + `ai-doc-check --check` all pass.
- [x] P5c: DONE — MANIFEST "P4 bin/ alias contract" documents that the canonical impl
      STAYS at the root path; `bin/` shims are additive aliases; removing root names
      requires a separate approved deprecation phase.

## Verification Plan (per slice)

- `php tools/ai/validate-install-surface.php`
- `php tools/ai/validate-ai-catalog.php`
- `php tools/ai/validate-catalog-drift.php`
- `php tools/ai/validate-generated-artifacts.php`
- `bash scripts/ai/ai-doc-check.sh --check`
- `composer test:fast`
- `bash scripts/ai/<each-changed-script>.sh --introspect` (contract preserved)
- `git diff --name-status --find-renames HEAD` (confirm intended moves only)

## Risks And Rollback

- Risk: relocated module breaks dependency resolution. Mitigation: re-anchor to the
  `scripts/ai` root in the same slice; verify `--introspect` + a live run.
- Risk: registry↔packs drift fails install-surface. Mitigation: edit both in the same
  slice; regenerate `script-registry.json` immediately.
- Risk: stale hashes in generated manifests. Mitigation: regenerate via official
  generators only; never hand-edit.
- Risk: test globs assume top-level-only scripts. Mitigation: shims keep top-level
  `*.sh` present; update private-module globs explicitly in P3c.
- Rollback: each slice is one commit; revert the commit. Root shims keep old names
  working, so a reverted slice restores prior behavior without consumer changes.

## P3 Review Outcome (resolved)

Reviewer verdict: approve-with-nits. All items resolved:

- Major (staging hygiene): the `ai-search`→`internal/search` rename had become
  unstaged (after an earlier `git reset`), so `repo-tool-inventory.php` regenerated
  `repo-required-tools.md` from a stale index. Fixed by staging the full change set
  (29 renames detected) and regenerating; `validate-generated-artifacts` now passes
  with the index consistent.
- Nits (stale comments) fixed in `ai-search.sh` (lines 6, 36),
  `internal/search/00-bootstrap.sh:16`, `MANIFEST.md` facade row,
  `tools/ai/sh-introspect/22-source-inline.php:11`, `tests/php/ShHelpTest.php:44`.
- Pre-existing gap to track during P4/P5: `packs.php` ships the `ai-search.sh` facade
  but NOT its module dir (`internal/search`); this predates P3. `internal/lib` IS
  shipped via the renamed `dir` entry. Verify installed-project parity for search
  modules during P4/P5.

## Handoff Notes

- Implement P3 first (P3a → P3b → P3c), one slice/commit each, verifying before the next.
- Then P4 one role group per slice in the stated order.
- Then P5 finalize.
- Treat every manifest/registry regeneration as approval-gated generated-artifact work.
- implementer means implementer agent handoff using OpenCode command: /implement
