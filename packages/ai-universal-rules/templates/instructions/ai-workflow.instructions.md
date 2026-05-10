---
applyTo: 'AGENTS.md,CLAUDE.md,README.md,docs/ai/**,.github/copilot-instructions.md,.github/agents/**,.github/instructions/**,.github/prompts/**,.github/skills/**'
description: 'Rules for AI workflow docs, Copilot adapter files, and stronger VS Code enforcement'
---

# AI Workflow Rules

- Keep canonical workflow guidance in `docs/ai/` and adapter-specific guidance in runtime files.
- Do not let `.github/` disagree with `AGENTS.md`, `CLAUDE.md`, `docs/ai/project-context.md`, or `docs/ai/workflow.md`.
- Prefer portable concepts first: policy, project context, capabilities, verification, approval boundaries.
- Do not rely on Markdown links being auto-loaded; repeat critical routing in always-on instructions, agent files, and task entrypoints.
- Mention runtime limitations explicitly instead of implying feature parity.
- Keep always-on instruction files short and durable.
- Keep live agent behavior documented in `docs/ai/agents.md`.
- Keep command failure logging, retry policy, and corrected usage guidance documented in `docs/ai/failure-handling.md`.
- State explicitly that safe repo-local read-only commands are approval-free by default.
- Route planning, editing, and review through `.github/instructions/context-gate.instructions.md` before mutation.
- Keep `.github/instructions/approval-boundaries.instructions.md`, `.github/instructions/generated-artifacts.instructions.md`, and `.github/instructions/security.instructions.md` aligned with the canonical docs they summarize.
- For mutating or high-risk tool use, route through `docs/ai/capabilities/authorization-and-tool-governance/CAPABILITY.md` before execution.
- For medium or high-risk agentic work, route through `docs/ai/capabilities/agent-observability-and-evidence/CAPABILITY.md` so outputs include traceable evidence fields when supported.
- For behavior-changing agentic work, route through `docs/ai/capabilities/evaluation-and-regression/CAPABILITY.md` and include regression evidence in task output.
- For medium/high-risk changes requiring temporary environment validation, route through `docs/ai/capabilities/preview-environments/CAPABILITY.md` and include lifecycle/cleanup evidence in task output.
- Route broad edits through `scripts/ai/ai-edit.sh` instead of raw shell replacement commands when the Copilot tool layer can handle the change.
- Keep the Copilot hook policy aligned with `policies/copilot/policy.yaml`, `scripts/ai/pre-tool-use.sh`, `scripts/ai/post-tool-use.sh`, `docs/ai/script-registry.md`, and `docs/ai/script-registry.json`.
- Treat `scripts/ai/pre-tool-use.sh` as the canonical pre-execution gate and `scripts/ai/post-tool-use.sh` as the canonical post-execution evidence writer; when the runtime supports repository hooks, they must remain authoritative through `.github/hooks/tool-policy.json`, and when it does not, the same boundary still applies without claiming automatic enforcement.
- Treat `.ai-logs/README.md` as the canonical checked-in contract for local evidence logs on supported shell-hook surfaces.
- Treat `.agent.md` tool lists as hard upper bounds, not suggestions.
- Prompt files must not widen the selected agent tool surface.
- For VS Code surfaces, keep `.vscode/settings.json` aligned with the repo hook and script registry so terminal auto-approval and sandbox rules reinforce the same boundary.
