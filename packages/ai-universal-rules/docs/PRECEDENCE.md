# Precedence

This root file is the short guide version.

See `docs/foundations/PRECEDENCE.md` for the maintained canonical copy.

## Practical Order

1. core policy
2. project context
3. capability folders
4. task entry points
5. staged agents
6. hooks and enforcement

## Non-Overlap Rule

- repo-wide instructions: broad policy
- path instructions: local conventions
- capabilities: reusable procedures
- prompts or commands: recurring one-off jobs
- agents: stage boundaries
- hooks: enforcement

If two files try to own the same responsibility, narrow or remove one.
