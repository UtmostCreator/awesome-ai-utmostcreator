# Install For GitHub Copilot

Use this guide for the smallest useful Copilot setup.

For the full operating model, read:

- `docs/workflows/SYSTEM-WORKFLOW.md`
- `docs/workflows/TASK-ENTRYPOINTS.md`
- `docs/foundations/COMPATIBILITY.md`

## Recommended Base Install

Start with the most stable primitives first:

- `templates/core/copilot-instructions.template.md` -> `.github/copilot-instructions.md`
- `templates/core/project-context.template.md` -> `docs/ai/project-context.md`
- `templates/shared/guardrails/AI-GUARDRAILS.md` -> `docs/ai/AI-GUARDRAILS.md`
- `templates/capabilities/project-context/` -> `docs/ai/capabilities/project-context/`
- `templates/capabilities/verify-change/` -> `docs/ai/capabilities/verify-change/`
- `templates/capabilities/review-diff/` -> `docs/ai/capabilities/review-diff/`
- `templates/instructions/architecture.instructions.md`
- `templates/instructions/testing.instructions.md`

Add other path instructions only when the repo truly needs them.

## Core Agent Set

If your Copilot surface supports custom agents reliably, start with only these four:

- `templates/core/agents/researcher.md`
- `templates/core/agents/architect.md`
- `templates/core/agents/implementer.md`
- `templates/core/agents/reviewer.md`

Recommended optional agents:

- `templates/core/agents/release-auditor.md` for `medium` or `high` risk work
- `templates/core/agents/refactorer.md` when behavior is already correct and only structure should change

Do not start with more agents than this.

## Core Prompt Set

If your surface supports prompt files, start with only:

- `templates/workflows/regression-test.md`
- `templates/workflows/release-safety.md`

Recommended optional prompt:

- `templates/workflows/docs-sync.md`

Do not make prompt files the required base install.

## Setup Steps

1. Copy repo-wide instructions, project context, guardrails, and base capabilities first.
2. Add only the path-specific instructions you can justify.
3. Add the core four agents only if your target Copilot surface supports them well.
4. Add at most two prompts to start.
5. Replace placeholders across all copied files.
6. Search for unresolved `<PLACEHOLDER_NAME>` tokens and project-specific leaks.

## Clear Choice Model

- unclear area -> `researcher`
- multi-step or risky task -> `architect`
- bounded implementation -> `implementer`
- diff audit or correctness check -> `reviewer`
- rollout-sensitive change -> optional `release-auditor`
- structure-only cleanup -> optional `refactorer`
- narrow one-off proving task -> `regression-test`
- release posture question -> `release-readiness`

## Surface Guidance

### VS Code Or CLI

- safest base: repo instructions + path instructions + capabilities
- agents: add only the compact core set
- prompt files: optional and surface-dependent

### GitHub.com

- safest base: repo instructions + `AGENTS.md` + capability folders
- agents may behave differently than in local IDEs
- prompt-file workflows should not be your default assumption

## Anti-Pattern To Avoid

Do not solve every workflow problem by adding more prompt files or more agents.

## After Install

- `ONBOARDING.md` — what to customize and in what order
- `../PLACEHOLDERS.md` — placeholder reference for all copied templates
- `../../docs/ai/POST-INSTALL.md` — post-install checklist and commands

## See Also

- `INSTALL-CATALOG.md` — full profile and pack index
- `INSTALL-OPENCODE.md` — OpenCode runtime install guide
- `../../docs/ai/install-order.md` — full ordered command flow and selective pack recipes
- `../../docs/ai/external-repo-install.md` — external repository install examples
- `workflows/SYSTEM-WORKFLOW.md` — end-to-end operating model
- `workflows/TASK-ENTRYPOINTS.md` — when to use each mechanism
- `foundations/COMPATIBILITY.md` — surface limits and fallbacks
