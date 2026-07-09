# Architecture Plan — refactorer agent-critic fixes round 2 (settings.json deny-floor + test-gate + prose)

- Ticket: none
- Source: architect design, agent-critic score 65/blocked on .claude/agents/refactorer.md (note: a prior remediation already closed git branch*/validate-*.php/authorization-and-tool-governance findings — see docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-1-refactorer-agent-critic-fixes.md — do not re-litigate those)
- Generated: 2026-07-08T09:27:50Z
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-10-refactorer-agent-critic-fixes-round2.md
- Archived: 2026-07-08 (all Todo Plan items and Acceptance Criteria complete)

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-10-refactorer-agent-critic-fixes-round2.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-10-refactorer-agent-critic-fixes-round2.md`). See "Archive On Completion" in the architecture-plan-writer agent policy for the exact steps.

## Context

`.claude/agents/refactorer.md` was scored 65/blocked by agent-critic in a second round of review, after a prior remediation (DONE-plan-1) already closed the git branch*/validate-*.php/authorization-and-tool-governance findings. This plan covers only the remaining findings.

## Problem

- BLOCKER: `.claude/settings.json` has no deny for `vendor/**`, `node_modules/**`, `.git/**`, `dist/**`, `build/**`, `coverage/**`, `package-lock.json`/`pnpm-lock.yaml`/`bun.lockb`, `**/generated/**`, `**/*.gen.*`, `credentials.*`, `auth.json` (OpenCode sibling denies all).
- MAJOR: Mandatory Test Gate scripts (phpunit family, `ai-test-select.sh`, `run-repo-tests.sh`) are claimed approved but absent from settings.json's allow list.
- MAJOR: secret Read-deny gaps: settings.json denies `.env`/`.pem`/`.key`/`id_rsa*` but not `*.crt`, `credentials.*`, `auth.json`, root-level `secrets.*`.
- MINOR: body references "the permission table," an OpenCode-only concept absent from Claude's frontmatter.
- MINOR: file is ~7% over the soft line-max (257 vs 240).

## Target Outcome

settings.json deny-floor matches the OpenCode parity baseline for refactorer's write scope; Mandatory Test Gate commands are actually allowed; secret Read-deny gaps closed; "permission table" wording corrected; body lightly trimmed.

## In Scope

- Track A (config-maintainer-owned): edit `packages/ai-universal-rules/templates/claude/settings.json` + `.claude/settings.json` — add 13 Edit+Write deny pairs (`vendor/**`, `node_modules/**`, `.git/**`, `dist/**`, `build/**`, `coverage/**`, `package-lock.json`, `pnpm-lock.yaml`, `bun.lockb`, `**/generated/**`, `**/*.gen.*`, `credentials.*`, `auth.json`); add allow entries for `vendor/bin/phpunit *`, `./vendor/bin/phpunit *`, `phpunit *`, `scripts/ai/ai-test-select.sh *`, `scripts/ai/run-repo-tests.sh*`; add Read-deny for `**/*.crt`, `credentials.*`, `auth.json`, `secrets.*`.
- Track B (implementer-owned): edit `packages/ai-universal-rules/templates/core/agents/refactorer.md` — reword the "permission table" sentence to be adapter-neutral; lightly tighten verbose prose sections (Shell Governance, Script Access, File Rename And Delete Policy) toward the 240-line soft max without removing any Hard Rule/Stop Condition/Mandatory Test Gate step/Final Output section.

## Out Of Scope (Things To Avoid)

- Re-touching git branch*/validate-*.php/authorization-and-tool-governance (already closed by DONE-plan-1).
- Building codegen for `.claude/settings.json` from `permission-layers/`.
- Fixing `.opencode/agents/refactorer.md`'s (319 lines) or `.github/agents/refactorer.agent.md`'s (269 lines) pre-existing soft-max overages as a required outcome (incidental benefit only, not required).

## Affected Paths

- `packages/ai-universal-rules/templates/claude/settings.json`
- `.claude/settings.json`
- `packages/ai-universal-rules/templates/core/agents/refactorer.md`
- `.opencode/agents/refactorer.md`
- `.claude/agents/refactorer.md`
- `.github/agents/refactorer.agent.md` (all regenerated after Track B)

## Contracts And Boundaries

Track A owner: config-maintainer. Track B owner: implementer. Both independent, either order.

## Todo Plan

