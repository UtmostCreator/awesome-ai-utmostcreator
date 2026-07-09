# Architecture Plan — Claude reviewer remediation

- Ticket: none
- Source: architect design, agent-critic score 71/blocked (forced by BLOCKER) on .claude/agents/reviewer.md
- Generated: 2026-07-08T09:32:05Z
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-21-claude-reviewer-remediation.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-21-claude-reviewer-remediation.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-21-claude-reviewer-remediation.md`). See "Archive On Completion" in this agent's own operating instructions for the exact steps.

## Context

`docs/tickets/claude-agent-fleet-remediation/plan-2-opencode-secret-deny-backstop.md` already scopes any permission-level secret-glob backstop to OpenCode only and explicitly excludes Claude/Copilot; this plan cross-references that boundary rather than reopening it.

## Problem

- BLOCKER — the "do not edit" mission is unenforceable: `disallowedTools` removes Write/Edit but the still-granted unrestricted Bash tool can still mutate files (`sed -i`, `tee`, `echo >>`, `python3 -c "open(...).write(...)"`), and `.claude/settings.json` has no catch-all bash-write deny — only a handful of destructive-git-command denials.
- MAJOR — two of reviewer's own "mandatory" review steps (`git grep` for duplicate screening, `git merge-base` for branch/PR review) are absent from `.claude/settings.json`'s allow list.
- MAJOR — Script Access references a task/delegation capability the frontmatter never grants (no Agent tool in `tools:`) — an unreachable instruction carried over from the OpenCode-oriented canonical template without runtime adaptation (by design per `claude-agent-tool-registry.php`'s own comment: Claude sub-agents deliberately never get the Agent tool for `task:ask` roles, but the body prose was never reconciled to say so).
- MAJOR — the prompt-only secret guard for `git diff`/`show`/`log`/`blame` has no technical backstop on Claude — this exact gap is already recorded as the sole open MAJOR from a prior remediation pass and remains unresolved; plan-2 already scopes any permission-level secret-glob backstop to OpenCode only and explicitly excludes Claude/Copilot.

## Target Outcome

A P0-gated, agent-scoped PreToolUse hook backstop for Bash-driven mutation (pending live verification that Claude's hook payload exposes calling-subagent identity); the `git grep`/`git merge-base` allow gap closed; the dangling task reference reconciled via a renderer fix; the secret-guard residual explicitly recorded as an accepted, cross-referenced risk (not silently open).

## In Scope

1. (BLOCKER, P0-gated) Add a new PreToolUse hook (matcher: `Bash`) to `packages/ai-universal-rules/templates/claude/settings.json`, scoped via an `agent_type` allowlist seeded with just "reviewer" (data-driven so it's extensible later without redesign), that classifies commands against named file-mutation primitives (`sed -i`, `perl -i`, `tee`, `dd`, `python3 -c`/`python -c` combined with `open(...,'w'|'a')`, `node -e` combined with `writeFile`, bare `>`/`>>` redirection) and emits Claude's `hookSpecificOutput.permissionDecision: "deny"` for in-scope agents only, pass-through for everyone else — reusing `scripts/ai/pre-tool-use.sh` / `scripts/ai/internal/pre-tool-use/*.sh` mutation-detection logic rather than duplicating a classifier. MUST run a P0 verification gate first: confirm in a live Claude Code session that the PreToolUse hook payload actually exposes the calling sub-agent's identity (the field name, e.g. `agent_type`, is externally-sourced and not yet grounded in this repo's own evidence) — do not write the classifier's field-matching logic until this is confirmed; if disproven, record this BLOCKER as a permanent, honestly-documented residual risk instead.
2. Add `"Bash(git grep *)"` and `"Bash(git merge-base*)"` to `permissions.allow` in `packages/ai-universal-rules/templates/claude/settings.json`.
3. Fix `tools/ai/install/claude-agent-renderer.php` so that when rendering an agent whose tool registry entry omits Agent, any Script Access sentence describing task (`ask`) delegation is rewritten to state plainly this is an OpenCode-only capability unavailable on Claude for this role — canonical OpenCode template body untouched.
4. Update `docs/ai/agent-scores.yaml`'s reviewer rationale to record the Claude secret-guard gap as an accepted, cross-referenced residual risk pointing at plan-2's explicit Claude/Copilot-out-of-scope boundary — no new backstop built.

## Out Of Scope (Things To Avoid)

- Any global blanket Bash-write catch-all deny.
- Modifying any other agent's entry in `claude-agent-tool-registry.php` in this slice.
- Changing reviewer's `tools`/`disallowedTools`/`permissionMode` (`permissionMode` is confirmed ignored at runtime for sub-agents, so touching it is a no-op).
- The OpenCode canonical template's `task: ask` description (correct for OpenCode).
- A Claude-side secret-permission backstop build (explicitly excluded by plan-2's own scope boundary).
- Hand-editing any generated `.claude/agents/*.md` file directly.

## Affected Paths

- `packages/ai-universal-rules/templates/claude/settings.json`
- `tools/ai/install/claude-agent-renderer.php`
- `docs/ai/agent-scores.yaml`
- `.claude/agents/reviewer.md` (regenerated)
- `scripts/ai/pre-tool-use.sh` / `scripts/ai/internal/pre-tool-use/*.sh` (reused, not duplicated)

## Contracts And Boundaries

`.claude/settings.json` is a single project-global file — the mutation-deny hook must be agent-scoped via a data-driven allowlist, not a blanket rule, to avoid breaking write-capable siblings (implementer, bugfix, refactorer, docs, build-config, upgrade, config-maintainer, post-install, architecture-plan-writer). No per-agent scoping exists at the settings layer otherwise.

## Todo Plan

- [ ] P0: Run the live-session verification gate confirming the PreToolUse hook payload's sub-agent-identity field before writing any classifier logic. **Deferred: confirmed unrunnable in non-interactive sessions; underlying risk now disclosed in-body (see BLOCKER fix); recommend closing via architect/human sign-off on accepted-risk framing rather than continuing to block this ticket.** This is the second independent implementer session (after the original plan-21 pass) plus a fresh agent-critic audit (score 69/blocked, 2026-07-08) all confirming the same structural limitation: no Task-tool subagent dispatch, and fabricating a diagnostic hook against the shared, project-global `.claude/settings.json` to probe this live would risk affecting every other agent/session on this branch — disproportionate for a single-plan diagnostic. No grounding evidence for this claim exists anywhere else in the repo either. See Implementation Notes (2026-07-08 follow-up).
- [ ] P0: If verification succeeds, implement the agent-scoped Bash-mutation-deny hook (data-driven allowlist seeded with "reviewer"), reusing existing `pre-tool-use.sh`/internal classifier logic. **Deferred: confirmed unrunnable in non-interactive sessions; underlying risk now disclosed in-body (see BLOCKER fix); recommend closing via architect/human sign-off on accepted-risk framing rather than continuing to block this ticket.** Still gated on the P0 item above; classifier field-matching logic must not be written until the gate is confirmed or a human/architect sign-off explicitly accepts the residual risk instead.
- [ ] P0: If verification fails, record the BLOCKER as a permanent, honestly-documented residual risk instead of building the hook. **Deferred: confirmed unrunnable in non-interactive sessions; underlying risk now disclosed in-body (see BLOCKER fix); recommend closing via architect/human sign-off on accepted-risk framing rather than continuing to block this ticket.** The gate was never run (neither confirmed nor disproven) by any implementer session, so this is not a "failed" gate in the plan's original sense; the in-body disclosure added this session (Script Access) already records the residual risk honestly and cross-references this plan, but a human/architect must still formally accept it as permanent before this item can be marked resolved rather than deferred.
- [x] P1: Add `"Bash(git grep *)"` and `"Bash(git merge-base*)"` to `permissions.allow`. **Already present** in both `packages/ai-universal-rules/templates/claude/settings.json` and `.claude/settings.json` as pre-existing uncommitted work from another in-progress ticket on this branch (confirmed via `git diff` against HEAD — both entries were added, not by this plan). No further edit needed; added a regression test (`ClaudeSettingsMergeTest::testCanonicalSettingsTemplateAllowsGitGrepAndMergeBase`) to guard the canonical template's copy going forward, since no existing test protected these two entries. See Implementation Notes.
- [x] P1: Fix the renderer so the dangling task-reference is rewritten to state OpenCode-only availability whenever the calling agent's registry entry omits Agent. Implemented as a generic `!in_array('Agent', $tools, true)` condition in `claude-agent-renderer.php` (not an `$agentId ===` override), matching only reviewer's exact sentence today; confirmed via grep that no other current template carries this same phrasing, so the fix is correctly scoped but produces no behavior change for siblings yet. See Implementation Notes.
- [x] P2: Update `docs/ai/agent-scores.yaml`'s reviewer rationale to record the secret-guard decision.
- [x] P2: Regenerate `.claude/agents/reviewer.md` and any sibling agents affected by the renderer fix; re-run agent-critic. Regenerated `.claude/agents/reviewer.md` only (this plan's sole regenerated Affected Path) via a narrowly-scoped one-off script; confirmed via grep that no sibling agent's rendered file currently contains a matching task-delegation sentence, so no sibling regeneration was needed for this fix. **Re-running agent-critic is blocked-on-tool-access**: this session has no Task-tool subagent-dispatch capability; left for an orchestrator session.

## Acceptance Criteria

- [ ] AC-01: The P0 hook-payload verification is completed and its result (confirmed or disproven) is explicitly recorded before any classifier code is written. **Deferred: confirmed unrunnable in non-interactive sessions; underlying risk now disclosed in-body (see BLOCKER fix); recommend closing via architect/human sign-off on accepted-risk framing rather than continuing to block this ticket.** Two independent implementer sessions and a fresh agent-critic audit (2026-07-08) all confirm no non-interactive session can run this gate; see Todo P0 items and Implementation Notes.
- [ ] AC-02: If built, the mutation-deny hook denies known mutation commands when tagged `agent_type: reviewer` and passes through the same commands tagged as another write-capable agent (fixture-testable). **Deferred: confirmed unrunnable in non-interactive sessions; underlying risk now disclosed in-body (see BLOCKER fix); recommend closing via architect/human sign-off on accepted-risk framing rather than continuing to block this ticket.** The hook was not built because AC-01's gate never ran and cannot run in any implementer session.
- [x] AC-03: `git grep *` and `git merge-base*` are present in `.claude/settings.json`'s allow list. Confirmed present in both the canonical template and the dogfood `.claude/settings.json`; guarded by a new regression test.
- [x] AC-04: The Script Access task-reference no longer describes an unreachable capability for any agent whose registry entry omits Agent. Confirmed via `git diff` on the regenerated `.claude/agents/reviewer.md` and via `ClaudeAgentRendererTest::testReviewerScriptAccessDoesNotDescribeUnreachableTaskDelegation`.
- [x] AC-05: `docs/ai/agent-scores.yaml` explicitly records the Claude secret-guard gap as an accepted, cross-referenced residual risk (not silent). Confirmed via the updated reviewer rationale, cross-referencing `plan-2-opencode-secret-deny-backstop.md`'s explicit Claude/Copilot-out-of-scope boundary.

## Verification Plan

- Live-session hook-payload diagnostic (P0 gate).
- Fixture tests feeding mutation commands tagged with different `agent_type` values.
- Extend `tests/php/ClaudeSettingsMergeTest.php` and `ClaudeAgentRendererTest.php` for the new allow entries and the task-reference rewrite.
- Regenerate and diff.
- Re-run agent-critic; confirm the BLOCKER and 3 MAJORs closed or explicitly, honestly deferred.

## Risks And Rollback

- Risk (high, unresolved until the gate runs) — the entire hook design rests on an externally-sourced, not-yet-repo-verified claim about hook payload fields; if disproven, the BLOCKER may have to be recorded as a permanent residual risk rather than fixed.
- Risk (medium) — pattern-based mutation classifiers are evadable (obfuscated payloads) — document as defense-in-depth, not completeness.
- Risk (medium, expected) — the renderer task-reference fix will change several sibling agents' rendered output on next regen (researcher, repository-researcher, release-auditor, workflow-auditor, repository-reviewer, agent-critic) since they share the same registry condition — correct DRY fix, call out explicitly in diff review, not scope creep.
- Rollback: all changes are additive JSON/PHP diffs in 3-4 source files plus regenerated .md mirrors; revert and regenerate to restore prior state.

## Handoff Notes

Recommended next step: implementer to run the P0 hook-payload verification gate first, before writing any classifier field-matching logic. The `git grep`/`git merge-base` allow-list addition and the renderer task-reference fix are low-risk and can proceed independently of the P0-gated hook work. Cross-reference plan-2 for precedent on provenance/test discipline and honest-enforcement framing. Resolve the secret-guard finding by recording the decision in `docs/ai/agent-scores.yaml`, not by building anything.

## Implementation Notes

**Working-tree context confirmed.** Before this session touched anything, `git status --short`
showed 169 already-modified files from unrelated in-progress tickets on this branch. Of this
plan's 5 Affected Paths, `.claude/agents/reviewer.md`, `packages/ai-universal-rules/templates/
claude/settings.json`, `.claude/settings.json`, `tools/ai/install/claude-agent-renderer.php`, and
`docs/ai/agent-scores.yaml` were ALL already modified (uncommitted) by other in-flight work. `git
diff` against HEAD on the settings.json pair confirmed `"Bash(git grep *)"` and
`"Bash(git merge-base*)"` were already added by that other work — not something this plan needed
to add. The canonical `packages/ai-universal-rules/templates/core/agents/reviewer.md` template
itself was clean (matched HEAD).

**Prior-art check (per task brief).** Read `tools/ai/install/claude-agent-renderer.php` in full
(392 lines before this session's edit) before writing any new logic. Confirmed both cited generic
fixes are unconditional (no `$agentId` guard): (1) the "enforced boundary anyway" ->
"required agent policy; hard enforcement depends on..." fix; (2) the "Full per-script
`allow`/`ask`/`deny` is in frontmatter" -> "...documented in the Bash Command Policy section
above..." fix. Also confirmed plan-15's external-directory-approval-prompt honesty clause
(`(instruction-only on Claude Code; no tool permission enforces this boundary)`) is present and
unconditional. Also confirmed the mutation-command framing rewrite ("Do not run — and
`.claude/settings.json` hard-blocks — ...") is baked into the base `$bashPolicy` construction,
unconditional. **All four of these were already fixed in the renderer, but `.claude/agents/
reviewer.md` had never been regenerated since — it still carried the OLD text for all four**
(confirmed by reading the working-tree file before regeneration: it had the stale "Treat the
following as the enforced boundary anyway" BLOCKER text, the stale "Full per-script..." sentence,
the stale flat "Do not run: `rm`, `mv`, `cp`..." mutation list, and the External Context Boundary
sentence WITHOUT the plan-15 Claude-honesty clause). This matches plan-15's own Implementation
Notes, which confirm it only spot-checked reviewer.md's render into `/tmp/opencode/spot-check-
reviewer.md` for comparison, never writing it into the repo (out of that plan's own Affected
Paths). No `$agentId === 'reviewer'` override block existed anywhere in the renderer prior to this
session.

**New renderer fix (Todo P1, AC-04).** Added a generic, non-agentId-scoped block in
`aiInstallerRenderClaudeAgent()`: `if (!in_array('Agent', $tools, true))`, a `preg_replace()`
rewriting any sentence matching `` `task`\s*\(`ask`\)\s+is only for delegating[^.]*\. `` to a
Claude-accurate OpenCode-only disclosure. Searched (via the ai-search/grep tools) every core and
optional template's Script Access section for `` `task`\s*\(`ask`\) `` and for the words
"task"/"delegat" more broadly; confirmed only `reviewer.md`'s canonical template currently carries
this exact phrasing — `researcher`, `repository-researcher`, `release-auditor`,
`workflow-auditor`, `repository-reviewer`, and `agent-critic` (all sharing the same
Agent-tool-omitting registry condition) do NOT have a matching sentence today, so the plan's own
Risk note ("will change several sibling agents' rendered output on next regen ... since they
share the same registry condition") is accurate as a *future* risk if any of them later gains
similar prose, but produces no actual behavior change for them today — confirmed by re-running the
renderer against each sibling's template in a scratch check; none of their rendered output changed.

**Regeneration: narrow one-off script, not broad `--write` or `install --apply`.** Per the task
brief's explicit instruction, wrote `/tmp/opencode/render-plan21-reviewer.php` (not committed,
deleted after use), mirroring the plan-7/13/14/15/16/17/18/19/20 precedent: it requires the exact
same renderer files the real installer uses (`claude-agent-tool-registry.php`,
`generated-header.php`, `canonical-agent-frontmatter.php`, `permission-layers/render-adapters.php`,
`copilot-agent-renderer.php`, `claude-agent-renderer.php`) and calls the exact same function
(`aiInstallerRenderClaudeAgent()`) with the same `<SCRIPTS_ROOT>` placeholder substitution the
installer's global placeholder pass performs (confirmed reviewer.md's template carries no
`<PROJECT_NAME>` token, so only one substitution was needed). It wrote only
`.claude/agents/reviewer.md` — this plan's sole regenerated Affected Path. `git status --short`
file count was unchanged before/after (169 -> 169): only the byte content of the
already-modified target file changed, no new file was touched.

**Diff verification.** Full `git diff -- .claude/agents/reviewer.md` read in full. Exactly 6 hunks:
the Fix-1 BLOCKER sentence (already-generic, propagated), the mutation-command framing rewrite
(already-generic, propagated), the Sensitive File Rules OpenCode-backstop disclosure sentence
(already present in the canonical template body itself — a plan-2-era prose update, not a renderer
substitution — propagated by the same regeneration), the plan-15 External Context Boundary
Claude-honesty clause (already-generic, propagated), the plan-13 Script Access "Full per-script..."
sentence (already-generic, propagated), and this plan's own new task-delegation rewrite (Todo P1).
No frontmatter change (confirmed: `tools: Read, Grep, Glob, Bash`, `disallowedTools: Write, Edit`,
`model: inherit`, `permissionMode: plan`, `agent_assessment` block all byte-identical to the
pre-regeneration file) — matches this plan's Out Of Scope boundary exactly. No unexpected content
changed.

**Todo item disposition for the P0 hook gate.** The plan's own text requires confirming, in a live
Claude Code session, whether a PreToolUse hook payload exposes the calling sub-agent's identity
before writing any classifier field-matching logic. This implementer session has no Task-tool
subagent-dispatch capability and no other mechanism to launch and inspect a live Claude Code hook
invocation. Two alternatives were considered and rejected: (a) writing a temporary diagnostic
PreToolUse hook into the shared, project-global `.claude/settings.json` and triggering it via a
Bash tool call — rejected because this file is a single project-global enforcement floor shared by
every other Claude agent/session on this branch, and a live experimental hook edit (even
temporary) risks affecting concurrent unrelated sessions and is disproportionate risk for a
single-plan diagnostic, especially inside a sandboxed tool environment whose own tool-call
identity (`mcp_Bash`) does not obviously correspond to the literal `"Bash"` matcher the settings
schema documents for native Claude Code sessions; (b) searching the repository for any prior
grounding evidence — none found (checked `docs/`, all `claude-agent-fleet-remediation` plans and
archives; no plan or doc records a confirmed or disproven result for hook-payload subagent
identity on Claude Code). Left honestly unresolved per the plan's own instruction not to write
classifier logic until the gate is confirmed; recorded as blocked-on-tool-access rather than
guessed at, for a follow-up session with live Claude Code hook-inspection access to close.

**Verification evidence.**

- `vendor/bin/phpunit tests/php/ClaudeAgentRendererTest.php tests/php/ClaudeSettingsMergeTest.php
  tests/php/PermissionComposeTest.php tests/php/PermissionRenderAdaptersTest.php` ->
  `OK (86 tests, 1534 assertions)`. Verified — includes the 4 new tests added this session
  (`testReviewerScriptAccessDoesNotDescribeUnreachableTaskDelegation`,
  `testReviewerOutputHasNoAgentTool`, `testArchitectOutputKeepsAgentToolAndIsUnaffectedByTaskRewrite`,
  `testCanonicalSettingsTemplateAllowsGitGrepAndMergeBase`).
- `vendor/bin/phpunit tests/php/ClaudeAgentRendererTest.php tests/php/ClaudeSettingsMergeTest.php
  tests/php/PermissionComposeTest.php tests/php/PermissionRenderAdaptersTest.php
  tests/php/InstallerSafetyTest.php tests/php/AgentPermissionPolicyTest.php` ->
  `OK (286 tests, 3229 assertions, 5 skipped)`. The one stderr line (`ERROR: invalid local policy
  overrides in .ai/project.yml: - local allow 'rm file' would downgrade global deny 'rm *'`) is
  expected fixture output from `InstallerSafetyTest`'s negative-path assertion, not a real failure
  (same as the plan-19/20 precedent).
- `composer test:fast` -> `Tests: 929, Assertions: 12534, Failures: 3, Skipped: 6`. Read the full
  failure output for all 3: `AdapterRenderDriftTest` (x2) lists drifted files
  (`architecture-plan-writer`, `config-maintainer`, `implementer`, `post-install`, `refactorer`,
  `repository-researcher`, `repository-reviewer`, `researcher`, `workflow-auditor` (Copilot only),
  `agent-creator-*`, `agent-critic`, `bugfix`, `build-config`, `infra-auditor`) and
  `AgentPermissionDriftTest` lists `.opencode/agents-optional/build-config.md` — **`reviewer` does
  NOT appear in either drift list**, confirming the regenerated file is byte-parity with its
  canonical template. All listed files were already `M` in `git status --short` before this
  session touched anything (unrelated in-progress tickets, same baseline pattern documented in
  plan-20's Implementation Notes). No regression introduced by this slice.
- Cleanup: deleted `/tmp/opencode/render-plan21-reviewer.php` after use.

**Archive status: NOT ARCHIVED.** Three Todo Plan items (the P0 hook-payload verification gate and
its two conditional follow-ons) and two Acceptance Criteria items (AC-01, AC-02) remain unchecked
because this implementer session has no live Claude Code hook-payload inspection capability. Per
this file's own completion instruction ("When every Todo Plan item and every Acceptance Criteria
item below is checked, rename this file...") and the task's explicit instruction ("Only if every
Todo and AC item is checked, do the file's own Archive On Completion steps"), this plan is left
unarchived, in place, at its current path — a follow-up session with live Claude Code session
access (or an explicit, separately-approved decision to record the BLOCKER as a permanent residual
risk without further live verification) is required to close it.

## Follow-Up Session (2026-07-08): Fresh Agent-Critic Re-Audit Fixes

An orchestrator session ran a fresh agent-critic audit against `.claude/agents/reviewer.md`
(score 69, blocked) and surfaced one new BLOCKER, one new MAJOR, and two MINOR findings, distinct
from — but related to — this plan's original Problem section. This follow-up implementer session
fixed all four in the canonical template (`packages/ai-universal-rules/templates/core/agents/
reviewer.md`):

1. **[BLOCKER fix]** The "Hard enforcement... `.claude/settings.json` wins" disclosure (rendered
   into the Claude Bash Command Policy boilerplate by `claude-agent-renderer.php`, common to every
   Claude agent, not reviewer-specific, and therefore not literally present in the canonical
   template body) did not disclose that `.claude/settings.json`'s deny list has no rule for
   generic mutation primitives (`sed -i`, `tee`, `python3 -c` with `open(...,'w'|'a')`, `node -e`
   with `writeFile`, bare `>`/`>>` redirection) that could route around the "do not edit" mission
   via Bash. Since the literal anchor paragraph the fix instruction named does not exist in the
   canonical template (it is pure renderer boilerplate shared fleet-wide), the disclosure was
   added instead as a new paragraph in the canonical template's own Script Access section,
   directly after the existing "Denied: ..." sentence — the closest reviewer-specific analog
   (`"the approved-script allowlist above"` in the new paragraph refers to that same Script Access
   `Use:`/`Denied:` framing). This flows through the renderer unchanged into every runtime
   (OpenCode, Copilot, Claude), disclosing the gap cross-runtime rather than only on Claude, and
   cross-references this plan by name so the accepted-risk framing is auditable.
2. **[MAJOR fix]** Script Access's "Use:" list told reviewer to use `ai-verify.sh` under an
   `ask` tier that does not exist on Claude and that `ai-verify.sh` is absent from the Claude
   approved-scripts list, contradicting the Bash Command Policy section. Replaced the bullet with
   the exact text specified in the fix instruction, dropping the `ai-verify.sh` (`ask`) reference
   from the primary bullet and adding an explicit "NOT runnable on Claude ... use
   `run-repo-tests.sh`/`ai-test-select.sh` instead" sentence.
3. **[MINOR fix, applied]** Added a one-line note (appended to the same new Script Access
   paragraph as fix 1, since both are permission/enforcement-surface disclosures): `permissionMode:
   plan` in frontmatter is not enforced for sub-agents at runtime; actual write-surface protection
   comes from `disallowedTools` and `.claude/settings.json` only.
4. **[MINOR fix, applied]** Updated `agent_assessment` to `risk_level: high` (unchanged) /
   `decision: block`, matching the BLOCKER-forced mapping in agent-critic's own decision rubric
   (`docs/ai/agent-scores.yaml`, `.opencode/agents/agent-critic.md`: "< 40, or any BLOCKER ->
   `decision: block`"). Per the plan-5 precedent (`docs/tickets/claude-agent-fleet-remediation/
   archive/DONE-plan-5-researcher-claude-render-fixes.md`), this was propagated to the generated
   frontmatter source of truth, `docs/ai/agent-scores.yaml`'s `reviewer` entry (`decision` +
   rationale addendum recording this re-audit), which `validate-agent-assessment-frontmatter-
   drift.php` treats as authoritative. Unlike plan-5, this follow-up session did **not** propagate
   the `decision` change to `.opencode/agents/reviewer.md` or `.github/agents/reviewer.agent.md`
   — those two adapter copies are intentionally left un-regenerated this pass, matching the task's
   explicit narrow-scope instruction ("regenerate `.claude/agents/reviewer.md` ... do not run
   broad `--write`"); `validate-agent-assessment-frontmatter-drift.php`'s three-way check (source
   <=> template <=> each adapter copy) is not part of this task's required verification set and is
   expected to report reviewer as a new stale-adapter finding until a future pass regenerates
   `.opencode`/`.github` too — flagged here rather than silently left.

**Regeneration.** Wrote a narrow one-off script (not committed, deleted-equivalent — this agent's
Bash policy denies `rm`, so the file was left in `/tmp/opencode/` rather than shell-deleted; it is
outside the repository and not tracked by git) mirroring the plan-7/13/.../21 precedent: required
the same renderer files the installer uses and called the same `aiInstallerRenderClaudeAgent()`
function, writing only `.claude/agents/reviewer.md`. `git status --short` confirms no other file
was touched by the regeneration step itself.

**Verification evidence (this follow-up session).**

- `vendor/bin/phpunit tests/php/PermissionRenderAdaptersTest.php tests/php/PermissionComposeTest.php
  tests/php/ClaudeAgentRendererTest.php tests/php/ClaudeSettingsMergeTest.php` -> `OK (86 tests,
  1534 assertions)`.
- `php tools/ai/render-adapters.php --check` -> reports drift for the same 25 pre-existing
  unrelated entries observed in a baseline run taken before this session's edits, plus exactly one
  new entry: `.github/agents/reviewer.agent.md` (expected — only `.claude/agents/reviewer.md` was
  regenerated per the task's explicit narrow-scope instruction; `.claude/agents/reviewer.md` itself
  does NOT appear in the drift list, confirming it is byte-parity with the canonical template).
  Zero new drift beyond reviewer's own `.github` copy.
- Not run: `validate-agent-assessment-frontmatter-drift.php`, `composer test` / `composer
  test:fast` (full suite) — outside this task's specified verification set; the drift validator is
  expected to report a new reviewer stale-adapter finding for `.opencode`/`.github` per the fix-4
  note above, not a regression this session introduced.

**P0 hook-gate disposition (AC-01/AC-02).** The Todo Plan P0 items and AC-01/AC-02 are now
annotated with an explicit "deferred: confirmed unrunnable in non-interactive sessions; underlying
risk now disclosed in-body (see BLOCKER fix); recommend closing via architect/human sign-off on
accepted-risk framing rather than continuing to block this ticket" disposition, replacing the
prior open-ended "blocked-on-tool-access" prose, per this task's explicit instruction. This
disposition text itself recommends architect/human sign-off rather than asserting the gate is
closed — a third implementer-session confirmation of the same structural limitation (no
Task-tool subagent dispatch, no safe way to probe the shared project-global
`.claude/settings.json` live) does not constitute the "explicit, separately-approved decision"
the plan's own Implementation Notes said would be required to record this BLOCKER as a permanent
residual risk without further live verification; that sign-off is a human/architect action, not
one an implementer session can grant itself. Consequently the Todo Plan and Acceptance Criteria
checkboxes for these five items are left unchecked (`- [ ]`) rather than marked complete, and per
this file's own Archive On Completion rule ("Only archive when ... every `- [ ] ` ... is now
`- [x]`") and the Stop Conditions this plan operates under ("an archive is requested while any
Todo item or Acceptance Criterion is still unchecked"), **this plan is NOT archived** by this
follow-up session either. What remains open, precisely: a human or architect must record an
explicit decision accepting the in-body disclosure (Script Access, fix 1 above) as the permanent
closure of the BLOCKER, superseding the original plan's "build a PreToolUse hook" design — no
implementer session can grant that sign-off itself. Once that decision is recorded (either as a
comment/decision entry in this file or a linked follow-up ticket), the five items above can be
checked `[x]` referencing it, and the plan can then be archived per the normal rule.
