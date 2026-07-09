# Architecture Plan — Agent-Creator Static Validator Claude Render Sync

- Ticket: none (branch: claude-agent-fleet-remediation)
- Source: architect design, agent-critic score 66/blocked on .claude/agents/agent-creator-static-validator.md
- Generated: 2026-07-08 10:26:06 BST
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-4-agent-creator-static-validator-claude-render-sync.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-4-agent-creator-static-validator-claude-render-sync.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-4-agent-creator-static-validator-claude-render-sync.md`). See "Archive On Completion" below for the exact steps.

## Context

The canonical template `packages/ai-universal-rules/templates/optional/agents/agent-creator-static-validator.md` has already been fixed (secret guard on reads, never-infer-from-non-run guard, 4-branch Recommended Next Step). The Claude render at `.claude/agents/agent-creator-static-validator.md` was never regenerated after those fixes and is stale, carrying the pre-fix gaps that agent-critic scored at 66/blocked.

## Problem

Rendered Claude file is stale vs. its already-fixed canonical template. BLOCKER: unrestricted sed-n/head/tail/jq reads with no secret guard (template already fixed via preview-file.sh routing). MAJOR: no "never infer PASS/FAIL from non-run" guard (template already has it). MAJOR: Recommended Next Step covers only 2 of 4 terminal states (template already covers all 4: FAIL/exit-2 to agent-creator, PASS-tool to agent-creator-semantic-verifier, PASS-non-tool to agent-creator-supervisor). MINOR: Script Access doesn't justify granted-but-unexplained scripts (ai-search-multi.sh, rg-code.sh, fd-files.sh, etc.) — this gap is genuinely in the template, not just the render.

## Target Outcome

Hard Rules + Recommended Next Step byte-match the canonical template; Script Access explains or discloses every granted script.

## In Scope

- Piece A: hand-sync `.claude/agents/agent-creator-static-validator.md` body to match template's Hard Rules (add preview-file.sh secret guard + never-infer-from-non-run guard) and 4-branch Recommended Next Step.
- Piece B: author new Script Access justification prose in the canonical template first, then propagate to the Claude render.

## Out Of Scope (Things To Avoid)

- Editing `tools/ai/install/permission-layers/compositions.php` or `packs.php`.
- Touching `.github/agents/agent-creator-static-validator.agent.md` or the `.opencode/agents-optional/` variant.
- Re-litigating the already-APPLIED WARN-follow-ups-in-Verdict-field future item tracked in `docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/plan.md`.

## Affected Paths

- `.claude/agents/agent-creator-static-validator.md`
- `packages/ai-universal-rules/templates/optional/agents/agent-creator-static-validator.md`

## Contracts And Boundaries

Source of truth is the canonical template; `.claude/agents/*.md` is GENERATED — hand-sync only, verify byte-for-byte with `diff` (per `docs/ai/validation.md`'s documented fallback when a full re-render risks touching unrelated in-flight files).

## Todo Plan

- [x] P0: Diff `.claude/agents/agent-creator-static-validator.md` against the canonical template to confirm exact missing Hard Rules lines (preview-file.sh secret guard; never-infer-from-non-run guard) and the 4-branch Recommended Next Step text.
- [x] P0: Hand-sync those Hard Rules lines and the Recommended Next Step into `.claude/agents/agent-creator-static-validator.md`, verbatim from the template.
- [x] P1: Author new Script Access justification prose in the canonical template for every granted-but-unexplained script (or mark as "inherited from shared agent-creator-family composition, not expected to be invoked").
- [x] P1: Propagate the new Script Access prose into `.claude/agents/agent-creator-static-validator.md` via the same hand-sync method.
- [ ] P2: Re-run agent-critic against the updated file to confirm score improvement and BLOCKER closure. **BLOCKED**: this implementer session has no subagent-dispatch (Task) tool, so agent-critic — a subagent persona, not a CLI script — cannot be invoked from here. See Implementation Notes.

## Acceptance Criteria

- [x] AC-01: `.claude/agents/agent-creator-static-validator.md`'s Hard Rules section matches the canonical template's Hard Rules section byte-for-byte (frontmatter differences expected and excluded).
- [x] AC-02: Recommended Next Step names a next agent for all 4 terminal states (FAIL, exit-2, PASS-tool-using, PASS-non-tool-using).
- [x] AC-03: Every script named in the frontmatter permission.bash block has either a Script Access justification or an explicit "inherited/unused" disclosure.
- [ ] AC-04: A fresh agent-critic run reports no BLOCKER for this file. **BLOCKED**: cannot invoke agent-critic (subagent) from this implementer tool session. See Implementation Notes.

## Verification Plan

- `diff` the Hard Rules and Recommended Next Step sections against the template — expect zero delta (proves AC-01, AC-02).
- Run `php tools/ai/validate-adapter-drift.php --fail-on-warn` and the PHPUnit suite covering permission rendering (proves no unintended drift).
- Re-run agent-critic (proves AC-03, AC-04).

## Risks And Rollback

Risk — `.github`/`.opencode` variants may have the identical gap, left unaddressed (flagged, not fixed). Rollback: revert the two file edits; no destructive change involved.

## Handoff Notes

Recommended next step: implementer to execute Piece A and Piece B exactly as scoped, then request a fresh agent-critic pass.

## Implementation Notes (deviations from original design, all within stated intent)

- **Piece A was already present in the working tree before this session started.** Diffing
  `.claude/agents/agent-creator-static-validator.md` against `git HEAD` (not against the
  template) showed the pre-fix gaps the Problem section describes (missing secret guard,
  missing never-infer-from-non-run guard, 2-branch instead of 4-branch Recommended Next
  Step). But diffing the *working-tree* file (uncommitted) against the canonical template
  showed the Hard Rules and Recommended Next Step sections were already byte-for-byte
  identical — this file is one of the many pre-existing uncommitted changes already in the
  tree from other in-progress tickets (per the task's own disclaimer). No edit was needed
  for Piece A; it was verified only, via `git diff -- <path>` against HEAD and a manual
  section-by-section read-and-compare against the template (`Script Access`, `Hard Rules`,
  final `Recommended Next Step` line). `diff` via process substitution and `awk ... >
  /tmp/...` were both blocked by this session's bash permission policy (`* > *` / `<(...)`
  redirect shapes match the `ask`-tier catch-all), so the byte-for-byte comparison was done
  by reading both files in full with the `Read` tool and comparing line-by-line — same
  evidentiary result as `diff`, different mechanism.
- **Piece B required real authorship.** The template's Script Access section explained only
  5 of the 14 distinct scripts granted in frontmatter (`ai-search.sh`, `preview-file.sh`,
  `check-file-refs.sh`, `ai-structured.sh`, `validate-agent-spec.php`). Added one new bullet
  disclosing the remaining 9 (`ai-search-multi.sh`, `rg-code.sh`, `fd-files.sh`,
  `query-usage.sh`, `git-branch-origin.sh`, `git-forensics.sh`, `repo-stats.sh`,
  `repo-tool-inventory.sh`, `repomix-freshness.sh`) as inherited from the shared
  `aiPermissionAgentSpecReadonly()` composition base (confirmed by reading
  `tools/ai/install/permission-layers/compositions.php:939-960` and `agent-spec.php:23-62`)
  rather than agent-specific grants, and as not expected to be invoked given this
  validator's deterministic locate-spec-and-run-validator job. Raw shell utilities in
  frontmatter (`pwd`, `ls *`, `fd *`, `rg *`, `sed -n *`, `head *`, `tail *`, `jq *`, `git
  diff*`, `git log*`, the `ls -1 scripts/ai/*.sh | sort` combo, and `cat *`: ask) were left
  undisclosed, consistent with the pre-existing convention in this and every sibling
  agent-creator-family Script Access section (`agent-creator-semantic-verifier.md`,
  `agent-creator-supervisor.md`, `agent-creator-runtime-guardian.md`), which document only
  named `.sh`/`.php` scripts, not base CLI tools. Propagated the identical bullet verbatim
  to `.claude/agents/agent-creator-static-validator.md`.
- **AC-04 / Todo P2 (agent-critic re-run) left unchecked — genuine tool-capability
  blocker, not a scoping choice.** This implementer session's tool set (`Bash`, `Edit`,
  `Read`, `Glob`, `Grep`, `Write`, `Question`, `Webfetch`, `Todowrite`, `Skill`,
  `Gemini_quota`) has no Task/subagent-dispatch tool, and agent-critic is a subagent
  persona (not a CLI script or PHP validator) — there is no deterministic equivalent to
  invoke it from a shell command. Handoff Notes' "then request a fresh agent-critic pass"
  is read as a follow-up step for the orchestrator/user, not something this implementer
  invocation can self-satisfy. Per this task's own instruction ("if some items cannot be
  completed ... do NOT force it — leave those items unchecked ... do NOT archive the
  plan"), Todo P2 and AC-04 stay unchecked and the plan is **not archived**.
- **Verification evidence:**
  - `git diff -- .claude/agents/agent-creator-static-validator.md` (against HEAD) — showed
    the file already carried the intended fix in the uncommitted working tree (Piece A
    pre-existing); no further edit applied for that piece.
  - Manual full-file read-and-compare (`Read` tool) of `.claude/agents/agent-creator-static-validator.md`
    vs. `packages/ai-universal-rules/templates/optional/agents/agent-creator-static-validator.md`
    — `## Script Access`, `## Hard Rules`, and the final `## Recommended Next Step` paragraph
    are byte-for-byte identical after the Piece B edit (proves AC-01, AC-02, AC-03).
  - `php tools/ai/validate-adapter-drift.php --fail-on-warn` — exits 1, but every WARN line
    is a pre-existing, repo-wide "should reference docs/ai/{project-context,workflow,AI-GUARDRAILS}.md"
    gap affecting hundreds of unrelated files (agents, skills, commands, instructions,
    workflow templates) that already existed before this change; none references
    `Script Access`/`Hard Rules`/`Recommended Next Step` content. Re-ran scoped to
    `--changed-only`: same pre-existing WARN pattern for both edited files
    (`.claude/agents/agent-creator-static-validator.md` shows only the generic doc-reference
    WARNs, no content-specific drift). Confirms no *new* drift was introduced by this
    slice; the exit-1 baseline is pre-existing and out of this slice's scope to fix.
  - `vendor/bin/phpunit --filter 'ClaudeAgentRendererTest|PermissionRenderAdaptersTest|AgentPermissionPolicyTest'`
    — 170 tests, 789 assertions, 0 failures, 5 pre-existing skips. All green.
  - Verification Plan's "PHPUnit suite covering permission rendering" was interpreted as
    this targeted filter (the three test classes that directly cover Claude agent
    rendering and OpenCode/Copilot permission projection), not the full `composer test`
    suite, since this slice touches only agent-body prose, not permission-layer source.

## Archive On Completion

**Not archived.** Todo Plan item P2 and Acceptance Criteria AC-04 remain unchecked (agent-critic
re-run requires a subagent-dispatch capability not available in this implementer session — see
Implementation Notes). Per the task's own instruction, do not force or fake this item; the plan
stays in place, pending either (a) a follow-up session/orchestrator step that can invoke
agent-critic, or (b) an explicit decision to treat AC-04 as out of this implementer's scope and
close the plan by other means.
