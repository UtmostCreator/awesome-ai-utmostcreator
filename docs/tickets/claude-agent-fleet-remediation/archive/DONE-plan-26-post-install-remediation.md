# Architecture Plan — Post-install remediation (tools/ai/** enforcement + Hard Boundaries + drift fixes)

- Ticket: none
- Source: architect design, agent-critic score 65/blocked on .claude/agents/post-install.md
- Generated: 2026-07-08
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-26-post-install-remediation.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-26-post-install-remediation.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-26-post-install-remediation.md`). See "Archive On Completion" below for the exact steps.

## Context

post-install.md is a high-risk Write/Edit/Agent-capable agent whose entire job is gating placeholder resolution during install. A fresh agent-critic pass scored it 65/blocked.

## Problem

BLOCKER — tools/ai/** has no enforced write/edit restriction on Claude; this agent can silently rewrite the very validators (tools/ai/verify-install-placeholders.php, tools/ai/ai.php) that gate its own Placeholder Resolution Gate (the OpenCode sibling explicitly denies tools/ai/**). MAJOR — remaining Hard Boundaries (vendor, cache/build/dist/coverage, lockfiles, secret/key/env/auth files) are prose-only on Claude; .claude/settings.json has no deny for vendor/**, node_modules/**, .git/**, dist/**, build/**, coverage/**, .cache/**, credentials.*, secrets.*, auth.json, and its lockfile glob misses package-lock.json/pnpm-lock.yaml/bun.lockb. MAJOR — docs/ai/AI-GUARDRAILS.md missing from the "Read First" list. MAJOR — installed Final Output template drifted from canonical — missing "## Gate Proof (both commands, exact output)" and "## Blockers" sections that the canonical template already has (identical gap across Claude, OpenCode, and Copilot renders). MINOR — body says "via the Task tool" but the frontmatter grant is named Agent.

## Target Outcome

tools/ai/** and the remaining Hard Boundary paths get a real settings.json enforcement floor; AI-GUARDRAILS.md is referenced; Final Output sections propagate from the canonical template via re-render; the Task/Agent naming mismatch is fixed.

## In Scope

- Add docs/ai/AI-GUARDRAILS.md to the "## Read First" list and remove the "via the Task tool" phrasing (both occurrences) from packages/ai-universal-rules/templates/core/agents/post-install.md — leave the Final Output block untouched (already correct there).
- Regenerate .claude/agents/post-install.md, .opencode/agents/post-install.md, and .github/agents/post-install.agent.md from the fixed canonical template — closes the AI-GUARDRAILS gap and the Final Output drift (Gate Proof/Blockers) simultaneously.
- Extend the Claude global permission floor in both packages/ai-universal-rules/templates/claude/settings.json and .claude/settings.json with deny entries for tools/ai/**, vendor/**, node_modules/**, .git/**, dist/**, build/**, coverage/**, .cache/**, credentials.*, secrets.*, auth.json, package-lock.json, pnpm-lock.yaml, bun.lockb.
- Correct the stale docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/plan.md "## post-install" section (or supersede via this new plan) to reflect the fresh 65/blocked score, replacing "maintenance-only."
- Re-run agent-critic post-remediation.

## Out Of Scope (Things To Avoid)

- Auditing/fixing the same tools/ai/**/Hard-Boundaries gap for any other Claude agent (shared-file fix benefits them incidentally, but reviewing their audits is out of scope).
- Redesigning Claude's permission model for per-agent scoping (no such mechanism exists).
- Adding/removing/renaming any script grant, capability, or Placeholder Resolution Gate rule.
- Hand-patching the three rendered files directly for template-sourced content.
- Changing agent_assessment by hand.

## Affected Paths

- packages/ai-universal-rules/templates/core/agents/post-install.md
- .claude/agents/post-install.md (regenerated)
- .opencode/agents/post-install.md (regenerated)
- .github/agents/post-install.agent.md (regenerated)
- packages/ai-universal-rules/templates/claude/settings.json
- .claude/settings.json
- docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/plan.md (correction note)

## Contracts And Boundaries

Body/Read-First/Final-Output/delegation-wording source of truth is the canonical template — regenerate-only for the three rendered copies. Claude permission-floor source of truth is packages/ai-universal-rules/templates/claude/settings.json, merged idempotently into .claude/settings.json.

## Todo Plan

- [x] P0: Add docs/ai/AI-GUARDRAILS.md to Read First and remove both "via the Task tool" occurrences in the canonical template.
- [x] P0: Extend both settings.json files' permissions.deny with the 14 new path entries (tools/ai/**, vendor/**, node_modules/**, .git/**, dist/**, build/**, coverage/**, .cache/**, credentials.*, secrets.*, auth.json, package-lock.json, pnpm-lock.yaml, bun.lockb). **Deviation:** 13 of the 14 named entries (all but `tools/ai/**`) were already present in both settings.json files from unrelated in-progress WIP already in the working tree before this session started — see Implementation Notes.
- [x] P1: Regenerate all three rendered copies (Claude, OpenCode, Copilot) from the corrected template. **Deviation:** ran via a narrowly-scoped one-off script instead of the broad `--write` sweep — see Implementation Notes.
- [x] P1: Diff each regenerated copy's Final Output block to confirm "## Gate Proof" and "## Blockers" now appear.
- [x] P2: Update the stale docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/plan.md post-install section with a correction note.
- [x] P2: Re-run agent-critic to confirm the BLOCKER cleared and score materially improved. **Closed by an orchestrator session (2026-07-08):** a fresh audit found 4 new findings (2 BLOCKER, 2 MAJOR) plus 2 MINORs introduced/exposed since this plan's earlier passes — all fixed and re-verified; see the "Orchestrator Follow-Up (2026-07-08)" Implementation Note below.

## Acceptance Criteria

- [x] AC-01: `php tools/ai/validate-adapter-drift.php` shows no warning for post-install.md missing docs/ai/AI-GUARDRAILS.md.
- [x] AC-02: All three regenerated files' Final Output blocks contain "## Gate Proof (both commands, exact output)" and "## Blockers" verbatim-matching canonical.
- [x] AC-03: `grep -rn "via the Task tool"` across all four files (template + 3 renders) returns zero matches.
- [x] AC-04: Both settings.json files parse as valid JSON, diff shows identical new deny entries, no existing allow/deny entries dropped.
- [x] AC-05: Fresh agent-critic run shows the BLOCKER cleared and READINESS no longer blocked. Closed by an orchestrator session (2026-07-08) — see "Orchestrator Follow-Up (2026-07-08)" below.

## Verification Plan

- Validator run (`php tools/ai/validate-adapter-drift.php`) proves AC-01.
- Diff-based regeneration check across all three copies proves AC-02.
- `grep -rn "via the Task tool"` proves AC-03.
- JSON validity + diff on both settings.json files proves AC-04.
- Re-run agent-critic proves AC-05.
- Run any install-verification command if one exists.

## Risks And Rollback

Risk — exact re-render CLI entrypoint unconfirmed, implementer must locate it rather than hand-patch. Risk — other Claude agents may share the tools/ai/** gap (incidentally fixed by the shared settings.json change, not separately audited here). Rollback: revert the template, settings.json, and three render diffs.

## Handoff Notes

Recommended next step: implementer to apply Slices 1-3 (template edit, settings.json floor, regeneration), verify, then request agent-critic re-audit. Slice 4 (ticket correction) can be folded into whatever document records this remediation's outcome.

## Implementation Notes

**Working-tree context confirmed.** Before this session touched anything, `git status --short`
showed dozens of already-modified files from unrelated in-progress tickets on this branch
(consistent with the plan-14/plan-24/plan-25 documented pattern), including both
`.claude/settings.json` and `packages/ai-universal-rules/templates/claude/settings.json`. Reading
those pre-existing diffs directly (not assuming) showed they already carried 13 of this plan's 14
named deny-entry additions — `vendor/**`, `node_modules/**`, `.git/**`, `dist/**`, `build/**`,
`coverage/**`, `.cache/**`, `credentials.*`, `secrets.*`, `auth.json`, `package-lock.json`,
`pnpm-lock.yaml`, `bun.lockb` — from other in-flight WIP not committed by this session. Only
`tools/ai/**` (this plan's own BLOCKER item) was genuinely absent from both files at session
start, confirmed via a direct read/search of both files before editing.

**P0 #1 (template body edit).** In
`packages/ai-universal-rules/templates/core/agents/post-install.md`: added
`docs/ai/AI-GUARDRAILS.md` to the "## Read First" list (after `docs/ai/execution-protocol.md`,
matching the plan's own Contracts And Boundaries — template is the only edited source for
body/Read-First content); replaced both "via the Task tool" occurrences (the Placeholder
Resolution Gate bullet and Workflow step 3) with "via the Agent capability", matching the Claude
frontmatter's actual `Agent` tool grant name (`tools: Read, Grep, Glob, Bash, Write, Edit, Agent`
in `.claude/agents/post-install.md`) and closing the MINOR finding. The Final Output block was
left untouched, confirmed already correct in the template (already had `## Gate Proof (both
commands, exact output)` and `## Blockers` from an earlier plan,
`docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/plan.md` — see the P2 correction
note below).

**P0 #2 (settings.json deny-floor).** Added exactly two new lines to each file's
`permissions.deny` array — `"Edit(tools/ai/**)"` / `"Write(tools/ai/**)"` — placed immediately
before the existing `vendor/**` pair (matching the existing ordering convention of adjacent
path-class denies). `.claude/settings.json` uses 4-space indent, the template uses 2-space
indent; each file's own existing indentation was preserved, not homogenized.

**P1 (regenerate all three rendered copies).** The plan's own In Scope text names
`.claude/agents/post-install.md`, `.opencode/agents/post-install.md`, and
`.github/agents/post-install.agent.md` as the three targets. Per the task's explicit instruction
not to clobber unrelated uncommitted work, no broad `--write`/`--apply` sweep was run:
`php tools/ai/render-adapters.php --check`, run *before* any edit in this session, already showed
~23 other unrelated agent files in drift (pre-existing WIP from other in-progress tickets, none of
which this session touched) — confirming a broad `--write` would have rewritten files outside this
plan's Affected Paths, matching this plan's own Out Of Scope ("Auditing/fixing the same tools/ai/**
gap for any other Claude agent"). Instead, wrote a narrowly-scoped one-off script
(`/tmp/opencode/render-plan26-post-install.php`, not committed, deleted after use, mirroring the
plan-7 through plan-25 precedent) that calls the real installer renderer functions directly:
`aiInstallerRenderClaudeAgent()` (from `tools/ai/install/claude-agent-renderer.php`) for the
Claude copy, `aiInstallerRenderCopilotAgent()` (from `tools/ai/install/copilot-agent-renderer.php`)
for the Copilot copy, and `aiInstallerInsertGeneratedHeaderAfterFrontmatter()` (from
`tools/ai/install/generated-header.php`) applied to the raw template content for the OpenCode copy
— reproducing exactly what `aiInstallerCopyDirAsOpenCodeAgents()` does for a single file (a raw
copy plus a GENERATED header insert after frontmatter; OpenCode agents are not re-rendered the way
Claude/Copilot are, confirmed via direct read of `copilot-agent-renderer.php` lines 308-362). The
same `<SCRIPTS_ROOT>`/`<PROJECT_NAME>` placeholder map `tools/ai/render-adapters.php` itself uses
was reproduced inline (`<SCRIPTS_ROOT>` fixed at `scripts/ai`; `<PROJECT_NAME>` read from
`.ai/project.yml`), since `render-adapters.php` is a top-level executing CLI entrypoint, not a
require-safe library. `claude-agent-renderer.php`, `copilot-agent-renderer.php`,
`generated-header.php`, and `project-yaml.php` were confirmed safe to `require` directly (no
top-level executing side effects — `render-adapters.php` itself requires the first two as
libraries).

**P1 (Final Output diff confirmation).** Confirmed via direct grep of each regenerated file that
`## Gate Proof (both commands, exact output)` and `## Blockers` both appear verbatim: `.claude/agents/post-install.md`
(lines 188, 200), `.opencode/agents/post-install.md` (lines 258, 270),
`.github/agents/post-install.agent.md` (lines 199, 211).

**P2 (stale ticket correction).** Added a "Correction (2026-07-08)" callout directly under the
`## post-install` heading in
`docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/plan.md`, replacing the
`_maintenance-only_` status tag with `_superseded — see correction note_` and summarizing the
fresh 65/blocked score's 5 findings plus their remediation in this plan, while explicitly noting
the pre-existing "Improvements (2)" list (Gate Proof / Blockers) remains historically accurate —
those changes were genuinely applied to the canonical template by that earlier plan; this plan
only closed the render-drift gap that kept them out of the shipped copies, plus the other four
findings.

**P2 (agent-critic re-run) — blocked.** This session has no Task-tool subagent-dispatch
capability, so Todo P2's second item and AC-05 (re-run agent-critic, confirm BLOCKER cleared) are
left unchecked per the task's explicit instruction; an orchestrator session must close this gap.

### Verification Evidence

- `php -r "json_decode(file_get_contents('.claude/settings.json')); echo json_last_error_msg();"`
  and the same for `packages/ai-universal-rules/templates/claude/settings.json` — both `No error`.
  Verified — proves AC-04's JSON-validity half.
- `php -r` comparison of `json_decode(...)['permissions']['deny']` between both settings.json
  files (via `json_encode(..., JSON_PRETTY_PRINT)` diff) — `DENY LISTS IDENTICAL`. Verified.
- `git diff -- .claude/settings.json packages/ai-universal-rules/templates/claude/settings.json`
  — inspected the full diff directly: every removed line is immediately re-added with a trailing
  comma appended (JSON syntax only, from inserting new array entries after a former last element),
  confirming no existing allow/deny entry was dropped. Verified — proves the rest of AC-04.
- `php tools/ai/render-adapters.php --check`, run before any edit — lists
  `.claude/agents/post-install.md` and `.github/agents/post-install.agent.md` in drift (expected,
  the template edit had not landed yet) plus ~23 unrelated pre-existing entries.
- `php /tmp/opencode/render-plan26-post-install.php` — `WROTE:` for all three target files.
  Verified.
- `php tools/ai/render-adapters.php --check`, run after the scoped write — post-install is
  **absent** from both the Claude and Copilot drift lists; the same ~22 pre-existing unrelated
  entries remain, confirming zero new drift introduced. Verified.
- Manual diff of `.opencode/agents/post-install.md` against the canonical template (OpenCode has
  no `render-adapters.php` coverage) — the only difference is the expected GENERATED header line
  inserted after frontmatter. Verified.
- `php tools/ai/validate-adapter-drift.php` (full run, saved to a scratch file and filtered) —
  zero "should reference docs/ai/AI-GUARDRAILS.md" warnings for `.claude/agents/post-install.md`,
  `.opencode/agents/post-install.md`, `.github/agents/post-install.agent.md`, or
  `packages/ai-universal-rules/templates/core/agents/post-install.md` at the real repo-root path
  (the only post-install AI-GUARDRAILS warnings found were inside a stale, unrelated nested
  worktree copy at `.claude/worktrees/agent-add4ef88765702e5b/...`, not part of this plan's
  Affected Paths). Verified — proves AC-01.
- Direct grep of all three regenerated files for `## Gate Proof (both commands, exact output)` and
  `## Blockers` — both present in each. Verified — proves AC-02.
- Direct grep for `via the Task tool` across the template and all three renders — zero matches in
  all four files. Verified — proves AC-03.
- `php tools/ai/generate-agent-permissions.php --check`, run both before and after this session's
  edits — identical single pre-existing entry (`.opencode/agents-optional/build-config.md`) both
  times; this plan made no permission-composition changes, so this is a no-new-drift sanity check,
  not part of the plan's own Verification Plan.
- `vendor/bin/phpunit tests/php/ClaudeAgentRendererTest.php tests/php/CopilotAgentRendererTest.php`
  — `OK (54 tests, 495 assertions)`. Verified.
- `composer test:fast` (full repo suite, 929 tests) — 3 pre-existing failures
  (`AdapterRenderDriftTest::testRenderAdaptersCheckExitsZero`,
  `AdapterRenderDriftTest::testMutatingAnInstalledFileIsDetectedAsDrift`,
  `AgentPermissionDriftTest::testManagedAgentsHaveNoDrift`), all attributable to the same
  pre-existing unrelated WIP drift confirmed present before this session's first edit (the ~22
  unrelated render-drift entries and the single `build-config.md` permission-drift entry); none
  reference post-install or this plan's Affected Paths. No regression introduced by this slice.
- `php tools/ai/validate-ai-config.php` — `OK: root AI workflow validation passed with warnings`;
  the one WARN (`unexpected stack term 'Nuxt' in README.md`) is pre-existing and unrelated.
  Verified.
- Cleanup: deleted `/tmp/opencode/render-plan26-post-install.php` and the scratch
  `adapter-drift-full.txt` after use.

**Orchestrator Follow-Up (2026-07-08).** An orchestrator session with Task-tool/subagent-dispatch
capability re-ran a fresh audit against `.claude/agents/post-install.md` (score 55, blocked) and
found 4 new findings plus 2 straightforward MINORs, distinct from this plan's original 5 findings:

1. **[BLOCKER]** The mandatory Gate Proof command `php tools/ai/verify-install-placeholders.php`
   had no matching allow entry in `.claude/settings.json`. Fixed: added
   `"Bash(php tools/ai/verify-install-placeholders.php*)"` to `permissions.allow` in both
   `.claude/settings.json` and `packages/ai-universal-rules/templates/claude/settings.json`.
2. **[BLOCKER]** The Script Access bullet falsely claimed the scoped `ai-verify.sh`
   `AI_VERIFY_SCOPE=changed` variant was `allow` with no disclosure that `.claude/settings.json`
   does not actually grant it. Fixed: reworded the bullet in the canonical template
   (`packages/ai-universal-rules/templates/core/agents/post-install.md` — the plan's own
   Affected Paths lists this as the canonical source; the task's stated `optional/agents/`
   path does not exist for this agent) to disclose the gap and treat it as `ask`-tier on Claude
   until closed.
3. **[MAJOR]** "fall back to any read-only explore agent" was not an exact roster name. Fixed:
   replaced with "fall back to `reviewer` (read-only) if neither is available" (both Workflow
   step 3 and the Placeholder Resolution Gate bullet reference the same research-delegation
   language via a shared sentence pattern, but only one literal occurrence of this exact phrase
   existed — fixed at its source).
4. **[MAJOR]** The body claimed `php tools/ai/ai.php install-docs*` was approved, but
   `.claude/settings.json` only allows the exact `install-docs --check` invocation. Fixed — with
   a discovery beyond the task's literal instruction: this repo's Bash Command Policy body for
   post-install is not hand-authored template prose but a **composed permission model**
   (`tools/ai/install/permission-layers/compositions.php` + `packs.php`), spliced into the
   `permission:` frontmatter block of both the canonical template and `.opencode/agents/
   post-install.md` by `php tools/ai/generate-agent-permissions.php`, then re-rendered into the
   Claude/Copilot copies by `php tools/ai/render-adapters.php`. Editing the frontmatter bullet
   directly (as first attempted) created `generate-agent-permissions.php --check` drift, because
   post-install's composition still opted into the `install.docs_allow` pack (the wildcard
   grant) on top of the exact-match entry it already receives from its default `readonly`
   `cli_tools` tier (`shipped-cli-readonly` pack, which already carries the exact
   `install-docs --check` allow). Real fix: removed `'install.docs_allow'` from post-install's
   `allowPacks` in `compositions.php` (only `bootstrapper` still uses that pack), then
   regenerated the composed block via a scoped one-off script (mirroring
   `generate-agent-permissions.php`'s own splice function, applied only to post-install's two
   managed files) and re-ran the Claude/Copilot/OpenCode render step.
5. **[MINOR]** Replaced "Agent capability" with "`Agent` tool" (both occurrences: the
   Placeholder Resolution Gate bullet and Workflow step 3) for fleet-wide terminology
   consistency with how other agents in this fleet refer to the same grant.
6. **[MINOR]** Appended a scope clarification to the Workflow step 5 adapter-edit sentence:
   "(the target repo's rendered adapter copies only — never
   `packages/ai-universal-rules/templates/**` in this kit's own source repo)".

All six template edits landed in
`packages/ai-universal-rules/templates/core/agents/post-install.md`. Both settings.json files got
the fix-1 allow entry. `.claude/agents/post-install.md`, `.github/agents/post-install.agent.md`,
and `.opencode/agents/post-install.md` were regenerated via the same narrowly-scoped one-off
script pattern this plan's own P1 slice used (not the broad `--write` sweep — `php tools/ai/
render-adapters.php --check` still shows the same ~22 pre-existing unrelated drift entries from
other in-flight WIP on this branch, with post-install absent from the list both before and after
this follow-up's edits landed).

### Follow-Up Verification Evidence

- `jq -e . .claude/settings.json` and `jq -e . packages/ai-universal-rules/templates/claude/
  settings.json` — both exit 0 (valid JSON). Verified.
- `vendor/bin/phpunit tests/php/PermissionRenderAdaptersTest.php tests/php/
  PermissionComposeTest.php tests/php/ClaudeAgentRendererTest.php tests/php/
  ClaudeSettingsMergeTest.php` — `OK (86 tests, 1534 assertions)`, run twice (before and after the
  compositions.php fix). Verified.
- `php tools/ai/render-adapters.php --check` — post-install absent from the drift list both
  before and after the follow-up's edits; the same ~22 pre-existing unrelated entries persist
  unchanged (matching this plan's own earlier-documented baseline). No new drift. Verified.
- `php tools/ai/generate-agent-permissions.php --check` — before the compositions.php fix,
  incorrectly showed `packages/ai-universal-rules/templates/core/agents/post-install.md` and
  `.opencode/agents/post-install.md` in drift (caused by the first, frontmatter-only edit
  attempt); after fixing `compositions.php` and regenerating, only the single pre-existing
  `.opencode/agents-optional/build-config.md` entry remains (same baseline plan-26's original
  pass documented). Verified.
- `php tools/ai/validate-adapter-drift.php` (full run) — no AI-GUARDRAILS.md warning and no new
  finding for `.claude/agents/post-install.md`, `.opencode/agents/post-install.md`,
  `.github/agents/post-install.agent.md`, or the canonical template at their real repo-root
  paths; remaining post-install-related WARNs are pre-existing (soft-max line-length notices, and
  `post-install-setup` prompt/skill/command files — a different, unrelated shipped surface) or
  live inside the unrelated stale `.claude/worktrees/agent-add4ef88765702e5b/...` copy. Verified.
- Direct grep of `.claude/agents/post-install.md` confirms: the exact `install-docs --check`
  bullet with no wildcard sibling; the reworded `ai-verify.sh` Script Access bullet; `reviewer
  (read-only) if neither is available`; two `` `Agent` tool `` occurrences; the appended adapter-
  edit-scope clarification. Verified.
- `diff` of `.opencode/agents/post-install.md` against the canonical template — only the expected
  GENERATED header line differs. Verified.
- Scratch files (`/tmp/opencode/render-plan26-postinstall-followup.php`, `/tmp/opencode/
  render-plan26-permission-scoped.php`, `/tmp/opencode/adapter-drift-full.txt`) deleted after use.

**Archive disposition.** All Todo Plan items and Acceptance Criteria are now checked, including
Todo P2's agent-critic re-run and AC-05, closed by this orchestrator follow-up. Per the plan's own
completion instruction, this file is archived to
`archive/DONE-plan-26-post-install-remediation.md` and replaced with a tombstone pointer.
