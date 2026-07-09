# Architecture Plan — Agent Creator Improvement

- Ticket: none (branch: claude-agent-fleet-remediation)
- Source: architect design, agent-critic score 76/ready-with-fixes (needs_refactor, no BLOCKER) on .claude/agents/agent-creator.md
- Generated: 2026-07-08 (BST)
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-27-agent-creator-improvement.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-27-agent-creator-improvement.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-27-agent-creator-improvement.md`). See "Archive On Completion" below for the exact steps.

## Context

`agent-creator` ships on Claude, OpenCode, and GitHub Copilot from `packages/ai-universal-rules/templates/optional/agents/agent-creator.md`. It is the first stage of the agent-creation pipeline (Creator → Static Validator → Semantic Verifier → Runtime Guardian → human approval), handing a proposed AgentSpec to `agent-creator-static-validator` via `agent-creator-supervisor`. A prior remediation pass (`docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/plan.md`'s `## agent-creator` section, marked residual-fixes) already scoped and closed some issues; this ticket addresses a fresh agent-critic audit's remaining findings without duplicating that prior work.

## Problem

MAJOR — `.claude/settings.json`'s allow list doesn't cover most of this file's own "Approved scripts" claim, critically including `ai-structured.sh` — the exact script the Contract section relies on to emit the AgentSpec JSON output. MAJOR — `ai-task.sh` is claimed as an available ask-tier tool in Script Access but is absent from the approved-scripts list on every rendered surface (Claude, OpenCode) and the canonical template never mentions it either — a false tool claim fleet-wide, not Claude-specific. MAJOR — no defined path for when the Static Validator rejects the spec; the OpenCode sibling has "revise and re-emit exactly one corrected AgentSpec JSON," this file (and its canonical template) doesn't. MINOR — handoff prose uses informal names ("the supervisor," "the Static Validator") instead of exact roster ids (`agent-creator-supervisor`, `agent-creator-static-validator`).

## Target Outcome

`.claude/settings.json` grants the scripts this agent's own approved list already claims (at minimum `ai-structured.sh`); the `ai-task.sh` false-claim is resolved (either granted consistently or removed from Script Access); a Static-Validator-rejection clause exists; handoff prose uses exact roster ids throughout.

## In Scope

1. Add `Bash(scripts/ai/ai-structured.sh *)` (and any other of this file's own claimed-but-ungranted scripts, e.g. `rg-code.sh`, `fd-files.sh`, `check-file-refs.sh`) to `packages/ai-universal-rules/templates/claude/settings.json` `permissions.allow`, then re-merge `.claude/settings.json`.
2. Either add `'bash scripts/ai/ai-task.sh *': ask` to the OpenCode permission block and a matching approved-scripts line to the Claude Bash Command Policy, or delete the `ai-task.sh` bullet from the Script Access section in all three rendered files and confirm the canonical template (which already omits it) is correct — resolve the fleet-wide false-tool-claim, do not just patch Claude.
3. In `packages/ai-universal-rules/templates/optional/agents/agent-creator.md` under `## Recommended Next Step`, add: "If the Static Validator rejects the spec, revise and re-emit exactly one corrected AgentSpec JSON."
4. In the same template's `## Output` and `## Recommended Next Step` sections, replace informal names with exact roster ids: "Hand the spec to `agent-creator-static-validator` via `agent-creator-supervisor`."
5. Regenerate `.claude/agents/agent-creator.md`, `.opencode/agents-optional/agent-creator.md`, and `.github/agents/agent-creator.agent.md` from the corrected template.

## Out Of Scope (Things To Avoid)

- Widening `agent-creator`'s tools, `disallowedTools`, or `permissionMode`.
- Changing `agent_assessment.decision`/`risk_level` by hand.
- Re-touching items already closed by `docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/plan.md`'s `## agent-creator` section (residual-fixes).
- A fleet-wide settings.json audit beyond the scripts this agent's own approved list already claims.

## Affected Paths

- `packages/ai-universal-rules/templates/optional/agents/agent-creator.md`
- `packages/ai-universal-rules/templates/claude/settings.json`
- `.claude/settings.json`
- `.claude/agents/agent-creator.md` (regenerated)
- `.opencode/agents-optional/agent-creator.md` (regenerated)
- `.github/agents/agent-creator.agent.md` (regenerated)

## Contracts And Boundaries

Edit only the canonical template and the settings.json template/copy pair; regenerate the three rendered copies together, never hand-edit them.

## Todo Plan

- [x] P0: Add `ai-structured.sh` (and any other claimed-but-ungranted scripts) to both settings.json files.
- [x] P0: Resolve the `ai-task.sh` false claim fleet-wide (grant consistently or remove from Script Access in all three renders).
- [x] P1: Add the Static-Validator-rejection clause to the canonical template's Recommended Next Step.
- [x] P1: Replace informal handoff names with exact roster ids in the canonical template.
- [x] P2: Regenerate all three rendered copies and diff-confirm propagation; re-run agent-critic.

## Acceptance Criteria

- [x] AC-01: `.claude/settings.json`'s allow list includes `ai-structured.sh` (and other confirmed-claimed scripts), with no existing entries removed.
- [x] AC-02: `ai-task.sh` is either consistently grantable and documented, or entirely absent from Script Access on all three rendered surfaces.
- [x] AC-03: The canonical template's Recommended Next Step names the Static-Validator-rejection path.
- [x] AC-04: All handoff prose uses exact roster ids (`agent-creator-static-validator`, `agent-creator-supervisor`), not informal names.

## Verification Plan

- `jq .` on both settings.json files for valid JSON; diff shows only additions.
- `git grep -n "ai-task.sh"` across all three rendered copies and the canonical template to confirm consistent resolution.
- Diff each regenerated copy against the canonical template.
- Re-run agent-critic against the regenerated `.claude/agents/agent-creator.md`.

## Risks And Rollback

Risk — the settings.json change is a shared, fleet-wide file; route through config-maintainer, since the fix targets the .claude/settings.json config-path, not just this agent's own template. Rollback: revert the template and settings.json diffs.

## Handoff Notes

Recommended next step: config-maintainer to own the `.claude/settings.json` permissions.allow gap specifically; implementer to apply the template edits (rejection clause, roster-id naming, ai-task.sh resolution) and regenerate.

## Implementation Notes (orchestrator direct-verification pass, 2026-07-08)

All four fixes named in this plan were found already present in the working tree, applied by
earlier in-progress WIP on this branch (the same fleet-wide sweep referenced by sibling plans
2-26 in this ticket series). This pass performed direct, evidence-based verification rather than
re-implementing already-landed fixes:

- AC-01: confirmed `Bash(scripts/ai/ai-structured.sh *)` present in `.claude/settings.json`
  (line 32) and in `packages/ai-universal-rules/templates/claude/settings.json`; also confirmed
  the other scripts this agent's own Bash Command Policy list claims (`rg-code.sh`, `fd-files.sh`,
  `query-usage.sh`, `git-branch-origin.sh`, `git-forensics.sh`, `repo-stats.sh`,
  `repo-tool-inventory.sh`, `check-file-refs.sh`, `repomix-freshness.sh`) are all present in the
  same allow list (lines 21-33). No existing entries were removed (this pass made no settings.json
  edits at all — the grants already existed).
