# Architecture Plan — Release-auditor agent-critic fixes (script coverage disclosure + stale render sync)

- Ticket: none
- Source: architect design, agent-critic score 72/ready-with-fixes (needs_refactor, no BLOCKER) on .claude/agents/release-auditor.md
- Generated: 2026-07-08
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-16-release-auditor-agent-critic-fixes.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-16-release-auditor-agent-critic-fixes.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-16-release-auditor-agent-critic-fixes.md`). See "Archive On Completion" below for the exact steps.

## Context

release-auditor.md scored 72/ready-with-fixes (needs_refactor, no BLOCKER) on a fresh agent-critic pass. Most findings close via regeneration from an already-fixed canonical template; two need small new disclosure sentences.

## Problem

MAJOR — "Approved scripts" list claims ~50 scripts, .claude/settings.json allows only ~13 (gh-pr-context.sh, check-file-refs.sh, ai-install-coverage.sh, every validate-*.php/php tools/ai/ai.php entry, raw rg/sed/jq all absent). MAJOR — ai-verify.sh recommended in body prose but excluded from the approved-command list (self-contradiction). MAJOR — Canonical References cites nonexistent docs/ai/risk-taxonomy.md (should be docs/ai/command-risk-taxonomy.md — already correct in canonical template, stale render). MAJOR — a verdict-accountability rule (name risk/owner/evidence for NOT READY/READY WITH NOTES) exists in canonical but was dropped from this render. MAJOR — secret-exposure guard weaker than canonical, no technical backstop (raw head/tail/bat/sed-n readers unrestricted, no secret-pattern deny).

## Target Outcome

Bucket A (regeneration) closes 3 of 5 findings (stale doc ref, verdict-accountability rule, secret-redirect prose) since the canonical template already has them fixed. Bucket B adds two small, honest disclosure sentences to the canonical template for the two settings.json/ai-verify.sh coverage gaps, without inventing a Claude-native technical secret-backstop.

## In Scope

- Bucket A: Regenerate .claude/agents/release-auditor.md, .opencode/agents/release-auditor.md, and .github/agents/release-auditor.agent.md via `php tools/ai/generate-agent-permissions.php --write` (matching the exact mechanism used in commit ca0e5597 for reviewer.md) — closes the stale doc ref, verdict-accountability rule, and secret-redirect prose findings with no new template content needed.
- Bucket B (in packages/ai-universal-rules/templates/core/agents/release-auditor.md): B1 — add one sentence after the ai-verify.sh (ask) Script Access bullet clarifying that ask-tier scripts require a separate per-run approval prompt and are not part of the renderer's fixed "Approved scripts" allow-list (resolves the self-contradiction without adding a settings.json entry). B2 — add one sentence to Hard Rules disclosing that .claude/settings.json's allow list is a shared, deliberately minimal fleet-wide baseline that doesn't yet enumerate every script this agent's approved list names, and that unlisted scripts will surface an interactive approval prompt (expected behavior, not a blocked command).

## Out Of Scope (Things To Avoid)

- Widening .claude/settings.json's allow list to cover the full script list (global surface, needs a separate fleet-level assessment).
- Editing tools/ai/install/claude-agent-renderer.php's shared boilerplate.
- Building any Claude-native technical secret-backstop deny rule (no such surface exists in Claude subagent frontmatter; disclosure-only is the correct, precedented posture).
- Duplicating docs/tickets/claude-agent-fleet-remediation/plan-2-opencode-secret-deny-backstop.md's OpenCode-side secret-backstop scope (it already names release-auditor as in-scope for that separate ticket).
- Hand-editing any of the three generated files directly.
- Refreshing docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/plan.md's stale release-auditor section (flag only).

## Affected Paths

- packages/ai-universal-rules/templates/core/agents/release-auditor.md
- .claude/agents/release-auditor.md (regenerated)
- .opencode/agents/release-auditor.md (regenerated)
- .github/agents/release-auditor.agent.md (regenerated)

## Contracts And Boundaries

Source of truth is the canonical template; regenerate the three adapter copies together via `--write`; never hand-edit them. plan-2 already owns the OpenCode-side secret-backstop enforcement for release-auditor — do not re-scope it here.

## Todo Plan

- [x] P0: Add the B1 sentence (ai-verify.sh ask-tier clarification) to the canonical template's Script Access section.
- [x] P0: Add the B2 sentence (settings.json shared-baseline disclosure) to Hard Rules.
- [x] P0: Run `php tools/ai/generate-agent-permissions.php --write` to propagate into all three rendered copies (closes Bucket A findings automatically since the template already has those fixes). **Deviation:** the actual body-content propagation mechanism is `tools/ai/render-adapters.php` (Claude/Copilot) plus the OpenCode copy-with-generated-header logic (`aiInstallerCopyDirAsOpenCodeAgents`), not `generate-agent-permissions.php` (which only manages the frontmatter `permission:` block and reported zero release-auditor drift throughout — see Implementation Notes). Ran via a narrowly-scoped one-off script instead of the broad `--write` sweep, per the task's explicit instruction not to clobber unrelated in-progress files.
- [x] P1: Diff-verify each of the four affected files shows only the expected drift closing plus the two new B1/B2 sentences.
- [x] P2: **Complete.** The plan.md flag-note half was already done (see Implementation Notes item 6). An orchestrator session with subagent-dispatch access ran a fresh agent-critic pass against the regenerated `.claude/agents/release-auditor.md` on 2026-07-08 and returned score 38/blocked with 4 findings (1 BLOCKER, 1 MAJOR, 2 MINOR — see Implementation Notes item 7 for detail and fixes). All 4 findings were fixed in the canonical template (findings #1 and #4) and in a release-auditor-scoped conditional in `tools/ai/install/claude-agent-renderer.php` (findings #2 and #3, since they are Claude-render-specific and the underlying phrases are shared boilerplate that other agents' Claude renders still legitimately use), then propagated to all three rendered copies via the same narrowly-scoped one-off script pattern from P0 above. Re-verified clean (see Verification Evidence below).

## Acceptance Criteria

- [x] AC-01: `rg -n "docs/ai/risk-taxonomy.md"` across all three rendered copies returns zero hits; `docs/ai/command-risk-taxonomy.md` returns one hit each.
- [x] AC-02: `rg -n "must name at least one risk"` (verdict-accountability sentence) returns one hit in each of the three copies.
- [x] AC-03: The B1 and B2 sentences appear in all three copies.
- [x] AC-04: `php tools/ai/validate-adapter-drift.php` and `php tools/ai/validate-generated-artifacts.php` pass cleanly (`OK`, no ERROR). `php tools/ai/generate-agent-permissions.php --check` still exits non-zero, but only for the single pre-existing, unrelated `.opencode/agents-optional/build-config.md` entry — confirmed present in this exact form in this session's very first (pre-edit) run of the same command, and untouched by any tool call this session made (see Implementation Notes). Same treatment as the `DONE-plan-15` precedent for the identical recurring situation.

## Verification Plan

- `git diff` on all four affected files.
- The four grep/rg checks per AC-01/AC-02/AC-03.
- Run `php tools/ai/generate-agent-permissions.php --check`, `validate-adapter-drift.php`, `validate-generated-artifacts.php` proves AC-04.
- Run the full PHPUnit suite matching the ca0e5597 precedent baseline.
- Re-run agent-critic and confirm findings closed or explicitly reclassified as disclosed-and-deferred (the settings.json coverage item).

## Risks And Rollback

Risk — B1/B2 wording may need Copilot-specific adaptation since Copilot's frontmatter shape may already express ask-tier differently — flag to adapter-drift capability during implementation. Risk — this branch has other uncommitted changes to the canonical template/OpenCode render/renderer; verify no collision before running --write, and sequence before or in coordination with plan-2's own eventual --write pass. Rollback: revert the template edit and re-run --write to restore.

## Handoff Notes

Recommended next step: implementer to execute Bucket A regeneration and Bucket B's two template additions, run the full verification plan, then re-run agent-critic for the closing score. Note in the plan that docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/plan.md's release-auditor section is stale for the Claude render and plan-2 already owns the OpenCode-side secret-backstop for this agent — no duplicate work.

**Update (2026-07-08, orchestrator session):** the P2 agent-critic re-run was completed. A fresh
agent-critic pass against the regenerated `.claude/agents/release-auditor.md` returned score
38/blocked (1 BLOCKER, 1 MAJOR, 2 MINOR — see Implementation Notes item 7). All 4 findings were
fixed and re-verified. Every Todo Plan item and Acceptance Criteria item is now `[x]`; this file
is archived per its own Completion instruction (see Implementation Notes item 8).

## Implementation Notes

**Working-tree state discovered before editing.** `git status --short` showed 150 files of
unrelated uncommitted changes from other in-progress tickets (per the task's own warning),
including `.claude/agents/release-auditor.md` and `.github/agents/release-auditor.agent.md`
(both already `M`) but **not** `.opencode/agents/release-auditor.md` (clean) or
`packages/ai-universal-rules/templates/core/agents/release-auditor.md` (clean). Reading the
pre-existing `.claude`/`.github` diffs against HEAD showed Bucket A's three findings (stale
`docs/ai/risk-taxonomy.md` ref, missing verdict-accountability sentence, weak secret-redirect
prose) were **already closed** in both — a side effect of another in-progress ticket's own
broader work, matching the plan-13/14/15 precedent of partial pre-existing sync. The canonical
template itself already carried all three Bucket A fixes too. Only `.opencode/agents/
release-auditor.md` was still genuinely stale (confirmed via `rg -n "docs/ai/risk-taxonomy.md"`
returning a hit there and nowhere else).

1. **Mechanism correction (per the Todo P0 #3 deviation note above).** The plan's In Scope
   text names `php tools/ai/generate-agent-permissions.php --write` as the regeneration
   mechanism, "matching the exact mechanism used in commit ca0e5597 for reviewer.md." Reading
   that tool's own source and doc comment showed it only splices the frontmatter `permission:`
   YAML block (via `aiPermissionSpliceBlock()`); it never touches body prose. Confirmed
   empirically: `php tools/ai/generate-agent-permissions.php --check`, run both before and
   after every edit in this session, reported the exact same single pre-existing entry
   (`.opencode/agents-optional/build-config.md`) and never mentioned release-auditor at any
   point — this agent's `permission:` block was never drifted. The real body-content
   propagation mechanism (confirmed by reading `ca0e5597`'s own diff stat, which touched all
   three rendered files directly alongside the template) is `tools/ai/render-adapters.php`
   for `.claude`/`.github` (`aiInstallerRenderClaudeAgent()` / `aiInstallerRenderCopilotAgent()`)
   plus `aiInstallerCopyDirAsOpenCodeAgents()`'s generated-header-insertion logic for
   `.opencode`. `render-adapters.php --check` (read-only) confirmed `.claude/agents/
   release-auditor.md` WAS genuinely drifted from the current renderer+template (29 total
   drifted files fleet-wide, release-auditor among them) — so a real regeneration was needed,
   just via a different tool than the plan named.
2. **Bucket B (template edits).** Added the B1 sentence to the `ai-diff-context.sh` /
   `ai-verify.sh` (`ask`) Script Access bullet and the B2 sentence as a new Hard Rules bullet
   in `packages/ai-universal-rules/templates/core/agents/release-auditor.md`, per spec.
3. **Scoped regeneration (per the task's explicit instruction).** Per the task's explicit
   instruction and the plan-7/13/14/15 precedent, the broad `php tools/ai/render-adapters.php
   --write` was **not** run (it has no per-agent filter and would have rewritten all 28 other
   currently-drifted agent files, clobbering unrelated in-progress-ticket work). A
   narrowly-scoped one-off PHP script (`/tmp/opencode/render-plan16-scoped.php`, not
   committed) was written instead: it calls the exact same renderer functions the real tools
   use (`aiInstallerRenderClaudeAgent()`, `aiInstallerRenderCopilotAgent()`,
   `aiInstallerInsertGeneratedHeaderAfterFrontmatter()`) and writes only the three
   release-auditor rendered files.
4. **Bug caught and fixed mid-session.** The script's first run omitted the `<SCRIPTS_ROOT>`/
   `<PROJECT_NAME>` placeholder substitution that `render-adapters.php`'s own CLI wrapper
   applies via `strtr()` after calling the renderer functions — this left literal
   `<SCRIPTS_ROOT>` tokens in the written `.claude`/`.github` files (confirmed via
   `rg -n "SCRIPTS_ROOT"`) and `php tools/ai/render-adapters.php --check` still listed
   `.claude/agents/release-auditor.md` as drifted afterward. Reverted the three files
   (`git checkout --`), fixed the script to mirror `aiRenderAdaptersPlaceholderMap()`'s exact
   substitution logic (`tools/ai/render-adapters.php` itself cannot be `require_once`'d — it
   is a CLI entrypoint with top-level `$argv`-driven side effects including `exit()`, not a
   pure function library — so the placeholder-map logic was mirrored inline instead), and
   re-ran. `php tools/ai/render-adapters.php --check` afterward confirmed `.claude/agents/
   release-auditor.md` was no longer in the drift list (28 entries remained, all pre-existing
   and unrelated — the same 28-of-29 baseline `DONE-plan-15` documented, minus this session's
   one closed entry).
5. **P1 (diff-verify).** `git diff` on all four affected files confirmed: `.github` and
   `.opencode` diffs are clean and minimal — exactly the three Bucket A findings plus the two
   new B1/B2 sentences, nothing else. The `.claude` diff is larger than the plan's "only the
   expected drift closing" expectation because the pre-existing `.claude/agents/
   release-auditor.md` had drifted from the *current* renderer beyond just the three Bucket A
   findings (e.g. the Claude-only "ask-tier is NOT runnable" clause and the hard-block-vs-
   prose-discouraged distinction in the Bash Command Policy `Do not run` line, and the
   `<SCRIPTS_ROOT>` token normalization) — this extra drift closure is a byproduct of using
   the real, current renderer (correct and expected per the plan's own "closes Bucket A
   findings automatically" framing), not scope creep introduced by this session. Same
   disposition as `DONE-plan-15`'s analogous broader-than-expected diff note.
6. **P2 (partial).** Added a flag-only note to `docs/tickets/arch-todo-agent-fleet-
   improvement-plans-20260707/plan.md`'s `## release-auditor` section (per Out Of Scope:
   "flag only," not a rewrite) pointing at this plan and explaining that section's
   "Current assessment" describes the canonical template only, not the separately-drifted
   Claude render this plan fixed. The agent-critic re-run half of P2 is blocked — no
   Task-tool subagent-dispatch capability this session (per the task's own stated
   limitation) — and is left for an orchestrator session, per Handoff Notes above.

