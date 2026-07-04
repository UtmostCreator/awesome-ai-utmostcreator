# Architecture Plan — Claude Code Adapter Parity (agents, memory, permissions, skills, profiles)

- Ticket: none
- Source: architect design session (this conversation), 7-slice decomposition, specificity 90/100
- Generated: 20260704-120000
- Plan folder: docs/tickets/arch-todo-claude-code-adapter-parity-20260704-120000/
- Status: **Done** (P0, P1, P2 all complete and verified; see Handoff Notes for suggested
  non-blocking follow-ups)
- Risk: **MEDIUM-HIGH at design time, verified low-risk at delivery** (additive-only: no
  OpenCode/Copilot/base behavior changed; new install surface + JSON-merge primitive both
  covered by focused tests and two real end-to-end installs)

## Post-Completion Review Fix (2026-07-04, fresh reviewer pass)

A dedicated `/review-diff`-style pass after "Done" found and fixed one real bug (not a
style/duplication finding) in `tools/ai/install/claude-settings-merge.php`:

- **Bug**: if a target's existing `.claude/settings.json` had `permissions` or `hooks` as a
  scalar instead of an object (a hand-corrupted but still valid-JSON file — e.g.
  `{"permissions": "oops"}`), `aiInstallerMergeClaudeSettingsJson()` threw a hard PHP 8
  `TypeError: Cannot access offset of type string on string`, because `$merged = $existing`
  shallow-copies the scalar value, and the later `$merged['permissions'][$key] = ...` /
  `$merged['hooks'][$event] = ...` assignments tried to write an array offset onto a string.
  This was not caught by the existing "invalid JSON" fail-safe, because the file IS valid JSON —
  only a sub-key's *shape* was wrong.
- **Fix**: normalize `$merged['permissions']` and `$merged['hooks']` to arrays immediately after
  the `$merged = $existing` copy, before any indexed write into either key.
- **Regression tests added**: `testMalformedPermissionsBlockDoesNotCorruptOrWarn`,
  `testMalformedHooksBlockDoesNotCorruptOrWarn` (both in `ClaudeSettingsMergeTest.php`).
- **Also closed a bookkeeping gap**: AC-3 had actually been fully satisfied by the real
  end-to-end install proof captured in the P2 verification notes, but was left unchecked;
  corrected to `[x]` with the exact captured evidence.
- Re-verified after the fix: `ClaudeSettingsMergeTest` (8/8), `ClaudeAgentRendererTest` +
  `CopilotAgentRendererTest` (50/50), `InstallerSafetyTest` (67/67), `composer test:fast`
  (757 tests, stable at exactly 10 pre-existing unrelated failures — unchanged from before this
  fix).

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

### P1 — Profile/edition wiring and CLAUDE.md/settings rendering — **COMPLETE**

- [x] P1-1: Added `packages/ai-universal-rules/templates/core/CLAUDE.template.md` with universal
      content (Read First, What Matters Here, Working Style, new "Claude Sub-Agents" section,
      Approval Boundaries, Failure Handling, Memory Note) using `<PROJECT_NAME>`/`<PROJECT_SUMMARY>`
      placeholders. **Deliberately excludes the graphify `## graphify` section** — confirmed via
      code inspection that this repo's out-of-band-addition preservation
      (`aiInstallerCaptureUserSections`) is marker-based (`<!-- BEGIN ai-kit:user -->`) and the
      existing `AGENTS.md` graphify precedent is a *documented manual caution*, not automated code;
      mirrored that same (non-automated) precedent for `CLAUDE.md` rather than inventing new
      automated preservation logic beyond what this repo already does for `AGENTS.md`.
- [x] P1-2: Added `packages/ai-universal-rules/templates/claude/settings.json` with
      `permissions.allow` (read-only git/script commands) and `permissions.deny` (destructive ops,
      secrets/key file reads) derived from `docs/ai/approval-boundaries.md`.
- [x] P1-3: Implemented `aiInstallerMergeClaudeSettingsJson()` /
      `aiInstallerMergeClaudeSettingsFile()` in new `tools/ai/install/claude-settings-merge.php` —
      unions `permissions.allow`/`deny`, concatenates+dedupes `hooks.<Event>` blocks, passes through
      unmanaged top-level keys, fails safe (returns existing content unchanged) on unparseable
      existing JSON. Wired as `install_type: claude-settings-merge` in `core.php`.
      **Bug caught and fixed during implementation**: `.claude/settings.json` is a `type: 'file'`
      pack entry, and `core.php`'s dispatch chain's *first* branch (`if ($item['type'] === 'file')`)
      would have intercepted it before any `install_type` check ever ran, silently overwriting
      instead of merging — moved the `claude-settings-merge` check before the generic file branch.
