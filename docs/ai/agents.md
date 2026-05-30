# AI Agents

Canonical guidance for installed AI agents. Keep runtime adapters thin and point them back to `docs/ai/` for policy, workflow, and verification rules.

## Live Agent Index

The following agents are live when their matching runtime adapter is installed. Runtime-specific adapter files define the role, tool boundary, and handoff contract while preserving the canonical guidance in this document.

<!-- Source-repo validator anchors: .github/agents/architect.agent.md .github/agents/bootstrapper.agent.md .github/agents/config-maintainer.agent.md .github/agents/implementer.agent.md .github/agents/refactorer.agent.md .github/agents/release-auditor.agent.md .github/agents/repository-researcher.agent.md .github/agents/repository-reviewer.agent.md .github/agents/researcher.agent.md .github/agents/reviewer.agent.md .github/agents/workflow-auditor.agent.md -->

| Agent | Runtime key | When to use |
| --- | --- | --- |
| Architect | `architect` | Scoping, design, ownership decisions, contract boundaries, adapter strategy, or risk posture before implementation. |
| Bootstrapper | `bootstrapper` | Installing or rehydrating the repository AI workflow surface before normal research, implementation, or review flows begin. |
| Config Maintainer | `config-maintainer` | Changing editor, shell, runtime, or tool configuration while preserving current behavior. |
| Implementer | `implementer` | A bounded implementation slice is clear and focused verification should happen in this repository. |
| Refactorer | `refactorer` | Behavior is already correct and the remaining work is structure, readability, duplication reduction, or maintainability. |
| Release Auditor | `release-auditor` | Medium or high risk changes need rollout, rollback, migration, observability, preview, or install-safety review. |
| Repository Researcher | `repository-researcher` | Strict script-first repository researcher using ai-search before raw search. |
| Repository Reviewer | `repository-reviewer` | Strict script-first diff reviewer using ai-search and validator evidence. |
| Researcher | `researcher` | Read-only repository grounding before planning, implementation, or review. |
| Reviewer | `reviewer` | Reviewing a change set for correctness, regressions, policy fit, duplication, adapter drift, and missing verification. |
| Workflow Auditor | `workflow-auditor` | Reviewing AI workflow files, instruction drift, repo context drift, or unsupported workflow claims. |

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

Runtime adapter files must not introduce policy that disagrees with `docs/ai/` canonical guidance.

## Related Docs

- `docs/ai/workflow.md`
- `docs/ai/execution-protocol.md`
- `docs/ai/approval-boundaries.md`
- `docs/ai/adapter-contract.md`
