# AI Universal Rules

Install-and-go AI workflow infrastructure for OpenCode and GitHub Copilot.

Machine-readable and generated package outputs:

- `manifest.json` - schema-backed package manifest
- `catalog.json` - generated resource index
- `docs/BROWSE.md` - generated browse surface

This package is no longer just a template bundle for instructions. It is a layered operating model for repo-scoped AI work:

- always-on policy
- durable project context
- task entry points
- staged agents and handoffs
- capability and skill packs
- deterministic enforcement and runtime inspection

The kit is intentionally asymmetric. It preserves one canonical workflow model, then adapts it to each runtime without pretending every surface exposes the same controls.

## Who This Is For

- teams setting up durable AI workflow infrastructure in a repository
- individuals sharing a reusable cross-tool starter kit
- repos that want one policy and capability model with different runtime adapters

## What Is Included

- core policy templates
- durable project-context templates
- capability folders as the canonical workflow layer
- OpenCode adapters for agents, commands, and skills
- GitHub Copilot adapters for instructions, agents, prompt files, and project skills where supported
- shared templates for guardrails, verification evidence, and approval packets
- workflow docs for routing, handoffs, approvals, observability, and monorepos
- operations docs for governance, hooks, MCP boundaries, maintenance, and troubleshooting

## Six-Layer Model

1. `instructions`: stable policy, defaults, architecture, security boundaries, verification expectations
2. `project-context`: durable repository facts and active owners
3. `prompt files / commands`: task entry points for recurring one-off jobs
4. `agents / subagents`: staged execution roles with tool and scope boundaries
5. `capabilities / skills`: deep optional workflow packs loaded when relevant
6. `hooks / MCP / runtime debug`: enforcement, bounded external access, and observability

## Start Small

Minimum base install for most repositories:

- `docs/ai/project-context.md`
- `docs/ai/capabilities/project-context/`
- `docs/ai/capabilities/verify-change/`
- `docs/ai/capabilities/review-diff/`
- one runtime adapter surface first: OpenCode or GitHub Copilot

Add `bug-regression`, `release-safety`, and `dependency-upgrade` only when the repository actually needs them.

Installer-first adoption is the supported path in this source repository:

- use `../../docs/ai/install-order.md` for the real command order and selective pack recipes
- use `../../docs/ai/external-repo-install.md` for external target examples and overwrite rules
- use `docs/INSTALL-CATALOG.md` for the generated profile and pack index

For non-trivial changes, use the trimmed risk model:

- `low`
- `medium`
- `high`

For `medium` and `high` risk changes, define rollback plan, observability signal, and feature-flag posture before implementation.

## Recommended Flow

1. classify the request and risk level
2. load stable policy and project context
3. choose the smallest fitting entry point
4. load the relevant capability or skill pack
5. delegate to staged agents only when isolation helps
6. verify with evidence, not claims
7. inspect runtime state when behavior seems off
8. escalate destructive or high-impact changes for approval

## Supported Targets

- OpenCode
- GitHub Copilot in VS Code or CLI
- GitHub Copilot on GitHub.com with reduced workflow surface

See `docs/foundations/COMPATIBILITY.md` for limits and fallbacks.

## Folder Map

- `templates/core/`: baseline policy, workflow, and durable context templates
- `templates/capabilities/`: canonical reusable workflow packs
- `templates/shared/`: cross-tool guardrail, verification, and approval templates
- `templates/instructions/`: canonical Copilot instruction sources
- `templates/workflows/`: shared workflow sources adapted into prompts, commands, and skills during installation
- `templates/commands/`: explicit OpenCode command wrappers that remain separate from shared workflows
- `templates/optional/agents/`: shared optional agent sources adapted per runtime during installation
- `templates/optional/`: specialist add-ons
- `docs/foundations/`: principles, precedence, compatibility, and control model
- `docs/workflows/`: task routing, handoffs, approvals, monorepos, observability
- `docs/operations/`: governance, enforcement, MCP boundaries, maintenance, troubleshooting, evals

## Read In This Order

1. `QUICKSTART.md`
2. `docs/ONBOARDING.md`
3. `docs/workflows/SYSTEM-WORKFLOW.md`
4. `docs/workflows/TASK-ENTRYPOINTS.md`
5. `docs/foundations/CAPABILITY-MODEL.md`
6. `docs/operations/GOVERNANCE.md`
7. `docs/foundations/SKILLS.md`
8. `docs/RELEASE-BUNDLES.md`

## Runtime-Specific Install Guides

- `docs/INSTALL-GITHUB-COPILOT.md` — GitHub Copilot setup
- `docs/INSTALL-OPENCODE.md` — OpenCode setup
- `docs/INSTALL-CATALOG.md` — full profile and pack index

## Important Design Rules

- Do not treat instructions as the workflow itself.
- Keep always-on files short and policy-focused.
- Put repeatable one-off jobs behind prompt files or commands.
- Use staged agents for isolation, not just persona flavor.
- Treat capabilities as the canonical source of reusable procedure.
- Treat skills as runtime adapters over canonical capability workflows.
- Treat hooks as enforcement and instructions as advisory.
- Document surface mismatch explicitly instead of implying parity.
- Follow `docs/ai/ai-file-standards.md` in installed repositories and `templates/core/ai-file-standards.template.md` in this package when adding agents, instructions, prompts, commands, skills, or capabilities.

## Primitive Boundaries

| File family | Best use | Keep out |
| --- | --- | --- |
| Instructions | stable repo or path rules | task workflows and role personas |
| Agents | persistent role, tool or permission boundary, handoff | full capability examples |
| Prompts | one-shot Copilot task launchers | durable policy and permission models |
| Commands | short OpenCode slash-command wrappers | long procedures |
| Skills | runtime-loaded capability adapters | project-wide rules |
| Capabilities | canonical reusable procedure | provider-specific syntax |

Line budgets and hard limits are installed through `docs/ai/ai-file-standards.md`; validation should fail hard-limit violations unless a file is generated or allowlisted.

## Important Note About Copilot Prompts

Prompt files are first-class task entry points in this kit, but support still varies by surface and enablement state. Treat prompt files as optional adapters with a documented fallback path to repository instructions, capabilities, and agents.

## Current Maturity Target

This package aims for a production-grade workflow benchmark rather than a file-placement starter:

- policy separated from procedure
- staged handoffs available
- deterministic enforcement documented
- runtime inspection included
- example repos show end-to-end usage

If you are new to the kit, start with `QUICKSTART.md` and `docs/workflows/SYSTEM-WORKFLOW.md`.

## Validation And Packaging

Use the root scripts when package metadata or generated outputs change:

- `php tools/ai/validate-ai-catalog.php`
- `php tools/ai/generate-ai-catalog.php`
- `php tools/ai/export-ai-universal-rules.php --profile=dual-runtime-starter`
