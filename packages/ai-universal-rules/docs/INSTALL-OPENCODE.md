# Install For OpenCode

Use this guide for the smallest useful OpenCode setup.

For the full operating model, read:

- `docs/workflows/SYSTEM-WORKFLOW.md`
- `docs/workflows/AGENT-HANDOFFS.md`
- `docs/operations/GOVERNANCE.md`

## Recommended Base Install

Copy these files into your target repository:

- `templates/core/AGENTS.template.md` -> `AGENTS.md`
- `templates/core/project-context.template.md` -> `docs/ai/project-context.md`
- `templates/shared/guardrails/AI-GUARDRAILS.md` -> `docs/ai/AI-GUARDRAILS.md`
- `templates/capabilities/project-context/` -> `docs/ai/capabilities/project-context/`
- `templates/capabilities/verify-change/` -> `docs/ai/capabilities/verify-change/`
- `templates/capabilities/review-diff/` -> `docs/ai/capabilities/review-diff/`

## Core Agent Set

Start with only these four agents:

- `templates/core/agents/researcher.md`
- `templates/core/agents/architect.md`
- `templates/core/agents/implementer.md`
- `templates/core/agents/reviewer.md`

Recommended optional agents:

- `templates/core/agents/release-auditor.md` for `medium` or `high` risk work
- `templates/core/agents/refactorer.md` when behavior is already correct and the remaining work is structural cleanup

Do not start with specialist agents beyond this set.

## Core Entry Points

Start with only these entry points:

- `templates/workflows/plan-slice.md`
- `templates/workflows/bug-regression.md`

Optional legacy helpers if you want direct command wrappers for narrower tasks:

- `templates/workflows/review-diff.md`
- `templates/commands/verify.md`

## Core Skills

Start with only these skill sources:

- `templates/workflows/project-context.md`
- `templates/workflows/verify-change.md`
- `templates/workflows/review-diff.md`

Add when the repo really needs them:

- `templates/workflows/bug-regression.md`
- `templates/workflows/release-safety.md`
- `templates/workflows/dependency-upgrade.md`

## Setup Steps

1. Copy the base layer first.
2. Add the core four agents.
3. Add `plan-slice` and `bug-regression` as your default entry points.
4. Replace placeholders across all copied files.
5. Remove sections that do not fit the repository.
6. Search for unresolved `<PLACEHOLDER_NAME>` tokens and project-specific leaks.

## Clear Choice Model

- unclear area -> `researcher`
- multi-step or risky task -> `architect`
- bounded implementation -> `implementer`
- diff audit or correctness check -> `reviewer`
- rollout-sensitive change -> optional `release-auditor`
- structure-only cleanup -> optional `refactorer`

## Suggested First Test

- Ask `researcher` to identify owners for an unfamiliar task.
- Ask `architect` for a bounded plan on a medium-sized task.
- Ask `implementer` to make a narrow change with focused verification.
- Ask `reviewer` to inspect the resulting diff.
- Confirm `release-auditor` is only used when risk is `medium` or `high`.

## Anti-Pattern To Avoid

Do not install many specialist agents before the core four-agent flow works cleanly.

## After Install

- `ONBOARDING.md` — what to customize and in what order
- `../PLACEHOLDERS.md` — placeholder reference for all copied templates
- `../../docs/ai/POST-INSTALL.md` — post-install checklist and commands

## See Also

- `INSTALL-CATALOG.md` — full profile and pack index
- `INSTALL-GITHUB-COPILOT.md` — GitHub Copilot runtime install guide
- `../../docs/ai/install-order.md` — full ordered command flow and selective pack recipes
- `../../docs/ai/external-repo-install.md` — external repository install examples
- `workflows/SYSTEM-WORKFLOW.md` — end-to-end operating model
- `workflows/AGENT-HANDOFFS.md` — staged agent handoff contract
- `operations/GOVERNANCE.md` — governance rules and required controls
