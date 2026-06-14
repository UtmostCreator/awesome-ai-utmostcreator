# Tool Policy

Tool policy defines which tool surfaces are preferred and when extra approval is
required.

## Preferred Tools

Use repository-approved scripts for search, preview, verification, and evidence.
The primary wrappers are documented in `docs/ai/tools/tool-map.md` and
`docs/ai/script-registry.md`.

## Risk Boundaries

Read-only discovery is normally safe. Mutation, generated rewrites, dependency
installation, secret access, deployments, and destructive commands require
explicit approval.

## Script Registry

When running repository scripts, prefer registered scripts and report the command
exactly. If a script is unknown or not documented, treat it as `ask` unless the
user explicitly approved it.

## Output Claims

Do not claim that verification, builds, or checks ran unless the command was
executed and its result is known.
