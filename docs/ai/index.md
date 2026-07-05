# docs/ai Index

Curated, hand-maintained entry point into `docs/ai/**`, with a one-line "why it
matters" annotation per doc. This is not the generated asset inventory — see
`docs/ai/catalog.md` for the exhaustive generated list of agents, skills,
prompts, and scripts.

## Start Here

- `project-context.md` — canonical durable project facts, scope, and source-of-truth ranking.
- `workflow.md` — default task flow and entry-point routing into capabilities.
- `execution-protocol.md` — required sequence, verification statuses, and the terminal signal line.

## Source Of Truth & Contracts

- `source-of-truth.md` — file-precedence order when docs and code disagree.
- `adapter-contract.md` — rules for keeping Copilot/OpenCode/Claude adapters thin and non-conflicting.
- `ai-file-standards.md` — size and role budgets for every AI workflow file type.
- `ownership.md` — who owns which paths and how ownership questions get resolved.
- `schema-ownership.md` — canonical owner and change process for each schema under `schemas/`.

## Execution, Verification & Failure Handling

- `execution-protocol.md` — see Start Here; also defines the terminal `signal:` line.
- `verification-matrix.md` — which verification depth applies to which change type.
- `validation.md` — validator commands to run after installer or config changes.
- `failure-handling.md` — how to report blocked, failed, or partial work.
- `handoff-contract.md` — required shape of a handoff between agents.
- `approval-boundaries.md` — operations that must stop and ask before proceeding.
- `command-risk-taxonomy.md` — how to classify a command's risk before running it.
- `command-policy.md` — allow/ask/deny posture for repository commands.
- `tool-policy.md` — which tool surfaces are preferred and when extra approval is required.

## Capabilities

- See `docs/ai/capabilities/README.md` for the task-to-capability router; capability
  folders are not re-listed here to avoid duplicating that index.

## Generated Artifacts & Catalog

- `catalog.md` — generated, exhaustive inventory of agents, skills, prompts, and scripts. Do not hand-edit.
- `generated-artifacts.md` — rules for reading, regenerating, or deleting anything under `docs/ai/generated/`.
- `AGENTS-MANIFEST.md` — generated manifest of installed agent surfaces.
- `installed-files.md` — generated list of files placed by the installer.
- `available-packs.md` — generated catalog of installable packs.
- `shipped-surface-inventory.md` — per-file role/load-path companion to the integration matrix.

## Agents & Operations

- `agents.md` — live agent routing and role summaries.
- `agent-ops.md` — day-to-day agent operating notes.
- `agent-ops-checklist.md` — checklist for agent operations and compatibility checks.
- `agent-script-access.md` — per-agent allow/ask/deny script permissions.
- `integration-matrix.md` — cross-runtime compatibility and semantic-parity review methodology.

## Project Setup & Install

- `SETUP.md` — first-run setup steps for this kit.
- `POST-INSTALL.md` — steps to run immediately after installation.
- `project-configuration.md` — how `.ai/project.yml` drives rendered output.
- `project-context-placeholders.md` — placeholders to resolve in `project-context.md`.
- `project-stack.md` — detected or declared project stack facts.
- `mandatory-tools-install.md` — tools that must be present before AI workflows run.
- `toolchain-requirements.md` — required toolchain versions.
- `repo-required-tools.md` — repo-specific required tooling beyond the base toolchain.

## Security & Guardrails

- `AI-GUARDRAILS.md` — hard safety limits for AI-assisted changes.
- `security.md` — security posture and reporting expectations.
- `MCP-BOUNDARIES.md` — boundaries for MCP tool/server usage.

## Reference

- `context-economy.md` — keeping loaded context small and relevant.
- `context-packing.md` — how context is assembled for agents.
- `non-technical-overview.md` — plain-language summary of this kit for non-engineers.
- `maintainer-guide.md` — guide for maintaining the kit itself.
- `maintenance-mode.md` — reduced-scope behavior during maintenance windows.
- `hooks.md` — pre/post-tool-use hook behavior.
- `opencode-models.md` — model selection notes for OpenCode.
- `copilot-getting-started.md` — onboarding steps for Copilot users.
- `copilot-tooling.md` — Copilot-specific tool surface notes.
- `copilot-cli-repo-integration.md` — Copilot CLI integration with this repo.
- `repo-documentation-generation.md` — how repo documentation is generated.
- `script-registry.md` — human-readable script registry.
- `scripts-reference.md` — reference for `scripts/ai/**` wrappers.
- `external-repo-install.md` — installing this kit into another repository.
- `session-reentry.md` — resuming work in a new session safely.
- `architecture-locks.md` — architecture decisions treated as locked.
