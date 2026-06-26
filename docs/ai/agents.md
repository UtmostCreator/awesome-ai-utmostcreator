# AI Agents

Canonical guidance for installed AI agents. Keep runtime adapters thin and point them back to `docs/ai/` for policy, workflow, and verification rules.

## Live Agent Index

The following agents are live when their matching runtime adapter is installed. Runtime-specific adapter files define the role, tool boundary, and handoff contract while preserving the canonical guidance in this document.

Use this file to decide which agent to start with. For the exact shipped inventory, runtime surface differences, and risk classification, see `docs/ai/AGENTS-MANIFEST.md`.

<!-- Source-repo validator anchors: .github/agents/agent-creator-runtime-guardian.agent.md .github/agents/agent-creator-semantic-verifier.agent.md .github/agents/agent-creator-static-validator.agent.md .github/agents/agent-creator-supervisor.agent.md .github/agents/agent-creator.agent.md .github/agents/architect.agent.md .github/agents/architecture-plan-writer.agent.md .github/agents/bootstrapper.agent.md .github/agents/bugfix.agent.md .github/agents/build-config.agent.md .github/agents/config-maintainer.agent.md .github/agents/docs.agent.md .github/agents/implementer.agent.md .github/agents/infra-auditor.agent.md .github/agents/post-install.agent.md .github/agents/refactorer.agent.md .github/agents/release-auditor.agent.md .github/agents/repository-researcher.agent.md .github/agents/repository-reviewer.agent.md .github/agents/researcher.agent.md .github/agents/reviewer.agent.md .github/agents/upgrade.agent.md .github/agents/workflow-auditor.agent.md -->

<!-- markdownlint-disable MD060 -->
| Agent                    | Runtime key                | When to use                                                                                                                                    |
| ------------------------ | -------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| Architect                | `architect`                | Scoping, design, ownership decisions, contract boundaries, adapter strategy, or risk posture before implementation.                            |
| Architecture Plan Writer | `architecture-plan-writer` | Persist a bounded architecture plan as a Todo markdown file under `docs/tickets/`; receives the architect handoff and never edits outside `docs/tickets/`. |
| Bootstrapper             | `bootstrapper`             | OpenCode-internal: installing or rehydrating the repository AI workflow surface. Not shipped to installed projects and has no Copilot adapter file. |
| Config Maintainer        | `config-maintainer`        | Changing editor, shell, runtime, or tool configuration while preserving current behavior.                                                      |
| Implementer              | `implementer`              | A bounded implementation slice is clear and focused verification should happen in this repository.                                             |
| Refactorer               | `refactorer`               | Behavior is already correct and the remaining work is structure, readability, duplication reduction, or maintainability.                       |
| Release Auditor          | `release-auditor`          | Medium or high risk changes need rollout, rollback, migration, observability, preview, or install-safety review.                              |
| Repository Researcher    | `repository-researcher`    | Strict script-first repository researcher using ai-search before raw search.                                                                   |
| Repository Reviewer      | `repository-reviewer`      | Strict script-first diff reviewer using ai-search and validator evidence.                                                                      |
| Researcher               | `researcher`               | Read-only repository grounding before planning, implementation, or review.                                                                     |
| Reviewer                 | `reviewer`                 | Reviewing a change set for correctness, regressions, policy fit, duplication, adapter drift, and missing verification.                         |
| Workflow Auditor         | `workflow-auditor`         | Reviewing AI workflow files, instruction drift, repo context drift, or unsupported workflow claims.                                            |
<!-- markdownlint-enable MD060 -->

## Which Agent To Start With

Use the first matching row. Start narrow, then hand off.

| If the request is mainly about... | Start with | Next handoff | Why |
| --------------------------------- | ---------- | ------------ | --- |
| understanding ownership, scope, contracts, tests, or current behavior before any edits | `researcher` or `repository-researcher` | `architect` or `implementer` | Read-only evidence first; avoid guessed changes. |
| turning a broad task into a bounded plan with acceptance criteria and verification | `architect` | `architecture-plan-writer`, then `implementer` | Scopes the slice before mutation. |
| writing a Todo plan under `docs/tickets/` only | `architecture-plan-writer` | `implementer` | Persists the plan without widening into source edits. |
| applying a bounded code, config, or workflow change | `implementer` | `reviewer` | Smallest write-capable path with focused verification. |
| fixing a reported bug with minimal change | `bugfix` | `reviewer` | Bug-focused reproduction and fix flow. |
| improving structure without changing behavior | `refactorer` | `reviewer` | Keeps refactors separate from feature or bug work. |
| changing editor, shell, runtime, or tool configuration | `config-maintainer` | `reviewer` | Preserves behavior while changing configuration. |
| aligning docs after behavior is already settled | `docs` | `reviewer` when a paired implementation diff exists | Keeps doc sync separate from implementation planning. |
| reviewing an existing diff for correctness or regression risk | `reviewer` or `repository-reviewer` | `implementer` if fixes are needed | Review starts from the actual diff, not from intent alone. |
| auditing AI workflow docs, repo-context drift, or unsupported workflow claims | `workflow-auditor` | `implementer` or `architect` | Specialized drift and policy review. |
| evaluating rollout, rollback, migration, or release safety | `release-auditor` | `implementer` or `architect` | Gate for medium/high-risk release posture. |

## Common Handoffs

- `researcher` -> `implementer`: when the change is already bounded and evidence is sufficient.
- `researcher` -> `architect`: when ownership, contracts, or scope are still unclear.
- `architect` -> `architecture-plan-writer` -> `implementer`: when the slice needs a persisted plan before edits.
- `implementer` -> `reviewer`: default path for non-trivial diffs.
- `implementer` -> `release-auditor`: add this when rollout or rollback risk matters.
- `workflow-auditor` -> `implementer`: when the issue is documentation or adapter drift rather than code logic.

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
