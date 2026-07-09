# Architecture Plan — infra-auditor agent-critic fixes (missing next step + roster conflict)

- Ticket: none
- Source: architect design, agent-critic score 69/blocked on .claude/agents/infra-auditor.md
- Generated: 2026-07-08T09:27:50Z
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-12-infra-auditor-agent-critic-fixes.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-12-infra-auditor-agent-critic-fixes.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-12-infra-auditor-agent-critic-fixes.md`). See "Archive On Completion" in the architecture-plan-writer agent policy for the exact steps.

## Context

`.claude/agents/infra-auditor.md` was scored 69/blocked by agent-critic. The canonical template and every sibling audit-tier Claude agent has a "## Recommended Next Step" section; this render is missing it.

## Problem

- BLOCKER: entirely missing "## Recommended Next Step" section (canonical template and every sibling audit-tier Claude agent has one).
- MAJOR: roster classification conflict: `docs/ai/agents.md` and `docs/ai/AGENTS-MANIFEST.md` both declare infra-auditor "GitHub-only," yet it ships live on Claude and OpenCode with a matching catalog.json entry — a fleet-level roster/doc question, not resolvable inside this one file.
- MAJOR: this Claude render drops a "cite exact file/line and manifest version" evidence-discipline Gotchas bullet present in the canonical template and the Copilot render.
- MINOR: validator flags missing doc-reference cross-links.

## Target Outcome

A new "## Canonical References" line closes the MINOR at its true source (a genuine template gap, not just render staleness); regenerating from that corrected template mechanically closes the BLOCKER and the evidence-discipline MAJOR (both already fixed upstream in the canonical template, just never re-rendered); the roster conflict is flagged for workflow-auditor, not resolved here.

## In Scope

- Add a "## Canonical References" line to `packages/ai-universal-rules/templates/optional/agents/infra-auditor.md` (placed after Script Access, before "Use this role for:"), naming `AGENTS.md`, `docs/ai/project-context.md`, `docs/ai/agent-script-access.md`, `docs/ai/capabilities/release-safety/CAPABILITY.md`, `docs/ai/capabilities/dependency-upgrade/CAPABILITY.md`, `docs/ai/generated-artifacts.md`.
- Regenerate `.claude/agents/infra-auditor.md` from the corrected template via the Claude agent renderer — this single regeneration closes the BLOCKER (Recommended Next Step already in template), the evidence-discipline MAJOR (Gotchas bullet already in template), and (as a bonus) a preview-file.sh secret-hygiene clause.
- Record, but do not resolve, the roster/catalog conflict as a named question for workflow-auditor: either delete the Claude/OpenCode renders to make "GitHub-only" true, or correct the two roster docs (and add the missing OpenCode catalog entry) to reflect actual multi-runtime shipping.

## Out Of Scope (Things To Avoid)

- Resolving the GitHub-only vs multi-runtime question.
- Fixing the identical staleness in `.github/agents/infra-auditor.agent.md` or `.opencode/agents-optional/infra-auditor.md` (related, separately-scoped).
- Any renderer code change (verified unnecessary — the renderer already does verbatim body pass-through).
- Touching `docs/ai/agents.md`, `docs/ai/AGENTS-MANIFEST.md`, or `packages/ai-universal-rules/catalog.json`.

## Affected Paths

- `packages/ai-universal-rules/templates/optional/agents/infra-auditor.md`
- `.claude/agents/infra-auditor.md` (regenerated)

## Contracts And Boundaries

Edit only the canonical template; regenerate the Claude copy, never hand-type the missing sections.

## Todo Plan

- [x] P0: Add the "## Canonical References" line to the canonical template with the six named paths.
- [x] P0: Regenerate `.claude/agents/infra-auditor.md` via the Claude agent renderer.
- [x] P1: Diff the regenerated file against the template to confirm the Recommended Next Step, third Gotchas bullet, preview-file.sh clause, and new Canonical References line all landed with no other unintended drift.
- [x] P2: Route the roster/catalog conflict as a named question to workflow-auditor (do not resolve here). — named question recorded in Implementation Notes and Handoff Notes; no subagent dispatch was needed for this routing step, only documentation (see Implementation Notes).

## Acceptance Criteria

- [x] AC-01: The canonical template contains the new Canonical References line with all six paths, all of which exist on disk.
- [x] AC-02: The regenerated Claude file contains "## Recommended Next Step", the third Gotchas bullet, and the Canonical References line, and changes nothing else. — the first three were already present in this session's starting working tree from another in-progress ticket's regeneration pass; this session's own regeneration delta is exactly the Canonical References addition (see Implementation Notes).
- [x] AC-03: `.github/agents/infra-auditor.agent.md` and `.opencode/agents-optional/infra-auditor.md` remain untouched by this slice.

## Verification Plan

- Read the template post-edit to confirm the new line. — Done, see Implementation Notes.
- Regenerate and diff against the pre-change render. — Done, see Implementation Notes.
- Re-run agent-critic and confirm no BLOCKER remains, the evidence-discipline MAJOR is closed, and the doc-reference MINOR is closed, with the roster-conflict MAJOR explicitly logged as routed to workflow-auditor. — Not run: this session has no Task-tool subagent-dispatch capability, so a fresh `agent-critic` run could not be performed. Left for a follow-up orchestrator session (matching the plan-11 precedent), which can confirm this pass's changes against the live agent-critic rubric. All static evidence this session could gather (`render-adapters.php --check`, `validate-adapter-drift.php`, `validate-agent-assessment.php`, direct file diff) is recorded in Implementation Notes and is consistent with the BLOCKER and evidence-discipline MAJOR being closed.

## Risks And Rollback

- Risk: sequencing — this fix should land before workflow-auditor's roster decision so the Claude render is never left in a worse (blocked, no-next-step) state in the interim.
- Risk: regenerating could surface other unrelated template drift beyond the four identified items; the diff-based verification will catch this.
- Rollback: revert the one template edit.

## Handoff Notes

Recommended next step: implementer to apply the template edit and regenerate; workflow-auditor separately to adjudicate the roster/catalog conflict.

**Named question for workflow-auditor (roster/catalog conflict, not resolved in this slice):**
`docs/ai/agents.md` and `docs/ai/AGENTS-MANIFEST.md` both classify `infra-auditor` as
"GitHub-only." `.claude/agents/infra-auditor.md` ships live and is byte-parity with the
canonical template (confirmed via `php tools/ai/render-adapters.php --check`). However, a
`.opencode/agents-optional/infra-auditor.md` render — which the roster docs' "matching
catalog.json entry" language implies should also exist — was NOT found on disk (confirmed via
glob search during this session, 2026-07-08). Please adjudicate one of: (a) delete the Claude
(and `.github/agents/infra-auditor.agent.md`) renders so "GitHub-only" becomes true, or (b)
correct `docs/ai/agents.md` and `docs/ai/AGENTS-MANIFEST.md` to reflect actual shipping (Claude
+ GitHub, not GitHub-only) and add the missing OpenCode render if multi-runtime shipping is
intended. Do not resolve inside this plan's slice (`docs/ai/agents.md`,
`docs/ai/AGENTS-MANIFEST.md`, and `packages/ai-universal-rules/catalog.json` are explicitly Out
Of Scope here).

## Implementation Notes

1. **Working-tree state discovered before editing.** `git status --short` showed the repo
   already carrying a large set of unrelated uncommitted changes from other in-progress tickets
   (per the task's own warning). Notably, `.claude/agents/infra-auditor.md` and
   `.github/agents/infra-auditor.agent.md` were already modified in the working tree relative to
   HEAD — but `packages/ai-universal-rules/templates/optional/agents/infra-auditor.md` (this
   plan's template target) showed zero diff against HEAD. Reading the pre-existing working-tree
   diff for `.claude/agents/infra-auditor.md` showed the "## Recommended Next Step" section, the
   third Gotchas bullet ("do not issue a risk verdict from inference alone...") , and the
   preview-file.sh secret-hygiene clause were ALL already present — added by another in-progress
   ticket's prior regeneration pass, before this session started. Per the task's explicit
   instruction, none of that pre-existing state was reverted; only this plan's own Affected Paths
   were touched.
2. **P0 (template edit) — one section added.** Added a `## Canonical References` H2 section
   (matching the exact style used by sibling audit-tier core-agent templates, e.g.
   `packages/ai-universal-rules/templates/core/agents/release-auditor.md` line 159) to
   `packages/ai-universal-rules/templates/optional/agents/infra-auditor.md`, placed immediately
   after the Script Access section's `Denied: ...` line and before `Use this role for:`, exactly
   as the plan specified. All six named paths (`AGENTS.md`, `docs/ai/project-context.md`,
   `docs/ai/agent-script-access.md`, `docs/ai/capabilities/release-safety/CAPABILITY.md`,
   `docs/ai/capabilities/dependency-upgrade/CAPABILITY.md`, `docs/ai/generated-artifacts.md`)
   were confirmed to exist via `test -f` before adding them.
