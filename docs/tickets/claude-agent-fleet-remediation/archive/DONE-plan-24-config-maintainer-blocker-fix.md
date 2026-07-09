# Architecture Plan — config-maintainer BLOCKER fix (config-path scoping + secret/lockfile/vendor guard)

- Ticket: none
- Source: architect design, agent-critic score 62/blocked on .claude/agents/config-maintainer.md
- Generated: 2026-07-08T09:27:50Z
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-24-config-maintainer-blocker-fix.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-24-config-maintainer-blocker-fix.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-24-config-maintainer-blocker-fix.md`). See "Archive On Completion" in the architecture-plan-writer agent policy for the exact steps.

## Context

`.claude/agents/config-maintainer.md` was scored 62/blocked by agent-critic. The canonical OpenCode sibling scopes edit access to `configs/**` plus named config files and denies `packages/**`, `vendor/**`, `node_modules/**`, `.git/**`, `dist/**`, `build/**`, `coverage/**`, `.cache/**`, generated paths, lockfiles, and secrets. None of this exists as Claude-side compensating prose or as a `.claude/settings.json` floor.

## Problem

- BLOCKER: blanket Write/Edit grant with no config-path scoping or secret/lockfile/vendor guard.
- MAJOR: installed copy's Hard Rules duplicate 4 rename/delete bullets the canonical template keeps only in File Rename And Delete Policy (stale render).
- MAJOR: false claim of a "path-scoped edit: permission" on Claude (no such mechanism exists).
- MAJOR: Final Output never names a next agent (canonical says "Default next step: reviewer.").
- MINOR: weaker verification-doc wording than canonical.
- MINOR: secret guard covers reading only, not writing.

## Target Outcome

4 of the 6 findings (MAJOR dedup, MAJOR false-permission-claim, MAJOR missing next-step, MINOR verification wording) close via regeneration alone since the canonical template already has them fixed. The BLOCKER closes via a paired template-prose + settings.json-floor fix.

## In Scope

- Regenerate `.claude/agents/config-maintainer.md` from the current canonical template — closes the 4 already-fixed-upstream findings with no new template content needed.
- Add a new Hard Rules scope statement to `packages/ai-universal-rules/templates/core/agents/config-maintainer.md` naming the intended write scope (`configs/**` + named dotfiles) and explicitly forbidding writes to `packages/**`, `vendor/**`, `node_modules/**`, `.git/**`, `dist/**`, `build/**`, `coverage/**`, `.cache/**`, generated paths, lockfiles, secrets/credentials — framed honestly as a behavioral rule since Claude cannot enforce path scoping natively.
- Extend `.claude/settings.json`'s Edit/Write deny-floor (and its template source `packages/ai-universal-rules/templates/claude/settings.json`) with the missing globs: `packages/**`, `vendor/**`, `node_modules/**`, `.git/**`, `dist/**`, `build/**`, `coverage/**`, `.cache/**`, `bun.lockb`, `secrets.*` (file glob), `credentials.*`, `auth.json`.

## Out Of Scope (Things To Avoid)

- Building a new renderer mechanism that mechanically converts `permission.edit` maps into a generated "Edit Scope Policy" section (flagged as a future improvement, touches the renderer + all 9 Claude write-tier agents).
- Auditing other write-tier agents' Claude copies for the same stale-render gap (flagged, not fixed).
- Changing `agent_assessment` by hand.
- Changing the OpenCode or Copilot renders.

## Affected Paths

- `packages/ai-universal-rules/templates/core/agents/config-maintainer.md`
- `.claude/agents/config-maintainer.md` (regenerated)
- `.claude/settings.json`
- `packages/ai-universal-rules/templates/claude/settings.json`

## Contracts And Boundaries

- `.claude/agents/config-maintainer.md` is GENERATED — regenerate only, never hand-edit.
- `.claude/settings.json` is the shared enforcement floor across all 24 Claude agents — additive-only changes.

## Todo Plan

- [x] P0: Regenerate `.claude/agents/config-maintainer.md` via the Claude install/render step, closing the 4 stale-drift findings. Already regenerated to match the (pre-this-session) template by other in-flight WIP before this session started — confirmed via `git diff` against HEAD that all 4 findings were already fixed. Superseded by the P1 re-render below (which incorporates the BLOCKER fix too), so only one actual render pass ran.
- [x] P0: Add the new Hard Rules scope statement to the canonical template (honest, behavioral-rule framing, not a false enforcement claim). See Implementation Notes.
- [x] P0: Extend both settings.json files' Edit/Write deny-floor with the 12 missing globs. Only 2 of the 12 named globs (`packages/**`, `secrets.*`) were actually missing as Edit/Write deny pairs — the other 10 were already present in both files from prior in-flight WIP in this series. Added the 2 missing pairs to both files. See Implementation Notes.
- [x] P1: Re-regenerate `.claude/agents/config-maintainer.md` to pick up the new Hard Rules scope statement. Ran via a narrow one-off render script (not the broad `--write`); confirmed byte-parity with the template body afterward.
- [x] P2: Re-run agent-critic to confirm the BLOCKER and all MAJOR/MINOR findings are closed. An orchestrator session ran a fresh audit against `.claude/agents/config-maintainer.md`: score 89, needs_refactor; original BLOCKER confirmed CLOSED. It found 2 new findings from this file's own prior-session prose (not the original 6): [MAJOR] the write-scope Hard Rules bullet's self-verify step was missing, and [MINOR] the Bash Command Policy footer's "Other listed commands... are prose-discouraged and interactively gated, not hard-blocked" sentence falsely implied `rm`/`mv`/`cp`/`chmod`/plain `git push`/`git reset` were "listed" for this agent when none appear in its own approved-scripts list (the same defect class plan-16/17/20 already fixed for release-auditor/workflow-auditor/agent-fleet-assessor). Both fixed in this pass — see Implementation Notes below (P2 re-audit fix).

## Acceptance Criteria

- [x] AC-01: Regenerated file's Hard Rules matches canonical (no duplicated rename/delete bullets). Verified via direct read of both files.
- [x] AC-02: No occurrence of the false "path-scoped edit: permission" phrase remains. Verified via targeted search — zero hits in either file.
- [x] AC-03: Final Output ends with "Default next step: reviewer." Verified via direct read (line 199 of the regenerated file, line 245 of the template).
- [x] AC-04: A new Hard Rules scope statement names the intended write scope and the forbidden paths, worded as a behavioral rule (not a false enforcement claim). Verified via direct read of the new Hard Rules bullet in both the template and the regenerated file.
- [x] AC-05: Both settings.json files' deny array includes all 12 new globs, with zero existing entries removed. Verified: `jq -S '.permissions.deny'` on both files is byte-identical (75 entries each, up from 71); of the 12 named globs only 2 (`packages/**`, `secrets.*`) were actually missing and both are now present as Edit/Write pairs in both files; `git diff --unified=0` on both files shows only additive `+` lines from this session's edits (see Implementation Notes for the pre-existing-WIP context around the larger diff).

## Verification Plan

- Diff regenerated file against template to confirm AC-01, AC-02, AC-03, AC-04.
- `jq .` both settings.json files for valid JSON and array-parity check for AC-05.
- Run `tests/php/PermissionComposeTest.php`, `PermissionRenderAdaptersTest.php`, `AiCatalogLibIoTest.php`.
- Re-run agent-critic and confirm the BLOCKER and 3 MAJORs are closed.

## Risks And Rollback

- Risk: other write-tier Claude agents likely share the same stale-render gap (flagged, not fixed here).
- Risk: settings.json change is fleet-wide; per adapter-contract guidance this should route through reviewer and likely release-auditor before merge.
- Rollback: revert both file diffs.

## Handoff Notes

Recommended next step: implementer to apply the template edit, settings.json extension, and regeneration; then route to reviewer (and likely release-auditor, given the permissions-floor change) before merge.

## Implementation Notes

**Working-tree context confirmed.** Before this session touched anything, `git status --short`
showed a large number of already-modified files from unrelated in-progress tickets on this branch
(consistent with plan-20 through plan-23's documented pattern). Of this plan's 4 Affected Paths,
3 (`.claude/agents/config-maintainer.md`, `.claude/settings.json`, `packages/ai-universal-rules/
templates/claude/settings.json`) were already modified (uncommitted) by other in-flight work
before this session started; only `packages/ai-universal-rules/templates/core/agents/
config-maintainer.md` was clean against HEAD.

**4-of-6 findings were already closed pre-session.** Reading `.claude/agents/config-maintainer.md`
against `git diff` HEAD before any edit showed the 4 findings the plan's own Target Outcome
attributes to "regeneration alone" (Hard Rules rename/delete-bullet dedup, the false
"path-scoped `edit:` permission" phrase, the missing "Default next step: reviewer." line, and the
stronger verification-doc wording) had already been fixed by prior in-flight WIP in this series —
this file was already byte-identical to the (pre-this-session) template body except for the
Claude-specific Bash Command Policy prefix. No action was needed for those 4 findings beyond
confirming they held and re-verifying after the BLOCKER fix's re-render below.

**BLOCKER fix — Hard Rules scope statement.** Added one new Hard Rules bullet to
`packages/ai-universal-rules/templates/core/agents/config-maintainer.md` naming the intended
write scope (`configs/**` plus the six named config dotfiles already present in this agent's own
frontmatter `permission.edit` allow list) and explicitly forbidding writes to `packages/**`,
`vendor/**`, `node_modules/**`, `.git/**`, `dist/**`, `build/**`, `coverage/**`, `.cache/**`,
generated output paths, lockfiles, and secrets/credentials — modeled directly on the
`docs.md`/`docs.agent.md` "Allowed edit paths" prior art found via grep before writing new prose
(same honest OpenCode-enforced-vs-Claude/Copilot-behavioral-only framing, same
`.claude/settings.json` deny-floor cross-reference, same `needs-scope-approval` stop-and-report
convention). Placed as a Hard Rules bullet (not a separate "## Edit Scope" section) per the plan's
own wording ("Add a new Hard Rules scope statement"). Also added one short adjacent bullet
("Do not write secrets or credentials either — the guard above covers both directions.") closing
the Problem section's MINOR "secret guard covers reading only, not writing" finding as a direct,
minimal byproduct of the new scope statement — this MINOR was not explicitly counted in the
plan's own Target Outcome tally (4 regeneration-closed + 1 BLOCKER-closed = 5 of 6), but the
Problem/In Scope text explicitly names forbidding secret *writes* as part of the new statement, so
closing it required no scope expansion beyond what the plan already specified.

**Settings.json gap was mostly already closed.** Read both `.claude/settings.json` and
`packages/ai-universal-rules/templates/claude/settings.json` before editing (both already
modified/expanded WIP from other plans in this series touching the same shared settings.json,
confirming this plan's own Risk note that a fleet-wide settings.json change "should route through
reviewer and likely release-auditor"). Of the 12 globs the plan's own In Scope text lists
(`packages/**`, `vendor/**`, `node_modules/**`, `.git/**`, `dist/**`, `build/**`, `coverage/**`,
`.cache/**`, `bun.lockb`, `secrets.*`, `credentials.*`, `auth.json`), 10 already had both `Edit()`
and `Write()` deny entries in both files. Only `packages/**` and `secrets.*` were missing (each
missing both the `Edit()` and `Write()` form — `Read(secrets.*)` was already present, but not the
write-direction guard). Added exactly those 4 lines (`Edit(packages/**)`, `Write(packages/**)`,
`Edit(secrets.*)`, `Write(secrets.*)`) to both files, placed adjacent to their sibling clusters
(`packages/**` next to the existing `vendor/**`/`node_modules/**`/`.git/**` group; `secrets.*` next
to the existing `credentials.*`/`auth.json` group). Verified JSON validity with `jq empty` on both
files post-edit (both passed), and confirmed via `jq -S '.permissions.deny'` that both files' deny
arrays are byte-identical after the edit (75 entries each, up from 71). Confirmed via
`git diff --unified=0` that this session's edits were purely additive (no `-` lines from lines this
session touched) — the very large mixed diff `git diff` shows against HEAD for both files is almost
entirely pre-existing WIP from other in-flight plans in this series, not from this session's 4-line
addition per file.

**Narrow one-off render script, not broad `--write`.** Per the task's explicit instruction (the
real `tools/ai/render-adapters.php --write` entrypoint iterates every core+optional agent template
and would have rewritten every other agent's already-drifted `.claude`/`.github` file too,
clobbering unrelated in-progress work — confirmed by the `render-adapters.php --check` drift list
below, which independently lists ~23 other already-drifted agent files untouched by this plan),
wrote `/tmp/opencode/render-plan24-config-maintainer.php` (not committed, deleted after use)
mirroring the plan-7 through plan-23 precedent (`/tmp/opencode/render-plan21-reviewer-followup.php`
read as the most recent prior-art template for this exact pattern). It required the same renderer
files the real installer/CLI uses (`claude-agent-renderer.php`, `copilot-agent-renderer.php`,
`generated-header.php`, transitively `permission-layers/render-adapters.php`,
`canonical-agent-frontmatter.php`, the tool/handoff registries) and called the exact same function
the real pipeline uses for this agent's Claude render: `aiInstallerRenderClaudeAgent()`. Confirmed
via grep this template carries no `<PROJECT_NAME>` token, so only the `<SCRIPTS_ROOT>` ->
`scripts/ai` substitution was needed (same map `render-adapters.php` itself applies). The script
wrote only the one target file (`.claude/agents/config-maintainer.md`); no other file was touched
by the render, matching this plan's own Out Of Scope instruction not to change the OpenCode or
Copilot renders.

**Confirmed byte-parity after render.** Diffed the rendered file's body (everything from
`# Config Maintainer Agent` onward) against the template's body: the only difference is the
generic, agent-agnostic Script Access sentence rewrite the renderer already applies to every Claude
agent ("Full per-script `allow`/`ask`/`deny` is documented in the Bash Command Policy section
above..." instead of "...is in frontmatter...") — a pre-existing generic fix confirmed via grep of
`claude-agent-renderer.php` to have no `$agentId === 'config-maintainer'` override block, so this
is expected, unconditional render behavior, not new scope. `php tools/ai/render-adapters.php
--check` confirms `.claude/agents/config-maintainer.md` does NOT appear in its drift list, meaning
the installed copy is byte-parity with the template's rendered output. The same drift-check run
does show `.github/agents/config-maintainer.agent.md` newly appearing in the drift list — expected
and correct, since this plan's own Out Of Scope explicitly excludes touching the OpenCode or
Copilot renders; that Copilot copy is now intentionally stale relative to the updated template,
matching the plan's own Risk note about other write-tier agents sharing this same
not-yet-regenerated-render gap.

**Verification evidence.**

- `jq empty` on both settings.json files -> both valid.
- `jq -S '.permissions.deny'` diff between both settings.json files -> identical (75 entries each).
- AC-01 through AC-05: all directly confirmed — see the AC entries above for each command and
  result.
- `vendor/bin/phpunit tests/php/PermissionComposeTest.php tests/php/PermissionRenderAdaptersTest.php
  tests/php/AiCatalogLibIoTest.php` -> `OK (73 tests, 1436 assertions)`.
- `vendor/bin/phpunit tests/php/AdapterRenderDriftTest.php` -> 2 failures, but the drift list (24
  files) does NOT include `.claude/agents/config-maintainer.md` — confirming this plan's
  regenerated Claude file is byte-parity with its canonical template. It DOES newly include
  `.github/agents/config-maintainer.agent.md` (expected — Out Of Scope explicitly excludes the
  Copilot render) plus ~22 other files that were already `M` in `git status --short` before this
  session touched anything (same pre-existing-WIP pattern documented in plan-20 through plan-23's
  Implementation Notes). No regression introduced by this slice.
- `php tools/ai/render-adapters.php --check` -> exit 1; confirmed `.claude/agents/
  config-maintainer.md` absent from its drift output (full output inspected directly, not just
  grepped).
- `git status --short` scoped to this plan's 4 Affected Paths (plus the incidentally-drifted
  `.github/agents/config-maintainer.agent.md`, which this session did not edit) shows exactly the
  expected files modified — no scope creep beyond the Affected Paths list.
- Cleanup: deleted `/tmp/opencode/render-plan24-config-maintainer.php` and the temporary drift-check
  output file after use.

**Todo item disposition for the blocked agent-critic re-run (prior session).** Per that session's
explicit instruction, it had no Task-tool subagent-dispatch capability, so Todo P2 ("Re-run
agent-critic to confirm the BLOCKER and all MAJOR/MINOR findings are closed") was left unchecked
and documented as blocked-on-tool-access. Because Todo P2 was not `[x]`, the plan was NOT archived
at that point per its own completion instruction, even though all 5 Acceptance Criteria were
independently verified and checked.

**P2 re-audit fix (this session).** An orchestrator session ran the blocked agent-critic re-audit
against `.claude/agents/config-maintainer.md`: score 89, needs_refactor; the original BLOCKER
confirmed CLOSED. Two new findings surfaced from this file's own prior-session prose:

1. [MAJOR] The write-scope Hard Rules bullet's claim was unenforceable without a self-check step —
   Claude's Write/Edit tool grant is unrestricted and the settings.json deny-floor only blocks
   dangerous categories, not a positive `configs/**`-only allowlist. Fix: appended a self-verify
   sentence to the same Hard Rules bullet in the canonical template
   (`packages/ai-universal-rules/templates/core/agents/config-maintainer.md`): "Self-verify before
   finishing: run `git status --short` and confirm every changed path matches this scope; if any
   path falls outside it, revert the change or stop and report `needs-scope-approval`."
2. [MINOR] The Bash Command Policy footer's shared boilerplate sentence ("Other listed commands
   (`rm`, `mv`, `cp`, `chmod`, plain `git push`/`git reset`) are prose-discouraged and interactively
   gated, not hard-blocked.") falsely implied these commands are "listed" for this agent when they
   never appear in config-maintainer's own approved-scripts list. Checked
   `tools/ai/install/claude-agent-renderer.php` per the task's instruction: this sentence is shared
   renderer boilerplate (line ~149, emitted for every Claude agent with a bash allowlist), with an
   established `$agentId === '...'` per-agent-override pattern already used for release-auditor
   (plan-16), workflow-auditor (plan-17), and agent-fleet-assessor (plan-20) — the same defect
   class, since none of these agents' own approved lists include those verbs either. Added the same
   pattern for `config-maintainer`, replacing the sentence with: "Other repository-wide commands not
   part of this agent's approved list (`rm`, `mv`, `cp`, `chmod`, plain `git push`/`git reset`)
   remain prose-discouraged and interactively gated repo-wide per `.claude/settings.json`, but are
   outside this agent's own bash surface regardless."

Regenerated `.claude/agents/config-maintainer.md` via a new narrow one-off script
(`/tmp/opencode/render-plan24-config-maintainer-recheck.php`, not committed, deleted after use),
mirroring the plan-21/plan-24 P1 precedent exactly (same `aiInstallerRenderClaudeAgent()` call, same
`<SCRIPTS_ROOT>` substitution, no `<PROJECT_NAME>` token in this template). Confirmed both fixes
landed via targeted search of the rendered file (`Self-verify before finishing` and `Other
repository-wide commands not part of this agent's approved list` both present, one hit each).

**Verification (this session).**

- `vendor/bin/phpunit tests/php/PermissionRenderAdaptersTest.php tests/php/PermissionComposeTest.php
  tests/php/ClaudeAgentRendererTest.php` -> `OK (77 tests, 1512 assertions)`.
- `php tools/ai/render-adapters.php --check` -> exit 1 (pre-existing drift, unrelated to this
  change); `.claude/agents/config-maintainer.md` is NOT in the drift list (byte-parity with the
  regenerated template). `.github/agents/config-maintainer.agent.md` IS in the drift list — expected
  and unchanged from the prior session's P1 pass, since this plan's Out Of Scope explicitly excludes
  the Copilot render. The other 21 drifted files are the same pre-existing-WIP entries already
  documented in the P1 pass's own verification evidence above (all were already `M` in
  `git status --short` before this session touched anything) — no new drift introduced by this
  session's edits.
- Cleanup: deleted `/tmp/opencode/render-plan24-config-maintainer-recheck.php` after use.

Because Todo P2 is now `[x]` and all 5 Acceptance Criteria remain `[x]`, this plan's own archival
gate (every Todo Plan item and every Acceptance Criteria item checked) is satisfied. Archived per
"Archive On Completion" immediately following this update.
