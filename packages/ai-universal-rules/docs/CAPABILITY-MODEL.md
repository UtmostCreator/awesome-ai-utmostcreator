# Capability Model

This kit now treats capabilities as the main reusable workflow unit.

## Why Capabilities

- Instructions are best for baseline policy.
- Agents are best for role-specific behavior.
- Prompts and commands are useful adapters.
- Capabilities are where workflow knowledge, examples, gotchas, setup, and verification should live.

This keeps global instructions short while giving complex workflows room to grow without turning into one giant markdown file.

## Capability Contract

Each capability folder should contain a short entry file plus supporting files for progressive disclosure.

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

Only keep files that actually add value for the workflow.

## Canonical Repository Location

When adopting this kit in a live repository, copy capability folders to:

`docs/ai/capabilities/`

Do not keep `templates/capabilities/` as the runtime location in an adopted repository unless the repository is intentionally preserving the template tree for demonstration.

## Entry File Requirements

The entry file should answer these questions clearly:

- What does this capability do?
- When should it trigger?
- When should it not trigger?
- What setup or inputs does it need?
- What files should be read next when more detail is needed?
- What outputs should it produce?

Keep the entry file concise. Put bulky examples, edge cases, and reference material in support files.

## Required Sections

Every reusable capability should define:

1. Purpose
2. Trigger conditions
3. Non-trigger conditions
4. Inputs and setup
5. Expected workflow
6. Verification expectations
7. Output contract
8. Related capabilities

## Required vs Optional Files

Required for every capability:

- `CAPABILITY.md`
- `gotchas.md`
- at least one of `examples.md` or `checklist.md`

Optional when they add real value:

- `reference.md`
- `config.example.json`
- `templates/`
- `scripts/`

## Gotchas

`gotchas.md` is the highest-signal support file.

Build it from real failure modes:

- common model mistakes
- repo-specific footguns
- bad defaults the capability should avoid
- anti-patterns that look reasonable but produce weak output

If a capability has no gotchas yet, it is probably still immature.

## Progressive Disclosure

Do not front-load everything into the entry file.

Use support files deliberately:

- `reference.md` for stable facts and exact conventions
- `examples.md` for worked examples and expected outputs
- `checklist.md` for ordered verification or release steps
- `scripts/` for deterministic helpers
- `templates/` for generated output shells

## Setup And Config

If a capability needs environment-specific values, include a `config.example.json` with the required fields and explain how missing config should be handled.

Good candidates:

- package manager commands
- service names
- environments
- smoke-test URLs
- CI workflow names
- supported app paths

## Memory And Data

Keep durable facts separate from workflow instructions.

- stable repository facts belong in project context files
- workflow history belongs in explicit logs or stable plugin data paths
- temporary run output should not be treated as durable memory

## Composition

Capabilities should reference each other by responsibility, not by fragile internal assumptions.

For common task-to-capability sequences, see `docs/COMPOSITION-RECIPES.md`.

Example composition:

- `project-context` feeds `architect`, `review-diff`, and `verify-change`
- `bug-regression` composes `project-context` and `verify-change`
- `release-safety` composes `review-diff` and `verify-change`

## Verification Ladder

Use this order by default unless the repository explicitly needs something narrower or broader:

1. focused proof or reproduction first
2. affected layer or package tests second
3. broader repository verification third
4. build as a smoke check when relevant
5. release-safety review only when risk warrants it

## Adapters

This repository keeps the capability content tool-agnostic first.

- OpenCode skills should adapt from capability folders when possible.
- GitHub Copilot agents and prompts should reference the same capability concepts.
- Commands and prompts are compatibility surfaces, not the canonical source of workflow knowledge.

## See Also

- `foundations/CAPABILITY-MODEL.md` — canonical maintained copy with full contract
- `COMPOSITION-RECIPES.md` — task-to-capability routing recipes
- `workflows/TASK-ENTRYPOINTS.md` — when capabilities are the right choice
- `templates/capabilities/README.md` — canonical capability template set