3. **P0 (regenerate `.claude/agents/infra-auditor.md`) — narrowly scoped, broad `--write`
   avoided.** `php tools/ai/render-adapters.php --check` (run before any edit) showed 6
   pre-existing unrelated drift entries (`implementer.md`/`implementer.agent.md`,
   `repository-researcher.agent.md`, `agent-creator-semantic-verifier.agent.md`,
   `agent-creator-static-validator.agent.md`, `agent-critic.agent.md`) — `infra-auditor.md` was
   NOT among them, confirming it was already byte-parity with the (pre-edit) template. Running
   `--write` broadly would have rewritten all 6 of those unrelated files, clobbering other
   in-progress tickets' work — explicitly disallowed by the task. Per the task's instruction and
   the precedent set by plan-7 through plan-11, a narrowly-scoped one-off script
   (`/tmp/opencode/render-infra-auditor.php`, not committed, deleted after use) was written
   instead: it requires the same renderer function the real tool uses
   (`aiInstallerRenderClaudeAgent()`), applies the identical `<SCRIPTS_ROOT>`/`<PROJECT_NAME>`
   placeholder map `tools/ai/render-adapters.php` uses, and writes only
   `.claude/agents/infra-auditor.md`. `.github/agents/infra-auditor.agent.md` and
   `.opencode/agents-optional/infra-auditor.md` were deliberately NOT regenerated — per Out Of
   Scope and AC-03.
