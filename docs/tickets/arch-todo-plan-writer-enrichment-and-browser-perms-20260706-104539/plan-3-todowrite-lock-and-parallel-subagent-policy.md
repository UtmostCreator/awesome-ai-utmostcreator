# Architecture Plan — Todowrite Always-Allow Lock + Safe Parallel Subagent Dispatch Policy

- Ticket: none (inferred from user instructions — see "Source Of This Plan")
- Source: user instruction to architect; mechanism/scope confirmed via two clarification rounds (see "Clarification Decisions")
- Generated: 2026-07-06T10:45:39Z (same session as plan-1/plan-2)
- Plan file: docs/tickets/arch-todo-plan-writer-enrichment-and-browser-perms-20260706-104539/plan-3-todowrite-lock-and-parallel-subagent-policy.md
- Sibling plans: plan-1-plan-writer-format-enrichment.md, plan-2-browser-webfetch-permission.md — this plan is independent of both and may run in any order relative to them (no shared files); see Recommended Execution Order for its own internal ordering.

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-3-todowrite-lock-and-parallel-subagent-policy.md` and move it into `archive/` under this branch folder.

## Source Of This Plan

No ticket id was supplied and the branch is `main`, so this plan is **inferred from user instructions**. The user asked for two things, bundled together: (A) auto-approve/always-allow `todowrite` (create-todo-list-in-own-UI) for any agent across Claude, OpenCode, and Copilot; (B) a policy for when it is safe to run multiple subagents/implementers in parallel without asking each time, gated on a specificity/clarity score, plus a workflow note ("begin implementation then run multiple super implementers if work won't cross").

## Clarification Decisions

Resolved via two rounds of `mcp_Question` before drafting this plan (recorded here per "ticket link... or mark as inferred... and save then under this info with plan"):

1. **Todowrite mechanism** — not asked separately; grounded directly in code (see Context/Problem). No ambiguity required a question.
2. **Claude Agent-tool grant** — user chose: grant the `Agent` tool (subagent spawn) to `implementer` when specificity >= 85; if the user directly/explicitly asks to run in parallel despite low specificity, **warn** with the exact score/gap, optionally ask clarifying questions to raise it, and **proceed anyway if the user still explicitly confirms** (human override respected, not silently blocked).
3. **Parallel mechanism** — user chose: in-process Task-tool fan-out (one orchestrating agent dispatches multiple subagent Task calls in the same run for provably non-overlapping slices), not just human multi-session guidance.
4. **Permission scalar change** — user chose: also update `task:` permission scalars where warranted (not purely behavioral/documentation), specifically for the two "implementer"-class agents the user named ("super implementers").

## Context

- `todowrite` in OpenCode is rendered **unconditionally** for every composed agent: `render-adapters.php` line 57 hardcodes `$lines[] = '  todowrite: allow';` before any per-agent branching. This is already a structural lock, not an accident — confirmed across all 13 core + optional composed agents.
- Claude's tool registry (`tools/ai/install/claude-agent-tool-registry.php`) has **no `TodoWrite` entry anywhere** — real gap, both in the read-only and write-capable tool sets and in the unknown-agent fallback.
- Copilot's tool registry (`tools/ai/install/copilot-agent-tool-registry.php`) uses a narrow, explicit tool-id vocabulary (`search/*`, `read/*`, `edit/*`, `execute/*`, `vscode/askQuestions`) with **no todo-list-equivalent id anywhere** — must be researched, not invented.
- Subagent spawn in Claude is gated by the same registry: only `task: allow` agents (`architect`, `post-install`) get the `Agent` tool; the file's own doc comment explains why — Claude subagents have no interactive ask channel, so `task: ask` cannot be honored inside one, and omitting `Agent` is the "conservative, honestly-documented fallback."
- `implementer` and `super-implementer` (OpenCode-only power variant — confirmed via `docs/ai/AGENTS-MANIFEST.md` line 58 and `docs/ai/agents.md` line 31, NOT a gap) currently carry **no `task:` key at all** in their OpenCode composition (`aiPermissionRenderNoTask()`), so their effective Task-tool ask/allow behavior falls to OpenCode's own unspecified-key default — this default's exact behavior is `unknown` pending Task 1 research.
- All existing agents already define a 0–100 "Instruction Specificity" score (see `architect.md`, `implementer.md`, `refactorer.md`) with named bands (e.g. implementer: `<50` hand off, `50–69` bounded subset, `70–89` implement with assumptions, `90–100` implement). The new `>= 85` parallel-dispatch gate sits inside the existing `70–89` band, meaning it is a **stricter** bar than plain "proceed," reusing the same scale rather than inventing a new one.
- `docs/ai/execution-protocol.md` already has a "Subagent Dispatch Stall Discipline" section (stalled/aborted dispatch handling) — the new policy extends this file with a pointer rather than duplicating it.

## Problem

1. Todowrite is not guaranteed available on Claude (missing tool) and is of unconfirmed status on Copilot, so "create a todo list in its own UI" is not actually reliable across all 3 runtimes today, despite being effectively solved on OpenCode already.
2. There is no documented, safe decision procedure for running multiple subagents in parallel; every dispatch today either has no `task:` key (unknown OpenCode default) or is `ask`/`deny` everywhere else, and Claude structurally cannot honor per-dispatch `ask`. Nothing currently tells an orchestrating agent when parallel fan-out is safe versus when it must stay serial.

## Target Outcome

- `todowrite`/its runtime-equivalent is confirmed or added as an always-allowed capability for every agent on OpenCode (already true — lock with a test), Claude (`TodoWrite` added to every tool-set), and Copilot (added if a real tool id exists; otherwise honestly documented as a platform gap).
- A new canonical policy (`docs/ai/parallel-subagent-safety.md`, linked from `execution-protocol.md`) defines exactly when an orchestrating agent may fan out multiple Task-tool calls (e.g. to `super-implementer`/`implementer`) without pausing per-dispatch: non-overlap is a hard, non-overridable floor; specificity >= 85 is a soft gate that can be explicitly overridden by a direct user request after a warning.
- `implementer` (OpenCode + Claude) and `super-implementer` (OpenCode-only) carry an explicit, intentional `task: allow` (or the researched safe equivalent) instead of an unknown/default key, so the fan-out capability is real and testable, not accidental.

## In Scope

- Research OpenCode's actual default behavior for an agent with no `task:` key (implementer, refactorer, super-implementer today).
- Add `task: allow` to `implementer` and `super-implementer` OpenCode compositions (via `compositions.php` + a `render-spec.php` builder), scoped ONLY to these two named "implementer-class" agents — not `refactorer` or any other agent lacking the key today.
- Add `TodoWrite` to every entry (and the fallback default) in `claude-agent-tool-registry.php`.
- Add the `Agent` tool to the `implementer` entry in `claude-agent-tool-registry.php` (super-implementer has no Claude render by design — OpenCode-only, not in scope for Claude).
- Research whether Copilot's tool vocabulary has a todo-list tool id and/or a subagent-spawn tool id; if yes, wire it in `copilot-agent-tool-registry.php`; if no, document the gap honestly in `docs/ai/copilot-tooling.md`.
- Write `docs/ai/parallel-subagent-safety.md`: the non-overlap hard floor, the specificity >= 85 soft gate, the warn-then-optional-override procedure for direct low-specificity requests, and per-runtime notes (OpenCode/Claude enabled, Copilot gap documented).
- Add a short pointer from `docs/ai/execution-protocol.md` "Subagent Dispatch Stall Discipline" to the new policy doc.
- Update `implementer.md`'s (and, where it has its own body, `super-implementer.md`'s) agent instructions to reference the new policy and describe the fan-out decision procedure.
- Update `tests/php/AgentPermissionPolicyTest.php` / `PermissionComposeTest.php` to assert the new `task: allow` grants for exactly `implementer` + `super-implementer`, and that no other agent's `task:` scalar changed.

## Out Of Scope (Things To Avoid)

- Do NOT change `task:` for any agent other than `implementer` and `super-implementer` (not `refactorer`, `config-maintainer`, `bootstrapper`, `post-install`, etc.) — user named "implementers/super implementers" specifically.
- Do NOT invent a Copilot todo-tool or subagent-spawn tool id without confirming it exists in the real Copilot/VS Code tool taxonomy this renderer targets. If unconfirmed, document as `unknown`/gap — never fabricate.
- Do NOT remove or weaken the non-overlap hard floor for any reason, including an explicit user request — per the user's own answer, only the specificity gate is human-overridable, not the overlap check.
- Do NOT touch the plan-writer format work (plan-1) or the webfetch/browser permission work (plan-2) — those are separate plans.
- Do NOT retroactively change any other agent's Claude `Agent` tool grant (`architect`, `post-install` keep their existing grants unchanged).
- Do NOT build new orchestration tooling/scripts for parallel dispatch — this is a documented policy + two permission-registry edits, not a new script or automation layer.

## Affected Paths

- `tools/ai/install/permission-layers/compositions.php` — add `task: allow` render metadata to `implementer` and `super-implementer` compositions
- `tools/ai/install/permission-layers/render-spec.php` — add a render builder emitting `task: allow` for these two agents (or reuse `aiPermissionRenderTaskAllow()`, which already exists per the earlier plan-2 research — confirm reusability)
- `packages/ai-universal-rules/templates/core/agents/implementer.md` — regenerated `permission:` block (`task: allow`) + new fan-out policy reference in body
- `.opencode/agents/super-implementer.md` — regenerated `permission:` block (`task: allow`) + policy reference (note: no `packages/` template source exists for this file by design — OpenCode-only; confirm the correct render/generation path during Task 1 before editing)
- `tools/ai/install/claude-agent-tool-registry.php` — add `TodoWrite` everywhere; add `Agent` to `implementer`
- `tools/ai/install/copilot-agent-tool-registry.php` — add todo/subagent tool ids IF they exist (research-gated)
- `docs/ai/copilot-tooling.md` — document any confirmed Copilot gap
- `docs/ai/parallel-subagent-safety.md` (new file) — the canonical policy
- `docs/ai/execution-protocol.md` — one-line pointer addition to "Subagent Dispatch Stall Discipline"
- `tests/php/AgentPermissionPolicyTest.php`, `tests/php/PermissionComposeTest.php` — new/updated assertions

## Files To Read For Similar Logic

~85% overlap with existing patterns — extend, do not reinvent:

- `tools/ai/install/permission-layers/render-spec.php` — `aiPermissionRenderTaskAllow()` (already exists, used by `architect`/`post-install` presumably) is the exact builder to reuse for implementer/super-implementer instead of writing a new one.
- `tools/ai/install/claude-agent-tool-registry.php` lines 32, 46 — `architect` and `post-install` already show the `array_merge($tools, ['Agent'])` pattern to copy for `implementer`.
- `docs/ai/execution-protocol.md` "Subagent Dispatch Stall Discipline" section — the sibling discipline this new policy must link to, not duplicate.
- `docs/ai/copilot-tooling.md` — existing precedent for documenting a Copilot platform gap honestly (the "Bash Command Policy cannot express per-command allowlists" pattern already used in `.claude/agents/*.md` / `.github/agents/*.agent.md` is the same honesty pattern to reuse for a todo/subagent gap).
- `tests/php/AgentPermissionPolicyTest.php` — existing per-agent scalar assertions (e.g. `testImplementerAsksBeforeBranchAndDependencyMutations`) to model the new assertions on.

## Contracts And Boundaries

- Contract: OpenCode's composed permission model + `--check` byte-stability gate is the source of truth (same as plan-2); Claude/Copilot tool registries are per-runtime projections keyed by filename stem.
- Boundary: `super-implementer` is intentionally OpenCode-only (`docs/ai/AGENTS-MANIFEST.md`) — do not create a Claude or Copilot render for it as part of this plan.
- Boundary: the non-overlap check is a hard floor enforced by agent judgment/instructions (no automated diff-overlap tool exists in this repo to invoke mechanically) — the policy doc must say this plainly rather than imply automatic enforcement.
- Boundary: granting `task: allow` to implementer/super-implementer is an autonomy-widening change explicitly confirmed by the user after being shown the trade-off (Claude's own registry comment explaining why `task: ask` agents don't get `Agent`) — record this trade-off in Risks, not silently.

## Recommended Execution Order

First safe chunk: **Task 1** (research OpenCode's true no-`task:`-key default) — read-only, zero edits, resolves the one real unknown blocking every other task in this plan.

1. Task 1 — research the no-`task:`-key default (read-only; unblocks everything else).
2. Task 2 — wire OpenCode `task: allow` for implementer + super-implementer.
3. Task 3 — Claude: add `TodoWrite` everywhere + `Agent` for implementer.
4. Task 4 — Copilot: research todo/subagent tool ids; wire or document gap.
5. Task 5 — write `docs/ai/parallel-subagent-safety.md` + link from execution-protocol.md + update implementer/super-implementer agent bodies.
6. Task 6 — update permission tests; full verification.

Tasks 2, 3, and 4 touch disjoint files (OpenCode compositions vs. Claude registry vs. Copilot registry + docs) and could be **dispatched to parallel subagents** once Task 1 is resolved, as a live example of this very policy (see Human Test Steps).

## Multi-Project Split And Order

single project — N/A. All edits are inside `awesome-ai-utmostcreator`.

## Todo Plan

- [ ] P0 — Task 1: Research OpenCode's no-`task:`-key default
  - [ ] P0.1: Determine what OpenCode does today when an agent's `permission:` block omits `task:` entirely (implementer, refactorer, super-implementer) — inherited global default, or effectively `allow`/`ask` — using OpenCode's own schema/docs plus `AgentPermissionPolicyTest.php`'s existing coverage of these three agents.
  - How this is tested: the finding is a documented fact (AC-01), not code; verified by citing the schema/doc/test evidence found.
- [ ] P1 — Task 2: OpenCode `task: allow` for implementer + super-implementer
  - [ ] P1.1: In `compositions.php`, change `implementer`'s and `super-implementer`'s `render:` to use (or extend) `aiPermissionRenderTaskAllow()`.
  - [ ] P1.2: Regenerate both agents' `permission:` blocks; confirm `task: allow` appears and nothing else changed.
  - [ ] P1.3: Run `php tools/ai/generate-agent-permissions.php --check` for byte-stability.
  - How this is tested: AC-02 (rendered block), AC-03 (`--check` green).
- [ ] P1 — Task 3: Claude — TodoWrite everywhere + Agent for implementer
  - [ ] P1.4: Add `TodoWrite` to `$readOnlyTools` and `$writeTools` (and the unknown-agent fallback) in `claude-agent-tool-registry.php`.
  - [ ] P1.5: Add `Agent` to the `implementer` entry (`array_merge($writeTools, ['Agent'])`, matching the `post-install` precedent).
  - How this is tested: AC-04 (every Claude agent's rendered `tools:` list includes `TodoWrite`; implementer's includes `Agent`).
- [ ] P1 — Task 4: Copilot — research + wire or document
  - [ ] P1.6: Research whether the Copilot/VS Code tool taxonomy used by this renderer has a todo-list tool id and/or a subagent-spawn tool id.
  - [ ] P1.7: If found, add it to `copilot-agent-tool-registry.php` for all agents (todo) / implementer (subagent). If not found, add an honest gap note to `docs/ai/copilot-tooling.md`.
  - How this is tested: AC-05 (either the tool id is wired and rendered, or the gap note exists and is accurate).
- [ ] P2 — Task 5: Policy doc + agent body updates
  - [ ] P2.1: Write `docs/ai/parallel-subagent-safety.md` with the non-overlap hard floor, the specificity >= 85 soft gate, and the warn-then-optional-override procedure for direct low-specificity requests.
  - [ ] P2.2: Add a one-line pointer from `docs/ai/execution-protocol.md` "Subagent Dispatch Stall Discipline" to the new doc.
  - [ ] P2.3: Update `implementer.md` (and `super-implementer.md`'s own body) to reference the policy and describe when it may fan out to parallel subagents.
  - How this is tested: AC-06 (doc exists and is linked), AC-07 (agent bodies reference it).
- [ ] P2 — Task 6: Tests + full verification
  - [ ] P2.4: Update `AgentPermissionPolicyTest.php`/`PermissionComposeTest.php` to assert `task: allow` for exactly `implementer` + `super-implementer` and no change elsewhere.
  - [ ] P2.5: Run the permission test suite and adapter-drift validator.
  - How this is tested: AC-08 (tests pass; status reported honestly).

## DONE / REMAIN

- [ ] 19 (open Todo Plan items — tasks + subtasks)
- [x] 0 (completed Todo Plan items)
- [ ] 8 (open Acceptance Criteria)
- [x] 0 (completed Acceptance Criteria)

> blockers / issues / errors: none confirmed yet. OPEN UNKNOWNS to resolve during implementation (not yet blockers, but must be closed before Task 2/Task 4 proceed): (1) OpenCode's exact no-`task:`-key default behavior (Task 1); (2) whether Copilot's tool taxonomy has any todo/subagent-spawn tool id at all (Task 4) — if the answer is no for both, Task 4 becomes a documentation-only task, not a wiring task. Record the exact evidence found for each here once resolved.

## Acceptance Criteria

- [ ] AC-01: OpenCode's no-`task:`-key default behavior for implementer/refactorer/super-implementer is documented with cited evidence (schema, docs, or existing test). Verified by reading the cited source.
- [ ] AC-02: `implementer` and `super-implementer`'s regenerated OpenCode `permission:` blocks contain `task: allow` and no unintended diff. Verified by reading the regenerated blocks.
- [ ] AC-03: `php tools/ai/generate-agent-permissions.php --check` reports byte-stable parity. Verified by exit status.
- [ ] AC-04: Every Claude-rendered agent's `tools:` list includes `TodoWrite`; `implementer`'s list additionally includes `Agent`. Verified by reading rendered `.claude/agents/*.md` files.
- [ ] AC-05: Either a real Copilot todo/subagent tool id is wired and rendered, or `docs/ai/copilot-tooling.md` accurately documents its absence. Verified by reading the registry/doc.
- [ ] AC-06: `docs/ai/parallel-subagent-safety.md` exists, states the non-overlap hard floor and the specificity >= 85 soft gate with the warn-then-optional-override procedure, and is linked from `execution-protocol.md`. Verified by reading both files.
- [ ] AC-07: `implementer.md` and `super-implementer.md` bodies reference the new policy and describe the fan-out decision procedure. Verified by reading both agent bodies.
- [ ] AC-08: `AgentPermissionPolicyTest.php`/`PermissionComposeTest.php` assert `task: allow` for exactly `implementer` + `super-implementer` (and unchanged elsewhere) and pass. Verified by test run.

## Optional Proof Checks

- Optional contract proof: `php tools/ai/validate-adapter-drift.php --fail-on-warn` after all edits, to confirm the three runtime surfaces stay internally consistent.
- UI / shared-UI proof: none directly renderable; the closest proxy is the Human Test Steps below (observing todo-list creation and parallel dispatch behavior live in each runtime).

## Verification Plan

- AC-01: cite evidence found (schema doc / OpenCode docs / existing test coverage); no command, an evidence citation.
- AC-02/AC-03: `bash scripts/ai/preview-file.sh packages/ai-universal-rules/templates/core/agents/implementer.md --around <permission-line>` + `php tools/ai/generate-agent-permissions.php --check`.
- AC-04: `bash scripts/ai/preview-file.sh .claude/agents/implementer.md --range 1:10` (and spot-check 2-3 other Claude agents).
- AC-05: `bash scripts/ai/preview-file.sh tools/ai/install/copilot-agent-tool-registry.php --range 12:59` or the gap-note doc.
- AC-06/AC-07: preview `docs/ai/parallel-subagent-safety.md`, `docs/ai/execution-protocol.md`, `implementer.md`, `super-implementer.md`.
- AC-08: `composer test -- --filter Permission` (focused, <=60s budget) then, if green, relevant broader suite; report exact command + status honestly including any failure.

## Human Test Steps

1. In OpenCode, ask the `implementer` agent to create a todo list for a small multi-step task; confirm the todo list appears in OpenCode's own UI without any permission prompt.
2. Repeat step 1 in Claude Code and in Copilot; confirm the todo list appears (or, for Copilot, confirm the documented gap behavior matches what actually happens).
3. Give the `implementer` agent a clearly-scoped, high-clarity request touching two disjoint files (e.g. two independent bug fixes in unrelated modules) and ask it to use multiple subagents; confirm it fans out to parallel `super-implementer`/`implementer` Task calls without pausing to ask, and reports both results.
4. Give it a vague, low-clarity request and ask it to run agents "in parallel" anyway; confirm it warns you about the low specificity/clarity score before proceeding, and only proceeds after your explicit confirmation.
5. Give it two requests that touch the SAME file; confirm it refuses to fan out in parallel regardless of how you answer, and explains that overlapping work cannot run in parallel.

## Risks And Rollback

- Risk (medium-high): granting `task: allow` to implementer/super-implementer removes a deliberate safety interrupt (per Claude's own registry doc comment). Mitigation: the non-overlap hard floor is non-overridable; the specificity gate defaults to safe/serial and only proceeds below-threshold on explicit, warned human override; this trade-off was explicitly surfaced to and accepted by the user before this plan was written.
- Risk (medium): OpenCode's no-`task:`-key default is currently `unknown` — Task 2 must not proceed until Task 1 closes this gap, otherwise the change's real effect is unverified.
- Risk (low): Copilot may have no equivalent tool at all for either todo or subagent-spawn; mitigation is to document honestly rather than force parity.
- Rollback: revert the `compositions.php`/`render-spec.php` edits + regenerate; revert `claude-agent-tool-registry.php`/`copilot-agent-tool-registry.php`; delete `docs/ai/parallel-subagent-safety.md` and the `execution-protocol.md` pointer line; revert test edits. No migration, no deploy. Success signal: all ACs pass and Human Test Steps behave as described.

## Handoff Notes

- Independent of plan-1 and plan-2 (no shared files) — may be implemented in any order relative to them.
- Task 1 is the mandatory first safe chunk; Tasks 2/3/4 may be dispatched to parallel subagents afterward as a live demonstration of the policy this plan itself introduces.
- Flag the two open unknowns (OpenCode default behavior; Copilot tool taxonomy) explicitly in the implementer's report — do not guess either.
- `implementer means implementer agent handoff using OpenCode command: /implement`.
- Given the medium-high risk autonomy change in Task 2, review in fresh context afterward: `reviewer means reviewer agent handoff using OpenCode command: /review-diff`, and consider `release-auditor` given the permission/autonomy posture change.
