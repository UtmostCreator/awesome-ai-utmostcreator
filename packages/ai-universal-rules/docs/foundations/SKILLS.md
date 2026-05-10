# Skills

Skills are the runtime adapter form of deeper workflow procedure.

They give an agent procedural guidance for a repeatable job without turning always-on instructions into one giant prompt. In this kit, capabilities remain the canonical workflow source and skills adapt that workflow to runtimes that support skill loading.

## What Skills Are

- a directory-based workflow package, usually built around `SKILL.md`
- a way to load deeper procedure only when relevant
- a good fit for workflows that need more than a short prompt or command
- an adapter layer, not the main source of repository truth

Skills are not the same as plugins, fine-tuning, or generic retrieval. They tell the agent how to carry out a repeatable task, in what order, with what boundaries, and with what evidence standard.

## Why Skills Exist

- repo-wide instructions should stay short and durable
- prompt files and commands are task entry points, not full procedure stores
- agents define stage posture and tool boundaries, not the entire workflow
- hooks enforce rules but do not explain procedure well

Skills let a runtime load a deeper workflow only when the task matches.

## Core Structure

The portable center of a skill is `SKILL.md`.

Typical shape:

```text
skill-name/
  SKILL.md
  scripts/
  references/
  assets/
```

`SKILL.md` should define at least:

- `name`
- `description`
- the operating procedure in the markdown body

Use bundled scripts, references, or assets only when they make the workflow more reliable or less repetitive.

## Progressive Disclosure

Skills work best when they are written for staged loading.

- metadata should be enough for the runtime to decide whether the skill fits
- the main body should be readable on activation without pulling every support file
- references, templates, and scripts should open only when the task actually needs them

This keeps larger skill libraries practical and reduces context pollution.

## Description Quality

The `description` field is not filler text. It is the trigger contract.

## See Also

- `CAPABILITY-MODEL.md` — capabilities as the canonical source; skills as runtime adapters
- `../workflows/TASK-ENTRYPOINTS.md` — when to use skills vs prompts, commands, or capabilities
- `../workflows/SYSTEM-WORKFLOW.md` — where skill loading fits in the lifecycle
- `COMPATIBILITY.md` — surface differences that affect skill availability

A good description should say:

- what the skill does
- when to use it
- what kinds of requests should match it
- where its scope stops when that is easy to confuse

Prefer descriptions that are specific enough to beat a vague generic match.

Good pattern:

```text
Use when reviewing a change set for correctness, regression risk, policy fit, and missing verification starting from the diff
```

Weak pattern:

```text
Helps with code review
```

## Relationship To Other Layers

- instructions: stable policy and defaults
- project context: durable repository facts
- capabilities: canonical reusable procedure
- prompt files or commands: named task entry points
- agents: stage isolation and role posture
- skills: runtime-loaded deep procedure
- hooks: deterministic enforcement
- MCP: external system access

Use skills when the workflow is deep enough to need more than a small prompt and should load only when relevant.

## Portability Rule

The portable part is the core skill structure and operating model, not every advanced runtime feature.

- skill packaging can converge across runtimes while activation details still differ
- invocation controls, tool approvals, subagent behavior, and dependency metadata may be runtime-specific
- do not imply identical behavior across OpenCode, Copilot, Claude-style runtimes, or future adapters

If portability matters, keep the workflow logic in capabilities and treat skills as thin runtime adapters. In the current installer, workflow entrypoint templates are also used to render prompt, command, and skill surfaces; those workflow templates must therefore stay short and point back to canonical capabilities instead of duplicating the full capability body.

## Trust And Safety

Skills should be treated like lightweight executable dependencies.

- review bundled scripts before trusting them
- review any tool approval or command-execution behavior carefully
- do not assume a portable format is automatically safe
- document fallback behavior when a skill depends on runtime-specific features

If a skill can run commands or widen tool access, its trust posture matters as much as its instructions.

## Design Rule For This Kit

Use this package layering consistently:

1. keep reusable workflow logic in `templates/capabilities/` and adopted copies under `docs/ai/capabilities/`
2. keep workflow entrypoint templates thin because they are installed as prompts, commands, and skills
3. keep skills as runtime adapters that point back to canonical capability workflows
4. keep always-on instructions short, policy-focused, and free of deep procedure blocks

This prevents drift between the portable workflow model and runtime-specific behavior.
