# AI File Standards

Use this file as the canonical content and size contract for AI workflow files in this repository and in installed target repositories.

## Purpose

Keep AI workflow files small, single-purpose, reference-driven, and generated from one source where practical.

## Primitive Roles

| Primitive | Owns | Must not own |
| --- | --- | --- |
| `AGENTS.md` | global rules, source-of-truth routing, safety defaults | full workflows, role bodies, long test matrices |
| `.github/copilot-instructions.md` | Copilot entry routing and repo summary | canonical policy duplicated from `docs/ai/` |
| `.github/instructions/*.instructions.md` | path or topic rules with deterministic `applyTo` | task workflows or agent personas |
| `.github/agents/*.agent.md` | persistent Copilot role, tool boundary, handoff contract | capability examples or long checklists |
| `.github/prompts/*.prompt.md` | one-shot task launchers | durable policy, tool permissions, full procedures |
| `.github/skills/*/SKILL.md` | Copilot-loadable capability adapter | project-wide rules or unrelated workflows |
| `.opencode/agents/*.md` | OpenCode role, mode, permission, handoff contract | duplicated capability procedure |
| `.opencode/commands/*.md` | short slash-command wrapper | long procedures or permission policy |
| `.opencode/skills/*/SKILL.md` | OpenCode-loadable capability adapter | global instructions or agent persona |
| `docs/ai/capabilities/*/CAPABILITY.md` | canonical reusable behavior | adapter-specific syntax |
| `checklist.md` | pass/fail execution steps | examples or reference essays |
| `gotchas.md` | recurring traps and safe response | generic warnings without detection |
| `reference.md` or `references.md` | ranked links to source docs, scripts, schemas | copied external content |
| `examples.md` | 2-4 high-signal examples | exhaustive transcripts |

## Source Hierarchy

When files disagree, resolve conflicts in this order:

1. user request
2. current git diff and working tree
3. source code
4. tests
5. schemas and contracts
6. runtime and config files
7. canonical docs under `docs/ai/`
8. adapter files under `.github/` and `.opencode/`
9. generated files
10. historical notes

Generated files are context unless their generator contract explicitly marks them as committed canonical artifacts.

## Line Budgets

Generated outputs are excluded from line limits, but must not be manually edited.

| File type | Ideal | Soft max | Hard max |
| --- | ---: | ---: | ---: |
| Root `AGENTS.md` | 60-120 | 180 | 250 |
| `.github/copilot-instructions.md` | 40-100 | 150 | 220 |
| `.github/instructions/*.instructions.md` | 40-90 | 140 | 180 |
| `.github/agents/*.agent.md` | 80-160 | 320 | 360 |
| Claude agents | 80-170 | 320 | 360 |
| `.github/prompts/*.prompt.md` | 30-80 | 120 | 160 |
| `.github/skills/*/SKILL.md` | 80-180 | 250 | 350 |
| `.opencode/agents/*.md` | 80-170 | 320 | 360 |
| `.opencode/commands/*.md` | 15-50 | 80 | 120 |
| `.opencode/skills/*/SKILL.md` | 80-180 | 250 | 350 |
| Root `AGENTS.md` template | 60-120 | 180 | 250 |
| `.github/copilot-instructions.md` template | 40-100 | 150 | 220 |
| Instruction templates | 40-90 | 140 | 180 |
| Agent templates | 80-170 | 320 | 360 |
| Workflow templates | 30-80 | 120 | 160 |
| Command templates | 15-50 | 80 | 120 |
| `CAPABILITY.md` | 120-220 | 300 | 400 |
| `checklist.md` | 40-100 | 140 | 180 |
| `gotchas.md` | 30-80 | 120 | 160 |
| `reference.md` / `references.md` | 20-70 | 100 | 140 |
| `examples.md` | 60-160 | 220 | 300 |

## Split Rules

Split a file before adding more when any of these are true:

- it has more than three responsibilities
- it has more than five top-level modes or subcommands
- repeated blocks exceed 25 lines
- a script mixes parsing, validation, rendering, and filesystem writes
- a prompt, command, skill, or agent repeats a full capability body

## Adapter Rules

- Capabilities are canonical procedure.
- Skills adapt capabilities for runtime loading.
- Prompts and commands launch one task and point to capabilities or skills.
- Agents define role posture, tool or permission boundaries, and handoff outputs.
- Instructions define stable defaults and path-specific rules.
- Runtime adapters may repeat short critical routing, but must not duplicate long procedure.

## Enforcement Expectations

- Hard max violations should fail validation unless the path is generated or explicitly allowlisted.
- Soft max violations should warn and include a split recommendation.
- Adapter drift checks should fail when rendered surfaces disagree with their source.
- Broken references should fail for installable docs, instructions, agents, skills, prompts, and commands.
- OpenCode agents should use `permission`, not deprecated `tools`.
- Copilot instruction files should use `applyTo` when deterministic application matters.
