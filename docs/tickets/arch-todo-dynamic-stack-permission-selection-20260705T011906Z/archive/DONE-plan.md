# Architecture Plan — Dynamic Stack Permission Selection

- Ticket: none
- Source: task description
- Generated: 20260705T011906Z
- Plan folder: docs/tickets/arch-todo-dynamic-stack-permission-selection-20260705T011906Z/

## Context

Risk: medium-high. This is a follow-up to layered permission Slice 1. Slice 1 of [`docs/tickets/arch-todo-permission-layer-composition-20260705T004618Z/plan.md`](../arch-todo-permission-layer-composition-20260705T004618Z/plan.md) introduced PHP-array permission layers and `aiPermissionComposeFromSpec()`. This follow-up makes stack overlays dynamic at install/wizard time.

## Problem

Agents need to start from zero/minimal permissions while install-time stack selection and detection adds permission overlays based on agent category/profile, selected stack(s), auto-detected target project signals, global safety layers, and user/maintainer-provided stack descriptors.

## Target Outcome

Use a hybrid JSON descriptor + PHP loader/normalizer design that supports dynamic stack permission selection without weakening the hard-deny floor. JSON remains dependency-free, schema-validatable, easier for users to extend than PHP arrays, and consistent with repo schema/projection patterns. PHP loader code owns validation, normalization, precedence, conflict handling, and computed fields. Generated `docs/ai/stack-registry.json` is not canonical source.

## In Scope

- Add canonical shipped stack descriptors under `packages/ai-universal-rules/stacks/*.json`.
- Add project-local descriptor discovery under `.ai/stacks/*.json`.
- Add stack descriptor and stack registry schemas under `schemas/ai/`.
- Add read-only stack registry loading, normalization, detection, and safe version-check support.
- Add wizard/CLI stack selection flags and non-interactive behavior for stack detection/selection.
- Add stack metadata write-through to `.ai/project.yml` summaries and `.ai-install-manifest.json` structured evidence.
- Add stack facts to `docs/ai/project-context.md` only through existing project-value rendering.
- Add stack permission overlays and minimal integration with `aiPermissionComposeFromSpec()`.
- Add generated `docs/ai/stack-registry.json` projection with `--check` and `--write` modes.
- Add focused tests for stack registry, detection, permission composition, metadata, generated registry, and end-to-end render integration.

## Out Of Scope (Things To Avoid)

- Do not add a YAML parser dependency.
- Do not install missing stack tools.
- Do not execute package-manager scripts during detection or version checks.
- Do not edit generated `docs/ai/project-context.md` directly as source of truth.
- Do not weaken `core:hard-deny` or bash `*` floor behavior.
- Do not hardcode wizard stack options.
- Do not absorb existing out-of-scope `composer test:fast` drift failures into this plan unless touched.
- Do not redesign Slice 1 permission-layer composition beyond the minimal stack overlay integration listed here.

## Affected Paths

- `packages/ai-universal-rules/stacks/{php,js-ts,python,go,rust,java,dotnet,ruby,shell,markdown,github-actions,make}.json`
- `schemas/ai/stack-descriptor.schema.json`
- `schemas/ai/stack-registry.schema.json`
- `tools/ai/install/stack-registry.php`
- `tools/ai/install/stack-detection.php`
- `tools/ai/install/permission-layers/stack-overlays.php`
- `tools/ai/generate-stack-registry.php`
- `tests/php/StackRegistryTest.php`
- `tests/php/StackDetectionTest.php`
- `tests/php/StackPermissionComposeTest.php`
- `docs/ai/stack-registry.json`
- Later integration paths: `tools/ai/install/config.php`, `tools/ai/commands/install_workflow.php`, `tools/ai/install/core.php`, `tools/ai/install/manifest.php`, `tools/ai/install/project-yaml.php`, `tools/ai/install/permission-layers/compose.php`, `tools/ai/install/permission-layers/language-overlays.php`

## Contracts And Boundaries

- Canonical shipped descriptors live under `packages/ai-universal-rules/stacks/*.json`; user/project local descriptors live under `.ai/stacks/*.json`.
- Required descriptor fields are `schema_version`, `id`, `label`, `detection`, `permission_overlays`, and `project_context`.
- Initial shipped stacks are `php`, `js-ts`, `python`, `go`, `rust`, `java`, `dotnet`, `ruby`, `shell`, `markdown`, `github-actions`, and `make`.
- Descriptor IDs must match `^[a-z][a-z0-9-]*$`.
- Version checks are read-only only and **structured**, not arbitrary shell strings. Descriptor entries
  use `{"id":"php","tool":"php","args":["-v"],"required":false}`. Validate `tool` with a strict
  executable-name regex, allow only `[]`, `["--version"]`, `["-v"]`, or `["version"]` args, and execute
  without a shell. They must never install tools or execute project scripts.