### Verification Evidence

- `rg -n "docs/ai/risk-taxonomy.md"` across all three rendered copies — zero hits (exit 1).
  `rg -n "docs/ai/command-risk-taxonomy.md"` — one hit in each of the three copies. Verified —
  proves AC-01.
- `rg -n "must name at least one risk"` — one hit in each of the three copies. Verified —
  proves AC-02.
- `rg -n "deliberately not part of the renderer"` (B1) and
  `rg -n "shared, deliberately minimal fleet-wide baseline"` (B2) — one hit each in all three
  copies. Verified — proves AC-03.
- `php tools/ai/validate-adapter-drift.php` — `OK: adapter drift validation completed`
  (WARN-only elsewhere, no ERROR, no release-auditor-specific finding). Verified.
- `php tools/ai/validate-generated-artifacts.php` — `OK: generated artifact baseline present`
  (all 5 sub-checks `OK`). Verified.
- `php tools/ai/generate-agent-permissions.php --check` — reports exactly one pre-existing
  entry, `.opencode/agents-optional/build-config.md`, identical to this session's very first
  (pre-edit) run of the same command and to `git status --short -- .opencode/agents-optional/
  build-config.md` showing no output (byte-identical to HEAD, untouched by this session).
  Not a regression — same disposition as `DONE-plan-13`/`DONE-plan-15`'s identical recurring
  note.
