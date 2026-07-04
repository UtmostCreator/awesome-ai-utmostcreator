# Architecture Plan — Claude Code Adapter Parity (agents, memory, permissions, skills, profiles)

- Ticket: none
- Source: architect design session (this conversation), 7-slice decomposition, specificity 90/100
- Generated: 20260704-120000
- Plan folder: docs/tickets/arch-todo-claude-code-adapter-parity-20260704-120000/
- Status: **In Progress** (P0 complete, verified; P1/P2 not started)
- Risk: **MEDIUM-HIGH** (new install surface touching packs/profiles/core dispatch; JSON-merge is new code; must not clobber existing user/graphify Claude files)

## Context

This repo already ships Copilot (`.github/**`) and OpenCode (`.opencode/**`) as fully rendered
adapters from one canonical agent source
(`packages/ai-universal-rules/templates/core/agents/*.md`, 13 agents), driven by the pack registry
(`tools/ai/install/packs.php`) and per-runtime renderers dispatched by `install_type` in
`tools/ai/install/core.php` (L215-246: `copilot-agents`, `opencode-agents`, `skill-dirs`,
`opencode-commands`).

Claude Code today has only two hand-grown, non-kit-managed surfaces: `CLAUDE.md` (confirmed
hand-maintained and NOT in the pack registry per `docs/ai/adapter-contract.md`) and
`.claude/settings.json` (graphify's own `PreToolUse` hooks, installed by graphify's own installer,
not this kit). There is no `adapter-claude` pack, no `claude` profile, no `.claude/agents/`
directory, and no Claude renderer anywhere in `tools/ai/install/`.

Authoritative Claude Code sub-agent contract (fetched from `docs.anthropic.com`, 2026-07-04):
sub-agents are markdown files in `.claude/agents/*.md` (project scope) with required frontmatter
`name` + `description`, and optional `tools`, `disallowedTools`, `model`
(`sonnet`/`opus`/`haiku`/`inherit`), `permissionMode`
(`default`/`plan`/`acceptEdits`/`dontAsk`/`bypassPermissions`), `skills`, `hooks`,
`Agent(agent_type)` spawn allowlists. `.claude/settings.json` carries `permissions.allow`/`deny`
and lifecycle hooks (`PreToolUse`, `PostToolUse`, `SubagentStart`, `SubagentStop`).
`AskUserQuestion` and `ExitPlanMode` are main-session-only tools — **not available inside a
subagent** — which matters for the "asking questions" requirement: a Claude subagent cannot
interactively ask; it must fall back to the prose handoff/stop-condition pattern already used for
OpenCode subagents.

**KEY RISK (research-confirmed, reused from `arch-todo-install-editions-20260614-230848/plan.md`):**
`aiInstallerResolveSelectedPacks()` (`packs.php` ~L438-448) hardcodes profile-name lists per
runtime (`'github-copilot'` and `'opencode'` only) to decide which adapter pack to strip/re-add
under `--runtime` override. A new `claude`/`claude-code` runtime value is NOT handled by this
function today (falls through the implicit no-op else). **Chosen approach, reusing the prior
resolved pattern (b):** every new profile/edition definition in `profiles.php` includes
`adapter-claude` directly, so the runtime re-add branch is defense-in-depth only, never load-bearing.
A `claude-code` branch is still added to `aiInstallerResolveSelectedPacks()` for explicit
single-runtime scoping and to strip the other two adapters when `--runtime claude-code` is passed.

