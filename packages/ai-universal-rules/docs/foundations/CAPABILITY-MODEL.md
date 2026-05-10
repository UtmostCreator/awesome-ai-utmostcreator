# Capability Model

Capabilities are the canonical reusable workflow layer in this kit.

## Why Capabilities Exist

- instructions are always-on and should stay short
- prompts and commands are task entry points, not the full workflow source
- agents provide stage posture and tool boundaries
- hooks enforce rules but do not explain procedure well
- capabilities hold the reusable workflow knowledge that adapters point back to

This keeps policy separate from procedure and prevents global files from turning into one giant bug-fix or release prompt.

## Capability Contract

Each capability folder should answer:

- what does this workflow do?
- when should it trigger?
- when should it not trigger?
- what inputs does it need?
- what evidence counts as done?
- what output shape should it produce?
- what other capabilities does it compose with?

Recommended shape:

```text
capability-name/
  CAPABILITY.md
  gotchas.md
  examples.md
  checklist.md
  reference.md
  config.example.json
  templates/
  scripts/
```

Only keep files that add value.

## Required Sections

Every capability should define:

1. purpose
2. trigger conditions
3. non-trigger conditions
4. required inputs and setup
5. expected workflow
6. verification expectations
7. output contract
8. related capabilities

## Required Files

- `CAPABILITY.md`
- `gotchas.md`
- at least one of `examples.md` or `checklist.md`

## See Also

- `../CAPABILITY-MODEL.md` — root summary version
- `../workflows/TASK-ENTRYPOINTS.md` — when to use capabilities vs other mechanisms
- `../workflows/SYSTEM-WORKFLOW.md` — where capability loading fits in the lifecycle
- `../../templates/capabilities/README.md` — canonical capability template set

Recommended for mature capabilities:

- `reference.md`
- capability-local templates or scripts when repeated output should be partially deterministic

## Canonical Location In Real Repositories

Copy capability folders into:

`docs/ai/capabilities/`

Do not treat runtime-specific adapters as the source of workflow truth.

## Relationship To Skills

- in OpenCode, skills should usually adapt from capability folders
- in GitHub Copilot, skills or prompt files should reference the same workflow concepts where supported
- capabilities remain the portable source even when one runtime has richer features than another

See `docs/foundations/SKILLS.md` for packaging, trigger-description quality, portability limits, and trust posture.

## Relationship To Agents

Agents are stage boundaries.

- researcher: gather facts without editing
- planner or architect: define bounded slice and risk posture
- implementer: edit and verify
- reviewer: audit in a fresh context
- release auditor: focus on rollout and rollback posture

Agents should call into capability logic, not replace it.

## Verification Ladder

Default order unless the repository defines something narrower:

1. focused proof or reproduction first
2. affected layer or package tests second
3. broader repository verification third
4. build as a smoke check when relevant
5. release-safety review only when risk warrants it

## Drift Resistance Rules

- keep durable facts in project context, not in capability history
- keep capability entry files short enough to scan quickly
- move recurring edge cases into `gotchas.md`
- move deterministic repeated output into templates or scripts
- do not duplicate the same workflow across multiple prompts, agents, and instructions