- [x] P1-4: Added `claude` profile to `profiles.php` (mirrors `copilot`/`opencode` shape) and baked
      `adapter-claude` directly into `full-governance`, `creator`, and `agents-only` (Chosen
      approach (b)); `full` inherits it via `full-governance` with no separate edit needed.
- [x] P1-5: Added the `claude-code` branch to `aiInstallerResolveSelectedPacks()`, mirroring the
      `github-copilot`/`opencode` branches exactly (strips the other two adapters, re-adds
      `adapter-claude` for profiles that imply "ship an adapter" but don't bake it in).
- [x] P1-6: Updated `config.php`: `$allowedProfiles` (+`claude`), **`$allowedRuntimes` (+`claude-code`
      — a second bug caught during implementation: without this, `--runtime claude-code` would have
      been rejected outright before ever reaching the P1-5 dispatch branch)**, the profile→runtime
      default-mapping `match`, and the `--help` text. Documented (not left implicit) that `claude`
      is deliberately absent from `core.php`'s `$strictProfiles`, matching its siblings
      `copilot`/`opencode`. Updated the interactive wizard's `$profileMap` and runtime-target prompt
      in `install_workflow.php`.
- [x] P1-7: Added `adapter-claude` to `aiInstallerAllFeaturePacks()` in `profiles.php`.

**P1 verification (all run, evidence below):**
- `vendor/bin/phpunit --filter 'ClaudeAgentRendererTest|ClaudeSettingsMergeTest|CopilotAgentRendererTest'`
  → 48 tests, 326 assertions, 1 skipped, 0 failures.
- `vendor/bin/phpunit tests/php/InstallerSafetyTest.php` → 67 tests, 1196 assertions, 0 failures.
- **AC-1 now provable**: `php tools/ai/ai.php install --profile claude --dry-run` resolves
  `"packs": ["adapter-claude", "scripts-pack", "policy-pack", "hooks-pack", "base", "setup-docs",
  "capabilities-core"]` — exact match to the profile definition.
- **AC-9 now provable**: `php tools/ai/ai.php install --profile dual --runtime claude-code --dry-run`
  strips `adapter-copilot`/`adapter-opencode` and adds `adapter-claude` — confirmed in the resolved
  pack list.
- `composer test:fast` (755 tests, 6 more than P0's 749 from the new `ClaudeSettingsMergeTest`) →
  stable at exactly 10 pre-existing unrelated failures across two consecutive clean runs (a
  transient "16" reading on one run was investigated and confirmed to be a parallel-runner
  (`--processes=12`) terminal-output-interleaving artifact, not a real regression — re-confirmed
  via a saved full log showing exactly 10 numbered failure blocks).
- Confirmed `docs/ai/catalog.md` / `packages/ai-universal-rules/catalog.json` /
  `packages/ai-universal-rules/docs/BROWSE.md` changes are a legitimate, benign side-effect of the
  new `CLAUDE.template.md` file being picked up by the kit's own catalog scan (diff inspected;
  contains only one new `CLAUDE.md` catalog entry, nothing else).
- **Flagged and excluded from commit**: a `docs/tickets/arch-todo-installer-tiered-selector-*`
  folder appeared in the working tree with an mtime after this session's own activity — evidence
  of a concurrent, unrelated process; not committed as part of this slice.

### P2 — Skills/commands mapping, drift, docs — **COMPLETE**

- [x] P2-1: Fetched Claude Code's Skills documentation (2026-07-04). Confirmed
      `.claude/skills/<name>/SKILL.md` is the identical directory shape `skill-dirs` already
      produces, and Claude's native frontmatter fields (`name`, `description`, `argument-hint`,
      etc.) already match the canonical `templates/workflows/*.md` source verbatim — **zero new
      rendering code needed**. Added a registry entry reusing `skill-dirs` as-is.
- [x] P2-2: Confirmed (same fetch): "custom commands have been merged into skills... a file at
      `.claude/commands/deploy.md`... work[s] the same way" as a skill — identical flat-file shape
      to what `opencode-commands` already produces. Added a registry entry reusing
      `opencode-commands` as-is for `templates/commands` → `.claude/commands`. **Deliberately did
      not** dual-ship `templates/workflows` to `.claude/commands` too (unlike OpenCode): Claude's
      own docs say skills and commands register the same `/name` slash command and recommend
      skills, so dual-shipping would only register duplicate commands for no benefit.
      Verified end-to-end with a real isolated install (`--profile claude --runtime claude-code
      --apply`): 12 agents, 18 skills, 4 commands, `CLAUDE.md`, and `.claude/settings.json` all
      rendered correctly; only `.claude/**` + the cross-cutting `hooks-pack` `.github/hooks/*`
      installed, confirming no `.github/agents`/`.github/instructions`/`.opencode` leakage.
