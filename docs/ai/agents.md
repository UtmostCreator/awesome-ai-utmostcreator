# AI Agents

Canonical guidance for installed AI agents. Keep runtime adapters thin and point them back to `docs/ai/` for policy, workflow, and verification rules.

## Live Agent Index

The following agents are live when their matching runtime adapter is installed. Runtime-specific adapter files define the role, tool boundary, and handoff contract while preserving the canonical guidance in this document.

Use this file to decide which agent to start with. For the exact shipped inventory, runtime surface differences, and risk classification, see `docs/ai/AGENTS-MANIFEST.md`.

<!-- Source-repo validator anchors: .github/agents/agent-definition-reviewer.agent.md .github/agents/agent-factory.agent.md .github/agents/architect.agent.md .github/agents/configuration-maintainer.agent.md .github/agents/fleet-assessor.agent.md .github/agents/implementer.agent.md .github/agents/orchestrator.agent.md .github/agents/plan-writer.agent.md .github/agents/release-auditor.agent.md .github/agents/researcher.agent.md .github/agents/reviewer.agent.md -->

<!-- markdownlint-disable MD060 -->
| Agent                     | Runtime key                 | When to use                                                                                                                                    |
| ------------------------- | --------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| Orchestrator              | `orchestrator`              | Coordinate a multi-step task by routing each stage to the right delivery agent through the shared handoff contract; owns loop and failure control. Does not edit, design, or review itself. |
| Researcher                | `researcher`                | Read-only repository grounding before planning, implementation, or review.                                                                     |
| Architect                 | `architect`                 | Scoping, design, ownership decisions, contract boundaries, adapter strategy, or risk posture before implementation.                            |
| Plan Writer               | `plan-writer`               | Persist a bounded architecture plan as a Todo markdown file under `docs/tickets/`; receives the architect handoff and never edits outside `docs/tickets/`. |
| Implementer               | `implementer`               | A bounded implementation slice is clear and focused verification should happen in this repository.                                             |
| Super Implementer         | `super-implementer`         | OpenCode-only: implementation slice variant with the same bounded-scope contract as Implementer.                                               |
| Configuration Maintainer  | `configuration-maintainer`  | Changing editor, shell, runtime, build, or tool configuration while preserving current behavior; absorbs the retired build-config via the `build-configuration` skill. |
| Reviewer                  | `reviewer`                  | Reviewing a change set for correctness, regressions, policy fit, duplication, adapter drift, and missing verification.                         |
| Release Auditor           | `release-auditor`           | Medium or high risk changes need rollout, rollback, migration, observability, preview, or install-safety review.                              |
| Agent Factory             | `agent-factory`             | Creating or materially changing an agent definition: decides reuse vs. create, produces a strict AgentSpec, runs static validation, checks semantic fit and guardrails, and holds for human approval. Produces specs, never runs the created agent. |
| Agent Definition Reviewer | `agent-definition-reviewer` | Audit one agent instruction file for schema, role, and permission fit, contradictions, handoffs, and token economy.                            |
| Fleet Assessor            | `fleet-assessor`            | Delegate each agent file to `agent-definition-reviewer`, then rank the fleet 0-100 with fix priorities.                                        |
| Script Runner             | `script-runner`             | OpenCode-only: run only registered `scripts/ai/*.sh` wrappers with per-script risk gating; blocks ad hoc commands, chaining, and file edits.   |
| UI Builder                | `ui-builder`                | Optional: build or modify user-interface components within a bounded slice, following existing product patterns and accessibility expectations. |
| Bootstrapper              | `bootstrapper`              | OpenCode-internal: installing or rehydrating the repository AI workflow surface. Not shipped to installed projects and has no Copilot adapter file. |
<!-- markdownlint-enable MD060 -->

## Which Agent To Start With

Use the first matching row. Start narrow, then hand off.

