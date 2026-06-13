# Agent Permission Rethink — Architecture Plan

Source proposal: `agent-permission-rethink.md` (repo root).
Research + architecture completed 2026-06-13. Risk: **HIGH** (security-posture change).

## Problem (confirmed)

Per-agent permission frontmatter carries large repeated Bash allow/ask/deny matrices:

- `.opencode/agents/architect.md` permission block ~104 lines (lines 15-118)
- `.opencode/agents/implementer.md` permission block ~206 lines (lines 22-227)

Both duplicate a long command policy in YAML plus prose "Script Access". This bloats
context and causes agents to attempt near-but-not-allowed command forms.

## Decisions (locked with user)

1. **Reuse existing taxonomies** (OQ-2). Do NOT add the proposal's 6-value
   `read|inspect|plan|verify|write|destructive` enum. Three taxonomies already exist:
   - registry `risk`: `read-only|mutating`
   - policy `tier0..tier4` (`command-policy.tiers.yaml`, `aiInstallerCommandPolicyRiskTiers()`)
   - `autonomy_level`: `observe|advise|act_with_approval`
   Gateway decisions derive from `risk` + `autonomy_level` + `requires_approval`.

2. **Extend `tools/ai/install/script-registry.php` as canonical** (Decision 1).
   Do NOT create `docs/ai/tool-registry.json` (~80% duplicate; violates >=75% reuse rule).
   Only genuinely new concept is profile visibility (agent -> tools).

3. **Reuse `aiRunScriptById` as the `tool:run` engine** (Decision 2). It already:
   fail-closes on unknown id, gates on pack + required tools, defaults to dry-run,
   passes `-- args` through. `tool:list`/`tool:describe` are thin registry views.

4. **Single agent->profile source of truth** (Decision 4): one
   `aiInstallerAgentProfiles()` map. Per-tool profile visibility derives from
   `risk`/`autonomy_level` (read-only -> all profiles; mutating -> impl-class profiles
   with approval), so we do NOT hand-edit `profiles[]` into ~40 registry entries.

5. **Generate compact inline frontmatter at install time** (Decision 5). OpenCode
   runtime include/merge is unproven; agents are already generated, so generate the
   compact block. (Deferred to P2/P3 — out of this session's P0-P1 scope.)

## BLOCKER resolution (OQ-1)

`render-agent-permissions.php` maps agents to `base-readonly/base-verify/base-impl`
but `command-policy.tiers.yaml` only defines `tier0..tier4`. Confirmed: the renderer
is **dead code** (errors `tier not found`; never executed in installer/tests/justfile;
only copied-as-file and existence-checked). It does NOT touch the P0-P1 surface
(`script-registry.php` + `ai.php` subcommands). Fix-vs-delete of the renderer is a
**separate deferred slice**.

## This session scope: P0-P1 only (additive, low rollback cost)

### P0 — Profile metadata in canonical registry

- Add `aiInstallerAgentProfiles()` returning agent -> profile map.
- Add `aiInstallerScriptProfiles(array $entry): array` deriving which profiles may see/run
  a tool from existing `risk`/`autonomy_level`/`requires_approval`.
- Files: `tools/ai/install/script-registry.php` (+ small helper).

Acceptance:
- Every tool resolves to a non-empty profiles list.
- Mutating/requires_approval tools never appear in a read-only profile's allow set.
- Existing registry tests stay green.

### P1 — Gateway subcommands on ai.php

- `tool:list [--profile=<role>] [--json]` — thin view over registry filtered by profile.
- `tool:describe <id> [--json]` — single-entry projection.
- `tool:run <id> [--profile=<role>] [-- args]` — wraps `aiRunScriptById` with a thin
  profile + approval pre-check before delegating.
- Files: `tools/ai/ai.php` (3 switch cases + usage), `tools/ai/commands/install_extras.php`
  (3 thin handlers reusing the existing engine).

Acceptance:
- unknown id fails closed.
- tool-not-in-profile fails closed.
- write/mutating tool without approval mode fails closed (stays dry-run/ask).
- stable JSON envelope via existing `aiCliWriteArtifact`.
- new `CliToolsTest`/test cases cover the three commands.

## Deferred (NOT this session)

- P2 generator of compact permission YAML from registry.
- P3 regenerate compact agent files + update `AgentPermissionPolicyTest`.
- P4 negative + "guard may only narrow" invariant tests.
- P-global `opencode.jsonc` grep/glob ask->allow.
- Renderer fix-vs-delete (dead `render-agent-permissions.php`).
- Drop original proposal items: `tool-registry.json`, 6-value enum, broad `tool:*` allow.

## Rollback posture

P0-P1 are additive. Rollback = revert the new switch cases + registry helper functions.
No data migration, no change to runtime agent frontmatter yet, so no permission-posture
change ships in this slice.

## Worktree note

The worktree is heavily dirty (in-progress `sh-introspect` refactor + many unrelated
changes). P0-P1 edits must stay isolated to `script-registry.php`, `ai.php`,
`install_extras.php`, and a new/extended test file. Do not touch pre-existing changes.