- `php tools/ai/render-adapters.php --check` before vs. after this session's scoped write —
  before: 29 drifted entries including `.claude/agents/release-auditor.md`; after: 28
  entries, with `release-auditor.md` now absent and every other entry unchanged. Verified —
  zero new drift introduced, exactly one entry closed.
- `composer test:fast` — 925 tests, 12514 assertions, 3 failures: `AdapterRenderDriftTest`
  (x2, listing the same 28-entry pre-existing fleet-wide baseline, `release-auditor.md`
  absent) and `AgentPermissionDriftTest::testManagedAgentsHaveNoDrift` (x1, the same single
  `build-config.md` pre-existing entry). None of the 3 failures reference a file this plan
  touched; all pre-date this session (confirmed via each failing file's own untouched `git
  status` state, e.g. `.claude/agents/architecture-plan-writer.md` and `.claude/agents/
  config-maintainer.md` were already `M` before this session started). Not a regression
  introduced by this plan — same disposition as `DONE-plan-15`'s identical note for the same
  two test classes.
- `vendor/bin/phpunit tests/php/PermissionRenderAdaptersTest.php tests/php/
  PermissionComposeTest.php tests/php/ClaudeAgentRendererTest.php` — 74 tests, 1504
  assertions, OK. Verified.
- `php tools/ai/ai.php placeholders --fail` — `OK: wrote docs/ai/generated/
  placeholders.json`, exit 0, no unresolved-placeholder failures. Verified.
- `git diff --stat -- docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/plan.md`
  — 1 file changed, 14 insertions(+), confirming the P2 flag-note addition is clean (no
  pre-existing unrelated diff on that file). Verified.

**Status (superseded — see item 7/8 below):** at the point this note was written, all Todo Plan
items except P2 and all 4 Acceptance Criteria were `[x]`; archiving was deferred pending an
orchestrator session closing the agent-critic re-run gap. That gap has now been closed — see
below.

7. **P2 completion — fresh agent-critic re-run findings and fixes (2026-07-08, orchestrator
   session).** An orchestrator session with Task-tool subagent-dispatch access ran a fresh
   agent-critic pass against the regenerated `.claude/agents/release-auditor.md` and returned
   score 38/blocked with 4 findings:
   - **[BLOCKER]** The Hard Rules `preview-file.sh` reference claimed "(bounded,
     redaction-aware)" — false. `scripts/ai/preview-file.sh` has zero secret-pattern/redaction
     logic; it only blocks `.git/` paths, oversized files, and binary files, and will print
     `.env`/`.pem`/`credentials.*` content verbatim if asked. Fixed in the canonical template:
     replaced with an accurate description of what the script actually blocks plus an explicit
     instruction that the agent itself must recognize and refuse secret-pattern paths before
     calling it.
   - **[MAJOR]** The Claude-only Bash Command Policy footer sentence "Other listed commands
     (`rm`, `mv`, `cp`, `chmod`, plain `git push`/`git reset`) are prose-discouraged and
     interactively gated, not hard-blocked" contradicted this agent's absolute no-mutation Hard
     Rule and falsely implied those verbs are "listed" when they never appear in
     release-auditor's own Approved-scripts list. Confirmed via search that this exact sentence
     is generic renderer boilerplate (`tools/ai/install/claude-agent-renderer.php`, emitted for
     every Claude-rendered agent with a restricted bash allowlist) — NOT template prose, and
     still accurate for other agents whose approved lists do differ. Fixed with a
     release-auditor-scoped `if ($agentId === 'release-auditor')` override in the renderer
     (mirroring the existing `researcher`-only override pattern already in that file) rather
     than changing the shared boilerplate for all agents.
   - **[MINOR]** The Hard Rules settings.json-disclosure sentence (added in this plan's earlier
     P0 pass) pointed readers "below" to the Script Access section for the full script
     enumeration. On Claude that section is just a categorized subset (same as every other
     Claude render) — the actual full per-script list is the Bash Command Policy section
     rendered above. Confirmed this sentence is unique to release-auditor's template (not
     shared boilerplate), but the "below" reference is only wrong in the Claude render (correct
     for OpenCode/Copilot, where the equivalent section genuinely does name every script).
     Fixed with the same release-auditor-scoped renderer override as the MAJOR finding, changing
     "below" to "above (Bash Command Policy)" for the Claude render only — template/OpenCode/
     Copilot keep "below" unchanged.
   - **[MINOR]** Missing an explicit "stop only when high-impact" escalation threshold that
     sibling auditor agents (reviewer.md, workflow-auditor.md, agent-critic.md,
     repository-reviewer.md) all carry. Fixed by appending the threshold clause to the
     canonical template's `unknown` Hard Rules bullet.
   All 4 fixes applied to `packages/ai-universal-rules/templates/core/agents/release-auditor.md`
   (BLOCKER + MINOR #2) and `tools/ai/install/claude-agent-renderer.php` (MAJOR + MINOR #1,
   scoped to `$agentId === 'release-auditor'` so no other agent's Claude render changes),
   then propagated to `.claude/agents/release-auditor.md`, `.github/agents/
   release-auditor.agent.md`, and `.opencode/agents/release-auditor.md` via the same
   narrowly-scoped one-off regeneration-script pattern as this plan's P0 (broad `--write` was
   not run — the branch still has 50+ unrelated in-progress files).
8. **Archive on completion.** Every Todo Plan item and every Acceptance Criteria item is now
   `[x]`. Per this file's own Completion instruction, this file is archived to
   `archive/DONE-plan-16-release-auditor-agent-critic-fixes.md` and replaced with a tombstone,
   dated 2026-07-08.

### Verification Evidence (P2 re-run, 2026-07-08)

- `vendor/bin/phpunit tests/php/PermissionRenderAdaptersTest.php tests/php/
  PermissionComposeTest.php tests/php/ClaudeAgentRendererTest.php` — 74 tests, 1504 assertions,
  OK. Verified.
- `php tools/ai/render-adapters.php --check` — `release-auditor` absent from the drift list;
  the 28 listed entries are byte-identical to this plan's own earlier documented pre-existing
  baseline (architecture-plan-writer, config-maintainer, implementer (+ `.agent.md`),
  post-install, refactorer, repository-researcher (+ `.agent.md`), repository-reviewer,
  researcher, reviewer, workflow-auditor, agent-creator-runtime-guardian,
  agent-creator-semantic-verifier (+ `.agent.md`), agent-creator-static-validator
  (+ `.agent.md`), agent-creator, agent-critic (+ `.agent.md`), agent-fleet-assessor, bugfix,
  build-config (+ `.agent.md`), docs, infra-auditor (+ `.agent.md`), upgrade). Zero new drift
  introduced. Verified.
- `php tools/ai/validate-adapter-drift.php` — `OK: adapter drift validation completed`, exit 0
  (WARN-only elsewhere across the repo, no ERROR, no release-auditor-specific finding).
  Verified.
- `php tools/ai/validate-generated-artifacts.php` — `OK: generated artifact baseline present`
  (all 5 sub-checks `OK`), exit 0. Verified.
- Manual diff inspection of all 4 touched files (`git diff`) confirmed each of the 4 fixes
  landed exactly as specified, scoped correctly (template-level fixes in all three renders;
  Claude-only fixes present only in `.claude/agents/release-auditor.md`, with `.github`/
  `.opencode` correctly retaining the original "below" wording and generic footer sentence
  since those renders were never claimed to be wrong for those findings). Verified.

**Status:** All Todo Plan items and all Acceptance Criteria are `[x]`. Archived per the
Completion instruction above.