**Second confirmed constraint:** the installer has exactly two `merge_strategy` values implemented
anywhere (`replace`, `skip-if-exists`) and **no JSON deep-merge primitive exists** in
`tools/ai/install/*.php` (`array_merge_recursive` does not appear; `never_auto_merge` is a
conflict-safety gate at plan time, not a content merge). Shipping a kit-managed
`.claude/settings.json` therefore needs one new, narrow, purpose-built merge function — not a
generic risky one — that unions `permissions.allow`/`permissions.deny` arrays and preserves any
pre-existing `hooks.PreToolUse` entries (e.g. graphify's) rather than overwriting them.

## Problem

Claude Code CLI is not a first-class shipped runtime. Everything Copilot/OpenCode get for free
(rendered agents, capability routing, permission enforcement, handoff contracts, skills/commands)
is absent or hand-maintained for Claude, creating drift risk and an incomplete "ship everywhere"
promise the repo's own `docs/ai/integration-matrix.md` already models as a target.

## Target Outcome

`.claude/agents/*.md`, `CLAUDE.md`, and `.claude/settings.json` are rendered/merged from the same
canonical source and pack-registry machinery as Copilot/OpenCode, selectable via a `claude` profile
and `--runtime claude-code`, with drift validation and matrix/doc parity — without ever clobbering
pre-existing user or graphify-authored Claude files.

## In Scope

- A Claude sub-agent renderer (`tools/ai/install/claude-agent-renderer.php`) modeled directly on
  `copilot-agent-renderer.php`.
- A `claude-agents` `install_type` wired into `core.php`'s single dispatch chain (mirrors
  `copilot-agents`/`opencode-agents` handling at L215-246; writer-count tracking mirrors L129-139
  only if/when an optional-agents-claude-pack is added — MVP ships single-writer only, see Non-Goals).
- A rendered `CLAUDE.template.md` (new `packages/ai-universal-rules/templates/core/CLAUDE.template.md`),
  replacing the current hand-maintained `CLAUDE.md` as the install target, preserving all current
  content (Read First, Working Style, Approval Boundaries, Failure Handling) plus a Claude
  sub-agent + handoff routing section.
- A narrowly-scoped JSON permission merge for `.claude/settings.json` (new function, e.g.
  `aiInstallerMergeClaudeSettingsJson()`) that unions `permissions.allow`/`deny` and never deletes
  pre-existing `hooks` entries.
- New `adapter-claude` pack in `packs.php`, new `claude` profile in `profiles.php`, `adapter-claude`
  added to `full-governance`/`agents-only`/`creator`/`full` per the same pattern as
  `adapter-copilot`/`adapter-opencode`.
- `aiInstallerResolveSelectedPacks()` `claude-code` runtime branch (defense-in-depth, per Chosen
  approach above).
- `config.php` `$allowedProfiles` + `--help` pipe-list additions; `core.php` `$strictProfiles`
  membership decision for `claude`; interactive wizard `$profileMap` addition (same consumer class
  the install-editions plan already enumerated — AC-2/AC-3/AC-6/AC-7 there).
- Claude skills/commands mapping: render `templates/workflows` → `.claude/skills` and
  `templates/commands` → Claude equivalents, mirroring the existing `skill-dirs`/`opencode-commands`
  mapping.
- Drift/doc updates: `validate-adapter-drift.php`, `integration-matrix.md`, `adapter-contract.md`
  (flip CLAUDE.md from hand-maintained to kit-managed, keep the graphify out-of-band note),
  `handoff-contract.md` (Claude handoff row), `ai-file-standards.md` (line budget for
  `.claude/agents/*.md`), `docs/ai/agents.md` (add Claude runtime key if the table format allows),
  shipped-surface inventory.
- A `ClaudeAgentRendererTest.php` PHPUnit test mirroring `tests/php/CopilotAgentRendererTest.php`.

## Out Of Scope (Things To Avoid)

- Do NOT hand-author 13 `.claude/agents/*.md` files as static duplicates — they must be *rendered*
  from the canonical source; hand-authoring recreates the exact adapter-drift this repo forbids.
- Do NOT implement a generic/risky JSON deep-merge utility — build only the narrow permission/hooks
  union needed for `.claude/settings.json`.
- Do NOT clobber the existing graphify `.claude/settings.json` `PreToolUse` hooks or the `## graphify`
  section in `CLAUDE.md` under any slice.
