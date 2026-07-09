# Architecture Plan — agent-fleet-assessor critic fixes

- Ticket: none
- Source: architect design, agent-critic score 66/blocked on .claude/agents/agent-fleet-assessor.md
- Generated: 2026-07-08T09:32:05Z
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-20-agent-fleet-assessor-critic-fixes.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-20-agent-fleet-assessor-critic-fixes.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-20-agent-fleet-assessor-critic-fixes.md`). See "Archive On Completion" in this agent's own operating instructions for the exact steps.

## Context

The renderer already fixed the Bash Command Policy contradiction fleet-wide in commit 5e1f3f17, but `.claude/agents/agent-fleet-assessor.md` was never regenerated after that fix — confirmed via `git grep "enforced boundary anyway"` across all 24 `.claude/agents/*.md` files, all still stale.

## Problem

- BLOCKER — Bash Command Policy section asserts it is simultaneously "the enforced boundary" and "advisory... overridden by settings.json," an irreconcilable framing already fixed fleet-wide upstream but never propagated to this file.
- MAJOR — "primary orchestrator (mode: all)" framing overclaims: `mode: all` is an OpenCode-only frontmatter field, absent from this file's actual Claude frontmatter. The underlying design intent (must be invoked directly, never nested, to retain delegation ability) is sound, but the wording overclaims what the file can literally become — and this constraint mattered concretely during the review run this file was designed for.
- MAJOR — `.claude/settings.json`'s enforced surface doesn't cover most of the body's own "Approved scripts" list (`fd`, `rg`, `ls`, `test -f/-d`, `command -v`, `wc`, `sed -n`, `git grep`, `check-file-refs.sh` all absent).
- MAJOR — the "Agent tool granted to this agent" claim is asserted but not independently verified against Anthropic's current Claude Code tool-naming — flagged unknown, recommend citing an existing evidence trail rather than re-fetching.
- MAJOR — no non-interactive fallback for the ">30 agents needs user approval" stop condition (Claude subagents can't use AskUserQuestion).

## Target Outcome

Regenerate (already-fixed-in-renderer) to close the BLOCKER; reword the Runtime Role opening clause to scope `mode: all` correctly; add a citation pointer for the Agent-tool claim; add a non-interactive fallback for the >30-agents stop condition; document (don't build) the settings.json coverage gap as a deferred, separately-approved item.

## In Scope

In `packages/ai-universal-rules/templates/optional/agents/agent-fleet-assessor.md`:

1. (Fix 2) Reword the "Runtime Role" opening clause so `mode: all` is scoped explicitly to OpenCode's frontmatter rather than asserted as this file's universal state, e.g. "This is a primary orchestrator (`mode: all` on OpenCode's frontmatter). On every runtime it must be invoked directly at the top of a session — never spawned as a nested subagent — so it retains the ability to delegate to agent-critic." Preserve every downstream sentence (Claude/Copilot nuance, nested-subagent stop instruction) unchanged.
2. (Fix 4) Change "Claude via the Agent tool granted to this agent" to cite its evidence trail, e.g. pointing at `docs/ai/integration-matrix.md`'s Runtime Limitation Notes (fetched 2026-07-04) as the tool-naming/AskUserQuestion-availability basis — does not change the tool-name decision, only makes it auditable.
3. (Fix 5) Extend the ">30 agents" Stop Conditions bullet with a non-interactive fallback reusing the convention already established for agent-creator-supervisor's approval gate ("...if the runtime cannot collect that approval interactively, state the exact count, emit `blocked: fleet size exceeds 30 — human approval required for a broad run`, and stop; never self-approve and never silently proceed").
4. Run `php tools/ai/ai.php install --apply` to regenerate `.claude/agents/agent-fleet-assessor.md`, `.opencode/agents/agent-fleet-assessor.md`, `.github/agents/agent-fleet-assessor.agent.md` — this single regeneration also closes Fix 1 (BLOCKER) since the renderer already emits the correct wording as of commit 5e1f3f17, just never propagated to this file. Confirm Fix 3 (settings.json coverage) requires no additional body edit once regenerated, since the renderer's fixed disclosure sentence already covers it generically.

## Out Of Scope (Things To Avoid)

- Rewriting the Runtime Role/Delegation Approach/Stop Conditions sections beyond the specific clauses named.
- Changing the Orchestrator Score Algorithm, Fleet Summary Algorithm, Reliable Aggregation, or Output Format sections.
- Re-applying the three already-APPLIED plan.md residual fixes (exactly-3/up-to-3 contradiction, fleet-summary bucket overlap, Core Mission wording).
- Widening `.claude/settings.json`'s enforced floor as part of this slice (deferred, separate approval — requires reviewer + release-auditor sign-off per architect Design Rules).
- Fixing sibling agents' independently-discovered regeneration drift (agent-creator-supervisor's missing sentence, or the ~19 other stale Bash Command Policy renders) — note as fleet-wide pattern only.
- Changing the Agent tool-name decision itself in `claude-agent-tool-registry.php`.

## Affected Paths

- `packages/ai-universal-rules/templates/optional/agents/agent-fleet-assessor.md`
- `.claude/agents/agent-fleet-assessor.md` (regenerated)
- `.opencode/agents/agent-fleet-assessor.md` (regenerated)
- `.github/agents/agent-fleet-assessor.agent.md` (regenerated)

## Contracts And Boundaries

Edit only the canonical template (Fixes 2, 4, 5); regeneration command is `php tools/ai/ai.php install --apply`; the regeneration will very likely touch other previously-stale `.claude/agents/*.md` files too as expected, already-approved-renderer-fix propagation — this is not scope creep and should not be reverted.

## Todo Plan

- [x] P0: Reword the Runtime Role opening clause (Fix 2) in the canonical template.
- [x] P0: Add the Agent-tool citation pointer (Fix 4) in the canonical template.
- [x] P0: Add the non-interactive fallback to the >30-agents Stop Conditions bullet (Fix 5) in the canonical template.
- [x] P1: Regenerate all three adapter copies. **Deviation:** did not run the broad `php tools/ai/ai.php install --apply` — the working tree already carries ~19 other stale/in-flight `.claude/agents/*.md` files from unrelated in-progress tickets, and a full install-apply would have rewritten those too, outside this plan's Affected Paths. Instead used a narrowly-scoped one-off script (`/tmp/opencode/render-plan20-agent-fleet-assessor.php`, not committed, deleted after use) that requires the exact same renderer files (`claude-agent-renderer.php`, `copilot-agent-renderer.php`, `generated-header.php`, `permission-layers/render-adapters.php`) and calls the exact same functions (`aiInstallerRenderClaudeAgent()`, `aiInstallerRenderCopilotAgent()`, plus the OpenCode raw-copy+header+placeholder steps `aiInstallerCopyDirAsOpenCodeAgents()` performs per-file) the real installer uses — same precedent as plan-13/15/16/17/18/19 — but wrote only the 3 target files. See Implementation Notes.
- [x] P1: `git diff` scoped to the three regenerated files plus the template — confirm only the 4 intended clauses changed (expect other stale files to also regenerate as a side effect; do not revert those). Confirmed for OpenCode and Copilot (exactly the 3 Fix 2/4/5 hunks, nothing else). The Claude render additionally picked up several already-fixed-generically renderer improvements this file had not yet been regenerated to reflect (the Fix 1 BLOCKER text plus 3 more: `git diff*`/`git log*` list additions, the mutation-command framing rewrite, and the Script-Access-vs-Bash-Command-Policy sentence) — all unconditional (no `$agentId` guard) in `claude-agent-renderer.php`, i.e. legitimate already-approved propagation per this plan's own Contracts And Boundaries, not scope creep. See Implementation Notes for the full accounting.
- [x] P2: Run the project's test suite for regressions — **done**, see Implementation Notes. Re-run agent-critic against the regenerated Claude file — **done**: an orchestrator session ran a fresh agent-critic audit against `.claude/agents/agent-fleet-assessor.md` (score 82, needs_refactor) and surfaced 2 fresh MAJORs and 2 fresh MINORs (the boolean-scoped `Agent` tool grant, the unreachable fleet-readiness `blocked_count > 0` state, the stale "prose-discouraged" sentence, and the unshaped "Recommended Next Step" section). All 4 were fixed in this pass (see Implementation Notes below) and re-verified.

## Acceptance Criteria

- [x] AC-01: `git grep -n "enforced boundary anyway" .claude/agents/agent-fleet-assessor.md` returns zero matches post-regeneration. Confirmed (`git grep` exit code 1 / no matches).
- [x] AC-02: Frontmatter stays unchanged: `tools: Read, Grep, Glob, Bash, Agent`; `disallowedTools: Write, Edit`; `permissionMode: plan`; no `mode:` key present. Confirmed byte-for-byte unchanged.
- [x] AC-03: The Runtime Role, Agent-tool-citation, and >30-agents fallback all read as specified above in the regenerated file. Confirmed via `git diff` on all three rendered adapters.
- [x] AC-04: The project's test suite passes with no regression from the regeneration step. The focused renderer/permission suite (`ClaudeAgentRendererTest`, `PermissionComposeTest`, `PermissionRenderAdaptersTest`, `InstallerSafetyTest`) passes: `OK (142 tests, 2922 assertions)`. The full `composer test:fast` run has 3 pre-existing failures (`AdapterRenderDriftTest` x2, `AgentPermissionDriftTest` x1) — confirmed unrelated to this slice: their drift lists name `architecture-plan-writer`, `config-maintainer`, `implementer`, `post-install`, `refactorer`, `repository-researcher`, `repository-reviewer`, `researcher`, `reviewer`, `workflow-auditor`, `agent-creator-*`, `bugfix`, `build-config`, `infra-auditor` — never `agent-fleet-assessor` — and those files were already `M` in `git status --short` before this session touched anything (unrelated in-progress tickets). No new failure was introduced by this slice's regeneration.

## Verification Plan

- `git diff` review of all touched files.
- Grep check per AC-01.
- Frontmatter confirmation per AC-02.
- Run the full test suite.
- Re-run agent-critic and record score/verdict delta per the repo's own re-audit convention (e.g. commit af500e0c).

## Risks And Rollback

- Risk — widening `.claude/settings.json`'s baseline would benefit every Claude agent but is a shared enforcement floor, not single-agent — deferred pending explicit owner approval, not bundled here.
- Risk — the Agent-tool citation (`docs/ai/integration-matrix.md`, dated 2026-07-04) is self-referential within this repo, not independently re-verified live against Anthropic's current docs — the citation makes the claim auditable, not independently re-confirmed; a live doc fetch remains a legitimate follow-up.
- Risk — regenerating via `install --apply` will touch ~19 other stale files as a side effect — reviewer should expect and accept the wider diff, not treat it as scope creep.
- Rollback: revert the three template clause edits and re-run `install --apply` to restore prior state.

## Handoff Notes

Recommended next step: implementer to apply Fixes 2/4/5, run `php tools/ai/ai.php install --apply`, and execute the Verification Plan. Expect and accept the wider regeneration diff touching other stale files (same underlying already-approved renderer fix propagating fleet-wide) — do not revert those incidental changes.

## Implementation Notes

**Working-tree context confirmed.** Before this session touched anything, `git status --short`
showed ~167 already-modified files from unrelated in-progress tickets in this series (settings.json,
manifest/catalog files, and ~19 other `.claude/agents/*.md` files mid-regeneration). Of this plan's
4 Affected Paths, only `.claude/agents/agent-fleet-assessor.md` was already modified (uncommitted);
the canonical template, `.opencode/agents/agent-fleet-assessor.md`, and
`.github/agents/agent-fleet-assessor.agent.md` were all clean (matched HEAD). `git show
HEAD:.claude/agents/agent-fleet-assessor.md` confirmed HEAD still had the stale "Treat the following
as the enforced boundary anyway." BLOCKER text and the stale "Full per-script `allow`/`ask`/`deny` is
in frontmatter" sentence — matching the plan's Context claim — but the pre-existing uncommitted
working-tree copy had *already* picked up the BLOCKER fix (from some other in-flight ticket's partial
regeneration), while Fixes 2/4/5 were genuinely absent everywhere. This matches the plan-19 precedent
exactly (some fixes pre-applied by other in-flight work, others not).

**Prior-art check (per task brief).** Read `tools/ai/install/claude-agent-renderer.php` in full
(376 lines). Confirmed: (1) the "enforced boundary anyway" -> "required agent policy; hard enforcement
depends on..." fix (line ~80-81) is unconditional, no `$agentId` guard — regenerating alone closes
Fix 1. (2) The "Full per-script `allow`/`ask`/`deny` is in frontmatter" -> "...documented in the Bash
Command Policy section above..." fix (line ~188-192) is also unconditional. (3) The mutation-command
framing rewrite ("Do not run — and `.claude/settings.json` hard-blocks — ...") is baked into the base
`$bashPolicy` construction (line 149), also unconditional; `release-auditor`/`workflow-auditor` only
override it further, on top of this same generic base text. (4) External-directory-clarification and
preview-file.sh-redaction fixes exist in the renderer but do not apply to this agent's body (it has
no such prose). (5) No `$agentId === 'agent-fleet-assessor'` override block exists anywhere in the
renderer — confirming this plan's premise that only a genuine regeneration (not new renderer logic)
was needed to close Fix 1/3.

**Canonical template edits (Fixes 2, 4, 5).** Applied to
`packages/ai-universal-rules/templates/optional/agents/agent-fleet-assessor.md` exactly as specified
in the plan's In Scope section (verbatim wording from the plan's own examples, with `agent-critic`
kept backtick-quoted to match the file's existing style). `git diff` on the template shows exactly 3
hunks, one per fix, no other line changed.

**Regeneration: narrow one-off script, not broad `install --apply`.** Per the task brief's explicit
instruction (avoid clobbering the ~19 other already-in-flight `.claude/agents/*.md` files a broad
`install --apply` would also rewrite), wrote `/tmp/opencode/render-plan20-agent-fleet-assessor.php`
(not committed, deleted after use) mirroring the plan-13/15/16/17/18/19 precedent: it requires the
exact same renderer files the real installer uses (`claude-agent-renderer.php`,
`copilot-agent-renderer.php`, `generated-header.php`, `permission-layers/render-adapters.php`,
`canonical-agent-frontmatter.php`, the tool/handoff registries) and calls the exact same functions
(`aiInstallerRenderClaudeAgent()`, `aiInstallerRenderCopilotAgent()`), plus reimplements the 2-step
OpenCode raw-copy path (`aiInstallerInsertGeneratedHeaderAfterFrontmatter()` + placeholder
substitution) that `aiInstallerCopyDirAsOpenCodeAgents()` performs per-file inside its
directory-wide-delete loop — reimplemented inline specifically to avoid that function's
`aiInstallerDeleteTree($dest)` call on the shared `.opencode/agents/` directory, which would have
deleted and rewritten every other agent file there. The script applied the same `<PROJECT_NAME>` ->
`awesome-ai-utmostcreator` and `<SCRIPTS_ROOT>` -> `scripts/ai` substitutions the installer's global
`aiInstallerApplyPlaceholders()` pass performs (these are the only two placeholder tokens present in
this agent's rendered output). It wrote only the 3 target files.

**Diff verification.** `git status --short` before vs. after the regeneration confirms exactly 4
paths changed in total (the template plus the 3 renders) — no other file in the ~167-file baseline
gained or lost a modification. Full diffs read for all 4 files:

- Template: 3 hunks, exactly Fixes 2/4/5, nothing else.
- `.opencode/agents/agent-fleet-assessor.md`: 3 hunks (6 lines changed), exactly Fixes 2/4/5. No
  frontmatter change, no generated-header change.
- `.github/agents/agent-fleet-assessor.agent.md`: 3 hunks (6 lines changed), exactly Fixes 2/4/5. No
  frontmatter/handoffs change.
- `.claude/agents/agent-fleet-assessor.md`: Fixes 2/4/5 plus 4 additional hunks, all already-fixed
  generic renderer improvements this file had not yet been regenerated to reflect (confirmed
  unconditional in the renderer, per the prior-art check above): the Fix 1 BLOCKER sentence, two new
  `git diff*`/`git log*` approved-script-list entries, the mutation-command framing rewrite, and the
  Script-Access-vs-Bash-Command-Policy sentence. Per this plan's own Contracts And Boundaries
  ("already-approved-renderer-fix propagation — this is not scope creep and should not be reverted")
  and the identical precedent in `DONE-plan-19-upgrade-claude-blocker-fix.md`'s Implementation Notes,
  these are expected byproducts of a genuine render invocation, not new defects.

**Verification evidence.**

- AC-01: `git -C <repo> grep -n "enforced boundary anyway" -- .claude/agents/agent-fleet-assessor.md`
  → exit code 1 (no matches). Verified.
- AC-02: read the regenerated frontmatter directly — `tools: Read, Grep, Glob, Bash, Agent`,
  `disallowedTools: Write, Edit`, `model: inherit`, `permissionMode: plan`, `agent_assessment` block
  unchanged, no `mode:` key. Verified byte-for-byte identical to the pre-regeneration frontmatter.
- AC-03: full `git diff` read for all 3 rendered adapters (above). Verified.
- AC-04 / focused suite:
  `vendor/bin/phpunit tests/php/ClaudeAgentRendererTest.php tests/php/PermissionComposeTest.php
  tests/php/PermissionRenderAdaptersTest.php tests/php/InstallerSafetyTest.php` ->
  `OK (142 tests, 2922 assertions)`. The one stderr line
  (`ERROR: invalid local policy overrides in .ai/project.yml: - local allow 'rm file' would downgrade
  global deny 'rm *'`) is expected fixture output from `InstallerSafetyTest`'s negative-path
  assertion, not a real failure (same as the plan-19 precedent).
- AC-04 / full suite: `composer test:fast` -> `Tests: 925, Assertions: 12521, Failures: 3, Skipped: 6`.
  Ran the 2 failing classes in isolation
  (`vendor/bin/phpunit tests/php/AdapterRenderDriftTest.php` and
  `vendor/bin/phpunit tests/php/AgentPermissionDriftTest.php`) and read their full failure output:
  neither drift list names `agent-fleet-assessor` anywhere; both name only files from unrelated
  in-progress tickets (`architecture-plan-writer`, `config-maintainer`, `implementer`, `post-install`,
  `refactorer`, `repository-researcher`, `repository-reviewer`, `researcher`, `reviewer`,
  `workflow-auditor`, `agent-creator-runtime-guardian`, `agent-creator-semantic-verifier`,
  `agent-creator-static-validator`, `agent-creator`, `agent-critic`, `bugfix`, `build-config`,
  `infra-auditor`), all of which were already `M` in `git status --short` before this session started.
  No regression introduced by this slice.
- Cleanup: deleted `/tmp/opencode/render-plan20-agent-fleet-assessor.php` after use.

**Second pass: fresh agent-critic re-audit (orchestrator session, 2026-07-08).** An orchestrator
session with Task-tool access ran the previously-blocked `agent-critic` re-audit against the
regenerated `.claude/agents/agent-fleet-assessor.md` and returned SCORE: 82/100, READINESS:
needs_refactor, with 2 fresh MAJOR findings and 2 fresh MINOR findings (none overlapping the 5
Problems already fixed in the first pass above):

1. MAJOR — Claude's `Agent` tool grant is boolean, not scoped to `agent-critic` by name, making
   "never spawn any other agent" prose-only/unenforceable. FIX: added a sentence directly under the
   Agent-tool citation bullet in "Delegation Approach And Runtime Fallback" instructing the agent to
   state and verify the exact `subagent_type` before every delegation call, and to treat any other
   surfaced subagent as `blocked: Agent tool scope exceeds mission`.
2. MAJOR — the Fleet Summary Algorithm's fleet-readiness branches had an unreachable/undefined
   state: a non-core agent blocked (`blocked_count > 0`) while `fleet_average >= 80` matched none of
   the three branches. FIX: changed the `blocked` branch to
   `any core workflow agent is blocked, or blocked_count > 0, or fleet_average < 80`, making the
   three branches jointly exhaustive.
3. MINOR — the Bash Command Policy footer's "Other listed commands ... are prose-discouraged and
   interactively gated, not hard-blocked" sentence was stale/misleading for this read-only
   orchestrator (same defect class release-auditor's plan-16 and workflow-auditor's plan-17 fixed).
   Per the task brief's instruction, checked `claude-agent-renderer.php` first and found the
   established `if ($agentId === '...')` per-agent override pattern (release-auditor,
   workflow-auditor) — added a matching `agent-fleet-assessor` override in the renderer (not the
   template, since this sentence is synthesized entirely by the renderer's Claude-only base
   `$bashPolicy` construction and never appears in the OpenCode-format template) with the exact
   wording the task specified. This is renderer-only and does not touch any other agent's render.
4. MINOR — "Recommended Next Step" had no fixed shape. FIX: added a `next:` / `reason:` /
   `blocking:` fixed-shape stub under the `## Recommended Next Step` heading in the Output Format
   section of the canonical template.

**Canonical template edits (this pass).** Applied fixes 1, 2, and 4 above to
`packages/ai-universal-rules/templates/optional/agents/agent-fleet-assessor.md`. `git diff` on the
template shows exactly 3 hunks, one per fix, no other line changed.

**Renderer edit (this pass).** Applied fix 3 above to `tools/ai/install/claude-agent-renderer.php`,
adding an `if ($agentId === 'agent-fleet-assessor')` block immediately after the existing
`workflow-auditor` override, mirroring its exact structure and comment style. Confirmed via
`ClaudeAgentRendererTest.php` (no existing test referenced `agent-fleet-assessor`, so no test
needed updating) that this is additive-only.

**Regeneration: narrow one-off script (this pass), not broad `--write`.** Per the task's explicit
instruction, wrote `/tmp/opencode/render-plan20-rerun-agent-fleet-assessor.php` (not committed,
deleted after use) that requires only `claude-agent-renderer.php` and `project-yaml.php` (which
transitively pull in every dependency `aiInstallerRenderClaudeAgent()` needs) and calls that exact
function with the same two placeholder substitutions (`<SCRIPTS_ROOT>`, `<PROJECT_NAME>` from
`.ai/project.yml`) that the newly-added `tools/ai/render-adapters.php` tool and the real installer
use — deliberately did NOT `require` `render-adapters.php` itself (it has top-level CLI side
effects/`exit()` calls unsuitable for require-as-library) and deliberately did NOT touch
`.opencode/agents/agent-fleet-assessor.md` or `.github/agents/agent-fleet-assessor.agent.md` (out
of this pass's scope; both already carried the pre-fix-1/2/4 Runtime-Role/Agent-tool/>30-agents
wording as pre-existing uncommitted changes from the earlier pass, and now show additional,
expected, not-yet-closed drift against the updated template for the 3 new fixes).

**Diff verification (this pass).** `git status --short` confirms exactly 5 paths changed in total:
the template, the renderer, and the one regenerated `.claude/agents/agent-fleet-assessor.md`
(`.opencode`/`.github` copies were already `M` beforehand and picked up no new writes from this
pass). Full `git diff` read for `.claude/agents/agent-fleet-assessor.md`: contains exactly fixes
1/2/3/4 from this pass, plus the fix-2/4/5 wording from the first pass (Runtime Role reword,
Agent-tool citation, >30-agents fallback) that the pre-existing working-tree copy of this file was
missing relative to the already-updated template — confirmed these are the same already-approved
first-pass fixes, not new scope, since the template itself already carried them unchanged going
into this pass.

**Verification evidence (this pass).**

- `vendor/bin/phpunit tests/php/PermissionRenderAdaptersTest.php tests/php/PermissionComposeTest.php
  tests/php/ClaudeAgentRendererTest.php` → `OK (74 tests, 1504 assertions)`.
- `php tools/ai/render-adapters.php --check` → reports drift for
  `.github/agents/agent-fleet-assessor.agent.md` (expected: only the Claude copy was regenerated
  this pass) plus the same pre-existing unrelated entries the first pass already catalogued
  (`architecture-plan-writer`, `config-maintainer`, `implementer`, `post-install`, `refactorer`,
  `repository-researcher`, `repository-reviewer`, `researcher`, `reviewer`, `workflow-auditor`,
  `agent-creator-*`, `agent-critic`, `bugfix`, `build-config`, `infra-auditor`, plus their
  `.github/agents/*.agent.md` counterparts where present). `.claude/agents/agent-fleet-assessor.md`
  does NOT appear in the drift list — confirmed byte-parity with the canonical template. No new
  entry beyond `agent-fleet-assessor`'s own Copilot copy was introduced.
- Frontmatter re-confirmed unchanged: `tools: Read, Grep, Glob, Bash, Agent`,
  `disallowedTools: Write, Edit`, `model: inherit`, `permissionMode: plan`, `agent_assessment` block
  unchanged, no `mode:` key.
- Cleanup: deleted `/tmp/opencode/render-plan20-rerun-agent-fleet-assessor.php` after use.

**Archive status: ARCHIVED.** Every Todo Plan item and every Acceptance Criteria item is now
checked `[x]`; per this file's own completion instruction, archived as
`archive/DONE-plan-20-agent-fleet-assessor-critic-fixes.md`.
