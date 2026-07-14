# Todo: Restructure `tools/ai/` into the layered `ai/` tree (physical reorg, in-place replace)

Status: **PLAN ONLY — no code moved.** Awaiting review + resolution of the open decisions in
"Blockers / decisions required" before Phase 1 begins.

## Goal (scoped)

Move the AI-kit source tree from `tools/ai/` into the proposed top-level `ai/` layout
(`ai/bin`, `ai/src`, `ai/config`, `ai/resources`, `ai/generated`, `ai/scripts`, `ai/tests`),
**preserving behavior byte-for-byte**. Per the confirmed decisions:

- **Depth: physical reorg only** — files keep their current *procedural* PHP (function libraries,
  `aiRun*()`/`aiInstaller*()` etc.). No function→class conversion, no namespace introduction, no
  PSR-4 autoloading. Only file locations and `require`/`include`/invocation paths change.
- **Placement: replace `tools/ai` in place** — the new tree becomes the source of truth and
  `tools/ai/` is deleted once every caller is repointed.
- **Execution: plan-first** — this document; implementation is gated on approval.

## Current reality (proven this session)

- **161 PHP files under `tools/ai/`**, all procedural. Entry point `tools/ai/ai.php` loads modules
  via `require_once __DIR__ . '/relative/path.php'` (evidence: `tools/ai/ai.php:5-28`). All internal
  loads are `__DIR__`-relative, so **any regrouping that changes a file's relative position to the
  file that requires it breaks that require** and must be rewritten.
- **No PSR-4 for source.** `composer.json` autoloads only `Tests\ → tests/php/`. The kit runs by
  direct `php tools/ai/<file>.php` invocation, not autoloading.
- **Config/data is colocated with code today:** `tools/ai/install/registry/*.{yaml,json}`,
  `tools/ai/rules/agnostic-leak-rules.json`, `tools/ai/tools/**` (tool-guide `.md`),
  `tools/ai/known-kit-checksums.json`. The target splits these into `ai/config`, `ai/resources`,
  `ai/generated`.
- **`tools/ai` is a PUBLISHED INSTALL PATH, not just an internal dir.** It is referenced as an
  install/target contract in generated + shipped surfaces (see blast radius below).

## Blast radius (proven this session — evidence-backed counts)

`rg "tools/ai"` excluding `vendor/`, `examples/**/vendor`, and `tools/ai/` itself:
**439 files / 3203 lines** reference the path. By group (file counts):

| Group | Files | Nature of reference | Risk |
|---|---|---|---|
| `docs/` | 153 | Doc mentions, `php tools/ai/ai.php …` examples | Low (text) |
| `tests/php/` | 74 | `require_once …/tools/ai/*.php` **and literal path assertions** | **High** |
| `scripts/` | 54 | Shell wrappers invoking `php tools/ai/*.php` | Med |
| `packages/**` | 52 | **Shipped install contract** (`catalog.json` `"path":"tools/ai/ai.php"`, templates) | **High + edit-denied** |
| `.github/` | 30 | Copilot adapter allowlists / workflows | **High (generated)** |
| `.opencode/` | 21 | `opencode.jsonc` permission keys `php tools/ai/ai.php …`, `bash tools/ai/*.sh` | **High (generated)** |
| `.claude/` | 21 | `settings.json` Bash allowlist `Bash(php tools/ai/ai.php …)` | **High (generated)** |
| root files | ~9 | `install-ai-kit.sh`, `justfile`, `composer.json`, `.gitignore`, `opencode.jsonc`, `llms.txt`, README/​SUPPORT/​PLACEHOLDERS `.md` | Med–High |
| `.ai-install-manifest.json` | 1 (10 lines) | Installed-file map w/ `"source":"tools/ai/…"` + target paths | **High** |
| `schemas/`, `handoff/`, `common-vocabulary/`, `___ARCHITECTURE_2.0/`, `v0.5/v0.6` | ~15 | Mixed docs/config | Low–Med |

Key concrete shapes (evidence):
- `tests/php/bootstrap.php:6` → `require_once dirname(__DIR__,2) . '/tools/ai/ai_catalog_lib.php'`.
- `tests/php/LocalManifestTest.php:31-32,101-103` → both **requires** `tools/ai/install/core.php`
  AND **asserts the literal strings** `'tools/ai/install/core.php'`, `'tools/ai/install/planner.php'`,
  `'tools/ai/commands/install_workflow.php'`. Path assertions must be updated in lockstep with moves.
- `install-ai-kit.sh` (root) hardcodes `php tools/ai/ai.php`, `php tools/ai/install-ai-kit.php`,
  `php tools/ai/validate-install-surface.php`, and probes `$TARGET/tools/ai/advisor/scorer.php`.
- `.opencode/opencode.jsonc:124,149-150` permission keys: `"bash tools/ai/*.sh *"`,
  `"php tools/ai/ai.php install * --dry-run"`.