- [x] P0: (Track A) Add the 13 Edit+Write deny pairs to both settings.json files.
- [x] P0: (Track A) Add the 5 test-gate allow entries to both settings.json files.
- [x] P0: (Track A) Add the 4 secret Read-deny entries to both settings.json files.
- [x] P1: (Track B) Reword the "permission table" sentence in the canonical template to be adapter-neutral.
- [x] P1: (Track B) Lightly trim verbose sections toward the 240-line soft max, preserving all structural sections.
- [x] P2: Regenerate all three rendered copies and diff-confirm propagation; re-run agent-critic. — a fresh orchestrator-run agent-critic audit (score 86) found 4 new mechanical findings (2 MAJOR, 2 MINOR) on `.claude/agents/refactorer.md`; all 4 are fixed and re-verified in this pass (see "Round 3 — fresh agent-critic findings" below).

## Acceptance Criteria

- [x] AC-01: Both settings.json files contain all 13 new deny pairs, 5 new allow entries, and 4 new Read-deny entries, with zero existing entries removed.
- [x] AC-02: "the permission table" no longer appears in `.claude/agents/refactorer.md`; an equally accurate phrase appears in `.opencode/agents/refactorer.md`.
- [x] AC-03: `wc -l` on `.claude/agents/refactorer.md` trends toward ≤240 with no structural section deleted.
- [x] AC-04: A fresh agent-critic run scores materially above 65 with these five findings resolved. — a fresh orchestrator-run agent-critic audit scored the file 86 (well above 65) with all five round-2 findings resolved; that same audit surfaced 4 new mechanical findings (2 MAJOR, 2 MINOR), which are fixed and re-verified below ("Round 3").

## Verification Plan

- `git diff` scoped to the six affected files.
- JSON validity + array-parity checks on settings.json.
- `wc -l` on the regenerated file.
- Structural diff (not just line count) to confirm no Hard Rule/Stop Condition/Test Gate/Final Output section was deleted.
- Re-run agent-critic.

## Risks And Rollback

- Risk: settings.json allow-list addition (phpunit/test commands) becomes available fleet-wide, not refactorer-private (assessed low risk, execution-only, non-mutating).
- Risk: `.opencode/agents/refactorer.md` is already near its own hard-max (319/320); verify no regression there.
- Rollback: revert both tracks' diffs independently.

## Handoff Notes

Recommended owner split — config-maintainer for Track A (settings.json), implementer for Track B (template prose + propagation). Converge on one agent-critic rerun after both land.

## Implementation Notes

Both tracks landed in one pass (implementer role, no config-maintainer/implementer split needed
since both were straightforward and independent per Contracts And Boundaries).

1. **Working-tree state discovered before editing.** `git status --short` on the plan's Affected
   Paths showed `.claude/agents/refactorer.md`, `.claude/settings.json`, and
   `packages/ai-universal-rules/templates/claude/settings.json` already carried a large
   pre-existing uncommitted diff from another in-progress ticket (a bash-policy allowlist
   expansion — new `git grep`/`rg`/`fd`/read-only-tool entries, plus a reworded "Bash Command
   Policy" opening sentence in `.claude/agents/refactorer.md` that, on inspection, exactly matches
   this repo's own `claude-agent-renderer.php` hardcoded wording). `.opencode/agents/refactorer.md`
   and `.github/agents/refactorer.agent.md` were clean (byte-identical to HEAD). Per the task's
   instruction, this pre-existing unrelated diff was left untouched and only added to, never
   reverted — the settings.json edits below are pure appends onto the existing (dirty) arrays, and
   the agent-file regeneration reproduces that same pre-existing prose byte-for-byte since it
   already matched the current renderer source.
2. **Track A (settings.json, both files)** — added exactly the 13 named paths as 26 `Edit(...)`/
   `Write(...)` deny-pair entries, the 5 named test-gate commands as `Bash(...)` allow entries, and
   the 4 named globs as `Read(...)` deny entries, to both
   `packages/ai-universal-rules/templates/claude/settings.json` and `.claude/settings.json`. No
   existing array entry was removed — confirmed by an exact-match `jq` membership check for all 35
   new strings against both files (each found exactly once) and by inspecting the diff, whose only
   `-`/`+` line pairs are the pre-existing final-array-element lines re-emitted with a trailing
   comma so the new entries could follow (not a real removal). Both files remain valid JSON (`jq -e
   .` on each). None of the 13 new deny paths overlapped any pre-existing deny entry (checked
   against the existing `**/*.lock`/`**/*.pem`/`**/*.key`/`**/*.crt`/`.env`/`.env.*`/
   `**/secrets/**` set); `**/*.crt` already had an Edit/Write deny pair from a prior remediation
   but had no Read-deny counterpart, which is exactly the gap AC-01/the Problem statement named.
3. **Track B (canonical template + reword)** — reworded the sole offending "the permission table"
   sentence (Required Flow step 1) to "This agent's directory-level `allow` grants are an outer
   ceiling…", which is accurate on every runtime (OpenCode literally has a `permission.edit`
   table; Claude/Copilot have the equivalent grant expressed differently) rather than asserting a
   frontmatter shape only OpenCode has. Verified via `rg` that the literal phrase "the permission
   table"/"permission table" is now absent from all four files (template + 3 rendered copies) and
   the adapter-neutral replacement phrase is present in all four.
