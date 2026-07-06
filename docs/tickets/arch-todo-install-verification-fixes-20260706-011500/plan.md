# Architecture Plan — Install Verification Fixes (single-runtime verify, in-target CLI, wrapper scoping)

- Ticket: none
- Source: install verification session (this conversation) — 4 fresh installs (opencode,
  copilot, claude, full-governance) + 1 reinstall-with-backup, specificity 85/100
- Generated: 20260706-011500
- Plan folder: docs/tickets/arch-todo-install-verification-fixes-20260706-011500/
- Status: **Done** (P1, P2, P3 implemented and verified; see Acceptance Criteria)
- Risk: **MEDIUM** — changes touch the installer pack registry (what ships into every target)
  and two post-install validators (the `--verify-after` gate). Additive-only in intent:
  ship more files + relax false-negative required-path assertions. No agent/adapter content
  changes. Covered by re-installing all 4 profiles + source `composer test:fast`.

## Context

A full install verification pass (see conversation) installed 4 profiles into temp dirs and
found the flagship `full-governance` install is healthy end-to-end, but three real defects
break the **single-runtime** profiles (`opencode`, `copilot`, `claude`) and the documented
**in-target CLI** for every profile. Evidence is reproducible.

### Bug 2 (HIGH) — single-runtime `--verify-after` and validators always fail

`tools/ai/validate-install-surface.php` (L74) and `tools/ai/validate-ai-config.php` (L22)
both hardcode `tools/ai/validate-install-surface.php` into their "required target path" list
for the target-mode branch. But that file — and the entire validator toolset — only ships via
`target-tools-pack`, which is only in `full-governance`/`full`. So `opencode`, `copilot`,
`claude`, `dual`, `guarded`, `accelerated`, `minimal`, `standard`, `agents-only` all fail
`--verify-after` and both validators.

Reproduced (source validators run against each target):

```
validate-ai-config.php --target=/tmp/.../opencode  -> ERROR: missing required target AI config path: tools/ai/validate-install-surface.php
validate-install-surface.php --target=/tmp/.../copilot -> ERROR: required target install path missing: tools/ai/validate-install-surface.php
```

Root cause: the required-path list assumes full-governance shipping. It must instead assert
only paths that the target's own installed packs actually provide. Both validators already
read `.ai-install-manifest.json` (which records `packs` and `files`).

### Bug 3 (HIGH) — `php tools/ai/ai.php <cmd>` fatals inside a full-governance target

`ai.php` -> `install/core.php` -> `install/copilot-agent-renderer.php` (L7)
`require_once canonical-agent-frontmatter.php`, a file `target-tools-pack` never ships.
Full transitive closure of `core.php` that is MISSING from `target-tools-pack`
(confirmed present/absent against a shipped target):

- `tools/ai/install/canonical-agent-frontmatter.php`
- `tools/ai/install/claude-agent-renderer.php`
- `tools/ai/install/claude-agent-tool-registry.php`
- `tools/ai/install/claude-settings-merge.php`
- `tools/ai/install/selection-engine.php`
- `tools/ai/install/stack-registry.php`
- `tools/ai/install/stack-detection.php`
- `tools/ai/install/stack-project-doc.php`
- `tools/ai/install/permission-layers/` (16 files: core, compose, edit-surfaces, verify-tiers,
  language-overlays, script-tiers, stack-overlays, packs, pack-sets, rules, patterns,
  agent-spec, render-spec, compositions, render-adapters — plus any others in the dir)
- `tools/ai/compile-command-policy.php` (required at core.php L1678, but **guarded by
  `is_file()`** so it silently no-ops in-target; ship it anyway for parity/correctness)

