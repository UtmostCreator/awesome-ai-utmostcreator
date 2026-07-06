# Arch Todo: Safe Standalone Per-Language Verification Scripts (Non-Autowired)

- Ticket slug: `arch-todo-safe-language-verify-scripts-20260706-003959`
- Status: `IMPLEMENTED — P0-P8 complete, see §13 for evidence`
- Author: architect handoff (implementer agent acting read-only for review + planning)
- Risk: `medium` (adds new shipped script surface + registry entries; no agent
  autowiring, no runtime/data change, no package installs)
- Verification command of record: `composer test`
- Revision: `v3` — P0-P3 are **IMPLEMENTED** (evidence in §13). This revision adds P4-P8:
  suggest-mode PHP/JS diagnostics, full-gate tools (deptrac, composer-require-checker,
  composer-unused, playwright, vitest run), an isolated example/sandbox project, and
  README updates. v2 incorporated a reviewed external suggestion (single-engine +
  thin-wrapper design, mode contract, tool-policy layer, reporting) with three repo-fit
  reconciliations recorded in §1b.

---

## 1) Context

The user asked to (a) **review** the existing verification surface, and (b) **architect**
a set of **separate shell scripts** that verify PHP / JS / HTML / Vue / TS files, where
"safe" means: a tool only runs when it is **already available** (on `PATH`, in Docker, in
`vendor/bin/`, or in `node_modules` via a `package.json` dependency). No mutation of
`composer.json`, `package.json`, lockfiles, or repo config. The new scripts must **not**
autowire into or be run by any agent for now.

A follow-up external suggestion refined the design toward **one reusable verification
engine + thin language wrappers** with a mode contract, a tool-policy layer, tool adapters,
and machine-readable reports. This revision adopts that design where it is a genuine
improvement and reconciles the three places where it conflicts with existing repo
conventions (§1b).

### 1a) Review of the existing surface (grounded evidence)

The canonical gate is `scripts/ai/ai-verify.sh` (thin loader) whose logic lives in
`scripts/ai/internal/ai-verify/*.sh`. Reviewed files: `90-run.sh`, `40-step-runner.sh`,
`10-scope.sh`, `20-shipped-filters.sh`, `35-jscpd.sh`, `36-plan-status.sh`,
plus `scripts/ai/common.sh` and `scripts/ai/internal/lib/{00-env,30-logging}.sh`.

**Already implemented safely today (no repo install, PATH/vendor/dependency-gated):**

- Shell: `shellcheck` + `shfmt -d` — guarded by `command -v`, scope-aware file lists
  (`tracked_existing_shell_files`), zsh/fish shebang skip. (`90-run.sh:89-110`)
- Workflows: `actionlint` — guarded by `command -v` + `[[ -d .github/workflows ]]`,
  changed-scope aware. (`90-run.sh:112-130`)
- Composer: `composer validate --strict` + `composer audit` — only when
  `composer.json`/`composer.lock` are in the changed set. (`90-run.sh:141-154`)
- PHP: `vendor/bin/pint --test`, `vendor/bin/phpstan`, `vendor/bin/psalm`,
  `vendor/bin/phpunit`, `vendor/bin/pest` — each guarded by `[[ -x vendor/bin/... ]]`,
  scope-filtered, shipped-kit files excluded. (`90-run.sh:211-257`)
- JS/TS/Vue: `pnpm run lint` / `pnpm exec eslint`, `tsc --noEmit`, `vue-tsc --noEmit`,
  `nuxi typecheck`, `graphql-codegen`, `graphql-eslint`, `biome check`, `knip`, `pnpm test`
  — each guarded by `has_package_script` / `has_package_dependency` (never `npx --yes`).
  (`90-run.sh:260-322`)
- Secrets: `gitleaks detect --source . --redact --no-banner` — `command -v` guarded.
  (`90-run.sh:324-330`)
- Security: `trivy fs`, `semgrep scan --config auto`, `osv-scanner` — `command -v`
  guarded, only in non-changed scope. (`90-run.sh:332-349`)
- Structured logging already exists: `log_json <event> <payload>` (`30-logging.sh:34`)
  writes rich JSONL to `${AI_LOG_DIR}/tool-usage.jsonl` (`00-env.sh:17,21`) and is already
  used by the verify pipeline (`90-run.sh:353,358`; `30-linecount.sh:52`; `35-jscpd.sh`).

**Confirmed gaps vs. the user's proposal (the delta this ticket addresses):**

1. **No standalone per-language entrypoints.** Everything is one monolithic pipeline
   fanned out by file presence. There is no `ai-verify-php.sh`, `ai-verify-js.sh`, etc.