- Detection order is exact file markers, bounded globs, then optional `scc` only when available; missing or failing `scc` is warning-only.
- Detection must not inspect secrets or recursively scan ignored/vendor-heavy directories beyond bounded globs.
- Duplicate IDs from `.ai/stacks/*.json` override shipped descriptors only when `extends` or `override: true` is explicit; otherwise duplicate IDs fail validation.
- Merge order is deterministic by `merge_priority`, then stack id.
- Stack descriptor validation must ensure referenced `language_overlays` exist, version commands match the safe allowlist, descriptors cannot grant destructive shell commands, and overlays cannot weaken the immutable permission floor.
- `.ai/project.yml` remains the human-editable source for project facts.
- `.ai-install-manifest.json` records install-time stack detection evidence and version-check results.
- `docs/ai/project-context.md` remains rendered/derived from project values and is not edited directly for stack metadata.
- Add simple scalar project-value keys first: `selectedStacks` and `detectedStacks` are comma-separated
  summaries, `primaryStack` is one stack id, `stackToolVersions` is a human-readable summary string,
  and `recommendedVerificationCommands` is a newline-free delimited summary. Structured details live
  only in `.ai-install-manifest.json` and generated registry artifacts.
- Wizard/CLI flags are `--stacks php,js-ts`, `--no-stack-detect`, and `--stack-detect-only`.
- Non-interactive behavior detects automatically and uses detected stacks unless `--stacks` is provided; never prompt under `--no-interaction`, `--ci`, or agent mode.
- Extend `aiPermissionComposeFromSpec()` minimally to accept `stack_overlays`, for example:

```php
aiPermissionComposeFromSpec([
  'profile' => 'impl',
  'edit_surface' => 'code',
  'verify_tier' => 'verify-focused',
  'stack_overlays' => ['php', 'markdown'],
  'language_overlays' => []
]);
```

- Permission layer merge order is `core:safe-read → core:git-read → profile/script tiers → verify tier → stack overlays → explicit language overlays → edit surface → agent exceptions → core:hard-deny`.
- Stack overlays may reference existing language overlays and may add specific safe version/test/lint patterns; hard-deny remains immutable.
- Implement these functions:

```php
aiStackDetect(string $targetRoot, array $registry, array $options = []): array
aiStackRunVersionChecks(string $targetRoot, array $selectedStacks): array
aiStackMergeSelections(array $detected, array $selected, array $disabled): array
```

- Stack descriptor model:

```json
{
  "schema_version": 1,
  "id": "php",
  "label": "PHP",
  "description": "PHP Composer/PHPUnit projects",
  "category": "language",
  "detection": {
    "files": ["composer.json"],
    "globs": ["*.php", "src/**/*.php", "tests/**/*.php"],
    "commands": [],
    "scc_languages": ["PHP"],
    "confidence": {"composer.json": 90, "*.php": 40, "scc:PHP": 60}
  },
  "version_checks": [
    {"id": "php", "tool": "php", "args": ["-v"], "required": false},
    {"id": "composer", "tool": "composer", "args": ["--version"], "required": false}
  ],
  "permission_overlays": {"language_overlays": ["php"], "extra_layers": []},
  "project_context": {
    "primaryLanguage": "PHP",
    "primaryRuntime": "PHP >=8.2",
    "packageManager": "composer",
    "primaryTestCommand": "composer test",
    "primaryVerifyCommand": "composer test"
  },
  "recommended_commands": {"install": "composer install", "test": "composer test", "lint": "php -l <changed-files>"},
  "package_managers": ["composer"],
  "conflicts": [],
  "implies": ["markdown", "shell"],
  "merge_priority": 50
}
```

## Todo Plan