| If the request is mainly about... | Start with | Next handoff | Why |
| --------------------------------- | ---------- | ------------ | --- |
| coordinating a multi-step task and routing each stage to the right agent | `orchestrator` | delivery agents via the shared handoff contract | Central intake and loop/failure control without editing or reviewing itself. |
| understanding ownership, scope, contracts, tests, or current behavior before any edits | `researcher` | `architect` or `implementer` | Read-only evidence first; avoid guessed changes. |
| turning a broad task into a bounded plan with acceptance criteria and verification | `architect` | `plan-writer`, then `implementer` | Scopes the slice before mutation. |
| writing a Todo plan under `docs/tickets/` only | `plan-writer` | `implementer` | Persists the plan without widening into source edits. |
| applying a bounded code, config, or workflow change | `implementer` | `reviewer` | Smallest write-capable path with focused verification. |
| changing editor, shell, runtime, build, or tool configuration | `configuration-maintainer` | `reviewer` | Preserves behavior while changing configuration. |
| reviewing an existing diff for correctness or regression risk | `reviewer` | `implementer` if fixes are needed | Review starts from the actual diff, not from intent alone. |
| evaluating rollout, rollback, migration, or release safety | `release-auditor` | `implementer` or `architect` | Gate for medium/high-risk release posture. |
| creating or materially changing an agent definition | `agent-factory` | `agent-definition-reviewer` | Staged spec-first agent creation with human approval; never runs the created agent. |
| auditing one agent instruction file for schema, role, permission fit, or contradictions | `agent-definition-reviewer` | `implementer` if fixes are needed | Single-file agent-definition review gate. |
| ranking the whole agent fleet with fix priorities | `fleet-assessor` | `agent-definition-reviewer`, then `implementer` | Delegates per-agent review, then scores the fleet. |

## Common Handoffs

- `orchestrator` -> delivery agents: routes each stage of a multi-step task within the shared handoff contract.
- `researcher` -> `implementer`: when the change is already bounded and evidence is sufficient.
- `researcher` -> `architect`: when ownership, contracts, or scope are still unclear.
- `architect` -> `plan-writer` -> `implementer`: when the slice needs a persisted plan before edits.
- `implementer` -> `reviewer`: default path for non-trivial diffs.
- `implementer` -> `release-auditor`: add this when rollout or rollback risk matters.
- `fleet-assessor` -> `agent-definition-reviewer`: when auditing the agent fleet file by file.
- `agent-factory` -> `agent-definition-reviewer`: when a new or changed agent definition needs a review gate.

## Shipped Coverage By Profile Or Edition

Agent availability depends on the installed runtime adapter packs and optional agent packs.

| Profile or edition | Agent coverage |
| ------------------ | -------------- |
| `minimal` / `basic` | No runtime-specific agent files. You still get root-level `AGENTS.md`, but not the `.github/agents/` or `.opencode/agents/` adapter surfaces. |
| `copilot`, `opencode`, `dual`, `standard`, `agents-only` | Base runtime agents ship from `adapter-copilot` and/or `adapter-opencode`. This is the core roster used for normal research, planning, implementation, and review. |
| `creator`, `full`, `full-governance` | Base runtime agents plus the optional agent packs for both surfaces. Use these when you need the broader governance or agent-creation flows. |

Notes:

- `docs/ai/AGENTS-MANIFEST.md` is the authoritative inventory for exact agent names, lifecycle, risk, and surface coverage differences.
- The two runtime surfaces are intentionally not a 1:1 set; some agents are GitHub-only or OpenCode-only.
- `bootstrapper` is internal to this source repository and is not shipped to installed target projects.

## Routing Rules

- prefer the most specialized agent that matches the task
- use the Orchestrator when a task spans multiple stages and needs routing plus loop control
- pair the Architect with the Implementer for medium and high risk slices
- use the Researcher before mutating unfamiliar code
- use the Reviewer on every non-trivial diff
- use the Release Auditor when rollout, rollback, or compatibility risk applies
- use the Agent Factory, Agent Definition Reviewer, or Fleet Assessor when agent definitions themselves are changing

## Handoff Conventions

- every agent must produce evidence using approved scripts and report verification honestly
- always inspect `git status` and the active diff before editing
- never weaken tests, schemas, policies, or safety checks
- escalate to the human when scope grows beyond the planned slice

## Adapter Parity

Runtime adapter files must not introduce policy that disagrees with `docs/ai/` canonical guidance.

## Related Docs

- `docs/ai/workflow.md`
- `docs/ai/execution-protocol.md`
- `docs/ai/approval-boundaries.md`
- `docs/ai/adapter-contract.md`