2. **`osv-scanner` uses a deprecated invocation** `osv-scanner scan --lockfile=.`
   (`90-run.sh:340,347`). The safer form is `osv-scanner scan source -r .`.
3. **`trivy`/`semgrep`/`osv-scanner` have no explicit opt-in switch** — they only run in
   `all` scope with no `VERIFY_SECURITY=1` gate.
4. **Biome detection is name-partial.** `has_package_dependency biome` (`90-run.sh:290`)
   does not match the real npm package `@biomejs/biome` nor a `biome.json`/`biome.jsonc`
   presence check.
5. **No HTML-specific verification path** exists at all.
6. **No mode contract.** PHP tools already vary (`pint --test` vs bare), but there is no
   uniform `check`/`suggest`/`fix` selector across languages.

### 1b) Review of the external suggestion — adopt / reconcile / reject

The suggestion is largely sound and reinforces the reuse-first direction. Decisions:

**Adopt as-is:**

- **Single engine + thin wrappers** (its §2, §3, §16.2). Matches this plan's dispatcher
  design. Canonical entrypoint `ai-verify.sh --language <lang>`; wrappers ≤10 lines.
- **`AI_VERIFY_MODE` contract** `check | suggest | fix | full`, default `check`; fix limited
  to safe formatters (`pint`, `eslint --fix`, `biome --write`, `shfmt -w`); `--unsafe` /
  `rector apply` / oxlint dangerous fixes never automatic (its §5, §10, §11).
- **Tool-policy layer** `50-tool-policy.sh` with `can_run_tool` / `is_standalone_safe_tool` /
  `has_composer_bin` / `has_node_dependency` / `has_node_script` (its §6). This centralizes
  guards that are currently inline in `90-run.sh`.
- **Language file discovery** `51-language-files.sh` with per-language pathspecs reusing the
  existing `scoped_changed_files_by_pathspec` (its §7).
- **Default scope `changed`** and security kept out of language wrappers (its §4, §13).
- **`suggest` mode via non-mutating flags** (`eslint --fix-dry-run`, `rector --dry-run`,
  `pint --test`) (its §5, §10, §17-P3).

**Reconcile (repo-fit corrections — these are the flags):**

- **FLAG-1 — Reporting directory.** The suggestion writes reports to `.ai/verify/`
  (its §14). This repo's **canonical evidence root is `.ai-logs/`** (`00-env.sh:17`,
  AGENTS.md: "use `.ai-logs/` as the canonical local evidence root"). `.ai/verify/` would
  introduce a competing, un-gitignored directory. **Decision:** write structured reports
  under `${AI_LOG_DIR}/verify/` (default `.ai-logs/verify/`), configurable via a new
  `VERIFY_REPORT_DIR` that **defaults from `AI_LOG_DIR`**, not a hardcoded `.ai/verify`.
- **FLAG-2 — Event writer duplication.** The suggestion's `write_verify_event` +
  `events.jsonl` (its §14) is ~90% a re-implementation of the existing `log_json`
  (`30-logging.sh:34`), which already emits versioned JSONL with trace/session/repo
  context. Per the `>=75%` reuse rule, **do not add `write_verify_event`.** Reuse
  `log_json` for the event stream; the only genuinely new artifacts are per-tool report
  files (e.g. `eslint.json`, `phpstan.txt`) written under `${AI_LOG_DIR}/verify/`.
- **FLAG-3 — Wrapper-by-`source` is unsafe.** The suggestion offers two wrapper forms; the
  `AI_VERIFY_LANGUAGE=php source "$_ai_verify_dir/ai-verify.sh"` form (its §2) is **unsafe
  here**: `ai-verify.sh` runs `set -euo pipefail`, `cd "$root"`, and terminates with
  `exit 0/1` (`ai-verify.sh:9,67`; `90-run.sh:78-79,354`). Sourcing it would `cd` and
  `exit` the **caller's** shell. **Decision:** wrappers MUST use the `exec` subcommand form
  (`exec "$(dirname …)/ai-verify.sh" --language php "$@"`), never `source`. The
  `AI_VERIFY_LANGUAGE=… source` form is explicitly forbidden in Things-To-Avoid.

**Reject / defer (out of scope for this ticket):**

- **Full tool-adapter abstraction for every tool** (its §8, §9, §10, adapter registry with
  per-tool metadata). Adopting a *thin* adapter dispatch is fine, but a full metadata-driven
  adapter framework is larger than this bounded slice. **Decision:** implement only the
  per-language dispatch + the small set of adapters needed for the five languages; do not
  build a generic tool-registry framework in this ticket.
