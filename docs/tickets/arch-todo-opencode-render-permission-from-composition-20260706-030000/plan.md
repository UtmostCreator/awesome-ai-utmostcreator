# Architecture Plan — Render OpenCode agent `permission:` block from composition at install time

- Ticket: arch-todo-opencode-render-permission-from-composition-20260706-030000
- Source: architect design handoff (user-approved FULL 5-slice change; doc-note alternative rejected)
- Generated: 2026-07-06 03:00:00
- Plan file: docs/tickets/arch-todo-opencode-render-permission-from-composition-20260706-030000/plan.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan.md` and move it into `archive/` under this ticket folder (`docs/tickets/arch-todo-opencode-render-permission-from-composition-20260706-030000/archive/DONE-plan.md`). See "Archive On Completion" below for the exact steps.

## Context

The OpenCode agent install path verbatim-copies template files from
`packages/ai-universal-rules/templates/{core,optional}/agents/*.md`, relying on a
pre-baked OpenCode `permission:` block committed inside each template. Copilot and
Claude installers instead re-render their permission projection from
`tools/ai/install/permission-layers/compositions.php` via
`aiPermissionResolveAllowedBash()`. This asymmetry means OpenCode is the only
consumer whose permission frontmatter is not derived from the single composition
source of truth.

Verified ground truth (established by prior investigation; trusted for this plan):

- Composition SOURCE: `tools/ai/install/permission-layers/compositions.php` →
  `aiPermissionAgentCompositions()` (26 agents; all 15 shipped core agents composed).
- OpenCode block renderer: `tools/ai/install/permission-layers/render-adapters.php:53`
  `aiPermissionRenderOpenCodeBlock($model, $render)`.
- Render specs live in `compositions.php` under each agent's `render` key:
  `{quote, extra_scalars, extra_scalars_before_edit?, external_directory?}`.
- Generator `tools/ai/generate-agent-permissions.php` splices baked blocks into 4
  dirs (`$dirs`, lines 41-51): templates/core, .opencode/agents, templates/optional,
  .opencode/agents-optional. `aiPermissionSpliceBlock()` REQUIRES an existing
  `permission:` key (errors "no top-level 'permission:' key found") and preserves a
  trailing `agent_assessment:` block.
- Install dispatch in `tools/ai/install/core.php:234-255`:
  `opencode-agents`→verbatim copy; `copilot-agents`/`claude-agents`→re-render from
  composition.
- `packs.php:136/144/156` copy the SAME source dir into `.github/agents`,
  `.opencode/agents`, `.claude/agents`.
- Copilot/Claude legacy fallback parses template `bash:` block
  (`canonical-agent-frontmatter.php:32-41`); for composed agents the composition
  always wins (`render-adapters.php:152-161`), so the legacy path is dead code for
  all shipped agents.
- THIS repo dogfoods `.opencode/agents/*.md` at runtime; OpenCode reads them as-is
  with no render hook, so they MUST retain a full baked block on disk.

## Problem

`aiInstallerCopyDirAsOpenCodeAgents()` in `tools/ai/install/copilot-agent-renderer.php:277`
verbatim-copies template agent files and depends on a pre-baked OpenCode
`permission:` block committed inside each template source file. OpenCode is not a
consumer of the composition permission source; the template files carry duplicated,
manually-synced permission state instead of pure "body + non-permission frontmatter"
source.

## Target Outcome

Make OpenCode the fourth consumer that renders its `permission:` block from the
composition at install time. Template source files stop carrying a baked OpenCode
`permission:` block and become pure "body + non-permission frontmatter" source, while
the committed/dogfooded `.opencode/agents{,-optional}/*.md` files keep a full baked
block on disk (still produced by the generator, scoped to `.opencode` dirs only).

## In Scope

- New `aiPermissionInsertBlock()` sibling that INSERTS a `permission:` block when no
  `permission:` key exists (before `agent_assessment:` if present, else before the
  closing `---`).
- Render step wired into `aiInstallerCopyDirAsOpenCodeAgents()`: for composed
  filename-stems, compose the model and render via
  `aiPermissionRenderOpenCodeBlock($model, $composition['render'])`, then insert the
  block; verbatim copy for hidden/uncomposed agents. Key by filename stem, never
  frontmatter `id`.
- Scope generator `$dirs` in `tools/ai/generate-agent-permissions.php` to `.opencode`
  dirs only (remove the two template dirs).
- Strip the `permission:` key entirely from all 13 core + any composed optional
  template files (preserve `agent_assessment:` and other frontmatter).
- Repoint tests: `AgentPermissionPolicyTest.php` and `AgentPermissionDriftTest.php`
  from template-file permission assertions to `.opencode/agents/*.md` or
  compose-model assertions; add a new guard test that every shipped template agent
  stem has a composition entry.
- Byte-identical fresh-install fixture proof (before/after `.opencode/agents/*.md`
  permission blocks).
- Update `docs/ai/adapter-contract.md` "Permission Projection Seam" prose.

## Out Of Scope (Things To Avoid)

- Do NOT change renderer output bytes: `aiPermissionRenderOpenCodeBlock` and
  `aiPermissionAllowedBashFromModel` stay untouched.
- Do NOT drop the baked block from committed `.opencode/agents/*.md` (breaks
  dogfooding).
- Do NOT add a `permission:` placeholder to templates — remove the key entirely.
- Do NOT weaken the strict `aiPermissionSpliceBlock()` "fail loudly on missing key"
  guarantee for the `.opencode` sync path — write a separate `aiPermissionInsertBlock()`.
- Do NOT touch `merge_into_existing`/co-writer logic in `core.php` (OpenCode has a
  single writer).
- Keep installed Copilot/Claude agents WITHOUT any `permission:` block (no adapter
  change / NAC).
- Keep `templates/core/agents/` existing as canonical source (NAC).
- Do NOT design, add, or implement anything outside these five slices.

## Affected Paths

- `tools/ai/install/copilot-agent-renderer.php` (`aiInstallerCopyDirAsOpenCodeAgents()`)
- `tools/ai/install/permission-layers/` (new `aiPermissionInsertBlock()` sibling)
- `tools/ai/generate-agent-permissions.php` (`$dirs` scoping, lines ~41-51)
- `packages/ai-universal-rules/templates/core/agents/*.md` (strip `permission:`)
- `packages/ai-universal-rules/templates/optional/agents/*.md` (strip `permission:` for composed stems)
- `tests/.../AgentPermissionPolicyTest.php`
- `tests/.../AgentPermissionDriftTest.php` (line ~65 researcher needles)
- new guard test (composition-entry coverage per template stem)
- `docs/ai/adapter-contract.md` ("Permission Projection Seam" prose)

## Contracts And Boundaries

- Single composition source of truth: `aiPermissionAgentCompositions()` in
  `compositions.php`.
- Renderer contract is frozen: `aiPermissionRenderOpenCodeBlock($model, $render)`
  output bytes must not change.
- Insert vs splice boundary: splice = strict replace requiring existing key (`.opencode`
  sync path); insert = new key when none exists (installer render path).
- Keying contract: composed agents are identified by FILENAME STEM, never
  frontmatter `id`.
- Committed `.opencode/agents{,-optional}/*.md` must always carry a complete baked
  block on disk (runtime dogfooding contract).

## Todo Plan

- [ ] P0: Slice 1 — Installer render seam. Add `aiPermissionInsertBlock()` (insert when no `permission:` key exists; before `agent_assessment:` if present, else before closing `---`). Wire `aiInstallerCopyDirAsOpenCodeAgents()` to render+insert for composed stems, verbatim otherwise. Add OpenCode-install test. Prove seam WHILE templates still carry blocks (insert acts as replace → no-op). Verify: new test + `composer test:fast`.
- [ ] P0: Slice 2 — Scope the generator. Remove the two template dirs (lines ~42 and ~49) from `generate-agent-permissions.php` `$dirs`; keep the `.opencode` dirs. Verify `php tools/ai/generate-agent-permissions.php --check` green.
- [ ] P0: Slice 3 (APPROVAL-GATED, 13+ files — confirm scope before applying) — Strip the `permission:` block from all 13 core + any composed optional template files, preserving `agent_assessment:` and all other frontmatter. Remove the key entirely (no placeholder).
- [ ] P1: Slice 4 — Repoint tests. Update `AgentPermissionPolicyTest.php` (all `templates/core/agents/*.md` file-based permission assertions → `.opencode/agents/*.md` OR compose-model assertions via `aiPermissionCompose($stem)`) and `AgentPermissionDriftTest.php` (line ~65 researcher template needles → `.opencode` path or compose-model). Add a NEW guard test: every shipped template agent filename-stem has a composition entry.
- [ ] P1: Slice 5 — Byte-identical fixture proof + docs. Run a fresh install fixture before/after and assert byte-identical `.opencode/agents/*.md` permission blocks. Update `docs/ai/adapter-contract.md` "Permission Projection Seam" prose to state OpenCode now re-renders rather than verbatim-copies.

## Acceptance Criteria

- [ ] AC-01: `templates/{core,optional}/agents/*.md` carry no OpenCode `permission:` block.
- [ ] AC-02: Installed/dogfooded `.opencode/agents/*.md` OpenCode `permission:` block is rendered from composition (`aiPermissionRenderOpenCodeBlock` + per-agent render spec).
- [ ] AC-03: Not-yet-composed/hidden agents fall back to verbatim copy without error.
- [ ] AC-04: `php tools/ai/generate-agent-permissions.php --check` green; full `composer test` green; fresh-install fixture yields BYTE-IDENTICAL `.opencode/agents/*.md` permission blocks before/after.
- [ ] AC-05: New guard test asserts every shipped template agent stem has a composition entry.
- [ ] AC-06: `.opencode/agents/*.md` still contain a complete block on disk after generation.
- [ ] NAC-01: Installed Copilot/Claude agents contain NO `permission:` (CopilotAgentRendererTest:271, ClaudeAgentRendererTest:250, InstallerSafetyTest:804 stay green).
- [ ] NAC-02: `templates/core/agents/` still exists (InstallerSafetyTest:444).
- [ ] NAC-03/04: No composed-model byte changes; PermissionRenderAdaptersTest round-trips unchanged.

## Verification Plan

Each step names the command or inspection surface that proves an AC:

- Slice 1 seam / AC-03: new OpenCode-install test + `composer test:fast` (insert-as-replace no-op while templates still carry blocks).
- Slice 2 / AC-04 (generator green): `php tools/ai/generate-agent-permissions.php --check`.
- Slice 3 / AC-01: inspect `templates/{core,optional}/agents/*.md` — no `permission:` key present.
- Slice 4 / AC-05: run repointed `AgentPermissionPolicyTest.php`, `AgentPermissionDriftTest.php`, and the new composition-coverage guard test.
- Slice 5 / AC-02, AC-04, AC-06: fresh-install fixture before/after byte-diff of `.opencode/agents/*.md` permission blocks; `php tools/ai/validate-adapter-drift.php --fail-on-warn`.
- Whole change / NAC-01, NAC-03/04: full `composer test` (CopilotAgentRendererTest, ClaudeAgentRendererTest, InstallerSafetyTest, PermissionRenderAdaptersTest all green).

Verification commands:

php tools/ai/generate-agent-permissions.php --check
composer test:fast
composer test
php tools/ai/validate-adapter-drift.php --fail-on-warn

## Risks And Rollback

- Risk level: MEDIUM (install path for security-relevant permission frontmatter).
- Rollback: revert the slice commit(s). `.opencode/agents/*.md` remain
  baked-and-committed throughout, so a revert restores the exact prior on-disk state.
  Slice 3 template strip is the only source-deletion and is recoverable from git
  history.
- Success signal: generator `--check` green + byte-identical fixture +
  full `composer test` green + `php tools/ai/validate-adapter-drift.php --fail-on-warn`
  green.

## Handoff Notes

- Slice 3 is APPROVAL-GATED (13+ template files stripped): confirm scope with the
  user before applying, per repository approval boundaries for broad multi-file edits.
- The new guard test (Slice 4) protects the dead legacy Copilot/Claude fallback from
  silently shipping an empty Shell Boundary for a future uncomposed agent — do not
  omit it.
- Key composed agents by filename stem, never frontmatter `id`, in the installer seam.
- Recommended next step: implementer means implementer agent handoff using OpenCode command: /implement