- [x] P0: Slice 1 — read-only stack registry + detector. Add schema; add descriptors for php, js-ts, shell, markdown, github-actions; add `stack-registry.php`, `stack-detection.php`, and tests using temp fixture roots. No wizard or permission rendering changes. **DONE** (all 12 descriptors landed; `StackRegistryTest`, `StackDetectionTest` green).
- [x] P0: Slice 2 — wizard + CLI stack selection, no permission rendering yet. Add `--stacks`, `--no-stack-detect`, `--stack-detect-only`; wizard displays detected stacks; run safe version checks; dry-run artifact reports selected/detected stacks and versions. Add focused `StackInstallWorkflowTest` (or `InstallWorkflowStackTest`) and use that exact PHPUnit filter. **DONE** (`StackInstallWorkflowTest` green; `tools/ai/commands/stack_selection.php` added).
- [x] P1: Slice 3 — permission composition vertical proof for one stack. Add `stack-overlays.php`; extend `aiPermissionComposeFromSpec()` with `stack_overlays`; prove PHP stack adds PHP overlay and cannot weaken hard deny. **DONE** (`stack-overlays.php` wired into `compose.php:10,64-67`; `StackPermissionComposeTest` green).
- [x] P1: Slice 4 — project metadata write-through. Extend `.ai/project.yml` support for scalar stack fields; write structured stack evidence to `.ai-install-manifest.json`; render project context from project values. If selected/detected stack facts are exposed beyond existing `primaryStack`, update the project-context template and placeholder map in this same slice. **DONE** (write-through never clobbers explicit user values; informational `.ai/stack-detection.json`; fixed pre-copy detection ordering + confidence-based `primaryStack` bugs; `StackProjectValuesTest` green).
- [x] P1: Slice 5 — remaining stack descriptors + generated registry. Add python/go/rust/java/dotnet/ruby/make; add `generate-stack-registry.php --check/--write`; add deterministic `docs/ai/stack-registry.json` projection. **DONE** (all 12 descriptors + `generate-stack-registry.php --check/--write` → `docs/ai/stack-registry.json`).
- [x] P2: Slice 6 — end-to-end install/render integration. Feed selected stacks into agent permission rendering once permission generator is ready; ensure OpenCode/Copilot/Claude projections use same stack-aware model; add drift tests. **DONE for the install-side model** (`StackEndToEndInstallTest` with real subprocess installs into temp targets). **Carry-over:** Copilot/Claude *renderer* projections still parse frontmatter `allowedBash` rather than the composed stack-aware model — that renderer rewiring is tracked in the permission plan Slice 5 (`arch-todo-permission-layer-composition-20260705T004618Z/plan.md`), which owns the shared renderer abstraction.

## Acceptance Criteria

- [x] AC-01: Stack descriptors live as JSON under `packages/ai-universal-rules/stacks/*.json`; local extensions are discoverable from `.ai/stacks/*.json`.
- [x] AC-02: Descriptor schema exists and validates detection, version check, permission overlay, context, and command metadata.
- [x] AC-03: Stack detection uses exact files/globs first and optional `scc` second; missing/failing `scc` is warning-only.
- [x] AC-04: Version checks are read-only and cannot install tools or run project scripts.
- [x] AC-05: Wizard and CLI can select stacks, disable auto-detection, and report detected confidence.
- [x] AC-06: Selected/detected stack metadata is written to `.ai/project.yml` summaries and `.ai-install-manifest.json` structured evidence.
- [x] AC-07: `docs/ai/project-context.md` receives stack facts only through project-value rendering, not direct manual edits.
- [x] AC-08: Permission composition accepts stack overlays without weakening hard-deny floor.
- [x] AC-09: PHP stack grants PHP-focused verification permissions; JS/TS grants package-manager test/lint/typecheck permissions; shell/markdown/github-actions add relevant safe tools.
- [x] AC-10: New stack files can be added without modifying wizard option lists.
- [x] AC-11: Generated `docs/ai/stack-registry.json` has check mode to prevent stale projection.

## Verification Plan

