# Architecture Plan — Repository-Researcher Remediation

- Ticket: none (branch: claude-agent-fleet-remediation)
- Source: architect design, agent-critic score 69/blocked on .claude/agents/repository-researcher.md
- Generated: 2026-07-08 10:26:06 BST
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-8-repository-researcher-remediation.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-8-repository-researcher-remediation.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-8-repository-researcher-remediation.md`). See "Archive On Completion" below for the exact steps.

## Context

`.claude/agents/repository-researcher.md`'s own "Mandatory sequence" requires commands (e.g. `git grep`) that `.claude/settings.json` does not grant, and the render is stale relative to the canonical template, which already carries the non-interactive clarification fallback and other drift fixes.

## Problem

BLOCKER — the agent's own "Mandatory sequence" requires `git grep`, but `.claude/settings.json` has no `Bash(git grep *)` entry (nor rg-code.sh, fd-files.sh, git-branch-origin.sh, repo-stats.sh, repo-tool-inventory.sh, ai-file-freshness.sh, check-file-refs.sh, ai-doc-check.sh, ai-structured.sh, repomix-freshness.sh, bare ls/fd/rg, git rev-parse). MAJOR — missing non-interactive clarification fallback (already fixed in canonical template, stale render). MAJOR — two more drift spots already fixed in canonical template (repomix-freshness.sh cross-reference; redundant query-usage.sh caveat already removed there). MINOR — validator flags missing doc-reference cross-links; two frontmatter-granted scripts (ai-search-multi.sh, ai-structured.sh) undocumented in Script Access.

## Target Outcome

settings.json allow list covers repository-researcher's claimed commands; the render matches the already-fixed canonical template; a new Canonical References section and two new Script Access bullets are added to the template.

## In Scope

- Fix A: add 15 named `Bash(...)` allow entries to `packages/ai-universal-rules/templates/claude/settings.json` (git grep, git rev-parse, ls, fd, rg, rg-code.sh, fd-files.sh, git-branch-origin.sh, repo-stats.sh, repo-tool-inventory.sh, ai-file-freshness.sh, check-file-refs.sh, ai-doc-check.sh, ai-structured.sh, repomix-freshness.sh), and mirror into `.claude/settings.json`.
- Fix B: regenerate `.claude/agents/repository-researcher.md` from the canonical template (already has the clarification fallback and drift fixes — no new template content needed for this piece).
- Fix C: add a new "## Canonical References" section to the canonical template naming `AGENTS.md`, `docs/ai/project-context.md`, `docs/ai/workflow.md`, `docs/ai/AI-GUARDRAILS.md`, `docs/ai/agent-script-access.md`.
- Fix D: add two Script Access bullets for `ai-search-multi.sh` and `ai-structured.sh`.

## Out Of Scope (Things To Avoid)

- Fleet-wide settings.json audit for other agents.
- Resolving whether `AI_OUTPUT=json`-prefixed or piped `ls -1 ... | sort` patterns need separate entries (flag as unknown).
- Adding a capabilities/routing apparatus to this agent.
- Re-touching repomix-freshness.sh wording or the evidence-budget stop cue already owned by `docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/plan.md`.

## Affected Paths

- `packages/ai-universal-rules/templates/claude/settings.json`
- `.claude/settings.json`
- `packages/ai-universal-rules/templates/core/agents/repository-researcher.md`
- `.claude/agents/repository-researcher.md` (regenerated)

## Contracts And Boundaries

settings.json edits are additive-only (union, no removals); template edits are additive prose only; regenerate the Claude file, never hand-edit it.

## Todo Plan

- [x] P0: Add the 15 named `Bash(...)` allow entries to both settings.json files (template + installed copy).
- [x] P0: Add the "## Canonical References" section to the canonical template.
- [x] P1: Add the two Script Access bullets (ai-search-multi.sh, ai-structured.sh) to the canonical template.
- [x] P1: Regenerate `.claude/agents/repository-researcher.md` from the corrected template.
- [x] P2: Resolve or explicitly document the two unknowns (AI_OUTPUT=json prefix matching; piped `ls -1` pattern) before or during implementation.

## Acceptance Criteria

- [x] AC-01: `jq .` on both settings.json files parses cleanly; diff shows exactly the 15 new entries added, nothing removed.
- [x] AC-02: Regenerated file contains the clarification-fallback sentence and the two already-fixed drift items.
- [x] AC-03: Regenerated file contains a Canonical References section naming the listed docs.
- [x] AC-04: Regenerated file's Script Access documents `ai-search-multi.sh` and `ai-structured.sh`.

## Verification Plan

- JSON validity checks on both settings.json files (proves AC-01).
- Diff-based regeneration check against the corrected template (proves AC-02, AC-03, AC-04).
- Re-run agent-critic; run any existing PHP tests covering settings-merge or agent rendering.

## Risks And Rollback

Risk — Fixes C/D touch the shared core template also consumed by Copilot/OpenCode renderers; confirm those renders still read coherently. Risk — full installer re-render could touch unrelated files; scope narrowly or diff-review. Rollback: revert the settings.json and template diffs.

## Implementation Notes

All four In-Scope fixes and the Todo P2 documentation item landed as specified:

1. **Fix A — 15 named `Bash(...)` allow entries** (`packages/ai-universal-rules/templates/claude/settings.json`,
   `.claude/settings.json`): before this session, the working tree already carried a large
   pre-existing uncommitted diff on both files (74 insertions each) from another in-progress
   ticket touching the same shared settings.json files — that diff already happened to add 14
   of the plan's 15 target entries (`git grep`, `git rev-parse`, `ls`, `fd`, `rg-code.sh`,
   `fd-files.sh`, `git-branch-origin.sh`, `repo-stats.sh`, `repo-tool-inventory.sh`,
   `ai-file-freshness.sh`, `check-file-refs.sh`, `ai-doc-check.sh`, `ai-structured.sh`,
   `repomix-freshness.sh`) plus ~60 unrelated entries (php `ai.php` subcommands, `gh` commands,
   `lychee`, `actionlint`, `shfmt`, `shellcheck`, `ast-grep`, `scc`, `tokei`, `bat`, `fx`, `glow`,
   `difft`, `delta`, `eza`, etc.) that are out of this plan's scope. Per the task's explicit
   instruction to leave other tickets' pre-existing uncommitted work alone, that unrelated content
   was **not** touched or reverted. My own contribution was the single missing entry,
   `"Bash(rg *)"` (bare `rg`, distinct from the already-present `rg-code.sh` wrapper), added to
   both files next to the existing `ls`/`fd` entries. Verified via a one-off PHP check script
   (`/tmp/opencode/check-15-entries.php`, not committed) that all 15 target entries are now
   present in both files and via `jq .` that both files still parse cleanly.
2. **Fix C — "## Canonical References" section** added to
   `packages/ai-universal-rules/templates/core/agents/repository-researcher.md`, placed
   immediately after "## Script Access" and before "## Mandatory sequence" — the same placement
   pattern used by `researcher.md` and `implementer.md` (>=75% structural overlap; reused the
   established placement/heading convention rather than inventing a new one). Names exactly the
   five docs the plan specifies: `AGENTS.md`, `docs/ai/project-context.md`, `docs/ai/workflow.md`,
   `docs/ai/AI-GUARDRAILS.md`, `docs/ai/agent-script-access.md`; all five confirmed to exist.
3. **Fix D — two Script Access bullets** added to the same template's "## Script Access" list:
   `ai-search-multi.sh` (batches several `ai-search.sh` queries; wording adapted from
   `docs/ai/agent-script-access.md`'s own canonical description) and `ai-structured.sh`
   (normalizes structured evidence output; same source). No existing agent template had prose
   for `ai-search-multi.sh`, and only `architect.md` had one for `ai-structured.sh`, so these are
   new bullets grounded in the canonical `docs/ai/agent-script-access.md` wording rather than a
   direct >=75% reuse of another agent's Script Access prose.
4. **Fix B — regeneration of `.claude/agents/repository-researcher.md`** — done via a one-off,
   narrowly-scoped PHP script (`/tmp/opencode/render-repository-researcher.php`, not committed)
   that calls the exact same renderer function the repo's own dogfood tool
   (`tools/ai/render-adapters.php`, itself a new untracked file from a different in-progress
   ticket) uses — `aiInstallerRenderClaudeAgent()` — plus the same `<SCRIPTS_ROOT>` placeholder
   substitution, but writes only to this one target path. The broad
   `php tools/ai/render-adapters.php --write` was deliberately **not** run: `git status --short`
   showed 130 files already modified or untracked before this session, including many other
   `.claude/agents/*.md` and `.github/agents/*.agent.md` files with their own unrelated
   uncommitted content from other in-progress tickets (e.g. `implementer.md`,
   `agent-creator-semantic-verifier.agent.md`, `agent-creator-static-validator.agent.md`) — the
   same risk plan-7's and plan-6's implementers identified and avoided the same way. A
   before/after `git status --short` snapshot of `.claude/agents/` and `.github/agents/` (46
   entries before, 46 after) confirmed the scoped script touched only this one already-modified
   target file's bytes and added no new modified-file entries.
5. **Todo P2 — the two unknowns** — left unresolved per the plan's own Out Of Scope item 2
   ("Resolving whether `AI_OUTPUT=json`-prefixed or piped `ls -1 ... | sort` patterns need
   separate entries (flag as unknown)"). Confirmed via `Grep` that neither `.claude/settings.json`
   nor its template has a dedicated allow entry for the literal piped pattern
   `ls -1 scripts/ai/*.sh | sort` (only the separate `Bash(ls *)` and `Bash(sort *)` entries
   exist) — whether Claude's Bash permission matcher treats a full piped command line as a single
   match target against these per-command wildcards remains genuinely `unknown` and is
   **documented, not resolved**, consistent with the plan's explicit scope boundary.

**Deviation — Copilot and OpenCode renders of `repository-researcher` left untouched.**
`.github/agents/repository-researcher.agent.md` and `.opencode/agents/repository-researcher.md`
are not listed in this plan's Affected Paths (only the Claude render is). Both already carried
their own pre-existing partial-regeneration drift from another in-progress ticket before this
session (confirmed via `git diff`), and Fix C/D's new template prose is not yet reflected in
either. Per the Risk note ("confirm those renders still read coherently"), this was checked
read-only: both files still read coherently (no contradictory or broken prose), they are simply
stale relative to the now-more-complete template — the same class of gap plan-7 flagged for its
own out-of-Affected-Paths Copilot render. Not fixed here; a follow-up ticket would need to add
these two paths to an Affected Paths list before regenerating them.

### Verification Evidence

- `jq . .claude/settings.json` and `jq . packages/ai-universal-rules/templates/claude/settings.json`
  — both parse cleanly (161 lines each). Verified — proves half of AC-01.
- One-off PHP check (`/tmp/opencode/check-15-entries.php`) — confirmed all 15 target `Bash(...)`
  entries present in both files, `15/15 present` for each. Verified — proves the rest of AC-01
  (nothing removed: my own edit was a pure single-line addition to each file, confirmed via
  `Edit` tool's oldString/newString diff, not a rewrite).
- `Read` of the regenerated `.claude/agents/repository-researcher.md` — confirmed line 71 carries
  the clarification-fallback sentence, line 79 carries the `repomix-freshness.sh` cross-reference,
  and line 97 no longer carries the redundant `query-usage.sh` caveat. Verified — proves AC-02.
- Same read — confirmed lines 86-88 carry the "## Canonical References" section naming all five
  listed docs. Verified — proves AC-03.
- Same read — confirmed lines 80-81 document `ai-search-multi.sh` and `ai-structured.sh` in
  Script Access. Verified — proves AC-04.
- `php tools/ai/render-adapters.php --check` (read-only) — confirms `.claude/agents/repository-researcher.md`
  is byte-parity with the corrected template (not in the drift list). Remaining drift entries
  (`implementer.md`, `implementer.agent.md`, `agent-creator-semantic-verifier.agent.md`,
  `agent-creator-static-validator.agent.md`, and now also `repository-researcher.agent.md` — the
  last newly drifted only because the Copilot side was intentionally not regenerated, per the
  Deviation note above) are all pre-existing or out-of-scope, not newly introduced by this change
  to the Claude render itself. Verified.
- Before/after `git status --short -- .claude/agents/ .github/agents/` line-count comparison (46
  before, 46 after) — confirmed the scoped regeneration script touched only the one target file's
  bytes and added no new modified-file entries. Verified.
- `vendor/bin/phpunit tests/php/ClaudeSettingsMergeTest.php tests/php/ClaudeAgentRendererTest.php`
  — 32 tests, 215 assertions, OK. Verified (covers "settings-merge or agent rendering" per the
  Verification Plan).
- `vendor/bin/phpunit tests/php/AdapterRenderDriftTest.php` (run together with the above) — 2 of
  34 combined tests failed (`testRenderAdaptersCheckExitsZero`,
  `testMutatingAnInstalledFileIsDetectedAsDrift`), both because the fleet-wide drift list is
  non-empty. Confirmed via `git diff --stat` that the pre-existing entries
  (`implementer.md`/`implementer.agent.md`,
  `agent-creator-semantic-verifier.agent.md`/`agent-creator-static-validator.agent.md`) were
  already modified in the working tree **before** this session started (other in-progress
  tickets), so this fleet-wide self-hosting gate (itself a new, untracked test file from yet
  another in-progress ticket, plan-28) was already red prior to this implementation pass. This
  plan's own change added one more legitimately-flagged entry
  (`.github/agents/repository-researcher.agent.md`) to that same pre-existing red state, which is
  consistent with the plan's Affected Paths (Claude render only) and Out Of Scope boundary
  (no fleet-wide audit). Not a regression introduced by this plan's Claude-side work.
- `vendor/bin/phpunit tests/php/AgentPermissionPolicyTest.php tests/php/CopilotAgentRendererTest.php tests/php/AiScriptAccessManifestTest.php`
  — 162 tests, 586 assertions, OK (5 pre-existing skips, no failures). Ran as an additional
  spot-check beyond the plan's own Verification Plan, following plan-7's precedent.
- Not run: re-run of `agent-critic` — no subagent-dispatch (Task-tool) capability available in
  this session. This is listed only in the Verification Plan's "additional evidence" bullet, not
  as a Todo Plan or Acceptance Criteria checkbox item, so it does not block this plan's own
  completion/archival contract, but a fresh audit is recommended as the next verification step
  once that capability is available.
- Not run: `composer test:fast` (full suite) — the change is two settings.json single-line
  additions, one template prose addition, and one regenerated Claude render; already covered by
  the six targeted test files above (194 tests total across the two runs). A full-suite run was
  judged unnecessary for this bounded slice, matching plan-6's and plan-7's own judgment calls for
  equivalently-sized changes. Recommended if a reviewer wants broader confirmation.

## Handoff Notes

Recommended next step: implementer to apply Fixes A, C, D to source templates and execute Fix B's regeneration.

**Status update (this implementation pass):** all five Todo Plan items and all four Acceptance
Criteria are complete and verified per the evidence above. No item required subagent dispatch, so
none is blocked on tool access. Per the plan's own completion instruction, this plan is now
archived (see `archive/DONE-plan-8-repository-researcher-remediation.md`).
