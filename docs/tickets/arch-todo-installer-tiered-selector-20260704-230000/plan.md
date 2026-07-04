# Architecture Plan — Tiered SelectionEngine for the PHP installer wizard (+ 2 P0 bug fixes)

- Ticket: none
- Source: architect design (verified read-only research, architect session)
- Generated: 20260704-230000
- Plan folder: docs/tickets/arch-todo-installer-tiered-selector-20260704-230000/
- Status: Todo (unchecked)
- Risk: MEDIUM (touches the public interactive install surface + introduces an optional dependency that must degrade gracefully into other people's repos; engine and non-interactive flags untouched)

## Context

This is a PHP-first AI workflow kit installed INTO other repositories. The PHP engine under `tools/ai/install/` (~7,200 lines, 23 modules) is the source of truth and MUST NOT be replaced. The interactive install wizard is reached only via `php tools/ai/ai.php install --wizard`; dispatch is at `tools/ai/commands/install_workflow.php:91-94` (`aiRunInstallWizard()` at lines 408-589). Prompt primitives live in `tools/ai/commands/helpers.php`: `aiPromptLine()` (254-259), `aiPromptYesNo()` (261-269), plus runtime/TTY detection `aiDetectRuntimeMode()` (298-327) classifying AI_AGENT / CI / HUMAN_TTY. Selectable packs are modeled in `tools/ai/install/packs.php` → `aiInstallerPackRegistry()` (line 7); presets/editions in `tools/ai/install/profiles.php` → `aiInstallerProfileDefinitions()` (5-31) and `aiInstallerAllFeaturePacks()` (33-61). Pack resolution + registry validation already happens in `aiInstallerResolveSelectedPacks()` (packs.php:430-470), which filters unknown keys at line 468. Dependency helpers already exist: `aiInstallerAgentDependencyWarnings()` (packs.php:511-519) and `aiInstallerPackToolRequirements()` (packs.php:521-532). Flag parsing: `aiInstallerParseArgs` (config.php, `--wizard` at :65) and `aiInstallerConfigFromAiArgs()` (install_preflight.php:621).

Environment confirmed: PHP 8.4.22 (mandatory runtime), Composer present, fzf present, just/jq/rg present; gum NOT installed; go NOT installed; node present but repo has NO package.json (not a JS project). `composer.json` requires only `php >=8.2`; there is NO runtime `require` and NO guaranteed `vendor/autoload.php` — the selector MUST degrade gracefully when Laravel Prompts is absent.

## Problem

1. P0 — Stale hardcoded pack list bug: `aiRunInstallWizard()` at install_workflow.php:430 hardcodes an optional-pack list `['scripts-pack','policy-pack','hooks-pack','ci-pack','evidence-pack','docs-reference-pack','capabilities-governance','delivery-pack','optional-agents-pack','optional-prompts-pack']`. The last two keys (`optional-agents-pack`, `optional-prompts-pack`) do NOT exist in `aiInstallerPackRegistry()`; the real keys are `optional-agents-opencode-pack`, `optional-agents-copilot-pack`, etc. Because `aiInstallerResolveSelectedPacks()` filters unknown keys (packs.php:468), those selections silently vanish and several real optional packs can never be chosen interactively. HIGHEST-VALUE FIX: the wizard must read pack keys dynamically from `aiInstallerPackRegistry()`.
2. P0 — Broken bash entrypoint stubs: both `install-ai-kit.sh` (root) and `tools/ai/install-ai-kit.sh` are committed 3-line `printf install\n` STUBS, yet `readme-install.md` documents them as the primary install path. This must be fixed or explicitly accounted for before advertising the installer as primary.
3. Reuse gap: `aiInstallerAgentDependencyWarnings()` exists but is NOT called by the wizard today (the wizard only calls `aiInstallerPackToolRequirements()`). Dependency warnings must be surfaced BEFORE apply.

## Target Outcome

Replace/wrap ONLY the prompt/selection layer of the wizard with a tiered SelectionEngine that auto-detects the best available UI backend and always degrades to plain STDIN. The installer engine, pack registry, profile system, and all non-interactive flags (`--profile`, `--with`, `--without`, `--all-features`) behave exactly as before. Interactive pack selection reads live keys from the registry, validates against it, and shows dependency + tool warnings before any apply. Laravel Prompts is introduced as an OPTIONAL dev-only backend that is used only when `vendor/autoload.php` exists AND Laravel Prompts is installed AND a TTY is available; its absence changes nothing for consumers.

## In Scope

- A SelectionEngine abstraction + backends chosen by capability detection.
- Rewrite the pack-selection portion of `aiRunInstallWizard()` to source keys from `aiInstallerPackRegistry()` and route prompts through the SelectionEngine.
- Wire `aiInstallerAgentDependencyWarnings()` into the wizard before the final apply gate.
- P0 fix for the stale hardcoded pack list.
- P0 fix-or-account for the two bash entrypoint stubs.
- Optional fzf backend (P2) and optional gum backend (P3), auto-detected, never required.
- Focused PHPUnit tests for detection precedence, registry-sourced selection, unknown-key rejection, and graceful degradation.

## Out Of Scope (Things To Avoid)

- Do NOT replace or broadly refactor the install engine under `tools/ai/install/`.
- Do NOT add a JS/Node installer, and do NOT require Go/Rust binaries. PHP stays the mandatory runtime.
- Do NOT make Laravel Prompts, fzf, or gum a hard requirement or add them to `composer.json` `require` (dev/suggest only).
- Do NOT change the CI / non-TTY / AI_AGENT code paths' behavior or output contract.
- Do NOT alter the meaning of `--profile`, `--with`, `--without`, `--all-features`, or the `--wizard`/`--no-interaction` dispatch.
- Do NOT introduce new pack-splitting or per-file selection granularity.
- Do NOT change `aiInstallerResolveSelectedPacks()` semantics; reuse it for validation.
- NOTE (adjacent, deferred): `packs.php` currently has uncommitted working-tree edits (` M tools/ai/install/packs.php`) and `adapter-claude` is uncommitted WIP — flag before editing packs.php so pre-existing changes are not clobbered; coordinate rather than overwrite.

## Affected Paths

- `tools/ai/commands/install_workflow.php` — `aiRunInstallWizard()` (408-589): replace the hardcoded `$with` loop at ~430 with registry-sourced selection via the SelectionEngine; insert dependency-warning gate before the final action prompt (~536). Dispatch at 91-94 unchanged.
- NEW `tools/ai/install/selection-engine.php` — SelectionEngine detection + backend functions (procedural, `aiInstaller*`/`aiSelection*` naming). Required by install_workflow.php.
- `tools/ai/commands/helpers.php` — reuse `aiPromptLine()`/`aiPromptYesNo()` (254-269) as the StdinSelector primitives; reuse `aiDetectRuntimeMode()` (298-327) for the TTY/CI/agent gate. No behavior change.
- `tools/ai/install/packs.php` — READ ONLY from wizard: `aiInstallerPackRegistry()` (7), `aiInstallerResolveSelectedPacks()` (430), `aiInstallerAgentDependencyWarnings()` (511), `aiInstallerPackToolRequirements()` (521). No signature changes.
- `install-ai-kit.sh` (root) and `tools/ai/install-ai-kit.sh` — P0: replace 3-line stubs with a real thin bash wrapper that execs `php tools/ai/ai.php install "$@"` (or document them as removed/deprecated in readme-install.md). Decision required at implementation time; see Risks.
- `readme-install.md` — align documented primary install path with whatever entrypoint decision is made (docs-sync).
- `composer.json` — add `laravel/prompts` under `require-dev` and/or `suggest` ONLY; never `require`.
- NEW `tests/php/InstallerSelectionEngineTest.php` — detection precedence + degradation + registry sourcing + unknown-key rejection. (Existing installer tests: InstallerSafetyTest.php, InstallLifecycleTest.php, InstallManifestReconciliationTest.php.)

## Contracts And Boundaries

- Codebase uses procedural PHP with `aiInstaller*` naming. PRIMARY design: implement the SelectionEngine as procedural functions, NOT classes, to match convention:
  - `aiSelectionDetectBackend(string $runtimeMode, string $root): string` — returns one of `laravel-prompts` | `fzf` | `gum` | `stdin` by precedence.
  - `aiSelectionMultiselect(string $backend, string $title, array $options, array $defaults): array` — returns selected keys.
  - `aiSelectionConfirm(string $backend, string $prompt, bool $default): bool`.
  - `aiSelectionChoose(string $backend, string $prompt, array $options, string $default): string`.
  - Backend-specific helpers: `aiSelectionLaravelPromptsAvailable(string $root): bool` (checks `vendor/autoload.php` exists AND `Laravel\Prompts\multiselect` is callable AND TTY), `aiSelectionFzfMultiselect(...)`, `aiSelectionGumMultiselect(...)`, `aiSelectionStdinMultiselect(...)` (wraps aiPromptLine/aiPromptYesNo).
- Class introduction is NOT justified here; if the implementer prefers a single `SelectionEngine` value object for testability, that is the ONLY allowed class and must live in `tools/ai/install/selection-engine.php` with a procedural entrypoint wrapper — but procedural is preferred. Record this as a decision point, not a mandate.
- Detection precedence (highest→lowest): Laravel Prompts (only if `vendor/autoload.php` + package installed + TTY) → fzf (if on PATH) → gum (if on PATH) → plain STDIN. STDIN is the guaranteed fallback and the forced choice whenever `aiDetectRuntimeMode()` returns CI or AI_AGENT, or STDIN is not a TTY.
- Validation contract: every selected pack set MUST pass through `aiInstallerResolveSelectedPacks()` (which filters against the registry at packs.php:468) before building the plan; unknown keys are rejected/dropped, never installed.
- Dependency-warning contract: before the final apply action, the wizard MUST call `aiInstallerAgentDependencyWarnings($packs)` and `aiInstallerPackToolRequirements($packs)`, print both, and (for agent-without-scripts) require explicit confirmation to proceed.
- Graceful-degradation contract: `require_once vendor/autoload.php` must be guarded by `file_exists()`; a missing package or missing autoloader silently selects the next backend. No fatal error, no warning to consumers who lack the optional deps.

## Todo Plan

P0:

- [ ] P0: Add `aiSelectionDetectBackend()` + `aiSelectionStdinMultiselect/Confirm/Choose()` in new `tools/ai/install/selection-engine.php` (StdinSelector wraps aiPromptLine/aiPromptYesNo; precedence returns `stdin` on CI/AI_AGENT/non-TTY).
- [ ] P0: Rewrite the pack-selection block in `aiRunInstallWizard()` (~install_workflow.php:426-436) to enumerate selectable pack keys dynamically from `aiInstallerPackRegistry()` and route through `aiSelectionMultiselect()`; delete the hardcoded stale list.
- [ ] P0: Keep `--profile/--with/--without/--all-features` non-interactive path byte-for-byte unchanged (only the `--wizard` interactive branch changes).
- [ ] P0: Validate the interactively selected packs through `aiInstallerResolveSelectedPacks()` and reject unknown keys before planning.
- [ ] P0: Decide + implement the bash entrypoint fix: replace the two `printf install` stubs with a thin `exec php tools/ai/ai.php install "$@"` wrapper, OR deprecate them and correct `readme-install.md`. Update `readme-install.md` to match.

P1:

- [ ] P1: Add `aiSelectionLaravelPromptsAvailable()` (guarded `file_exists(vendor/autoload.php)` + callable check + TTY) and `aiSelectionLaravelPromptsMultiselect/Confirm/Choose()` using Laravel Prompts checkbox/multiselect.
- [ ] P1: Add `laravel/prompts` to `composer.json` `require-dev` and `suggest` only (never `require`).
- [ ] P1: Insert dependency-warning gate before final apply: call `aiInstallerAgentDependencyWarnings()` + `aiInstallerPackToolRequirements()`, print, and confirm.

P2:

- [ ] P2: Add optional `aiSelectionFzfMultiselect()` (auto-detected via `command -v fzf`, with preview), slotted between Laravel Prompts and gum in precedence.

P3:

- [ ] P3: Add optional `aiSelectionGumMultiselect()` (auto-detected via `command -v gum`), slotted below fzf.
- [ ] P3: Add `tests/php/InstallerSelectionEngineTest.php` covering precedence, registry sourcing, unknown-key rejection, and stdin degradation.

## Acceptance Criteria

- [ ] AC-01: In CI / non-TTY / AI_AGENT mode, `aiSelectionDetectBackend()` returns `stdin` and the wizard output/behavior is unchanged from current (regression-locked by existing installer tests).
- [ ] AC-02: `php tools/ai/ai.php install --profile dual --dry-run`, `--with <pack> --dry-run`, `--without <pack> --dry-run`, and `--all-features --dry-run` produce the same selected-pack set and plan as before this change (non-interactive path untouched).
- [ ] AC-03: The interactive wizard's optional-pack list is derived from `aiInstallerPackRegistry()` keys at runtime; no hardcoded pack-key literal list remains in `aiRunInstallWizard()` (grep shows the stale array removed).
- [ ] AC-04: Selecting a real optional pack that was previously unreachable (e.g. `optional-agents-opencode-pack`) results in it appearing in the resolved pack set and the dry-run plan.
- [ ] AC-05: Any unknown pack key passed into selection is rejected by `aiInstallerResolveSelectedPacks()` and never appears in the plan (unit-tested).
- [ ] AC-06: When agent adapter packs are selected without `scripts-pack`, `aiInstallerAgentDependencyWarnings()` output is printed before the final apply prompt.
- [ ] AC-07: With NO `vendor/autoload.php` present, the wizard runs to completion with the stdin backend and emits no fatal error and no warning about missing Laravel Prompts (graceful degradation verified).
- [ ] AC-08: When Laravel Prompts + TTY are available, `aiSelectionDetectBackend()` returns `laravel-prompts`; when only fzf is present it returns `fzf`; when only gum is present it returns `gum` (precedence unit-tested with detection stubs).
- [ ] AC-09: `composer.json` contains `laravel/prompts` under `require-dev`/`suggest` only and NOT under `require`; `composer validate` passes.
- [ ] AC-10: The two bash entrypoints either exec the PHP installer with forwarded args (verified by a smoke invocation printing the installer banner) OR are documented as deprecated and `readme-install.md` no longer advertises a non-working primary path.
- [ ] AC-11: `composer test` (and `composer test:fast`) pass, including the new `InstallerSelectionEngineTest`.

## Verification Plan

- `composer test:fast` (parallel, ~19-21s) as the primary gate; `composer test` for serial ordering triage.
- `php tools/ai/ai.php install --profile dual --dry-run` and repeat for `--with`, `--without`, `--all-features` to confirm non-interactive parity (AC-02).
- `composer validate` for composer.json changes (AC-09).
- Targeted: `php vendor/bin/phpunit --filter InstallerSelectionEngine` for detection/degradation tests.
- `php tools/ai/validate-adapter-drift.php` if any adapter/doc surface changes; `php tools/ai/ai.php install-docs --check` after readme-install.md edits.
- Manual smoke of the two bash entrypoints to confirm they either exec the installer or are correctly deprecated (AC-10).

## Risks And Rollback

- RISK (optional dep): Adding Laravel Prompts wrong (into `require`) would break installs in consumer repos lacking it. Mitigation: `require-dev`/`suggest` only + `file_exists(vendor/autoload.php)` guard + callable check; AC-07/AC-09 lock this.
- RISK (behavior drift): Refactoring prompts could change CI/non-TTY output. Mitigation: only the `--wizard` interactive branch changes; CI/AI_AGENT forced to stdin; existing installer tests regression-lock (AC-01/AC-02).
- RISK (bash entrypoint decision): Turning stubs into real wrappers vs deprecating is a public-surface decision. Mitigation: implementer must confirm the intended primary install path with the maintainer before editing readme-install.md; treat as its own reviewable slice.
- RISK (pre-existing edits): `tools/ai/install/packs.php` has uncommitted working-tree changes and `adapter-claude` is WIP. Mitigation: read-only from the wizard; flag any packs.php edit and do not clobber pre-existing changes.
- ROLLBACK: The change is additive (one new file + a bounded rewrite of one function + dev-only composer entry). Revert `install_workflow.php` wizard block, delete `selection-engine.php`, drop the `require-dev`/`suggest` composer entry, and revert the two bash entrypoints to restore prior behavior. No data migration, no engine change, so rollback is a straight git revert of the touched files.

## Handoff Notes

- Recommended next step after this plan: implementer, starting with P0 (registry-sourced selection + stdin backend), keeping the non-interactive path frozen and verifying with `composer test:fast` and the `--dry-run` parity commands. Confirm the bash-entrypoint direction (real wrapper vs deprecate) with the maintainer before touching readme-install.md.
- Procedural-first: prefer `aiSelection*` functions over classes; justify any class in review.
- Reuse, do not reimplement: `aiInstallerResolveSelectedPacks`, `aiInstallerAgentDependencyWarnings`, `aiInstallerPackToolRequirements`, `aiPromptLine`, `aiPromptYesNo`, `aiDetectRuntimeMode`.
