# Architecture Plan — agent-creator-runtime-guardian permission fix (critical)

- Ticket: none
- Source: architect design, agent-critic score 35/blocked (risk_level critical) on .claude/agents/agent-creator-runtime-guardian.md
- Generated: 2026-07-08T09:27:50Z
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-25-agent-creator-runtime-guardian-permission-fix.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-25-agent-creator-runtime-guardian-permission-fix.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-25-agent-creator-runtime-guardian-permission-fix.md`). See "Archive On Completion" in the architecture-plan-writer agent policy for the exact steps.

## Context

`.claude/agents/agent-creator-runtime-guardian.md` was scored 35/blocked with risk_level critical by agent-critic. The file grants unrestricted raw readers and self-contradicts its own Script Access section against its Bash Command Policy.

## Problem

- BLOCKER: unrestricted `sed -n`/`head`/`tail` raw readers contradict the file's own "do not read/print secrets" rule, no settings.json Bash-deny backstop.
- BLOCKER: Script Access self-contradicts the Bash Command Policy (names `session-checkpoint.sh`/`ai-rollback.sh`/`ai-verify.sh` as actively-used tools while the policy lists those same three as NOT runnable).
- MAJOR: missing non-interactive ambiguity fallback (already fixed in template, dropped from render).
- MAJOR: vague Recommended Next Step names ("the supervisor", "implement") instead of exact roster ids.
- MINOR: Stop Conditions doesn't require concrete values or explicit unknown.

## Target Outcome

Raw-reader grant is narrowed from allow to ask via the existing permission-composition system; Script Access wording reflects the ask-tier reality instead of falsely implying free use; stale-render items (fallback, roster ids) sync from the already-correct template.

## In Scope

- In `tools/ai/install/permission-layers/compositions.php`, add `askPacks: ['core.safe_read.raw_read_ask_gate']` to the agent-creator-runtime-guardian composition entry — reuses an existing, precedented pack to narrow `sed -n`/`head`/`tail`/`jq`/`yq`/`rg`/`bat` from allow to ask. Regenerate via `php tools/ai/generate-agent-permissions.php --write`.
- In the canonical template's Script Access section, reword the `session-checkpoint.sh`/`ai-rollback.sh`/`ai-verify.sh` bullets to state they are ask-tier/approval-gated capabilities, not tools freely "used" — keeping the documented capability (per `docs/ai/agent-script-access.md`) intact while removing the false "actively uses" framing.
- Confirm (already present, no edit needed) the non-interactive fallback, roster-id Recommended Next Step, and concrete-value Stop Conditions hint in the template, then regenerate this one agent's Claude render.

## Out Of Scope (Things To Avoid)

- Touching any other agent's template/composition/render.
- Adding a settings.json Bash-level secret-path deny backstop (fleet-wide, separate ticket).
- Changing `agent_assessment` by hand.
- Changing `tools`/`disallowedTools`/`permissionMode`.
- A full fleet re-render.

## Affected Paths

- `tools/ai/install/permission-layers/compositions.php`
- `packages/ai-universal-rules/templates/optional/agents/agent-creator-runtime-guardian.md`
- `.claude/agents/agent-creator-runtime-guardian.md` (regenerated)

## Contracts And Boundaries

Permission grants live only in `compositions.php`/`packs.php`/`core.php`, never hand-authored into frontmatter or the Claude render. Regenerate via the confirmed installer entrypoint; do not hand-type.

## Todo Plan

- [x] P0: Add the askPacks entry to compositions.php's agent-creator-runtime-guardian composition and run `php tools/ai/generate-agent-permissions.php --write`. **Deviation:** ran via a narrowly-scoped one-off script instead of the broad `--write` sweep — see Implementation Notes.
- [x] P0: Reword the Script Access bullets for `session-checkpoint.sh`/`ai-rollback.sh`/`ai-verify.sh` to reflect ask-tier gating, not active use.
- [x] P1: Regenerate this one agent's Claude render and confirm the fallback/roster-id/Stop-Conditions content (already in template) now appears correctly.
- [x] P2: Run `tests/php/PermissionComposeTest.php` and `PermissionRenderAdaptersTest.php` (done, 50 tests/1306 assertions, OK — see Implementation Notes); re-run agent-critic — an orchestrator session ran a fresh audit (score 85, needs_refactor) against `.claude/agents/agent-creator-runtime-guardian.md` and found 1 MAJOR + 2 MINOR findings, all fixed and re-verified in this pass (see Implementation Notes, "Agent-Critic Re-Run Findings (Fresh Audit)").

## Acceptance Criteria

- [x] AC-01: `sed -n`/`head`/`tail`/`jq`/`yq` no longer appear in the Approved scripts list (moved to ask-tier, which on Claude means removed from the approved list per the existing disclaimer pattern). Verified via direct read of the regenerated `.claude/agents/agent-creator-runtime-guardian.md`.
- [x] AC-02: Script Access no longer claims active use of ask-tier-only scripts. Verified via direct read — the reworded bullets state runtime-conditional, ask-tier framing instead of unconditional active use.
- [x] AC-03: Non-interactive fallback, exact roster ids, and concrete-value Stop Conditions all appear in the regenerated render. Verified via direct read (Hard Rules line, Final Output roster-id routing, Stop Conditions concrete-value hint).

## Verification Plan

- `git diff` scoped to compositions.php confirms only this agent's askPacks changed.
- Diff the regenerated render against expectations for AC-01, AC-02, AC-03.
- Run the two named PHPUnit files.
- Re-run agent-critic and confirm both BLOCKERs cleared.

## Risks And Rollback

- Risk: exact regeneration command for a single agent's Claude render is unconfirmed; implementer must verify before relying on it.
- Risk: ask-gating via a shared pack could ripple if that pack is later reused/edited elsewhere; mitigated by only touching this agent's askPacks list.
- Rollback: revert compositions.php and template diffs, re-run --write.

## Handoff Notes

Recommended next step: implementer to apply the compositions.php entry and template wording fix, regenerate, run the two named PHPUnit files, then re-run agent-critic. This is risk_level critical — treat as P0 across the fleet.

## Implementation Notes

**Working-tree context confirmed.** Before this session touched anything, `git status --short`
showed ~177 lines of already-modified files from unrelated in-progress tickets on this branch
(consistent with plan-14/plan-24's documented pattern). Of this plan's 3 Affected Paths, none had
been touched by other WIP yet — `packages/ai-universal-rules/templates/optional/agents/
agent-creator-runtime-guardian.md` was clean against HEAD; `.claude/agents/
agent-creator-runtime-guardian.md` was already `M` (modified) by other in-flight WIP, but reading
that pre-existing diff showed it already carried the non-interactive fallback, roster-id Recommended
Next Step, and concrete-value Stop Conditions hint the plan's own P1 item expected to "already be
present" — confirming the template's own already-correct content (also confirmed clean/unmodified)
had simply not yet been re-rendered into all necessary copies at session start.
`tools/ai/install/permission-layers/compositions.php` was already `M` too, but the pre-existing diff
was scoped entirely to a different agent's entry (`build-config`, ~line 850-870), confirmed via
direct read not to touch the `agent-creator-runtime-guardian` entry (~line 1008) this plan edits.

**P0 #1 (askPacks entry).** Added `'core.safe_read.raw_read_ask_gate'` as a third entry to the
existing `askPacks` array on the `agent-creator-runtime-guardian` composition
(`tools/ai/install/permission-layers/compositions.php`), alongside the two already-present entries
(`verify.manual_ask`, `agent_creator.ask_session_checkpoint`). Confirmed via read of `packs.php`
that this pack narrows `rg *`/`bat *`/`jq *`/`yq *`/`head *`/`tail *`/`sed -n *` from allow to ask,
and confirmed via `aiPermissionComposeFromSpec()`'s own ordering (deny_packs -> allow_packs ->
ask_packs -> exceptions -> hard-deny floor) that this agent's existing `denyPacks`
(`deny_extended_probe_tools`, `deny_common_generics`) contain none of these seven patterns, so no
conflict/re-deny exception was needed (unlike the `post-install` precedent noted in the same file,
which does need one). `php -l` confirmed no syntax errors after the edit.

**Deviation — narrow script instead of broad `--write`.** The plan's own In Scope text names
`php tools/ai/generate-agent-permissions.php --write` as the regeneration command. Per the task's
explicit instruction not to clobber unrelated uncommitted work, this broad sweep was **not** run:
`php tools/ai/generate-agent-permissions.php --check`, run *before* any edit in this session,
already showed exactly one pre-existing, unrelated drift entry
(`.opencode/agents-optional/build-config.md`) — confirming that a broad `--write` would have also
rewritten that unrelated file (out of this plan's scope; matches this plan's own Out Of Scope
"Touching any other agent's template/composition/render"). Instead, wrote a narrowly-scoped
one-off script (`/tmp/opencode/render-plan25-agent-creator-runtime-guardian-perm.php`, not
committed, deleted after use, mirroring the plan-14/plan-16/plan-24 precedent) that composes only
the `agent-creator-runtime-guardian` entry via the real `aiPermissionComposeFromSpec()` /
`aiPermissionRenderOpenCodeBlock()` library functions, and splices the result into only this
agent's own files. `aiPermissionSpliceBlock()` itself lives inside
`generate-agent-permissions.php`'s own top-level entrypoint script (not a require-safe library —
that file executes its full CLI logic, including `exit()`, at require-time), so its ~55-line body
was reproduced verbatim (confirmed via direct read before copying, cited by source line range in
the script's own comment) rather than required, matching the plan-14 precedent of reproducing
non-library entrypoint logic for a scoped script. Re-running `generate-agent-permissions.php
--check` after the scoped write confirmed: only the same one pre-existing `build-config.md` entry
remains; `agent-creator-runtime-guardian` produces zero drift in either its template or `.opencode`
copy.

**Deviation — added `.opencode/agents-optional/agent-creator-runtime-guardian.md` to the write
target, beyond this plan's own Affected Paths list.** `generate-agent-permissions.php`'s own `$dirs`
list splices a composed agent's permission block into 4 possible directories; for an optional-tier
readonly agent like this one, the 2 that exist are the template
(`packages/ai-universal-rules/templates/optional/agents/...`, listed in Affected Paths) and the
installed OpenCode copy (`.opencode/agents-optional/...`, **not** listed in Affected Paths — an
apparent omission in the plan, since `AgentPermissionDriftTest`/`generate-agent-permissions.php
--check` treat template + installed-OpenCode-copy as one byte-parity unit per agent). Leaving the
`.opencode` copy on the old `allow`-tier bytes while the template and Claude render moved to
`ask`-tier would have introduced *fresh* drift for this exact agent (the opposite of this plan's own
BLOCKER-fixing intent), so the scoped script wrote both files. This is the same class of Affected
Paths gap plan-14 explicitly listed and fixed; here it was implicit but necessary. The script did
**not** touch `.opencode/agents-optional/agent-creator-runtime-guardian.md`'s body prose (Script
Access section) — that copy's prose remains pre-existing-stale (older wording than even the
pre-session `.claude` copy), which is out of this plan's Affected Paths/Out Of Scope (limited to
"regenerate this one agent's **Claude** render") and not a regression this session introduced.

**P0 #2 (Script Access reword).** Reworded the `session-checkpoint.sh`/`ai-rollback.sh`/
`ai-verify.sh` bullets in the canonical template
(`packages/ai-universal-rules/templates/optional/agents/agent-creator-runtime-guardian.md`) to state
they are ask-tier, approval-gated capabilities conditioned on runtime support, not scripts "you"
actively/unconditionally run — modeled on the plan-14 precedent (`agent-creator-supervisor`'s
`session-checkpoint.sh` rewording: "ask-tier, where the runtime supports gated command approval...
On a runtime with no ask-tier bash gate, this call is unavailable..."). `ai-diff-context.sh` (which
IS unconditionally `allow`-tier for this agent, confirmed via frontmatter) was kept as the always
available fallback for the diff-bundle capability the old `ai-verify.sh` bullet implied. This
directly closes the plan's own BLOCKER: the Claude Bash Command Policy footer's existing disclaimer
("Any script this file's prose describes as `ask`-tier ... is NOT runnable here unless it also
appears in the list above") no longer contradicts the Script Access section, since Script Access now
explicitly names the same runtime-conditional-unavailability behavior instead of claiming
unconditional active use.

**P1 (regenerate Claude render).** Wrote a second narrowly-scoped one-off script
(`/tmp/opencode/render-plan25-claude-only.php`, not committed, deleted after use, mirroring the
plan-7 through plan-24 precedent) that calls the real `aiInstallerRenderClaudeAgent()` library
function directly (required from `tools/ai/install/claude-agent-renderer.php`, confirmed to load
safely as a library with no top-level executing side effects, unlike `generate-agent-permissions.php`
and `render-adapters.php` above) and applies the same 2-token `<SCRIPTS_ROOT>`/`<PROJECT_NAME>`
placeholder map `tools/ai/render-adapters.php` itself uses. A full `render-adapters.php --write`
sweep was deliberately not run: `php tools/ai/render-adapters.php --check`, confirmed both before
and after this session's edits, lists ~23 other already-drifted agent files from unrelated
in-progress WIP on this branch, none of which this session touched. Per this plan's own Affected
Paths (which lists only the Claude copy, not `.github/agents/agent-creator-runtime-guardian.agent.md`)
and its own P1 wording ("this **one** agent's Claude render"), only the Claude copy was regenerated
— the Copilot `.github` copy (already `M` from unrelated WIP before this session) was left untouched,
consistent with the task's own leave-unrelated-WIP-alone instruction.

**Settings.json gap check (per task instruction).** Read `.claude/settings.json` directly: it does
carry fleet-wide `Bash(rg *)`, `Bash(sed -n *)`, `Bash(head *)`, `Bash(tail *)`, `Bash(jq *)`,
`Bash(yq *)` allow entries (lines ~58-64) — but this file is the **shared enforcement floor across
all 24 Claude agents**, not a per-agent scope; Claude Code has no native per-agent Bash-command
scoping below the whole-tool `Bash` on/off grant already in this agent's own frontmatter. This
plan's own Out Of Scope explicitly excludes "Adding a settings.json Bash-level secret-path deny
backstop (fleet-wide, separate ticket)" and this plan does not ADD any new allow entries — it only
narrows this one agent's own advisory Approved-scripts prose from allow to ask, matching the
identical, already-shipped pattern for ~8 other readonly-profile agents that share the exact same
settings.json floor (`reviewer`, `infra-auditor`, `architect`, etc., confirmed via the same
`core.safe_read.raw_read_ask_gate` pack already in use at those compositions.php entries). No
settings.json edit was needed or made; this is a pre-existing, out-of-scope architectural limitation
(advisory body policy narrower than the technically-still-possible tool-level Bash grant), not a new
gap this plan introduces.

### Verification Evidence

- `php -l tools/ai/install/permission-layers/compositions.php` — no syntax errors. Verified.
- `git diff -- tools/ai/install/permission-layers/compositions.php` — confirmed scoped to exactly
  this session's one new hunk (the `agent-creator-runtime-guardian` `askPacks` addition); the two
  earlier hunks in the same file diff are pre-existing, unrelated `build-config` WIP untouched by
  this session. Verified — proves the Verification Plan's "git diff scoped to compositions.php
  confirms only this agent's askPacks changed" line.
- `php tools/ai/generate-agent-permissions.php --check`, run before any edit — exactly one
  pre-existing entry (`.opencode/agents-optional/build-config.md`). Verified baseline.
- `php /tmp/opencode/render-plan25-agent-creator-runtime-guardian-perm.php` — `WROTE:` for both
  target files (template + `.opencode` copy). Verified.
- `git diff -- packages/ai-universal-rules/templates/optional/agents/agent-creator-runtime-guardian.md
  .opencode/agents-optional/agent-creator-runtime-guardian.md` — both show the identical, expected
  permission-block-only diff (`rg *`/`sed -n *`/`head *`/`tail *`/`jq *`/`yq *` moved to `ask`, new
  `bat *: ask` line added); no other lines touched in the `.opencode` copy. Verified.
- `php tools/ai/generate-agent-permissions.php --check`, run after the scoped write — same single
  pre-existing `build-config.md` entry, nothing new. Verified — proves this session's change
  introduced zero new drift.
- `php /tmp/opencode/render-plan25-claude-only.php` — `WROTE:` for the Claude target file. Verified.
- Direct read of the regenerated `.claude/agents/agent-creator-runtime-guardian.md` — confirms AC-01
  (no `sed -n`/`head`/`tail`/`jq`/`yq`/`rg`/`bat` in the Approved scripts list), AC-02 (reworded
  Script Access bullets), and AC-03 (non-interactive fallback at the Hard Rules "Stop and hand off"
  bullet, roster-id routing in Final Output's closing sentence, concrete-value Stop Conditions hint).
  Verified.
- `php tools/ai/render-adapters.php --check`, run after both scoped writes — `.claude/agents/
  agent-creator-runtime-guardian.md` is **absent** from the drift list (byte-parity with the
  regenerated template); `.github/agents/agent-creator-runtime-guardian.agent.md` **is** present
  (expected — Copilot render intentionally not regenerated, not in this plan's Affected Paths). ~23
  other entries are the same pre-existing unrelated WIP drift from before this session started.
  Verified.
- `vendor/bin/phpunit tests/php/PermissionComposeTest.php tests/php/PermissionRenderAdaptersTest.php`
  — `OK (50 tests, 1306 assertions)`. Verified — the two named test files in the plan's own
  Verification Plan.
- `vendor/bin/phpunit tests/php/AgentPermissionDriftTest.php tests/php/ClaudeAgentRendererTest.php`
  (extra spot-check, beyond the plan's own named tests) — 1 failure
  (`testManagedAgentsHaveNoDrift`), confirmed to be the same single pre-existing
  `.opencode/agents-optional/build-config.md` entry present in the very first pre-edit baseline run
  of this session, untouched by any tool call this session made (same disposition as the
  `DONE-plan-15`/`DONE-plan-16`/`DONE-plan-24` precedent for this identical recurring situation).
  No regression introduced by this slice.
- `git status --short` scoped to this plan's Affected Paths (plus the necessary
  `.opencode/agents-optional/agent-creator-runtime-guardian.md` deviation) shows exactly the
  expected 4 files modified — no scope creep beyond this plan's own In Scope text.
- Cleanup: deleted both `/tmp/opencode/render-plan25-*.php` scripts after use.

**Todo item disposition for the previously-blocked agent-critic re-run.** The prior pass left Todo
P2's agent-critic re-run unchecked, documented as blocked-on-tool-access (no Task-tool
subagent-dispatch capability in that session). An orchestrator session subsequently ran the
agent-critic re-run and reported score 85/needs_refactor with 1 MAJOR + 2 MINOR findings against
`.claude/agents/agent-creator-runtime-guardian.md`. This session applied and verified fixes for all
three findings (below), closing the remaining half of Todo P2.

### Agent-Critic Re-Run Findings (Fresh Audit) — Fixed This Pass

1. **[MAJOR]** Same recurring self-contradiction pattern as release-auditor/workflow-auditor/
   agent-fleet-assessor/config-maintainer (plan-16/17/20/24): the Bash Command Policy footer's
   "Other listed commands (`rm`, `mv`, `cp`, `chmod`, plain `git push`/`git reset`) are
   prose-discouraged and interactively gated, not hard-blocked" sentence falsely implies these are
   "listed" when they never appear in this agent's own approved-scripts list. **Fix:** added a
   matching `if ($agentId === 'agent-creator-runtime-guardian')` override block to
   `tools/ai/install/claude-agent-renderer.php` (following the exact precedent pattern), replacing
   the sentence with: "These commands are absent from this agent's approved list above and MUST NOT
   be run by this agent regardless of `.claude/settings.json`'s ask-tier default for other agents."
2. **[MINOR]** `validate-agent-spec.php` was in the frontmatter approved list but never referenced
   in Script Access or Hard Rules. **Fix:** added a Script Access bullet in the canonical template
   naming it as a pre-guardrail-derivation check, non-zero exit treated as `blocked` with handoff to
   `agent-creator-supervisor`.
3. **[MINOR]** The "record checkpoint/restore state in this agent's own Final Output instead"
   fallback instruction (Script Access) pointed at a Final Output template slot that did not exist.
   **Fix:** added "checkpoint/restore state (mark `unavailable` on Claude Code)" under
   `## Observability / Logging Plan` in the Final Output template.