Breaks documented POST-INSTALL commands: `advisor --all`, `preflight`, `placeholders`,
`verify`, `install`, `rollback --backup <id> --apply`. Standalone validators are unaffected
(they don't require `core.php`).

### Bug 1 (MEDIUM) — wrapper scripts silently ignore `--runtime`/`--profile`

`install-ai-kit.sh` only parses `--force`/`--strict-placeholders`; any other flag is dropped
and its bare value is captured as `PROJECT_NAME`. It then always calls the PHP installer with
hardcoded `--profile full-governance --runtime both`. So `install-opencode-kit.sh` /
`install-copilot-kit.sh` (which forward `--runtime opencode --profile opencode`) always produce
a full dual/triple-runtime install. No `install-claude-kit.sh` exists.

## Scope And Ownership

- **In scope**: `tools/ai/validate-install-surface.php`, `tools/ai/validate-ai-config.php`,
  `tools/ai/install/packs.php` (target-tools-pack file list), `install-ai-kit.sh`,
  optionally add `tools/ai/install-claude-kit.sh`. Focused tests under `tests/`.
- **Out of scope**: agent/adapter template content, permission composition logic, Claude
  parity feature work (tracked separately), any change to full-governance behavior.

## Slices

### P1 — Bug 2: pack-aware required-target-path checks (do first; smallest, highest value)

Make both validators derive their required-path list from what the target's manifest packs
actually ship, instead of hardcoding `tools/ai/validate-install-surface.php`.

- Keep the universal minimum that every profile ships: `AGENTS.md`,
  `docs/ai/project-context.md`, `docs/ai/POST-INSTALL.md`, `scripts/ai/ai-search.sh`,
  `.ai-install-manifest.json` (and for install-surface: `scripts/ai/preview-file.sh`).
- Only require `tools/ai/validate-install-surface.php` when the manifest indicates
  `target-tools-pack` is installed (check `manifest['packs']` contains it, or equivalently
  that the file is a managed entry in `manifest['files']`).
- Acceptance: `validate-ai-config.php --target` and `validate-install-surface.php --target`
  both pass for opencode/copilot/claude AND still pass for full-governance.

### P2 — Bug 3: ship core.php's full runtime closure in target-tools-pack

Add the missing files/dir to the `target-tools-pack` file list in `packs.php`, mirroring the
existing `$targetToolInstallerFiles` shape (`merge_strategy: replace`). Add the
`permission-layers` dir as a single `['type' => 'dir', ...]` entry. Mark `required: true`
for the fatal-chain files; `compile-command-policy.php` `required: true` too.

- Acceptance: inside a freshly installed full-governance target,
  `php tools/ai/ai.php preflight` and `php tools/ai/ai.php advisor --all` run without the
  `canonical-agent-frontmatter.php` fatal.

### P3 — Bug 1: wrapper arg forwarding + install-claude-kit.sh

- Fix `install-ai-kit.sh` to accept and forward `--runtime`/`--profile` (and pass them to the
  PHP installer) instead of hardcoding `full-governance`/`both` and misassigning bare values
  to `PROJECT_NAME`.
- Add `install-claude-kit.sh` mirroring the opencode/copilot wrappers.
- Acceptance: `install-opencode-kit.sh --target X` produces an opencode-only install
  (no `.github/agents`, no `.claude`); manifest `runtime == opencode`.

## Things To Avoid

- Do not weaken either validator's real invariants — only remove the false-universal
  `validate-install-surface.php` requirement for profiles that legitimately don't ship it.
- Do not change full-governance shipping semantics beyond adding the missing closure files.
- Do not touch agent templates, permission-layer composition logic, or Claude feature parity.
- Do not delete or rewrite the wrapper scripts wholesale; make the minimal arg-forwarding fix.
- Keep each slice's diff reviewable; P2's packs.php addition is a list edit, not a refactor.

## Verification Ladder

1. Focused: re-run source validators `--target` against all 4 temp installs.
2. Reinstall all 4 profiles fresh; confirm `--verify-after` passes for single-runtime too.
3. In-target: `php tools/ai/ai.php preflight` + `advisor --all` inside full-governance target.
4. Wrapper: dry-run/real install via `install-opencode-kit.sh`; assert scoped output.
5. Regression: `composer test:fast` in source repo (baseline: ~10 known pre-existing failures
   per the Claude-parity ticket note — confirm count unchanged).

## Acceptance Criteria

- [x] P1: opencode/copilot/claude targets pass both validators AND `--verify-after`
  (fresh reinstalls of all three with `--verify-after` printed all three "validation passed"
  lines + "install complete"; previously all failed on the missing
  `tools/ai/validate-install-surface.php` required path).
- [x] P1: full-governance still passes both validators (verified in-target `--strict`).
- [x] P2: `ai.php advisor --all` (exit 0), `preflight`, `verify --changed` (exit 0) all run
  in-target without the `canonical-agent-frontmatter.php` fatal. Missing closure now shipped
  (canonical-agent-frontmatter, claude renderers/registry, claude-settings-merge, selection-engine,
  stack-registry/detection/project-doc, `permission-layers/` dir, compile-command-policy.php).
- [x] P3: `install-opencode-kit.sh`/`install-copilot-kit.sh` already scoped correctly (they
  forward to the thin `tools/ai/install-ai-kit.sh` that execs PHP with `"$@"` — the original
  Bug 1 report conflated that thin wrapper with the hardcoded root `install-ai-kit.sh`). Added
  the missing `tools/ai/install-claude-kit.sh` (dry-run: `profile=claude runtime=claude-code`,
  targets `.claude/agents`). Also made the root `install-ai-kit.sh` accept and forward
  `--target`/`--profile`/`--runtime`/`--project-name` (backward compatible; shellcheck clean).
- [x] Regression: no net regression. Serial `composer test` failure count is identical with my
  changes stashed vs applied (16/16); the failing set is non-deterministic across runs and driven
  by pre-existing shared-generated-artifact test-isolation flakes plus untracked in-progress
  `ai-verify-html.sh`/`script-registry.md` work (NOT my files). Every candidate failure that
  touched my code (`CliToolsTest::testValidateAiConfig*`) was proven pre-existing by reverting
  only `validate-ai-config.php` and observing the identical `.ai-logs/verify/` error. Focused
  suites pass in isolation: `InstallerSafetyTest` 68/68, installer+validator filter 96/96.

## Note On Pre-Existing Working-Tree Changes (flagged, not touched)

The working tree already contained unrelated in-progress work from other tickets that I did NOT
modify: `packages/ai-universal-rules/docs/INSTALL-CATALOG.md`, `scripts/ai/internal/ai-verify/90-run.sh`,
and untracked `scripts/ai/ai-verify-html.sh`, `scripts/ai/internal/ai-verify/*.sh`, `tests/shell/*.bats`,
and two other `docs/tickets/arch-todo-*` dirs (android-kmp-verify-lane, safe-language-verify-scripts).
These are the source of the pre-existing serial test failures.
