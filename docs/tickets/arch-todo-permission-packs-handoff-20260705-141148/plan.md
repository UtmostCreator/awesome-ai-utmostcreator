# Architecture Plan — Permission Pack Refactor: Continuation Handoff

- Ticket: none (continuation of `docs/tickets/arch-todo-permission-layer-composition-20260705T004618Z/`)
- Source: mid-session handoff (Super-Implementer paused after 10 agents composed + pack refactor landed)
- Generated: 20260705-141148
- Plan folder: docs/tickets/arch-todo-permission-packs-handoff-20260705-141148/
- Risk: **medium-high** (touches shipped agent permission blocks across three runtime harnesses)
- Parent plan (authoritative, do not lose): `docs/tickets/arch-todo-permission-layer-composition-20260705T004618Z/plan.md`
  (Slice 10 + N-8 describe the pack model; this file is the resumable task list on top of it)

## Context

This continues the layered-permission ticket. A prior session landed the composition core (Slice 1),
researcher proof (Slice 2), the profile map for 15 agents (Slice 4 core), the harness adapter seam
(Slice 8), and then — at user direction, to stop `exceptions`-list duplication — the **permission
pack refactor (Slice 10)**. Ten of the fifteen shipped agents are now composed from
`tools/ai/install/permission-layers/` and reuse named packs from `packs.php`. The user's standing
requirement: **permissions must be built from reusable packs, not duplicated `'permission' => 'bash',
'pattern'` literals**, so a policy change is one edit and every harness (OpenCode, Claude, Copilot)
projects from the same pack set.

## Problem

Three shipped agents are not yet composed; several downstream slices (surface regeneration,
validators, dead-code, docs) are not done; and a repository-wide sweep is needed to guarantee no
bash/edit pattern is duplicated across agent compositions outside a pack (the new N-8 rule).

## Target Outcome

All 15 shipped agents composed from the layered pack model (or explicitly excluded with reason);
`.github/agents/**` and Claude surfaces regenerated through the adapters; validators updated
(including an N-8 duplicate-pattern-not-in-pack check); dead code and docs resolved; full
`composer test` green at the documented 10-failure baseline with zero new regressions.

## In Scope

- Compose `bootstrapper`, `script-runner`, `super-implementer` using packs (Slice 4 remainder).
- Regenerate Copilot `.github/agents/**` + Claude surfaces through the Slice-8 adapters (Slice 5).
- Validator/contract/drift updates + registry↔permission drift test P2c (Slice 6).
- Dangerous-gap validator checks + the N-8 duplicate-pattern check (Slice 9).
- Dead-code deletion (approval-gated) + `command-policy.tiers.yaml` investigation + docs (Slice 7).
- Final full verification and marking both parent plan files complete.

## Out Of Scope (Things To Avoid)

- Do NOT recompose or regenerate `release-auditor` or `architecture-plan-writer` — both are dirty
  from the concurrent v0.6 program (coordination gate still open). Exclude them until that lands.
- Do NOT re-introduce cross-agent `'permission' => 'bash'/'edit', 'pattern'` duplication into any
  agent's `exceptions`. Any rule shared by 2+ agents goes in a pack (N-8). `exceptions` is for
  genuinely single-agent one-offs only.
- Do NOT add `allow_bash()`/`deny_bash()`/`ask_bash()` wrapper helpers — packs use the existing
  `aiPermissionEntries()` helper.
- Do NOT rename `exceptions` or its `agent:exceptions` layer name (tests assert against it).
- Do NOT weaken the immutable hard-deny floor, any agent's shipped `'*'` baseline, or any deny.
- Do NOT hand-edit generated permission blocks; always change the source layer/pack/composition and
  regenerate via `php tools/ai/generate-agent-permissions.php --write`.
- Do NOT touch `docs/ai/index.md` or the other concurrently-modified files not owned by this ticket.
- Do NOT absorb the pre-existing 10 baseline test failures into this work (they are unrelated:
  ShipReferenceIntegrityTest, ShIntrospect/ShHelp golden snapshots, AgentsManifestTest missing
  `script-runner`, generate-repo-structure metadata gaps).

## Affected Paths

