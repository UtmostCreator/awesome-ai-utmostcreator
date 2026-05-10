# Onboarding

Use this guide if you are adopting the kit for the first time and want to understand what to copy, what to edit, and what not to over-customize.

## Start Here

For most repositories, begin with:

- `docs/ai/project-context.md`
- `docs/ai/capabilities/project-context/`
- `docs/ai/capabilities/verify-change/`
- `docs/ai/capabilities/review-diff/`
- one adapter surface: OpenCode or GitHub Copilot
- one shared guardrail file for common failure modes

Add more only when the base layer works.

## What Each File Type Is For

- `project-context`: durable repository facts, active paths, commands, and boundaries; not reusable workflow logic
- capability folders: reusable workflows with gotchas, examples, and verification; not runtime-specific adapter behavior
- repo-wide instructions: stable broad guidance that should apply often; not the place for detailed workflows
- path-specific instructions: narrow subsystem guidance; not repository-wide policy
- agents: bounded roles and posture; not a replacement for project context or capabilities
- skills: runtime access to capability workflows; not the only source of workflow truth
- commands: invocation wrappers and compatibility helpers; not canonical workflow definitions
- prompts: optional surface-specific guidance; not guaranteed command equivalents
- hooks: deterministic enforcement; not general policy storage
- MCP: bounded external access; not a default assumption

## Minimum Starting Set

Use this as the default starting point for most repos:

1. `docs/ai/project-context.md`
2. `docs/ai/capabilities/project-context/`
3. `docs/ai/capabilities/verify-change/`
4. `docs/ai/capabilities/review-diff/`
5. repo-wide instructions for your chosen runtime

## Customize In This Order

1. Fill in project context.
2. Replace placeholders in the base capabilities.
3. Add repo-wide instructions.
4. Add path-specific instructions if the repo truly needs them.
5. Add task entry points such as prompts or commands for recurring one-off jobs.
6. Add staged runtime adapters such as agents and skills.
7. Add specialist capabilities such as `bug-regression`, `release-safety`, or `dependency-upgrade` only when needed.
8. Add hooks and MCP boundaries only after the workflow and fallback path are clear.

## What Not To Customize Too Early

- Do not copy every optional capability before the base layer is stable.
- Do not move workflow logic into repo-wide instructions.
- Do not add many specialist agents before the main workflows are working.
- Do not treat prompts as the canonical workflow source.
- Do not leave template paths in live repository references.

## Common Adoption Mistakes

- unresolved placeholders in copied files
- template paths left in runtime docs
- duplicated workflow rules across repo-wide instructions and capabilities
- capability folders copied without project context
- optional adapters added before the base install is proven

## First Toy-Repo Test

- Can the system identify active paths from `project-context`?
- Can it choose narrow-first verification instead of jumping to a broad build?
- Can it review a diff without mostly restating it?
- Can it explain which capability fits a request and why?
- Can it route a repeated one-off task through a prompt or command instead of bloating always-on instructions?
- Can it keep research, implementation, and review in distinct contexts when the task is non-trivial?

## When To Add More

- add `bug-regression` when bug-fix work is common and should start from reproducible evidence
- add `release-safety` when rollout, rollback, or compatibility risk matters
- add `dependency-upgrade` when upgrades are frequent or risky
- add optional prompts and agents only after the base install works cleanly

## See Also

- `INSTALL-GITHUB-COPILOT.md` — GitHub Copilot base install guide
- `INSTALL-OPENCODE.md` — OpenCode base install guide
- `INSTALL-CATALOG.md` — full profile and pack index
- `../PLACEHOLDERS.md` — placeholder reference for all copied templates
- `workflows/SYSTEM-WORKFLOW.md` — end-to-end operating model
- `../../docs/ai/POST-INSTALL.md` — post-install checklist and commands
