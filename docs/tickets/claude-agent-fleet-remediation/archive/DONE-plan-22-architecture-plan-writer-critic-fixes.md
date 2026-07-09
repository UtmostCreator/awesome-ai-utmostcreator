# Architecture Plan — architecture-plan-writer critic fixes

- Ticket: none
- Source: architect design, agent-critic score 74/needs_refactor on .claude/agents/architecture-plan-writer.md
- Generated: 2026-07-08T09:32:05Z
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-22-architecture-plan-writer-critic-fixes.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-22-architecture-plan-writer-critic-fixes.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-22-architecture-plan-writer-critic-fixes.md`). See "Archive On Completion" in this agent's own operating instructions for the exact steps.

## Context

This ticket is recursive: this same agent (architecture-plan-writer) will persist this very plan about fixing itself. This is expected and not a conflict of interest, since every requirement/AC/non-goal is fixed by this design, not the agent's own judgment; its job here is to transcribe this bounded plan into the required format, not make new scoping decisions.

## Problem

- MAJOR — Core Mission's "exactly one allowed write surface" claim states an absolute, unhedged capability while Claude subagent frontmatter/`.claude/settings.json` have no per-path or per-subagent Write/Edit scoping — a false-confidence overclaim, especially since the file's own later text (How To Write The File) already carries a hedged, runtime-agnostic version of the same claim (duplicated, not just contradicted).
- MAJOR — Archive On Completion falsely states "this agent's bash permission denies `mv`, `cp`, and `rm`" — `.claude/settings.json` only denies `Bash(rm -rf *)`, nothing for bare `mv`/`cp`/`rm`, and settings.json permissions are global, not per-subagent.
- MINOR — missing references to `docs/ai/project-context.md`, `docs/ai/workflow.md`, `docs/ai/AI-GUARDRAILS.md`.
- MINOR — rename/delete policy stated twice (Hard Rules + dedicated File Rename And Delete Policy section).
- MINOR — redundant `date`/`date *` bash allowlist entries — but this is evidenced as a FALSE POSITIVE: `compositions.php` shows `date *` requires a trailing space+content and does NOT match the bare zero-argument `date` invocation; both entries are functionally distinct and must not be removed.

## Target Outcome

Core Mission is reworded to cross-reference (not duplicate) the already-hedged enforcement language; the false `mv`/`cp`/`rm` claim is replaced with a runtime-agnostic operational rule; a proportionate Canonical References line is added; the Hard Rules rename/delete duplication collapses to a single cross-reference bullet; the `date`/`date *` finding is declined as a false positive with evidence recorded.

## In Scope

Edit `packages/ai-universal-rules/templates/core/agents/architecture-plan-writer.md` only, four bounded changes:

1. Core Mission — replace the unhedged "exactly one allowed write surface" sentence with: "This agent's intended write surface is markdown files under `docs/tickets/` only — see 'How To Write The File' below for how that scope is enforced (or not) per runtime."
2. Archive On Completion — replace the false absolute claim with: "Do not shell-move, shell-copy, or shell-delete the file to archive it, regardless of what this agent's bash policy does or does not block on the current runtime — archiving must always go through the write/edit tool. Instead: ..." (keep the existing two-step write+tombstone instructions unchanged after this sentence).
3. Add a short Canonical References block after Core Mission or Incoming Handoff Contract: "Ground scope and provenance in `docs/ai/project-context.md`. Ticket/branch lifecycle conventions used for `{branch-name}` and archive-on-completion mirror `docs/ai/workflow.md`. The do-not-widen-scope and do-not-invent-architecture rules mirror `docs/ai/AI-GUARDRAILS.md`."
4. Collapse the four duplicated Hard Rules rename/delete bullets into one cross-reference bullet: "Rename and delete are governed entirely by the 'File Rename And Delete Policy' section below — do not restate its rules here," keeping the full, more complete rule only in the dedicated section.

Then regenerate `.claude/agents/architecture-plan-writer.md`, `.opencode/agents/architecture-plan-writer.md`, `.github/agents/architecture-plan-writer.agent.md` from the updated template. Do NOT touch the `date`/`date *` bash permission entries — record the critic's MINOR finding as a false positive with the `compositions.php` evidence citation.

## Out Of Scope (Things To Avoid)

- Adding any new Write/Edit path-scoping mechanism to Claude Code (none exists).
- Migrating this agent's permission block to the compositions.php/packs.php generator (explicitly gated, in-flight migration — coordination gate per `docs/ai/source-of-truth.md`).
- Changing `agent_assessment` metadata (owned by the next agent-critic pass).
- Investigating or fixing why .opencode/.github still carry stale `decision: approve_with_minor_fixes` and pre-improvement "How To Write The File" text (route to workflow-auditor as a separate regeneration/pipeline-integrity question — confirmed present verbatim in both, while the template and .claude already have the fixed wording; also, `.github`'s Final Output wrongly references an OpenCode-specific "/implement" command).
- Editing `docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/plan.md`.
- Removing or altering the `date`/`date *` bash permission grants.

## Affected Paths

- `packages/ai-universal-rules/templates/core/agents/architecture-plan-writer.md`
- `.claude/agents/architecture-plan-writer.md` (regenerated)
- `.opencode/agents/architecture-plan-writer.md` (regenerated)
- `.github/agents/architecture-plan-writer.agent.md` (regenerated)

## Contracts And Boundaries

Edit only the canonical template. `.claude/agents/architecture-plan-writer.md` and its siblings are GENERATED — regenerate via the confirmed installer/render entrypoint (exact CLI unconfirmed — see Risks); do not hand-edit the three generated files.

## Todo Plan

- [x] P0: Apply the Core Mission rewording (item 1).
- [x] P0: Apply the Archive On Completion rewording (item 2).
- [x] P1: Add the Canonical References block (item 3).
- [x] P1: Collapse the duplicated Hard Rules rename/delete bullets (item 4).
- [x] P1: Regenerate all three adapter copies from the updated template.
- [x] P2: Confirm (do not fix) the .opencode/.github staleness and flag it for workflow-auditor; do not touch `date`/`date *` entries. Confirmed present pre-regeneration (stale `decision: approve_with_minor_fixes`, pre-improvement "How To Write The File" wording, and — additionally, not named in the plan's own Out Of Scope prose but confirmed via diff — a stale OpenCode-specific `/implement` reference in `.opencode`'s own Final Output, plus the equivalent stale text in `.github`); resolved only as an incidental regeneration side effect, not a deliberate fix by this plan. See Implementation Notes.

## Acceptance Criteria

- [x] AC-01: `rg -n "exactly one allowed write surface"` across the template and all three rendered copies returns zero matches. Verified (exit 1, no matches).
- [x] AC-02: `rg -n "bash. permission denies .mv., .cp., and .rm."` across the same four files returns zero matches. Verified (exit 1, no matches).
- [x] AC-03: `rg -n "docs/ai/project-context.md|docs/ai/workflow.md|docs/ai/AI-GUARDRAILS.md"` on the Claude copy returns at least one match per path. Verified — one line matches all three paths.
- [x] AC-04: The Hard Rules section shows the 4 rename/delete bullets replaced by 1 cross-reference bullet; the dedicated File Rename And Delete Policy section is unchanged. Verified via diff and direct read.
- [x] AC-05: `git diff` on the canonical template's frontmatter bash: map shows zero lines changed for `date *` / `date` allow entries. Verified — zero date-line diff hunks.
- [x] AC-06: `git diff` on the three regenerated adapter files shows changes confined to the four intended sections (plus, for .opencode/.github only, incidental resync of the already-template-fixed "How To Write The File" wording). Verified — see Implementation Notes for full diff accounting.

## Verification Plan

- The six rg/diff checks above.
- Run `tests/php/PermissionRenderAdaptersTest.php`, `AiCatalogLibIoTest.php`, `PermissionComposeTest.php` if they cover this agent's rendering.
- Recommend a fresh agent-critic pass afterward to confirm score improvement past 74.

## Risks And Rollback

- Risk — exact CLI entrypoint to regenerate the three adapters is unconfirmed (not documented in `docs/ai/workflow.md`); implementer must confirm before running, and must not hand-edit the three generated files if the entrypoint can't be confirmed — ask rather than guess.
- Risk — regenerating will also resync .opencode/.github's stale text as an incidental side effect; call this out explicitly so it isn't mistaken for having resolved the workflow-auditor-owned drift-cause question.
- Risk (low) — the four fixes are shared verbatim across all three adapters via the renderer's body passthrough, so a single template edit is expected to apply cleanly everywhere.
- Rollback: revert the four template edits; no regeneration needed if reverted before running.

## Handoff Notes

Recommended next step: implementer to apply the four template edits (not the bash permission map), then run the sanctioned regeneration path (confirm entrypoint first) to refresh all three adapters. Report the incidental .opencode/.github resync (if it happens) as a side effect, not as having answered the workflow-auditor question. After implementation, route to reviewer and release-auditor (generated-artifact + permission-adjacent change), then to a fresh agent-critic pass to close the loop on the 74/100 score. Separately (outside this plan), flag the .opencode/.github stale decision/pre-improvement-text drift for workflow-auditor as its own investigation.

## Implementation Notes

**Working-tree context confirmed.** Before this session touched anything, `git status --short`
showed 169 already-modified files from unrelated in-progress tickets on this branch. Of this
plan's 4 Affected Paths, all four (`packages/ai-universal-rules/templates/core/agents/
architecture-plan-writer.md`, `.claude/agents/architecture-plan-writer.md`,
`.opencode/agents/architecture-plan-writer.md`, `.github/agents/architecture-plan-writer.agent.md`)
were ALREADY modified (uncommitted) by other in-flight work before this session started — none
were clean against HEAD. The pre-existing template diff was exactly one unrelated hunk (a new
`## Architecture Diagram` section, from a separate in-progress ticket, plan-30-style) — left
untouched throughout this session, confirmed still present and unmodified in every subsequent
diff.