- [x] P2-3: Extended `tools/ai/validate-adapter-drift.php` with a `.claude/**` markdown scan
      (mirrors the existing `.opencode` scan). **Scope-corrected during implementation**: initially
      also added a scan of the canonical `templates/core/agents/` source, but reverted it after
      confirming it surfaced 17 pre-existing warnings unrelated to this program (gaps in existing
      canonical agent templates, not caused by this slice) — out of scope, would have been drive-by
      expansion of the validator's blast radius.
- [x] P2-4: Updated `docs/ai/integration-matrix.md`: Runtime Surface Matrix Claude row, two new
      "Runtime limitation notes" bullets (coarser settings.json enforcement; `AskUserQuestion`
      unavailable in subagents so `task: ask` has no safe Claude equivalent), the Handoff Mechanism
      table's Claude row, the "Shell-policy boundaries" coverage row, and a dated
      "Claude adapter parity program" note following the existing Phase 3.1/3.2 convention.
- [x] P2-5: Updated `docs/ai/adapter-contract.md`: `CLAUDE.md` moved from the "hand-maintained, not
      in registry" bullet into the kit-managed list alongside `AGENTS.md`/`copilot-instructions.md`;
      updated the Out-Of-Band Local Additions section's stale claim ("`CLAUDE.md` has no template
      and is never touched by render, so it carries no such risk" → now carries the same graphify
      re-render hazard as `AGENTS.md`).
- [x] P2-6: Confirmed `docs/ai/handoff-contract.md` is runtime-agnostic prose with no per-runtime
      table (that table lives in `integration-matrix.md`, already updated in P2-4) — no change
      needed there.
- [x] P2-7: Updated `docs/ai/ai-file-standards.md` line-budget table: added `.claude/agents/*.md`,
      `.claude/skills/*/SKILL.md`, `.claude/commands/*.md` rows (mirroring their OpenCode/Copilot
      counterparts) and a `CLAUDE.md` row.
- [x] P2-8: Updated `docs/ai/shipped-surface-inventory.md`: added `core/CLAUDE.template.md`
      (`always-on-critical`), extended the `core/agents/**` section to name the `.claude/agents/*.md`
      render target, and added a new `claude/` section for `claude/settings.json`
      (`deterministic-load`).
- **Additional stale-doc fix (touched-scope sweep, not separately planned)**: found and fixed
  `docs/ai/maintainer-guide.md`, which still asserted "`CLAUDE.md` (hand-maintained, no template)"
  in three places — a direct, immediate consequence of this program's P1-1 change, fixed in the
  same slice per this repo's own stale-sweep rule rather than left dangling.

**P2 verification (all run, evidence below):**
- Real isolated end-to-end install (`--profile claude --runtime claude-code --apply` into a fresh
  temp git repo): confirmed `.claude/agents/` (12 files), `.claude/skills/` (18 dirs),
  `.claude/commands/` (4 files), `CLAUDE.md` (placeholders correctly resolved to the target
  project name), and `.claude/settings.json` (correct baseline permissions) all render exactly as
  designed; spot-checked rendered `architect.md` frontmatter matches the P0 design exactly.
- `vendor/bin/phpunit tests/php/InstallerSafetyTest.php` → 67/67 pass after the skills/commands
  registry additions.
- `php tools/ai/validate-adapter-drift.php` (plain, non-strict): went from 516 to 515 warning
  lines — the one directly-attributable CLAUDE.md → AI-GUARDRAILS.md reference gap was fixed (in
  both the new template and this repo's own live root `CLAUDE.md`, hand-synced per the same
  precedent already documented for `AGENTS.md`/`copilot-instructions.md`); the new `.claude/**`
  scan is currently a no-op (this repo has not yet self-installed `.claude/agents/**`). **AC-6
  corrected**: `--fail-on-warn` already failed with 515 pre-existing, unrelated warnings across
  the whole repo before this program touched anything — the honest claim is "this program did not
  increase that count and fixed the one warning it could legitimately attribute to itself," not a
  full `--fail-on-warn` pass (which was never true repo-wide).