- **`fix` and `suggest` modes as part of the initial slice.** They are valuable but expand
  the surface and risk. **Decision:** land `check` mode first (P0-P2); gate `suggest` behind
  P3 and `fix` behind a later, explicitly-approved slice (fix mutates files → approval).
- **`web` composite language** (its §7). Nice-to-have; defer until the five named languages
  are proven.

### 1c) Reuse decision (per `>=75%` rule)

- Overlap with `ai-verify.sh` internals: **~85%** of the actual check logic (tool guards,
  scope helpers, `run_step`, shipped-filters, `log_json`) is directly reusable. The new
  per-language scripts **MUST delegate** to the existing internal modules.
- Overlap of the suggestion's `write_verify_event` with existing `log_json`: **~90%** →
  reuse `log_json` (FLAG-2).
- Overlap with `arch-todo-code-quality-gate-and-agent-rules-20260705T131645Z`: **~20%**
  (both touch "verification"). That ticket is a code-**size** gate + agent quality rules;
  no fold-in.

---

## 2) Problem

There is no way to run a **single language's** safe verification in isolation, on demand,
without triggering the whole pipeline or any agent permission path. The user wants small,
auditable, manually-invoked scripts per language whose only behavior is "run the tools that
are already installed, safely, in a bounded scope." The existing pipeline is close but is
(a) monolithic, (b) not exposed per language, (c) has three safety/correctness gaps (osv
invocation, security opt-in, biome detection), and (d) has no uniform mode contract.

---

## 3) Target Outcome

- One canonical entrypoint `scripts/ai/ai-verify.sh --language <php|js|ts|vue|html>` plus
  five ≤10-line convenience wrappers, all sharing scope/mode/policy/reporting logic and
  running **only already-available tools**.
- Zero autowiring: no agent `permission:` entry, no capability reference, no command/skill
  invocation, no hook wiring. Runnable only by a human typing the command.
- The three confirmed safety/correctness gaps fixed **inside shared internals** so the
  existing `ai-verify.sh` and the new per-language path both benefit.
- Structured evidence reused via `log_json`; per-tool reports under `${AI_LOG_DIR}/verify/`.
- Registry + docs updated so the scripts are discoverable and correctly classed
  `read-only` / manual / `requires_approval: true`.

---

## 4) In Scope