4. **Track B (light trim)** — trimmed exactly the three named sections (Shell Governance, Script
   Access, File Rename And Delete Policy) by merging same-topic sentences/bullets onto one line
   each (no sentence or bullet's content was dropped, only line breaks removed): Shell Governance's
   first two sentences merged into one line; Script Access's two `ask`-tier tool bullets
   (`ai-edit.sh`/`ai-rollback.sh` and `session-checkpoint.sh`) merged into one bullet; File Rename
   And Delete Policy's first two bullets merged into one, and its next two bullets merged into one.
   Net: canonical template 318 -> 314 lines; rendered `.claude/agents/refactorer.md` 258 -> 254
   lines (trending toward, not fully reaching, the 240 soft max — consistent with the plan's
   "lightly trim… toward" wording, not a hard requirement to hit 240). All four named
   do-not-remove sections (Hard Rules, Stop Conditions, Mandatory Test Gate, Final Output) were
   left untouched and verified present with unchanged content in all four files (Mandatory Test
   Gate's 6 numbered steps individually confirmed intact).
5. **Regeneration — narrowly scoped, broad `--write`/`--apply` deliberately avoided.** Both the
   full installer (`aiInstallerCopyDirAsClaudeAgents`/`aiInstallerCopyDirAsOpenCodeAgents`, which
   delete-and-rewrite whole destination trees) and the repo's own dogfood gate
   (`tools/ai/render-adapters.php --write`, which iterates every core+optional template and
   rewrites any `.claude/agents/*.md`/`.github/agents/*.agent.md` that differs from its rendered
   form) would have touched far more than this plan's 3 target files — confirmed via
   `git status --short -- .claude/agents/ .github/agents/ .opencode/agents/` = 51 already-modified
   entries before this session's edits (other in-progress tickets). Per the task's explicit
   instruction and the same precedent plan-7/8/9 set, a narrowly-scoped one-off script
   (`/tmp/opencode/render-refactorer.php`, not committed, deleted after use) was written instead:
   it requires the exact same renderer functions the real tools use
   (`aiInstallerRenderClaudeAgent()`, `aiInstallerRenderCopilotAgent()`,
   `aiInstallerInsertGeneratedHeaderAfterFrontmatter()` for the OpenCode copy, mirroring
   `aiInstallerCopyDirAsOpenCodeAgents()`'s per-file step), applies the identical `<SCRIPTS_ROOT>`/
   `<PROJECT_NAME>` placeholder resolution `tools/ai/render-adapters.php` applies via
   `strtr($rendered, $placeholderMap)` (that file could not be `require()`'d directly — it is a
   self-executing CLI with a top-level `exit()` — so the map-construction logic was reproduced
   inline instead of duplicated as a new abstraction), and writes to only the 3 target files this
   plan's Affected Paths name. A before/after
   `git status --short -- .claude/agents/ .github/agents/ .opencode/agents/` line-count comparison
   (51 before, 53 after — the +2 is `.github/agents/refactorer.agent.md` and
   `.opencode/agents/refactorer.md` newly entering the modified set; `.claude/agents/refactorer.md`
   was already modified from the pre-existing unrelated diff) confirmed the script touched only
   the 3 target files. `php tools/ai/render-adapters.php --check` afterward reported zero drift
   for any `refactorer.md`/`refactorer.agent.md` path, confirming the narrowly-scoped script's
   output is byte-identical to what the real dogfood `--write` would have produced for this agent.
6. **Todo P2 (regeneration + diff-confirm half)** — confirmed via `git diff --stat` across all 6
   Affected Paths (242 insertions, 41 deletions across exactly those 6 files, no other file
   touched) and via the per-file diffs above that the template edits propagated correctly and only
   the intended lines changed in each of the 3 rendered copies.
7. **Todo P2 / AC-04 (agent-critic re-run)** — not run. This session has no Task-tool
   subagent-dispatch capability, so re-running the `agent-critic` persona was not possible; both
   items are left unchecked per the task's explicit instruction rather than claimed as done.
8. **Out Of Scope respected** — git branch*/validate-*.php/authorization-and-tool-governance
   findings from DONE-plan-1 were not re-touched; no codegen for `.claude/settings.json` from
   `permission-layers/` was built (settings.json stays hand-maintained JSON, matching its current
   form); `.opencode/agents/refactorer.md`'s and `.github/agents/refactorer.agent.md`'s soft-max
   overages were not required to be fully closed and were not — both improved as an incidental
   byproduct of the Track B trim (319 -> 315 and 269 -> 265 respectively) but remain above their
   own soft maxes, which the plan explicitly does not require fixing here.

### Verification Evidence

- `jq -e . <file>` on both settings.json files — both valid JSON. Verified.
- Exact-match `jq` membership check for all 35 new strings (26 deny + 5 allow + 4 read-deny)
  against both settings.json files — each found exactly once in each file, zero mismatches.
  Verified — proves AC-01.
- `git diff -U0` on both settings.json files, inspected line-by-line — the only `-` lines are the
  pre-existing final-array-element strings re-emitted with a trailing comma (not a removal);
  `allow`/`deny` array element counts both increased from their pre-existing baseline by exactly
  5 and 30 (26 deny + 4 read-deny) respectively in both files. Verified — proves "zero existing
  entries removed."
- `rg -n "the permission table|permission table"` across the template and all 3 rendered
  copies — zero matches. `rg -n "directory-level .allow. grants"` — present in all 3 rendered
  copies with the adapter-neutral wording. Verified — proves AC-02.
- `wc -l` before/after: canonical template 318 -> 314; `.claude/agents/refactorer.md` 258 -> 254;
  `.opencode/agents/refactorer.md` 319 -> 315; `.github/agents/refactorer.agent.md` 269 -> 265.
  Verified — proves AC-03 (trending toward 240, no regression on `.opencode/agents/refactorer.md`'s
  near-hard-max risk called out in Risks And Rollback).
- `rg -c` for `## Hard Rules`, `## Stop Conditions`, `## Mandatory Test Gate`, `## Final Output` —
  each present exactly once in the template and all 3 rendered copies; the Mandatory Test Gate's
  6 numbered steps individually confirmed present and unchanged via direct read. Verified — proves
  "no structural section deleted."
- `git status --short -- .claude/agents/ .github/agents/ .opencode/agents/` before/after the
  narrow render script (51 -> 53, +2 = exactly the 2 newly-touched target files) — confirmed no
  collateral files were touched. Verified.
- `php tools/ai/render-adapters.php --check` — zero drift entries for any `refactorer.md`/
  `refactorer.agent.md` path (the 5 reported drift entries — `implementer.md`/
  `implementer.agent.md`, `repository-researcher.agent.md`,
  `agent-creator-semantic-verifier.agent.md`, `agent-creator-static-validator.agent.md` — are all
  pre-existing from other in-progress tickets, matching DONE-plan-9's evidence of the same 5
  files). Verified — proves the narrowly-scoped script's output is byte-identical to the real
  renderer's.
- `php tools/ai/validate-adapter-drift.php` — completed with `OK: adapter drift validation
  completed`; the only `refactorer`-matching WARN lines point at a pre-existing, unrelated
  `.claude/worktrees/agent-add4ef88765702e5b/` copy, not this plan's target files. Verified.
- `php tools/ai/validate-install-surface.php` — exit 0; the only `refactorer`-matching lines are
  pre-existing soft-max WARNs on `.github/agents/refactorer.agent.md` (265, was 269) and
  `.opencode/agents/refactorer.md` (315, was 319) — both improved, neither newly introduced, and
  both explicitly Out Of Scope to fully close per this plan. Verified.
- `vendor/bin/phpunit tests/php/PermissionRenderAdaptersTest.php tests/php/PermissionComposeTest.php`
  — 50 tests, 1305 assertions, OK.
  `vendor/bin/phpunit tests/php/ClaudeAgentRendererTest.php tests/php/ClaudeSettingsMergeTest.php tests/php/AiCatalogLibIoTest.php`
  — 55 tests, 345 assertions, OK. Verified (matches the plan's Verification Plan bullets; no
  test in the repo directly asserts settings.json's plain-JSON contents, so the `jq`-based checks
  above are the direct evidence for that half).
- Not run: re-run of `agent-critic` — no subagent-dispatch (Task-tool) capability available in
  this session, consistent with plan-8/plan-9's precedent. This blocks Todo P2's second half and
  AC-04 specifically; all five underlying findings (BLOCKER settings.json deny-floor, MAJOR
  test-gate allow gap, MAJOR secret Read-deny gaps, MINOR "permission table" wording, MINOR
  soft-line-max overage) are resolved and evidenced above, but the fresh score itself could not be
  produced.
- Not run: `composer test:fast` (full suite) — the change is two settings.json edits and one
  template + 3 regenerated agent files; already covered by the plan's own targeted 105-test/
  1650-assertion run above (across the two `vendor/bin/phpunit` invocations). A full-suite run was
  judged unnecessary for this bounded slice, matching plan-8/plan-9's judgment call for
  equivalently-sized changes. Recommended if a reviewer wants broader confirmation.

### Round 3 — fresh agent-critic findings (score 86, routed to implementer)

An orchestrator session ran the previously-blocked fresh `agent-critic` audit and scored
`.claude/agents/refactorer.md` 86/100 — confirming the round-2 findings above stayed resolved —
while surfacing 4 new mechanical findings. This implementation pass fixed all 4.

1. **MAJOR — `.claude/settings.json` missing 10 approved-script allow entries.** The rendered
   file's own "Approved scripts" list claimed `php tools/ai/validate-agent-assessment.php *`,
   `php tools/ai/validate-agent-assessment-values.php`, `php tools/ai/validate-adapter-drift.php *`,
   `php tools/ai/validate-ai-config.php`, `semgrep *`, `markdownlint-cli2 *`, `git stash list*`,
   `git stash show*`, `php -l *`, and `bash scripts/doctor.sh*` as approved, but none of the 10 had
   a matching `Bash(...)` entry in either `.claude/settings.json`'s or the template's
   `permissions.allow` array. Added all 10 as new `Bash(...)` allow entries to both files, appended
   after the existing test-related entries — zero existing entries removed or reordered. Verified
   via `jq -e .` (both files valid JSON) and by direct read of the appended block in both files.
2. **MAJOR — Required Flow step 1 didn't disclose Claude Code's actual permission shape.** Added a
   clarifying sentence to the canonical template's Required Flow step 1: on Claude Code, `Edit`/
   `Write` are granted repo-wide except the `deny` paths named in `.claude/settings.json` — there is
   no path-level permission narrowing the declared scope contract to the target file(s); the
   contract is enforced entirely by this agent's own self-discipline and the Stop Conditions
   section. Propagated to all 3 rendered copies via the narrow regen script.
3. **MINOR — `ai-verify.sh` exact-match "Approved script" bullet not annotated.** Added a note to
   the Script Access section's `ai-verify.sh` bullet clarifying that even the exact-match
   `AI_VERIFY_SCOPE=changed VERIFY_SECRETS=0 bash scripts/ai/ai-verify.sh *` form (listed `allow` in
   the OpenCode permission table and shown as an "Approved script" in the Claude Bash Command
   Policy body) still surfaces an interactive Claude Code approval prompt unless
   `.claude/settings.json`'s `permissions.allow` also carries a matching `Bash(...)` entry.
4. **MINOR — 254 lines, over the 240 soft max.** Collapsed the named near-duplicate git-forensic
   bullets (`git show*`/`git blame*`/`git rev-parse*`/`git ls-files*`) into one line pointing to
   `docs/ai/agent-script-access.md`, per the finding. That collapse alone (254 -> 251) was not
   sufficient to reach under 240, so — staying within the same "collapse near-duplicate Bash
   Command Policy bullets" technique the finding itself sanctions — also collapsed 4 more
   same-script/different-env-prefix clusters (`ai-search.sh`, `ai-search-multi.sh`,
   `preview-file.sh` plain/`AI_OUTPUT=json`/`env AI_OUTPUT=json` triplets, and the
   `ai-verify.sh` plain/`env`-prefixed pair) into one line each, plus 3 light, content-preserving
   body-prose merges (Similarity And Duplication Rule's 3 reuse-check bullets merged into one
   sentence; File Rename And Delete Policy's 4 bullets merged into 2; Shell Governance's 2
   sentences merged into 1). No Hard Rule, Stop Condition, Mandatory Test Gate step, or Final
   Output section line was touched. Result: `.claude/agents/refactorer.md` 254 -> 237 lines (under
   240, 3-line margin); canonical template 314 -> 307; `.opencode/agents/refactorer.md` 315 -> 308;
   `.github/agents/refactorer.agent.md` 265 -> 258.

   Implementation detail: fixes 2-4's git-forensic/env-prefix/rename collapses in the "Approved
   scripts" bullet list are auto-generated by `tools/ai/install/claude-agent-renderer.php`, not
   literal template text, so the collapse logic was added there as a data-driven cluster list
   gated strictly on `$agentId === 'refactorer'` — every other Claude-rendered agent's bullet
   generation is byte-identical to before (confirmed via `php tools/ai/render-adapters.php --check`
   showing the exact same 5 pre-existing unrelated drift entries — `implementer.md`/
   `implementer.agent.md`, `repository-researcher.agent.md`,
   `agent-creator-semantic-verifier.agent.md`, `agent-creator-static-validator.agent.md` — as
   before this pass, with zero new drift introduced for any agent other than refactorer).

Regeneration used the same narrowly-scoped one-off-script pattern as plan-7/8/9/10's earlier
passes: a PHP script at `/tmp/opencode/render-refactorer.php` (not committed, deleted after use)
required the same renderer functions the real tools use
(`aiInstallerRenderClaudeAgent()`/`aiInstallerRenderCopilotAgent()`/
`aiInstallerInsertGeneratedHeaderAfterFrontmatter()`), applied the same `<SCRIPTS_ROOT>`/
`<PROJECT_NAME>` placeholder map `tools/ai/render-adapters.php` uses, and wrote only
`.claude/agents/refactorer.md`, `.github/agents/refactorer.agent.md`, and
`.opencode/agents/refactorer.md`. `git status --short -- .claude/agents/ .github/agents/
.opencode/agents/` stayed at 53 entries before and after (these 3 files were already modified from
the pre-existing round-2 diff, so the count did not change) — confirming no collateral file was
touched.

#### Round 3 Verification Evidence

- `jq -e . .claude/settings.json` and `jq -e . packages/ai-universal-rules/templates/claude/settings.json`
  — both valid JSON. Verified — proves the settings.json edits didn't break JSON syntax.
- `php tools/ai/render-adapters.php --check` — reports drift for exactly the same 5 pre-existing,
  unrelated files as before this pass (`implementer.md`, `implementer.agent.md`,
  `repository-researcher.agent.md`, `agent-creator-semantic-verifier.agent.md`,
  `agent-creator-static-validator.agent.md`); zero drift for any `refactorer.md`/
  `refactorer.agent.md` path. Verified — proves the narrow regen script's output is byte-identical
  to what the real renderer produces for refactorer's 3 rendered copies.
- `php tools/ai/validate-adapter-drift.php` — completed with `OK: adapter drift validation
  completed`; the only `refactorer`-matching WARN lines point at the same pre-existing, unrelated
  `.claude/worktrees/agent-add4ef88765702e5b/` copy noted in the round-2 evidence, not this pass's
  target files. Verified.
- `wc -l .claude/agents/refactorer.md` — 237 (< 240). Verified — proves the MINOR line-max finding
  is resolved, not just "trending toward" as round 2 left it.
- `vendor/bin/phpunit tests/php/PermissionRenderAdaptersTest.php tests/php/PermissionComposeTest.php tests/php/ClaudeAgentRendererTest.php tests/php/ClaudeSettingsMergeTest.php`
  — 82 tests, 1520 assertions, OK. Verified.
- `php -l tools/ai/install/claude-agent-renderer.php` — no syntax errors. Verified.

**Status update (this implementation pass):** all Todo Plan items and all Acceptance Criteria
items are now `[x]`. Per this file's own completion instruction, it is archived as part of this
pass — see `archive/DONE-plan-10-refactorer-agent-critic-fixes-round2.md`.
