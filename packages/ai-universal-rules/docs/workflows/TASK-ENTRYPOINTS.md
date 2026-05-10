# Task Entrypoints

This document explains when to use each mechanism.

## Use Instructions When

- the rule is stable and broadly relevant
- the guidance should apply across many tasks
- the content is policy, not a procedural script

Examples:

- architecture boundaries
- security constraints
- verification expectations
- naming and ownership rules

## Use Project Context When

- the task needs durable repository facts
- ownership, active paths, or commands matter
- another workflow needs factual grounding first

## Use Prompt Files Or Commands When

- the task is recurring but not always-on
- the job is narrow and one-off per invocation
- you want a named workflow entry point without making it permanent context

Examples:

- create failing regression test
- review migration plan
- prepare release-readiness checklist
- sync docs for changed behavior

## Use Agents When

- the task needs a distinct stage boundary
- different tool permissions should apply
- you want fresh context between research, implementation, and review

## Use Skills When

- the procedure is deep enough to need more than a small prompt
- the workflow benefits from bundled references, scripts, or templates
- the guidance should load only when relevant

Use `docs/foundations/SKILLS.md` when defining a skill so activation scope, portability, and safety stay explicit.

## Use Hooks When

- the repo needs deterministic enforcement
- a check must happen every time a lifecycle event occurs
- advisory instructions are not strong enough

## Use MCP When

- the task needs external system access
- the repo must document boundaries, allowed servers, and trust posture

## Anti-Patterns

- putting every repeated task into always-on instructions
- using a single giant agent for research, implementation, review, and release
- using prompt files as the canonical source of workflow knowledge
- assuming hooks exist when no runtime can enforce them

## See Also

- `SYSTEM-WORKFLOW.md` — end-to-end lifecycle and entry point selection rule
- `AGENT-HANDOFFS.md` — staged agent handoff contract
- `../foundations/CAPABILITY-MODEL.md` — capability folders as the reusable workflow layer
- `../foundations/SKILLS.md` — skill definition and activation scope
- `../foundations/PRECEDENCE.md` — layering and non-overlap rules