- `tools/ai/install/permission-layers/packs.php`, `compositions.php` (add 3 agents; extend packs)
- `.opencode/agents/{bootstrapper,script-runner,super-implementer}.md` + their template sources
  where present (bootstrapper has a template; script-runner/super-implementer are `.opencode`-only)
- `tools/ai/generate-agent-snippets.php` (`$kind` map — drop any agent migrated into compositions)
- `tools/ai/install/copilot-agent-renderer.php`, `claude-agent-renderer.php`,
  `claude-settings-merge.php`, `.github/agents/**`, `.claude/agents/**` (Slice 5)
- `tools/ai/validate-script-access.php` or `validate-agent-spec.php` (Slice 6 + 9)
- `tools/ai/render-agent-permissions.php` (Slice 7 deletion, approval-gated)
- `docs/ai/command-policy.tiers.yaml` (Slice 7 investigate-only — LIVE hook source, do not delete)
- Docs: `docs/ai/agent-script-access.md`, `adapter-contract.md`, `generated-artifacts.md`,
  `source-of-truth.md`
- Parent plan file (mark AC/checkboxes complete as slices land)

## Contracts And Boundaries

- Pack model (Slice 10): `role = profile + surfaces + packs + small inline exceptions`. Packs are
  effect-homogeneous, named, resolved via `deny_packs`/`allow_packs`/`ask_packs` in the spec, merged
  in that order after the edit surface and before `exceptions`, before the immutable floor.
- Ground-truth method (mandatory per agent): read the shipped `.opencode/agents/<stem>.md`, build a
  candidate spec, and diff with the throwaway helper `php /tmp/opencode/diff-agent.php <stem>
  <spec.php>` until BASH shows only the known-droppable `git status --short && git branch
  --show-current` literal and any redundant deny-restatements, and EDIT matches (extra deny-only
  additions from shared edit surfaces are protective and allowed). Then port the spec into
  `compositions.php` preferring packs, add the agent, regenerate, and `--check` must be green.
- Keying rule: compositions and generators key on **filename stem**, never frontmatter `id`
  (`super-implementer.md` has `id: implementer`).
- Two generators must never both own one agent: when an agent enters `compositions.php`, remove it
  from `generate-agent-snippets.php`'s `$kind` map in the same change.
- Renderer omits no-op floor-restatement lines (any bash entry whose effect equals the `'*'`
  effect); this is behavior-preserving and keeps files under the line budget.
- Harness reuse (user requirement): Copilot/Claude adapters resolve `allowedBash` via
  `aiPermissionResolveAllowedBash()` — composed-model projection for migrated agents, legacy parse
  for the rest. Slice 5 makes this the shipped path for `.github/agents/**` + `.claude/agents/**`.

## Todo Plan

### P0 — resume composition (packs-first) — DONE (2026-07-05, continuation session)

- [x] Compose `bootstrapper` (profile `impl`, edit surface `code` — ground-truth diff corrected the
  handoff's `install` guess: internal `.opencode/agents/bootstrapper.md` matches the `code` surface's
  allow set exactly; packs used for `git.stash_read`/`proof.*`; exceptions only for unique rules);
  removed from `generate-agent-snippets.php` `$kind`; regenerated (both `.opencode/agents/` and its
  `packages/ai-universal-rules/templates/core/agents/` template); `--check` green;
  `generate-agent-snippets.php --check` green.
