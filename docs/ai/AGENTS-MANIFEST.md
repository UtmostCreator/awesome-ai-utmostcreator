# Agent Role/Lifecycle Manifest

This manifest is the inventory of AI agent definitions across the two runtime
adapter surfaces, mirroring the role/risk classification model in
`scripts/ai/MANIFEST.md` (which covers scripts). It is classification-only:
agent filenames and IDs remain the stable contract, and no files are renamed by
this inventory.

The two adapter surfaces are NOT a 1:1 set. OpenCode agents live under
`.opencode/agents/<name>.md`; Copilot/GitHub agents live under
`.github/agents/<name>.agent.md`. Some agents exist on only one surface (see
"Surface coverage differences"). Canonical agent behavior and routing live in
`docs/ai/agents.md` and the capability docs; these adapter files stay thin per
`docs/ai/adapter-contract.md`.

Lifecycle labels below follow the event model proposed in the archived planning
doc `docs/tickets/archive/root-cleanup-20260614/todo-agents-rework.md`
(E00 intake .. E120 post-install) and are descriptive
classification only — they are not enforced runtime metadata. Risk reflects the
agent's mutation/gate posture, not its quality.

## Lifecycle groups

| Group | Purpose |
| --- | --- |
| `orchestration` | Decides sequence, prevents unsafe handoffs |
| `discovery` | Reads repo, gathers context, no mutation |
| `planning` | Creates scope contract, ACs, verification plan |
| `execution` | Performs scoped changes (mutating) |
| `validation` | Deterministic checks |
| `review` | Semantic/correctness review (gate) |
| `release` | Ship/no-ship decision (gate) |
| `agent-factory` | Creates and validates agents |
| `runtime-safety` | Tool and execution safety (gate) |

## Agent inventory

Mutating = the agent can edit repository files. Gate = the agent approves or
blocks other work. These columns mirror the `can_mutate` / `can_gate` intent in
`docs/tickets/archive/root-cleanup-20260614/todo-agents-rework.md` and
`docs/tickets/archive/root-cleanup-20260614/todo-agents-script-rework.md` (P6) without adding
enforced frontmatter.

| Agent | OpenCode | GitHub | Lifecycle | Mutating | Gate | Risk | Purpose |
| --- | :---: | :---: | --- | :---: | :---: | --- | --- |
| `agent-factory` | yes | yes | agent-factory | no | yes | medium | Staged agent-creation pipeline (reuse→spec→static validation→semantic fit→guardrails→human approval); produces specs, never runs the created agent. Merges the retired agent-creator + supervisor. |
| `architect` | yes | yes | planning | no | yes | high | Scoping, design, contract boundaries, risk posture. |
| `plan-writer` | yes | yes | planning | no | no | medium | Persists a bounded plan as a Todo markdown file under `docs/tickets`. |
| `researcher` | yes | yes | discovery | no | no | medium | Read-only grounding (scope, ownership, contracts, tests); folds the retired repository-researcher's script-first ai-search evidence discipline. |
| `implementer` | yes | yes | execution | yes | no | high | Bounded implementation slice with focused verification. |
| `super-implementer` | yes | no | execution | yes | no | high | Implementation slice variant (OpenCode-only). |
| `configuration-maintainer` | yes | yes | execution | yes | no | high | Editor/shell/runtime/tool config changes; absorbs the retired build-config's build/packaging/verification config scope via the `build-configuration` skill. |
| `agent-definition-reviewer` | yes | yes | review | no | yes | medium | Audits one agent instruction file for schema/role/permission fit, contradictions, handoffs, and token economy. |
| `fleet-assessor` | yes | yes | orchestration | no | yes | medium | Delegates each agent file to `agent-definition-reviewer`, then ranks the fleet 0-100 with fix priorities. |
| `orchestrator` | yes | yes | orchestration | no | yes | medium | Supervisor/coordinator: routes each stage of a task to the right delivery agent via the shared handoff contract; owns loop and failure control. Does not edit, design, or review. |
| `reviewer` | yes | yes | review | no | yes | high | Correctness, regression, policy, duplication, adapter-drift review. |
| `release-auditor` | yes | yes | release | no | yes | critical | Ship/no-ship decision for medium/high-risk changes. |
| `script-runner` | yes | no | execution | no | no | high | Runs only registered scripts/ai/*.sh wrappers with per-script risk gating; blocks ad hoc commands, chaining, and file edits (OpenCode-only). |
| `bootstrapper` | yes | no | orchestration | yes | no | high | INTERNAL kit-install orchestration (OpenCode-only). |

## Surface coverage differences

These agents exist on only one adapter surface. A complete inventory and any
coverage check must treat the two surfaces as distinct sets, not assume parity.

- OpenCode-only (`.opencode/agents/*.md`): `bootstrapper`, `script-runner`,
  `super-implementer`.
- GitHub-only (`.github/agents/*.agent.md`): none.

The shared set present on BOTH surfaces (11 agents): `agent-factory`,
`agent-definition-reviewer`, `fleet-assessor`, `orchestrator`, `architect`,
`plan-writer`, `configuration-maintainer`, `implementer`,
`release-auditor`, `researcher`, `reviewer`.

## Notes

- Lifecycle and risk columns are descriptive classification, not enforced
  runtime metadata. Adding enforced `agent_score` / lifecycle frontmatter is a
  separate, later phase (see `docs/tickets/arch-todo-remaining-rework-*`).
- Source of truth for live routing remains `docs/ai/agents.md`; this manifest is
  an inventory/classification aid, lower authority than canonical docs per
  `docs/ai/source-of-truth.md`.
