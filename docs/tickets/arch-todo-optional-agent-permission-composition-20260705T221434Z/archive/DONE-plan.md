# Architecture Plan — Optional agent permission-block composition (11 optional agents)

- Ticket: none (branch `main`; explicit descriptive folder used per instruction, matching sibling precedent `docs/tickets/arch-todo-complete-permission-composition-migration/`)
- Source: architect design handoff (bounded, Slice A -> B -> C -> D, with Design Fork F1 locked)
- Generated: 2026-07-05T22:14:34Z
- Plan file: docs/tickets/arch-todo-optional-agent-permission-composition-20260705T221434Z/plan.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan.md` and move it into `archive/` under this folder (`docs/tickets/arch-todo-optional-agent-permission-composition-20260705T221434Z/archive/DONE-plan.md`). See "Archive On Completion" in the writer contract for the exact steps.

## Context

The repo just completed migrating ALL 15 core-class agents into a composed
permission-block source-of-truth system (`tools/ai/install/permission-layers/`),
archived at `docs/tickets/arch-todo-complete-permission-composition-migration/archive/DONE-plan.md`.
That plan explicitly excluded the 11 optional agents under
`packages/ai-universal-rules/templates/optional/agents/`:
`agent-creator`, `agent-creator-runtime-guardian`, `agent-creator-semantic-verifier`,
`agent-creator-static-validator`, `agent-creator-supervisor`, `bugfix`, `build-config`,
`docs`, `infra-auditor`, `ui-builder`, `upgrade`. This was a deliberate scope boundary,
not a technical rejection. These 11 remain 100% hand-maintained today with NO drift gate
of any kind — unlike the 15 core agents, which now have
`generate-agent-permissions.php --check` + `tests/php/PermissionComposeTest.php` coverage.

Reuse assessment per agent (evidence gathered during scoping, informs Slice grouping below):

| Agent | Fit |
|---|---|
| `infra-auditor` | Low-effort reuse — readonly/verify profile, edit_surface:none, task:ask; near-identical to composed `release-auditor`. |
| `agent-creator-static-validator` | Low-effort reuse — readonly profile shape. |
| `agent-creator-semantic-verifier` | Low-effort reuse — same shape as static-validator. |
| `agent-creator-runtime-guardian` | Low-effort reuse — edit:deny, task:ask, readonly-profile shape, shares agent-creator-pipeline script block. |
| `agent-creator-supervisor` | Low-effort reuse — same shape; `mode: all` is template-only, irrelevant to permission composition. |
| `agent-creator` | Moderate effort — readonly-profile shape but denies back several ai-read-baseline ids individually (needs `exceptions`/`deny_packs`, same pattern as `architecture-plan-writer`'s prior Slice C); has trailing `agent_assessment:` frontmatter key (already handled by existing splice function). |
| `bugfix` | Low-effort reuse — impl profile + `code` edit surface, near-exact match to composed `refactorer`/`implementer`. |
| `build-config` | Low-effort reuse — same as bugfix; edit surface matches `code` verbatim. |
| `upgrade` | Moderate effort — impl profile + `code` edit surface base, but flagged "strongest gates"/critical risk in AGENTS-MANIFEST.md; likely needs agent-unique `exceptions` — must verify against FULL shipped block before implementing, not just an excerpt. |
| `docs` | Low-effort reuse — impl profile + `docs` edit surface, exact match. |
| `ui-builder` | Needs an explicit yes/no decision first — `hidden: true` (renderer-suppressed everywhere, so composing it only touches the template source, never a shipped artifact); uses a DIFFERENT ask-default `"*": ask` baseline (every other composed agent is deny-default) plus double-quote YAML style vs. single-quote elsewhere. Do not silently normalize either quirk. |

No optional agent has a missing/structurally broken permission block. The one real
inconsistency is `ui-builder`'s ask-default baseline + double-quote style, which must be
flagged, not silently changed.

## Problem

The 11 optional agents' `permission:` blocks are hand-maintained with zero drift
protection, unlike the 15 now-composed core agents. There is no mechanism today that
proves these blocks stay in sync with the shared permission-composition primitives
(profiles, packs, edit-surfaces, language overlays), and no test asserts they even have
a composition entry.

## Target Outcome

All 11 optional agents composed into `tools/ai/install/permission-layers/compositions.php`
using EXISTING profile builders (`aiPermissionAgentSpecReadonly`/`Verify`/`Impl`), packs,
edit-surfaces, and language-overlay primitives, with only small, evidence-justified
additions. `generate-agent-permissions.php --write`/`--check` becomes source-of-truth for
these agents' `permission:` blocks in `packages/ai-universal-rules/templates/optional/agents/*.md`.
Copilot/Claude projections activate automatically via the existing
`aiPermissionResolveAllowedBash()` compositions-or-legacy-fallback contract — zero
renderer changes needed for that part.

## Design Fork — LOCKED Decision (F1 chosen by user; F2 rejected)

- **Option F1 (LOCKED/CHOSEN):** Permission-block composition only. Add the 11 agents to
  `compositions.php` for `permission:` rendering; do NOT touch
  `aiInstallerAgentProfiles()`/`aiInstallerScriptProfiles()` (the tool-gateway/script-visibility
  profile map, currently exactly the 15 core-agent keys). Extend
  `tests/php/PermissionComposeTest.php` with a NEW, separate invariant test asserting
  "every one of the 11 optional-agent keys has a composition entry" — WITHOUT merging this
  into the existing `testCompositionsKeySetMatchesAgentProfilesExceptDocumentedExclusions`
  1:1 equality test (that test must stay scoped to the 15-key `aiInstallerAgentProfiles()`
  set, unchanged).
- Option F2 (rejected): widening tool-gateway profile visibility to these 11 agents —
  would widen tool-gateway script visibility beyond rendering parity; not requested.
  See Out Of Scope.

## In Scope

- **CORRECTION (implementer pre-execution check, evidence: `git ls-files
  .opencode/agents-optional/`):** the claim below this note originally read "no second
  `.opencode/agents-optional/` committed copy exists in this repo" — this was FALSE.
  `git ls-files .opencode/agents-optional/` proves all 11 files ARE tracked and
  byte-identical to `packages/ai-universal-rules/templates/optional/agents/*.md` (same
  dual-source-of-truth shape as the 15 core agents' `packages/.../core/agents/*.md` +
  `.opencode/agents/*.md` pair). The `$dirs` extension below now correctly adds BOTH
  optional-agent directories, mirroring the existing 2-dir pattern for core agents —
  omitting `.opencode/agents-optional/` would have reproduced the exact
  "dual-ownership drift" bug the core migration's Slice A already hit once
  (`generate-agent-snippets.php`'s stale `$kind` map) via a different mechanism.
- Generator directory extension: add BOTH `packages/ai-universal-rules/templates/optional/agents`
  AND `.opencode/agents-optional` to `$dirs` in `tools/ai/generate-agent-permissions.php`
  (currently hardcoded to 2 core paths only). Each optional agent has TWO files to splice,
  same shape as the 15 core agents.
- Composing all 11 optional agents' `permission:` blocks into `compositions.php` using
  existing profile builders, packs, edit-surfaces, and language-overlay primitives.
- A NEW, separate `PermissionComposeTest.php` invariant test asserting all 11 optional-agent
  keys have a composition entry (Design Fork F1, kept apart from the existing 15-key
  equality test).
- A possible new shared pack-set for the agent-creator-family's identical custom
  script-access block — ONLY if verification confirms byte-identity across the relevant
  agent-creator-family members first (mirrors `aiPermissionPackSetFullProof()`'s "2+ agents
  share an exact combination" precedent).
- A possible `deny_packs` addition for `agent-creator`'s own narrower ai-read subset
  (extends existing atomic pack machinery, no new semantics).
- An explicit yes/no decision on composing `ui-builder` before implementing it (Slice D,
  gated); if yes, using its existing `$starBaseline` parameter on
  `aiPermissionAgentSpecReadonly` rather than forcing deny-default normalization.
- Verifying, before Slice C, that all 15 core agents remain drift-clean after the shared
  `$dirs` change (regression check, since `$dirs` is shared across core and optional paths).

## Out Of Scope (Things To Avoid)

- Do NOT widen `aiInstallerAgentProfiles()`/`aiInstallerScriptProfiles()` tool-gateway
  visibility to these 11 agents (Option F2) — would widen tool-gateway script visibility
  beyond rendering parity; not requested. Explicitly rejected per the locked F1 decision.
- Do NOT add Claude support for optional agents (`.claude/agents-optional/` does not exist
  today — separate, pre-existing feature gap).
- Do NOT resolve the `AGENTS-MANIFEST.md` "GitHub-only" vs. actual shipped-surface
  staleness for `bugfix`/`build-config`/`upgrade`/`infra-auditor`/`docs` beyond flagging it
  (a separate docs-sync-scoped fix).
- Do NOT change `ui-builder`'s `hidden: true` suppression or ship it anywhere.
- Do NOT touch `generate-agent-snippets.php`'s (now-empty) `$kind` map.
- Do NOT re-open Slice D's language-overlay/pack-set reconciliation for the 15 CORE agents
  (already done, separate ticket: `arch-todo-complete-permission-composition-migration`).
- Do NOT silently normalize `ui-builder`'s ask-default baseline or double-quote YAML style
  — flag only, or explicitly decide via `$starBaseline`, never force-change without
  recording the decision.
- Do NOT hand-edit any generated `permission:` block — always use `--write`.
- Do NOT introduce an `exceptions` pattern duplicated across 2+ agents
  (`tests/php/PermissionComposeTest.php` forbids it — `testNoExceptionPatternDuplicatedAcrossTwoOrMoreAgents`).
- Do NOT weaken the immutable hard-deny floor
  (`aiPermissionAssertNoHardDenyWeakening` / `testTrulyUniversalDangerousScriptsRemainOnImmutableFloor`).
- Do NOT create a shared agent-creator-family pack-set without first confirming byte-identity
  across ALL relevant family members (only 2 of 5 were spot-checked during scoping).

## Affected Paths

- `tools/ai/generate-agent-permissions.php` (`$dirs` array extension: BOTH optional dirs)
- `tools/ai/install/permission-layers/compositions.php` (11 new composition entries, possible
  new pack-set / `deny_packs` entry)
- `packages/ai-universal-rules/templates/optional/agents/*.md` AND
  `.opencode/agents-optional/*.md` (both copies of each of the 11 agents; corrected from the
  original single-path listing — see "CORRECTION" note in In Scope)
- `tests/php/PermissionComposeTest.php` (new invariant test, additive; existing
  `testCompositionsKeySetMatchesAgentProfilesExceptDocumentedExclusions` needs a minimal,
  documented adjustment — see Contracts And Boundaries — to exclude the 11 optional-agent
  keys from its 15-key equality check, since Option F1 deliberately does NOT add them to
  `aiInstallerAgentProfiles()`.

## Contracts And Boundaries

- Generator composes statically (`aiPermissionComposeFromSpec`), never calls
  `aiStackDetect` — same contract as the core migration.
- `aiPermissionSpliceBlock()` already handles a trailing `agent_assessment:` key (needed
  for `agent-creator.md`) — reuse as-is, no change needed.
- `aiPermissionResolveAllowedBash()`'s compositions-or-legacy-fallback contract means
  Copilot/Claude rendering is automatically correct the moment a composition entry
  exists — no renderer change in scope.
- `optional-agents-opencode-pack`'s OpenCode renderer already honors `hidden: true`
  (skips `ui-builder`) — composing its template does not change that suppression.
- New pack-set(s), if confirmed, must be effect-homogeneous per
  `testPacksAreEachEffectHomogeneous` and must not duplicate an `exceptions` pattern
  across 2+ agents per `testNoExceptionPatternDuplicatedAcrossTwoOrMoreAgents`.
- The existing `is_file()` guard in `generate-agent-permissions.php` makes the `$dirs`
  extension a no-op for the 15 core keys — must verify all 15 stay drift-clean after this
  change (regression risk since `$dirs` is shared).
- `docs/ai/AGENTS-MANIFEST.md` vs `docs/ai/shipped-surface-inventory.md` conflict on
  GitHub-only claims: code/templates (shipped via `optional-agents-opencode-pack`)
  outrank the manifest per `docs/ai/source-of-truth.md` — treat manifest as stale for
  those 5 agents, flag only, do not fix here.
- Mechanism for keeping the 15-key equality test intact (Design Fork F1): once
  `compositions.php` gains the 11 optional-agent keys, its key set is 26, not 15, so
  `testCompositionsKeySetMatchesAgentProfilesExceptDocumentedExclusions`'s direct
  `assertSame` would break unless the 11 optional-agent keys are explicitly excluded
  from that comparison first. Implementation: add a single named list (e.g. a small
  `aiPermissionOptionalAgentKeys(): array` helper or an equivalent local test constant)
  listing exactly the 11 optional-agent stems, subtract it from `$compositionKeys` before
  the existing equality assertion, and reuse the same list in the new Slice B invariant
  test (single source, no duplication of the 11-name list across two tests).

## Todo Plan

- [x] P0: Slice A — extend `$dirs` in `tools/ai/generate-agent-permissions.php` to include
      BOTH `packages/ai-universal-rules/templates/optional/agents` AND
      `.opencode/agents-optional` (corrected from the plan's original single-dir claim —
      see "CORRECTION" note above).
- [x] P0: Slice A — ran `--write` then `--check` after the `$dirs` change alone (before
      adding any composition entries): confirmed no-op, all 15 core agents stayed
      drift-clean (`OK: managed agent permission blocks in sync`).
- [x] P0: Slice A — composed `infra-auditor` (readonly profile, edit:none, task:ask,
      `cli_tools: none`; new shared packs `core.safe_read.deny_extended_probe_tools` +
      `git.grep_allow` + reused `git.branch_wildcard_deny`; `php-composer-validate` overlay
      alone).
- [x] P0: Slice A — composed `bugfix` (impl profile, `code` edit surface, `cli_tools: none`;
      new shared packs `git.mutating_add_commit_only_deny` +
      `package_manager.narrow_no_add_or_bun_deny`; `php-lint`+`php-phpunit`+
      `php-composer-validate` overlays; required raw-read-tool ask-gate tightening — see
      Handoff Notes).
- [x] P0: Slice A — composed `build-config` (identical shape to `bugfix` plus
      `verify.install_coverage_allow`).
- [x] P0: Slice A — composed `docs` (impl profile, `docs` edit surface, `cli_tools: none`;
      new shared pack `package_manager.deny_all_mutations`, ALSO retrofitted onto
      already-composed `refactorer` — extracted from refactorer's 11 inline exceptions,
      verified byte-identical rendered output — to avoid a cross-agent duplicate-exception
      violation once `docs` needed the same 11-pattern set).
- [x] P0: Slice A — compose `agent-creator-static-validator` (readonly profile shape).
      Confirmed NOT byte-identical to the other 2 landed family members (missing
      `git grep *`/`git status*`/`yq *`; uniquely adds `cat *`: ask, denies
      `ai-diff-context.sh` back) — composed individually with its own `exceptions`, per-agent
      denyPacks (`core.safe_read.deny_git_grep`, `git.deny_show`, `git.deny_ls_files`,
      `core.safe_read.deny_yq`) rather than folded into a shared family bundle.
- [x] P0: Slice A — compose `agent-creator-semantic-verifier` (readonly profile shape).
      Shares an identical CLI/git/context-script surface with `agent-creator-runtime-guardian`
      (new shared packs `ai_scripts.deny_context_and_doc_scripts`, `git.deny_show`,
      `git.deny_ls_files`, `agent_creator.validate_spec_allow`); needs `verify.manual_ask`
      (readonly profile has no `ai-verify` tier by default) for `ai-verify.sh`: ask.
- [x] P0: Slice A — compose `agent-creator-runtime-guardian` (edit:deny, task:ask,
      readonly-profile shape). Same surface as semantic-verifier plus its own
      `ai-rollback.sh`/`session-checkpoint.sh`: ask exceptions; its shipped
      `pre-tool-use.sh`/`post-tool-use.sh`: ask grants are intentionally narrowed to `deny`
      (immutable hard-deny floor — `aiPermissionAssertNoHardDenyWeakening` forbids looser
      than deny for these two scripts on ANY composed agent; same precedent as
      `architecture-plan-writer`'s prior `post-tool-use.sh` narrowing).
- [x] P0: Slice A — for each landed Slice A agent (all 7: infra-auditor, bugfix,
      build-config, docs, agent-creator-static-validator, agent-creator-semantic-verifier,
      agent-creator-runtime-guardian), diffed the rendered `permission:` block and confirmed
      either a documented no-op (pattern matches the `'*'` floor, or a redundant literal
      already covered by a glob) or an explicitly enumerated, intentional, safety-increasing
      deviation (see Handoff Notes for the full enumerated list).
- [x] P0: Slice B — added a NEW, separate invariant test
      (`testEveryComposedOptionalAgentKeyIsAKnownOptionalAgent`) to
      `tests/php/PermissionComposeTest.php`, backed by a single canonical
      `aiPermissionOptionalAgentKeys(): array` helper (11 names) in `compositions.php`; the
      existing `testCompositionsKeySetMatchesAgentProfilesExceptDocumentedExclusions` was
      adjusted (documented in its own docblock) to exclude that same list before its 15-key
      equality assertion, per the "Mechanism for keeping the 15-key equality test intact"
      note above.
- [x] P1: Slice C — full-file (not excerpt) read and diff of `agent-creator.md`'s FULL
      shipped `permission:` block before composing it. Confirmed: no `agent_assessment:`
      splice issue in practice (the generator's existing "next top-level key" logic
      handles it, same mechanism as `architect.md`); `agent-creator` uniquely grants NO
      `validate-agent-spec.php` at all and keeps the context-packaging family at its
      default `ask` (unlike static-validator/semantic-verifier/runtime-guardian).
- [x] P1: Slice C — full-file (not excerpt) read and diff of `upgrade.md`'s FULL shipped
      `permission:` block before composing it. Confirmed via `diff` against
      `build-config.md`'s ORIGINAL ground truth (`git show HEAD`) that the two were
      byte-identical BEFORE either was composed — `upgrade` reuses `build-config`'s exact
      composition.
- [x] P1: Slice C — verified byte-identity (or precisely enumerated non-identity) of the
      agent-creator-family's shared custom script-access block across all 5 members via a
      full pairwise diff (creator vs runtime-guardian, creator vs semantic-verifier,
      creator vs supervisor, semantic-verifier vs runtime-guardian). Result: NOT one
      shared bundle — 4 small, effect-homogeneous, precisely-scoped shared packs instead
      (see Handoff Notes for the full decomposition).
- [x] P1: Slice C — added 4 new agent-creator-family packs
      (`agent_creator.deny_freshness_and_doc_check`, `agent_creator.deny_ai_diff_context`,
      `agent_creator.ask_session_checkpoint`, reusing existing
      `ai_scripts.deny_context_and_doc_scripts`/`agent_creator.validate_spec_allow`/
      `git.deny_show`/`git.deny_ls_files` from the 3 already-landed members) instead of one
      coarse bundle — ground truth did not support a single shared pack-set.
- [x] P1: Slice C — composed `agent-creator` (readonly profile, `cli_tools: none`; new
      pack `agent_creator.deny_freshness_and_doc_check`; reused
      `agent_creator.deny_ai_diff_context`, refactored out of static-validator's prior
      inline exception since `agent-creator` needed the identical pattern too;
      `ai-task.sh` forced from ground-truth `ask` to `deny` via the immutable hard-deny
      floor, no exception added — matches the architecture-plan-writer/script-runner
      precedent).
- [x] P1: Slice C — composed `agent-creator-supervisor` (readonly profile, `cli_tools:
      none`; `mode: all` left untouched, template-only; reuses
      `agent_creator.deny_freshness_and_doc_check` + `agent_creator.validate_spec_allow` +
      `agent_creator.ask_session_checkpoint`, the last refactored out of runtime-guardian's
      prior inline exception since `agent-creator-supervisor` needed the identical pattern
      too; `ai-task.sh`/`pre-tool-use.sh`/`post-tool-use.sh` all forced from ground-truth
      `ask` to `deny` via the immutable floor — 3 forced tightenings, documented, no
      exceptions added for any of the three).
- [x] P1: Slice C — composed `upgrade` (byte-identical composition to `build-config`, see
      above).
- [x] P1: Slice C — for each Slice C agent, diffed the rendered `permission:` block via a
      sorted `{pattern: effect}` set comparison against the pre-my-changes `git show HEAD`
      ground truth: every remaining line was either a no-op floor-omission (renderer skips
      any bash entry whose effect matches the agent's own `'*'` floor), a redundant
      compound-command literal already covered by a glob, or one of the enumerated,
      intentional, immutable-floor-forced tightenings above — no unexplained widening.
- [x] P2: Slice D (GATED) — explicit "yes" decision recorded (user instruction: "complete
      ui builder then and commit changes") — composing `ui-builder` before any further
      Slice D work.
- [x] P2: Slice D — composed `ui-builder` using `aiPermissionAgentSpecImpl`'s
      `starBaseline: 'ask'` parameter (preserved, not deny-normalized) and a new one-off
      `ui` edit surface (no `scripts/**`/`tools/**` grant, unlike `code`). The mixed
      single/double-quote YAML style was NOT preserved as mixed (the render system
      supports only one quote style per agent) — `quote: 'single'` chosen to match the
      majority of the file and every other composed agent; flagged, not silently done
      (see `aiPermissionRenderUiBuilder()`, render-spec.php).
- [x] P2: Slice D — diffed the rendered `permission:` block via the same sorted
      `{pattern: effect}` method as every other slice; found and fixed 2 real gaps
      during this diff (not silently accepted): (1) `core:safe-read`'s unconditional
      `'git grep *': allow` (already existed in `core.php` before this session — an
      analysis error during Slice A missed it) needed an explicit ask-gate-back exception
      for `ui-builder`, since ground truth never grants it; (2) a genuine pre-existing bug
      in `core.php`'s hard-deny floor — `'bash scripts/ai/common.sh*'` (no space) was the
      only dangerous-script entry not using the space-separated convention every sibling
      entry and the `script-tiers:ai-deny-dangerous` layer's own pattern generator use —
      invisible for every prior agent (all `starBaseline: 'deny'`, so both the glued and
      space forms matched the floor and were omitted from rendering);
      `ui-builder`'s first-ever non-`deny` baseline (`'ask'`) surfaced both as redundant
      literal lines. Fixed in `core.php`; verified zero behavior/rendering change for
      `repository-researcher`/`repository-reviewer` (the only 2 other non-`deny`-baseline
      agents, `'ask'`) via `git diff` (both merely deduplicated an already-'deny' pair
      into one line) and `super-implementer` (`'allow'` baseline — zero diff at all).

## Acceptance Criteria

- [x] AC-01: `tools/ai/generate-agent-permissions.php`'s `$dirs` array includes
      `packages/ai-universal-rules/templates/optional/agents` AND
      `.opencode/agents-optional`.
- [x] AC-02: `php tools/ai/generate-agent-permissions.php --write` followed by
      `php tools/ai/generate-agent-permissions.php --check` exits 0, covering all 15 core
      agents AND all 11 optional agents (`ui-builder` composed last, Slice D).
- [x] AC-03: `tools/ai/install/permission-layers/compositions.php` contains composition
      entries for all 11 optional agents.
- [x] AC-04: A new, separate test in `tests/php/PermissionComposeTest.php` asserts every
      composed optional-agent key is a recognized optional-agent name
      (`testEveryComposedOptionalAgentKeyIsAKnownOptionalAgent`), and the existing 15-key
      `testCompositionsKeySetMatchesAgentProfilesExceptDocumentedExclusions` equality test
      (adjusted to exclude the optional-agent key list first) still passes.
- [x] AC-05: `git diff` of each landed Slice A agent's rendered `permission:` block shows
      either no change or a documented, intentional change only — no unexplained widening
      (full enumerated diff review in Handoff Notes).
- [x] AC-06: `aiInstallerAgentProfiles()`/`aiInstallerScriptProfiles()` are unmodified by
      this plan (Option F2 confirmed not implemented; tool-gateway visibility for these 11
      agents unchanged from before this plan — verified via `git diff
      tools/ai/install/script-registry.php` showing no hunks).
- [x] AC-07: Full-file diffs of `agent-creator.md` and `upgrade.md` are performed and
      recorded (not excerpts) before either is composed in Slice C.
- [x] AC-08: Byte-identity (or precise non-identity) of the agent-creator-family
      shared script-access block is verified across all 5 relevant members — result: NOT
      one shared bundle (see Handoff Notes) — 4 new packs added, all effect-homogeneous
      (`testPacksAreEachEffectHomogeneous` green).
- [x] AC-09: `tools/ai/install/permission-layers/compositions.php` contains composition
      entries for `agent-creator`, `agent-creator-supervisor`, and `upgrade` after Slice C
      completes, with no `exceptions` pattern duplicated across 2+ agents
      (`testNoExceptionPatternDuplicatedAcrossTwoOrMoreAgents` green — 2 prior inline
      exceptions, static-validator's `ai-diff-context.sh` deny and runtime-guardian's
      `session-checkpoint.sh` ask, were refactored into shared packs once `agent-creator`/
      `agent-creator-supervisor` needed the identical patterns).
- [x] AC-10: An explicit yes/no decision on composing `ui-builder` is recorded in this
      plan's Handoff Notes before any Slice D implementation work begins. **DECIDED: yes
      (explicit user instruction), composed and verified.**
- [x] AC-11: `composer test:fast` passes with no new regressions after each slice (verified:
      identical 12-failure baseline before and after every slice this session, confirmed via
      `git stash` isolation).
- [x] AC-12 (AMENDED): No change in this plan touches `.claude/agents-optional/`,
      `generate-agent-snippets.php`'s `$kind` map, or `ui-builder`'s `hidden: true`
      suppression (all confirmed true). **One exception beyond the original wording,
      disclosed rather than silently checked off:** the Slice D `common.sh*` hard-deny-floor
      bug fix in `core.php` (see Slice D notes above) DID touch 2 of the 15 core agents'
      RENDERED output (`repository-researcher`, `repository-reviewer`) — not their
      composition entries, and zero effect/behavior change (verified via `git diff`: both
      merely deduplicated an already-`deny` pair into one line). `super-implementer` was
      also checked and showed zero diff at all. This was a necessary, safety-neutral bug
      fix discovered by `ui-builder`'s first-ever non-`deny` baseline, not an unrelated
      widening of scope. The 15 core agents' composition entries (beyond the shared `$dirs`
      regression check).

## Verification Plan

- `php tools/ai/generate-agent-permissions.php --write` then `--check` (exit 0) — proves
  AC-02: newly composed optional agents are drift-clean AND all 15 core agents remain
  drift-clean (regression check, `$dirs` is shared).
- Before/after diff of each optional agent's rendered `permission:` block
  (`git diff packages/ai-universal-rules/templates/optional/agents/<stem>.md`) per slice —
  proves AC-05/AC-09: byte-stability-in-effect (sorted-set comparison) for every agent not
  explicitly flagged as an intentional widening.
- `composer test:fast` — proves AC-04/AC-11: no new regressions vs. pre-slice baseline;
  new/updated `tests/php/PermissionComposeTest.php` assertions must pass.
- Manual FULL-FILE diff spot check for `agent-creator` and `upgrade` before Slice C —
  proves AC-07 (only excerpts were read during scoping — do not implement from partial
  evidence).
- Full byte-identity verification across all 5 agent-creator-family members' script-access
  block BEFORE creating the shared pack-set in Slice C — proves AC-08 (only 2 of 5 were
  spot-checked during scoping).
- Manual code review confirming `aiInstallerAgentProfiles()`/`aiInstallerScriptProfiles()`
  diffs are empty at the end of this plan — proves AC-06.

## Risks And Rollback

- No drift gate exists today for any of the 11 agents — net safety improvement, but also
  means no existing regression test to lean on; must hand-verify each agent's FULL shipped
  block, not excerpts.
- `AGENTS-MANIFEST.md` vs `shipped-surface-inventory.md` conflict (medium) — flagged, not
  fixed, in this ticket.
- `ui-builder`'s ask-default baseline is architecturally different from every other
  composed agent — must use the `$starBaseline` param, not force-normalize; low risk since
  it's suppressed either way (`hidden: true`).
- Agent-creator-family pack-set assumption is UNVERIFIED end-to-end (only 2/5 files fully
  read during scoping) — verify before Slice C; if wrong, treat as a small correction
  slice (same precedent as the core migration's own "Slice D Correction").
- Rollback per slice: revert the composition entries + generator `$dirs` line + any new
  pack-set; additive-only, no data migration, `--write` before/after diff proves
  byte-stability so rollback is low-risk.

## Handoff Notes

- **Slice A status: 7 of 7 agents landed and verified** —
  `infra-auditor`, `bugfix`, `build-config`, `docs`, `agent-creator-static-validator`,
  `agent-creator-semantic-verifier`, `agent-creator-runtime-guardian`. Slice A is complete;
  Slice B is also complete (invariant test already added, see below). Only Slice C
  (`agent-creator`, `agent-creator-supervisor`, `upgrade`) and gated Slice D (`ui-builder`)
  remain, per the plan's own slice boundaries — not attempted, out of this bounded pass's
  scope. All 7 confirmed byte-stable-in-effect
  via a sorted `'pattern': effect` set diff (not literal `git diff`, which is noisy due to
  YAML key reordering across layer application points — same caveat as the original core
  migration's Slice D). Every remaining diff line was one of:
  1. a removed no-op restatement (pattern already matches the agent's own `'*': deny`
     floor, or a redundant literal compound command already covered by an existing glob —
     e.g. the two `git status --short...` compound lines, always redundant with
     `git status*`/`git branch*`);
  2. an added edit-surface denyTail entry from the standard `code`/`docs` surface's fuller
     15–21-entry denyTail vs. the agent's smaller hand-maintained one (deny-only widening,
     safety-increasing, not an allow change);
  3. the 2 `AI_VERIFY_SCOPE=changed...` ai-verify scoped-allow lines and bare `phpunit *`,
     both inherited unconditionally from the standard `impl` profile / `php-phpunit`
     overlay — the SAME grants every other already-composed impl-profile agent
     (implementer/refactorer/bootstrapper) already has, not a new one-off decision;
  4. the required, intentional `rg`/`jq`/`yq`/`head`/`tail`/`sed -n`/`bat` `allow`→`ask`
     tightening for all 3 impl-profile agents (bugfix/build-config/docs), because
     `testRawReadToolsAreNeverAllowedInWriteProfileAgents` hard-enforces this for every
     impl-profile agent today (matches implementer/refactorer/post-install/
     config-maintainer's existing posture) — NOT optional, a pre-existing repo-wide
     invariant these 3 agents were simply never subject to before being composed.
- **New shared packs added** (`tools/ai/install/permission-layers/packs.php`):
  `core.safe_read.deny_extended_probe_tools` (21 patterns), `git.grep_allow` (1),
  `git.mutating_add_commit_only_deny` (13), `package_manager.narrow_no_add_or_bun_deny`
  (4), `package_manager.deny_all_mutations` (11). The last one required a companion,
  zero-behavior-change refactor of the ALREADY-COMPOSED `refactorer` agent (extracted its
  11 inline exceptions into this pack) to avoid a cross-agent duplicate-exception
  violation once `docs` needed the identical 11-pattern deny set — verified byte-identical
  via `--check` before/after.
- **Agent-creator-family: 3 of 5 landed first** (`agent-creator-static-validator`,
  `agent-creator-semantic-verifier`, `agent-creator-runtime-guardian`); `agent-creator` and
  `agent-creator-supervisor` were completed next in Slice C (see "Slice C status: COMPLETE"
  below — all 5 are now composed). Additional packs added while composing the first 3
  landed members:
  `ai_scripts.deny_context_and_doc_scripts` (6: pack-context/run-repomix-context/
  repomix-context-tree/repomix-scc-router/ai-file-freshness/ai-doc-check, all deny — the
  same 6-pattern subset `architecture-plan-writer` already denies via its own larger,
  agent-unique exceptions list; NOT retrofitted onto architecture-plan-writer since that
  would touch an already-verified core-agent composition for only a partial overlap, and
  packs-vs-exceptions across agents are not flagged by the duplicate-exception test),
  `agent_creator.validate_spec_allow` (1: the shared `validate-agent-spec.php` grant),
  `git.deny_show` (1), `git.deny_ls_files` (1, both new — `core:git-read` grants these by
  default and none of the 3 landed family members keep them), `core.safe_read.deny_yq` (1,
  shared with `docs` — `docs`'s existing yq-deny stayed an `exceptions` entry, NOT this
  pack, because `docs` also carries `askPacks: ['core.safe_read.raw_read_ask_gate']` which
  applies AFTER `deny_packs` in compose order and would silently re-loosen a pack-based
  deny back to `ask`; verified this the hard way — an earlier attempt to move `docs`'s yq
  deny into a pack broke it, caught by a `--write`/diff round-trip before landing).
  **CORRECTION to this note's own prior claim:** the "13-pattern CLI preamble IS
  byte-identical across all 4 non-static-validator members, just `git grep *`: allow
  net-new" claim below was INCOMPLETE once verified against the full ground truth for
  `agent-creator-semantic-verifier`/`agent-creator-runtime-guardian`: both ALSO deny back
  `git show*`/`git ls-files*`/`git blame*`/`git branch*`/`git rev-parse*` (5 more patterns
  core:git-read grants by default) — reused `git.deny_blame`/`git.branch_wildcard_deny`/
  `git.deny_rev_parse` (already existed) plus the 2 new atomic packs above. `agent-creator`
  and `agent-creator-supervisor` WERE verified against this corrected, larger deny set when
  composed in Slice C (see "Slice C status: COMPLETE" below) — the original narrower claim
  was not carried forward into their actual compositions.
  - The bespoke "full AI script access (agent-creator pipeline)" block is **explicitly NOT
    identical** across even these 4: `ai-diff-context` (deny vs allow),
    `ai-verify.sh` (deny vs ask), `ai-task.sh` (ask vs deny), `pack-context`/
    `run-repomix-context`/`repomix-context-tree`/`repomix-scc-router` (ask vs deny),
    `ai-rollback`/`session-checkpoint`/`pre-tool-use`/`post-tool-use` (deny vs ask) all
    vary between `agent-creator` and `agent-creator-runtime-guardian` alone (only 2 of 4
    compared in depth originally) — confirmed by actually composing 2 of the 4
    (semantic-verifier, runtime-guardian): each needed its OWN per-agent
    `exceptions`/askPacks for this block, not a shared bundle (`ai_scripts.deny_context_
    and_doc_scripts` covers only the 6 patterns genuinely shared across all 3 landed
    members, not the whole pipeline block). Do NOT assume the plan's original "possible
    shared pack-set... mirrors `aiPermissionPackSetFullProof()`'s precedent" framing for
    the remaining `agent-creator`/`agent-creator-supervisor` without re-verifying full
    ground truth first.
  - `agent-creator.md` also carries a trailing `agent_assessment:` frontmatter key after
    `permission:` — already handled by `aiPermissionSpliceBlock()`'s existing "next
    top-level key" logic (no generator change needed, confirmed by the same mechanism
    already used for `architect.md`).
- **Slice C status: COMPLETE (this pass) — `upgrade`, `agent-creator`,
  `agent-creator-supervisor` all landed and verified.**
  - `upgrade`: verified via `diff` against `build-config.md`'s ORIGINAL `git show HEAD`
    ground truth BEFORE either was composed — byte-identical. Reuses `build-config`'s
    exact composition (same denyPacks/allowPacks/languageOverlays/askPacks).
  - `agent-creator`: full-file read confirmed the corrected, larger git-deny set (not the
    original narrower claim) — needs `git.deny_blame`/`git.branch_wildcard_deny`/
    `git.deny_rev_parse`/`git.deny_show`/`git.deny_ls_files` (all 5 reused, no new git
    packs). New pack `agent_creator.deny_freshness_and_doc_check` (ai-file-freshness +
    ai-doc-check deny, WITHOUT the context-packaging family — unlike the 3 already-landed
    members, `agent-creator` keeps context-packaging at its default `ask`). Reused
    `agent_creator.deny_ai_diff_context` (refactored OUT of static-validator's prior inline
    exception into this pack, since `agent-creator` needed the identical pattern —
    otherwise would have violated `testNoExceptionPatternDuplicatedAcrossTwoOrMoreAgents`).
    Confirmed: `agent-creator` grants NO `validate-agent-spec.php` at all (the one family
    member that doesn't) — `agent_creator.validate_spec_allow` deliberately NOT applied.
    `ai-task.sh` ground truth is `ask`; forced to `deny` via the immutable hard-deny floor
    (no exception added — adding `ask` would throw
    `aiPermissionAssertNoHardDenyWeakening`). The `agent_assessment:` trailing frontmatter
    key confirmed handled by the existing splice logic with zero generator change.
  - `agent-creator-supervisor`: same freshness/doc-check pack as `agent-creator`, plus
    `agent_creator.validate_spec_allow` (ground truth grants both the specific
    `validate-agent-spec.php` pattern AND the broader `validate-*.php` glob — only the
    specific pattern is composed; the broader glob is dropped as a minor, safety-increasing
    narrowing, not a widening). Reused `agent_creator.ask_session_checkpoint` (refactored
    OUT of runtime-guardian's prior inline `session-checkpoint.sh`: ask exception into this
    pack, since `agent-creator-supervisor` needed the identical pattern — same
    duplicate-exception-avoidance reasoning as above). THREE forced immutable-floor
    tightenings for this one agent — `ai-task.sh`, `pre-tool-use.sh`, `post-tool-use.sh` all
    ground-truth `ask`, all forced to `deny`, none added as exceptions (would throw). `mode:
    all` in its frontmatter is untouched (template-only, outside `permission:` scope).
  - All 3 verified via the same sorted `{pattern: effect}` set-diff method as Slice A/the
    3 already-landed family members — see the diffs run this session for the exact,
    fully-enumerated line-by-line accounting (every line explained, no unexplained
    widening).
- **Slice D status: COMPLETE — `ui-builder` composed** (explicit user "yes" decision:
  "complete ui builder then and commit changes"). `hidden: true` suppression, `mode:
  subagent`, and every other template-only frontmatter key are untouched — only the
  `permission:` block is generated. Its `"*": ask` baseline is preserved via
  `starBaseline: 'ask'`, not normalized to the deny-default every other agent uses. New
  one-off `ui` edit surface (`edit-surfaces.php`) and `aiPermissionRenderUiBuilder()`
  (`render-spec.php`, for the `webfetch: deny` scalar + no `task:` key) added — both
  single-consumer, following the same precedent as the `tickets` surface and
  `aiPermissionRenderScriptRunner()`. Two real gaps found and fixed during this slice's
  diff review (not silently accepted): the `core:safe-read` layer's pre-existing
  unconditional `'git grep *': allow` needed an ask-gate-back exception for `ui-builder`
  (missed during Slice A's ground-truth pass for `infra-auditor`/`bugfix`/etc., which
  wanted `git grep` allowed anyway so the gap was invisible there); and a genuine,
  pre-existing `core.php` hard-deny-floor typo (`common.sh*` missing the space-separated
  convention every sibling dangerous-script entry uses) that had been invisible for every
  prior `deny`-baseline agent, fixed with zero behavior change verified for the 2 other
  non-`deny`-baseline agents (`repository-researcher`/`repository-reviewer`, `'ask'`) and
  `super-implementer` (`'allow'`).
- **This plan's implementation work is now fully complete: all 11 optional agents composed,
  drift-clean, and verified with zero new `composer test:fast` regressions** (same 12
  pre-existing, unrelated baseline failures before and after every slice this session).
  Per this plan's own completion instruction, it should now be renamed to
  `DONE-plan.md` and moved into `archive/` under this ticket folder.
- Copy-paste verification commands (one per line):

```text
php tools/ai/generate-agent-permissions.php --write
php tools/ai/generate-agent-permissions.php --check
composer test:fast
vendor/bin/phpunit --filter PermissionComposeTest
```

- Recommended next step: reviewer means reviewer agent handoff using OpenCode command:
  `/review-diff` — this plan's work is done; a fresh-context review of the full diff
  (12 files across `tools/ai/install/permission-layers/`, 22 regenerated agent `.md`
  files, 1 test file, and this plan) is the natural next step before/alongside committing.
