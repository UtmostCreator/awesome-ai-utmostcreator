# Architecture Plan — Implementer permission line-reduction pilot (Lever A: collapse JSON-variant search-script rules into one leading-wildcard pattern), equivalence-proof gated

- Ticket: none
- Source: architect handoff (bounded task description)
- Generated: 2026-07-06T23:30:00Z
- Plan file: docs/tickets/arch-todo-implementer-permission-line-reduction-pilot-20260706-233000/plan.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan.md` and move it into `archive/` under this ticket folder (`docs/tickets/arch-todo-implementer-permission-line-reduction-pilot-20260706-233000/archive/DONE-plan.md`). See "Archive On Completion" below for the exact steps.

## Context

- Generated agent `permission:` blocks are long because the renderer emits ONE YAML line per command pattern. The single biggest, most isolated bloat source is `aiPermissionScriptCommandPatterns()` in `tools/ai/install/permission-layers/script-tiers.php` (lines ~110–120): for the three `supports_json` search scripts (`ai-search.sh`, `ai-search-multi.sh`, `preview-file.sh`) it emits 3 lines each — `bash ...`, `AI_OUTPUT=json bash ...`, `env AI_OUTPUT=json bash ...` (9 lines total per agent that has them).
- Claude and Copilot DERIVE their allow-lists from the composed model (`aiPermissionAllowedBashFromModel` in `render-adapters.php:137–151`); OpenCode reads the rendered `permission:` YAML. So collapsing the model shrinks ALL THREE runtimes' outputs at once.
- OpenCode docs confirm: `*` matches zero-or-more chars (leading `*` is valid); bash rules match PARSED commands; last-match-wins with `*` first.
- CORRECTED GROUND-TRUTH CONFLICT TO VERIFY DURING P0: one prior investigation reported `.opencode/agents/*.md` is currently EMPTY in this repo (rendered blocks live only in `packages/ai-universal-rules/templates/core/agents/*.md`), while an existing plan (`arch-todo-opencode-render-permission-from-composition-20260706-030000/plan.md`, line 43) states this repo dogfoods `.opencode/agents/*.md` at runtime. The implementer MUST resolve this by direct `ls`/Glob of `.opencode/agents/` before assuming which files the generator will splice. Record the actual state in the plan's P0 step.

## Problem

Reduce rendered permission line count (starting with `implementer`) with PROVABLE security equivalence, without weakening any deny or the hard-deny floor, and without a false byte-collapse that widens the match surface.

## Chosen Lever

Lever A: replace the 3 JSON-variant patterns per search script with ONE pattern that matches the bare, `AI_OUTPUT=json`-prefixed, and `env AI_OUTPUT=json`-prefixed forms — at the source function `aiPermissionScriptCommandPatterns()`. Classification: behavior-preserving-but-byte-changing, CONDITIONAL on the equivalence proof. The exact collapsed spelling is NOT assumed; it is chosen empirically in the equivalence-proof step. If no single spelling proves exactly equivalent under OpenCode's matcher without widening, FALL BACK to a narrower collapse or HALT Lever A and report — never silently widen.

## GATE OUTCOME (2026-07-06): LEVER A HALTED — proven to widen

The equivalence proof was executed (temporary harness
`tests/php/PermissionJsonVariantCollapseEquivalenceTest.php`, 3 tests / 32 assertions,
green; harness since removed after serving its gating purpose). Result:

- OpenCode globs support only `*` (zero-or-more) and `?` (exactly one) — NO alternation
  or character classes. Therefore no single pattern can match exactly the three allowed
  prefixes `{"", "AI_OUTPUT=json ", "env AI_OUTPUT=json "}` before the script token.
- The naive `* bash scripts/ai/ai-search.sh *` collapse WIDENS: it flips adversarial
  commands such as `EVIL=1 bash scripts/ai/ai-search.sh foo` and
  `rm -rf / ; bash scripts/ai/ai-search.sh foo` from `deny` to `allow` (arbitrary
  env-prefix / command-injection now granted). This fails AC-02 (zero widening).
- Every reasonable single-pattern candidate either widens (leading `*`) or narrows
  (one explicit prefix only, missing the other two legit forms).

Decision per plan Handoff Note + AC-02: HALT Lever A. Do NOT edit
`aiPermissionScriptCommandPatterns()`. No production code changed.

## Superseding direction

The safe, zero-widening line-reduction is already scoped in a sibling plan:
`docs/tickets/arch-todo-opencode-render-permission-from-composition-20260706-030000/plan.md`.
That plan STRIPS the `permission:` block entirely from the template source files
(`packages/ai-universal-rules/templates/core/agents/*.md`), making them pure
"body + non-permission frontmatter" (maximum instruction space), while the generator
keeps the full baked block only in the committed/dogfooded `.opencode/agents/*.md`.
It is gated by a byte-identical fresh-install fixture proof, so it reduces authored
lines with provably zero effect change. Verified precondition: `opencode.jsonc` already
carries the shared search-script allows globally (lines 78–86), and OpenCode merges
agent permissions with the global block. RECOMMENDATION: pursue that plan instead of
this one for template-side line reduction; retain this plan as the durable record of
WHY the wildcard-collapse lever is unsafe.

## Out Of Scope (Things To Avoid)

- Do NOT folder-namespace scripts (Lever B) or edit `opencode.jsonc` global block (Lever C) in this pilot.
- Do NOT weaken or reorder the hard-deny floor; `*` stays first.
- Do NOT move/rename/delete any script file (approval-gated) — if tempted, stop and ask.
- Do NOT hand-edit rendered blocks; only change the source function and regenerate via `--write`.
- Note the byte change is REPO-WIDE (all 15 composed agents regenerate together because the source is shared) — regenerate all, but assert `implementer` semantics explicitly.

## Affected Paths

- `tools/ai/install/permission-layers/script-tiers.php` (the collapse — source of truth)
- `packages/ai-universal-rules/templates/core/agents/*.md` (regenerated via `--write`; all composed agents, pilot asserts implementer)
- `.opencode/agents/*.md` (regenerated IF present — verify in P0)
- `tests/php/PermissionRenderAdaptersTest.php`, `tests/php/PermissionComposeTest.php`, `tests/php/AgentPermissionPolicyTest.php` (expected strings/counts)
- `tools/ai/validate-ai-config.php` (ONLY if `opencode.jsonc` literal-key checks are implicated — flag before editing; ideally NOT touched since this pilot does not edit opencode.jsonc)
- A temporary equivalence-proof test (kept or removed per implementer's call)
- Doc note in `docs/ai/adapter-contract.md` (Permission Projection Seam) recording the collapse convention

## Todo Plan

- [ ] P0: Confirm baseline green: `php tools/ai/generate-agent-permissions.php --check` and `composer test:fast`. Record the ACTUAL state of `.opencode/agents/` (empty vs populated) via Glob/ls and note which dirs the generator will splice.
- [ ] P0 (GATING): Write a temporary equivalence test that enumerates a representative command corpus (bare, `AI_OUTPUT=json bash <script> ...`, `env AI_OUTPUT=json bash <script> ...` for each of the 3 search scripts, PLUS adversarial near-misses that must NOT match) and asserts the collapsed pattern set yields IDENTICAL deny/allow/ask as the current 3-variant set under OpenCode last-match-wins semantics. Adoption of Lever A is gated on this passing with zero widening.
- [ ] P1: If equivalence holds, edit `aiPermissionScriptCommandPatterns()` to emit the collapsed form.
- [ ] P1: Regenerate all composed agents via `php tools/ai/generate-agent-permissions.php --write`.
- [ ] P1: Update exact-string/count assertions in the three named tests to the collapsed form.
- [ ] P1: Verify Claude/Copilot derived allow-lists remain semantically identical (they derive from the model).
- [ ] P2: Document the collapse convention in `docs/ai/adapter-contract.md`.
- [ ] P2: Full verification (see below).

## Acceptance Criteria

Explicit:

- [ ] AC-01: Rendered permission line count for `implementer` is reduced (search-script lines 3->1).
- [ ] AC-02 (linchpin): Equivalence proof passes — collapsed set matches current set (deny/allow/ask) over the corpus with ZERO widening or narrowing.
- [ ] AC-03: `php tools/ai/generate-agent-permissions.php --check` green after `--write`.
- [ ] AC-04: `php tools/ai/validate-adapter-drift.php` green.
- [ ] AC-05: `PermissionRenderAdaptersTest`, `PermissionComposeTest`, `AgentPermissionPolicyTest`, `composer test:fast` green.
- [ ] AC-06: All three runtimes' shipped/derived outputs re-verified enforceable and semantically unchanged.

Negative:

- [ ] NAC-01: No allow/ask/deny effective outcome changes for any command.
- [ ] NAC-02: `*` still renders first; hard-deny floor unchanged.
- [ ] NAC-03: No script file moved/renamed/deleted; `opencode.jsonc` not edited.

## Verification Plan

- Equivalence test (gating) -> proves AC-02, NAC-01.
- `generate-agent-permissions.php --check` -> AC-03, NAC-02.
- `validate-adapter-drift.php` -> AC-04.
- The three named tests + `composer test:fast` -> AC-05.
- Manual diff of `implementer.md` + Claude/Copilot derived lists -> AC-01, AC-06.

## Risks And Rollback

1. OpenCode parsed-command matcher may not treat any leading-wildcard spelling as exactly equivalent -> gated by equivalence proof; fall back or halt.
2. Shared source -> repo-wide byte churn across all 15 agents; partial regen leaves --check red -> regenerate all together.
3. Claude/Copilot derived lists change too and may be assumed OpenCode-only -> explicit AC-06 re-verifies all three.

Rollback: revert the pilot commit; since the change is a pure pattern re-expression proven equivalent, rollback is a clean git revert with `--check` re-green as the success signal.

## Cross-Reference

Complementary to (does NOT supersede) `docs/tickets/arch-todo-implementer-permission-profile-pilot-20260706-215934/plan.md` (authoring/labeling only, keeps bytes identical). Recommended sequence: land the labeling plan first, then this line-reduction plan. Also related: `arch-todo-opencode-render-permission-from-composition-20260706-030000` (global-baseline render lever, deferred here).

## Handoff Notes

- Recommended next step: implementer means implementer agent handoff using OpenCode command: /implement
- The P0 (GATING) equivalence proof is the linchpin (AC-02): adoption of Lever A depends on it passing with ZERO widening or narrowing. If no single collapsed spelling proves exactly equivalent under OpenCode's parsed-command matcher, FALL BACK to a narrower collapse or HALT Lever A and report — never silently widen.
- Resolve the `.opencode/agents/` ground-truth conflict (empty vs populated) in P0 by direct `ls`/Glob BEFORE assuming which files the generator will splice; record the actual state.
- The source function is shared, so a `--write` regenerates all 15 composed agents together — regenerate all in one pass or `--check` stays red; assert `implementer` semantics explicitly.

## Archive On Completion

When every `## Todo Plan` item AND every `## Acceptance Criteria` item above is checked `[x]`, archive this plan so active and finished plans stay separated:

```text
docs/tickets/arch-todo-implementer-permission-line-reduction-pilot-20260706-233000/plan.md
  -> docs/tickets/arch-todo-implementer-permission-line-reduction-pilot-20260706-233000/archive/DONE-plan.md
```

Steps: (1) create `archive/DONE-plan.md` with the full plan contents, (2) replace this `plan.md` with a one-line tombstone pointing to the archived copy. Do not archive while any item is still unchecked.