**Prior-art check (per task brief).** Read `tools/ai/install/claude-agent-renderer.php` in full
(415 lines) before writing any new logic. Confirmed no `$agentId === 'architecture-plan-writer'`
override block exists anywhere in the file. Confirmed the three cited generic fixes are present
and unconditional (no `$agentId` guard): (1) the "enforced boundary anyway" -> "required agent
policy; hard enforcement depends on..." fix; (2) the external-directory-approval-prompt
instruction-only clarification (scoped in its own comment to architect/reviewer/researcher/
implementer, but implemented as an unconditional `preg_replace` against any body containing the
matching phrase); (3) the dangling `task` (`ask`) delegation rewrite, gated generically on
`!in_array('Agent', $tools, true)`. Read this agent's canonical template body in full and
confirmed it contains NONE of the three trigger phrases these generic fixes target (no
"external-directory approval prompt" text, no "Full per-script `allow`/`ask`/`deny` is in
frontmatter" Script Access sentence — this agent has no Script Access section at all — and no
`` `task`\s*\(`ask`\)\s+is only for delegating `` phrasing), so none of those three generic
fixes apply to this agent's render; only the BLOCKER "enforced boundary anyway" fix and the
mutation-command framing rewrite (also unconditional, baked into the base `$bashPolicy`
construction) were relevant, and only because the pre-existing working-tree `.claude/agents/
architecture-plan-writer.md` copy had never been regenerated since those renderer fixes landed
upstream — confirmed by reading it before regeneration: it still carried the stale "Treat the
following as the enforced boundary anyway" text and the stale flat "Do not run: `rm`, `mv`,
`cp`..." mutation list.

