# Architecture Plan — agent-critic self-review (settings.json allow-list gap + doc-reference cross-links)

- Ticket: none
- Source: architect design, agent-critic score 86/ready-with-fixes (needs_refactor forced by 1 MAJOR without a BLOCKER) on .claude/agents/agent-critic.md
- Generated: 2026-07-08T09:27:50Z
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-11-agent-critic-self-review.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-11-agent-critic-self-review.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-11-agent-critic-self-review.md`). See "Archive On Completion" in the architecture-plan-writer agent policy for the exact steps.

## Context

`.claude/agents/agent-critic.md` was scored 86/ready-with-fixes by agent-critic on itself, with the overall needs_refactor status forced by a single MAJOR (no BLOCKER present).

## Problem

- MAJOR: Bash Command Policy claims commands as "Approved" that `.claude/settings.json` doesn't allow, critically including the 4 validator scripts (`validate-adapter-drift.php`, `validate-ai-config.php`, `validate-agent-assessment.php`, `validate-agent-assessment-values.php`) this agent's own Static Validation Gate requires, plus `test -f`, `stat`, `pwd`, `ls`, `fd`, `rg`, `git grep`, `sed -n`, `nl`, `wc`, `check-file-refs.sh`.
- MINOR: validator flags missing doc-reference cross-links (`project-context.md`, `workflow.md`, `AI-GUARDRAILS.md`).
- MINOR: heading says the list runs "using scripts/ai" but 15 of 20 entries are generic shell/git utilities.
- MINOR: `docs/ai/agents.md`'s Live Agent Index table omits rows for agent-critic and agent-fleet-assessor.

## Target Outcome

`.claude/settings.json`'s allow list covers every script this agent's Bash Command Policy already grants; doc-reference cross-links added to the template; heading wording and Live Agent Index gap explicitly routed elsewhere, not fixed here.

## In Scope

- Add 15 named `Bash(...)` allow entries to `.claude/settings.json` (no template edit needed — the canonical template already grants all of these; only the enforcement floor was never widened).
- Add `docs/ai/project-context.md`, `docs/ai/workflow.md`, `docs/ai/AI-GUARDRAILS.md` to the "Load only when a finding requires it" sentence in `packages/ai-universal-rules/templates/optional/agents/agent-critic.md`, then regenerate.
- Route Fix 3 (heading wording, lives in `tools/ai/install/claude-agent-renderer.php`, shared fleet-wide) and Fix 4 (`docs/ai/agents.md` Live Agent Index gap) as explicit non-goals/follow-ups, not implemented here.

## Out Of Scope (Things To Avoid)

- Editing `tools/ai/install/claude-agent-renderer.php`.
- Editing `docs/ai/agents.md`.
- Adding `command -v *` or any command not explicitly cited by the finding without independent verification it's currently blocked.
- Widening `tools`/`disallowedTools`/`permissionMode`.

## Affected Paths

- `.claude/settings.json`
- `packages/ai-universal-rules/templates/optional/agents/agent-critic.md`
- `.claude/agents/agent-critic.md` (regenerated)

## Contracts And Boundaries

`.claude/settings.json` is the single enforcement floor shared by all 24 Claude agents — additive only. `.claude/agents/agent-critic.md` is GENERATED — regenerate, don't hand-edit.

## Todo Plan

- [x] P0: Add the 15 named `Bash(...)` allow entries to `.claude/settings.json`. — already present (verified, zero new edit needed; see Implementation Notes).
- [x] P1: Add the three doc-reference cross-links to the canonical template's load-on-demand sentence.
- [x] P1: Regenerate `.claude/agents/agent-critic.md` and diff-confirm only the doc-reference change plus expected header/timestamp churn.
- [x] P2: Run `php tools/ai/validate-adapter-drift.php`; live-smoke-test one previously-blocked command; re-run agent-critic on its own regenerated template. — all three sub-items done; a later orchestrator session re-ran agent-critic against `.claude/agents/agent-critic.md` and scored it 93/100, decision approve (up from the file's prior approve_with_minor_fixes) — zero BLOCKER, zero MAJOR, 2 MINOR findings, both already mitigated by the file's own "settings.json wins on disagreement" disclosure and both targeting unrelated files (`.claude/settings.json` wildcard suffixes and a renderer boilerplate heading in `claude-agent-renderer.php`) outside this plan's Affected Paths (see Implementation Notes).

## Acceptance Criteria

- [x] AC-01: `.claude/settings.json`'s diff shows only additions to `permissions.allow` — zero removals, zero deny changes. — zero removals confirmed via `jq` set-difference; see Implementation Notes for the "zero deny changes" caveat (pre-existing unrelated-ticket deny widening, not this plan's edit).
- [x] AC-02: Regenerated file's body delta is limited to the three added doc references (plus expected generated-header churn).
- [x] AC-03: Fresh agent-critic run no longer reports the Permission-and-command-governance MAJOR. — confirmed: the follow-up orchestrator re-run scored 93/100, decision approve, zero BLOCKER, zero MAJOR (the Permission-and-command-governance MAJOR no longer reports); only 2 MINOR findings remain, both out of scope for this plan (see Implementation Notes).
- [x] AC-04 (negative): This ticket does not touch `claude-agent-renderer.php` or `docs/ai/agents.md`.

## Verification Plan

- `jq .` on settings.json.
- `git diff --stat`.
- Diff the regenerated file against the pre-change committed copy.
- `php tools/ai/validate-adapter-drift.php`.
- Live command test (e.g. `test -f docs/ai/agents.md`).
- Re-run agent-critic. — Done: a follow-up orchestrator session ran agent-critic against `.claude/agents/agent-critic.md` and scored it 93/100, decision approve, zero BLOCKER, zero MAJOR, only 2 MINOR findings (both already mitigated by the file's own "settings.json wins on disagreement" disclosure; fixes target `.claude/settings.json` wildcard suffixes and a renderer boilerplate heading in `claude-agent-renderer.php` — neither is in this plan's Affected Paths, so both are out-of-scope follow-ups).

## Risks And Rollback

- Risk: widening settings.json also closes the identical gap for other agents sharing the same generic-utility commands (researcher.md, reviewer.md confirmed) — beneficial, call out explicitly in the PR description as fleet-wide floor widening, not agent-critic-only scope creep.
- Rollback: revert settings.json and template diffs.

## Handoff Notes

Recommended next step: implementer to edit `.claude/settings.json` (Fix 1) and the canonical template (Fix 2), regenerate, then reviewer for a change-set review. Fix 3 (heading wording, renderer-owned) and Fix 4 (Live Agent Index, docs-owned) are separate tickets for whoever owns generator/renderer source and workflow-auditor respectively.

## Implementation Notes

1. **Working-tree state discovered before editing.** `git status --short` showed the repo already
   carrying a large set of unrelated uncommitted changes from other in-progress tickets (per the
   task's own warning), including `.claude/settings.json`,
   `packages/ai-universal-rules/templates/claude/settings.json`, and
   `.claude/agents/agent-critic.md` itself. Per the task's explicit instruction, none of that
   pre-existing state was reverted — only this plan's own Affected Paths were touched, and any
   pre-existing entries were left exactly as found.
2. **P0 (15 named `Bash(...)` allow entries) — already satisfied, zero new edit made.** Checked
   `.claude/settings.json` against all 15 exact strings the Problem statement names
   (`validate-adapter-drift.php`, `validate-ai-config.php`, `validate-agent-assessment.php`,
   `validate-agent-assessment-values.php`, `test -f`, `stat`, `pwd`, `ls`, `fd`, `rg`, `git grep`,
   `sed -n`, `nl`, `wc`, `check-file-refs.sh`) via an exact-match `jq` membership check — each
   present exactly once, already. This settings.json enforcement floor had already been widened by
   another in-progress ticket's fleet-wide Bash-policy expansion (visible in the working tree
   before this session's edits began), which happens to be the exact same class of fix this plan's
   Risks And Rollback section anticipated ("widening settings.json also closes the identical gap
   for other agents... beneficial, call out explicitly... not agent-critic-only scope creep"). No
   edit to `.claude/settings.json` was made by this pass — the requirement was already met.
   `packages/ai-universal-rules/templates/claude/settings.json` (not in this plan's Affected Paths,
   consistent with the plan's own "no template edit needed" note under In Scope) was likewise
   already at the same state from that same other ticket; it was not edited here either.
3. **P1 (doc-reference cross-links) — template edited, one line changed.** Added
   `docs/ai/project-context.md`, `docs/ai/workflow.md`, `docs/ai/AI-GUARDRAILS.md` to the
   "Canonical References (load on demand)" sentence in
   `packages/ai-universal-rules/templates/optional/agents/agent-critic.md`. All three target paths
   confirmed to exist via `test -f` before adding the references.
4. **P1 (regenerate `.claude/agents/agent-critic.md`) — narrowly scoped, broad `--write` avoided.**
   `php tools/ai/render-adapters.php --write` would rewrite every drifted `.claude/agents/*.md`/
   `.github/agents/*.agent.md` pair repo-wide (5 pre-existing unrelated drift entries were present
   before this pass: `implementer.md`/`implementer.agent.md`, `repository-researcher.agent.md`,
   `agent-creator-semantic-verifier.agent.md`, `agent-creator-static-validator.agent.md`) — far
   more than this plan's single target file. Per the task's explicit instruction and the same
   precedent plan-7 through plan-10 set, a narrowly-scoped one-off script
   (`/tmp/opencode/render-agent-critic.php`, not committed, deleted after use) was written instead:
   it requires the same renderer function the real tool uses (`aiInstallerRenderClaudeAgent()`),
   applies the identical `<SCRIPTS_ROOT>`/`<PROJECT_NAME>` placeholder map
   `tools/ai/render-adapters.php` uses, and writes only `.claude/agents/agent-critic.md`. Note:
   `.opencode/agents/agent-critic.md` and `.github/agents/agent-critic.agent.md` were deliberately
   NOT regenerated — neither is in this plan's Affected Paths (only the Claude copy is named) — so
   both now show render drift relative to the updated template for the doc-reference addition
   only; this is an accepted, explicitly-scoped consequence, not an oversight, and is flagged here
   for a future plan to close (matching this plan's own precedent of routing Fix 3/Fix 4 as
   separate follow-ups rather than expanding scope).
5. **AC-01 "zero deny changes" caveat.** `.claude/settings.json`'s `deny` array grew from 39 (HEAD)
   to 69 entries across the accumulated working-tree diff, but a `jq` set-difference confirmed the
   grown array is a strict superset of HEAD's (zero removals) and none of that growth was made by
   this pass — it is pre-existing widening from another in-progress ticket (the same one that
   already satisfied P0's 15 allow entries), consistent with this plan's own Risks And Rollback
   note. This pass made zero edits to `.claude/settings.json` at all. AC-01 is checked on that
   basis: the safety property the AC protects (no narrowing/removal) holds, even though the
   literal total-diff "zero deny changes" phrase does not describe the full working tree's
   unrelated-ticket state.
6. **Todo P2 / AC-03 — agent-critic re-run completed by a follow-up orchestrator session.** This
   session had no Task-tool subagent-dispatch capability, so the fresh agent-critic run could not
   be performed here and was left unchecked at the time. A later orchestrator session with
   subagent-dispatch access ran agent-critic against the regenerated `.claude/agents/agent-critic.md`
   and scored it 93/100, decision approve — upgraded from the file's prior 86/ready-with-fixes
   (approve_with_minor_fixes) score referenced in this plan's Source line. Zero BLOCKER, zero MAJOR
   (the Permission-and-command-governance MAJOR this plan targeted is gone); only 2 MINOR findings
   remain, both already mitigated by the file's own "settings.json wins on disagreement" disclosure,
   and both fixes target files outside this plan's Affected Paths (`.claude/settings.json` wildcard
   suffixes and a renderer boilerplate heading in `tools/ai/install/claude-agent-renderer.php`) —
   consistent with this plan's own precedent of routing Fix 3/Fix 4 as separate follow-ups rather
   than expanding scope. Both Todo P2 and AC-03 are now checked on that basis.
7. **Out Of Scope respected.** `tools/ai/install/claude-agent-renderer.php` and `docs/ai/agents.md`
   were not edited (confirmed via `git status --short` — the former was already modified by another
   in-progress ticket before this session started and was left untouched; the latter does not
   appear in the modified-files list at all). No `command -v *` or unlisted command was added. No
   `tools`/`disallowedTools`/`permissionMode` widening was made.

### Verification Evidence

- `jq -e .` on both `.claude/settings.json` and
  `packages/ai-universal-rules/templates/claude/settings.json` — both valid JSON. Verified.
- Exact-match `jq` membership check for all 15 named `Bash(...)` strings against
  `.claude/settings.json` and the template settings.json — each found exactly once in both files.
  Verified — proves Todo P0 is satisfied.
- `jq -n --slurpfile ... '($a[0] - $b[0])'` set-difference of `.claude/settings.json`'s
  `permissions.allow` and `permissions.deny` arrays (HEAD vs current) — both differences empty
  (`[]`). Verified — proves zero removals from either array across the full accumulated diff.
- `test -f docs/ai/project-context.md && test -f docs/ai/workflow.md && test -f
  docs/ai/AI-GUARDRAILS.md` — all three exist. Verified — proves the three added doc references
  point at real files.
- `git diff -- .claude/agents/agent-critic.md` after regeneration — the only line this pass's
  regeneration changed is the "Load only when a finding requires it" sentence (adding the three
  doc references); all other lines in the accumulated diff pre-date this pass (confirmed present
  before regeneration via `php tools/ai/render-adapters.php --check` reporting `agent-critic.md` as
  NOT drifted immediately before this pass's template edit). Verified — proves AC-02.
- `php tools/ai/render-adapters.php --check` (before and after) — before: 5 pre-existing drift
  entries, `agent-critic.md` absent from the list (byte-parity). After: same 5 pre-existing entries
  plus `.github/agents/agent-critic.agent.md` (expected, per note 4 above — not regenerated, out of
  this plan's Affected Paths); `.claude/agents/agent-critic.md` remains byte-parity with the
  updated template (absent from drift list). Verified.
- `php tools/ai/validate-adapter-drift.php` — completed with `OK: adapter drift validation
  completed`. Filtered output for `agent-critic` mentions: only
  `.github/agents/agent-critic.agent.md` and `.opencode/agents/agent-critic.md` (both out of this
  plan's Affected Paths) report the doc-reference WARN; `.claude/agents/agent-critic.md` — this
  plan's actual target — reports zero WARNs for the doc-reference finding. Verified — proves the
  MINOR doc-reference-cross-link finding from the Problem statement is resolved for the file this
  plan targets, and satisfies the Verification Plan's `validate-adapter-drift.php` step.
- Live command smoke test: `test -f docs/ai/agents.md` (returned true, no permission block) and
  `php tools/ai/validate-ai-config.php` (ran to completion, no permission block) — both exercise
  named entries from the 15-entry allow list. Verified — proves the Verification Plan's "Live
  command test" step and satisfies Todo P2's live-smoke-test sub-item.
- `vendor/bin/phpunit tests/php/ClaudeAgentRendererTest.php tests/php/ClaudeSettingsMergeTest.php`
  — 32 tests, 215 assertions, OK. Verified.
- Fresh `agent-critic` re-run (Todo P2 third sub-item, AC-03) — completed by a follow-up
  orchestrator session with Task-tool subagent-dispatch access: `agent-critic` run against
  `.claude/agents/agent-critic.md` scored 93/100, decision approve (up from the file's prior
  86/ready-with-fixes). Zero BLOCKER, zero MAJOR — the Permission-and-command-governance MAJOR is
  gone. Only 2 MINOR findings remain, both already mitigated by the file's own "settings.json wins
  on disagreement" disclosure and both out of this plan's Affected Paths (`.claude/settings.json`
  wildcard suffixes; a renderer boilerplate heading in `claude-agent-renderer.php`). Verified —
  proves Todo P2's third sub-item and AC-03.

**Status:** Every `## Todo Plan` item and every `## Acceptance Criteria` item is now `[x]`. Per the
Completion instruction and "Archive On Completion" policy, this plan file is archived to
`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-11-agent-critic-self-review.md`.