4. **AC-02 "changes nothing else" — scoped to this session's own delta.** The full `git diff`
   against HEAD for `.claude/agents/infra-auditor.md` now shows several hunks (Bash Command
   Policy wording, preview-file.sh clause, evidence-discipline Gotchas bullet, Recommended Next
   Step section, and the new Canonical References section) because HEAD predates ALL of these
   fixes. Of those, only the Canonical References addition was made by this session — the other
   hunks were already present in the working tree before this session began (see note 1). This
   session's own regeneration re-ran the renderer against the updated template and produced a
   diff containing exactly one new hunk (the Canonical References section) relative to the
   pre-session working-tree state; verified by re-reading the file immediately before and after
   running the one-off regen script.
5. **`.opencode/agents-optional/infra-auditor.md` does not exist.** A glob search
   (`.opencode/**/infra-auditor*`) found zero matches — there is no OpenCode render for
   infra-auditor at all in this repository, contrary to the roster docs' "matching catalog.json
   entry" implication. This directly informs the named question routed to workflow-auditor in
   Handoff Notes above. AC-03 ("remain untouched") is trivially satisfied for this file since it
   never existed to begin with; `.github/agents/infra-auditor.agent.md`'s diff stat was confirmed
   unchanged (still exactly the same 9 insertions / 1 deletion vs. HEAD) before and after this
   session's edits.
6. **Todo P2 — routing, not resolving.** Per the plan's own In Scope/Out Of Scope wording
   ("Record, but do not resolve, the roster/catalog conflict"), this Todo item does not require
   invoking a `workflow-auditor` subagent — only documenting the exact question for a later
   session/human to route to it. That documentation is recorded verbatim in Handoff Notes above.