- Do NOT invent Claude frontmatter fields absent from the fetched Anthropic docs (no `handoffs:` —
  Claude has no structured handoff; the prose "Recommended next step" sentence stays mandatory).
- Do NOT claim per-command bash enforcement lives in Claude *frontmatter* — it is tool-level
  (`tools`/`disallowedTools`/`permissionMode`) plus a body policy section plus `.claude/settings.json`
  `permissions`; per-command nuance is advisory in the body, same honesty standard the matrix
  already applies to Copilot.
- Do NOT change existing OpenCode/Copilot rendered output as a side effect of any slice (golden-file
  parity check required before/after).
- Do NOT build the dual-writer "optional-agents-claude-pack" merge path in this program — defer it
  explicitly; MVP profile ships only the base 13-agent set through `adapter-claude`.
- Do NOT exceed one bounded slice per PR; each slice targets ≤6 files / ≤300-500 changed lines where
  feasible (the renderer slice is the one exception, scoped to a single new file + its test).

## Affected Paths

- `tools/ai/install/claude-agent-renderer.php` (new)
- `tools/ai/install/claude-agent-tool-registry.php` (new, mirrors `copilot-agent-tool-registry.php`)
- `tools/ai/install/core.php` (dispatch branch, `$strictProfiles`)
- `tools/ai/install/packs.php` (`adapter-claude` pack, edition/profile pack lists,
  `aiInstallerResolveSelectedPacks()` runtime branch)
- `tools/ai/install/profiles.php` (`claude` profile, edition updates)
- `tools/ai/install/config.php` (`$allowedProfiles`, `--help` text)
- `tools/ai/install/install_workflow.php` (interactive `$profileMap`, if present under this name —
  confirm exact file/function during implementation; the install-editions plan cites this path)
- `packages/ai-universal-rules/templates/core/CLAUDE.template.md` (new)
- `packages/ai-universal-rules/templates/claude/settings.json` (new)
- `tools/ai/validate-adapter-drift.php`
- `docs/ai/integration-matrix.md`, `docs/ai/adapter-contract.md`, `docs/ai/handoff-contract.md`,
  `docs/ai/ai-file-standards.md`, `docs/ai/agents.md`, `docs/ai/shipped-surface-inventory.md`
- `tests/php/ClaudeAgentRendererTest.php` (new)

## Contracts And Boundaries

- **Single source of truth**: `packages/ai-universal-rules/templates/core/agents/*.md` remains the
  only canonical agent body; the Claude renderer only transforms frontmatter + appends a body
  policy section, exactly as the Copilot renderer does.
- **`install_type` dispatch contract**: every new `install_type` value must be handled in
  `core.php`'s single elseif chain; `backup.php` only special-cases `install_type === 'skill-dirs'`
  (L447) — confirm whether `claude-agents` needs an equivalent backup-path branch before
  implementation, do not assume parity by default.
- **Fallback rule** (`integration-matrix.md` "Handoff Mechanism Per Runtime" / C-5): the prose
  "Recommended next step" sentence is mandatory on the rendered Claude agent body; no structured
  `handoffs:`-equivalent field exists for Claude.
- **Non-clobber contract**: any write to `.claude/settings.json` or `CLAUDE.md` must preserve
  pre-existing out-of-band content (graphify), matching the precedent already documented in
  `adapter-contract.md` for `AGENTS.md`'s `## graphify` section.
- **Source-of-truth doc pairing**: `adapter-contract.md` and `integration-matrix.md` must move in
  the same slice as any behavior change, per the Maintenance/Thinning Rule already in
  `integration-matrix.md`.

## Todo Plan

### P0 — Critical path (first shippable increment) — **COMPLETE**

