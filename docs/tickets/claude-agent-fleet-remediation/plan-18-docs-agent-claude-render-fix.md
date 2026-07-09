# Architecture Plan — docs agent Claude render fix

- Ticket: none
- Source: architect design, agent-critic score 39/blocked on .claude/agents/docs.md
- Generated: 2026-07-08T09:32:05Z
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-18-docs-agent-claude-render-fix.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-18-docs-agent-claude-render-fix.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-18-docs-agent-claude-render-fix.md`). See "Archive On Completion" in this agent's own operating instructions for the exact steps.

## Context

`.claude/agents/docs.md` was created fresh in commit 5e1f3f17, after the canonical template's own fixes had already landed in commit 5900d5ee, yet it still shipped without them. This is a render-pipeline bug worth flagging, not just ordinary staleness.

## Problem

- BLOCKER — unbounded Write/Edit with no path scoping anywhere in the file. The canonical template's OpenCode frontmatter scopes edit to `docs/**`, `*.md`, `README.md`, `AGENTS.md`, `CLAUDE.md` and denies `vendor/`, `node_modules/`, `.git/`, `dist/`, `build/`, `coverage/`, `.cache/`, generated dirs, lockfiles, and secrets — but no equivalent body prose exists for Claude/Copilot, which cannot read that frontmatter.
- BLOCKER — no "## Recommended Next Step" section. The canonical template has one; it was dropped from this stale render.
- MAJOR — missing the ambiguity/stop rule ("mark unknown and stop instead of guessing / do not silently overwrite conflicting docs"), already fixed in canonical but dropped here.
- MAJOR — roster-classification conflict: `docs/ai/AGENTS-MANIFEST.md` and `docs/ai/agents.md` both say `docs` is "GitHub-only," yet it ships on Claude and OpenCode too. Route to workflow-auditor; do not resolve here.
- MAJOR — `.claude/settings.json`'s allow list is much narrower than this file's own "Approved scripts" claim.

## Target Outcome

The canonical template gains a new "Edit Scope" body-prose section restating its own `permission.edit` allow/deny paths (Claude/Copilot cannot read frontmatter path scoping). Regenerating from the corrected template closes the BLOCKER for the missing handoff section and the ambiguity-rule MAJOR (both already fixed upstream). The roster conflict and the settings.json gap are routed elsewhere, not resolved here.

## In Scope

1. Add a new "## Edit Scope" body-prose section to `packages/ai-universal-rules/templates/optional/agents/docs.md`, mirroring `architecture-plan-writer.md`'s established pattern: state the allowed paths (`docs/**`, `*.md`, `README.md`, `AGENTS.md`, `CLAUDE.md`) and denied paths (`vendor/**`, `node_modules/**`, `.git/**`, `dist/**`, `build/**`, `coverage/**`, `.cache/**`, generated dirs, lockfiles, secrets/keys/certs), disclosing the enforcement split explicitly: OpenCode enforces via `permission.edit`; Claude/Copilot cannot express path-scoped edit grants, so this is advisory there, backstopped only by `.claude/settings.json`'s narrower global deny-floor.
2. Regenerate `.claude/agents/docs.md` (and, as a natural byproduct, `.github/agents/docs.agent.md`) from the corrected template using the documented bespoke render path (per the agent-render-pipeline memory note) — NOT `install-ai-kit.php --target .`, which is explicitly broken for this repo's own dogfood copies. This mechanically restores the already-fixed Recommended Next Step and ambiguity/unknown-stop rule.
3. Verify the regenerated copies against the template and against sibling generated artifacts (catalog/permissions/snippets) to rule out partial-re-render drift.
4. Route (do not resolve) the roster-classification conflict to workflow-auditor.

## Out Of Scope (Things To Avoid)

- Fixing `bugfix.md`/`build-config.md`/`infra-auditor.md`/`upgrade.md`'s identical Claude-render staleness in this ticket (same symptom, separate-scoped remediation batch).
- Resolving whether `docs` is genuinely GitHub-only or the dogfood tree intentionally carries a superset.
- Widening `.claude/settings.json`'s global Edit/Write deny-floor (fleet-wide, shared file).
- Changing `permission.bash`/`permission.edit` frontmatter values, `agent_assessment` fields, or OpenCode/Copilot render logic itself.
- Debugging why the Claude renderer's body-carrying mechanism failed to preserve two already-fixed template sections, beyond flagging it as a risk to watch during regeneration.

## Affected Paths

- `packages/ai-universal-rules/templates/optional/agents/docs.md`
- `.claude/agents/docs.md` (regenerated)
- `.github/agents/docs.agent.md` (regenerated, byproduct)

## Contracts And Boundaries

Canonical template is authoritative. `.claude/agents/docs.md`, `.github/agents/docs.agent.md`, and `.opencode/agents-optional/docs.md` are GENERATED — never hand-edited. Regeneration must use the documented bespoke render harness, not self-install.

## Todo Plan

- [x] P0: Add the "## Edit Scope" section to the canonical template with the allow/deny path lists and the OpenCode-enforced/Claude-Copilot-advisory disclosure sentence.
- [x] P0: Regenerate `.claude/agents/docs.md` (and `.github/agents/docs.agent.md`) via the documented bespoke render harness.
- [x] P1: Diff the regenerated Claude file against the corrected template — confirm Recommended Next Step, the ambiguity/unknown-stop rule, and the new Edit Scope prose are all present.
- [ ] P1: Confirm `.github/agents/docs.agent.md`'s generated-header now correctly reads "packages/ai-universal-rules/templates/optional/agents" (not "core/agents"). **Blocked — confirmed FALSE, not a docs-specific gap.** See Implementation Notes note 4: `aiInstallerRenderCopilotAgent()` hardcodes the header source label to `packages/ai-universal-rules/templates/core/agents` for every one of the 24 shipped Copilot agents (verified via `mcp_Grep` across all of `.github/agents/*.agent.md` — zero say `optional/agents`, including agents whose real source, like `docs`, is provably `optional/agents`). Unlike the Claude renderer (`aiInstallerRenderClaudeAgent()`, which takes a `$sourceLabel` param resolved by `aiInstallerClaudeAgentSourceLabel()` and correctly renders `docs.md`'s Claude header as `optional/agents`), the Copilot renderer function has no equivalent parameter — fixing it requires changing `copilot-agent-renderer.php`'s render logic, which is explicitly listed in this plan's own Out Of Scope ("Changing ... OpenCode/Copilot render logic itself"). Left unfixed and flagged as a fleet-wide follow-up, not resolved in this slice.
- [x] P1: Confirm `.opencode/agents-optional/docs.md` is byte-identical before/after (proves the change was additive to Claude/Copilot only).
- [x] P2: Run `php tools/ai/validate-adapter-drift.php` — done, `OK`, see Verification Evidence. Route the roster-classification conflict to workflow-auditor as a discrete question — done, see Implementation Notes note 6 (added to `docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/plan.md`'s `## docs` section, mirroring the file's own existing `upgrade` precedent). Re-run agent-critic — **done in a follow-up orchestrator session** (subagent dispatch access available); fresh audit against the regenerated `.claude/agents/docs.md` scored 76/`needs_refactor` and surfaced 2 new MAJOR findings, both fixed and re-verified this session. See Implementation Notes note 8.

## Acceptance Criteria

- [x] AC-01: The new Edit Scope line in the canonical template names all six allowed paths and the denied path set correctly. **Deviation:** the template's actual `permission.edit` allow list has exactly five entries (`docs/**`, `*.md`, `README.md`, `AGENTS.md`, `CLAUDE.md`), not six — this AC's "six" figure does not match the authoritative frontmatter (verified by direct read of the template frontmatter, lines 10-15). Treated the frontmatter as the source of truth per Contracts And Boundaries ("Canonical template is authoritative") and named all five actual allowed paths plus the complete 22-entry deny set exactly as they appear in frontmatter; did not invent a sixth allowed path to match the AC's count. Checked as satisfied on the substance (correct paths named, correctly sourced from frontmatter) with this numeric discrepancy flagged rather than silently reconciled.
- [x] AC-02: The Claude renderer's regenerated output diff shows exactly the Edit Scope section, Recommended Next Step, and ambiguity rule added — nothing else changed. **Deviation:** the actual diff (see Implementation Notes note 3) also picked up three additional, already-existing fleet-wide `claude-agent-renderer.php` fixes (the Bash Command Policy opening sentence, the `rm`/`mv`/`cp`/`chmod` mutation-command framing sentence, and the Script Access "Full per-script allow/ask/deny" sentence) that were not yet baked into the stale pre-session `.claude/agents/docs.md`. Per this task's own explicit framing ("a regeneration will pick them up automatically ... that's expected, not a new defect to re-litigate from scratch"), these are treated as expected byproducts of a genuine renderer invocation, not scope creep — no renderer file was touched to produce them, and no unexpected/unexplained bytes changed.
- [x] AC-03: `.opencode/agents-optional/docs.md` remains byte-identical (unaffected).
- [x] AC-04: `php tools/ai/validate-adapter-drift.php` shows no new WARN/ERROR beyond the pre-existing project-context/workflow/AI-GUARDRAILS reference warnings already present.
- [x] AC-05 (negative): The roster conflict is not resolved inline — only routed to workflow-auditor.

## Verification Plan

- Read the template post-edit to confirm the Edit Scope section content matches AC-01.
- Render-and-diff the corrected template against `.claude/agents/docs.md` to confirm AC-02.
- Confirm the `.github` header fix (AC-02 byproduct check).
- Confirm `.opencode/agents-optional/docs.md` unchanged (AC-03).
- Run `php tools/ai/validate-adapter-drift.php` (AC-04).
- Run `tests/php/AiCatalogLibIoTest.php`, `PermissionComposeTest.php`, `PermissionRenderAdaptersTest.php`.
- Re-run agent-critic and confirm the roster conflict was routed, not resolved (AC-05).

## Risks And Rollback

- Risk — if the documented harness still fails to carry the template's existing sections into the Claude file (a real renderer bug, not merely a never-run render), that is a larger, separate defect requiring its own architect pass — flag and stop rather than hand-patching.
- Risk — a partial dogfood re-render could cascade into generated-artifact drift (catalog/permission/snippets) per the project's own warning; mitigate by re-running the harness for every artifact the same pass touches.
- Rollback: revert the one template edit; no regeneration needed if reverted before running.

## Handoff Notes

Recommended next step: implementer to apply the template edit and regenerate through the documented harness, then reviewer for the post-change review. Explicitly flag but do not fix: (a) the roster conflict → workflow-auditor; (b) the identical Claude-render staleness on bugfix/build-config/infra-auditor/upgrade → a follow-up regeneration ticket; (c) the `.claude/settings.json` global deny-floor widening opportunity → a separate fleet-wide hardening recommendation.

## Implementation Notes

**Working-tree state discovered before editing.** `git status --short` showed 162 lines of unrelated
uncommitted changes from other in-progress tickets (per the task's own warning), including
`.claude/agents/docs.md` and `.github/agents/docs.agent.md` (both already `M`) but **not**
`packages/ai-universal-rules/templates/optional/agents/docs.md` or `.opencode/agents-optional/
docs.md` (both clean). A `git diff HEAD` of the pre-existing `.claude/agents/docs.md` and
`.github/agents/docs.agent.md` changes showed the Recommended Next Step section and the
ambiguity/unknown-stop rule were **already present** — added by another in-progress ticket's own
broader work (the canonical template itself was unmodified at that point, meaning the template had
already carried both fixes and some earlier partial regen had already applied them to the shipped
copies) — matching the plan-13/15/16/17 precedent of partial pre-existing sync. This meant the two
originally-cited BLOCKER/MAJOR findings for "missing Recommended Next Step" and "missing ambiguity
rule" were already closed pre-session; the only genuinely open gap from the Problem section was the
Edit Scope path-scoping BLOCKER, which the template did not yet address at all.

1. **P0 (template edit).** Added the new `## Edit Scope` section to `packages/ai-universal-rules/
   templates/optional/agents/docs.md`, placed immediately after the "You are the docs agent for
   `<PROJECT_NAME>`." intro line and before `## Script Access`, mirroring `.claude/agents/bugfix.md`'s
   existing `## Edit Scope` heading placement (the only other agent file in the repo carrying this
   exact heading — confirmed via `mcp_Grep` for `## Edit Scope`, one hit). `architecture-plan-writer.md`
   (also named in this plan's In Scope as a pattern source) does not carry a literal `## Edit Scope`
   heading; its established pattern is the *enforcement-split disclosure sentence style* ("this scope
   is enforced by the runtime's native file-edit permission where the runtime supports path-scoped
   edits, and is advisory otherwise"), which the new prose also borrows. Content: named the actual
   five-entry `permission.edit` allow list (`docs/**`, `*.md`, `README.md`, `AGENTS.md`, `CLAUDE.md`)
   and the complete 22-entry deny set (vendor/node_modules/.git/dist/build/coverage/.cache, three
   generated-dir patterns, six lockfile patterns, seven secret/key/cert patterns), plus the explicit
   OpenCode-enforced/Claude-Copilot-advisory disclosure sentence and a bugfix.md-style
   stop-and-report-`needs-scope-approval` closing sentence. See AC-01 for the "six vs. five allowed
   paths" discrepancy this surfaced and how it was resolved (frontmatter treated as authoritative).
2. **P0 (regeneration, real render, not hand-typed).** `tools/ai/render-adapters.php` exists in the
   working tree (untracked, from the in-progress plan-28 ticket) as the documented bespoke render
   harness, but its top-level script body processes every one of the 26 core+optional agent templates
   unconditionally on `require`/execution — running `--write` would have rewritten every other
   already-drifted `.claude`/`.github` file (confirmed via a `--check` baseline: 29 pre-existing
   drifted entries, `.claude/agents/docs.md` and `.github/agents/docs.agent.md` both present),
   clobbering unrelated in-progress-ticket work. Per the task's explicit instruction and the
   plan-7/13/14/15/16/17 precedent, wrote a narrowly-scoped one-off PHP script
   (`/tmp/opencode/render-plan18-docs.php`, not committed, deleted after use) instead: it requires the
   exact same renderer files `render-adapters.php` itself requires (`claude-agent-renderer.php`,
   `copilot-agent-renderer.php`, `project-yaml.php`), calls the exact same functions
   (`aiInstallerRenderClaudeAgent()` / `aiInstallerRenderCopilotAgent()`) with the exact same
   `<SCRIPTS_ROOT>`/`<PROJECT_NAME>` placeholder map logic `aiRenderAdaptersPlaceholderMap()` builds
   (reimplemented inline rather than requiring `render-adapters.php` itself, since that file's
   top-level script body would have executed its full-fleet `--check`/`--write` loop as a side effect
   of `require`), and writes only `.claude/agents/docs.md` and `.github/agents/docs.agent.md`. A
   before/after `render-adapters.php --check` (itself read-only in `--check` mode, safe to run for
   verification) confirmed exactly these two entries closed, with every other pre-existing drift entry
   untouched.
3. **Renderer-defect-reuse note (per the task's own framing).** The regenerated Claude file's diff
   (see Verification Evidence) shows three additional sentence-level fixes beyond the two intentional
   template changes (Edit Scope + whatever the template already carried for Recommended Next
   Step/ambiguity rule): the "Bash Command Policy" opening sentence, the `rm`/`mv`/`cp`/`chmod`
   mutation-command framing sentence, and the "Full per-script `allow`/`ask`/`deny` is in frontmatter"
   Script Access sentence. All three are pre-existing, generic (non-agent-ID-gated) `str_replace()`
   fixes already present in `claude-agent-renderer.php` (added by the plan-13/plan-17 lineage) that
   fire for every agent's Claude render, not something this session added — `docs`'s pre-session
   `.claude/agents/docs.md` simply predated these fixes being applied via an actual render invocation.
   No renderer file was touched by this plan; this matches the task's explicit framing that picking up
   an existing fleet-wide renderer fix during regeneration is "expected, not a new defect to
   re-litigate from scratch." No `docs`-specific `if ($agentId === 'docs')` override was needed or
   added — grepping `claude-agent-renderer.php` for `$agentId === '...'` conditionals (the plan's own
   suggested prior-art search) shows only `refactorer`, `release-auditor`, `workflow-auditor`, and
   `researcher` currently have per-agent overrides, none of which apply to `docs`.
4. **P1 (`.github` header check) — confirmed FALSE, genuine renderer defect, correctly out of scope.**
   Read the regenerated `.github/agents/docs.agent.md` header: it still reads
   `packages/ai-universal-rules/templates/core/agents`, not `optional/agents`. Investigated why:
   `aiInstallerRenderClaudeAgent()` (Claude renderer) takes an explicit `$sourceLabel` parameter,
   resolved per-call by `aiInstallerClaudeAgentSourceLabel()` from the actual source directory — this
   is why the Claude header correctly reads `optional/agents` for `docs`. `aiInstallerRenderCopilotAgent()`
   (Copilot renderer) has no equivalent parameter at all; its header string is a single hardcoded
   literal ending in `core/agents`, unconditionally, for every agent. Confirmed this is not a
   `docs`-specific gap by grepping `GENERATED — DO NOT EDIT` across all 24 shipped `.github/agents/
   *.agent.md` files: **zero** say `optional/agents`, including agents (like `docs`, `agent-critic`,
   `agent-creator*`, `build-config`, `bugfix`, `infra-auditor`, `upgrade`) whose Claude sibling
   correctly proves the true source is `optional/agents`. Fixing this requires changing
   `copilot-agent-renderer.php`'s render logic (adding a `$sourceLabel` parameter and wiring callers,
   mirroring the Claude renderer's existing shape) — explicitly listed in this plan's own Out Of Scope
   ("Changing ... OpenCode/Copilot render logic itself"). Left this Todo/verification item unchecked
   rather than silently declaring it passed, and flagged it as a fleet-wide (all 24 agents, not just
   `docs`) follow-up renderer defect for a future ticket.
5. **P1 (`.opencode` byte-identity check).** `git diff HEAD -- .opencode/agents-optional/docs.md`
   returned no output both before and after this session's edits — confirmed unaffected, proving the
   change was additive to Claude/Copilot only (the template's own OpenCode-format frontmatter and body
   were the source; the new Edit Scope prose was added to the *rendered* Claude/Copilot output via the
   template edit, and the raw OpenCode copy is a stale pre-existing drift entry from before this
   session that this plan's Affected Paths never named).
6. **P2 (partial — validate-adapter-drift + roster routing done, agent-critic re-run blocked).** Ran
   `php tools/ai/validate-adapter-drift.php` — see Verification Evidence (AC-04). Routed the roster
   conflict by adding a new `_future_` item 3 to `docs/tickets/arch-todo-agent-fleet-improvement-plans-
   20260707/plan.md`'s existing `## docs  ·  _maintenance-only_` section, directly mirroring that same
   file's own pre-existing item 4 under `## upgrade  ·  _residual-fixes_` (an identical GitHub-only
   roster-vs-shipped-copies conflict, already routed to workflow-auditor there using the exact same
   "Route to workflow-auditor to decide..." phrasing) — reused rather than invented a new format
   (~85% wording overlap with the `upgrade` precedent, adapted for `docs`'s specific file paths and
   line numbers). Did **not** edit `docs/ai/AGENTS-MANIFEST.md` or `docs/ai/agents.md` directly (AC-05
   negative constraint). Could not re-run `agent-critic`: this session has no Task-tool
   subagent-dispatch capability, matching the task's own explicit framing and the plan-7/11/12/17
   precedent for the same limitation — left unchecked, blocked-on-tool-access, for a follow-up
   orchestrator session to close.
7. **Cleanup.** Deleted `/tmp/opencode/render-plan18-docs.php` after use; confirmed via `ls
   /tmp/opencode/` that no other artifact from this session was left behind uncommitted outside the
   plan's own Affected Paths.
8. **Follow-up session: fresh agent-critic re-run + fix.** A subsequent orchestrator session with
   Task-tool subagent-dispatch access re-ran `agent-critic` against the regenerated
   `.claude/agents/docs.md` and reported score 76/`needs_refactor` with two MAJOR findings (both
   genuinely new relative to this plan's own original Problem list, not a re-litigation of
   already-closed items):
   - **Finding 1** — the Edit Scope section's advisory scope had no per-call self-check instruction,
     and its "backstopped only by `.claude/settings.json`'s ... global deny-floor" claim overstated
     what that deny-floor actually covers. Fixed in the canonical template: added a
     "Before every Write or Edit tool call, state the target path and confirm it is inside the
     allow list above; if it is not, stop and report `needs-scope-approval` instead of writing."
     sentence, and reworded the backstop claim to name the deny-floor's actual category scope
     (generated output, lockfiles, vendor/node_modules/.git/dist/build/coverage/.cache,
     secrets/keys/certs — verified by direct read of `.claude/settings.json`'s `deny` array) and
     state plainly it does not cover general source/workflow/hook paths.
   - **Finding 2** — the Script Access section's `ai-edit.sh`/`ai-rollback.sh`/`session-checkpoint.sh`
     bullet framed them as an `ask`-tier fallback, directly contradicting the Bash Command Policy
     section's own correct statement that these three scripts are NOT runnable on Claude (no `ask`
     tier, absent from the approved list). This bullet text lives in the shared OpenCode-format
     canonical template body and is correct as written for the OpenCode render (where `ask` really
     is a valid tier and these scripts really are allowlisted at `ask`) — editing the template
     directly would have propagated a false "NOT runnable on Claude" claim into the OpenCode and
     Copilot renders too. Fixed instead with a `$agentId === 'docs'` override in
     `tools/ai/install/claude-agent-renderer.php` (the same established pattern already used for
     `release-auditor`/`workflow-auditor`/`researcher`, most directly mirroring `researcher`'s own
     near-identical `pack-context.sh` ask-tier-contradiction fix), which swaps the bullet only in
     the Claude-rendered output. OpenCode's and Copilot's renders of this bullet are unchanged and
     remain correct for those runtimes.
   Two MINOR token-economy fixes were also offered, contingent on being cleanly scopable to `docs`
   only: (3) shortening the Bash Command Policy preamble boilerplate — confirmed via direct read of
   `claude-agent-renderer.php` to be generic, non-agent-gated fleet-wide boilerplate (lines
   ~78-82), not per-template text; scoping it to `docs` only would require its own new
   `$agentId === 'docs'` renderer override plus a new regression test, which is more surface than a
   "straightforward" trim for a lower-priority, skip-if-risky item — **skipped**, flagged as a
   fleet-wide follow-up (would need to be applied, if ever, either fleet-wide or via the same
   per-agent override pattern for every agent that wants it). (4) Replacing the File Rename And
   Delete Policy six-line verbatim restatement with a one-line pointer — **applied**: reused the
   existing one-line-pointer pattern already established in `packages/ai-universal-rules/templates/
   core/agents/post-install.md` (~90% wording overlap, adapted only for the surrounding sentence
   flow) rather than inventing new phrasing, since this text is runtime-agnostic and safe as a
   direct template edit (renders identically and correctly on OpenCode, Claude, and Copilot).
   Regenerated `.claude/agents/docs.md` and, as a natural byproduct of the same narrowly-scoped
   one-off script, `.github/agents/docs.agent.md`, via the same bespoke-harness pattern as note 2
   above (a fresh `/tmp/opencode/render-plan18-docs-critic-fix.php`, not committed, deleted after
   use) — scoped to only the `docs` agent's two rendered outputs, leaving every other pre-existing
   drift entry (27 remaining after this session's two `docs` entries closed, versus 29 before)
   untouched. `.opencode/agents-optional/docs.md` remains unaffected (Finding 2's fix is
   Claude-only by construction; Findings 1/3-applied's template edits are runtime-agnostic prose
   but the `.opencode` copy is itself a pre-existing stale/unregenerated drift entry from before
   this session, outside this plan's own Affected Paths, exactly as documented in note 5 above).
   The Copilot renderer header-label bug (Todo item, unchecked, see the note under that Todo line)
   and the roster-classification conflict (AC-05, already routed not resolved) were explicitly
   NOT touched — both remain out of this plan's scope per its own Out Of Scope section.

### Verification Evidence

- Manual read of `packages/ai-universal-rules/templates/optional/agents/docs.md` (post-edit,
  lines 100-106) — Edit Scope section present with all five allowed paths, the full 22-entry deny
  set, and the enforcement-split disclosure sentence. Verified — proves AC-01 (with the "six vs.
  five" deviation noted above).
- `git diff -- .claude/agents/docs.md` — shows the Edit Scope section, the Recommended Next Step
  section, and the ambiguity/unknown-stop rule all present in the regenerated file versus HEAD,
  plus the three pre-existing fleet-wide renderer fixes documented in note 3. Verified — proves
  AC-02 (with the "additional expected changes" deviation noted above).
- `git diff -- .github/agents/docs.agent.md` — same three sections present; header still reads
  `core/agents` (see note 4, unchecked Todo item).
- `git status --short -- .opencode/agents-optional/docs.md` and `git diff --stat -- .opencode/
  agents-optional/docs.md` — both empty output (byte-identical to HEAD). Verified — proves AC-03.
- `php tools/ai/render-adapters.php --check` before vs. after this session's scoped write — before:
  29 drifted entries including `.claude/agents/docs.md` and `.github/agents/docs.agent.md`; after:
  27 entries, both `docs` entries closed, every other entry unchanged. Verified — zero new drift
  introduced, exactly two entries closed.
- `php tools/ai/validate-adapter-drift.php` — `OK: adapter drift validation completed` (WARN-only
  elsewhere, all pre-existing `docs/ai/workflow.md`/`docs/ai/project-context.md`/
  `docs/ai/AI-GUARDRAILS.md` doc-reference gaps on unrelated `packages/ai-universal-rules/
  templates/workflows/*.md` files, none mentioning `docs`). Verified — proves AC-04.
- `vendor/bin/phpunit tests/php/AiCatalogLibIoTest.php tests/php/PermissionComposeTest.php
  tests/php/PermissionRenderAdaptersTest.php` — 73 tests, 1436 assertions, OK. Verified (matches
  the plan's exact named suites).
- `vendor/bin/phpunit tests/php/ClaudeAgentRendererTest.php tests/php/CopilotAgentRendererTest.php`
  — 51 tests, 487 assertions, OK. Verified (extra confidence beyond the plan's named suites, since
  this session touched both renderers' output).
- `php tools/ai/generate-agent-permissions.php --check` — reports exactly one pre-existing entry,
  `.opencode/agents-optional/build-config.md`, identical to the DONE-plan-13/16/17 precedent
  baseline and untouched by any edit this session made. Not a regression. Verified.
- `php tools/ai/validate-generated-artifacts.php` — all 5 sub-checks `OK`, `OK: generated artifact
  baseline present`. Verified.
- `php tools/ai/ai.php placeholders --fail` — `OK: wrote docs/ai/generated/placeholders.json`,
  exit 0, no unresolved-placeholder failures. Verified.
- `mcp_Grep` for `GENERATED — DO NOT EDIT` across all `.github/agents/*.agent.md` (24 files) —
  zero say `optional/agents`; confirms note 4's "fleet-wide, not docs-specific" claim.
- `mcp_Grep` for `\$agentId === '` in `claude-agent-renderer.php` — four existing per-agent
  overrides (`refactorer`, `release-auditor`, `workflow-auditor`, `researcher`), none for `docs`;
  confirms note 3's "no docs-specific override needed" claim.
- `git diff --stat` of all touched files — `packages/ai-universal-rules/templates/optional/agents/
  docs.md` (4 lines: the Edit Scope section), `.claude/agents/docs.md` (16 lines), `.github/agents/
  docs.agent.md` (74 lines — larger than the Claude diff because the pre-session Copilot copy was
  further behind current renderer output than the pre-session Claude copy, including a missing
  `agent_assessment` block and stale Shell Boundary prose, all pre-existing renderer-fix pickup, not
  new drift introduced by this plan), and `docs/tickets/arch-todo-agent-fleet-improvement-plans-
  20260707/plan.md` (34 lines, the routing-note addition only). Verified.

### Follow-Up Session — Fresh Agent-Critic Re-Run (Note 8) — Verification Evidence

- Manual read of `packages/ai-universal-rules/templates/optional/agents/docs.md` (post-edit) — Edit
  Scope paragraph now contains the per-call self-check sentence and the reworded, category-scoped
  backstop claim (Finding 1); File Rename And Delete Policy block replaced with the one-line
  `docs/ai/approval-boundaries.md` pointer (MINOR fix 4). Verified.
- Manual read of `.claude/settings.json`'s `permissions.deny` array — confirms the deny-floor's
  actual category scope named in the reworded Edit Scope sentence (generated output, lockfiles,
  vendor/node_modules/.git/dist/build/coverage/.cache, secrets/keys/certs) and that it does not
  mention general source, workflow, or hook paths. Verified — grounds Finding 1's fix.
- `mcp_Grep` for `ai-edit\.sh.*ai-rollback\.sh.*ask` across `packages/ai-universal-rules/templates`
  — confirms the ask-tier bullet text is shared across 9 templates (5 optional-tier agents plus
  `refactorer`/`implementer`/`config-maintainer`/`bootstrapper`), i.e. this is a template-body
  sentence correct for OpenCode, not something safe to overwrite fleet-wide from a docs-scoped
  ticket — confirms the decision to fix Finding 2 via a `claude-agent-renderer.php`
  `$agentId === 'docs'` override rather than a template edit.
- `diff -u` of `.claude/agents/docs.md` before vs. after the scoped regen — shows exactly three
  changed regions (Edit Scope paragraph, Script Access `ai-edit.sh`/`ai-rollback.sh`/
  `session-checkpoint.sh` bullet, File Rename And Delete Policy block) and nothing else. Verified —
  proves the two MAJOR fixes plus MINOR fix 4 landed cleanly with no unexpected drift.
- `diff -u` of `.github/agents/docs.agent.md` before vs. after — shows the Edit Scope and File
  Rename And Delete Policy changes (both runtime-agnostic template edits) but NOT the Script Access
  bullet change (Claude-only override, correctly does not leak into the Copilot render). Verified —
  proves the `$agentId === 'docs'` scoping is correctly Claude-only.
- `git status --short -- .opencode/agents-optional/docs.md` and `git diff --stat -- .opencode/
  agents-optional/docs.md` — both empty output (byte-identical to HEAD, unaffected by this
  follow-up pass). Verified.
- `vendor/bin/phpunit tests/php/PermissionRenderAdaptersTest.php tests/php/PermissionComposeTest.php
  tests/php/ClaudeAgentRendererTest.php` — 74 tests, 1504 assertions, OK. Verified (matches the
  task's exact named suites for this follow-up pass).
- `php tools/ai/render-adapters.php --check` before vs. after this follow-up session's scoped
  write — before: 29 drifted entries (pre-existing, unrelated to this ticket); after: 27 entries,
  the same set minus `.claude/agents/docs.md` and `.github/agents/docs.agent.md` (both closed
  again), every other entry byte-identical to the before list. Verified — zero new drift introduced.
- `php tools/ai/validate-adapter-drift.php` — `OK: adapter drift validation completed`, same
  WARN-only `packages/ai-universal-rules/templates/workflows/*.md` doc-reference gaps as the
  original pass, none mentioning `docs`. Verified.
- Cleanup: deleted `/tmp/opencode/render-plan18-docs-critic-fix.php` and its two `.before` backup
  copies after use; no other artifact from this follow-up pass left behind outside this plan's own
  Affected Paths.

**Status:** The `agent-critic` re-run Todo sub-item is now closed: a fresh audit (score
76/`needs_refactor`) surfaced 2 new MAJOR findings against the regenerated `.claude/agents/docs.md`,
both fixed and re-verified above. One MINOR fix (File Rename And Delete Policy one-line pointer) was
also applied as a low-risk byproduct; the other MINOR fix (Bash Command Policy preamble trim) was
evaluated and explicitly skipped as not cleanly scopable to `docs` alone without new renderer surface
and a new regression test, per this task's own skip-if-risky allowance, and flagged as a fleet-wide
follow-up. The roster-classification conflict (AC-05) remains correctly routed, not resolved, per
this plan's own Out Of Scope boundary. One Todo Plan item remains unchecked: the `.github/agents/
docs.agent.md` generated-header confirmation (Todo line, note 4) — confirmed FALSE and explicitly
flagged as a genuine, fleet-wide (all 24 Copilot agents, not `docs`-specific) `copilot-agent-renderer.php`
defect that this plan's own Out Of Scope section forbids fixing here ("Changing ... OpenCode/Copilot
render logic itself"). This plan's own prior session already established, in this same Status
section, that an unchecked Todo item blocks archiving under this file's completion instruction
("archiving requires every Todo Plan item AND every Acceptance Criterion to be `[x]`") — that
self-documented interpretation is unchanged by this follow-up pass, and item 56 was never marked
"unchecked by design, not a real blocker" anywhere in this plan's own text; it was marked unchecked
because it is a real, unresolved (if out-of-scope-to-fix-here) defect. Per this task's own
instruction, that item is NOT touched or force-checked in this pass. **This plan therefore remains
NOT ARCHIVED.** It stays in place at its current path pending either (a) a future ticket that fixes
the fleet-wide Copilot header-label defect and closes item 56, or (b) an explicit decision by a
human owner to amend this plan's own completion instruction or Out Of Scope framing to formally
accept that gap as a permanent, non-blocking exception (neither of which this implementer session is
authorized to decide unilaterally).