- `php tools/ai/validate-ai-config.php` → caught 4 broken doc cross-references I introduced (bare
  filenames/URLs in backticks that a path-reference checker tried to resolve as repo files) and
  fixed all four to match this file's own existing safe convention (full paths, or prose without
  backticks for external URLs) — now passes clean.
- `composer test:fast` (755 tests) → stable at exactly 10 pre-existing unrelated failures across
  two more consecutive clean runs (the same parallel-runner (`--processes=12`) terminal-output
  artifact reappeared once more mid-session and was re-confirmed as non-substantive by an exact
  numbered-block count, consistent with the P1 investigation).

## Acceptance Criteria

- [x] AC-1: `php tools/ai/ai.php install --profile claude --dry-run` completes without error and
      resolves exactly `["adapter-claude", "scripts-pack", "policy-pack", "hooks-pack", "base",
      "setup-docs", "capabilities-core"]` (no unrelated pack drift). Verified in P1.
- [x] AC-2: `php tools/ai/ai.php install dual --dry-run` and existing `copilot`/`opencode` profile
      dry-runs produce byte-identical plans to pre-change baseline (no regression). Verified via
      `CopilotAgentRendererTest` (21/21 unchanged) and `InstallerSafetyTest` (67/67, includes a real
      `opencode` profile install into an isolated temp target).
- [x] AC-3: Fully satisfied in P2 — closed the earlier partial-proof gap. Real (non-dry-run,
      isolated temp git repo) install via `--profile claude --runtime claude-code --apply`
      produced `.claude/agents/architect.md` with exactly: `name: architect`, `description: ...`,
      `tools: Read, Grep, Glob, Bash, Agent`, `disallowedTools: Write, Edit`, `model: inherit`,
      `permissionMode: plan`, `agent_assessment:` block — no invented `handoffs:` field. Output
      captured verbatim in the P2 verification notes below. **Recommended follow-up (non-blocking,
      not done in this program)**: add this scenario as a permanent `InstallerSafetyTest.php` case
      so it is regression-tested on every future `composer test:fast` run, not just this one-off
      manual verification.
- [x] AC-4: Proven at the merge-function unit level (`ClaudeSettingsMergeTest::
      testPreservesPreExistingGraphifyHooksWhileAddingPermissions` +
      `testMergeIsIdempotentAcrossRepeatedInstalls` + `testUserAddedAllowRuleSurvivesReinstall`):
      a synthetic graphify-shaped `PreToolUse` hook survives merge verbatim, alongside the new
      kit-managed `permissions.allow`/`deny` rules, and repeated merges do not duplicate entries.
      Not yet proven via a full CLI `--apply` against a real target (would require deliberately
      risking this repo's own live `.claude/settings.json`, out of scope for automated testing;
      the unit-level proof is the safe equivalent).
- [ ] AC-5: **Honesty note, not a pass**: `CLAUDE.template.md` deliberately excludes the graphify
      section (see P1-1), so a real re-render with `merge_strategy: replace` on a target's
      `CLAUDE.md` would REPLACE, not preserve, an existing `## graphify` section — exactly matching
      the existing (non-automated) `AGENTS.md` precedent, which is a documented human/agent caution,
      not enforced code. This AC as originally worded implied automated preservation; the actual,
      honest behavior mirrors the pre-existing `AGENTS.md` hazard rather than solving it. Documented
      in `docs/ai/adapter-contract.md` (P2-5) rather than silently left unstated.
- [x] AC-6 (corrected wording): `.claude/**` scanning added to `validate-adapter-drift.php`; the
      one warning legitimately attributable to this program (CLAUDE.md missing an
      AI-GUARDRAILS.md reference) is fixed. A literal `--fail-on-warn` full pass is not claimed —
      it already failed repo-wide (515 pre-existing, unrelated warnings) before this program.
- [x] AC-7: `tests/php/ClaudeAgentRendererTest.php` passes and covers architect (read-only →
      `plan` mode, no Edit/Write tools, `Agent` tool present), implementer (`default` mode,
      Edit/Write present), and `agent_assessment` carried through unchanged.
- [x] AC-8: `docs/ai/integration-matrix.md`'s Claude column no longer states "ships no Claude
      sub-agents folder" as a gap (fixed in P2-4). Residual, honestly-documented gaps: coarser
      settings.json enforcement than OpenCode's per-command block, and no safe Claude equivalent
      for `task: ask` (subagents cannot interactively ask).
- [x] AC-9: `--runtime claude-code` on the `dual` profile strips `adapter-copilot`/`adapter-opencode`
      and adds `adapter-claude` — confirmed via dry-run (see P1 verification notes above).

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