- [x] P0-1: Confirmed. `install_workflow.php` L421-423 has the interactive `$profileMap` array
      (P1 scope, not touched yet). `backup.php` L447 only special-cases `install_type ===
      'skill-dirs'` (rename mapping) and `rename_ext`; Claude agent files are identity-named
      (`<id>.md`, no rename), so the existing generic branch already handles them correctly —
      confirmed **no `backup.php` change needed for P0**.
- [x] P0-2: Wrote `tools/ai/install/claude-agent-tool-registry.php` — derived the mapping from each
      canonical agent's `permission.edit`/`permission.task` values (7 read-only agents with
      `edit: deny` → `plan` mode; 6 write-capable agents → `default` mode; `Agent` tool granted only
      to `architect`/`post-install`, the two with canonical `task: allow` — every other agent
      (including `task: ask`) conservatively omits `Agent` since Claude subagents cannot
      interactively ask for spawn approval).
- [x] P0-3: Wrote `tools/ai/install/claude-agent-renderer.php` — reused the exact frontmatter-parse
      approach from `copilot-agent-renderer.php`, emits `name`/`description`/`tools`/
      `disallowedTools`/`model: inherit`/`permissionMode`/carried-through `agent_assessment`, appends
      a "Bash Command Policy" body section (mirrors the Copilot "Shell Boundary" section) noting
      `.claude/settings.json` is the enforced surface when it disagrees with the body text. Reuses
      `aiInstallerInsertGeneratedHeaderAfterFrontmatter` and `aiCopilotExtractAssessmentBlock`.
- [x] P0-4: Added `aiInstallerCopyDirAsClaudeAgents()` in the same file (single-writer, identity
      filenames, no merge-variant, per Non-Goals).
- [x] P0-5: Wired `claude-agents` into `core.php`'s dispatch chain (new `elseif` alongside the
      `opencode-agents`/`skill-dirs` branches) and added the `require_once` for the new renderer
      file. Confirmed via grep that `verify-no-overwrite.php`/`manifest.php` only reference
      `merge_strategy`, never `install_type` — no changes needed there.
- [x] P0-6: Wrote `tests/php/ClaudeAgentRendererTest.php` (21 tests) mirroring
      `CopilotAgentRendererTest.php`'s structure — covers architect (read-only + `Agent` tool +
      `plan` mode + no invented `handoffs:` field), implementer (write tools + `default` mode),
      researcher (no `Agent` tool), the copy-refresh non-destructive guarantee, tool-registry
      coverage of all 13 templates, and `agent_assessment` round-tripping.
- [x] P0-7: Added `adapter-claude` pack to `packs.php` (dir copy, `install_type: claude-agents`,
      scoped comment noting `CLAUDE.template.md`/settings-merge are explicitly P1).

**P0 verification (all run, evidence below):**
- `vendor/bin/phpunit --filter ClaudeAgentRendererTest` → 21 tests, 66 assertions, 1 skipped (no
  `.claude/agents` installed yet in this repo — expected), 0 failures.
- `vendor/bin/phpunit --filter CopilotAgentRendererTest` → 21 tests, 247 assertions, 0 failures
  (no regression to the existing renderer).
- `vendor/bin/phpunit tests/php/InstallerSafetyTest.php` → 67 tests, 1182 assertions, 0 failures
  (broad real-install regression suite, unaffected by the new dispatch branch/pack).
- `composer test:fast` (749 tests) → 12 pre-existing failures before this slice; after fixing the
  2 this slice caused (see below), 10 remain — all pre-existing and unrelated (graphify skill
  line-count, `script-runner` agent-manifest gap, `.claude`/`bin`/`graphify-out`/etc. missing
  repo-structure metadata, `ai-search.sh` introspection golden-snapshot drift for the `head`
  command) — confirmed via `git diff --stat` that none of those touch files this slice modified.
- **Side effect fixed in-slice**: adding `adapter-claude` to `packs.php` made
  `CatalogDriftValidatorTest` (2 tests) fail because the generated install catalog was stale. Ran
  the sanctioned generator (`php tools/ai/ai.php install-docs --write`); confirmed via `git diff`
  the regenerated `packages/ai-universal-rules/docs/INSTALL-CATALOG.md` gained exactly one new
  line (`- \`adapter-claude\` (1 items)`); re-ran the test → 3/3 pass.
