# Architecture Plan — Architect self-review Claude fixes (settings.json coverage + external-directory enforcement)

- Ticket: none
- Source: architect design, agent-critic score 84/needs_refactor on .claude/agents/architect.md
- Generated: 2026-07-08
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-15-architect-self-review-claude-fixes.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-15-architect-self-review-claude-fixes.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-15-architect-self-review-claude-fixes.md`). See "Archive On Completion" below for the exact steps.

## Context

architect.md scored 84/needs_refactor on a fresh agent-critic pass. This ticket implements the narrowly-scoped Claude-specific fixes (Fix A regeneration, Fix B renderer edit) and explicitly defers the fleet-wide settings.json coverage gap (Fix C) to workflow-auditor.

## Problem

MAJOR — body's "Approved scripts" list is not covered by .claude/settings.json's actual allow list (rg-code.sh, fd-files.sh, check-file-refs.sh, ai-doc-check.sh, ai-structured.sh, ai-file-freshness.sh, repo-stats.sh, repo-tool-inventory.sh, git-branch-origin.sh, repomix-freshness.sh, php tools/ai/ai.php, and generic readers all claimed approved but absent from the enforced settings file) — this is a cross-agent, shared settings.json gap, best routed through workflow-auditor for a fleet-wide sync. MAJOR — the "ask before reading" external-directory boundary rule has no technical enforcement (Read tool unrestricted, settings.json only blocks secret-file patterns). MINOR — the same script list is called both "the enforced boundary" and "advisory body policy" within the same file (a smaller instance of a pattern scored as a full BLOCKER elsewhere in the fleet — workflow-auditor.md, agent-fleet-assessor.md).

## Target Outcome

Fix A (mechanical, already-fixed-in-renderer, just never applied to this file) closes the self-contradiction MINOR; Fix B (renderer edit) closes the external-directory enforcement gap fleet-wide for every agent sharing this same section; Fix C (settings.json coverage) is explicitly deferred to workflow-auditor as its own fleet-wide ticket, not implemented here.

## In Scope

- Fix A: Re-render .claude/agents/architect.md from the current (already-corrected at HEAD, commit 5e1f3f17) tools/ai/install/claude-agent-renderer.php against packages/ai-universal-rules/templates/core/agents/architect.md — no renderer code change needed, the fix already exists and was simply never applied to this file. Expected diff limited to the Bash Command Policy opening sentence.
- Fix B: Extend the existing OpenCode-phrase-neutralization block in claude-agent-renderer.php's aiInstallerRenderClaudeAgent() (the same block that already handles "external_directory: ask" phrasing) with one more transformation: append a Claude-specific honesty clause after "the runtime's external-directory approval prompt" stating this is instruction-only guidance on Claude with no enforcing tool permission. This regenerates identically for every Claude agent carrying the same External Context Boundary language, not just architect.

## Out Of Scope (Things To Avoid)

- Hand-editing .claude/agents/architect.md's generated content.
- Broadening .claude/settings.json's allow list as part of this ticket (Fix C, deferred to workflow-auditor).
- Rewriting the External Context Boundary body prose in the shared canonical template (the gap is Claude-specific enforcement, not universal wording — belongs in the renderer per the Provider-Agnostic Design Rule).
- Re-rendering any other agent file in this pass (the same stale "enforced boundary anyway" wording affects all 24 .claude/agents/*.md files — fixing that fleet-wide is workflow-auditor's job, not bundled here).
- Modifying tools/ai/install/permission-layers/*.

## Affected Paths

- .claude/agents/architect.md (regenerated)
- tools/ai/install/claude-agent-renderer.php (lines ~104-109, extended)

## Contracts And Boundaries

Fix A is regeneration-only, no source edit. Fix B is a renderer edit affecting every Claude agent sharing the External Context Boundary section (fleet-wide benefit from one change, verified before merge).

## Todo Plan

- [x] P0: Regenerate .claude/agents/architect.md from the current renderer + template (Fix A) and confirm the diff is limited to the Bash Command Policy opening sentence.
- [x] P0: Extend the OpenCode-phrase-neutralization block in claude-agent-renderer.php with the Claude-specific external-directory honesty clause (Fix B).
- [x] P1: Regenerate architect.md again (and spot-check one or two other agents carrying the same External Context Boundary section) to confirm Fix B propagates correctly.
- [x] P2: Run tests/php/PermissionRenderAdaptersTest.php and `php tools/ai/generate-agent-permissions.php --check`; recommend workflow-auditor open a fleet-wide ticket for Fix C (settings.json sync) and for the fleet-wide "enforced boundary anyway" regeneration.

## Acceptance Criteria

- [x] AC-01: `rg -n "enforced boundary" .claude/agents/architect.md` returns no match after Fix A; the file still contains "advisory body policy" in a mutually consistent context.
- [x] AC-02: The regenerated External Context Boundary sentence explicitly states the rule is instruction-only on Claude with no enforcing tool permission.
- [x] AC-03: `php tools/ai/generate-agent-permissions.php --check` and the PHPUnit suite pass with no regression.
- [x] AC-04 (negative): This ticket does not widen .claude/settings.json's allow list.

## Verification Plan

- `git diff .claude/agents/architect.md` scoped check proves AC-01.
- `rg` checks per AC-01/AC-02.
- Run tests/php/PermissionRenderAdaptersTest.php proves AC-03.
- Spot-check regeneration of 1-2 sibling agents sharing the External Context Boundary section for regression, proves AC-02 propagation.
- Diff `.claude/settings.json` before/after proves AC-04 (negative).

## Risks And Rollback

Risk — exact regeneration entrypoint used in commit 5e1f3f17 for 7 files is unconfirmed; implementer should confirm before running rather than assume install-ai-kit.sh works self-referentially. Risk — regenerating only architect.md while 23 other files remain stale (with the same old wording) is an intentionally narrow slice — call this out explicitly so it isn't mistaken for "fleet done." Risk — Fix B's renderer edit is untested against every agent body variant; verify against more than architect's exact wording before calling it fleet-safe. Rollback: revert the renderer edit and the regenerated file.

## Handoff Notes

Recommended next step: implementer to execute Fix A (regenerate architect.md) and Fix B (renderer edit + targeted regeneration); separately flag Fix C (settings.json sync) for workflow-auditor to scope as its own fleet-wide ticket.

**Recommendation for workflow-auditor (per Todo P2, not resolved in this slice):** two fleet-wide
follow-ups remain open: (1) Fix C — sync `.claude/settings.json`'s `permissions.allow` list against
every shipped Claude agent's actual "Approved scripts" body text (this session found architect's
own list already partially covered by pre-existing in-flight `.claude/settings.json` changes, but a
systematic per-agent audit was not performed here, per Out Of Scope); (2) a fleet-wide re-render of
all `.claude/agents/*.md` files against the current (Fix-A/Fix-B-updated) renderer + templates —
`php tools/ai/render-adapters.php --check` currently lists 29 drifted files (architect.md, this
plan's own regen target, is the only one this session closed); workflow-auditor should scope and
sequence that fleet-wide `--write` once the other in-flight per-agent remediation tickets in this
same branch have landed, to avoid clobbering their own uncommitted work.

## Implementation Notes

**Working-tree state discovered before editing.** `git status --short` showed 149 files of unrelated
uncommitted changes from other in-progress tickets (per the task's own warning), including
`.claude/agents/architect.md`, `packages/ai-universal-rules/templates/core/agents/architect.md`,
`tools/ai/install/claude-agent-renderer.php`, and `.claude/settings.json` — all already `M`
(modified) before this session started. Reading `.claude/agents/architect.md`'s pre-existing
working-tree diff against HEAD showed **Fix A's outcome was already present**: the "enforced
boundary anyway" sentence was already rewritten to "Treat the following list as required agent
policy; hard enforcement depends on `.claude/settings.json` or runtime hooks." — a side effect of
another in-progress ticket's own broader regeneration pass (which also carried unrelated
Architecture-Diagram-Mermaid template additions, plan-13's "Full per-script..." Script Access fix,
and a refactorer-only Bash Command Policy bullet-collapse feature, none of which are this plan's
concern). `rg -n "enforced boundary"` against the working-tree file confirmed zero matches even
before this session's own edits — AC-01's first half was already satisfied. This matches the
plan-13/plan-14 precedent of finding partial pre-existing sync from concurrent in-progress work.

1. **Fix B context check (per the task's explicit instruction).** Confirmed plan-13's own fix (the
   "Full per-script `allow`/`ask`/`deny` is in frontmatter" false-claim correction, landed in
   `claude-agent-renderer.php` lines ~169-179) is present and distinct from this plan's Fix B (the
   external-directory enforcement honesty clause) — no duplication, no overlap; both fixes coexist
   in the same function without conflict.
2. **Fix B (renderer edit).** Added one more `preg_replace()` to the existing OpenCode-phrase-
   neutralization block in `aiInstallerRenderClaudeAgent()` (`tools/ai/install/
   claude-agent-renderer.php`): after normalizing "OpenCode `external_directory: ask` prompt" ->
   "the runtime's external-directory approval prompt" (and stripping the OpenCode-specific
   parenthetical for implementer's variant), a new transformation appends
   `(instruction-only on Claude Code; no tool permission enforces this boundary)` immediately after
   the phrase "external-directory approval prompt" wherever it appears in a rendered Claude agent
   body. Confirmed via `rg -n "external_directory"` across all core+optional agent templates that
   exactly 4 agents carry this prose pattern (architect, reviewer, researcher, implementer); the new
   transformation is a no-op for the other ~22 agents (no match, no effect).
3. **Fix A + P1 (scoped regeneration).** Per the task's explicit instruction, the broad
   `php tools/ai/render-adapters.php --write` was **not** run (it has no per-agent filter and would
   have rewritten all 29 currently-drifted files, clobbering unrelated in-progress-ticket work). A
   narrowly-scoped one-off PHP script (`/tmp/opencode/render-plan15-scoped.php`, not committed,
   mirrors the plan-7/plan-13/plan-14 precedent) was written instead: it calls the exact same
   `aiInstallerRenderClaudeAgent()` function the real tool uses, writes only
   `.claude/agents/architect.md` (this plan's sole Affected regenerated path), and additionally
   renders (but does NOT write into the repo — writes to `/tmp/opencode/spot-check-*.md` instead)
   the reviewer and researcher Claude bodies for spot-check comparison only, per Out Of Scope's
   explicit "Re-rendering any other agent file in this pass" prohibition.
4. **P1 (spot-check propagation).** `rg -n "external-directory approval prompt"` against
   `/tmp/opencode/spot-check-reviewer.md` and `/tmp/opencode/spot-check-researcher.md` confirmed the
   new honesty clause renders identically in both, byte-for-byte matching the clause landed in the
   real `architect.md` write. Neither temp file was copied into the repo.
5. **P2 (tests + recommendation).** Ran the tests named in the plan (see Verification Evidence
   below) and recorded the Fix C / fleet-wide-regen recommendation for workflow-auditor in Handoff
   Notes above — this required no subagent dispatch, only direct command execution and
   documentation, so it is not blocked by this session's lack of Task-tool access.

### Verification Evidence

- `rg -n "enforced boundary" .claude/agents/architect.md` — no matches. Verified — proves AC-01
  (first half).
- `rg -n "advisory body policy" .claude/agents/architect.md` — 1 match, line 98, in the same
  mutually-consistent "Hard enforcement (beyond this advisory body policy) lives in
  `.claude/settings.json`..." context as before. Verified — proves AC-01 (second half).
- `git diff -- .claude/agents/architect.md` — 4 hunks: the Fix-A Bash Command Policy opening
  sentence (pre-existing before this session, confirmed unaffected by this session's own edits),
  the Fix-B External Context Boundary clause (new, added by this session's scoped regen), the
  plan-13 Script Access sentence correction (pre-existing), and unrelated Mermaid-diagram template
  additions from another in-progress ticket (pre-existing, not touched by this plan). The actual
  diff is broader than the plan's "limited to the Bash Command Policy opening sentence" expectation
  because concurrent in-progress template/renderer edits from other tickets were already present in
  the working tree before this session started (see Implementation Notes above) — this plan's own
  contribution to that diff is exactly the Fix-B clause (1 line changed). Verified.
- `rg -n "external-directory approval prompt"` against `/tmp/opencode/spot-check-reviewer.md` and
  `/tmp/opencode/spot-check-researcher.md` — both contain
  "...external-directory approval prompt (instruction-only on Claude Code; no tool permission
  enforces this boundary) and sensitive-file rules..." identically. Verified — proves AC-02's
  fleet-wide propagation (Todo P1).
- `vendor/bin/phpunit tests/php/PermissionRenderAdaptersTest.php` — 14 tests, 314 assertions, OK.
  Verified.
- `vendor/bin/phpunit tests/php/PermissionRenderAdaptersTest.php tests/php/PermissionComposeTest.php
  tests/php/ClaudeAgentRendererTest.php` — 74 tests, 1504 assertions, OK (broader targeted
  regression check for the renderer edit). Verified.
- `php tools/ai/generate-agent-permissions.php --check` — reports 1 pre-existing drift entry
  (`.opencode/agents-optional/build-config.md`), confirmed via `git status --short -- .opencode/
  agents-optional/build-config.md` (no output — file is byte-identical to git HEAD, untouched by
  this session or this plan's Affected Paths) to be unrelated pre-existing drift, not a regression
  introduced by Fix A/Fix B. Proves AC-03 in the "no regression" sense (same treatment as
  DONE-plan-13's AdapterRenderDriftTest informational note).
- `composer test:fast` — 925 tests, 12514 assertions, 3 failures: `AdapterRenderDriftTest` (x2) and
  `AgentPermissionDriftTest::testManagedAgentsHaveNoDrift` (x1). Isolated re-run of
  `AdapterRenderDriftTest` alone confirmed its drift list is the same 29-entry pre-existing
  fleet-wide baseline (`architect.md` absent — already fixed this session); the
  `AgentPermissionDriftTest` failure is the same single `build-config.md` pre-existing entry cited
  above. None of the 3 failures reference a file this plan touched or introduced; all pre-date this
  session (confirmed via each failing file's own untouched `git status` state). Not a regression
  introduced by this plan — same disposition as DONE-plan-13's "Informational... not treated as a
  regression" note for the identical test class.
- `php tools/ai/render-adapters.php --check` before vs. after this session's scoped write — before:
  30 drifted entries including `.claude/agents/architect.md`; after: 29 entries, with
  `architect.md` now absent (byte-parity with the Fix-A/Fix-B-updated canonical template +
  renderer) and every other entry unchanged. Verified — zero new drift introduced, exactly one
  entry closed.
- `php tools/ai/ai.php placeholders --fail` — `OK: wrote docs/ai/generated/placeholders.json`, exit
  0, no unresolved-placeholder failures. Verified.
- `git status --short -- .claude/settings.json` — modified, but confirmed via this session's own
  tool-call history that no `Edit`/`Write` call ever targeted this file; its modification predates
  this session (part of the 149-file baseline). Verified — proves AC-04 (this ticket did not widen
  `.claude/settings.json`'s allow list).
- `git status --short | wc -l` before and after this session's edits — 149 both times (no new file
  entered or left the modified/untracked set; only two already-modified files,
  `.claude/agents/architect.md` and `tools/ai/install/claude-agent-renderer.php`, were further
  edited by this session). Verified.

**Status:** All Todo Plan items (P0-P2) and all 4 Acceptance Criteria are `[x]`. Per this file's own
completion instruction, archiving now proceeds: this plan is moved to
`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-15-architect-self-review-claude-fixes.md`
and replaced with a one-line tombstone, dated 2026-07-08.