- `.claude/settings.json:86-93` Bash allowlist entries: `Bash(php tools/ai/ai.php …)`.
- `packages/ai-universal-rules/catalog.json:3` `"generated_by":"php tools/ai/generate-ai-catalog.php"`
  and `:1960` `"path":"tools/ai/ai.php"` — this file is **generated** and lives under the
  **edit-denied `packages/**`** template area.
- `known-kit-checksums.json` `checksums` map is currently **empty (`{}`)** → low risk for now.

## Blockers / decisions required (STOP — resolve before Phase 1)

These are genuine forks the reorg cannot resolve on its own. Each changes the phasing.

1. **Install-contract compatibility (highest).** `tools/ai/` is what the kit *installs into*
   downstream repos (catalog.json `path` entries, `.ai-install-manifest.json` sources/targets,
   adapter allowlists all name `tools/ai/…`). Renaming to `ai/` is a **compatibility-posture
   change** for every consumer of this kit. Per CLAUDE.md Approval Boundaries this must be an
   explicit decision:
   - (a) Change the installed layout too (downstream repos get `ai/` next release — breaking; needs
     a migration for already-installed projects), **or**
   - (b) Keep the *installed* path as `tools/ai/` but restructure only the *source-of-record* repo
     layout (source lives in `ai/`, but generators still emit/ship `tools/ai/` targets). This
     preserves the downstream contract but means the new `ai/` layout and the shipped `tools/ai/`
     layout diverge — generators/templates must map source→target.
   - **Recommendation: (b)** for the first pass — smallest external blast radius, no consumer break.

2. **`packages/**` is edit-denied (generator-managed).** 52 references, including the shipped
   `catalog.json` install contract, live under `packages/**` which my operating constraints forbid
   editing directly (route via generator or ask). Any reference change there must flow through
   `generate-ai-catalog.php` / the template pipeline, **not** a sed. Confirm: am I allowed to (i)
   run the generators to regenerate `packages/**` outputs, and (ii) edit the *generator* sources and
   *templates* that hardcode `tools/ai`? If neither, decision #1 must be (b) and templates stay.

3. **Adapter surfaces (`.github`, `.opencode`, `.claude`) are generated.** Their `tools/ai`
   references (permission allowlists, workflows) are rendered from templates. They must be updated
   by **re-running the renderers**, not hand-edited, to avoid adapter drift (the exact failure mode
   `validate-adapter-drift.php` guards). Confirm the render/generate commands are in scope to run.

4. **Config/resource split granularity.** The target separates `config/` vs `resources/` vs
   `generated/`. Several current files are ambiguous: `tools/ai/install/registry/*.yaml` (config?),
   the paired `*.json` (generated from yaml?), `tools/ai/tools/**` `.md` (resources?),
   `known-kit-checksums.json` (generated? config?). Need a confirmed classification table before
   moving, because generators write to some of these paths.

5. **`ai/tests` vs `tests/php`.** Target has `ai/tests/{Unit,Integration,Architecture,Fixtures}`,
   but the suite is at `tests/php/` with `composer.json` `autoload-dev: Tests\ → tests/php/` and
   `phpunit.xml.dist bootstrap="tests/php/bootstrap.php"`. Decision: (a) also relocate tests into
   `ai/tests/` (updates composer PSR-4 + phpunit bootstrap/dirs + 74 files), or (b) leave tests at
   `tests/php/` this pass and only repoint their `require`/assertions. **Recommendation: (b)** to
   keep the reorg bounded; relocating tests can be a follow-up ticket.

## Proposed old→new mapping (draft — for review, not yet applied)