- Dry-run proof: `php tools/ai/ai.php install --profile minimal --with adapter-claude --dry-run`
  resolved `"packs": ["base", "setup-docs", "capabilities-core", "adapter-claude"]` in
  `docs/ai/generated/install.json`, confirming the pack is registered and selectable.

### P1 — Profile/edition wiring and CLAUDE.md/settings rendering

- [ ] P1-1: Add `packages/ai-universal-rules/templates/core/CLAUDE.template.md` preserving current
      `CLAUDE.md` content verbatim plus a new "Claude Sub-Agents" section pointing to
      `.claude/agents/`; register it in `packs.php` under `adapter-claude` with
      `merge_strategy: replace` (same class as `AGENTS.template.md`).
- [ ] P1-2: Add `packages/ai-universal-rules/templates/claude/settings.json` with
      `permissions.allow`/`permissions.deny` derived from the canonical read-only script allowlist
      and secrets/destructive-op denials from `docs/ai/approval-boundaries.md`.
- [ ] P1-3: Implement the narrow `aiInstallerMergeClaudeSettingsJson()` merge function (JSON decode
      both sides, union `permissions.allow`/`permissions.deny` arrays de-duplicated, concatenate
      `hooks.PreToolUse`/other hook arrays rather than replacing, re-encode with
      `JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES` matching existing JSON-write style elsewhere in
      `core.php`/`manifest.php`). Wire it as the write path for the `.claude/settings.json` pack
      entry (new `install_type` value, e.g. `claude-settings-merge`, dispatched in `core.php`).
- [ ] P1-4: Add `claude` profile to `profiles.php`: `['minimal', 'adapter-claude', 'scripts-pack',
      'policy-pack', 'hooks-pack']`, and add `adapter-claude` directly into `full-governance`,
      `agents-only`, `creator`, `full` edition definitions (Chosen approach (b), not the runtime
      re-add branch alone).
- [ ] P1-5: Add the `claude-code` branch to `aiInstallerResolveSelectedPacks()` (defense-in-depth,
      strips `adapter-copilot`/`adapter-opencode` when `--runtime claude-code` is passed, mirrors
      the two existing branches at packs.php ~L438-448).
- [ ] P1-6: Update `config.php` `$allowedProfiles` + `--help` pipe-list; update `core.php`
      `$strictProfiles` membership decision for `claude` (document the choice, do not leave
      implicit); update the interactive wizard's `$profileMap` (confirmed location from P0-1).
- [ ] P1-7: Add `claude-agents` to `aiInstallerAllFeaturePacks()` equivalent list if a parallel
      concept exists for pack names (confirm against `profiles.php` `aiInstallerAllFeaturePacks()`
      — add `adapter-claude` there).

### P2 — Skills/commands mapping, drift, docs

- [ ] P2-1: Render `templates/workflows` → `.claude/skills` (reuse `skill-dirs` install_type if the
      output shape matches; add a Claude-specific frontmatter transform only if Claude's skill
      frontmatter contract differs materially — confirm via a second `docs.anthropic.com` fetch on
      the Skills page before assuming parity).
- [ ] P2-2: Render `templates/commands` → a Claude command surface (`.claude/commands/` or
      documented equivalent) if Claude Code supports a directly analogous concept; otherwise mark
      `unknown`/deferred with an explicit matrix note rather than inventing a mapping.
- [ ] P2-3: Update `tools/ai/validate-adapter-drift.php` to include `.claude/agents/**` and
      `CLAUDE.md` template parity checks (currently only referenced at L13 as a raw path, not
      diffed against a template source).