- Canonical entrypoint change: add a `--language <lang>` flag to `scripts/ai/ai-verify.sh`
  (backward compatible — absent flag preserves today's full pipeline).
- Five convenience wrappers (≤10 lines each, `exec` delegation only):
  `ai-verify-php.sh`, `ai-verify-js.sh`, `ai-verify-ts.sh`, `ai-verify-vue.sh`,
  `ai-verify-html.sh`.
- New shared internal modules:
  - `50-tool-policy.sh` — `can_run_tool`, `is_standalone_safe_tool`, `has_composer_bin`,
    `has_node_dependency`, `has_node_script` (centralize existing inline guards).
  - `51-language-files.sh` — per-language pathspecs + `scoped_language_files`.
  - `53-language-dispatch.sh` — `ai_verify_language <lang>` mapping to the existing check
    subsets (PHP / JS / TS / Vue / HTML).
  - `54-reporting.sh` — thin helpers that reuse `log_json` and write per-tool report files
    under `${AI_LOG_DIR}/verify/` (FLAG-1, FLAG-2).
- Targeted shared fixes in `90-run.sh` (benefit both paths):
  - `osv-scanner scan source -r .` (FLAG in §1a-2).
  - `VERIFY_SECURITY` opt-in for `trivy`/`semgrep`/`osv-scanner`.
  - Broaden Biome detection to `@biomejs/biome` / `biome` / `biome.json(c)`.
- `AI_VERIFY_MODE` (default `check`) plumbed into the dispatch; only `check` implemented in
  P0-P2 (suggest → P3; fix → later approved slice).
- Registry entries in `tools/ai/install/script-registry.php` for each new wrapper
  (`risk: read-only`, `requires_approval: true`, `autonomy_level: advise`,
  `mutates_state: false`), regenerated `docs/ai/script-registry.json`.
- Pack membership in `tools/ai/install/packs.php` (`scripts-pack`, `merge_strategy: replace`).
- Tests mirroring the `AI_VERIFY_TEST_MODE` stub pattern.
- Docs: short entries in `docs/ai/script-registry.md` and `docs/ai/verification-matrix.md`.

---

## 5) Out Of Scope (Things To Avoid)

- **Do NOT autowire.** No entry in any `.opencode/agents/*.md`, `.github/agents/*.md`,
  permission layer (`tools/ai/install/permission-layers/*`: `script-tiers.php`,
  `verify-tiers.php`, `compose.php`, `compositions.php`, `packs.php`), capability, command,
  skill, or hook.
- **Do NOT install packages or mutate config.** No `npx --yes`, `pnpm dlx`,
  `composer global require`, `curl | bash`, or edits to `composer.json`/`package.json`/
  lockfiles. (The pre-existing opt-in `jscpd` `npx --yes` path stays as-is and is NOT
  extended into the new scripts.)
- **Do NOT source `ai-verify.sh` from wrappers** (FLAG-3). Wrappers use `exec … --language
  <lang> "$@"` only. The `AI_VERIFY_LANGUAGE=… source ai-verify.sh` form is forbidden.
- **Do NOT invent a new evidence directory** (FLAG-1). Reports go under `${AI_LOG_DIR}/verify/`
  (default `.ai-logs/verify/`), never `.ai/verify/`.
- **Do NOT add `write_verify_event`** (FLAG-2). Reuse `log_json` for events.
- **Do NOT run external `phpstan`/`psalm`/`eslint`/`vue-tsc`/`rector` from PATH or `npx`.**
  Only `vendor/bin/*` (PHP) and `pnpm exec` backed by a real `package.json` dependency
  (JS/TS). PATH is acceptable ONLY for the standalone repo-agnostic tools
  (`shellcheck`, `shfmt`, `actionlint`, `gitleaks`, `trivy`, `semgrep`, `osv-scanner`,
  `lychee`).
- **Do NOT default-enable broad scanners.** `trivy`/`semgrep` stay behind `VERIFY_SECURITY=1`
  (or `AI_VERIFY_SCOPE=all`), never in a per-language default run.
- **Do NOT run `biome --write`, `biome --unsafe`, `rector process` (apply), or any mutating
  fix automatically.** `check`/`suggest` are non-mutating; `fix` is deferred to a separate
  approved slice.
- **Do NOT build a generic metadata-driven tool-adapter framework** in this ticket (defer;
  §1b reject list). Implement only the five languages' dispatch.
- **Do NOT duplicate tool-guard logic.** New guards live once in `50-tool-policy.sh`; the
  dispatcher and (optionally refactored) `90-run.sh` call them.
- **Do NOT hand-edit** `docs/ai/script-registry.json` (regenerate via the exporter).
- **Do NOT** touch inactive/legacy paths or exceed ~6 files per implementation slice.

---

## 6) Affected Paths

- `scripts/ai/ai-verify.sh` (edit: add `--language` flag parsing, `exec`-safe)
- `scripts/ai/ai-verify-php.sh` / `-js.sh` / `-ts.sh` / `-vue.sh` / `-html.sh` (new, ≤10 lines)
- `scripts/ai/internal/ai-verify/50-tool-policy.sh` (new)
- `scripts/ai/internal/ai-verify/51-language-files.sh` (new)
- `scripts/ai/internal/ai-verify/53-language-dispatch.sh` (new)
- `scripts/ai/internal/ai-verify/54-reporting.sh` (new)
- `scripts/ai/internal/ai-verify/90-run.sh` (edit: osv form, `VERIFY_SECURITY`, biome detect;
  optionally route existing guards through `50-tool-policy.sh`)
- `tools/ai/install/script-registry.php` (edit: 5 new entries)
- `tools/ai/install/packs.php` (edit: 5 new pack files)
- `docs/ai/script-registry.json` (regenerated, not hand-edited)
- `docs/ai/script-registry.md`, `docs/ai/verification-matrix.md` (edit: short entries)
- `tests/**` (new: dispatch + entrypoint coverage)

NOTE: this exceeds the ~6-file soft limit, so implementation MUST split into the bounded
slices in §8. Each slice stays within the limit.

## 7) Contracts And Boundaries

- **Engine contract:** `ai-verify.sh --language <lang>` shares `ai-verify`'s existing
  contract: exit `0` on all-pass, `1` when `failures > 0`, stream `==> <label>` per step,
  honor `AI_VERIFY_SCOPE` (default `changed` for the language path), `AI_VERIFY_MODE`
  (default `check`), `VERIFY_TIMEOUT`, `VERIFY_GUARD`, and the anti-freeze watchdog.
- **Wrapper contract:** `exec` delegation only, zero tool logic (FLAG-3). Originally targeted
  ≤10 lines; implementation found a repo-wide invariant (`ShIntrospectIndexTest`) requiring
  every top-level script to answer `--introspect` targeting the canonical implementation, so
  wrappers adopted the existing `scripts/ai/bin/verify/ai-verify.sh` delegating-shim pattern
  (~22 lines) — still zero tool logic, ceiling relaxed to ≤25 lines (see §13 evidence).
- **Reporting contract:** events via `log_json`; per-tool report files under
  `${AI_LOG_DIR}/verify/` (FLAG-1/2). No new top-level directory.
- **Registry contract:** each entry mirrors `ai-verify` (`risk: read-only`,
  `requires_approval: true`, `supports_json: false`, `bounded_output: true`,
  `mutates_state: false`, `writes_paths: ['.ai-logs/']`, `autonomy_level: advise`).
- **Permission contract:** absent by design. No allow/ask entry anywhere; a human invokes
  the scripts directly. This is the enforceable meaning of "not autowired."

---

## 8) Todo Plan

### P0 — Shared internals: policy layer + safety fixes — **DONE** (see §13)

- [x] Add `50-tool-policy.sh` (`can_run_tool`, `is_standalone_safe_tool`, `has_composer_bin`;
      reused existing `has_package_dependency`/`has_package_script` instead of new
      `has_node_dependency`/`has_node_script`, per the reuse rule), sourced after
      `40-step-runner.sh`.
- [x] Fix `osv-scanner` invocation to `osv-scanner scan source -r .` in `90-run.sh`.
- [x] Add `VERIFY_SECURITY` (default `0`): `trivy`/`semgrep`/`osv-scanner` run when
      `VERIFY_SECURITY=1` OR `AI_VERIFY_SCOPE=all`; preserve current default output.
- [x] Broaden Biome detection via `can_run_tool biome` (`@biomejs/biome` OR `biome` OR
      `biome.json(c)`), check-only.
- [x] Add `AI_VERIFY_TEST_MODE` assertions proving the three fixes + policy predicates.

### P1 — Reporting + language file discovery — **DONE** (see §13)

- [x] Add `54-reporting.sh`: `verify_report_dir` defaulting from `AI_LOG_DIR`
      (`${AI_LOG_DIR}/verify/`); per-tool report file helpers; reuse `log_json` for events
      (NO `write_verify_event`).
- [x] Add `51-language-files.sh`: `language_pathspecs <lang>` + `scoped_language_files <lang>`
      reusing `scoped_changed_files_by_pathspec`.
- [x] Tests: report dir resolves under `.ai-logs/verify/` (never `.ai/verify`); pathspecs
      correct per language; scope honored.

### P2 — Language dispatcher + entrypoint + wrappers (check mode) — **DONE** (see §13)

- [x] Add `53-language-dispatch.sh`: `ai_verify_language <php|js|ts|vue|html>` running only
      that language's already-defined check subset via `can_run_tool` guards.
- [x] Add `--language <lang>` parsing to `ai-verify.sh` (backward compatible).
- [x] Add 5 wrappers (`exec` delegation, ~22 lines due to the introspect-shim requirement
      discovered during implementation — see the Wrapper contract note in §7 — no logic).
- [x] `ai-verify-html.sh`: run a project-local HTML tool only if present
      (`biome`→`htmlhint`→configured `eslint`), else clean skip (Q3).
- [x] Tests: each language runs only its steps, skips cleanly when tools/deps absent,
      exits 0/1 correctly; wrappers contain no tool logic.

### P3 — Registry, packs, docs (discoverability, still non-autowired) — **DONE** (see §13)

- [x] Add 5 registry entries to `script-registry.php` (read-only / advise / approval-required,
      `writes_paths: ['.ai-logs/']`).
- [x] Add 5 pack file entries to `packs.php` (`scripts-pack`, `merge_strategy: replace`).
- [x] Regenerate `docs/ai/script-registry.json` via
      `php tools/ai/ai.php registry:export --output docs/ai/script-registry.json`; confirmed
      with `registry:export --check`.
- [x] Short entries in `docs/ai/script-registry.md` + `docs/ai/verification-matrix.md`
      stating **manual-only, not agent-wired**.
- [x] Proved no permission-layer/agent/hook file references the new scripts.

### P4 — PHP suggest-mode + output-format diagnostics (approved 2026-07-06) — **DONE**

User explicitly requested these; treated as an approved expansion of this same ticket
(not `fix` mode — every item below is non-mutating).

- [x] `run_php_language_files`: added Rector as a non-mutating check —
      `vendor/bin/rector process --dry-run "${files[@]}"` when `can_run_tool rector`.
      Never `rector process` (apply).
- [x] Added `VERIFY_OUTPUT_FORMAT` (default `table`). When `json`: PHPStan runs with
      `--error-format=json`, Psalm with `--output-format=json`, and both write raw output
      to `${AI_LOG_DIR}/verify/{phpstan,psalm}.json` via `write_verify_report_file`.
- [x] `run_php_language_files`: added `composer validate --strict` + `composer audit`,
      gated via the existing `scope_has_exact_changed_path` (reused, not reimplemented).

### P5 — JS/Nuxt suggest mode + Vitest --changed (approved 2026-07-06) — **DONE**

- [x] `AI_VERIFY_MODE=suggest` now runs `pnpm exec eslint --fix-dry-run --format json`
      via a dedicated `run_eslint_suggest_files` path (never routed through `run_step`,
      so it can never increment `$failures`); writes `eslint-suggest.json`.
- [x] `run_js_or_ts_lint_files` (shared by js/ts/vue) runs `pnpm exec vitest run --changed`
      when `has_package_dependency vitest` — distinct from the full unscoped `vitest run`
      (P6).
- [x] Confirmed + regression-tested: `nuxi typecheck` already only fires when the Vue/Nuxt
      dispatch has a non-empty file list (empty-list-early-return pattern).

### P6 — Full-gate additions (VERIFY_FULL=1 / AI_VERIFY_SCOPE=all only) — **DONE**

New tools, all gated behind the existing `VERIFY_FULL` branch in `90-run.sh` (never
default-on), added alongside the existing phpunit/pest/pnpm-test/trivy/semgrep/
osv-scanner/jscpd/lychee/knip full-gate checks already shipped:

- [x] `vendor/bin/deptrac analyse` when `[[ -x vendor/bin/deptrac ]]`.
- [x] `vendor/bin/composer-require-checker check composer.json` when the binary exists.
- [x] `vendor/bin/composer-unused` when the binary exists — **advisory only** via a new
      `check_composer_unused()` (mirrors the `35-jscpd.sh` tiering idiom): never
      increments `$failures`, writes `${AI_LOG_DIR}/verify/composer-unused.txt`.
- [x] `pnpm exec playwright test` when `has_package_dependency '@playwright/test'`.
- [x] `pnpm exec vitest run` (full, unscoped) when `has_package_dependency vitest`.

### P7 — Isolated example/sandbox project (install + test harness, approved 2026-07-06)

Per explicit user decision: an isolated, self-contained example project, NOT the kit's
own root `composer.json`/`package.json` (which do not and must not gain these
dependencies). This folder is both (a) the "install it here and prove it fully works"
test harness and (b) the literal copy-paste bundle for a user's own project.

- [x] New folder `examples/verify-config-example/` with its own `composer.json`
      (require-dev: pint, phpstan, psalm, rector, `deptrac/deptrac` — corrected from the
      stale `qossmic/deptrac`, verified against Packagist — composer-require-checker,
      composer-unused) and `package.json` (devDependencies: eslint, @biomejs/biome,
      typescript, vue-tsc, vue, vitest, @playwright/test, htmlhint; nuxi/nuxt
      intentionally skipped to keep install fast — noted in the example's own README).
- [x] Config files with sane, minimal defaults: `phpstan.neon`, `psalm.xml`, `rector.php`,
      `deptrac.yaml`, `eslint.config.js`, `biome.json`, `vitest.config.ts`,
      `playwright.config.ts`, `tsconfig.json`.
- [x] Small sample source files that pass cleanly: `src/Example.php`, `src/example.js`,
      `src/example.ts`, `src/GreetingCard.vue`, `src/example.html`, plus
      `src/example.test.ts` (vitest) and `tests/example.spec.ts` (playwright).
- [x] Ran `composer install --working-dir=examples/verify-config-example` (75 packages) and
      `pnpm install --dir examples/verify-config-example` (165 packages) — confirmed via
      `git status` that the kit's own root gained no composer.json/package.json/lockfile.
- [x] Ran all 5 `ai-verify-{php,js,ts,vue,html}.sh` against the example folder — real,
      non-stubbed evidence every tool fires (Pint PASS, PHPStan/Psalm clean, Rector "done",
      composer validate/audit clean, ESLint/Biome clean, `vitest run --changed` 1 passed,
      `vue-tsc`/`tsc --noEmit` clean) — plus the P6 full-gate tools (deptrac 0 violations,
      composer-require-checker clean after adding a psr-4 autoload block, composer-unused
      advisory-clean, playwright 1 passed, `vitest run` 1 passed).
- [x] `examples/verify-config-example/README.md` (67 lines) explains: copy the config
      files into your project, install the listed tools yourself, then run
      `bash scripts/ai/ai-verify-<lang>.sh .` — manual-only, never agent-wired.

### P8 — README updates (depends on P4-P7) — **DONE**

- [x] Added a "Per-Language Verification Wrappers" section to the root `README.md`
      (between "What It Ships With" and "Safety and Scope") covering all 5 wrappers, the
      `AI_VERIFY_MODE`/`VERIFY_OUTPUT_FORMAT`/`VERIFY_FULL`/`AI_VERIFY_SCOPE=all` knobs,
      the full-gate tool list, and a link to `examples/verify-config-example/`; added a
      `docs/ai/script-registry.md` link under "Documentation". States manual-only, never
      agent-wired.

---

## 9) Acceptance Criteria

- [x] AC-01: `bash scripts/ai/ai-verify-php.sh .` (and `ai-verify.sh --language php .`) run
      only PHP-surface checks that are already installed, and exit 0 when none apply.
- [x] AC-02: `bash scripts/ai/ai-verify-js.sh .` runs only JS/TS checks gated by
      `package.json` dependencies; with no `package.json` it skips cleanly and exits 0.
- [x] AC-03: `ai-verify-ts.sh` / `ai-verify-vue.sh` run only typecheck/vue-tsc/eslint scoped
      to their file types; `ai-verify-html.sh` runs an HTML tool only if project-local, else
      logs a skip.
- [x] AC-04: No new script appears in any `.opencode/agents/*`, `.github/agents/*`,
      `tools/ai/install/permission-layers/*`, capability, command, skill, or hook — proven
      by a repo-wide search returning zero permission/wiring references.
- [x] AC-05: `osv-scanner` invocation is `scan source -r .`; broad scanners run only under
      `VERIFY_SECURITY=1` or `AI_VERIFY_SCOPE=all`; Biome fires on `@biomejs/biome`/`biome`/
      `biome.json(c)` and is check-only.
- [x] AC-06: No package-install / config-mutation command (`npx --yes`, `pnpm dlx`,
      `composer global`, `curl|bash`, lockfile edits) is introduced in the new scripts, and
      no wrapper uses the `source` form (only `exec`).
- [x] AC-07: Reports/events land under `${AI_LOG_DIR}/verify/` (default `.ai-logs/verify/`);
      no `.ai/verify/` path and no new `write_verify_event` function are introduced.
- [x] AC-08: 5 registry entries exist (`read-only`, `requires_approval: true`,
      `mutates_state: false`); `registry:export --check` is clean.
- [x] AC-09: New tests pass; `composer test` has only pre-existing, independently-confirmed
      unrelated failures (see §13).
- [x] AC-10: Rector `--dry-run`, PHPStan/Psalm `VERIFY_OUTPUT_FORMAT=json` output, and
      per-language composer validate/audit all work per P4 and never mutate files —
      proven both by 13 new bats tests and by real execution against the P7 example
      (Rector "done", phpstan/psalm clean, composer validate/audit clean).
- [x] AC-11: `AI_VERIFY_MODE=suggest` runs `eslint --fix-dry-run --format json` and is
      advisory-only (never increments `$failures`); `vitest --changed` runs by default when
      the vitest dependency is present — proven by bats tests and real
      `vitest run --changed` execution (1 passed) against the P7 example.
- [x] AC-12: deptrac/composer-require-checker/composer-unused/playwright/`vitest run` all
      run ONLY under `VERIFY_FULL=1`, never in the default per-language check path —
      proven by 18 new bats tests (`ai-verify-full-gate.bats`) and by real execution
      against the P7 example (deptrac 0 violations, composer-unused advisory-clean,
      playwright/vitest run 1 passed each).
- [x] AC-13: `examples/verify-config-example/` is self-contained with its own
      `composer.json`/`package.json`; `git status` confirmed the kit's own root
      composer.json/package.json/lockfiles were never touched by the scoped installs.
- [x] AC-14: Running each `ai-verify-<lang>.sh` against the example project produced real
      (non-stubbed) evidence every requested tool actually executed (see P7 evidence).
- [x] AC-15: README documents the per-language wrappers, the new mode/format knobs, and the
      example folder, explicitly restating manual-only/non-agent-wired.

---

## 10) Verification Plan

1. Focused: `AI_VERIFY_TEST_MODE=1 bash scripts/ai/ai-verify.sh --language php .`
   (and per language) to exercise stub-mode step selection without heavy tools.
2. Affected-layer: new `tests/**` for policy predicates, reporting dir, dispatch, wrappers
   (`bash scripts/ai/run-test-focused.sh` / targeted PHPUnit or bats).
3. Wiring proof:
   `rg -n 'ai-verify-(php|js|ts|vue|html)' .opencode .github tools/ai/install/permission-layers`
   MUST return zero matches (AC-04).
4. Reporting proof: `rg -n '\.ai/verify' scripts/ai` returns zero matches; report dir
   resolves under `.ai-logs/verify/` (AC-07).
5. Registry: `php tools/ai/ai.php registry:export --check`.
6. Adapter safety: `php tools/ai/validate-adapter-drift.php` (should be unaffected).
7. Broad: `composer test` (or `composer test:fast`) as the final gate.

Apply per-command timeouts from `docs/ai/execution-protocol.md`
(read-only 30s; focused test 60s; full suite 180s; parallel 90s).

---

## 11) Risks And Rollback

- Risk: new scripts drift from `ai-verify` internals. Mitigation: thin dispatchers + one
  shared policy layer; no duplicated guard logic.
- Risk: someone later wires them into an agent. Mitigation: AC-04 search guard + explicit
  docs note; a validator assertion is a possible follow-up (out of scope).
- Risk: `--language`/`VERIFY_SECURITY` refactor changes existing default output.
  Mitigation: `--language` is additive and backward compatible; `VERIFY_SECURITY` preserves
  current default; both covered by `AI_VERIFY_TEST_MODE`.
- Risk (from suggestion): wrapper-by-`source` would `exit`/`cd` the caller's shell.
  Mitigation: `exec`-only wrapper contract (FLAG-3), enforced by AC-06 and a test.
- Rollback: revert the new/edited script files, the `90-run.sh` diff, the registry/pack
  edits, and regenerate the registry JSON. All read-only; no data/state migration.
- Success signal: per-language scripts run and exit correctly; wiring + reporting searches
  return zero forbidden matches; `composer test` green.

---

## 12) Handoff Notes

- P0-P3 are **implemented and verified** (see §13 for evidence). P4-P8 are the newly
  approved expansion (2026-07-06): implementer agent handoff continues via bounded
  slices — P4+P5 together (both touch `53-language-dispatch.sh`, so one agent, one
  file, no parallel conflict), P6 in parallel with P4/P5 (touches only `90-run.sh`),
  P7 in parallel with both (isolated new `examples/verify-config-example/` folder, no
  script-file overlap), then P8 (README) sequential last, once P4-P7 land.
- Original Open Questions Q1-Q4 (below) are resolved — kept for history.

### Open Questions For User (resolved — kept for history)

1. **Q1 (scope default):** Resolved — per-language default `AI_VERIFY_SCOPE=changed`
   (translated from the root loader's `ai` default; see `53-language-dispatch.sh`
   critical-gotcha comment).
2. **Q2 (naming):** Resolved — canonical `ai-verify.sh --language <lang>` + thin
   delegating-shim wrappers (adjusted from ≤10 to ~22 lines per the introspect finding).
3. **Q3 (HTML tool):** Resolved — detection order `biome → htmlhint → clean skip`
   (no "configured eslint" fallback was implemented; deemed too var  iable to guess safely).
4. **Q4 (modes now or later):** Resolved — `check`-only shipped in P0-P3;
   `suggest` mode is now added in P5 (2026-07-06, user-approved); `fix` mode remains
   out of scope for this ticket (mutates files, needs its own separate approval).

## 13) Implementation Evidence (P0-P3, 2026-07-06)

- **Verification run:** 63/63 new bats tests pass across
  `tests/shell/ai-verify-{tool-policy,language-files,reporting,language-dispatch}.bats`;
  `ScriptsAiManifestTest` + `ShIntrospectIndexTest` (10/10) pass; `registry:export --check`
  clean; AC-04 wiring grep and AC-07 `.ai/verify` grep both return zero matches.
- **`composer test` (full suite):** has pre-existing, unrelated failures confirmed via
  `git stash` to a clean HEAD with zero changes from this ticket (e.g.
  `testGenerateRepoStructureCheckModeExitsZero` fails identically on clean HEAD due to
  unrelated repo-structure drift for `.claude`, `bin`, `graphify-out`, etc. — nothing this
  ticket touches).
- **Two issues found and fixed during implementation** (not in the original plan text):
  1. The ≤10-line wrapper target conflicted with `ShIntrospectIndexTest::
     testEveryAiScriptSupportsIntrospect` (every top-level script must answer
     `--introspect`). Fixed by adopting the repo's existing delegating-shim pattern from
     `scripts/ai/bin/verify/ai-verify.sh` (~22 lines, still zero tool logic).
  2. `scripts/ai/MANIFEST.md` and `docs/ai/scripts-reference.md` needed new rows for the
     5 wrapper scripts (`ScriptsAiManifestTest` / `ShipReferenceIntegrityTest`).
- **Pre-existing, out-of-scope dirty-tree content** noted but not touched: an unrelated
  "plan-status" feature, Kotlin/Android verify modules from sibling in-flight tickets, and
  `docs/ai/AGENTS-MANIFEST.md` script-runner-agent additions.
