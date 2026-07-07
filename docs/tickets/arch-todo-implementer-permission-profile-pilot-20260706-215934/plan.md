# Architecture Plan — Implementer permission-profile pilot: label the generated permission block and add an authoring-vocabulary projection (implementer agent only)

- Ticket: none
- Source: architect handoff (bounded task description)
- Generated: 2026-07-06T21:59:34Z
- Plan file: docs/tickets/arch-todo-implementer-permission-profile-pilot-20260706-215934/plan.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan.md` and move it into `archive/` under this ticket folder (`docs/tickets/arch-todo-implementer-permission-profile-pilot-20260706-215934/archive/DONE-plan.md`). See "Archive On Completion" below for the exact steps.

## Context

- `packages/ai-universal-rules/templates/core/agents/implementer.md` carries a ~190-line inline `permission:` block (lines 22–211). The user perceives this as confusing bloat and wants agents to declare short "profiles" instead of inline permission rules, shipping cleanly to OpenCode, Copilot, and Claude while preserving all permissions.
- CRITICAL VERIFIED FACT: that 190-line block is GENERATED OUTPUT, spliced in by `tools/ai/generate-agent-permissions.php` (splices into BOTH `packages/ai-universal-rules/templates/core/agents/implementer.md` AND `.opencode/agents/implementer.md`; `--check` is a byte-identical drift gate; the splicer already preserves the trailing hand-authored `agent_assessment:` key at lines 212–214).
- The TRUE source of truth is the ~15-line composition spec in `tools/ai/install/permission-layers/compositions.php` (lines 411–438): `aiPermissionAgentSpecImpl(editSurface:'code', render: aiPermissionRenderNoTask(), allowPacks:[...], languageOverlays:['php','js-ts'], askPacks:[...], exceptions:[...])`.
- A mature composition engine ALREADY EXISTS (profiles+layers+packs+generator+validator+three-runtime seam): `compose.php` (`aiPermissionProfileLayerNames` defines readonly/verify/impl), `agent-spec.php` (named-arg DSL), `render-adapters.php` (`aiPermissionRenderAdapters()` returns opencode/copilot/claude; all pure functions of the composed model). Copilot (`copilot-agent-renderer.php:38`) and Claude (`claude-agent-renderer.php:41`) derive their bash allow-list from the model at render time via `aiPermissionResolveAllowedBash()`; they do NOT read the inline YAML. OpenCode reads the spliced `permission:` YAML directly at runtime.

## Problem

The ~190-line inline `permission:` block in the human-authored implementer template reads as confusing bloat, even though it is generated, byte-identical, drift-gated output. The authoring surface should shrink and be clearly framed as generated ("do not hand-edit"), and a short profile vocabulary should be introduced — WITHOUT changing the shipped enforcement surface that OpenCode reads at runtime.

## Target Outcome

For the `implementer` agent ONLY: the human-authored frontmatter clearly labels the `permission:` block as generated/collapsed output, an optional three-axis authoring vocabulary (`capability_profile`, `permission_profile`, `command_profile`) is defined strictly as projections over the already-existing composed model, and all three runtimes' shipped outputs remain fully enforceable and byte-identical in every permission rule.

## In Scope

- Reclassify/label the rendered `implementer` `permission:` block as generated/collapsed output ("do not hand-edit; regenerate with --write"), NOT shrinking the shipped enforcement surface.
- Introduce the three-axis authoring vocabulary (`capability_profile`, `permission_profile`, `command_profile`) as OPTIONAL projections over the existing model:
  - `permission_profile` ≙ existing `profile`(impl) + `edit_surface`(code)
  - `command_profile` ≙ existing `cli_tools` + verify/write packs
  - `capability_profile` ≙ a named bundle over the existing `capabilities:` list
- Keep these projections in the PHP registry + `docs/ai/**`, NOT as new enforceable frontmatter in the shipped OpenCode file.
- Keep the shipped OpenCode `permission:` block fully expanded and byte-identical.
- Pilot limited to the `implementer` agent.

## Out Of Scope (Things To Avoid)

- Do NOT replace the OpenCode `permission:` YAML with a bare unexpanded pointer (breaks runtime enforcement).
- Do NOT change any allow/ask/deny rule, pack, overlay, or exception for implementer.
- Do NOT touch any agent other than implementer.
- Do NOT weaken the hard-deny floor or any test.
- Do NOT invent a new composition engine.
- Do NOT destructively rename `capabilities:`; `capability_profile` is additive/bundle-reference only.
- Do NOT reorder bash rules (`*` must render FIRST — `.findLast()` last-match-wins).
- No rename/delete without approval.

## Affected Paths

- `packages/ai-universal-rules/templates/core/agents/implementer.md`
- `.opencode/agents/implementer.md` (regenerated via `--write`)
- `tools/ai/install/permission-layers/compositions.php` (additive annotation only, if used)
- `docs/ai/adapter-contract.md` and/or `docs/ai/ai-file-standards.md`
- `tools/ai/generate-agent-permissions.php` or renderer (ONLY if the marker needs splicer support — flag before editing)

## Contracts And Boundaries

- Chosen Design (Option A): Keep the inline rendered `permission:` block as-is but formally reclassify/label it as generated/collapsed output; shrink the AUTHORING surface (and add clear "generated — do not hand-edit" framing), NOT the shipped enforcement surface. Introduce the three-axis authoring vocabulary ONLY as optional projections over the already-existing model. These live in the PHP registry + `docs/ai/**`, NOT as new enforceable frontmatter in the shipped OpenCode file. The shipped OpenCode `permission:` block stays fully expanded and byte-identical.
- Rationale for rejecting Option B (bare pointer in shipped file): OpenCode reads `permission:` YAML directly at runtime, so an unexpanded pointer in the shipped `.opencode/agents/implementer.md` would BREAK enforcement. Copilot/Claude could tolerate a pointer (they render from the model) but OpenCode cannot, so a uniform pointer scheme is unsafe.
- Reuse assessment: ~85–90% reuse of the existing `tools/ai/install/permission-layers/` system. Engine reused unchanged. `permission_profile`/`command_profile` ≥90% overlap with existing `profile`+`edit_surface`/`cli_tools`; `capability_profile` ~40% overlap over existing `capabilities:` list. Net new work = naming/labeling projection, not a new abstraction. Per AGENTS.md ≥75% reuse rule: EXTEND, do not reinvent.

## Todo Plan

- [ ] P0: Confirm current `implementer` byte-baseline: run `php tools/ai/generate-agent-permissions.php --check` (must be green before any change).
- [ ] P0: Design a generated-block marker/label convention and VALIDATE it against `aiPermissionSpliceBlock` boundary detection (the splicer treats `#` comment and blank/indented lines specially — the marker must survive a `--write`/`--check` round-trip without shifting the block boundary).
- [ ] P1: Add the authoring vocabulary as an additive projection annotation for the `implementer` spec in `compositions.php` (projecting existing profile/edit_surface/cli_tools/capabilities — no new enforceable inputs, no rule changes).
- [ ] P1: Document the profile vocabulary + generated-block convention in `docs/ai/adapter-contract.md` (Permission Projection Seam) and/or `docs/ai/ai-file-standards.md`.
- [ ] P1: Regenerate via `php tools/ai/generate-agent-permissions.php --write`; confirm the shipped OpenCode block stays fully expanded and rule-identical (diff shows only marker/label + docs, ZERO rule-line changes).
- [ ] P2: Run full verification (see Verification Plan below).

## Acceptance Criteria

Explicit:

- [ ] AC-01: `implementer`'s human-authored frontmatter clearly labels the `permission:` block as generated/collapsed output ("do not hand-edit; regenerate with --write").
- [ ] AC-02: All three runtimes' shipped outputs remain enforceable: OpenCode `permission:` YAML stays fully expanded and byte-identical; Copilot Shell Boundary and Claude Bash policy remain derived from the composed model (unchanged).
- [ ] AC-03: `php tools/ai/generate-agent-permissions.php --check` passes (byte-identical drift gate) after regeneration.
- [ ] AC-04: `php tools/ai/validate-adapter-drift.php` passes.
- [ ] AC-05: `tests/php/PermissionRenderAdaptersTest.php`, `PermissionComposeTest`, `AgentPermissionPolicyTest`, and `composer test:fast` (full suite) pass.
- [ ] AC-06: Diff of `implementer.md` shows only marker/label + authoring-vocabulary docs; ZERO permission rule-line changes.

Negative:

- [ ] NAC-01: No rule (allow/ask/deny), pack, overlay, or exception for implementer changes.
- [ ] NAC-02: No other agent file changes.
- [ ] NAC-03: The shipped `.opencode/agents/implementer.md` `permission:` block is NEVER an unexpanded pointer.
- [ ] NAC-04: Bash `*` entry still renders first; hard-deny floor unchanged.

## Verification Plan

- `php tools/ai/generate-agent-permissions.php --check` — proves AC-03, NAC-01, NAC-04 (byte-identical drift gate; any rule-line or ordering change fails here).
- `php tools/ai/validate-adapter-drift.php` — proves AC-04 (adapter surfaces stay consistent).
- `tests/php/PermissionRenderAdaptersTest.php`, `PermissionComposeTest`, `AgentPermissionPolicyTest` — prove AC-02, AC-05, NAC-04 (three-runtime seam + policy floor unchanged).
- `composer test:fast` — proves AC-05 (full suite passes).
- Manual diff inspection of `implementer.md` (both template and `.opencode/`) — proves AC-01, AC-06, NAC-02, NAC-03 (only marker/label + docs changed; block still fully expanded).

## Risks And Rollback

Top risks:

1. OpenCode enforcement breakage if the shipped block ever becomes a pointer — mitigated by keeping the block fully expanded + `--check` gate.
2. Vocabulary drift if `*_profile` keys become an independent source — mitigated by defining them strictly as projections of the existing model.
3. Marker convention conflicting with `aiPermissionSpliceBlock` — mitigated by validating the marker against a `--write`/`--check` round-trip before adopting.

Rollback: revert the pilot commit; because no rule changed and the block stays byte-identical, rollback is a clean git revert with `--check` re-green as the success signal.

## Handoff Notes

- Recommended next step: implementer means implementer agent handoff using OpenCode command: /implement
- The P0 marker/round-trip validation step gates everything downstream; if the marker cannot survive a `--write`/`--check` round-trip without shifting the splice boundary, pause and re-design the convention before touching `compositions.php` or docs.
- Editing `tools/ai/generate-agent-permissions.php` or the renderer is conditional (only if the marker needs splicer support) — flag before editing, per Files In Scope.

## Archive On Completion

When every `## Todo Plan` item AND every `## Acceptance Criteria` item above is checked `[x]`, archive this plan so active and finished plans stay separated:

```text
docs/tickets/arch-todo-implementer-permission-profile-pilot-20260706-215934/plan.md
  -> docs/tickets/arch-todo-implementer-permission-profile-pilot-20260706-215934/archive/DONE-plan.md
```

Steps: (1) create `archive/DONE-plan.md` with the full plan contents, (2) replace this `plan.md` with a one-line tombstone pointing to the archived copy. Do not archive while any item is still unchecked.