- [ ] P2-4: Update `docs/ai/integration-matrix.md`: flip the Claude "Runtime limitation notes" and
      "Critical-Topic Coverage Matrix" cells that currently say "advisory only" / "ships no Claude
      sub-agents folder" to reflect the new shipped reality; add a dated methodology note per the
      existing Phase 3.1/3.2 convention; keep honest any residual gap (e.g., `AskUserQuestion`
      unavailable inside Claude subagents).
- [ ] P2-5: Update `docs/ai/adapter-contract.md`: remove the "CLAUDE.md is hand-maintained, not in
      this kit's pack registry" claim (now false); keep the graphify out-of-band-addition guidance
      (still true — graphify still appends independently of this kit's render).
- [ ] P2-6: Update `docs/ai/handoff-contract.md` "Review Handoff" section / add a Claude row
      alongside the existing Copilot/OpenCode handoff-mechanism table entries in the matrix.
- [ ] P2-7: Update `docs/ai/ai-file-standards.md` line-budget table with a `.claude/agents/*.md`
      row (model on the `.opencode/agents/*.md` row).
- [ ] P2-8: Update `docs/ai/shipped-surface-inventory.md` with the new template subtree
      classification (`always-on-critical` for `CLAUDE.template.md`, `deterministic-load` for
      `.claude/agents/**`).

## Acceptance Criteria

- [ ] AC-1: `php tools/ai/ai.php install claude --dry-run` completes without error and plans exactly
      the `adapter-claude` pack's targets (no unrelated pack drift). **Blocked on P1-4** (`claude`
      profile does not exist yet); interim evidence: `--with adapter-claude` resolves the pack
      correctly today (see P0 verification notes above).
- [x] AC-2: `php tools/ai/ai.php install dual --dry-run` and existing `copilot`/`opencode` profile
      dry-runs produce byte-identical plans to pre-change baseline (no regression). Verified via
      `CopilotAgentRendererTest` (21/21 unchanged) and `InstallerSafetyTest` (67/67, includes a real
      `opencode` profile install into an isolated temp target).
- [ ] AC-3: A real (non-dry-run, isolated temp target) install of the `claude` profile produces
      `.claude/agents/architect.md` with valid frontmatter containing only fields present in the
      fetched Anthropic sub-agent schema (`name`, `description`, `tools`, `disallowedTools`, `model`,
      `permissionMode`, no invented fields like `handoffs:`). **Partially proven at unit level**:
      `ClaudeAgentRendererTest::testClaudeAgentCopyRefreshesWithoutDeletingDestinationTree` performs
      a real filesystem write via `aiInstallerCopyDirAsClaudeAgents()` directly and every frontmatter
      field is asserted by the renderer tests; the literal full-CLI-install path is P1 scope (needs
      the `claude` profile to exist). Recommend adding an `adapter-claude` scenario to
      `InstallerSafetyTest.php` in P1 to close this AC formally.
- [ ] AC-4: Running the same install against a target that already has a graphify-authored
      `.claude/settings.json` (with `PreToolUse` hooks) results in a merged file that still contains
      the original `PreToolUse` hooks entries verbatim, plus the new kit-managed
      `permissions.allow`/`deny` rules.
- [ ] AC-5: Running the same install against a target that already has a `## graphify` section in
      `CLAUDE.md` preserves that section after the `CLAUDE.template.md` render (matching the
      documented `AGENTS.md` re-apply precedent).
- [ ] AC-6: `php tools/ai/validate-adapter-drift.php --fail-on-warn` passes with the new Claude
      surfaces included in its checks.
- [ ] AC-7: `tests/php/ClaudeAgentRendererTest.php` passes and covers at least: architect (read-only
      → `plan` mode, no Edit/Write tools), implementer (`default` mode, Edit/Write present), and one
      agent with `agent_assessment` carried through unchanged.
- [ ] AC-8: `docs/ai/integration-matrix.md`'s Claude column no longer states "ships no Claude
      sub-agents folder" as a gap; the row's `Status` reflects `covered` where true, with any
      remaining gap named explicitly (not silently dropped).
