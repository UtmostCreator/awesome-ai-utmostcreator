# Architecture Plan — Browser/WebFetch Permission For Edit-Capable Agents (OpenCode + Copilot + Claude)

- Ticket: none (inferred from user instructions — see "Source Of This Plan")
- Source: user instruction to architect; user chose "webfetch: allow only" mechanism and "all edit/mutate agents" scope via clarification questions
- Generated: 2026-07-06T10:45:39Z
- Plan file: docs/tickets/arch-todo-plan-writer-enrichment-and-browser-perms-20260706-104539/plan-2-browser-webfetch-permission.md
- Sibling plan: plan-1-plan-writer-format-enrichment.md (execute BEFORE this plan — see Recommended Execution Order)

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-2-browser-webfetch-permission.md` and move it into `archive/` under this branch folder (`docs/tickets/arch-todo-plan-writer-enrichment-and-browser-perms-20260706-104539/archive/DONE-plan-2-browser-webfetch-permission.md`).

## Source Of This Plan

No ticket id was supplied and the branch is `main`, so this plan is **inferred from user instructions**. The user asked to give agents that can edit/change/delete the ability to "open a browser and inspect it and logs and click buttons" across OpenCode, Copilot, and Claude. Via clarification the user confirmed:

- Mechanism = `webfetch: allow` only (the one browser-class scalar this repo already supports; used today by `script-runner`). Interactive click/DOM/live-log-tailing needs a browser tool the repo does not have — that is documented as a known gap, not built here.
- Scope = **all edit/mutate agents**: `implementer`, `super-implementer`, `refactorer`, `config-maintainer`, `post-install`, `bootstrapper` (every composed agent with an edit/mutate surface).

## Context

Agent permissions are NOT hand-written per file for these agents — they are **composed** in `tools/ai/install/permission-layers/compositions.php` and rendered by `tools/ai/install/permission-layers/render-adapters.php` + `render-spec.php` into:

- OpenCode: the `permission:` YAML block in each agent template (`packages/ai-universal-rules/templates/core/agents/*.md`)
- Copilot + Claude: an `allowedBash` projection resolved via `aiPermissionResolveAllowedBash()` (render-adapters.php lines 115-160)

`webfetch` is a real OpenCode permission scalar rendered from a render-spec "extra_scalars" builder. Precedent: `aiPermissionRenderScriptRunner()` (render-spec.php lines 37-49) emits `'webfetch' => 'allow'`; `aiPermissionRenderUiBuilder()` (lines 60-63) emits `'webfetch' => 'deny'`. So adding `webfetch: allow` to an agent = give that agent's composition a render builder whose `extra_scalars` includes `'webfetch' => 'allow'`, then re-render and update parity tests.

Permission behavior is guarded by `tests/php/AgentPermissionPolicyTest.php` and `tests/php/PermissionComposeTest.php` (the latter is already dirty in the worktree — do not clobber unrelated changes).

## Problem

Edit-capable agents cannot fetch a URL to inspect a rendered page or fetched log output, so they cannot verify web-facing behavior of a change they just made. Only `script-runner` (a read/analysis agent) has `webfetch: allow` today.

## Target Outcome

Each of the 6 edit-capable agents has `webfetch: allow` in its OpenCode `permission:` block and the equivalent WebFetch capability reflected in the Copilot/Claude projection, added through the composition/render pipeline (not hand-edited frontmatter), with permission-policy tests updated to assert the new grant and re-render byte-stability preserved. The interactive click/DOM/log-tail limitation is documented.

## In Scope

- Add a `webfetch: allow` extra-scalar to the render metadata of the 6 edit-capable composed agents in `compositions.php` (via `render:` builder), reusing/extending render-spec builders in `render-spec.php` rather than inlining per agent where a shared shape exists.
- Re-render the OpenCode `permission:` blocks in the 6 agent templates via the existing generator (`php tools/ai/generate-agent-permissions.php` / the install render path) — never hand-edit the rendered YAML.
- Ensure the Copilot/Claude `allowedBash` projection path is unaffected or correctly reflects the new scalar (webfetch is a scalar, not a bash pattern — confirm whether the projection needs any change or is a no-op; record `unknown` until verified).
- Update `tests/php/AgentPermissionPolicyTest.php` (and `PermissionComposeTest.php` if it asserts render shape) to assert `webfetch: allow` for the 6 agents and to keep the `--check` byte-stability gate green.
- Document the interactive-browser gap: add a short note (in the agents' Script Access / a capability or `docs/ai/`) that `webfetch` allows URL fetch/inspection only; clicking buttons, DOM interaction, and live log tailing require a browser/MCP tool not present in this repo.

## Out Of Scope (Things To Avoid)

- Do NOT invent or wire a new browser/Playwright/MCP tool, click driver, or log-tailing tool. `webfetch: allow` only. (User-confirmed.)
- Do NOT grant `webfetch` to read-only agents (architect, researcher, reviewer, auditors, plan-writer, agent-creator family) — scope is edit/mutate agents only.
- Do NOT hand-edit the rendered `permission:` YAML in the agent templates; change the composition/render source and regenerate.
- Do NOT touch the plan-writer format work — that is `plan-1-...`.
- Do NOT modify the unrelated in-flight worktree changes to `PermissionComposeTest.php`, `researcher.md`, `core.php`, etc. Only add to them if the test file genuinely must assert the new grant, and preserve existing dirty content (flag before touching).
- Do NOT change `websearch` or `external_directory` scalars for these agents (only `webfetch`).
- Do NOT run the installer's `--apply`/deploy path without approval; use `--check`/dry-run + targeted regeneration.

## Affected Paths

- `tools/ai/install/permission-layers/compositions.php` — add `webfetch: allow` render metadata to 6 agent compositions
- `tools/ai/install/permission-layers/render-spec.php` — add/extend render builder(s) emitting `webfetch: allow` (mirror `aiPermissionRenderScriptRunner` precedent)
- `packages/ai-universal-rules/templates/core/agents/{implementer,super-implementer,refactorer,config-maintainer,post-install,bootstrapper}.md` — regenerated `permission:` block (generator output, not hand-edited)
- `tests/php/AgentPermissionPolicyTest.php` — assert new grant (and any Copilot/Claude parity assertions)
- `tests/php/PermissionComposeTest.php` — only if it asserts render shape for these agents (preserve existing dirty changes)
- `tools/ai/install/permission-layers/render-adapters.php` — read-only reference unless the Copilot/Claude projection provably needs a change
- Documentation target for the interactive-gap note: `unknown` until Task 4 (candidate: each agent's "Script Access" section or a short `docs/ai/` note)

## Files To Read For Similar Logic

This is ~90% a copy of an existing pattern — read these first and extend, do not reinvent:

- `tools/ai/install/permission-layers/render-spec.php` lines 30-63 — `aiPermissionRenderScriptRunner()` (`webfetch: allow`) and `aiPermissionRenderUiBuilder()` (`webfetch: deny`) are the exact precedent builders to mirror.
- `tools/ai/install/permission-layers/compositions.php` lines 497-575 (`script-runner`, which consumes the webfetch-allow render) and lines 369-411 (`refactorer`/`implementer`, which currently use `aiPermissionRenderNoTask()` — the render builder that must change).
- `tools/ai/install/permission-layers/render-adapters.php` lines 115-160 — `aiPermissionResolveAllowedBash()` to confirm whether a scalar-only change affects the Copilot/Claude projection (likely a no-op; confirm).
- `tests/php/AgentPermissionPolicyTest.php` — existing per-agent scalar assertions to copy the assertion pattern for `webfetch`.
- `docs/tickets/arch-todo-optional-agent-permission-composition-20260705T221434Z/archive/DONE-plan.md` — the completed plan that introduced `ui-builder`'s `webfetch: deny` via the same render-builder mechanism (proven end-to-end recipe).

## Contracts And Boundaries

- Contract: the composed permission model is the single source of truth; OpenCode YAML + Copilot/Claude `allowedBash` are projections. `php tools/ai/generate-agent-permissions.php --check` is the byte-stability parity gate and MUST stay green.
- Contract: `render-spec.php` builders are generator-only presentation metadata (extra scalars + quote), not part of the composed bash model — see its own doc block. Adding `webfetch` belongs here.
- Boundary: read-only agents must remain webfetch-denied/absent; only the 6 edit/mutate agents change.
- Boundary: interactive browser control (click/DOM/log tail) is explicitly out of the platform's current capability — documented, not implemented.

## Recommended Execution Order

First safe chunk: **Task 1** (add/extend the render-spec builder emitting `webfetch: allow`) — it is a pure additive function with an exact precedent (`aiPermissionRenderScriptRunner`), unit-testable in isolation, and changes no agent until Task 2 wires it in.

1. Task 1 — add the render builder (isolated, no agent affected yet).
2. Task 2 — wire the builder into the 6 compositions.
3. Task 3 — regenerate the OpenCode blocks + confirm Copilot/Claude projection; run `--check`.
4. Task 4 — update permission tests + document the interactive-browser gap.
5. Task 5 — full verification.

Rationale: builder-before-wiring keeps the first change inert and reversible; regeneration is gated behind a green composition; tests + docs land only once the rendered surface is stable.

## Multi-Project Split And Order

single project — N/A. All edits are inside `awesome-ai-utmostcreator`. `docs/ai/project/project-interaction.md` external map contains only placeholder rows, so no external project edit or ordering applies. (If a future variant of this work touched a second project, this section would split per project and require reading that interaction map first.)

## Todo Plan

- [ ] P0 — Task 1: Add a `webfetch: allow` render builder
  - [ ] P0.1: In `render-spec.php`, add a builder (e.g. `aiPermissionRenderNoTaskWebfetch()` or extend the existing shape) whose `extra_scalars` includes `'webfetch' => 'allow'`, mirroring `aiPermissionRenderScriptRunner()`; preserve the correct scalar ordering/quote each agent already uses (implementer/refactorer use `NoTask`; config-maintainer/post-install/bootstrapper/super-implementer — confirm each one's current render builder first).
  - How this is tested: a focused PHPUnit assertion on the builder's returned array (AC-01); pure function, no side effects.
- [ ] P1 — Task 2: Wire the builder into the 6 edit-capable compositions
  - [ ] P1.1: `implementer` — swap its `render:` to the webfetch-allow variant.
  - [ ] P1.2: `refactorer` — same.
  - [ ] P1.3: `config-maintainer` — same (currently `aiPermissionAgentSpecVerify`; confirm its render builder).
  - [ ] P1.4: `post-install` — same.
  - [ ] P1.5: `bootstrapper` — same.
  - [ ] P1.6: `super-implementer` — same.
  - How this is tested: composition-level assertion that each of the 6 renders `webfetch: allow` and read-only agents still do not (AC-02, AC-03).
- [ ] P1 — Task 3: Regenerate rendered surfaces + parity
  - [ ] P1.7: Run the generator to re-render the 6 OpenCode `permission:` blocks; confirm each now contains `webfetch: allow` and nothing else changed.
  - [ ] P1.8: Determine whether `aiPermissionResolveAllowedBash()` (Copilot/Claude) needs any change for a scalar; if no-op, record that; if not, wire the WebFetch capability into the Copilot/Claude projection.
  - [ ] P1.9: Run `php tools/ai/generate-agent-permissions.php --check` and confirm byte-stability parity is green.
  - How this is tested: AC-04 (rendered YAML contains the scalar), AC-05 (`--check` green).
- [ ] P2 — Task 4: Tests + gap documentation
  - [ ] P2.1: Update `tests/php/AgentPermissionPolicyTest.php` to assert `webfetch: allow` for the 6 agents and its absence/deny for read-only agents; preserve any unrelated in-flight edits.
  - [ ] P2.2: Add the interactive-browser-gap note (webfetch = fetch/inspect only; click/DOM/log-tail unsupported) to the agreed doc surface (resolve `unknown` target during implementation, flag before writing).
  - How this is tested: AC-06 (tests assert the grant), AC-07 (gap note present).
- [ ] P2 — Task 5: Full verification
  - [ ] P2.1: Run the permission test suite + adapter-drift validator.
  - How this is tested: AC-08 (test + validator status reported honestly).

## DONE / REMAIN

Author fills these counts on every update by counting the checkboxes above. Initial state:

- [ ] 15 (open Todo Plan items — tasks + subtasks)
- [x] 0 (completed Todo Plan items)
- [ ] 8 (open Acceptance Criteria)
- [x] 0 (completed Acceptance Criteria)

> blockers / issues / errors: none at plan time. KNOWN CONSTRAINT to surface during implementation — the worktree is already dirty (`PermissionComposeTest.php`, `researcher.md`, `core.php`, `compositions.php`, `validate-*.php`, `install-ai-kit.sh`). Preserve those pre-existing changes; flag any that conflict with this work before editing, and record the exact command + status of any failed regeneration or test here.

## Acceptance Criteria

- [ ] AC-01: A render-spec builder returns `extra_scalars` containing `'webfetch' => 'allow'`. Verified by focused PHPUnit assertion on the builder.
- [ ] AC-02: Each of the 6 edit-capable agents' composed spec renders `webfetch: allow`. Verified by composition/render assertion.
- [ ] AC-03: No read-only agent (architect, researcher, reviewer, auditors, plan-writer, agent-creator family) gains `webfetch: allow`. Verified by negative assertion across the read-only set.
- [ ] AC-04: The regenerated OpenCode `permission:` block in each of the 6 agent templates contains `webfetch: allow` and no unintended diff. Verified by reading the regenerated blocks / diff.
- [ ] AC-05: `php tools/ai/generate-agent-permissions.php --check` reports byte-stable parity (green). Verified by exit status.
- [ ] AC-06: `tests/php/AgentPermissionPolicyTest.php` asserts the new grant for the 6 agents and its absence for read-only agents, and passes. Verified by test run.
- [ ] AC-07: A documented note states `webfetch` allows URL fetch/inspection only and that click/DOM/live-log interaction is unsupported (no such tool in repo). Verified by reading the note.
- [ ] AC-08: The permission test suite and adapter-drift validator pass (or every failure is reported with exact command + status). Verified by command output.

## Optional Proof Checks

- Optional contract proof: run `php tools/ai/validate-adapter-drift.php --fail-on-warn` to confirm the Copilot/Claude/OpenCode surfaces stay consistent after the scalar addition.
- UI / shared-UI proof: none directly; however, once merged, a human can smoke-test that an edit-capable agent can now fetch a URL (see Human Test Steps) — this is the closest thing to a UI proof for this change.

## Verification Plan

- AC-01/AC-02/AC-03: `composer test:fast` filtered to the permission tests, or `composer test -- --filter AgentPermissionPolicyTest` (respect the anti-freeze budget: focused filter ≤ 60s, full suite ≤ 180s).
- AC-04: `bash scripts/ai/preview-file.sh packages/ai-universal-rules/templates/core/agents/implementer.md --around <permission-block-line>` for each of the 6 templates after regeneration.
- AC-05: `php tools/ai/generate-agent-permissions.php --check` — report exact status.
- AC-06/AC-08: `composer test -- --filter Permission` then, if green, the fuller relevant suite; also `php tools/ai/validate-adapter-drift.php`. Report every command + status honestly, including failures.
- AC-07: preview the doc surface holding the gap note.

## Human Test Steps

After merge, a human can confirm the new capability in OpenCode (primary), then Copilot/Claude:

1. Open OpenCode, start the `implementer` (or `refactorer`) agent, and ask it to fetch a known public URL (e.g. "fetch https://example.com and show the title").
2. Confirm the agent performs the web fetch WITHOUT a permission-denied error (previously it would have been blocked).
3. Start a read-only agent (e.g. `reviewer`) and ask it to fetch the same URL; confirm it is still NOT granted webfetch (scope guard holds).
4. In Copilot and Claude, open an edit-capable agent and confirm the WebFetch capability is present in its projected permission surface (inspect the rendered agent config / attempt a fetch).
5. Confirm that asking any agent to "click a button" or "tail the live log" is correctly reported as unsupported (documents the known gap).

## Risks And Rollback

- Risk (medium): touching the permission pipeline can drift the rendered surfaces or break the `--check` gate. Mitigation: builder-before-wiring order, `--check` gate as AC-05, targeted tests before full suite.
- Risk (medium): the dirty worktree already contains unrelated permission-file changes; accidental clobber. Mitigation: Out-Of-Scope rule + blockers note require preserving and flagging pre-existing changes before editing.
- Risk (low): granting webfetch to mutate agents slightly widens capability. Mitigation: scoped to the 6 edit agents only; read-only agents explicitly excluded (AC-03); webfetch is fetch-only, not command execution.
- Rollback: revert the `render-spec.php` builder + the 6 `compositions.php` `render:` edits + regenerate; `git checkout --` the regenerated templates and test edits. No data migration, no deploy. Success signal after change: `--check` green + permission tests green + the Human Test Steps behave as described.

## Handoff Notes

- Execute AFTER `plan-1-plan-writer-format-enrichment.md`.
- This is a permissions/pipeline change (medium risk) — reviewer and likely release-auditor are warranted per architect Design Rules (install/catalog/permissions-affecting change).
- Preserve the existing dirty worktree edits; flag conflicts before touching shared files.
- Resolve the `unknown` doc target for the gap note during Task 4 and flag it before writing.
- `implementer means implementer agent handoff using OpenCode command: /implement`.
- After implementation, review in fresh context: `reviewer means reviewer agent handoff using OpenCode command: /review-diff`.
