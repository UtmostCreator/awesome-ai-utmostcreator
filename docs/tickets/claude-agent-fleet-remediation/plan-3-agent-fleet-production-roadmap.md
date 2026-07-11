# Architecture Plan — Production-Grade Agent Fleet Architecture — AgentSpec, Permission Compiler, Policy Gates, Evals

- Ticket: none
- Source: user-pasted roadmap in chat session, 2026-07-07
- Generated: 2026-07-07T19:11:53Z
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-3-agent-fleet-production-roadmap.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-3-agent-fleet-production-roadmap.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-3-agent-fleet-production-roadmap.md`). See "Archive On Completion" below for the exact steps.

## Context

- Current 24-agent fleet estimated at 74/100. Fleet has strong role coverage but is too prompt-dependent: no single source of truth across Claude/`.claude/agents`, OpenCode/`.opencode/agents`, Copilot/`.github/agents` causes adapter drift; permissions are hand-maintained per agent instead of compiled from reusable profiles; there is no runtime policy gate enforcing deny rules (only advisory prose); there is no evidence-first context contract; there is no eval harness proving agents behave correctly instead of just sounding correct.
- Core principle (record verbatim): "Production-grade agents are not better prompts. They are verified workflows with bounded permissions, deterministic evidence, reusable skills, and CI enforcement."
- This plan is the fleet-infrastructure layer above the per-agent template remediation pass that just completed (`docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/plan.md`, 24/24 agents, 2026-07-07). That prior pass fixed prose/frontmatter defects inside existing templates; this plan proposes new source-of-truth tooling (AgentSpec, permission compiler, policy gate, evals) that would generate those templates going forward. It does not re-touch the 24 just-remediated templates itself.
- Prior-art check performed before writing this plan (see Risks And Rollback and Handoff Notes for the full findings): a mature permission-composition engine already exists in this repo (`tools/ai/install/permission-layers/compositions.php`, `compose.php`, `agent-spec.php`, `render-adapters.php`) with profiles/layers/packs and a three-runtime render seam (OpenCode/Copilot/Claude), plus a locked decision record (`arch-todo-agent-permission-rethink-20260613T154104Z/plan.md`) that explicitly rejected building a second parallel tool/permission registry to avoid duplicating `tools/ai/install/script-registry.php` under the project's `>=75%` reuse rule. Any Phase 1 implementation plan that follows this roadmap MUST reconcile the proposed `packages/ai-contracts/schemas/permission-profile.schema.json` and "Permission Compiler" concept against that existing engine rather than reinventing it.

## Problem

The fleet scores well on role coverage but is structurally fragile in the areas that matter for production use: permission safety, cross-adapter portability, hallucination resistance, verification discipline, and runtime enforceability all lag because there is no compiled, single-source-of-truth spec for agents/skills/commands/permissions, no runtime-enforced policy gate (today's guardrails are advisory prose per adapter), no structured evidence/handoff contract that orchestration can machine-check, and no eval harness that proves agent behavior rather than asserting it in prompt text.

## Target Outcome

Record the following per-area score table (current estimate -> target), as stated in the source roadmap:

| Area | Current | Target |
|---|---|---|
| Agent role coverage | 86 | 92 |
| Permission safety | 68 | 95 |
| Cross-adapter portability | 62 | 94 |
| Hallucination resistance | 65 | 93 |
| Verification discipline | 72 | 95 |
| Token economy | 74 | 90 |
| Fleet maintainability | 70 | 96 |
| Runtime enforceability | 58 | 94 |

Final achievable score: ~94/100. The missing 6 points are structural: Copilot/Claude/OpenCode do not expose identical permission/runtime semantics, so parity requires CI/hook enforcement wherever native permission support is absent — this gap cannot be closed by prompt or schema design alone.

## In Scope

- `packages/ai-contracts/schemas/{agent,skill,command,permission-profile,handoff,evidence}.schema.json` — new AgentSpec contract schemas.
- `packages/ai-universal-rules/specs/{agents,skills,commands,permissions}/*.yml` — new AgentSpec source-of-truth layer that generates the existing `packages/ai-universal-rules/templates/**`.
- `tools/ai/{render-agents,validate-agents,validate-permissions,validate-generated-drift,eval-agents,score-handoffs,policy-gate}.php` — new tooling scripts implementing the spec/render/validate/eval pipeline.
- Reusable permission profiles: `readonly-research`, `readonly-review`, `scoped-edit`, `docs-edit`, `config-edit`, `unsafe-deny` (baseline), `release-guarded`.
- Runtime policy gate: a scripts-level deny/require_approval/allow pattern list (destructive git, secret reads, `curl | sh`, etc.), wired via Claude PreToolUse hooks and OpenCode permissions, with CI/wrapper enforcement as the Copilot fallback since Copilot instructions are not a hard sandbox.
- Portable skills under `.agents/skills/` (`scope-contract`, `evidence-search`, `safe-edit`, `test-selection`, `adapter-parity`, `release-audit`, `agent-assessment`, `prompt-injection-defense`) rendered/copied to provider-native skill paths.
- New workflow commands: `/project-profile`, `/scope`, `/context-pack`, `/policy-audit`, `/agent-fleet-assess`, `/drift-check`, `/verify-change`, `/release-check`, `/secret-scan`, `/adapter-parity`.
- Structured output contract appended to every production agent's Final Output (Scope used / Evidence / Changes / Verification / Risk / Next handoff) so orchestration is machine-checkable.
- Golden eval harness under `tests/agent-evals/` (fixtures + `expected/*.yml`) covering: scope refusal, minimal bugfix, review accuracy, unsafe-command block, adapter parity, hallucinated-fact refusal, secret-access denial, handoff scoring.
- `agent-fleet-assessor` upgrade to a read-only orchestrator that prefers deterministic validators before LLM judgement (`permission_profile: readonly-research`, `delegates_to: agent-critic`).
- Copilot hardening: `.github/agents/*.agent.md`, `.github/copilot-instructions.md`, `.github/instructions/*.instructions.md`, `.github/prompts/*.prompt.md`, `.github/workflows/copilot-setup-steps.yml`, plus CI policy gates since Copilot instructions alone are not a sandbox.
- `AGENTS.md` trimmed to only stable operating rules (Build/Test/Verify, Scope confidence thresholds 70/90, Evidence discipline) — long procedures move to skills, not stay inline.

## Out Of Scope (Things To Avoid)

- Description-cleanup-only work (scored 72/100 by the roadmap — not sufficient alone, and already covered by the separate `arch-todo-agent-fleet-improvement-plans-20260707` ticket).
- MCP registry with budget rules (roadmap explicitly flags this "powerful but risky", e.g. GitHub MCP context bloat) — do not design this now.
- Observability logging and model/temperature/step policy (roadmap ranks these below the validation-focused items, 87 and 84) — defer to a later plan.
- Hand-editing any currently generated file under `packages/ai-universal-rules/templates/**/agents`, `.claude/agents`, `.opencode/agents`, `.github/agents` outside the (not-yet-built) renderer.
- Actually implementing Phase 1-5 code in this pass — this plan file records the roadmap and phase breakdown only; implementation is a separate follow-up plan gated on architect approval of the schema design.
- Redesigning the existing `tools/ai/install/permission-layers/` composition engine (`compose.php`, `agent-spec.php`, `render-adapters.php`, `compositions.php`) or `tools/ai/install/script-registry.php` from scratch — the Phase 1 implementation plan must reconcile with this prior art (see Risks And Rollback), not replace it unreviewed.
- Re-touching any of the 24 agent templates already remediated by `arch-todo-agent-fleet-improvement-plans-20260707` as part of this plan.

## Affected Paths

None yet — this plan records the roadmap only. Prospective paths for the (separate, future) Phase 1 implementation plan:

- `packages/ai-contracts/schemas/*.schema.json` (new)
- `packages/ai-universal-rules/specs/{agents,skills,commands,permissions}/*.yml` (new)
- `tools/ai/render-agents.php`, `tools/ai/validate-agents.php`, `tools/ai/validate-permissions.php`, `tools/ai/validate-generated-drift.php`, `tools/ai/eval-agents.php`, `tools/ai/score-handoffs.php`, `tools/ai/policy-gate.php` (new)
- `.agents/skills/**` (new)
- `.claude/commands/**`, `.opencode/command/**`, `.github/prompts/**` (new workflow commands, adapter-specific projections)
- `tests/agent-evals/**` (new)
- `.github/agents/*.agent.md`, `.github/copilot-instructions.md`, `.github/instructions/*.instructions.md`, `.github/prompts/*.prompt.md`, `.github/workflows/copilot-setup-steps.yml` (hardening edits)
- `AGENTS.md` (trim pass)
- `packages/ai-universal-rules/templates/core/agents/agent-fleet-assessor.md` (upgrade to read-only orchestrator)

## Contracts And Boundaries

- AgentSpec (`packages/ai-universal-rules/specs/**`) becomes the single source of truth; `packages/ai-universal-rules/templates/**` and the three adapter projections (`.claude/agents`, `.opencode/agents`, `.github/agents`) become generated output only, never hand-edited.
- No provider-specific permission syntax may appear in the shared spec body; adapter-specific projection is a renderer responsibility, not a spec-authoring responsibility.
- The runtime policy gate must fail closed for unknown/unmatched commands and must not silently downgrade a deny to an allow across any adapter.
- Every production agent's Final Output must carry the structured contract (Scope used / Evidence / Changes / Verification / Risk / Next handoff) so an orchestrator can machine-check handoffs without re-parsing free prose.
- This plan is a record-only artifact: it authorizes no code changes. The Phase 1 implementation plan is a separate deliverable gated on an architect design pass (see Handoff Notes).

## Todo Plan

- [x] P0: Route this plan through architect to validate schema design against existing in-flight permission tickets (`arch-todo-agent-permission-rethink-20260613T154104Z`, `arch-todo-implementer-permission-profile-pilot-20260706-215934`, `arch-todo-implementer-permission-line-reduction-pilot-20260706-233000`, and the existing `tools/ai/install/permission-layers/` composition engine) before any Phase 1 implementation starts. This item gates every other item below. **DONE 2026-07-10:** architect design pass completed; reconciled Phase 1 persisted as `docs/tickets/claude-agent-fleet-remediation/plan-31-agentspec-reconciliation-phase1.md`. Key outcome: most proposed artifacts already exist (schemas in `schemas/ai/`, render/drift via plan-28, policy gate via `compile-command-policy.php`, context-pack, secret-scan); Phase 1 must EXTEND the existing composition engine, not build the net-new `packages/ai-contracts/` + `packages/ai-universal-rules/specs/**` layer (that violates the locked "no second SoT" decision). Four HUMAN-DECISION items surfaced in plan-31; **D1 (net-new specs layer vs extend existing engine) gates all downstream implementation and remains unresolved.**
- [ ] P0 (Phase 1 — Foundation): Design and land `packages/ai-contracts/schemas/{agent,skill,command,permission-profile,handoff,evidence}.schema.json`.
- [ ] P0 (Phase 1 — Foundation): Design `packages/ai-universal-rules/specs/{agents,skills,commands,permissions}/*.yml` as the AgentSpec source-of-truth layer that generates `packages/ai-universal-rules/templates/**`.
- [ ] P0 (Phase 1 — Foundation): Build `tools/ai/render-agents.php` and `tools/ai/validate-generated-drift.php` so every generated file carries a GENERATED/DO-NOT-EDIT header and manual edits fail the drift check.
- [ ] P0 (Phase 1 — Foundation): Build `tools/ai/validate-agents.php` to enforce one agent maps to exactly one source spec.
- [ ] P0 (Phase 2 — Safety/Permissions): Define reusable permission profiles (`readonly-research`, `readonly-review`, `scoped-edit`, `docs-edit`, `config-edit`, `unsafe-deny`, `release-guarded`), reconciled against the existing composition engine's profile/layer/pack model.
- [ ] P0 (Phase 2 — Safety/Permissions): Build `tools/ai/validate-permissions.php` to enforce read-only agents cannot edit and edit agents cannot touch denied paths.
- [ ] P0 (Phase 2 — Safety/Permissions): Build `tools/ai/policy-gate.php` (runtime policy gate: deny/require_approval/allow pattern list for destructive git, secret reads, `curl | sh`, etc.), wired via Claude PreToolUse hooks and OpenCode permissions.
- [ ] P0 (Phase 2 — Safety/Permissions): Design the Copilot CI/wrapper enforcement fallback (Copilot instructions are not a hard sandbox) and land `tools/ai/secret-scan` coverage plus `.github/workflows/copilot-setup-steps.yml` policy gates.
- [ ] P1 (Phase 3 — Context/hallucination-reduction): Design the evidence-first context contract (agents must cite evidence before edits; unknown facts marked `unknown`) and encode it in the `evidence.schema.json` + agent spec fields.
- [ ] P1 (Phase 3 — Context/hallucination-reduction): Build `tools/ai/eval-agents.php` and the golden eval harness under `tests/agent-evals/` (fixtures + `expected/*.yml`) covering scope refusal, minimal bugfix, review accuracy, unsafe-command block, adapter parity, hallucinated-fact refusal, secret-access denial, handoff scoring.
- [ ] P1 (Phase 3 — Context/hallucination-reduction): Define the context-pack token budget mechanism (`/context-pack` command, `scripts/ai/context-pack --task ... --max-tokens ...`).
- [ ] P1 (Phase 4 — Workflow intelligence): Land new workflow commands `/project-profile`, `/scope`, `/context-pack`, `/policy-audit`, `/agent-fleet-assess`, `/drift-check`, `/verify-change`, `/release-check`, `/secret-scan`, `/adapter-parity` as adapter-projected commands (Claude `.claude/commands`, OpenCode `.opencode/command`, Copilot `.github/prompts`).
- [ ] P1 (Phase 4 — Workflow intelligence): Add the structured output contract (Scope used / Evidence / Changes / Verification / Risk / Next handoff) to every production agent's Final Output, and build `tools/ai/score-handoffs.php` to score declared next-handoffs 0-100.
- [ ] P1 (Phase 4 — Workflow intelligence): Upgrade `agent-fleet-assessor` to a read-only orchestrator (`permission_profile: readonly-research`, `delegates_to: agent-critic`) that prefers deterministic validators before LLM judgement; build `scripts/ai/agent-fleet-assess --check`.
- [ ] P1 (Phase 4 — Workflow intelligence): Ensure the orchestrator layer cannot invoke an edit agent without a Scope Contract, and that blocked agents are reported as blocked, never "ready with fixes".
- [ ] P1 (Phase 5 — Copilot hardening): Land `.github/agents/*.agent.md`, `.github/copilot-instructions.md`, `.github/instructions/*.instructions.md`, `.github/prompts/*.prompt.md` hardening plus the CI policy-gate fallback proven in Phase 2.
- [ ] P1 (Phase 5 — Copilot hardening): Trim `AGENTS.md` to only stable operating rules (Build/Test/Verify, Scope confidence thresholds 70/90, Evidence discipline); move long procedures into `.agents/skills/**`.
- [ ] P1 (Phase 5 — Copilot hardening): Land portable skills under `.agents/skills/` (`scope-contract`, `evidence-search`, `safe-edit`, `test-selection`, `adapter-parity`, `release-audit`, `agent-assessment`, `prompt-injection-defense`) and confirm they render/copy to provider-native skill paths for Claude/OpenCode/Copilot.

## Acceptance Criteria

- [ ] AC-01: Every generated file carries a GENERATED/DO-NOT-EDIT header.
- [ ] AC-02: Every agent maps to exactly one source spec.
- [ ] AC-03: Manual edits to generated files fail the drift check.
- [ ] AC-04: Claude, OpenCode, and Copilot projections generate from the same spec.
- [ ] AC-05: No provider-specific permission syntax appears in the shared spec body.
- [ ] AC-06: Read-only agents cannot edit.
- [ ] AC-07: Edit agents cannot touch denied paths.
- [ ] AC-08: Bash defaults to deny/ask.
- [ ] AC-09: Destructive git commands are denied.
- [ ] AC-10: `.env`/secret files are denied.
- [ ] AC-11: Copilot has a CI/policy fallback (since Copilot instructions alone are not a hard sandbox).
- [ ] AC-12: Agents must cite evidence before edits.
- [ ] AC-13: Unknown facts are marked `unknown`.
- [ ] AC-14: The context pack has a token budget.
- [ ] AC-15: Every agent declares allowed next handoffs with a 0-100 score.
- [ ] AC-16: The orchestrator cannot invoke an edit agent without a Scope Contract.
- [ ] AC-17: Blocked agents are reported as blocked, never "ready with fixes".

## Verification Plan

- `scripts/ai/validate-agents` — proves AC-02 (one agent, one spec).
- `scripts/ai/render-agents --check` — proves AC-04 (identical spec-to-projection generation across adapters).
- `scripts/ai/validate-generated-drift` — proves AC-01 and AC-03 (headers present, hand-edits fail the gate).
- `scripts/ai/validate-permissions` — proves AC-06, AC-07 (read-only/edit boundary enforcement).
- `scripts/ai/policy-gate --fixture tests/policy/unsafe-commands.json` — proves AC-08, AC-09, AC-10 (bash deny/ask defaults, destructive git denied, secret files denied).
- `scripts/ai/secret-scan` — proves AC-10 and reinforces AC-11 (Copilot CI fallback surface).
- `scripts/ai/project-profile --check` — proves the project-profile command surfaces correct context for downstream evidence checks (supports AC-12).
- `scripts/ai/context-pack --task ... --max-tokens 12000` — proves AC-14 (token-budgeted context pack).
- `scripts/ai/eval-agents --suite hallucination` — proves AC-12, AC-13 (evidence citation, unknown marking) via golden eval fixtures.
- `scripts/ai/score-handoffs` — proves AC-15 (0-100 handoff scoring).
- `scripts/ai/agent-fleet-assess --check` — proves AC-16, AC-17 (orchestrator Scope Contract gating, blocked-status reporting).
- `scripts/ai/validate-workflow-graph` — proves AC-05 and cross-checks the whole spec-to-adapter graph for provider-specific leakage.

## Risks And Rollback

- The cross-adapter semantic gap (Copilot/Claude/OpenCode do not expose identical permission/runtime semantics) is structural and unavoidable — this is the ~6-point gap noted in Target Outcome. Mitigate via CI/hook enforcement, not by claiming false parity in the spec or in agent-facing prose.
- Big-bang schema migration risk against the 24 just-remediated agent templates (`arch-todo-agent-fleet-improvement-plans-20260707/plan.md`, completed 2026-07-07, 24/24 agents). Rollback plan: leave current hand/generator-maintained templates untouched until the renderer and drift checker are proven on one pilot agent first.
- Golden eval fixtures will false-positive fail if the spec/renderer output changes without updating `expected/*.yml` in the same change — any Phase 1 implementation must update fixtures atomically with renderer changes.
- **Prior-art overlap findings (checked before writing this plan, must be reconciled by the Phase 1 implementation plan, not re-litigated from scratch here):**
  - `docs/tickets/arch-todo-complete-permission-composition-migration/` — archived (`archive/DONE-plan.md`); an earlier permission-composition migration already completed. Confirms a composition engine already exists and previously reached a done state; this plan's "Permission Compiler" concept must be checked against what that migration already built before any new compiler is designed.
  - `docs/tickets/arch-todo-agent-permission-rethink-20260613T154104Z/plan.md` — locked architecture decisions (2026-06-13, HIGH risk) that: (1) reuse existing taxonomies (registry `risk`: read-only/mutating; policy `tier0..tier4`; `autonomy_level`) instead of inventing a new 6-value enum; (2) extend `tools/ai/install/script-registry.php` as the canonical tool registry and explicitly do NOT create a second `docs/ai/tool-registry.json` because it would be ~80% duplicate, citing the project's `>=75%` reuse rule; (3) reuse `aiRunScriptById` as the `tool:run` engine rather than building a new one; (4) keep a single `aiInstallerAgentProfiles()` agent-to-profile source of truth. **Direct conflict risk:** this new plan's `packages/ai-contracts/schemas/permission-profile.schema.json` and reusable permission-profile list must not duplicate that registry/profile map — the Phase 1 implementation plan must either extend the existing registry or get explicit architect sign-off to introduce a second source of truth.
  - `docs/tickets/arch-todo-implementer-permission-profile-pilot-20260706-215934/plan.md` — already piloted a three-axis authoring vocabulary (`capability_profile`, `permission_profile`, `command_profile`) as additive projections over the existing composed model, scoped to the `implementer` agent only, explicitly keeping the shipped OpenCode `permission:` block byte-identical. This is materially the same shape as this plan's "Reusable permission profiles" scope item; the naming and projection approach from this pilot should be reused rather than redesigned.
  - `docs/tickets/arch-todo-implementer-permission-line-reduction-pilot-20260706-233000/plan.md` — an equivalence-proof-gated attempt to collapse permission-pattern line count was HALTED after proving it would widen the allow surface (OpenCode globs support only `*`/`?`, no alternation, so a naive collapse grants unintended command forms). It records a superseding direction toward a sibling plan `docs/tickets/arch-todo-opencode-render-permission-from-composition-20260706-030000/plan.md` (found during this overlap check, not in the original list given), which strips the `permission:` block from template source files and keeps the full baked block only in generated `.opencode/agents/*.md`. Any Phase 1 renderer design in this plan should reuse that direction rather than re-deriving it, and must not repeat the halted wildcard-collapse approach.
  - `docs/tickets/arch-todo-agent-fleet-improvement-plans-20260707/plan.md` — the per-agent template remediation pass that just finished (24/24 agents, 2026-07-07). Confirms this new plan is correctly scoped as the next infrastructure layer up, not a re-run of per-template fixes; no scope conflict, but the Phase 1 implementation plan should not re-touch those 24 templates directly — it should generate equivalent or improved output through the new renderer once proven.

## Handoff Notes

- Recommended next step: `architect means architect agent handoff`. This plan is large and cross-cutting (schema design, permission semantics across three adapters, CI enforcement) and should get an architect design pass BEFORE architecture-plan-writer is invoked again to write the Phase 1 implementation-ready subtask plan.
- The architect pass should explicitly resolve the conflict/reuse risk flagged in Risks And Rollback against `arch-todo-agent-permission-rethink-20260613T154104Z`'s locked decisions (reuse `tools/ai/install/script-registry.php` and the existing composition engine; do not build a second parallel registry) before any `packages/ai-contracts/schemas/permission-profile.schema.json` design is finalized.
- The architect pass should also confirm whether `docs/tickets/arch-todo-opencode-render-permission-from-composition-20260706-030000/plan.md` (found during this overlap check) is still open/relevant, since its "strip permission block from template source, generate into `.opencode/agents/*.md`" direction overlaps with this plan's Phase 1 renderer goals.