- [ ] AC-9: `--runtime claude-code` on any profile that includes `adapter-claude` does not also
      install `.github/**` or `.opencode/**` adapter content (confirms the resolveSelectedPacks
      branch works even though profile-baked adapters are the primary mechanism).

## Verification Plan

- `php tools/ai/ai.php install claude --dry-run` / `... dual --dry-run` / `... copilot --dry-run` /
  `... opencode --dry-run` (regression + new-profile smoke).
- `php tools/ai/validate-adapter-drift.php --fail-on-warn`
- `php tools/ai/validate-ai-config.php` (confirms rendered `CLAUDE.md` still satisfies existing
  reference checks at `validate-ai-config.php` L502-514).
- `composer test:fast` (full suite), then focused: `vendor/bin/phpunit --filter ClaudeAgentRendererTest`
  and `--filter CopilotAgentRendererTest` (regression proof the existing renderer is untouched).
- Manual diff: rendered `.opencode/agents/*` and `.github/agents/*` output before/after this
  program must be byte-identical (negative test — no cross-runtime regression).
- Manual isolated-temp-dir install test simulating a pre-existing graphify `.claude/settings.json`
  and `## graphify` `CLAUDE.md` section, confirming AC-4/AC-5 non-clobber behavior.

## Risks And Rollback

- **Medium-high**: `.claude/settings.json` merge is new code with no prior art in this installer;
  bugs risk silently dropping graphify's hooks. Mitigation: AC-4 is a mandatory non-dry-run test
  before this ships in any profile; rollback = do not select the `claude`/any Claude-adapter profile
  (purely additive pack, no default profile changes).
- **Medium**: `aiInstallerResolveSelectedPacks()` runtime-branch omission would silently leave
  `--runtime claude-code` non-functional (same class of bug the install-editions plan already
  flagged for two other runtimes). Mitigation: profile-baked adapters (Chosen approach (b)) make
  this defense-in-depth only, not load-bearing, so a missed branch degrades gracefully rather than
  breaking installs.
- **Medium**: Claude Code CLI version skew — some frontmatter fields (`permissionMode: manual`
  alias, `effort`, `isolation`) are version-gated per the fetched docs. Mitigation: render only the
  documented-stable field set (`name`, `description`, `tools`, `disallowedTools`, `model`,
  `permissionMode`); treat newer fields as future, optional additions.
- **Low-medium**: skills/commands mapping (P2-1/P2-2) has an `unknown` — Claude's skill frontmatter
  contract was not independently re-fetched in this research pass. Mitigation: P2-1 requires a
  second doc fetch before implementation; if the contract diverges materially, that becomes its own
  bounded follow-up slice rather than blocking P0/P1.
- **Rollback for the whole program**: every artifact is additive (new pack, new profile, new files);
  reverting means removing `adapter-claude` from `profiles.php`/`packs.php` and deleting the new
  renderer/template files — no existing Copilot/OpenCode/base behavior is modified in P0/P1.

## Handoff Notes

Implement in slice order P0 → P1 → P2. P0 is the smallest shippable increment (renderer + dispatch
+ pack, no profile/settings-merge yet) and can land as its own reviewed PR. P1 introduces the new
JSON-merge primitive and profile/runtime wiring — highest-risk slice, needs its own focused review
pass given AC-4's non-clobber requirement. P2 is docs/drift parity and can trail behind P0/P1 by a
PR or two without blocking adoption. Update `docs/ai/integration-matrix.md` in the same PR as any
slice that changes a matrix-tracked topic, per that doc's own Maintenance Rule.

Recommended next step: `implementer means implementer agent handoff` for P0-1 through P0-7 first;
route to `reviewer` after P0 lands (touches install/catalog surface); route to `release-auditor`
before P1 ships (new merge primitive, profile/runtime surface change is medium-high risk per the
repo's own risk classification rule).
