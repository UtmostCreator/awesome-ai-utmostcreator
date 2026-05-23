# AI Agents

Canonical guidance for installed AI agents. Keep runtime adapters thin and point them back to `docs/ai/` for policy, workflow, and verification rules.

## Live Agent Index

The following agents are live in this repository. Each `.github/agents/*.agent.md` file defines the persistent Copilot role, tool boundary, and handoff contract. Mirror surfaces under `.opencode/agents/` provide equivalent OpenCode definitions.

| Agent | GitHub Copilot path | When to use |
| --- | --- | --- |
| Architect | `.github/agents/architect.agent.md` | Scoping, design, ownership decisions, contract boundaries, adapter strategy, or risk posture before implementation. |
| Bootstrapper | `.github/agents/bootstrapper.agent.md` | Internal AI kit installation workflow from dry-run through apply and validation for this repository. |
| Config Maintainer | `.github/agents/config-maintainer.agent.md` | Changing editor, shell, runtime, or tool configuration while preserving current behavior. |
| Implementer | `.github/agents/implementer.agent.md` | A bounded implementation slice is clear and focused verification should happen in this repository. |
| Refactorer | `.github/agents/refactorer.agent.md` | Behavior is already correct and the remaining work is structure, readability, duplication reduction, or maintainability. |
| Release Auditor | `.github/agents/release-auditor.agent.md` | Medium or high risk changes need rollout, rollback, migration, observability, preview, or install-safety review. |
| Repository Researcher | `.github/agents/repository-researcher.agent.md` | Strict script-first repository researcher using ai-search before raw search. |
| Repository Reviewer | `.github/agents/repository-reviewer.agent.md` | Strict script-first diff reviewer using ai-search and validator evidence. |
| Researcher | `.github/agents/researcher.agent.md` | Read-only repository grounding before planning, implementation, or review. |
| Reviewer | `.github/agents/reviewer.agent.md` | Reviewing a change set for correctness, regressions, policy fit, duplication, adapter drift, and missing verification. |
| Workflow Auditor | `.github/agents/workflow-auditor.agent.md` | Reviewing AI workflow files, instruction drift, repo context drift, or unsupported workflow claims. |

## Routing Rules

- prefer the most specialized agent that matches the task
- pair the Architect with the Implementer for medium and high risk slices
- use the Repository Researcher or Researcher before mutating unfamiliar code
- use the Reviewer or Repository Reviewer on every non-trivial diff
- use the Release Auditor when rollout, rollback, or compatibility risk applies
- use the Workflow Auditor when AI workflow files themselves are changing

## Handoff Conventions

- every agent must produce evidence using approved scripts and report verification honestly
- always inspect `git status` and the active diff before editing
- never weaken tests, schemas, policies, or safety checks
- escalate to the human when scope grows beyond the planned slice

## Adapter Parity

OpenCode mirrors live under `.opencode/agents/` with equivalent roles. Adapter files must not introduce policy that disagrees with `docs/ai/` canonical guidance.

## Related Docs

- `docs/ai/workflow.md`
- `docs/ai/execution-protocol.md`
- `docs/ai/approval-boundaries.md`
- `docs/ai/adapter-contract.md`