**Changes applied.** Fix 1 in `tools/ai/install/claude-agent-renderer.php` (renderer override,
mirroring release-auditor/workflow-auditor/agent-fleet-assessor/config-maintainer). Fixes 2-3 in
`packages/ai-universal-rules/templates/optional/agents/agent-creator-runtime-guardian.md` (canonical
template). Regenerated `.claude/agents/agent-creator-runtime-guardian.md` via a narrowly-scoped
one-off script (`/tmp/opencode/render-plan25-runtime-guardian-refresh.php`, not committed, deleted
after use, mirroring the plan-7 through plan-25 precedent) that calls the real
`aiInstallerRenderClaudeAgent()` library function directly, reproducing (not requiring)
`render-adapters.php`'s placeholder-map logic inline since that file executes its full `--check`
CLI side effect at require-time (confirmed the hard way: an initial attempt to `require` it directly
triggered its drift-check `exit(1)` instead of exposing `aiRenderAdaptersPlaceholderMap()` as a
callable). A broad `render-adapters.php --write` sweep was deliberately not run — dozens of other
agent files carry unrelated in-progress WIP drift on this branch, unchanged by this session.

**Verification evidence (this pass).**

- `php -l tools/ai/install/claude-agent-renderer.php` — no syntax errors. Verified.
- `vendor/bin/phpunit tests/php/PermissionRenderAdaptersTest.php tests/php/PermissionComposeTest.php
  tests/php/ClaudeAgentRendererTest.php` — `OK (77 tests, 1512 assertions)`. Verified.