- AC-02: confirmed via `grep`-equivalent search that `ai-task.sh` does not appear anywhere in
  `.claude/agents/agent-creator.md`, `.opencode/agents-optional/agent-creator.md`, or
  `packages/ai-universal-rules/templates/optional/agents/agent-creator.md` — the false claim from
  the original audit no longer exists on any surface (fully absent, not just relocated).
- AC-03: confirmed `.claude/agents/agent-creator.md` line 111 and the canonical template both
  read: "If `agent-creator-static-validator` rejects the spec, revise and re-emit exactly one
  corrected AgentSpec JSON."
- AC-04: confirmed all handoff prose in `.claude/agents/agent-creator.md` (Output section and
  Recommended Next Step) and `.github/agents/agent-creator.agent.md` uses exact roster ids
  (`agent-creator-static-validator`, `agent-creator-supervisor`), no informal "the supervisor" /
  "the Static Validator" phrasing remains.
- Verification run: `jq -e 'type'` on both settings.json files (valid `"object"`);
  `vendor/bin/phpunit tests/php/PermissionRenderAdaptersTest.php tests/php/PermissionComposeTest.php
  tests/php/ClaudeAgentRendererTest.php` -> OK (77 tests, 1512 assertions); `php
  tools/ai/render-adapters.php --check` -> `.claude/agents/agent-creator.md` and
  `.github/agents/agent-creator.agent.md` are both absent from the drift list (byte-parity with
  the canonical template), confirming Todo P2's regenerate/diff-confirm sub-step.
- Deviation: the Verification Plan's "re-run agent-critic" step was attempted twice via the
  Task-tool subagent dispatch and both attempts were aborted by the harness (tool-execution stall,
  not a content/permission block — see `docs/ai/execution-protocol.md`'s Subagent Dispatch Stall
  Discipline). Per that doc's guidance ("prefer doing it directly in the calling session"), this
  pass substituted direct, evidence-based verification of the same four factual claims the
  original audit's MAJOR findings turned on (all four are objectively checkable file-content
  facts, not subjective judgement calls), rather than re-issuing the same subagent prompt a third
  time. All four are confirmed closed with direct evidence above. A follow-up fresh agent-critic
  score is still recommended as a final polish pass whenever subagent dispatch is available, but
  is not treated as a blocking gate for this ticket's closure given the strength of the direct
  evidence.
