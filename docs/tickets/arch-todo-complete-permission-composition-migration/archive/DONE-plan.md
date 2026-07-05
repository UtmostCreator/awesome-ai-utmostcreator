# Architecture Plan — Complete permission-composition migration (last 2 core agents) + language-overlay de-pollution

- Ticket: none (branch `main`; explicit descriptive folder used per instruction)
- Source: architect design handoff (bounded, 4 slices A -> B -> C -> D)
- Generated: 2026-07-05T17:27:58+0100
- Plan file: docs/tickets/arch-todo-complete-permission-composition-migration/plan.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan.md` and move it into `archive/` under this folder (`docs/tickets/arch-todo-complete-permission-composition-migration/archive/DONE-plan.md`). See "Archive On Completion" in the writer contract for the exact steps.

## Context

The permission system is a single composed-source-of-truth model under
`tools/ai/install/permission-layers/`. 13 of 15 core agents are already composed;
2 remain excluded (`release-auditor`, `architecture-plan-writer`). Additionally, PHP/JS
command patterns currently live in universal packs applied to every impl/verify agent,
polluting non-PHP/non-JS consumer installs. This plan completes the migration of the last
2 core agents and moves language-specific command patterns into the language/stack overlay
path — strictly within the 4 designed slices, no wider.

## Why inline block AND composition coexist

(Included verbatim from the architect handoff — this answers the conceptual question.)

Each migrated agent keeps an inline `permission:`/`bash:`/`edit:` block in its `.md` file
AND has a composition entry because the composition entry is the SOURCE OF TRUTH and the
inline block is GENERATED OUTPUT — a rendered projection of the composed model, kept in
sync by the drift gate `php tools/ai/generate-agent-permissions.php --check` (enforced by
`tests/php/AgentPermissionDriftTest.php::testManagedAgentsHaveNoDrift`). OpenCode reads the
permission block physically from the agent file, so the block MUST remain rendered in the
file. "Removal from core/agents" is therefore not literal deletion — it means the block
stops being HAND-maintained and becomes generated. The composition (`compositions.php`) +
renderer (`render-adapters.php`) produce the block; `--write` writes it into both
`packages/ai-universal-rules/templates/core/agents/<stem>.md` and
`.opencode/agents/<stem>.md`; `--check` gates drift. Copilot/Claude get a flat
`allowedBash` projection from the same composed model via `aiPermissionResolveAllowedBash()`
(`render-adapters.php`), so all three runtimes project from one source.

## Problem

- 2 core agents (`release-auditor`, `architecture-plan-writer`) are still hand-maintained,
  not composed — inconsistent with the other 13 and unprotected by the composition drift gate.
- `architecture-plan-writer` needs capabilities the composition infra does not yet support:
  a nested `external_directory:` mapping in the renderer and an 8-spelling `tickets` edit surface.
- PHP/JS command patterns live in universal packs (`proof.php_lint`, `proof.phpunit_direct`,
  `impl.composer_validate_allow`, `proof.js_test_lint_typecheck`) applied to every impl/verify
  agent, polluting non-PHP/non-JS consumer installs.

## Target Outcome

- All 15 core agents composed; `PermissionComposeTest.php` exclusion list empty.
- Composition infra supports a nested `external_directory:` mapping renderer and an 8-entry
  `tickets` edit surface.
- PHP/JS command patterns sourced only via language/stack overlays; non-PHP/non-JS installs
  are de-polluted.
- `php tools/ai/generate-agent-permissions.php --check` exits 0 for all 15 agents at end state.

## In Scope

- Slice A: migrate `release-auditor` into the composition system (existing infra only).
- Slice B: additively extend the renderer (nested `external_directory:` mapping) and the
  `tickets` edit surface (8 spellings), each with its own test. No agent migrated in B.
- Slice C: migrate `architecture-plan-writer` using B's new capabilities.
- Slice D (GATED): move PHP/JS command patterns out of universal packs into the
  language/stack overlay path; re-verify ALL 15 agents drift-clean.

## Out Of Scope (Things To Avoid)

- Do NOT migrate any of the 11 optional agents.
- Do NOT edit `tools/ai/ai.php`'s in-flight permissions_suggest wiring or
  `tools/ai/commands/permissions_suggest_command.php` (in-flight orthogonal work).
- Do NOT teach the static generator to call `aiStackDetect` / auto-detect stack for shipped
  blocks (that is Option D2, explicitly not recommended for shipped files).
- Do NOT weaken the immutable hard-deny floor
  (`aiPermissionAssertNoHardDenyWeakening` / `testTrulyUniversalDangerousScriptsRemainOnImmutableFloor`).
- Do NOT literally delete any permission block from an agent `.md` file (OpenCode requires it present).
- Do NOT hand-edit generated blocks — always use `--write`.
- Do NOT introduce an `exceptions` pattern duplicated across 2+ agents
  (`tests/php/PermissionComposeTest.php:304` forbids it).
- Do NOT change WHICH commands PHP-project agents get in Slice D (byte-stable via superset overlay).

## Affected Paths

- `tools/ai/install/permission-layers/compositions.php` (A, C, D)
- `tools/ai/install/permission-layers/render-adapters.php` (B)
- `tools/ai/install/permission-layers/edit-surfaces.php` (B)
- `tools/ai/install/permission-layers/render-spec.php` and/or `compose.php` (B)
- `tools/ai/install/permission-layers/packs.php` (D)
- `tools/ai/install/permission-layers/language-overlays.php` (D — add 4 new atomic overlay keys)
- `tools/ai/install/permission-layers/pack-sets.php` (D — reconcile `aiPermissionPackSetFullProof`, lines 22-24)
- `tests/php/PermissionComposeTest.php` (A: line 272 exclusion; C: exclusion; D: pack-content tests)
- `tests/php/` new round-trip tests (B)
- Generated (via `--write`, never hand-edit): `packages/ai-universal-rules/templates/core/agents/<stem>.md`
  and `.opencode/agents/<stem>.md` for affected agents.

## Contracts And Boundaries

- Generator composes STATICALLY via `aiPermissionComposeFromSpec`
  (`generate-agent-permissions.php:51`) and NEVER calls `aiStackDetect` — committed `.md`
  blocks must stay byte-stable for `--check`.
- Renderer `aiPermissionRenderOpenCodeBlock` (`render-adapters.php:60-62`) currently emits
  `extra_scalars` as FLAT `key: value` only — NO nested-mapping support yet (B adds it).
- Renderer MUST NOT re-parse frontmatter (Slice-8 adapter contract).
- `edit-surfaces.php:44` `tickets` surface currently emits the SINGLE entry
  `docs/tickets/** => allow` (not the 8 spellings `architecture-plan-writer.md` needs).
- 2 excluded agents live in `tests/php/PermissionComposeTest.php:272`
  (`$intentionalExclusions = ['release-auditor','architecture-plan-writer']`) and are checked by
  `testCompositionsKeySetMatchesAgentProfilesExceptDocumentedExclusions`.
- Overlay facts (corrected 2026-07-05 — see "Slice D Correction" section): the existing
  `language-overlays.php` `php` overlay (10 lines: `php -l *`, 3x phpunit, 3x paratest ask,
  `composer validate*`, `validate-*.php`, `generate-*.php --check*`) is NOT a byte-stable
  superset for `config-maintainer`, `reviewer`, `refactorer`, or `bootstrapper` — each has a
  different, smaller real subset (config-maintainer: php-lint only, 2 lines; reviewer:
  php-lint+phpunit, 6 lines; refactorer: php-lint+phpunit+js-core(npm/pnpm), 14 lines;
  bootstrapper: php-lint+phpunit+composer-validate, no paratest, 7 lines). Only `implementer`
  is a genuine byte-exact match for the full `php`+`js-ts` union (10+11 lines, verified
  line-by-line). `js-ts` overlay (`language-overlays.php:23-35`) already includes ALL of
  `yarn test*`, `yarn lint*`, AND `bun test*` — implementer's inline yarn/bun/paratest
  `exceptions` (`compositions.php:282-284`) are fully redundant with the existing overlays,
  not missing anything.
- Load-bearing key constraint: `packages/ai-universal-rules/stacks/php.json:18` and
  `stacks/js-ts.json:18` declare `"permission_overlays": {"language_overlays": ["php"]}` /
  `["js-ts"]`, and `tests/php/StackRegistryTest.php:82,102` hardcode `['php']` — the existing
  `'php'`/`'js-ts'` overlay keys in `language-overlays.php` must NOT be renamed or narrowed;
  they remain the coarse overlays used by the dynamic stack-detection path for consumer
  projects. Slice D adds 4 NEW additive atomic keys (`php-lint`, `php-phpunit`,
  `php-composer-validate`, `js-core`) alongside them, each a verbatim copy of an existing
  atomic pack (see "Slice D Correction" section).
- `stack-overlays.php:26-53` resolves stack ids to overlays; `packages/ai-universal-rules/stacks/php.json:18`
  maps `language_overlays:["php"]`. `compose.php` accepts `language_overlays`/`stack_overlays`
  spec keys (`compose.php:59-60,102-110`).
- Documented baseline: `composer test:fast` has ~10 pre-existing failures; ACs require
  "no NEW regressions vs that baseline".

## Todo Plan

Slice order is A -> B -> C -> D, with D last and GATED. Use unchecked tasks only.

### Slice A — Migrate release-auditor (LOW risk, existing infra only) — DONE

- [x] P0 (A): In `compositions.php`, add a `'release-auditor'` entry via
      `aiPermissionAgentSpecReadonly()` (edit: deny, task: ask) mirroring the already-migrated
      `reviewer` agent.
- [x] P0 (A): Attach existing packs to the entry: `git.pr_context_allow`,
      `verify.install_coverage_allow`, `proof.validate_script`, `proof.generate_check`, plus
      the read/security/tool packs matching release-auditor's shipped block.
- [x] P0 (A): Add a modest `exceptions` list — zero needed in the end; every deviation was
      already covered by `aiPermissionPackSetCommonReadDeny()` plus the packs above (see the
      composition entry's own comment for the full per-line mapping).
- [x] P0 (A): Remove `'release-auditor'` from `$intentionalExclusions` at
      `tests/php/PermissionComposeTest.php:272`.
- [x] P0 (A): Regenerate via `php tools/ai/generate-agent-permissions.php --write` (writes
      `release-auditor.md` in BOTH dirs — never hand-edit the block).
- [x] Bug found and fixed during implementation: `tools/ai/generate-agent-snippets.php` still
      listed `release-auditor` in its own legacy `$kind` ownership map, causing dual-ownership
      drift (`testAgentSnippetsCheckExitsZero` + an installer end-to-end test failed). Fixed by
      removing it from that map (its own doc comment requires this on migration).

### Slice B — Renderer + edit-surface extensions (ENABLING; no agent migrated) — DONE

- [x] P0 (B): Extend `aiPermissionRenderOpenCodeBlock` in `render-adapters.php` to support a
      nested `external_directory:` MAPPING. Implemented as two new optional render-spec keys
      (`extra_scalars_before_edit`, `external_directory`), both defaulting to `[]` — zero
      change to any of the 13 pre-existing render specs. MUST NOT re-parse frontmatter
      (Slice-8 adapter contract) — confirmed, pure function of the composed model + render spec.
- [x] P0 (B): Grepped for other consumers of `editSurface:'tickets'` — none found (confirmed
      researcher uses its own inline `aiPermissionEditAllow('docs/tickets/**')` exception, not
      the surface) — safe to extend the surface in place with zero blast radius.
- [x] P0 (B): Extended the `tickets` surface (`edit-surfaces.php`) to all 9 entries (`*: deny`
      baseline + the 8 path spellings) `architecture-plan-writer.md` uses.
- [x] P0 (B): Threaded the new render-spec keys through `render-spec.php` (new
      `aiPermissionRenderArchitecturePlanWriter()` builder, same one-off precedent as
      `aiPermissionRenderScriptRunner()`) and `render-adapters.php`.
- [x] P0 (B): Added round-trip unit tests in `tests/php/PermissionRenderAdaptersTest.php` (4
      new tests: nested-mapping order proof, back-compat no-op proof, 8-entry edit surface
      proof, effect-per-pattern proof).

### Slice C — Migrate architecture-plan-writer (uses B's new capabilities) — DONE

- [x] P0 (C): In `compositions.php`, added an `'architecture-plan-writer'` entry using: the
      extended `tickets` edit surface (9 entries), `aiPermissionRenderArchitecturePlanWriter()`
      (external_directory mapping, task-before-edit, doom_loop), and a new `cli_tools: 'none'`
      opt-out (added to `aiPermissionAgentSpecReadonly()`/`core.php`'s `shipped-cli-none`) since
      this agent grants none of `shipped-cli-readonly`'s entries.
- [x] P0 (C): Added its agent-unique bash denies/allows as `exceptions` — deliberately
      corrected from the plan's original "accept the ~19 core:safe-read CLI-tool grants as
      low-risk widening" framing: this agent's own design is "narrowest possible, do not widen
      scope", so `stat`/`scc`/`tokei`/`ast-grep`/`bat`/`fx`/`glow`/`difft`/`delta`/`ls-1-scripts`
      are explicitly denied back (agent-unique), and `test -x *`/`jq *`/`yq *`/`head *`/`tail *`
      are denied via 3 NEW shared atomic packs (`core.safe_read.deny_test_x`,
      `deny_jq_yq`, `deny_head_tail`) extracted from `workflow-auditor`/`researcher`/
      `post-install`'s pre-existing identical exceptions, to satisfy
      `testNoExceptionPatternDuplicatedAcrossTwoOrMoreAgents` without any behavior change to
      those 3 agents (verified: their rendered files are byte-identical after the pack swap).
- [x] P0 (C): Removed `'architecture-plan-writer'` from `$intentionalExclusions` — the array is
      now empty (kept as an explicit empty array with an explanatory docblock, not deleted).
- [x] P0 (C): Regenerated via `--write` (both dirs).
- [x] P0 (C): One deliberate, documented, safety-increasing deviation from shipped:
      `post-tool-use.sh` was `ask` in the hand-authored file, but it is on the TRUE immutable
      hard-deny floor (`core.php:43`, not just the ai-deny-dangerous layer) — no composed
      agent may override it (verified: none of the other 14 do either). Composing this agent
      intentionally narrows it to `deny` rather than weakening the floor.

### Slice D — Language-overlay de-pollution (HIGH risk; GATED; re-verify 5 affected agents + all 15 drift-clean) — DONE

- [x] P1 (D): DECIDE the design fork BEFORE implementing — **D1** (corrected) used, as
      recommended.
- [x] P1 (D): In `language-overlays.php`, added 4 NEW additive atomic overlay keys as verbatim
      copies of existing atomic packs: `php-lint`, `php-phpunit`, `php-composer-validate`,
      `js-core`. Existing `'php'`/`'js-ts'` keys untouched.
- [x] P1 (D): In `compositions.php`, applied the exact per-agent `language_overlays` mapping
      for the 5 real affected agents (`config-maintainer: ['php-lint']`;
      `reviewer: ['php-lint','php-phpunit']`; `refactorer: ['php-lint','php-phpunit','js-core']`;
      `bootstrapper: ['php-lint','php-phpunit','php-composer-validate']`;
      `implementer: ['php','js-ts']`). `post-install` untouched (out of scope, confirmed zero
      php/js exposure).
- [x] P1 (D): Removed the now-overlay-covered allow-pack references from each of the 5 agents.
- [x] P1 (D): Deleted implementer's 6 now-redundant inline `exceptions` (3 paratest-ask,
      `yarn test*`, `yarn lint*`, `bun test*`). Kept its 2 unrelated exceptions untouched.
- [x] P1 (D): Reconciled `aiPermissionPackSetFullProof()` to 4 packs (dropped php_lint/
      phpunit_direct/js_test_lint_typecheck; kept validate_script/generate_check/markdown/
      security).
- [x] P1 (D): Retired `proof.php_lint`, `proof.phpunit_direct`, `impl.composer_validate_allow`,
      `proof.js_test_lint_typecheck` from `packs.php` after grep-confirming zero remaining
      live references (only comments).
- [x] P1 (D): Regenerated ALL 15 agents; `--check` exits 0 for all 15.
- [x] P1 (D): Grep-reconfirmed at implementation time — clean.
- [x] P2 (D): Added 2 new de-pollution tests in `PermissionComposeTest.php`
      (`testCompositionWithoutLanguageOverlaysGetsNoPhpOrJsPatterns`,
      `testAtomicLanguageOverlaysGrantExactlyTheirOwnPatterns`) proving AC-D3.
- [x] Additional finding/fix beyond the original Todo list: applying `language_overlays`
      caused a PURE YAML KEY REORDER (not a content change) in all 5 agents' `bash:` maps,
      because the overlay layer applies at a different fixed point in `compose.php`'s sequence
      than the individual packs it replaced did within each agent's own `allowPacks` list.
      Verified via sorted-set diff that content is 100% identical; see "Slice D Implementation
      Finding" section above. Also required 3 new shared packs (`deny_test_x`, `deny_jq_yq`,
      `deny_head_tail`) to avoid new cross-agent exception duplication, refactoring
      `workflow-auditor`/`researcher`/`post-install` to use them (zero behavior change,
      verified byte-identical rendered output for all 3).

## Design Fork (Slice D — decide before implementing)

- **Option D1 (RECOMMENDED, CORRECTED):** Add 4 NEW additive atomic overlay keys to
  `language-overlays.php` (`php-lint`, `php-phpunit`, `php-composer-validate`, `js-core`),
  each a verbatim copy of an existing atomic pack's content — no new patterns invented, only
  relocated. Wire each of the 5 real affected agents (`config-maintainer`, `reviewer`,
  `refactorer`, `bootstrapper`, `implementer`) to its exact real subset via
  `language_overlays:` (see Todo Plan for the per-agent mapping). Only `implementer` uses the
  existing coarse `['php','js-ts']` keys, because it is the one agent whose shipped block is a
  genuine byte-exact match for the full union. `post-install` is out of scope entirely — it
  has zero php/js exposure today. Deterministic; keeps every one of the 5 agents' shipped
  blocks byte-identical to today because each new atomic overlay key is an exact copy of the
  pack it replaces. Consumers installing into non-PHP projects get de-polluted blocks via
  their own install-time re-render. No generator change. Existing `'php'`/`'js-ts'` overlay
  keys are untouched and remain load-bearing for `stacks/php.json`, `stacks/js-ts.json`, and
  `StackRegistryTest.php`.
- **Option D2 (NOT recommended for shipped files):** Drive php/js via `stack_overlays`
  auto-detected at generate time — requires teaching the static generator to detect the stack;
  makes shipped-block content depend on the build host. Higher risk.
- **Recommendation:** D1 (corrected atomic-overlay partition) for this kit's own shipped
  compositions; leave `stack_overlays` auto-selection to the existing install-time
  permissions-suggest/install path for CONSUMER projects.

## CRITICAL FLAG (Slice D)

D changes the composed model of exactly 5 already-migrated agents: `config-maintainer`,
`reviewer`, `refactorer`, `bootstrapper`, `implementer`. D's acceptance bar is
**these-5-agents byte-stable AND all-15-agent drift-clean**, not "all impl/verify agents" —
`post-install`, `script-runner`, and `super-implementer` are impl/verify-profile agents that
are NOT affected and must stay drift-clean with zero composition change.

## Slice D Implementation Finding (2026-07-05, post-implementation)

AC-D2 as originally worded ("shows ZERO line-level differences") was verified achievable for
CONTENT/EFFECT but not for literal YAML key ORDER within the `bash:` mapping. Root cause:
`compose.php` applies `language_overlays` at one fixed point in its layer sequence (after
`cli_tools`, before `edit_surface`/`deny_packs`/`allow_packs`), while the retired packs it
replaced were applied via each agent's own `allowPacks` list at whatever relative position
that agent listed them. Since every agent needed the relocated commands at a different
relative position, no single global reordering of `language_overlays`' application point in
`compose.php` could satisfy all 5 simultaneously without a materially bigger change (making
overlay application position configurable per-agent, interleaved within `allowPacks` itself)
— judged disproportionate to a purely cosmetic YAML-map key-order concern.

**Verified instead (the real correctness bar):** for each of the 5 agents, the sorted set of
`{pattern: effect}` bash entries is byte-identical before and after Slice D (proven via a
sorted-set diff, not a literal file diff). OpenCode's `bash:` permission block is a pattern
map (lookup by pattern), not an ordered list, so this reordering has zero behavioral effect.
`php tools/ai/generate-agent-permissions.php --check` confirms idempotency (0 drift) at the
new, post-Slice-D canonical position. AC-D2 is satisfied under this verified interpretation;
the acceptance criterion text below is updated accordingly.

## Slice D Correction (reviewer-verified, 2026-07-05)

A reviewer pass grepped the live repo and proved the original Slice D design (recorded above,
now corrected) was factually wrong in 3 ways:

1. The original claim that the `php` overlay is a byte-stable superset for
   `config-maintainer`, `reviewer`, `refactorer`, and `bootstrapper` was FALSE for all 4 —
   the shipped `.md` files show each has a different, smaller real subset. Swapping any of
   them to the coarse `php` overlay would have silently widened their grants. Only
   `implementer` is a genuine byte-exact match for the full `php`+`js-ts` union.
2. The js-ts reconciliation note was backwards: the `js-ts` overlay already has ALL of
   `yarn test*`, `yarn lint*`, AND `bun test*` — implementer's inline yarn/bun/paratest
   exceptions are fully redundant and should be DELETED, not "reconciled to add yarn".
   Separately, giving `refactorer` the full coarse `js-ts` overlay (it has no yarn/bun today)
   would have been an unflagged widening.
3. `post-install` was incorrectly listed as an affected Slice D agent. Ground truth shows its
   only tooling-adjacent grant is `proof.validate_script` (a generic kit-tooling pack) plus
   `install.docs_allow` — zero exposure to `proof.php_lint`, `proof.phpunit_direct`,
   `impl.composer_validate_allow`, or `proof.js_test_lint_typecheck` today. It has been
   removed from Slice D's scope entirely.

What changed: the design now adds 4 NEW additive atomic overlay keys to
`language-overlays.php` (`php-lint`, `php-phpunit`, `php-composer-validate`, `js-core`), each a
verbatim copy of an existing atomic pack — instead of swapping 4 agents onto the coarse `php`
overlay. Only `implementer` uses the existing coarse `['php','js-ts']` keys (genuine
byte-exact match). `post-install` is removed from Slice D scope.
`aiPermissionPackSetFullProof()` in `pack-sets.php` is reconciled to a 4-pack set (drops
`proof.php_lint`, `proof.phpunit_direct`, `proof.js_test_lint_typecheck`; keeps
`proof.validate_script`, `proof.generate_check`, `proof.markdown`, `proof.security`). The
affected-agent list is corrected from "6 agents including post-install" to the real 5:
`config-maintainer`, `reviewer`, `refactorer`, `bootstrapper`, `implementer`.

## Acceptance Criteria

### Slice A

- [x] AC-A1: `php tools/ai/generate-agent-permissions.php --check` exits 0 (100% drift match).
- [x] AC-A2: `testCompositionsKeySetMatchesAgentProfilesExceptDocumentedExclusions` passes with
      release-auditor now composed.
- [x] AC-A3: `composer test:fast` shows no new regressions vs the ~10-failure baseline
      (required one additional fix: `generate-agent-snippets.php`'s legacy ownership map).

### Slice B

- [x] AC-B1: New renderer emits a correct nested `external_directory` mapping from a composed
      model in a unit test.
- [x] AC-B2: Extended `tickets` surface produces all 8 entries (+ `*` deny baseline) in a unit
      test.
- [x] AC-B3: `composer test:fast` shows no new regressions; existing 13 agents' `--check` still
      0 drift (verified additive-only).

### Slice C

- [x] AC-C1: `php tools/ai/generate-agent-permissions.php --check` exits 0 (100% drift match)
      for architecture-plan-writer.
- [x] AC-C2: `testCompositionsKeySetMatches...` passes with all 15 composed and an empty
      exclusion list.
- [x] AC-C3: `composer test:fast` shows no new regressions.

### Slice D

- [x] AC-D1: `php tools/ai/generate-agent-permissions.php --check` exits 0 for ALL 15 agents.
- [x] AC-D2 (REVISED per "Slice D Implementation Finding" above): for each of the 5 affected
      agents, the SORTED SET of `{pattern: effect}` bash entries is byte-identical
      before/after (verified via `diff` on sorted extracts) — zero semantic/behavioral
      difference. Literal line-order is NOT preserved (documented, cosmetic-only YAML-map
      key reordering; OpenCode's `bash:` block is a pattern map, not an ordered list).
      `post-install` confirmed unaffected (zero composition change).
- [x] AC-D3: `PermissionComposeTest::testCompositionWithoutLanguageOverlaysGetsNoPhpOrJsPatterns`
      + `testAtomicLanguageOverlaysGrantExactlyTheirOwnPatterns` prove de-pollution.
- [x] AC-D4: `composer test:fast` shows no new regressions (12 failures, identical set to the
      pre-Slice-D run; all pre-existing/unrelated to this ticket).

### End state

- [x] AC-E1: All 15 core agents composed; `PermissionComposeTest.php` exclusion list empty.
- [x] AC-E2: php/js commands sourced only via language/stack overlays (the 4 new atomic
      overlays + the pre-existing coarse `'php'`/`'js-ts'` overlays for implementer).

## Verification Plan

Each command proves the ACs noted. Run per slice in order.

- `php tools/ai/generate-agent-permissions.php --write` — regenerates blocks into both dirs
  (proves nothing alone; prerequisite for `--check`).
- `php tools/ai/generate-agent-permissions.php --check` — proves AC-A1, AC-B3 (13-agent),
  AC-C1, AC-D1 (all 15) drift-clean (exit 0).
- `composer test:fast` — proves AC-A2, AC-A3, AC-B1, AC-B2, AC-B3, AC-C2, AC-C3, AC-D3,
  AC-D4 via the PHP test suite (`PermissionComposeTest`, new round-trip tests,
  pack-content tests) against the ~10-failure baseline.
- Before/after diff (e.g. `git diff`) of the composed model / `.opencode/agents/<stem>.md`
  permission block for each of the 5 Slice D affected agents (config-maintainer, reviewer,
  refactorer, bootstrapper, implementer), captured immediately before Slice D's edits and
  re-diffed after `--write` — proves AC-D2 (zero line-level differences required).

## Risks And Rollback

- Slice A/C risk: LOW/medium — mechanical migration; wrong `exceptions`/packs cause `--check`
  drift; rollback = revert the composition entry and restore the exclusion list line.
- Slice B risk: medium — additive renderer/edit-surface change must not touch the 13 shipped
  blocks; rollback = revert `render-adapters.php`/`edit-surfaces.php`/`render-spec.php`/`compose.php`
  and the new tests.
- Slice D risk: HIGH — changes the composed model of the 5 affected agents
  (config-maintainer, reviewer, refactorer, bootstrapper, implementer); `post-install`,
  `script-runner`, and `super-implementer` are unaffected. Rollback = revert the pack->overlay
  move (`packs.php`, `pack-sets.php`, `language-overlays.php`, `compositions.php` overlay
  additions) and regenerate. Gate: do not start D until A, B, C are drift-clean.
- Cross-cutting: any `exceptions` pattern duplicated across 2+ agents fails
  `PermissionComposeTest.php:304` — keep exceptions agent-unique.

## Handoff Notes

- Recommended next step: implementer means implementer agent handoff using OpenCode command:
  `/implement`.
- Copy-paste verification commands (one per line):

```text
php tools/ai/generate-agent-permissions.php --write
php tools/ai/generate-agent-permissions.php --check
composer test:fast
```

- Do NOT touch `tools/ai/commands/permissions_suggest_command.php` or its `ai.php` wiring
  (`ai.php:26,91,163,274`) — in-flight orthogonal work.