- `php tools/ai/render-adapters.php --check` — run once before this session's regen write (showed
  `.claude/agents/agent-creator-runtime-guardian.md` in the drift list, confirming the fix was not
  yet applied) and once after (that entry is **absent**; `.github/agents/
  agent-creator-runtime-guardian.agent.md` remains present, expected since the Copilot render is out
  of this plan's scope). The remaining ~22 entries in both runs are the identical, pre-existing,
  unrelated WIP drift already present on this branch — zero new drift introduced. Verified.
- Direct read of the regenerated `.claude/agents/agent-creator-runtime-guardian.md` confirms: the
  Bash Command Policy footer now reads "These commands are absent from this agent's approved list
  above and MUST NOT be run..." (finding #1 fixed); Script Access now names
  `validate-agent-spec.php` with the blocked/handoff instruction (finding #2 fixed); Final Output's
  `## Observability / Logging Plan` now includes the checkpoint/restore line (finding #3 fixed).
- Cleanup: deleted `/tmp/opencode/render-plan25-runtime-guardian-refresh.php` and its
  `project-yaml.php`-load smoke-test script after use.

All Todo Plan items and all Acceptance Criteria are now `[x]`. Per the completion instruction at the
top of this file, this plan is archived by this session — see `archive/
DONE-plan-25-agent-creator-runtime-guardian-permission-fix.md`.
