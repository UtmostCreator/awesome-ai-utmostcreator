# Control Model

This package separates advisory controls from deterministic controls.

## Advisory Controls

Use these to shape behavior:

- repo instructions
- path-specific instructions
- `AGENTS.md` or similar shared memory files
- prompt files
- agent descriptions
- capability folders
- skills

These influence the model, but do not guarantee execution.

## Deterministic Controls

Use these when the repo needs guaranteed checks or boundaries:

- tool restrictions
- read-only agents
- hooks
- required approval packets
- explicit verification commands
- MCP allowlists and environment boundaries

## Canonical Sources By Responsibility

- policy: core instruction files
- durable facts: project context
- procedure: capabilities
- stage boundaries: agents and subagents
- task invocation: prompt files or commands
- enforcement: hooks and tool boundaries
- external access boundaries: MCP rules

## Key Rule

Do not confuse the existence of an instruction with enforcement.

If a repo must guarantee secrets scanning, destructive-action blocking, or required validation, use hooks or other deterministic controls where the runtime supports them.

## See Also

- `COMPATIBILITY.md` — surface differences that affect what controls are available
- `../operations/HOOKS-AND-ENFORCEMENT.md` — deterministic controls and minimum enforcement posture
- `../operations/GOVERNANCE.md` — required controls and governance goals
- `../operations/MCP-BOUNDARIES.md` — deterministic boundaries for external system access