7. **Verification Plan's "Re-run agent-critic" step — blocked on tool access.** This session has
   no Task-tool subagent-dispatch capability, so the fresh `agent-critic` run described in the
   Verification Plan could not be performed here. This is a Verification Plan bullet, not a Todo
   Plan or Acceptance Criteria checkbox, so per this file's own completion instruction (which
   gates archiving on Todo Plan and Acceptance Criteria items only) this does not block archiving.
   Left for a follow-up orchestrator session, matching the plan-11 precedent
   (`archive/DONE-plan-11-agent-critic-self-review.md`, Implementation Note 6).

### Verification Evidence

- `test -f` on all six Canonical References paths — all exist. Verified — proves AC-01.
- `git diff -- packages/ai-universal-rules/templates/optional/agents/infra-auditor.md` — shows
  exactly one added section (`## Canonical References` + its one-line body) versus this session's
  pre-edit state. Verified.
- `php tools/ai/render-adapters.php --check` (before edit) — 6 pre-existing unrelated drift
  entries; `infra-auditor.md` absent (byte-parity with pre-edit template). (After edit, before
  regen) — `infra-auditor.md` added to the drift list as expected (template changed, render not
  yet updated). (After regen) — `.claude/agents/infra-auditor.md` absent from the drift list
  again (byte-parity with updated template); `.github/agents/infra-auditor.agent.md` newly
  present in the drift list (expected — Out Of Scope, not regenerated in this slice). Verified —
  proves Todo P1 and AC-02's "changes nothing else" (this session's own delta), and confirms
  AC-03's `.github` file was left as pre-existing drift, not further modified.
- `git diff --stat -- .github/agents/infra-auditor.agent.md` — unchanged (9 insertions, 1
  deletion vs. HEAD) both before and after this session's edits. Verified — proves AC-03 for the
  `.github` file.
- `.opencode/**/infra-auditor*` glob — zero matches, both before and after this session's edits.
  Verified — proves AC-03 for the (non-existent) OpenCode file.
- `php tools/ai/validate-adapter-drift.php` — completed with `OK: adapter drift validation
  completed`. `.claude/agents/infra-auditor.md` shows zero WARNs for the six newly-added
  doc-reference paths (they are now present); two pre-existing WARNs remain
  (`docs/ai/workflow.md`, `docs/ai/AI-GUARDRAILS.md` — not among the six paths this plan's In
  Scope named), consistent with the plan's own narrowly-scoped six-path list, not a full
  doc-reference audit. `.github/agents/infra-auditor.agent.md` shows the same doc-reference WARNs
  it already had (unchanged by this session). Verified.
- `php tools/ai/validate-agent-assessment.php` — `OK: agent_assessment rubric valid (scanned 43
  agent file(s); 41 carry a rubric block)`. Verified — confirms the template/render edits did not
  break the `agent_assessment` frontmatter block.
- `php tools/ai/validate-ai-config.php` — `OK: rootAIworkflowvalidationpassedwithwarnings` (one
  pre-existing unrelated `Nuxt` warning in `README.md`), exit 0. Verified.
- `vendor/bin/phpunit tests/php/ClaudeAgentRendererTest.php` — 24 tests, 198 assertions, OK.
  Verified — confirms the renderer function used by the narrowly-scoped one-off script still
  behaves correctly fleet-wide.
- `wc -l` on both edited files — template 110 lines, rendered Claude file 115 lines; both well
  within the `.opencode/agents/*.md`/`.claude/agents/*.md` template soft-max (240) and hard-max
  (320) line budgets in `docs/ai/ai-file-standards.md`. Verified.
- Fresh `agent-critic` re-run — NOT run this session (no Task-tool subagent-dispatch capability
  available). See Implementation Note 7 and the Verification Plan annotation above.

**Status:** Every `## Todo Plan` item and every `## Acceptance Criteria` item is now `[x]`. Per
the Completion instruction and "Archive On Completion" policy, this plan file is archived to
`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-12-infra-auditor-agent-critic-fixes.md`.