- [x] Compose `script-runner` (profile `readonly` — ground-truth diff showed `verify` would wrongly
  widen `run-repo-tests`/`ai-verify`; `edit_surface` `none` — corrected a real shipped bug where
  `edit: "*": allow` contradicted the agent's own documented `edit: deny` policy); ground-truth diff;
  regenerated; `--check` green. Intentional tightening: lost its prior ask-gated `ai-task.sh` access
  (that pattern is on the immutable hard-deny floor, proven by
  `PermissionComposeTest::testTrulyUniversalDangerousScriptsRemainOnImmutableFloor`).
- [x] Compose `super-implementer` (profile `impl`, edit surface new `unrestricted` — added to
  `edit-surfaces.php`, reserved for this one pinned-open power agent; pinned `'*': allow` baseline
  per N-3; `.opencode`-only, untracked file, `id: implementer` — keyed on filename stem); regenerated.
  **Flag for review**: this substantially tightens the previously fully-open (`bash: '*': allow` with
  nothing else) agent — it now gains ~20 explicit ask/deny lines from the immutable floor,
  ai-context-ask, ai-write, git-mutating-ask, and package-manager-ask tiers. This is the intentional,
  already-locked N-3 design ("hard-deny floor immutable... only the `'*'` baseline is agent-tunable"),
  not a new decision — flagged here for visibility before this file is committed.
- [x] After each agent: ran
  `vendor/bin/phpunit --configuration phpunit.xml.dist --filter 'PermissionComposeTest|AgentPermissionDriftTest|PermissionRenderAdaptersTest|AgentPermissionPolicyTest'` — green (157 tests, 0 failures, 5 pre-existing skips).

### P1 — packs sweep, surfaces, drift

- [x] N-8 sweep: DONE. Scanned `compositions.php` for any `['permission'=>'bash'/'edit','pattern'=>...]`
  exception duplicated across 2+ agents. Added 14 new atomic packs to `packs.php`
  (`core.safe_read.deny_file_probe`, `deny_nl`, `deny_sed_n`, `deny_eza`, `deny_rg`, `deny_git_grep`;
  `git.deny_blame`, `deny_rev_parse`; `hard_stop.deny_chown`; `script.ai_write_ask`; `impl.sg_allow`,
  `impl.composer_validate_allow`; `install.docs_allow`) and rewired `architect`, `workflow-auditor`,
  `config-maintainer`, `refactorer`, `implementer`, `post-install`, `script-runner`,
  `super-implementer`, and `researcher` to reference them instead of duplicating exceptions. Verified
  semantically zero-behavior-change for the touched already-shipped agents via a direct
  `aiPermissionComposeFromSpec()` before/after model comparison (not git-diff, which is contaminated
  by this ticket's large pre-existing uncommitted history) — all identical except one harmless,
  fully-subsumed addition for `post-install` (`bash -n scripts/doctor.sh` literal, already matched by
  its existing `bash -n scripts/*.sh` wildcard grant). Final scan: only one 2-agent duplicate remains
  (`git reset*: deny` in `refactorer` and `script-runner`) and it is intentionally NOT packed because
  script-runner's copy is a proven no-op (never renders; its `readonly` profile never grants the ask
  in the first place) — documented inline instead of packing a dead statement.
- [x] Slice 5: DONE (smaller than scoped — see finding). `bootstrapper`, `script-runner`,
  `super-implementer` have NO `.github/agents/**` or `.claude/agents/**` counterparts at all
  (OpenCode-only, confirmed via directory listing) — nothing to regenerate for them. For the
  other 10 already-migrated agents' `.github/agents/*.agent.md` files:
  `php tools/ai/ai.php adapter-plan --target .` (copilot+opencode) reports `"create": []`,
  `"modify": []` — already byte-in-sync with the composed model (Slice 8's adapter wiring from
  the prior session already closed this gap; the N-8 pack refactor's semantic-identity
  verification confirms nothing changed underneath). Separately (pre-existing, NOT part of this
  ticket's scope): `install --dry-run --runtime both` reports `"create": 3` under the
  `adapter-claude` pack — this repo has literally never generated `.claude/agents/*.md` files
  for ANY agent (the directory doesn't exist), unrelated to anything composed here; creating an
  entire new file class is a separate, larger, approval-gated action, not attempted.
  `phpunit --filter 'ClaudeAgentRendererTest|CopilotAgentRendererTest|ClaudeSettingsMergeTest'`
  green (67 tests); graphify hooks in `.claude/settings.json` manually confirmed present and
  untouched.
- [x] Slice 4 profile-map assertion: DONE. Landed
  `PermissionComposeTest::testCompositionsKeySetMatchesAgentProfilesExceptDocumentedExclusions`
  (AC-8) — all 13 non-excluded agents now composed; `release-auditor` + `architecture-plan-writer`
  documented as the two intentional exclusions and asserted not to gain a composition silently.

### Refactor pass (2026-07-05, continuation session #2, user-directed) — DONE

Reviewed `compositions.php` for maintainability (raw permission-array literals mixed with
policy, comments, and agent deltas throughout — estimated 55/100) and refactored it toward a
typed, function-based vocabulary (this repo uses plain functions almost everywhere —
33 `tools/ai/install/*.php` files vs. 1 using a class — so the target design translates the
requested `Rule`/`BashPattern`/`AgentSpec`/`RenderSpec`/`PackSet` OOP shapes into equivalent
`aiPermission*`-prefixed functions instead of introducing the repo's second class):

- `rules.php` (new): `aiPermissionRule()` + `aiPermissionBashAllow/Ask/Deny()` +
  `aiPermissionEditAllow/Deny()` typed rule constructors. Optional `$why` param is carried on
  the rule array for human review but dropped by `aiPermissionApplyLayer()` before it reaches
  the composed model — verified zero rendered-output impact.
- `patterns.php` (new): `aiPatternAiScript()` (space-separated `bash scripts/ai/<name> <args>`
  builder — every wrapper except the pre-existing `run-repo-tests.sh` special case, which is
  kept as a raw literal per script-tiers.php's own established no-space convention),
  `aiPatternAiTool()` and `aiPatternGit()` (single-suffix prefix builders — deliberately NOT a
  two-arg name+args split for these two families, since real `php tools/ai/ai.php <sub>*` and
  `git <sub>*` spacing conventions are genuinely inconsistent across patterns and a naive
  builder risked silently changing matching semantics). Also defines the four shell-composition
  deny patterns as named constants (`AI_BASH_PATTERN_PIPE`/`AND_CHAIN`/`SEMICOLON_CHAIN`/
  `COMMAND_SUBSTITUTION`) so they are reusable by both script-runner's composition and the new
  validator test below.
- `agent-spec.php` (new): `aiPermissionAgentSpecReadonly()` / `aiPermissionAgentSpecVerify()`
  (added after catching a bug — see below) / `aiPermissionAgentSpecImpl()` — PHP 8 named-argument
  builders collapsing the repeated `{compose_spec, render}` shape into one call per agent.
- `render-spec.php` (new): `aiPermissionRenderTaskAsk/Allow()`, `aiPermissionRenderNoTask()`,
  `aiPermissionRenderScriptRunner()` — the repeated `render` metadata shape.
- `pack-sets.php` (new): `aiPermissionPackSetFullProof()` (refactorer + implementer's identical
  7-pack proof bundle) and `aiPermissionPackSetCommonReadDeny()` (architect + refactorer's
  identical 2-pack deny bundle) — only bundled combinations genuinely shared by 2+ agents, per
  the same atomic-pack discipline `packs.php` already documents (no vague/premature bundles).
- `compositions.php` rewritten: all 13 agents now built from the above vocabulary instead of
  raw `['permission' => ..., 'pattern' => ..., 'effect' => ...]` literals. Net effect: 566 →
  485 lines despite more documentation, and zero raw permission arrays remain in the file.
- **Bug caught mid-refactor (not shipped):** `aiPermissionAgentSpecReadonly()` hardcodes
  `profile: 'readonly'`; `config-maintainer` actually needs `profile: 'verify'`. Using the
  readonly builder for it would have silently downgraded its script-tier grants (dropping
  `ai-verify`/`ai-test-select`/`run-repo-tests`/scoped-verify-allow). Added a dedicated
  `aiPermissionAgentSpecVerify()` builder instead of a generic profile-string parameter (so a
  future typo can't silently land an agent on the wrong profile) and fixed the call site before
  verification — never shipped in a rendered file.
- New validator tests added to `PermissionComposeTest.php` (reuses the existing owning test
  file per N-2 — no third validator surface):
  `testNoExceptionPatternDuplicatedAcrossTwoOrMoreAgents()` (formalizes the manual N-8 sweep as
  a permanent failing-fixture test instead of a one-off `rg`/PHP scan) and
  `testShellCompositionOperatorsAreNeverAllowedForAnyComposedAgent()` (pipe/`&&`/`;`/command
  substitution can never resolve to `allow` for any composed agent).
- **Verification:** `generate-agent-permissions.php --check` — zero drift (byte-identical
  rendered output before/after the whole refactor); `generate-agent-snippets.php --check` green;
  full permission/renderer test filter green (225 tests); `composer test:fast` — 850 tests
  (848 + 2 new validator tests), same 10 pre-existing baseline failures, 0 new regressions.
- **Not done (deliberately, to avoid over-engineering per the refactor's own "avoid a complex
  DSL too early" guidance):** mandatory `why`/`owner` on every exception (would require
  rewriting ~150 existing exception entries — the field is supported and optional, not
  enforced); per-agent-per-file split (`agent-overrides/*.php` / `ResearcherComposition::make()`
  style) — the refactor's own preference for "fewer files, function extraction" was followed
  instead; `reviewerProof`/`phpProof` pack-set bundles (each used by only 1 agent today —
  premature per the same 2+-agent-sharing rule already enforced for atomic packs).

### P2 — validators, dead code, docs

- [x] Slice 6: DONE. `validate-script-access.php` header corrected (no longer claims
  "inline .opencode/agents permissions remain canonical" for all agents — now names the
  13 composed agents vs. the 2 still-inline-canonical exclusions); re-ran the validator,
  still green. **Scope correction:** `validate-agent-spec.php` is NOT the right file to
  align — it validates a completely different system (the Agent Creator Edition's JSON
  `AgentSpec` proposal pipeline, an unrelated tool allowlist/forbidden-baseline concept), not
  `.opencode/agents/*.md` permission blocks; the plan's original text appears to have assumed
  overlap that doesn't exist. Landed the registry↔permission drift test (rethink P2c, absorbed
  per the Reconciliation table) as
  `PermissionComposeTest::testEveryComposedScriptReferenceIsRegistered()` — every
  `scripts/ai/*.sh` reference in any composed agent's bash patterns must match a real
  `aiInstallerScriptRegistry()` `installed_path`, catching a typo'd or renamed script path.
  `AgentPermissionPolicyTest` reviewed — green (124 tests), no semantic change (it asserts
  against generated frontmatter files, which are unchanged by this session's refactor).
- [x] Slice 9: DONE (with one deliberate scoping refinement — see below). Added to
  `PermissionComposeTest.php` (composed-model-based, not raw-frontmatter, per this slice's own
  requirement; N-8's duplicate-pattern check already landed as
  `testNoExceptionPatternDuplicatedAcrossTwoOrMoreAgents()` in the refactor pass above):
  - Check 1/2 refined: rather than a literal "missing terminal `*`" scan (which would have
    flagged every write-profile agent's standard denyTail-only edit shape — an
    already-validated, safe, pre-existing pattern per `validate-ai-config.php:820-826` — with
    zero real signal), enforced the more precise and actually-dangerous invariant: neither
    `edit '*'` nor `bash '*'` may resolve to `allow` for any composed agent except the one
    documented, pinned exception (`super-implementer`, N-3).
  - Check 4 (dependency-manager mutations), 5 (mutating VCS), 6 (`ai-edit.sh`/`ai-rollback.sh`),
    7 (repomix/context-packaging) all landed as hard, zero-tolerance tests — verified clean
    against every one of the 13 composed agents before landing (no shipped agent trips them).
  - Check 3 (raw read tools — `rg`/`bat`/`jq`/`yq`/`head`/`tail`/`sed -n` — allow in a
    write-profile agent): found a real, pre-existing, cross-cutting gap trips on **every**
    impl-profile agent (refactorer, implementer, bootstrapper, super-implementer, post-install
    all grant these via `core:safe-read`'s default). Per this slice's own "advisory-first,
    never silently flip green to red" instruction, landed as a **ratchet** (does-not-worsen)
    test pinned at today's baseline count (35) instead of a hard zero-tolerance check — fixing
    the underlying exposure is a broad, 5-agent policy change out of scope for a
    validator-landing slice; flagged here for a future, explicitly-scoped decision rather than
    silently tightened or silently ignored.
  - Failing-fixture proofs added for checks 1 and 4 (representative sample — every check's
    detection logic is a straightforward composed-model pattern lookup, same shape, so one
    demonstrated proof per shape class was judged sufficient rather than mechanically
    duplicating one fixture per check).
  - Verification: `phpunit --filter PermissionComposeTest` — 35 tests green;
    `composer test:fast` — 860 tests, same 10 pre-existing baseline failures, 0 new.
- [x] Slice 7: PARTIALLY DONE (deletion deliberately withheld — no approval given this
  session). Re-confirmed `docs/ai/command-policy.tiers.yaml` is still LIVE: compiled by
  `tools/ai/compile-command-policy.php` into the real, present, executable
  `.github/hooks/scripts/command-policy.compiled.sh`; validated by
  `tools/ai/validate-command-policy.php` + `tools/ai/validate-ai-config.php`; documented in
  `docs/ai/security.md:20,60`. Untouched, as required. Investigated
  `tools/ai/render-agent-permissions.php`: reads the OLD pre-permission-layers
  `command-policy.tiers.yaml` tier map (`base-readonly`/`base-verify`/`base-impl`) directly —
  no caller invokes it anywhere in the current system (only referenced as a shipped file in
  `packs.php`'s manifest and an existence check in `validate-ai-config.php:100`); this matches
  the parent plan's prior "proven dead" finding. **Not deleted — this session has no explicit
  user deletion approval**; flagging it again here for a future approval-gated pass. Updated 3
  of the 4 named docs with a pointer to the new permission-layers generation source
  (`source-of-truth.md`'s "Editable vs Generated Files", `adapter-contract.md`'s new
  "Permission Projection Seam" section, `agent-script-access.md`'s intro). **Scope correction:**
  `docs/ai/generated-artifacts.md` is about a different category of "generated" (ephemeral
  `docs/ai/generated/*.json`/`*.md` pipeline outputs) — the `.opencode/agents/*.md` permission
  block is a different kind of generated content (generated into a tracked/shipped file, not
  into that ephemeral directory), so no edit was needed there; left untouched rather than
  force an unrelated addition.

### P3 — close out

- [x] Final `composer test:fast` (parallel) AND `composer test` (full serial): both confirmed
  exactly the same 10 pre-existing baseline failures, zero new regressions, 860 tests total.
- [x] Marked all satisfied checkboxes/ACs complete in the parent plan
  (`arch-todo-permission-layer-composition-20260705T004618Z/plan.md`). The dynamic-stack plan
  (`arch-todo-dynamic-stack-permission-selection-20260705T011906Z/`) was not touched — no
  changes in this session intersected its scope (stack-overlays.php was read but not modified).

## Acceptance Criteria

- [x] AC-01: DONE — `bootstrapper`, `script-runner`, `super-implementer` composed from packs;
  regenerate byte-identically to pre-migration shipped frontmatter except documented droppable
  lines and two intentional tightenings (script-runner's `ai-task.sh` immutable-floor loss;
  super-implementer's floor-tier ask/deny gates on top of its pinned `'*': allow`);
  `generate-agent-permissions.php --check` is green.
- [x] AC-02: DONE (N-8 sweep) — 14 new atomic packs added; every bash/edit pattern previously
  duplicated across 2+ `compositions.php` entries now lives in a pack. One remaining 2-agent
  duplicate (`git reset*: deny` in refactorer/script-runner) is intentionally NOT packed and
  documented inline: script-runner's copy is a proven no-op (never renders under its `readonly`
  profile). A dedicated validator test now exists too
  (`testNoExceptionPatternDuplicatedAcrossTwoOrMoreAgents()`, refactor pass).
- [x] AC-03: DONE for OpenCode + Copilot (`.github/agents/**` confirmed in sync via
  `adapter-plan`); Claude has no per-agent files in this repo at all (pre-existing, unrelated
  gap — see Slice 5 note); graphify-owned `.claude/settings.json` hooks confirmed preserved.
- [x] AC-04: DONE — `PermissionComposeTest::testEveryComposedScriptReferenceIsRegistered()`
  present and green.
- [x] AC-05: DONE — checks 1/2/4/5/6/7 landed as hard tests (all clean against all 13 agents);
  check 3 landed as a ratchet test (pre-existing, cross-cutting gap — see Slice 9 note); N-8
  duplicate check landed. No shipped agent flipped red.
- [x] AC-06: PARTIAL BY DESIGN — `render-agent-permissions.php` deletion explicitly deferred
  (no approval given this session, re-confirmed dead); `command-policy.tiers.yaml` confirmed
  live and untouched; 3 of 4 named docs updated (`generated-artifacts.md` is a different
  "generated" category — scope correction, see Slice 7 note).
- [x] AC-07: DONE — `composer test:fast` ends at the 10 documented baseline failures, zero new.
- [x] AC-08: DONE — `release-auditor` and `architecture-plan-writer` remain uncomposed;
  `PermissionComposeTest::testCompositionsKeySetMatchesAgentProfilesExceptDocumentedExclusions()`
  asserts they are documented, intentional exclusions (not forgotten).

## Verification Plan

- Per agent: `php -l` changed files; `php tools/ai/generate-agent-permissions.php --check`;
  `php tools/ai/generate-agent-snippets.php --check`;
  `vendor/bin/phpunit --configuration phpunit.xml.dist --filter 'PermissionComposeTest|AgentPermissionDriftTest|PermissionRenderAdaptersTest|AgentPermissionPolicyTest'`.
- Slice 5: `phpunit --filter 'ClaudeAgentRendererTest|CopilotAgentRendererTest|ClaudeSettingsMergeTest'`.
- Slice 6/9: `php tools/ai/validate-script-access.php`; `php tools/ai/validate-agent-spec.php`; new
  fixture tests.
- Final: `composer test` then `composer test:fast` — expect 10 failures (baseline), 0 new.
- Anti-freeze: per `docs/ai/execution-protocol.md` budgets (focused filter 60s, `test:fast` 90s).

## Risks And Rollback

| Risk | Mitigation / rollback |
|---|---|
| Recomposing a dirty v0.6 file clobbers concurrent work | Exclude release-auditor + architecture-plan-writer; never regenerate over uncommitted edits |
| super-implementer `'*': allow` baseline accidentally tightened/loosened | Pin `shipped_star_baseline => 'allow'`; PermissionComposeTest asserts per-agent baseline |
| Pack extraction changes rendered output | `--check` must be byte-identical after every migration; deeper model-equality spot-check available |
| Slice 5 surface regen drops graphify hooks | Merge-only `claude-settings-merge.php` + ClaudeSettingsMergeTest; manual hook check |
| Rollback | Every change git revert-clean; shipped files regenerate from source layers; no data/migration surface |

## Review Pass (2026-07-05, continuation session #3, reviewer-agent posture)

Full review-diff pass against the whole permission-layers system. Verdict: **PASS, 3 real bugs
found and fixed, 0 outstanding defects.**

- **[Fixed, low severity]** `post-install` had a redundant `chown *: deny` exception exactly
  restating the `hard_stop.deny_chown` pack it already uses — dead code from an incomplete N-8
  cleanup. Removed; `--check` confirmed zero rendered-output change.
- **[Fixed, medium severity — weak test proofs]** Two Slice-9 "failing-fixture" tests
  (`testEditStarAllowCheckCatchesAnUndocumentedAgent`,
  `testDependencyManagerCheckCatchesAnAllowedInstall`) made trivial/tautological assertions that
  would pass regardless of whether the real detection logic worked. Rewritten to build the bad
  fixture and run the actual check assertion via `expectException(ExpectationFailedException::class)`,
  genuinely proving detection.
- **[Fixed, medium severity — missing test coverage]** `AgentPermissionPolicyTest::GIT_MUTATING_AGENT_FILES`
  (hand-maintained data-provider list) never covered `post-install` or `super-implementer` —
  both impl-profile agents with correct git-mutation `ask` grants that were simply never added
  to this regression check as agents were migrated across sessions. Added both (verified they
  already pass).
- **[Not changed, low severity, documented tradeoff]** `aiPermissionAgentSpecReadonly/Verify/Impl()`
  are ~90% duplicate code — flagged per the ≥75% overlap screening rule, but this is an
  intentional, already-justified tradeoff (distinct names prevent a future typo'd profile string
  from silently misrouting an agent).
- **[Not changed, observation]** `READ_ONLY_STRICT_DENY_AGENTS` doesn't include `script-runner.md`
  (would pass if added) — smaller, more clearly-scoped fixes were prioritized; flagged as a
  possible future follow-up, not a defect.
- Final verification after all review fixes: `generate-agent-permissions.php --check` +
  `generate-agent-snippets.php --check` + `validate-script-access.php` all green;
  `composer test:fast` — 863 tests (860 + 3 new `GIT_MUTATING_AGENT_FILES` cases), same 10
  pre-existing baseline failures, 0 new regressions.

## Handoff Notes

- **Ticket status: substantially COMPLETE.** All of P0/P1 (composition + N-8 sweep), the
  user-directed refactor pass, and P2 (Slice 5/6/9/7) landed across two continuation sessions on
  2026-07-05. All 13 non-excluded agents composed; `compositions.php` rebuilt on a typed,
  function-based vocabulary; `packs.php` has 34 packs; 5 new supporting files
  (`rules.php`/`patterns.php`/`agent-spec.php`/`render-spec.php`/`pack-sets.php`); 12 new
  validator/policy tests in `PermissionComposeTest.php` (now 35 tests total). Final verification:
  `composer test:fast` AND `composer test` (serial) both — 860 tests, same 10 pre-existing
  baseline failures, 0 new regressions.
- **Bugs found + fixed across this program (do not re-investigate):** core.php literal
  embedded-quote git-status commands; `grep *` wrongly on immutable floor; reviewer-class
  `git branch*` breadth (test-enforced); legacy allowedBash parser empty for double-quoted agents;
  config-maintainer redundant verify exceptions; `generate-agent-snippets.php` dual-ownership;
  renderer no-op floor-restatement bloat (implementer.md hard-max); script-runner's shipped
  `edit: "*": allow` contradicted its own documented `edit: deny` policy (corrected via
  `edit_surface: none`); an earlier handoff guess that bootstrapper's edit surface is `install`
  was wrong — ground-truth diff proved it is `code`; mid-refactor, `aiPermissionAgentSpecReadonly()`
  would have silently downgraded config-maintainer's profile from `verify` to `readonly` — caught
  before it shipped, fixed with a dedicated `aiPermissionAgentSpecVerify()` builder.
- **Flagged for human confirmation before this diff is committed (not blockers, but real
  decisions a human should own):**
  1. **super-implementer's permission block substantially tightens** (two-line fully-open
     `edit/bash: '*': allow` → ~65 lines of floor/tier ask/deny gates on top of its still-pinned
     `'*': allow`). This is the already-locked N-3 design, not a new decision, but it changes the
     actual behavior of an actively-used internal power agent (`ai-edit.sh`/`ai-rollback.sh`, git
     mutations, package-manager installs are now `ask`, not silently allowed).
  2. `packages/ai-universal-rules/templates/core/agents/bootstrapper.md` (installed into *other*
     projects) gained a widened `ai-verify.sh` grant (`ask`→`allow`), matching this repo's
     internal ground truth — confirm that's intended for consumer-project installs.
  3. **Deletion explicitly withheld, no approval given:** `tools/ai/render-agent-permissions.php`
     re-confirmed dead (no live caller) but not deleted. This repo has also never generated any
     `.claude/agents/*.md` file at all (a separate, pre-existing, unrelated gap, not created).
  4. Check 3 of Slice 9 (raw read tools allow in write-profile agents) is a real, pre-existing,
     cross-cutting exposure across 5 shipped agents, landed as a ratchet test rather than fixed —
     a future, explicitly-scoped policy decision is needed if this should be tightened.
- **Deliberately not done (over-engineering guard, not a gap):** mandatory `why`/`owner` on every
  exception (supported, optional, not retrofitted onto ~150 existing entries);
  per-agent-per-file split; `reviewerProof`/`phpProof` pack-set bundles (each used by only 1 agent
  today).
- **Throwaway tool still on disk:** `/tmp/opencode/diff-agent.php` + `/tmp/opencode/spec-*.php` —
  no longer needed; safe to delete (never shipped, not referenced by anything tracked).
- **Excluded (coordination gate):** `release-auditor`, `architecture-plan-writer` — dirty from v0.6.
- Recommended next step: reviewer means reviewer agent handoff using OpenCode command:
  `/review-diff` — all implementation work in this ticket is done and green; a fresh-context
  review pass (especially of the 3 flagged decisions above) is the natural next step before this
  large, multi-session, still-uncommitted diff is committed. Only if the user separately approves
  deletion of `render-agent-permissions.php` or first-time `.claude/agents/**` generation would
  further implementer work be needed.
