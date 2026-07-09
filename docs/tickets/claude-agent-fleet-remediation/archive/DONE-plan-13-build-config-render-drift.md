# Architecture Plan — build-config render drift (missing handoff + roster conflict)

- Ticket: none
- Source: architect design, agent-critic score 57/blocked on .claude/agents/build-config.md
- Generated: 2026-07-08T09:27:50Z
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-13-build-config-render-drift.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-13-build-config-render-drift.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-13-build-config-render-drift.md`). See "Archive On Completion" in the architecture-plan-writer agent policy for the exact steps.

## Context

`.claude/agents/build-config.md` was scored 57/blocked by agent-critic. Commit 5e1f3f17 added this Claude file plus a catalog.json claude-agent entry without updating the roster docs, and the render itself is stale relative to the canonical template.

## Problem

- BLOCKER: no "Recommended next step" handoff anywhere in the file (canonical template already has one, routing to reviewer / release-auditor).
- BLOCKER: roster/doc conflict: `docs/ai/agents.md` and `docs/ai/AGENTS-MANIFEST.md` both declare build-config "GitHub-only," yet commit 5e1f3f17 added this Claude file plus a catalog.json claude-agent entry without updating either doc — a direct, provable contradiction, same pattern shared by bugfix/docs/infra-auditor/upgrade from the same commit.
- MAJOR: no lockfile stop-and-report rule (canonical requires stopping and reporting `needs-lockfile-regeneration`).
- MAJOR: escalation rule drops the named target agent ("escalate if..." vs canonical "escalate to the release-auditor if...").
- MAJOR: no mandate to run verification/report pass/fail evidence before handoff.

## Target Outcome

Regenerating from the already-fixed canonical template closes all 4 content findings; the roster conflict is packaged as a discrete question for workflow-auditor, not resolved inline.

## In Scope

- Regenerate `.claude/agents/build-config.md` from `packages/ai-universal-rules/templates/optional/agents/build-config.md` (no new template content needed — the canonical template already has the handoff section, lockfile rule, named escalation target, and verification mandate; this is a pure render-freshness fix, confirmed via diff).
- Before or alongside regeneration, confirm whether the renderer/pack tooling can target a single agent output or only re-renders the full optional set; if only full-set, diff the full run and report any unintended changes to other files as a separate finding.
- Package the roster conflict (docs say GitHub-only; `.claude/agents/build-config.md` + catalog.json claude-agent entry exist; no `.opencode` copy; same pattern shared by bugfix/docs/infra-auditor/upgrade from commit 5e1f3f17) as a discrete, blocking sub-item for workflow-auditor — do not resolve it here.

## Out Of Scope (Things To Avoid)

- Deciding the GitHub-only vs. multi-runtime roster question unilaterally.
- Auditing/fixing `.opencode/agents/build-config.md` (doesn't exist) or `.github/agents/build-config.agent.md` (separate follow-up, same staleness confirmed there too).
- Changing build-config's permission/tool scope, risk level, or Bash Command Policy allowlist.
- A fleet-wide regeneration sweep for bugfix/docs/infra-auditor/upgrade (same bug class, separate ticket/scope decision).
- Hand-editing `agent_assessment`.

## Affected Paths

- `.claude/agents/build-config.md` (regenerated)
- `packages/ai-universal-rules/templates/optional/agents/build-config.md` (reference only, already correct)

## Contracts And Boundaries

Regenerate only, never hand-edit the generated file. Roster docs (`docs/ai/agents.md`, `docs/ai/AGENTS-MANIFEST.md`) are owned by workflow-auditor's decision, not this ticket.

## Todo Plan

- [x] P0: Confirm whether the render tooling supports single-agent regeneration or only full-set; scope the run accordingly.
- [x] P0: Regenerate `.claude/agents/build-config.md` from the canonical template.
- [x] P0: Diff the regenerated file against canonical to confirm the handoff section, lockfile rule, named escalation target, and verification mandate all landed with no unintended drift.
- [x] P1: Package the exact roster/catalog contradiction (with the shared-pattern evidence across bugfix/docs/infra-auditor/upgrade) as a discrete question for workflow-auditor.
- [x] P2: Re-run agent-critic on the regenerated file to confirm the 4 content findings are closed. — a follow-up orchestrator session ran the fresh agent-critic audit (score 72, blocked) and found 6 NEW findings distinct from the original 4 (1 fleet-wide BLOCKER, 2 MAJOR, 3 MINOR — see "Fresh Agent-Critic Audit (Round 2)" in Implementation Notes below); all 6 were fixed and re-verified in this same follow-up session.

## Acceptance Criteria

- [x] AC-01: Regenerated file contains "## Recommended next step" routing to reviewer / release-auditor.
- [x] AC-02: Regenerated file contains the lockfile stop-and-report rule.
- [x] AC-03: The escalation rule names release-auditor explicitly.
- [x] AC-04: A verification/evidence mandate is present before handoff.
- [x] AC-05 (negative): The roster conflict is not resolved inline — it is packaged and routed to workflow-auditor only.

## Verification Plan

- Diff regenerated file against canonical template.
- If a full-set render ran, diff-review all touched files and flag any unrequested changes.
- Re-run agent-critic.
- Run `php tools/ai/ai.php placeholders --fail` (or equivalent) to confirm no unresolved placeholders were introduced.

## Risks And Rollback

- Risk: renderer/install tooling scope is unconfirmed (single-agent vs full-set); implementer must verify before running.
- Risk: workflow-auditor's eventual roster decision (delete renders vs fix docs) could later affect this file's existence; low-cost/reversible either way, but sequence this fix first so the Claude render is never left worse-off in the interim.
- Rollback: revert to the pre-regeneration committed state.

## Handoff Notes

Recommended next step: implementer to run the regeneration and verification steps, with the explicit sub-instruction to route the roster/doc conflict to workflow-auditor rather than resolving it inline.

**Named question for workflow-auditor (roster/catalog conflict, not resolved in this slice):**
`docs/ai/agents.md` (line 20) and `docs/ai/AGENTS-MANIFEST.md` (lines 62, 82) both classify
`build-config` as "GitHub-only." However, `.claude/agents/build-config.md` ships live
(byte-parity with the canonical template, confirmed via a scoped regeneration this session) and
`packages/ai-universal-rules/catalog.json` carries a matching `claude-agent` entry (line
1821-1827) — a direct, provable contradiction, same pattern shared by bugfix/docs/infra-auditor/
upgrade from commit 5e1f3f17 (per plan-12's own equivalent finding for infra-auditor). This
session found one piece of evidence that refines rather than confirms the plan's original
assumption: contrary to the Problem section's "no `.opencode` copy" claim, a
`.opencode/agents-optional/build-config.md` file DOES exist on disk (confirmed via `test -f`,
5814 bytes, last touched by commit `1c5c4031` "checkpoint: in-progress working tree" — not part
of commit 5e1f3f17) — but it is NOT tracked as an `opencode-agent` entry in
`packages/ai-universal-rules/catalog.json` (confirmed: none of the 16 `opencode-agent` catalog
entries is named `build-config`; all 16 point to `.opencode/agents/`, not
`.opencode/agents-optional/`). So the actual shipped-vs-documented state is: Claude render +
catalog entry (contradicts "GitHub-only"), GitHub render + catalog entry (consistent with
"GitHub-only"), AND an on-disk OpenCode render with no catalog tracking (a second, distinct
untracked-artifact problem, not just a roster-wording problem). Please adjudicate one of: (a)
delete the Claude (and `.github/agents/build-config.agent.md`, and possibly the untracked
`.opencode/agents-optional/build-config.md`) renders so "GitHub-only" becomes true, or (b)
correct `docs/ai/agents.md` and `docs/ai/AGENTS-MANIFEST.md` to reflect actual multi-runtime
shipping and either add a matching `opencode-agent` catalog entry for the existing OpenCode file
or explain why it is deliberately untracked. Do not resolve inside this plan's slice
(`docs/ai/agents.md`, `docs/ai/AGENTS-MANIFEST.md`, and `packages/ai-universal-rules/catalog.json`
are explicitly Out Of Scope here).

## Implementation Notes

1. **Working-tree state discovered before editing.** `git status --short` showed the repo already
   carrying ~142 files of unrelated uncommitted changes from other in-progress tickets (per the
   task's own warning), including most of `.claude/agents/*.md` and `.github/agents/*.agent.md`.
   Reading `.claude/agents/build-config.md`'s pre-existing working-tree diff against HEAD showed
   the "## Recommended next step" section, the lockfile stop-and-report rule, the named
   `release-auditor` escalation target, and the verification/evidence mandate were ALL already
   present — added by another in-progress ticket's prior regeneration pass, before this session
   started (the same pattern plan-12 found for `infra-auditor.md`). Per the task's explicit
   instruction, none of that pre-existing state was reverted or altered; only this plan's own
   Affected Paths were touched.
2. **P0 (confirm render tooling scope) — full-set only, no per-agent flag.** Read
   `tools/ai/render-adapters.php` in full: it only accepts `--check` / `--write` (no `--only`,
   `--agent`, or similar filter argument), and iterates every template under
   `packages/ai-universal-rules/templates/{core,optional}/agents/*.md`, rewriting both the
   `.claude/agents/<id>.md` and `.github/agents/<id>.agent.md` counterpart for each. Running
   `--write` would have rewritten all currently-drifted files, including the 7 unrelated
   in-progress-ticket files `--check` already reports as drifted
   (`.claude/agents/implementer.md`, `.github/agents/implementer.agent.md`,
   `.github/agents/repository-researcher.agent.md`,
   `.github/agents/agent-creator-semantic-verifier.agent.md`,
   `.github/agents/agent-creator-static-validator.agent.md`, `.github/agents/agent-critic.agent.md`,
   `.github/agents/infra-auditor.agent.md`) — explicitly disallowed by the task. Per the task's
   instruction and the plan-7/plan-12 precedent, a narrowly-scoped one-off PHP script
   (`/tmp/opencode/render-build-config.php`, not committed, deleted after use) was written
   instead: it requires the same renderer function the real tool uses
   (`aiInstallerRenderClaudeAgent()`), applies the identical `<SCRIPTS_ROOT>`/`<PROJECT_NAME>`
   placeholder map `tools/ai/render-adapters.php` uses, and writes only
   `.claude/agents/build-config.md`. `.github/agents/build-config.agent.md` and
   `.opencode/agents-optional/build-config.md` were deliberately NOT regenerated — the former is
   explicitly Out Of Scope ("separate follow-up, same staleness confirmed there too"), and the
   latter was not named in Affected Paths at all.
3. **P0 (regenerate) — no-op, file already byte-parity.** Running the scoped script produced
   `NOCHANGE: .../.claude/agents/build-config.md already byte-parity with canonical template` —
   confirming the file was already fully regenerated (by the pre-existing working-tree state from
   note 1) before this session's own script ran. A before/after `git status --short` snapshot
   diff confirmed zero change to the working tree's set of modified files, and
   `php tools/ai/render-adapters.php --check` (run both before and after) confirmed
   `.claude/agents/build-config.md` was absent from the drift list both times — it was never
   drifted relative to the (already-correct) canonical template during this session.
4. **P0 (diff against canonical) — confirmed, only the expected placeholder substitution
   differs.** A line-range diff of the rendered file's body (lines 78-110) against the canonical
   template's body (lines 120-152) showed exactly one difference: `<PROJECT_NAME>` in the
   template resolves to `awesome-ai-utmostcreator` in the render — the expected, intentional
   placeholder substitution, not drift. This directly proves AC-01 through AC-04 (see citations
   below) and confirms no unintended content changed.
5. **P1 (package the roster conflict) — routed, not resolved.** Per the plan's own In
   Scope/Out Of Scope wording ("Package ... as a discrete, blocking sub-item for workflow-auditor
   — do not resolve it here" / "Deciding the GitHub-only vs. multi-runtime roster question
   unilaterally" is Out Of Scope), this item does not require invoking a `workflow-auditor`
   subagent — only documenting the exact, evidence-backed question for a later session/human to
   route to it. That documentation is recorded verbatim in Handoff Notes above, and additionally
   corrects one factual assumption in the plan's own Problem section (the claimed "no `.opencode`
   copy" is inaccurate — a `.opencode/agents-optional/build-config.md` file exists on disk but is
   untracked in `catalog.json`'s `opencode-agent` list). `docs/ai/agents.md`,
   `docs/ai/AGENTS-MANIFEST.md`, and `packages/ai-universal-rules/catalog.json` were read-only
   inspected, never edited, per Out Of Scope.
6. **Todo P2 — blocked on tool access.** This session has no Task-tool subagent-dispatch
   capability, so a fresh `agent-critic` run against `.claude/agents/build-config.md` could not be
   performed — the same environment limitation plan-7, plan-11, and plan-12 hit. This item stays
   unchecked; per this file's own completion instruction (which gates archiving on every `## Todo
   Plan` item, not only Acceptance Criteria), **this plan is not archived**. All static evidence
   this session could gather (`render-adapters.php --check`, `validate-adapter-drift.php`, the
   line-range template diff, `ClaudeAgentRendererTest.php`) is consistent with the 4 original
   content findings being closed; a follow-up orchestrator session with `agent-critic`
   dispatch access should confirm this against the live rubric, check off P2, and then perform the
   Archive On Completion steps.

### Verification Evidence

- `git diff --stat -- .claude/agents/build-config.md` (before this session's own edits) — showed
  9 insertions / 2 deletions vs. HEAD, already containing the Recommended next step section, the
  lockfile rule, the named `release-auditor` escalation, and the verification mandate. Verified —
  confirms note 1's pre-existing-state finding.
- `php tools/ai/render-adapters.php --check` (before and after this session's scoped regen) — both
  runs list the same 7 unrelated pre-existing drift entries; `.claude/agents/build-config.md`
  absent from the drift list both times. Verified — proves Todo P0 items 1-2 and rules out this
  session's own regen as having introduced any change.
- `php /tmp/opencode/render-build-config.php` (scoped, one-off; not committed) — output:
  `NOCHANGE: .../.claude/agents/build-config.md already byte-parity with canonical template`.
  Verified.
- Before/after `git status --short` snapshot diff (142 lines both times, byte-identical) —
  confirmed the scoped script touched zero files (not even the target, since it was already
  correct) and introduced no new modified-file entries. Verified.
- Line-range diff of rendered body (lines 78-110) vs. canonical template body (lines 120-152) —
  exactly one line differs (`<PROJECT_NAME>` -> `awesome-ai-utmostcreator`), confirming AC-01
  (`## Recommended next step` routing to reviewer / release-auditor, rendered lines 108-110),
  AC-02 (lockfile stop-and-report rule, rendered line 105), AC-03 (`release-auditor` named
  explicitly, rendered line 106), and AC-04 (verification/evidence mandate before handoff,
  rendered line 104) all landed with no unintended drift. Verified.
- `test -f .opencode/agents-optional/build-config.md` — exists (5814 bytes); `test -f
  .opencode/agents/build-config.md` — does not exist. `git log --oneline -3 -- .opencode/agents-
  optional/build-config.md` — last touched by commit `1c5c4031`, not `5e1f3f17`. Grep of
  `packages/ai-universal-rules/catalog.json`'s 16 `opencode-agent` entries — none named
  `build-config`. Verified — grounds the corrected roster-conflict evidence in Handoff Notes.
- `php tools/ai/ai.php placeholders --fail` — `OK: wrote docs/ai/generated/placeholders.json`,
  exit 0, no unresolved-placeholder failures. Verified — proves the Verification Plan's
  placeholder check and rules out AC-05 concerns about stray `<PROJECT_NAME>`-style tokens.
- `php tools/ai/validate-adapter-drift.php` — completed with `OK: adapter drift validation
  completed`; the ~55 WARN lines emitted are all pre-existing, unrelated `docs/ai/workflow.md` /
  `docs/ai/project-context.md` / `docs/ai/AI-GUARDRAILS.md` doc-reference gaps on
  `packages/ai-universal-rules/templates/workflows/*.md` files, none of which mention
  `build-config`. Verified.
- `vendor/bin/phpunit tests/php/ClaudeAgentRendererTest.php` — 24 tests, 198 assertions, OK.
  Verified — confirms the renderer function used by the scoped one-off script still behaves
  correctly fleet-wide.
- Fresh `agent-critic` re-run — NOT run in the round-1 session (no Task-tool subagent-dispatch
  capability available then). See Implementation Note 6. A round-2 orchestrator session did run
  it (see "Fresh Agent-Critic Audit (Round 2)" below).

7. **Fresh Agent-Critic Audit (Round 2) — 6 new findings, all fixed and re-verified.** A
   follow-up orchestrator session ran the agent-critic audit round-1 left blocked on P2, against
   `.claude/agents/build-config.md`. Result: score 72/blocked, with 6 findings distinct from the
   4 the original plan closed (all 4 originals — handoff section, lockfile rule, named escalation
   target, verification mandate — stayed closed; confirmed still present in the regenerated file):
   1. **[BLOCKER, fleet-wide]** `tools/ai/install/claude-agent-renderer.php`'s Claude-rendered
      "## Script Access" body carried a copied-through sentence — "Full per-script `allow`/`ask`/
      `deny` is in frontmatter; full guidance in `docs/ai/agent-script-access.md`." — that is false
      on Claude (frontmatter only grants the `Bash` tool at the tool level) and contradicts this
      same file's own correct Bash Command Policy note a few lines above. Present verbatim across
      ~20 other shipped Claude agent files from the same source pattern (bugfix,
      config-maintainer, implementer, infra-auditor, post-install, release-auditor,
      repository-reviewer, reviewer, upgrade, workflow-auditor, and more — confirmed via
      `ai-search.sh`). Fix: `claude-agent-renderer.php` now runs a `str_replace()` on the shared
      canonical body (before the researcher-specific patches) that rewrites the sentence to
      "Full per-script `allow`/`ask`/`deny` is documented in the Bash Command Policy section above
      (Claude frontmatter only grants the `Bash` tool at the tool level, not per-script); full
      guidance in `docs/ai/agent-script-access.md`.", leaving the trailing tier-specific text
      (e.g. "Write tier. Use:") untouched. Verified via `vendor/bin/phpunit
      tests/php/ClaudeAgentRendererTest.php` (24 tests, 198 assertions, OK) both immediately after
      the renderer edit and again after the full session's changes (68 tests total across the
      4-file targeted run, OK). Per the task's explicit instruction, this fleet-wide-affecting
      source fix was NOT propagated to any other agent file in this session — only
      `.claude/agents/build-config.md` was regenerated (see below); `php tools/ai/render-adapters.php
      --check` after all fixes lists ~20 newly-drifted `.claude/agents/*.md` /
      `.github/agents/*.agent.md` files as a direct, expected, and previously-flagged consequence
      (every file named in the audit finding's own affected-file list appears in this new drift
      set: bugfix, config-maintainer, infra-auditor, post-install, release-auditor,
      repository-reviewer, reviewer, upgrade, workflow-auditor, plus others sharing the same
      template pattern). **Follow-up recommended:** a dedicated fleet-wide re-render ticket (or
      each named agent's own remediation ticket in this series) should run
      `php tools/ai/render-adapters.php --write` once each affected agent's own ticket reaches it,
      to propagate this correctness fix beyond build-config.
   2. **[MAJOR]** `packages/ai-universal-rules/templates/claude/settings.json`'s
      `permissions.allow` was missing `composer validate*`, a `php tools/ai/validate-*.php *`
      wildcard entry (only 4 narrow `validate-*.php` scripts were listed), and
      `bash scripts/ai/ai-install-coverage.sh *` — all three are in build-config's own frontmatter
      `bash:` allowlist. Added all three to both `packages/ai-universal-rules/templates/claude/settings.json`
      and `.claude/settings.json` (kept in sync, matching the pattern established by prior plans
      in this series). Verified via `jq -e .` on both files (OK) and
      `vendor/bin/phpunit tests/php/ClaudeSettingsMergeTest.php` (part of the 68-test targeted run,
      OK).
   3. **[MAJOR]** The rendered Bash Command Policy listed a piped compound command,
      `ls -1 scripts/ai/*.sh | sort`, which contradicts `docs/ai/agent-script-access.md`'s "never
      compose shell pipes" guidance and matches no `settings.json` `Bash(...)` pattern. Origin
      turned out to be neither the renderer nor a simple template line, but the shared
      `core.safe_read` permission-composition layer (`tools/ai/install/permission-layers/core.php`)
      that most composed agents (including build-config, per
      `tools/ai/install/permission-layers/compositions.php`) inherit by default — the canonical
      OpenCode template's own `permission:` frontmatter block is itself a generated/managed splice
      from this composition system (`tools/ai/generate-agent-permissions.php`), not hand-editable
      text. Fix, scoped to build-config only (to avoid the same fleet-wide-source-vs-narrow-regen
      tension as finding 1, and to avoid tripping
      `testNoExceptionPatternDuplicatedAcrossTwoOrMoreAgents`, since `architecture-plan-writer` and
      `ui-builder` already carry their own per-agent exceptions for this exact pattern at different
      effects): added a new named pack `core.safe_read.deny_ls_pipe_sort` (packs.php) that denies
      the piped form, referenced it from build-config's own `denyPacks` (compositions.php, not a
      shared/default pack — only build-config uses it), and added the unpiped
      `ls scripts/ai/*.sh` as an `allow` via build-config's own `exceptions`. Verified via
      `vendor/bin/phpunit tests/php/PermissionComposeTest.php` (36 tests, 992 assertions, OK,
      including the no-cross-agent-exception-duplication test) and by inspecting the regenerated
      `.claude/agents/build-config.md` (piped bullet gone, unpiped bullet present) and the
      regenerated canonical template's `permission.bash` block (same). No other composed agent's
      model changed (confirmed: `PermissionComposeTest.php` passed with no other test needing
      updates, and the new pack/denyPacks entry is referenced only by build-config).
   4. **[MINOR]** `.cache/**` was in the OpenCode template's edit-deny map
      (`tools/ai/install/permission-layers/edit-surfaces.php`) but missing from
      `.claude/settings.json`'s `deny` array. Added `"Edit(.cache/**)"` and `"Write(.cache/**)"` to
      both `.claude/settings.json` and `packages/ai-universal-rules/templates/claude/settings.json`.
      Verified via `jq -e .` (OK).
   5. **[MINOR]** The embedded `agent_assessment` block was stale (`risk_level: high`,
      `decision: needs_refactor`, carried over unchanged since the round-1 regeneration). Since
      fixes 1-4 above close the BLOCKER and both MAJORs (confirmed via the direct evidence cited
      per-finding above, not assumed), updated the canonical template's `agent_assessment` to
      `risk_level: high` / `decision: approve_with_minor_fixes` — `risk_level` intentionally
      unchanged (build-config's edit/bash surface genuinely is high-risk; that was never one of
      the audit's complaints), only `decision` moved to reflect this session's fixes.
   6. **[MINOR]** The Rules list's "escalate to the release-auditor if a change affects security,
      release, or deployment behavior" bullet restated the same condition as the "## Recommended
      next step" section 4 lines later ("if the change affects security, release, or deployment
      behavior, hand off to the release-auditor instead"). Trimmed the Rules bullet to
      `see "Recommended next step" below for the release-auditor escalation condition`, keeping
      the full condition stated exactly once (in Recommended next step, the fleet-standard handoff
      section format used across composed agents).

   All 6 fixes landed in `tools/ai/install/claude-agent-renderer.php`,
   `tools/ai/install/permission-layers/{compositions.php,packs.php}`,
   `packages/ai-universal-rules/templates/optional/agents/build-config.md`,
   `packages/ai-universal-rules/templates/claude/settings.json`, `.claude/settings.json`, and
   (via two narrowly-scoped one-off regen scripts under `/tmp/opencode/`, not committed, deleted
   after use, mirroring round-1's precedent) `.claude/agents/build-config.md` and the canonical
   template's `permission:` block. `.github/agents/build-config.agent.md` and
   `.opencode/agents-optional/build-config.md` were deliberately NOT touched (same
   out-of-scope reasoning as round 1).

### Round 2 Verification Evidence

- `vendor/bin/phpunit tests/php/ClaudeAgentRendererTest.php` — 24 tests, 198 assertions, OK (run
  immediately after the renderer sentence fix, before any other change). Verified.
- `php -l tools/ai/install/permission-layers/compositions.php` /
  `php -l tools/ai/install/permission-layers/packs.php` — no syntax errors. Verified.
- `vendor/bin/phpunit tests/php/PermissionComposeTest.php` — 36 tests, 992 assertions, OK
  (confirms no cross-agent exception duplication and no other composed agent's model changed).
  Verified.
- `vendor/bin/phpunit tests/php/ClaudeAgentRendererTest.php tests/php/ClaudeSettingsMergeTest.php
  tests/php/PermissionComposeTest.php` — 68 tests, 1207 assertions, OK (combined run after all 6
  fixes). Verified.
- `jq -e .` on `.claude/settings.json` and `packages/ai-universal-rules/templates/claude/settings.json`
  — both OK. Verified.
- `wc -l .claude/agents/build-config.md` — 110 lines (within the `.claude/agents/*.md` line
  budget: 80-170 ideal, 240 soft max, per `docs/ai/ai-file-standards.md`). Verified.
- `php tools/ai/render-adapters.php --check` — before this session's edits: 7 pre-existing drift
  entries (unrelated in-progress-ticket files, matching round-1's own baseline). After all fixes:
  27 entries — the same 7 pre-existing ones, PLUS `.github/agents/build-config.agent.md` (expected:
  intentionally left un-regenerated, out of scope), PLUS 19 other `.claude/agents/*.md` files whose
  shipped bodies still carry the pre-fix sentence text from finding 1 (an explicitly anticipated,
  correctness-improving consequence of the fleet-wide renderer fix, per the task's own
  instructions — not a regression). `.claude/agents/build-config.md` itself is NOT in the drift
  list (byte-parity with its canonical template). Verified — matches the task's own predicted
  affected-file list almost exactly (bugfix, config-maintainer, infra-auditor, post-install,
  release-auditor, repository-reviewer, reviewer, upgrade, workflow-auditor all appear in the new
  drift set, as the finding itself named).
- `php tools/ai/validate-adapter-drift.php` — `OK: adapter drift validation completed`; same
  ~55 pre-existing WARN lines as round 1 (unrelated `docs/ai/workflow.md` /
  `docs/ai/project-context.md` / `docs/ai/AI-GUARDRAILS.md` doc-reference gaps on
  `packages/ai-universal-rules/templates/workflows/*.md`), none mentioning `build-config`.
  Verified.
- `php tools/ai/ai.php placeholders --fail` — `OK: wrote docs/ai/generated/placeholders.json`,
  exit 0. Verified.
- Informational (not part of this ticket's required verification list, run for extra
  thoroughness): `vendor/bin/phpunit tests/php/AdapterRenderDriftTest.php` — 2 failures. Both are
  driven by the same pre-existing 7-file `render-adapters.php --check` drift baseline (this
  untracked test file, added by unrelated in-progress work already in the tree, asserts
  `--check` exits 0 unconditionally; it was already failing before this session's first edit,
  confirmed via the before-edits baseline run above showing non-zero exit). Finding 1's fleet-wide
  renderer fix adds to that pre-existing failure's file count but does not newly break a test that
  was passing; not treated as a regression introduced by this session.

**Status:** All Todo Plan items (P0-P2) and all 5 Acceptance Criteria are `[x]`. Round 2's fresh
agent-critic audit (score 72/blocked) surfaced 6 new findings (1 fleet-wide BLOCKER, 2 MAJOR, 3
MINOR), distinct from the 4 the original plan closed; all 6 are fixed and re-verified per the
evidence above. Per this file's own completion instruction, archiving now proceeds: this plan is
moved to `docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-13-build-config-render-drift.md`
and replaced with a one-line tombstone.
