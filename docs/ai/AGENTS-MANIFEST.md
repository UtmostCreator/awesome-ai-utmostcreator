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
| `post-install` | Install/drift verification |
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
| `agent-creator` | yes | yes | agent-factory | no | no | high | Turns an approved brief into a strict AgentSpec; never self-approves. |
| `agent-creator-static-validator` | yes | yes | agent-factory | no | yes | medium | Deterministic AgentSpec static validation. |
| `agent-creator-semantic-verifier` | yes | yes | agent-factory | no | yes | medium | Judges whether a valid AgentSpec matches the request. |
| `agent-creator-runtime-guardian` | yes | yes | runtime-safety | no | yes | critical | Enforces input/tool-call/output guardrails and stop conditions. |
| `agent-creator-supervisor` | yes | yes | orchestration | no | yes | high | Routes the agent-factory pipeline; blocks unsafe handoffs. |
| `architect` | yes | yes | planning | no | yes | high | Scoping, design, contract boundaries, risk posture. |
| `architecture-plan-writer` | yes | yes | planning | no | no | medium | Persists a bounded plan as a Todo markdown file under `docs/tickets`. |
| `repository-researcher` | yes | yes | discovery | no | no | low | Script-first read-only repository research. |
| `researcher` | yes | yes | discovery | no | no | medium | Read-only grounding (scope, ownership, contracts, tests). |
| `repository-reviewer` | yes | yes | discovery / review | no | yes | medium | Script-first diff review using ai-search + validator evidence. |
| `implementer` | yes | yes | execution | yes | no | high | Bounded implementation slice with focused verification. |
| `super-implementer` | yes | no | execution | yes | no | high | Implementation slice variant (OpenCode-only). |
| `bugfix` | no | yes | execution | yes | no | medium | Reproduce, isolate, fix, verify a bug (GitHub-only). |
| `refactorer` | yes | yes | execution | yes | no | high | Structure/readability improvement; tightly path-scoped. |
| `config-maintainer` | yes | yes | execution | yes | no | high | Editor/shell/runtime/tool config changes. |
| `build-config` | no | yes | execution | yes | no | high | Build/packaging/verification config edits (GitHub-only). |
| `upgrade` | no | yes | execution | yes | no | critical | Dependency/platform upgrades; strongest gates (GitHub-only). |
| `docs` | no | yes | execution / release | yes | no | low | Documentation alignment after verified behavior (GitHub-only). |
| `workflow-auditor` | yes | yes | validation | no | yes | high | AI workflow/instruction/repo-context drift checks. |
| `infra-auditor` | no | yes | validation | no | yes | high | Dependency/build/release/compatibility risk audit (GitHub-only). |
| `agent-critic` | yes | yes | review | no | yes | medium | Audits one agent instruction file for schema/role/permission fit, contradictions, handoffs, and token economy. |
| `agent-fleet-assessor` | yes | yes | orchestration | no | yes | medium | Delegates each agent file to `agent-critic`, then ranks the fleet 0-100 with fix priorities. |
| `reviewer` | yes | yes | review | no | yes | high | Correctness, regression, policy, duplication, adapter-drift review. |
| `release-auditor` | yes | yes | release | no | yes | critical | Ship/no-ship decision for medium/high-risk changes. |
| `post-install` | yes | yes | post-install | no | yes | high | Placeholder cleanup, install/drift verification after kit install. |
| `script-runner` | yes | no | execution | no | no | high | Runs only registered scripts/ai/*.sh wrappers with per-script risk gating; blocks ad hoc commands, chaining, and file edits (OpenCode-only). |
| `bootstrapper` | yes | no | orchestration | yes | no | high | INTERNAL kit-install orchestration (OpenCode-only). |

## Surface coverage differences

These agents exist on only one adapter surface. A complete inventory and any
coverage check must treat the two surfaces as distinct sets, not assume parity.

- OpenCode-only (`.opencode/agents/*.md`): `bootstrapper`, `script-runner`,
  `super-implementer`.
- GitHub-only (`.github/agents/*.agent.md`): `bugfix`, `build-config`, `docs`,
  `infra-auditor`, `upgrade`.

The shared set present on BOTH surfaces (19 agents): `agent-creator`,
`agent-creator-runtime-guardian`, `agent-creator-semantic-verifier`,
`agent-creator-static-validator`, `agent-creator-supervisor`, `agent-critic`,
`agent-fleet-assessor`, `architect`, `architecture-plan-writer`,
`config-maintainer`, `implementer`, `post-install`, `refactorer`,
`release-auditor`, `repository-researcher`, `repository-reviewer`, `researcher`,
`reviewer`, `workflow-auditor`.

## Notes

- Lifecycle and risk columns are descriptive classification, not enforced
  runtime metadata. Adding enforced `agent_score` / lifecycle frontmatter is a
  separate, later phase (see `docs/tickets/arch-todo-remaining-rework-*`).
- Source of truth for live routing remains `docs/ai/agents.md`; this manifest is
  an inventory/classification aid, lower authority than canonical docs per
  `docs/ai/source-of-truth.md`.
