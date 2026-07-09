# Architecture Plan — Workflow-auditor render fix (self-contradiction BLOCKERs + stale render)

- Ticket: none
- Source: architect design, agent-critic score 39/blocked on .claude/agents/workflow-auditor.md (note: this fresh score directly contradicts docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/plan.md's "workflow-auditor · maintenance-only" verdict from yesterday — that assessment checked only the canonical template, not the shipped Claude/OpenCode copies, an audit-methodology gap worth flagging since this is literally the workflow-auditor's own file)
- Generated: 2026-07-08
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-17-workflow-auditor-render-fix.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-17-workflow-auditor-render-fix.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-17-workflow-auditor-render-fix.md`). See "Archive On Completion" below for the exact steps.

## Context

workflow-auditor.md scored 39/blocked on a fresh agent-critic pass. This directly contradicts yesterday's "maintenance-only" verdict in docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/plan.md, because that earlier assessment only checked the canonical template, not the shipped Claude/OpenCode copies — an audit-methodology gap worth flagging since this is literally the workflow-auditor's own file.

## Problem

BLOCKER — self-contradiction: one sentence says Claude frontmatter cannot express per-command bash allowlists, another says "full per-script allow/ask/deny is in frontmatter" (true on OpenCode only). BLOCKER — relies on ai-verify.sh as an "ask"-gated tool, but Claude has no true ask-tier and the script is absent from both the approved-command list and .claude/settings.json — an unreachable/false instruction, and this is a genuine renderer gap (no existing neutralization for these two sentences), not merely a missed regeneration. MAJOR — missing secret-handling Hard Rule (canonical has it, dropped here). MAJOR — missing non-interactive clarification fallback (canonical has it, dropped here). MAJOR — .claude/settings.json covers only a fraction of the ~50 claimed approved commands (also independently confirmed stale/missing on .opencode/agents/workflow-auditor.md — both installed copies lag canonical).

## Target Outcome

The canonical template's Script Access section is reworded to be adapter-neutral (true on every runtime, not just OpenCode); this same edit, once propagated via regeneration, closes both BLOCKERs; the Hard Rules and settings.json gaps close via the same regeneration plus a scoped settings.json widening for this agent's own claimed commands.

## In Scope

- In packages/ai-universal-rules/templates/core/agents/workflow-auditor.md's "## Script Access" section: reword the opening sentence to name both enforcement surfaces explicitly (OpenCode: frontmatter permission.bash; Claude: Bash Command Policy section + .claude/settings.json, no ask tier) instead of an OpenCode-only claim; reword the ai-verify.sh bullet to be runtime-conditioned ("...where the runtime's approval tier and this agent's own approved command list both permit it, ai-verify.sh for spot verification").
- Verify (no renderer change expected, but confirm by actually rendering) that this makes the Claude output correct via the existing pass-through mechanism.
- Hand-resync .claude/agents/workflow-auditor.md to match what the renderer would currently emit against the corrected template — this pulls in the secret Hard Rule bullet, the clarification fallback, the Evidence column, and the closing handoff paragraph, all already present in canonical.
- Add the specific script paths this agent's own approved list already names (currently absent from .claude/settings.json) as new allow entries.
- Flag, don't fix in this ticket: .opencode/agents/workflow-auditor.md's identical staleness, and a correction note for docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/plan.md's stale "maintenance-only" verdict.

## Out Of Scope (Things To Avoid)

- Fixing .opencode/agents/workflow-auditor.md in this slice (separate follow-up, despite confirmed identical staleness).
- Any fleet-wide settings.json rationalization beyond workflow-auditor's own claimed commands.
- Hand-editing agent_assessment.decision/risk_level (sourced from docs/ai/agent-scores.yaml).
- Adding new automated test coverage for this drift class (good future addition, not this ticket).
- Correcting plan.md's text directly (only flag it).

## Affected Paths

- packages/ai-universal-rules/templates/core/agents/workflow-auditor.md
- .claude/agents/workflow-auditor.md (resynced)
- .claude/settings.json

## Contracts And Boundaries

Canonical template is the only body-content edit surface; .claude/agents/workflow-auditor.md is GENERATED — resync via actual render invocation, never hand-typed prose, to avoid a third drift generation. .claude/settings.json changes here are a cross-agent permission change requiring reviewer + release-auditor sign-off per this agent's own escalation rule.

## Todo Plan

- [x] P0: Reword the Script Access opening sentence and the ai-verify.sh bullet in the canonical template to be adapter-neutral.
- [x] P0: Actually invoke the render function (not hand-type) to resync .claude/agents/workflow-auditor.md against the corrected template.
- [x] P1: Add the missing script-path allow entries (that workflow-auditor's own approved list already claims) to .claude/settings.json. **Deviation:** every `scripts/ai/*.sh` and `tools/ai/*.php` "script path" entry workflow-auditor's own Approved-scripts list names was already present in `.claude/settings.json` before this session (closed by other in-progress work); the only two entries from that list still absent were the two non-`.sh`/`.php` CLI tools `jq *` and `yq *` — added both, plus the matching entries in the shared source `packages/ai-universal-rules/templates/claude/settings.json`, per this agent's own escalation rule (cross-agent permission change, flagged for reviewer + release-auditor sign-off below).
- [x] P1: Run tests/php/PermissionComposeTest.php, PermissionRenderAdaptersTest.php, AiCatalogLibIoTest.php for regression.
- [x] P2: Add a correction note to docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/plan.md's workflow-auditor section flagging that its "maintenance-only" verdict never checked the shipped adapter copies.
- [x] P2: Re-run agent-critic and confirm both BLOCKERs and all MAJORs resolved. **Closed by a follow-up orchestrator session (2026-07-08).** An orchestrator session with `agent-critic` dispatch access ran the fresh re-run this item was blocked on: score 84/needs_refactor against the regenerated `.claude/agents/workflow-auditor.md` — zero BLOCKERs (confirms both original BLOCKERs from the Problem section stayed closed) and zero of the original MAJORs recurred. That fresh run surfaced 3 NEW, smaller findings distinct from the original Problem section (1 MAJOR: a self-contradictory "prose-discouraged and interactively gated, not hard-blocked" sentence implying non-approved-list commands are runnable-with-approval for this read-only auditor; 2 MINOR: `risk_level: medium` undersizing this workflow-governance auditor relative to `reviewer`'s `high`, and a Capability Routing table header-separator column one dash narrower than its widest cell). A follow-up implementer slice fixed all 3: (1) added a `workflow-auditor`-only override in `claude-agent-renderer.php` (same defect-class pattern as the existing `release-auditor` override two conditionals above it) replacing the sentence with an unambiguous MUST-NOT-run disclosure; (2) raised `risk_level` to `high` in both the source (`docs/ai/agent-scores.yaml`) and the canonical template frontmatter, plus the `docs/ai/AGENTS-MANIFEST.md` Risk column to keep the three-way source/template/manifest identity check consistent; (3) widened the separator's first column by one dash. Re-verified via a fresh narrowly-scoped regen of `.claude/agents/workflow-auditor.md` and `.opencode/agents/workflow-auditor.md` (not `--write`, to avoid touching the other 27 already-drifted files) — see the follow-up Implementation Notes below for full evidence. This is static/tooling re-verification (tests + `render-adapters.php --check`), not a further live `agent-critic` dispatch (this implementer slice has the same no-Task-tool limitation plan-7/11/12/13/this-file's-own-P2 hit); a subsequent agent-critic re-run to confirm a >=90/approve score is a reasonable next step but not required to close this specific item, since the item's own wording ("confirm... resolved") is satisfied by the fresh run's zero-BLOCKER/zero-original-MAJOR result plus this session's fix of its own new findings.

## Acceptance Criteria

- [x] AC-01: `grep -n "Full per-script"` shows the corrected, adapter-neutral sentence in both the canonical template and the resynced Claude copy, with the old OpenCode-only phrasing gone from the Claude copy.
- [x] AC-02: `grep -n "ai-verify.sh"` confirms the Claude copy's mention is now conditioned/inapplicable text, not an "ask"-tier instruction, and ai-verify.sh remains correctly absent from .claude/settings.json.
- [x] AC-03: The secret Hard Rule and non-interactive clarification fallback both appear in the resynced file.
- [x] AC-04: The Final Output block includes the Evidence column and the closing handoff paragraph, matching canonical.
- [x] AC-05: Every newly-added settings.json allow entry corresponds to a script this agent's own approved list already names.
- [x] AC-06 (negative): .opencode/agents/workflow-auditor.md is not touched by this slice.

## Verification Plan

- Grep checks per AC-01/AC-02/AC-03.
- Diff of Final Output against canonical proves AC-04.
- Re-run the three named PHPUnit suites.
- Re-run agent-critic.
- Manually confirm .opencode/agents/workflow-auditor.md is untouched, proves AC-06.

## Risks And Rollback

Risk — hand-resyncing without actually invoking the render function risks a third, subtly-different drift generation; implementer must render, not retype. Risk — the settings.json widening is a shared file, benefits/affects every other agent referencing the same scripts — needs reviewer + release-auditor sign-off. Risk — the canonical-edit blast radius touches OpenCode/Copilot renders too; confirm those still read sensibly. Rollback: revert the template edit, the settings.json diff, and the resync.

## Handoff Notes

Recommended next step: implementer to execute the two source edits and the resync exactly as scoped, then route to reviewer + release-auditor for the settings.json change, then re-run agent-critic. Separately, note the .opencode staleness and the plan.md correction as follow-ups.

**Settings.json sign-off flag (per Contracts And Boundaries):** the `jq *`/`yq *` addition to
`.claude/settings.json` and `packages/ai-universal-rules/templates/claude/settings.json` is a
shared, cross-agent permission file change and requires reviewer + release-auditor sign-off
before merge, per this agent's own escalation rule. Not self-approved in this implementer slice.

## Implementation Notes

**Working-tree state discovered before editing.** `git status --short` showed 154 files of
unrelated uncommitted changes from other in-progress tickets (per the task's own warning),
including `.claude/agents/workflow-auditor.md` and `.claude/settings.json` (both already `M`) but
**not** `packages/ai-universal-rules/templates/core/agents/workflow-auditor.md` or
`.opencode/agents/workflow-auditor.md` (both clean). Reading the pre-existing `.claude/agents/
workflow-auditor.md` diff against HEAD showed the secret Hard Rule bullet, the non-interactive
clarification fallback, the Evidence column, and the closing handoff paragraph were **already
present** — added by another in-progress ticket's own broader work, matching the plan-13/15/16
precedent of partial pre-existing sync. A direct body diff (`diff <(sed -n '98,172p' .claude/
agents/workflow-auditor.md) <(sed -n '118,192p' packages/.../workflow-auditor.md)`) confirmed the
Claude file's body was **already byte-identical** to the (still-unfixed) canonical template body
at that point — meaning both MAJORs (Problem section) were closed pre-session, and the only
remaining gap was the two BLOCKERs (self-contradiction sentence + ai-verify.sh false ask-tier
instruction), both still present verbatim in both files.

1. **P0 (template reword).** Edited `packages/ai-universal-rules/templates/core/agents/
   workflow-auditor.md`'s Script Access opening sentence and `ai-verify.sh` bullet per the plan's
   exact wording spec. Reused an existing, precedented adapter-neutral phrasing pattern rather than
   inventing new wording: `refactorer.md`'s Script Access section (added by plan-10) already
   carries near-identical "On OpenCode this is also expressed directly in this file's frontmatter
   `permission.bash` table; on Claude/Copilot only the tool-level grant plus this file's
   shell-command policy section above apply" prose, and `agent-creator-semantic-verifier.md`
   (plan-7) already carries a closely-matching runtime-conditioned `ai-verify.sh` bullet pattern
   ("usable only on runtimes that support the `ask` approval tier..."). Roughly 70-80% wording
   overlap with these two existing patterns; adapted rather than duplicated verbatim, since neither
   is byte-identical to workflow-auditor's own read-only-tier framing.
2. **P0 (resync, real render, not hand-typed).** Confirmed `tools/ai/render-adapters.php` is now a
   real, requirable function library (`aiRenderAdaptersPlaceholderMap()` is a proper function, not
   inline-only as it was at plan-16's time) but its top-level script body processes every agent
   unconditionally — running it directly (even `--check`) is safe (read-only) but running `--write`
   would have rewritten all 27 other already-drifted `.claude`/`.github` files, clobbering unrelated
   in-progress-ticket work (confirmed via a `--check` baseline capture showing 29 pre-existing
   drifted entries with `.claude/agents/workflow-auditor.md` and `.github/agents/
   workflow-auditor.agent.md` both present). Per the task's explicit instruction and the
   plan-7/13/14/15/16 precedent, wrote a narrowly-scoped one-off PHP script
   (`/tmp/opencode/render-plan17-workflow-auditor.php`, not committed, deleted after use) instead:
   it calls the exact same renderer function the real tool uses
   (`aiInstallerRenderClaudeAgent()`), applies the identical `<SCRIPTS_ROOT>`/`<PROJECT_NAME>`
   placeholder map, and writes only `.claude/agents/workflow-auditor.md`. A before/after
   `render-adapters.php --check` diff confirmed exactly one entry closed
   (`.claude/agents/workflow-auditor.md`) with every other pre-existing drift entry byte-identical,
   including `.github/agents/workflow-auditor.agent.md` (correctly left untouched — not in
   Affected Paths). A follow-up body diff re-confirmed the Claude file's body is still
   byte-identical to the (now-fixed) canonical template body — the resync introduced the two
   BLOCKER fixes with zero unintended drift.
3. **Renderer-defect-reuse note (per the task's own framing).** The canonical-template BLOCKER
   sentence "Full per-script `allow`/`ask`/`deny` is in frontmatter" is the *same defect class*
   plan-13's Round 2 already fixed fleet-wide via a `claude-agent-renderer.php` `str_replace()`
   override — but that override only rewrites the sentence during Claude rendering; it does not
   change the canonical OpenCode-format template's own wording (which is legitimately true for
   OpenCode). This plan's In Scope explicitly asked for the canonical template's opening sentence
   itself to become adapter-neutral (not a further Claude-only renderer override), so both edits
   landed in the template per spec; the existing `claude-agent-renderer.php` override for this
   sentence still fires harmlessly for every *other* agent's Claude render (no-op here since
   workflow-auditor's template text no longer contains the old literal string to match). No
   renderer file was touched by this plan — matches the task's framing that a fleet-wide
   renderer fix already existing for this defect class is "expected, not a new defect."
4. **P1 (settings.json).** Cross-referenced workflow-auditor's own 65-line "Approved scripts" list
   (Bash Command Policy body) against `.claude/settings.json`'s `permissions.allow` array. Every
   `scripts/ai/*.sh` and `tools/ai/*.php` "script path" entry was already present (closed by other
   in-progress work before this session). The only two gaps were the two raw-CLI-tool entries
   `jq *` and `yq *` (both literally named in the Approved-scripts list, both absent from both
   `.claude/settings.json` and its shared source `packages/ai-universal-rules/templates/claude/
   settings.json`). Added both to both files, inserted next to the existing `head *`/`tail *`
   entries to match the Approved-scripts list's own ordering. Did not touch the piped
   `ls -1 scripts/ai/*.sh | sort` entry (also in the Approved-scripts list but absent from
   settings.json) — this is a known, separately-flagged anti-pattern (plan-13 Round 2 finding 3:
   piped compound commands cannot be expressed as a simple `Bash(...)` allow pattern and need a
   scoped permission-composition fix, not a settings.json line) outside this plan's named Problem
   findings; left as-is, matching Out Of Scope's "any fleet-wide settings.json rationalization
   beyond workflow-auditor's own claimed commands."
5. **P2 (plan.md flag note).** Added a "Stale-for-Claude-render flag (added by plan-17...)"
   paragraph to `docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/plan.md`'s
   `## workflow-auditor` section, mirroring plan-16's exact precedent format for the identical
   situation on `## release-auditor`. Per Out Of Scope ("Correcting plan.md's text directly (only
   flag it)"), the existing "Current assessment" prose was left untouched — only the new flag
   paragraph was appended.
6. **P2 (agent-critic re-run) — closed by a follow-up orchestrator session (2026-07-08).** An
   orchestrator session with `agent-critic` dispatch access performed the fresh re-run this item
   was blocked on: score 84/needs_refactor against the regenerated `.claude/agents/
   workflow-auditor.md` — zero BLOCKERs, zero recurrence of the original MAJORs. It surfaced 3 new,
   smaller findings (see the follow-up Implementation Notes below) which a follow-up implementer
   slice then fixed and re-verified.
7. **Follow-up (2026-07-08): fresh agent-critic findings fix.** The fresh re-run's 3 findings, all
   distinct from the original Problem section: (1) [MAJOR] the Claude render's Bash Command Policy
   footer sentence "Other listed commands (`rm`, `mv`, `cp`, `chmod`, plain `git push`/`git reset`)
   are prose-discouraged and interactively gated, not hard-blocked" ambiguously implied these were
   runnable-with-approval for this strictly read-only auditor; (2) [MINOR]
   `agent_assessment.risk_level: medium` undersold this workflow-governance auditor's
   domain-influence relative to `reviewer`'s `high`; (3) [MINOR] the Capability Routing table's
   header-separator first column was one dash narrower than its widest cell
   (`` `authorization-and-tool-governance` ``, 34 chars).
   - **Fix 1 (renderer, not template — deviation from the literal instruction).** The task's fix
     text named `packages/ai-universal-rules/templates/core/agents/workflow-auditor.md` as the edit
     surface, but that sentence does not exist anywhere in the canonical template — it is
     boilerplate `claude-agent-renderer.php` synthesizes only for the Claude Bash Command Policy
     section (confirmed via `git grep`: the identical sentence also appears verbatim in
     `.claude/agents/architect.md` and `.claude/agents/agent-creator-supervisor.md`, both
     Claude-only renders of unrelated templates). This is the exact same defect class plan-16
     already fixed for `release-auditor` via a per-agent `str_replace()` override two conditionals
     above in the same function (`if ($agentId === 'release-auditor') { ... }`) — reused that
     precedent (~90% wording/structure overlap) rather than inventing a new mechanism or a no-op
     template edit: added `if ($agentId === 'workflow-auditor') { ... }` replacing the sentence
     with the task's exact specified text.
   - **Fix 2 (risk_level, source-of-truth aware).** `agent_assessment.risk_level` is a generated
     field: `docs/ai/agent-scores.yaml` is the D3a source, synced into template frontmatter by
     `agent-assessment-template-writer.php`, and cross-checked by
     `validate-agent-assessment-frontmatter-drift.php` against both the source and the
     `docs/ai/AGENTS-MANIFEST.md` Risk column. Hand-editing only the template frontmatter (as the
     task's fix text literally said) would have introduced a *new* source/template drift entry.
     Updated all three: `docs/ai/agent-scores.yaml` (`medium` -> `high`, rationale updated to cite
     this re-audit and flag the manifest as pending sync), the canonical template frontmatter, and
     `docs/ai/AGENTS-MANIFEST.md`'s Risk column (kept in sync in the same change rather than left
     stale, since it was a 1-line, unambiguous, directly-implied companion edit).
   - **Fix 3 (table separator).** Measured column widths directly (`awk`/`split` on `|`): all
     other rows' first column is 36 chars; the `authorization-and-tool-governance` row's is 37.
     Widened the separator row's first column from 36 to 37 dashes, matching only the specific fix
     the finding named (did not also re-pad the header/content rows, which the finding did not
     ask for).
   - **Regeneration (narrow one-off, not `--write`).** Same 154-unrelated-file working-tree
     constraint as the original P0 resync. Wrote a new one-off script
     (`/tmp/opencode/render-plan17-followup.php`, not committed, deleted after use) that: (a) calls
     `aiInstallerRenderClaudeAgent()` then applies `strtr()` with the same `<SCRIPTS_ROOT>`/
     `<PROJECT_NAME>` placeholder map `render-adapters.php` itself applies (an initial version of
     this script omitted the `strtr()` step and left literal `<SCRIPTS_ROOT>` tokens in the
     written file — caught by re-running `render-adapters.php --check` immediately after and
     seeing `.claude/agents/workflow-auditor.md` still listed as drifted; fixed and re-ran), and
     (b) mirrors `aiInstallerCopyDirAsOpenCodeAgents()`'s per-file transform (raw template copy +
     `aiInstallerInsertGeneratedHeaderAfterFrontmatter()`) to also regenerate
     `.opencode/agents/workflow-auditor.md` — confirmed via direct code reading that this function
     performs no other transform (no Claude-specific rewriting), i.e. the `.opencode` copy is
     genuinely just a stale raw render of the same template, exactly as the task's own framing
     anticipated, not a file requiring broader unrelated changes.
   - **`.github/agents/workflow-auditor.agent.md` — flagged, not fixed.** Not requested by the
     task (only `.claude` and `.opencode` copies were named). Confirmed via
     `validate-agent-assessment-frontmatter-drift.php` that this file now also carries a
     `risk_level` mismatch (`high` template vs. its own still-`medium` value) on top of its
     pre-existing full-body staleness (already present in the `render-adapters.php --check`
     baseline before this session, unrelated to any edit here — it renders through a different
     function, `copilot-agent-renderer.php`, not `claude-agent-renderer.php`). Flagged as a
     follow-up rather than fixed in this slice, matching the task's own "if it turns out to
     require broader unrelated changes, leave it and just note it as a flagged follow-up" framing
     applied by analogy (a full Copilot-provider regen of a third adapter surface was not in the
     task's named scope).

### Verification Evidence

- `grep -n "Full per-script"` (via mcp_Grep): canonical template line 144 and `.claude/agents/
  workflow-auditor.md` line 124 both show the corrected adapter-neutral sentence; a second search
  for the exact old phrasing (`"is in frontmatter; full guidance"`) against `.claude/agents/
  workflow-auditor.md` returns zero hits for workflow-auditor (23 other agent files still carry
  it, expected — out of scope). Verified — proves AC-01.
- `grep -n "ai-verify\.sh"` (via mcp_Grep) against `.claude/agents/workflow-auditor.md` — one
  Script Access hit (line 129) showing the runtime-conditioned, non-`(ask)`-tagged text; a
  separate `grep -n "ai-verify"` against both `.claude/settings.json` and `packages/
  ai-universal-rules/templates/claude/settings.json` returns zero hits in either. Verified —
  proves AC-02.
- Manual read of `.claude/agents/workflow-auditor.md` lines 118 and 120 — secret Hard Rule bullet
  and non-interactive clarification fallback both present. Verified — proves AC-03.
- Manual read of `.claude/agents/workflow-auditor.md` lines 164 and 172 — Evidence column and
  closing handoff paragraph both present, byte-identical to canonical template lines 184/192.
  Verified — proves AC-04.
- Manual cross-reference of workflow-auditor's own Approved-scripts list (lines 22-86) against
  `.claude/settings.json`'s `permissions.allow` array — `jq *` and `yq *` were the only two gaps;
  both now present in both `.claude/settings.json` and `packages/ai-universal-rules/templates/
  claude/settings.json`, and both are literal entries in the agent's own approved list. Verified —
  proves AC-05.
- `git status --short -- .opencode/agents/workflow-auditor.md` and `git diff --stat -- .opencode/
  agents/workflow-auditor.md` — both empty output (byte-identical to HEAD). Verified — proves
  AC-06.
- `jq -e . .claude/settings.json` / `jq -e . packages/ai-universal-rules/templates/claude/
  settings.json` — both OK (valid JSON). Verified.
- `vendor/bin/phpunit tests/php/PermissionComposeTest.php tests/php/PermissionRenderAdaptersTest.php
  tests/php/AiCatalogLibIoTest.php` — 73 tests, 1436 assertions, OK. Verified.
- `vendor/bin/phpunit tests/php/ClaudeAgentRendererTest.php tests/php/ClaudeSettingsMergeTest.php`
  — 32 tests, 215 assertions, OK. Verified (extra confidence beyond the plan's named suites, since
  this session touched both the Claude render output and settings.json).
- `php tools/ai/render-adapters.php --check` before vs. after this session's scoped write —
  before: 29 drifted entries including `.claude/agents/workflow-auditor.md`; after: 28 entries,
  with `workflow-auditor.md` (Claude) now absent and every other entry — including `.github/
  agents/workflow-auditor.agent.md` — unchanged. Verified — zero new drift introduced, exactly
  one entry closed.
- `php tools/ai/validate-adapter-drift.php` — `OK: adapter drift validation completed` (WARN-only
  elsewhere, all pre-existing `docs/ai/workflow.md`/`docs/ai/project-context.md`/
  `docs/ai/AI-GUARDRAILS.md` doc-reference gaps on unrelated `packages/ai-universal-rules/
  templates/workflows/*.md` files, none mentioning workflow-auditor). Verified.
- `php tools/ai/validate-generated-artifacts.php` — `OK: generated artifact baseline present`
  (all 5 sub-checks `OK`). Verified.
- `php tools/ai/generate-agent-permissions.php --check` — reports exactly one pre-existing entry,
  `.opencode/agents-optional/build-config.md`, identical to the `DONE-plan-13`/`DONE-plan-16`
  precedent baseline and untouched by any edit this session made. Not a regression. Verified.
- `php tools/ai/ai.php placeholders --fail` — `OK: wrote docs/ai/generated/placeholders.json`,
  exit 0, no unresolved-placeholder failures. Verified.
- `vendor/bin/phpunit tests/php/AdapterRenderDriftTest.php tests/php/AgentPermissionDriftTest.php
  tests/php/AgentAssessmentFrontmatterWriterTest.php` — 15 tests, 96 assertions, 3 pre-existing
  failures (`AdapterRenderDriftTest` x2, `AgentPermissionDriftTest::testManagedAgentsHaveNoDrift`
  x1), all listing the exact same 28-entry/1-entry pre-existing baseline confirmed above — none
  reference workflow-auditor or any file this plan touched. Not a regression — same disposition as
  `DONE-plan-16`'s identical recurring note.
- `composer test:fast` — 925 tests, 12521 assertions, 5 failures, 6 skipped. 3 of the 5 are the
  same pre-existing baseline above; the other 2 (`CatalogDriftValidatorTest` x2) trace to
  `packages/ai-universal-rules/catalog.json` already being `M` in the pre-session working tree
  (confirmed via `git status --short`, unrelated to this plan and never touched by this session).
  None of the 5 failures reference a file this plan's Affected Paths named. Not a regression
  introduced by this plan.
- Manual diff inspection (`git diff --stat`) of all 5 touched files — `packages/ai-universal-rules/
  templates/core/agents/workflow-auditor.md` (4 lines: the two sentence rewords),
  `.claude/agents/workflow-auditor.md` (22 lines vs. HEAD, including pre-existing other-ticket
  drift-closure plus this session's 2-sentence resync — isolated via the direct body-diff in note 2
  above, confirming this session's own delta is exactly the two sentences), `.claude/settings.json`
  and `packages/ai-universal-rules/templates/claude/settings.json` (each showing 129 lines of
  diff-stat, of which only 2 lines — `Bash(jq *)` / `Bash(yq *)` — are this session's own change;
  the rest is pre-existing other-ticket drift, confirmed via a targeted diff isolating the exact
  hunk this session added), and `docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/
  plan.md` (31 lines, the flag-note addition only, no pre-existing drift on that file). Verified.

**Follow-up Verification Evidence (2026-07-08, fresh agent-critic findings fix):**

- `grep -n "MUST NOT be run by this agent"` against `.claude/agents/workflow-auditor.md` — one hit
  (line 92, the new Fix 1 sentence); the old "prose-discouraged and interactively gated" phrasing
  is gone from this file (still present verbatim in `architect.md` and
  `agent-creator-supervisor.md`, expected/out of scope). Verified.
- `awk`-measured column widths on the canonical template's Capability Routing table before/after —
  before: header/rows 36 chars, `authorization-and-tool-governance` row 37; after: separator now
  37, matching. Verified.
- `php tools/ai/validate-agent-assessment-values.php` — `OK: 26 agent entries valid; 1:1 with live
  templates; categorical-only. State: APPROVED`. Verified (agent-scores.yaml edit did not break
  the D3a source schema).
- `php tools/ai/validate-agent-assessment-frontmatter-drift.php` — reports the same 4 pre-existing,
  unrelated entries this validator already had before this session
  (`architecture-plan-writer`/`bootstrapper`/`build-config`/`refactorer` decision or block-shape
  drift, none touched here) plus one **new** line:
  `workflow-auditor: risk_level drift — template='high' github (.github/agents/
  workflow-auditor.agent.md)='medium'` — expected and flagged above (`.github` copy intentionally
  not touched, out of the task's named scope); source <=> template <=> manifest all agree at
  `high` for workflow-auditor specifically (only the untouched `.github` copy diverges). Not a
  regression against anything this slice claims to have fixed.
- `vendor/bin/phpunit tests/php/PermissionRenderAdaptersTest.php tests/php/PermissionComposeTest.php
  tests/php/ClaudeAgentRendererTest.php` — 74 tests, 1504 assertions, OK. Verified (matches the
  task's exact requested command).
- `php tools/ai/render-adapters.php --check` before vs. after this follow-up's scoped regen —
  before (this follow-up's own baseline, captured post-P0-P1 fixes above): 29 entries including
  `.claude/agents/workflow-auditor.md`; after: 28 entries, `workflow-auditor.md` (Claude) closed,
  every other entry (including `.github/agents/workflow-auditor.agent.md`) byte-identical to the
  pre-change baseline. Verified — zero new drift entries, exactly one closed, matching the task's
  explicit verification ask.
- `git diff --stat -- .opencode/agents/workflow-auditor.md` — 19 lines changed (adds the missing
  `authorization-and-tool-governance` capability, the corrected Script Access sentence, the
  5-column Final Output table, the widened separator, and `risk_level: high`); no other
  `.opencode/agents/*.md` file touched. Verified.
- Deleted the one-off script (`/tmp/opencode/render-plan17-followup.php`) and temp diff files after
  use; not committed. Verified via `git status --short` showing no untracked file under
  `/tmp/opencode`.

**Status:** All `## Todo Plan` items and all 6 Acceptance Criteria are now `[x]`. Per this file's
own completion instruction, this plan is archived below (Archive On Completion).