Source families → target (assuming decision #1 = (b): source layout only). Directory-level:

| Current | Target (`ai/`) | Notes |
|---|---|---|
| `tools/ai/ai.php` | `ai/bin/agent-kit` (or `ai/src/Cli/…` + thin `bin/` shim) | Entry dispatcher; keep a `php` shim named per target `bin/agent-kit` |
| `tools/ai/sh-introspect.php` + `sh-introspect/**` | `ai/bin/sh-introspect` + `ai/src/ShellIntrospection/**` | Numeric-prefixed includes are order-sensitive |
| `tools/ai/install-ai-kit.php` + `install/**` | `ai/bin/install-kit` + `ai/src/Installer/**` | Largest subtree (~70 files) |
| `tools/ai/advisor/**` | `ai/src/Advisor/**` | scanner/scorer/packer/… stay procedural |
| `tools/ai/commands/**` | `ai/src/Cli/Command/**` (procedural cmd files) | Loaded by the dispatcher |
| `tools/ai/generate-*.php`, `render-*.php` | `ai/src/Generation/**` | Generators; watch output paths |
| `tools/ai/validate-*.php`, `validation/**` | `ai/src/Validation/**` | Validators |
| `tools/ai/ai_catalog_lib.php` + catalog files | `ai/src/Catalog/**` | `tests/php/bootstrap.php` requires this |
| `tools/ai/ai_output_lib.php`, `command-exists.php`, shared helpers | `ai/src/Shared/**` | Loaded early by many files |
| `tools/ai/install/registry/*.yaml` | `ai/config/registry/*.yaml` | pending decision #4 |
| `tools/ai/install/permission-layers/*` (data) | `ai/config/permissions/*` | code vs data split needed |
| `tools/ai/rules/agnostic-leak-rules.json` | `ai/config/rules/agnostic-leak-rules.json` | |
| `tools/ai/tools/**` (`.md` guides) | `ai/resources/tool-guides/**` | |
| `tools/ai/known-kit-checksums.json` | `ai/generated/checksums/…` | pending decision #4 |
| `tools/ai/*.sh`, `install/*.sh` | `ai/scripts/**` or `ai/bin/` | shell entry points |

A **file-level `old → new` CSV** (all 161 files) will be produced as Phase 0 output and reviewed
before any move — the table above is directory-level only.

## Phased execution (each phase leaves the repo green; gated on the decisions above)

- **Phase 0 — Freeze baseline + full mapping.** Capture green baseline: `composer test` (or
  `test:fast`), plus `php tools/ai/validate-install-surface.php --strict`, `validate-ai-config.php`,
  `validate-ai-catalog.php`, `validate-adapter-drift.php`, `validate-generated-artifacts.php`. Emit
  the 161-row `old→new` CSV. **No moves.**
- **Phase 1 — Scaffold `ai/` skeleton.** Create empty target dirs + `ai/composer.json`/`bin/` shims
  as decided. No file moves yet. Verify structure only.
- **Phase 2 — Move one self-contained leaf subtree** (candidate: `sh-introspect` → `ai/…` +
  `ai/bin/sh-introspect`) as the proving slice. Update its internal requires, its entry shim, its
  tests, and any external invocation. Run full validators + tests. This calibrates effort/risk.
- **Phase 3 — Move remaining subtrees** in dependency order (Shared → Catalog → Advisor → Commands
  → Generation/Validation → Installer last, it's largest and most-referenced). After each subtree:
  rewrite internal `__DIR__` requires, repoint `tests/php` requires + literal path assertions,
  update root `install-ai-kit.sh`/`justfile`/`.gitignore`/`composer.json` text, **regenerate**
  adapter + catalog + manifest surfaces (never hand-edit generated/packages), run all validators.
- **Phase 4 — Config/resource/generated split** per confirmed decision #4; repoint every reader
  (registry-loader, generators' output paths).
- **Phase 5 — Delete `tools/ai/`** only after `rg "tools/ai"` shows no remaining *live* references
  (docs updated or intentionally historical), all validators green, all tests green.
- **Phase 6 — Docs sweep** (153 files): update `php tools/ai/ai.php …` examples in the same slice
  as the behavior they describe (per CLAUDE.md "sync affected setup instructions in the same slice").

## Validators (run after every slice — must stay green)

- `composer test:fast` (full suite before Phase 5 / risky slices).
- `php <kit>/validate-install-surface.php --strict`
- `php <kit>/validate-ai-config.php` · `validate-ai-catalog.php` · `validate-catalog-drift.php`
- `php <kit>/validate-adapter-drift.php` (guards `.github`/`.opencode`/`.claude` parity)
- `php <kit>/validate-generated-artifacts.php` · `validate-command-policy.php`
- `php <kit>/validate-mentor-parity.php` (mentor-mode must stay green — standing user directive)
- `rg "tools/ai"` scoped sweep to confirm no orphaned live references before deletion.

## Things to avoid

- **Do not hand-edit `packages/**`, `.github/**`, `.opencode/**`, `.claude/**` generated outputs** —
  regenerate via the owning generator/renderer, or the adapter-drift validator will (correctly) fail.
- **Do not sed the whole repo** — literal path assertions in tests, generated files, and historical
  docs/backups (`.ai/backups/**`) must be handled by category, not blindly.
- **Do not break the downstream install contract** without an explicit decision (#1) + consumer
  migration story.
- **Do not convert procedural code to classes** — out of scope (Depth = physical reorg only).
- **Do not touch `mentor-mode`** or its 4 levels (standing directive).
- **Do not move `.ai/backups/**`** historical snapshots that contain their own `tools/ai/` copies —
  they are point-in-time and must not be rewritten.

## Acceptance criteria

- New `ai/` tree matches the approved target layout; `tools/ai/` removed (Phase 5).
- All validators listed above green; `composer test:fast` green.
- No *live* `tools/ai/` references remain (historical backups excepted and documented).
- Downstream install contract handled per decision #1 (unchanged, or changed with a migration).
- Adapter surfaces regenerated (not hand-edited); zero adapter drift.
- `graphify update .` re-run so the graph reflects the new layout.

## Open questions captured for the reviewer

1. Install-contract: option (a) change downstream layout, or (b) source-only reorg? (recommend b)
2. Am I cleared to run generators + edit generator/template sources under `packages/**`?
3. Move `tests/php` → `ai/tests` now, or repoint-in-place and relocate later? (recommend later)
4. Confirm the config/resources/generated classification table (decision #4).
5. Entry-point naming: `ai/bin/agent-kit` shim wrapping `php` — confirm the three `bin/` names
   (`agent-kit`, `sh-introspect`, `install-kit`) and that they should be executable shims.