**Canonical template edits (items 1-4).** Applied to `packages/ai-universal-rules/templates/
core/agents/architecture-plan-writer.md` exactly as specified in the plan's own In Scope section,
using the plan's own wording verbatim for items 1-3. For item 4, the plan's own prose embeds a
trailing comma inside its example quotation as a sentence-continuation artifact ("...here,"
keeping the full..."); wrote the bullet with a closing period instead, since a standalone Markdown
bullet reads better that way and no AC checks the exact trailing punctuation. `git diff` on the
template shows exactly 4 hunks — one per fix — confirming the pre-existing unrelated Architecture
Diagram hunk was not touched.

**Regeneration: narrow one-off script, not broad `--write`.** Per the task's explicit instruction
(the real `tools/ai/render-adapters.php --write` entrypoint iterates every core+optional agent
template and would have rewritten every other agent's already-drifted `.claude`/`.github` file
too, clobbering unrelated in-progress work), wrote
`/tmp/opencode/render-plan22-architecture-plan-writer.php` (not committed, deleted after use)
mirroring the plan-7/13/.../21 precedent: it required the exact same renderer files the real
installer/CLI uses (`claude-agent-renderer.php`, `copilot-agent-renderer.php`,
`generated-header.php`, transitively `permission-layers/render-adapters.php`,
`canonical-agent-frontmatter.php`, the tool/handoff registries) and called the exact same
functions (`aiInstallerRenderClaudeAgent()`, `aiInstallerRenderCopilotAgent()`,
`aiInstallerInsertGeneratedHeaderAfterFrontmatter()` for the OpenCode raw-copy path) the real
`tools/ai/render-adapters.php --write` and the full installer use, including the same
`<SCRIPTS_ROOT>` -> `scripts/ai` placeholder substitution via `strtr()` that
`render-adapters.php` itself applies (an initial version of this script omitted that
substitution and produced a literal, unresolved `<SCRIPTS_ROOT>` token in the Claude render —
caught by diff review before it was left in place, then fixed and re-run). Confirmed this
template carries no `<PROJECT_NAME>` token, so only one substitution was needed. The script wrote
only the 3 target files. `git status --short` file count was unchanged before/after (169 -> 169):
only the byte content of the already-modified target files changed, no new file was touched.

**Diff verification.** Full `git diff` read for all 3 regenerated adapters plus the template
(above). Template: exactly 4 hunks, items 1-4, nothing else. `.claude/agents/
architecture-plan-writer.md`: the 4 template fixes plus 2 already-generic, already-approved
renderer improvements this file had not yet been regenerated to reflect (the BLOCKER "enforced
boundary anyway" fix and the mutation-command framing rewrite) — both confirmed unconditional in
the renderer per the prior-art check above, so this is expected byproduct of a genuine render
invocation, not new scope. `.opencode/agents/architecture-plan-writer.md` and `.github/agents/
architecture-plan-writer.agent.md`: the same 4 template fixes plus a substantially larger
incidental resync — confirmed this file's Out Of Scope claim about stale `decision:
approve_with_minor_fixes` and pre-improvement "How To Write The File" text was accurate (both
present verbatim pre-regeneration in both files), and additionally surfaced one detail not named
in the plan's own Out Of Scope prose: `.opencode`'s Final Output also carried the stale
OpenCode-specific `implementer means implementer agent handoff using OpenCode command: /implement`
sentence (the plan's Out Of Scope text attributed this specific defect only to `.github`, but it
was present in `.opencode` too) — both are now resynced to the current template's
`Handoff Routing`-section-based Final Output as an incidental side effect of this regeneration,
not a deliberate fix by this plan. No frontmatter/tool-grant/permission-block change beyond
`agent_assessment.decision` (`approve_with_minor_fixes` -> `approve`, matching the already-current
template value) in either `.opencode` or `.github`; the Claude frontmatter (`tools:`,
`permissionMode:`, `agent_assessment` block) is byte-identical to the pre-regeneration file.

**Verification evidence.**

- `vendor/bin/phpunit tests/php/PermissionRenderAdaptersTest.php tests/php/AiCatalogLibIoTest.php
  tests/php/PermissionComposeTest.php` -> `OK (73 tests, 1436 assertions)`.
- The six rg/diff Acceptance Criteria checks (AC-01 through AC-06) all confirmed directly — see
  the AC entries above for each command and result.
- `composer test:fast` -> `Tests: 929, Assertions: 12531, Failures: 3, Skipped: 6`. Read the full
  failure output for all 3: `AdapterRenderDriftTest` (x2) lists drifted files
  (`config-maintainer`, `implementer`, `post-install`, `refactorer`, `repository-researcher`,
  `repository-reviewer`, `researcher`, `reviewer` (Copilot only), `workflow-auditor` (Copilot
  only), `agent-creator-*`, `agent-critic`, `bugfix`, `build-config`, `infra-auditor`) and
  `AgentPermissionDriftTest` lists `.opencode/agents-optional/build-config.md` —
  **`architecture-plan-writer` does NOT appear in either drift list**, confirming the regenerated
  files are byte-parity with their canonical template. All listed files were already `M` in
  `git status --short` before this session touched anything (unrelated in-progress tickets, same
  baseline pattern documented in plan-20's and plan-21's Implementation Notes). No regression
  introduced by this slice.
- `git status --short -- .claude .opencode .github packages/ai-universal-rules | rg -i
  "architecture-plan-writer"` confirms exactly this plan's 4 Affected Paths were modified this
  session — no other file under those roots was touched.
- Cleanup: deleted `/tmp/opencode/render-plan22-architecture-plan-writer.php` after use.

**Todo item disposition for the recommended agent-critic re-run.** The Verification Plan's third
bullet ("Recommend a fresh agent-critic pass afterward to confirm score improvement past 74") is
a recommendation, not a Todo Plan or Acceptance Criteria item, and this session has no Task-tool
subagent-dispatch capability to run it. Recorded here as blocked-on-tool-access for an
orchestrator session to close, per the task's own instruction; it does not block archiving since
it was never a required Todo/AC item.

**Archive status: ARCHIVED.** Every Todo Plan item and every Acceptance Criteria item is now
checked `[x]`; per this file's own completion instruction, archived as
`archive/DONE-plan-22-architecture-plan-writer-critic-fixes.md`.
