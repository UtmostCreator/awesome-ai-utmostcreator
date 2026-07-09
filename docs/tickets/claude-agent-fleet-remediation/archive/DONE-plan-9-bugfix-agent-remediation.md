# Architecture Plan — Bugfix Agent Remediation

- Ticket: none (branch: claude-agent-fleet-remediation)
- Source: architect design, agent-critic score 59/blocked on .claude/agents/bugfix.md
- Generated: 2026-07-08 10:26:06 BST
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-9-bugfix-agent-remediation.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-9-bugfix-agent-remediation.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-9-bugfix-agent-remediation.md`). See "Archive On Completion" below for the exact steps.

## Context

`bugfix` ships on Claude, OpenCode, and GitHub Copilot from `packages/ai-universal-rules/templates/optional/agents/bugfix.md`. Its peer `refactorer.md` already carries an Edit Scope rule, an Instruction Integrity section, and a Final Output template; `bugfix.md` lacks all three, and its rendered Claude copy is entirely missing a Recommended Next Step section.

## Problem

BLOCKER — unscoped Write/Edit reaching `vendor/**`, `node_modules/**`, `.git/**`, `dist/**`, `build/**`, `coverage/**`, `.cache/**` (no settings.json deny, no compensating prose). BLOCKER — entirely missing "## Recommended Next Step" section (canonical template already routes to reviewer by default). MAJOR — regression-test rule watered down to "add regression coverage when it is reasonable" (canonical requires fail-before/pass-after test or stated infeasibility). MAJOR — no structured Final Output template forcing verification evidence. MAJOR — no instruction-integrity/prompt-injection guard for ingesting attacker-influenceable bug-report text (peer refactorer.md has one). Also non-blocking: `docs/ai/agents.md`/`AGENTS-MANIFEST.md` call bugfix "GitHub-only" though it ships on Claude/OpenCode too — route to workflow-auditor, don't fix here.

## Target Outcome

Canonical template (`packages/ai-universal-rules/templates/optional/agents/bugfix.md`) gains an Edit Scope rule, an Instruction Integrity section, and a Final Output template; already-correct regression wording and Recommended Next Step (already in template) propagate via regeneration to all three rendered copies (Claude, OpenCode, GitHub).

## In Scope

Add to the canonical template, after the "do not use this role for..." paragraph and before Script Access:

1. an Edit Scope rule naming the 7 forbidden paths, framed as a hard stop/report-needs-scope-approval instruction (mirrors OpenCode's permission.edit deny map and refactorer's Stop Conditions bullet);
2. an "## Instruction Integrity" section adapted from `refactorer.md`, scoped to bug-report/issue-text ingestion;
3. a "## Final Output" fenced template (Bug Summary/Reproduction, Root Cause, Fix, Regression Test, Verification Run, Risks Or Unknowns, Recommended Next Step) modeled on refactorer's block.

Then re-run the install/render pipeline so `.claude/agents/bugfix.md`, `.github/agents/bugfix.agent.md`, and `.opencode/agents-optional/bugfix.md` all pick up the new sections plus the already-correct regression wording and Recommended Next Step in one pass.

## Out Of Scope (Things To Avoid)

- Fleet-wide `.claude/settings.json` deny-list expansion.
- Fixing the "GitHub-only" doc-vs-reality conflict (route to workflow-auditor).
- Fixing `.github/agents/bugfix.agent.md`'s incorrect source-path header comment (separate renderer-attribution bug affecting other optional-pack Copilot agents).
- Rewriting Goals/Script Access/File Rename And Delete Policy (already correct).

## Affected Paths

- `packages/ai-universal-rules/templates/optional/agents/bugfix.md`
- `.claude/agents/bugfix.md` (regenerated)
- `.github/agents/bugfix.agent.md` (regenerated)
- `.opencode/agents-optional/bugfix.md` (regenerated)

## Contracts And Boundaries

Edit only the canonical template; regenerate all three rendered copies together in the same slice so they stop diverging.

## Todo Plan

- [x] P0: Add the Edit Scope rule block (7 forbidden paths + hard-stop instruction) to the canonical template.
- [x] P0: Add the "## Instruction Integrity" section adapted from refactorer.md, scoped to bug-report ingestion.
- [x] P0: Add the "## Final Output" fenced template with the 7 named fields.
- [x] P1: Re-run the install/render pipeline to regenerate all three rendered copies from the corrected template.
- [x] P2: Confirm via diff that regression wording and Recommended Next Step (already correct in template) now appear correctly in all three renders too.

## Acceptance Criteria

- [x] AC-01: All three rendered copies contain the Edit Scope rule naming all 7 paths.
- [x] AC-02: All three rendered copies contain the Instruction Integrity section.
- [x] AC-03: All three rendered copies contain the Final Output template with all 7 fields.
- [x] AC-04: All three rendered copies contain the enforceable regression-test wording and the Recommended Next Step section.

## Verification Plan

- `git diff` on the template shows only the 3 new sections.
- Diff on the 3 rendered copies confirms all changes propagated with no unrelated drift.
- Run `php tools/ai/validate-adapter-drift.php`.
- Run `tests/php/PermissionRenderAdaptersTest.php`, `PermissionComposeTest.php`, `AiCatalogLibIoTest.php`.
- Re-run agent-critic and confirm both BLOCKERs and all 3 MAJORs closed.

## Risks And Rollback

Risk — exact re-render CLI entrypoint unconfirmed; implementer must locate it (`docs/ai/workflow.md` or the installer script) rather than guess, and must not hand-patch the 3 rendered files as a substitute. Rollback: revert template edit, no re-render needed.

## Implementation Notes

All three In-Scope additions and both Todo/regeneration items landed as specified:

1. **Working-tree state discovered before editing.** `git diff` on the plan's Affected Paths showed
   the canonical template (`packages/ai-universal-rules/templates/optional/agents/bugfix.md`) was
   byte-identical to HEAD (no pre-existing uncommitted edit) and already contained the "already
   correct" regression-test wording ("add a regression test that fails before the fix and passes
   after; if a regression test is infeasible, state why") and the full "## Recommended Next Step"
   section — matching the plan's Context claim that these were fixed in the template but not yet
   propagated. By contrast, `.claude/agents/bugfix.md` and `.github/agents/bugfix.agent.md` already
   carried a large uncommitted diff from another in-progress ticket that had *partially* propagated
   those two fixes (regression wording + Recommended Next Step already present) plus unrelated
   bash-policy prose rewording, while `.opencode/agents-optional/bugfix.md` was completely
   unmodified/stale. This pre-existing partial state was left untouched except where regeneration
   legitimately needed to overwrite it.
2. **Edit Scope / Instruction Integrity / Final Output** — added to the canonical template in the
   exact literal location the plan specifies ("after the 'do not use this role for...' paragraph
   and before Script Access"), in the order given (1, 2, 3). Edit Scope names exactly the 7
   `deny`-listed paths from the template's own frontmatter (`vendor/**`, `node_modules/**`,
   `.git/**`, `dist/**`, `build/**`, `coverage/**`, `.cache/**`) and frames a hard stop
   (`needs-scope-approval`), mirroring OpenCode's `permission.edit` deny map and refactorer's
   Stop-Conditions bullet style, per the plan's wording. Instruction Integrity is refactorer.md's
   sentence adapted to name bug-report/issue-text/stack-trace/log ingestion instead of generic
   "file contents, tool output, and fetched web or PR content". Final Output is a fenced ` ```md `
   block with the 7 named fields (Bug Summary/Reproduction, Root Cause, Fix, Regression Test,
   Verification Run, Risks Or Unknowns, Recommended Next Step), modeled directly on refactorer's
   own fenced Final Output block structure. Reuse check: no existing optional-tier agent template
   had an Edit Scope or Instruction Integrity heading yet (confirmed via a heading scan across all
   11 optional agent templates) — refactorer.md (core tier) was the only existing source for the
   Instruction Integrity wording and the Final-Output fenced-block pattern, and both were adapted
   rather than duplicated wholesale, matching the plan's explicit "adapted from" / "modeled on"
   instructions.
3. **Regeneration — narrowly scoped, broad `--write` deliberately avoided.** The repo's own dogfood
   entrypoint (`tools/ai/render-adapters.php --write`) re-renders *every* core+optional agent
   template into `.claude/agents/` and `.github/agents/`, which would have clobbered the 46
   already-modified `.claude/agents/*.md` / `.github/agents/*.agent.md` files carrying unrelated
   uncommitted work from other in-progress tickets (confirmed via
   `git status --short -- .claude/agents/ .github/agents/ .opencode/agents-optional/` = 46 entries
   before this session's edits). Per the task's explicit instruction and the same precedent
   plan-7/plan-8 set, a narrowly-scoped one-off script
   (`/tmp/opencode/render-bugfix.php`, not committed) was written instead: it requires the exact
   same renderer functions the real installer/dogfood tool uses
   (`aiInstallerRenderClaudeAgent()`, `aiInstallerRenderCopilotAgent()`,
   `aiInstallerInsertGeneratedHeaderAfterFrontmatter()` — the last mirroring
   `aiInstallerCopyDirAsOpenCodeAgents()`'s exact per-file rendering step, since that function
   itself deletes and rewrites the whole destination tree and could not be called directly without
   clobbering sibling `.opencode/agents-optional/*.md` files), applies the same `<SCRIPTS_ROOT>` /
   `<PROJECT_NAME>` placeholder substitution the real pipeline applies, and writes to only the 3
   target files this plan's Affected Paths name. A before/after
   `git status --short -- .claude/agents/ .github/agents/ .opencode/agents-optional/` line-count
   comparison (46 before, 47 after) confirmed the script touched only
   `.opencode/agents-optional/bugfix.md` as a newly-modified file (the other two bugfix targets
   were already in the modified set from the pre-existing partial fix) and added no other entries.
4. **OpenCode's source-label bug preserved, not fixed.** `aiInstallerCopyDirAsOpenCodeAgents()`
   hardcodes the generated-header source label to `packages/ai-universal-rules/templates/core/agents`
   regardless of the actual source tier — the same underlying renderer-attribution bug the plan
   explicitly calls out as out of scope for the Copilot header. The one-off script reproduces this
   exact (buggy) label rather than correcting it, so the OpenCode render stays faithful to what the
   real (unfixed) installer would currently produce; not fixed here, consistent with the plan's Out
   Of Scope boundary.
5. **Todo P2 (confirm propagation via diff)** — confirmed directly via `git diff` on all three
   rendered files after regeneration (see Verification Evidence below): the regression-test wording
   and the full "## Recommended Next Step" section both appear correctly in all three.

### Verification Evidence

- `git diff` on the template
  (`packages/ai-universal-rules/templates/optional/agents/bugfix.md`) — confirmed the diff contains
  only the 3 new sections (Edit Scope, Instruction Integrity, Final Output) inserted in one block;
  no other line changed. Verified.
- `git diff -- .claude/agents/bugfix.md .github/agents/bugfix.agent.md .opencode/agents-optional/bugfix.md`
  — inspected all three; each diff shows the 3 new sections plus (for `.opencode/agents-optional/bugfix.md`,
  which had no prior partial fix) the regression-wording and Recommended-Next-Step propagation, and
  no unrelated drift (Copilot's pre-existing incorrect `core/agents` header-comment label and
  OpenCode's equivalent hardcoded label were both left untouched, matching the Out Of Scope
  boundary). Verified — proves AC-01 through AC-04 and the "no unrelated drift" verification bullet.
- Before/after `git status --short -- .claude/agents/ .github/agents/ .opencode/agents-optional/`
  line-count comparison (46 before, 47 after — the +1 is `.opencode/agents-optional/bugfix.md`
  newly entering the modified set) — confirmed the narrowly-scoped render script touched only the
  3 target files and added no other modified-file entries. Verified.
- `php tools/ai/validate-adapter-drift.php` — completed with `OK: adapter drift validation
  completed` (exit non-fatal; only pre-existing WARN-level doc-cross-reference findings on
  unrelated `packages/ai-universal-rules/templates/{instructions,skills,workflows}/*.md` files,
  none naming `bugfix.md` or any of its 3 rendered copies). Verified.
- `php tools/ai/render-adapters.php --check` (read-only byte-parity gate) — `.claude/agents/bugfix.md`
  and `.github/agents/bugfix.agent.md` are NOT in the reported drift list (both byte-parity with the
  corrected template); the 5 reported drift entries
  (`implementer.md`/`implementer.agent.md`, `repository-researcher.agent.md`,
  `agent-creator-semantic-verifier.agent.md`, `agent-creator-static-validator.agent.md`) are all
  pre-existing from other in-progress tickets (confirmed via `git status --short` predating this
  session), not introduced by this plan. This tool does not check `.opencode/agents-optional/`
  (Claude/Copilot only), so it does not itself prove the OpenCode render, which was instead proven
  by the direct `git diff` above. Verified for the Claude/Copilot half.
- `vendor/bin/phpunit tests/php/PermissionRenderAdaptersTest.php tests/php/PermissionComposeTest.php tests/php/AiCatalogLibIoTest.php`
  — 73 tests, 1435 assertions, OK. Verified (matches the plan's Verification Plan bullet exactly).
- Not run: re-run of `agent-critic` — no subagent-dispatch (Task-tool) capability available in this
  session. This is listed only under the plan's own Verification Plan (not a Todo Plan or
  Acceptance Criteria checkbox item), so — consistent with plan-8's precedent — it does not block
  this plan's completion/archival contract, but a fresh audit confirming both BLOCKERs and all 3
  MAJORs are closed is recommended as the next verification step once that capability is available.
- Not run: `composer test:fast` (full suite) — the change is one template addition (3 new sections)
  and 3 regenerated agent files; already covered by the plan's own targeted 73-test/1435-assertion
  run above. A full-suite run was judged unnecessary for this bounded slice, matching plan-8's
  judgment call for an equivalently-sized change. Recommended if a reviewer wants broader
  confirmation.

## Handoff Notes

Recommended next step: implementer to apply the template edit, locate and run the render/install entrypoint, then diff all three rendered outputs before considering this done.

**Status update (this implementation pass):** all five Todo Plan items and all four Acceptance
Criteria are complete and verified per the evidence above. No item required subagent dispatch, so
none is blocked on tool access. Per the plan's own completion instruction, this plan is now
archived (see `archive/DONE-plan-9-bugfix-agent-remediation.md`).