- [x] Slice 1 verification: run `php -l tools/ai/install/stack-registry.php`.
- [x] Slice 1 verification: run `php -l tools/ai/install/stack-detection.php`.
- [x] Slice 1 verification: run `php tools/ai/validate-schemas.php` after adding stack schemas.
- [x] Slice 1 verification: run `vendor/bin/phpunit --configuration phpunit.xml.dist --filter StackRegistryTest`.
- [x] Slice 1 verification: run `vendor/bin/phpunit --configuration phpunit.xml.dist --filter StackDetectionTest`.
- [x] Slice 2 verification: run `php -l tools/ai/install/config.php`.
- [x] Slice 2 verification: run `php -l tools/ai/commands/install_workflow.php`.
- [x] Slice 2 verification: run `vendor/bin/phpunit --configuration phpunit.xml.dist --filter StackInstallWorkflowTest` (or the exact focused test class added in Slice 2).
- [x] Slice 3 verification: run `php -l tools/ai/install/permission-layers/stack-overlays.php`.
- [x] Slice 3 verification: run `php -l tools/ai/install/permission-layers/compose.php`.
- [x] Slice 3 verification: run `vendor/bin/phpunit --configuration phpunit.xml.dist --filter PermissionComposeTest`.
- [x] Slice 3 verification: run `vendor/bin/phpunit --configuration phpunit.xml.dist --filter StackPermissionComposeTest`.
- [x] Slice 4 verification: run `php -l tools/ai/install/core.php`.
- [x] Slice 4 verification: run `php -l tools/ai/install/manifest.php`.
- [x] Slice 4 verification: run `vendor/bin/phpunit --configuration phpunit.xml.dist --filter ProjectValues`.
- [x] Slice 4 verification: run `vendor/bin/phpunit --configuration phpunit.xml.dist --filter InstallManifest`.
- [x] Slice 5 verification: run `php -l tools/ai/generate-stack-registry.php`.
- [x] Slice 5 verification: run `php tools/ai/generate-stack-registry.php --check`.
- [x] Slice 5 verification: run `php tools/ai/validate-schemas.php` after adding remaining descriptors/projection schema changes.
- [x] Slice 5 verification: run `vendor/bin/phpunit --configuration phpunit.xml.dist --filter StackRegistryTest`.
- [x] Slice 6 verification: run `vendor/bin/phpunit --configuration phpunit.xml.dist --filter PermissionComposeTest`.
- [x] Slice 6 verification: run `vendor/bin/phpunit --configuration phpunit.xml.dist --filter StackPermissionComposeTest`.
- [x] Slice 6 verification: run `vendor/bin/phpunit --configuration phpunit.xml.dist --filter AgentPermissionDriftTest`.
- [x] Slice 6 verification: run `php tools/ai/generate-stack-registry.php --check`.
- [x] Slice 6 verification: run `composer test:fast`.

## Risks And Rollback

- Risk: medium-high because this touches installer flow, agent permission rendering, and project metadata.
- Rollback: revert added stack registry/detector files and stack CLI args; existing permission-layer Slice 1 remains usable without stack overlays.
- Success signal: focused stack registry/detection/compose tests pass and install dry-run reports selected stacks without changing files.
- Release-safety review is recommended before applying generated permission changes across all runtime adapters.
- Unknown: exact integration order with the permission generator readiness is not proven by this handoff.
- Pre-existing worktree changes were present before this plan was written and are not part of this plan.
- Dynamic-stack Slice 1 may start now because it touches only registry/detector/schema/tests and no
  permission rendering; Slice 3 is blocked on landing or explicitly coordinating the untracked
  permission-layer Slice 1 API (`tools/ai/install/permission-layers/*`, `PermissionComposeTest`).

## Completion Status (2026-07-05)

- **All 6 slices DONE.** 12 stack descriptors, schema validation, read-only detector, wizard/CLI
  selection flags, `.ai/project.yml` write-through, generated `docs/ai/stack-registry.json`, and
  end-to-end real-install integration tests all landed and green.
- New tests (all passing): `StackRegistryTest`, `StackDetectionTest`, `StackInstallWorkflowTest`,
  `StackPermissionComposeTest`, `StackProjectValuesTest`, `StackEndToEndInstallTest`.
- Final verification: full `composer test` (serial) and `composer test:fast` (parallel) both end at
  the same 10 pre-existing, unrelated baseline failures (graphify skill size, sh-introspect/ShHelp
  golden-snapshot drift, `AgentsManifestTest` missing script-runner, generate-repo-structure
  metadata gaps). Zero new regressions from this plan.

### Remaining follow-up (deferred, flagged — not silently skipped)

- [ ] **F-1 (packaging, outside both plans' explicit scope):** decide whether
  `tools/ai/install/permission-layers/*` and `packages/ai-universal-rules/stacks/*.json` need
  `packs.php` registration to ship to downstream-installed targets. This is a pre-existing
  packaging pattern not exercised by any current test; confirm downstream-shippable install support
  is required before adding pack entries. If required, do it as a bounded slice with an
  install-surface test proving the files land in a fresh target.
- [ ] **F-2 (carry-over, owned by permission plan Slice 8, consumed by Slice 5):** Copilot/Claude
  renderer projections are not yet stack-aware because they still parse frontmatter `allowedBash`.
  Owned by `arch-todo-permission-layer-composition-20260705T004618Z/plan.md` Slice 8 (the adapter
  seam that actually does the projection work); Slice 5 consumes the Slice 8 adapters to regenerate
  the shipped surfaces, so it does not separately re-absorb F-2.

## Handoff Notes

- This plan is bounded to dynamic stack permission selection as a follow-up to layered permission Slice 1.
- Implement in slices; do not start with all runtime adapter projections until registry, detector, and one-stack permission composition are proven.
- Keep generated `docs/ai/stack-registry.json` as projection/check output only; do not treat it as canonical descriptor source.
