# Precedence

This package uses layered workflow assets, so precedence and non-overlap must be explicit.

## Preferred Ordering By Purpose

1. always-on policy
2. durable project context
3. capability folders
4. task entry points
5. staged agents and handoffs
6. hooks and enforcement

## Conflict Avoidance Rule

Every layer should have a distinct responsibility.

- repo-wide instructions: broad stable policy
- path-specific instructions: narrow local conventions
- project context: durable facts
- capability folders: reusable procedures
- skills: runtime-loaded capability adapters
- prompt files or commands: recurring one-off jobs
- agents: role posture and stage boundaries
- hooks: enforcement

If two files compete for the same responsibility, narrow or remove one of them.

## OpenCode Guidance

- keep `AGENTS.md` broad and stable
- keep `.opencode/agents/` focused on roles
- keep `.opencode/commands/` narrow and task-invocation oriented
- keep `.opencode/skills/` thin adapters over capability folders when possible

## GitHub Copilot Guidance

- keep `.github/copilot-instructions.md` short and broad
- keep `.github/instructions/*.instructions.md` path-focused
- use prompt files for recurring one-off tasks where supported
- use `.github/skills/` as project skill adapters on Copilot surfaces that support skills
- use custom agents for stage-specific work, not giant global procedure blocks
- do not assume identical behavior across VS Code, CLI, and GitHub.com

## See Also

- `../workflows/TASK-ENTRYPOINTS.md` — practical guidance on which mechanism owns which responsibility
- `../workflows/SYSTEM-WORKFLOW.md` — lifecycle ordering that reflects this precedence
- `COMPATIBILITY.md` — surface differences that affect which layers are available
- `DESIGN-PRINCIPLES.md` — principles behind the layering model
