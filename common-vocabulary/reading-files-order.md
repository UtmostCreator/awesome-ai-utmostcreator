# AI Repository Instruction Sources and Intended Read Order

This page documents the repository-facing files each runtime is expected to read.
It is a repo contract, not a claim about hidden provider prompts or internal model state.

Use this page when you want to know which files matter first for GitHub Copilot,
OpenCode, and Claude in this repository.

## Copilot

Copilot does not expose one strict, fully observable file preload sequence for every surface.
In this repo, the intended order is:

Note: Copilot surfaces differ. In VS Code and related agent surfaces, multiple instruction
files may be combined into the active context without a strict observable merge order. Treat
the list below as this repository's intended reading contract, not as a provider-guaranteed
preload sequence.

1. `.github/copilot-instructions.md`
2. `.github/instructions/context-gate.instructions.md`
3. The other matching `.github/instructions/*.instructions.md` files for the current task or path
4. `docs/ai/project-context.md`
5. `docs/ai/workflow.md`
6. `docs/ai/execution-protocol.md`
7. `docs/ai/ai-file-standards.md`
8. `docs/ai/failure-handling.md`
9. `docs/ai/agent-ops-checklist.md`
10. `docs/ai/integration-matrix.md`
11. Any additional canonical docs named by `.github/copilot-instructions.md` that match the task, such as `docs/ai/adapter-contract.md`, `docs/ai/approval-boundaries.md`, or generated-artifact docs
12. `AGENTS.md` when the Copilot surface also loads repository agent instructions. In this
    repository, that currently means the root `AGENTS.md`.

How to read that list:

- Step 1 is explicit in the Copilot adapter.
- Step 2 is mandatory because `.github/copilot-instructions.md` says to apply the context gate before planning, editing, or reviewing.
- Step 3 depends on the file being touched. For this page, the matching instructions are the repo-wide ones such as `base.instructions.md`, `architecture.instructions.md`, `execution-protocol.instructions.md`, `security.instructions.md`, `targets.instructions.md`, `tools.instructions.md`, and `approval-boundaries.instructions.md`.
- Steps 4 through 11 are the canonical docs Copilot is told to use.
- Step 12 exists because this repository also ships `AGENTS.md` as a repo-wide instruction surface, but the exact load timing depends on the Copilot surface.
- Repository-owned Copilot customization can also include `.github/prompts/*.prompt.md`,
  `.github/agents/*.agent.md`, `.github/skills/*/SKILL.md`, and `.github/hooks/*.json`
  when the active Copilot surface supports them.

Out of scope for this repository contract:

- Organization-level Copilot instructions
- User-level or profile-level Copilot instructions
- IDE-local instruction files outside this repository
- Session context, cached context, or provider-internal prompts

## OpenCode

OpenCode is the clearest case because `opencode.jsonc` declares the instruction list explicitly.
It reads these files in this exact order:

1. `AGENTS.md`
2. `docs/ai/project-context.md`
3. `docs/ai/project/project-interaction.md`
4. `docs/ai/tools/ai-search.md`
5. `docs/ai/tools/tool-map.md`
6. `docs/ai/tools/actions/search-evidence.md`
7. `docs/ai/tools/actions/preview-file.md`
8. `docs/ai/workflow.md`
9. `docs/ai/execution-protocol.md`
10. `docs/ai/ai-file-standards.md`
11. `docs/ai/approval-boundaries.md`
12. `docs/ai/generated-artifacts.md`
13. `docs/ai/adapter-contract.md`

That order comes directly from the `instructions` array in `opencode.jsonc`.

Repository-owned OpenCode surfaces also include `.opencode/agents/`, `.opencode/commands/`,
and `.opencode/skills/`. They are not part of the `instructions` array above, but OpenCode may
use them when the active task, agent, or command references them.

This page only describes repository-owned OpenCode files. Non-repository OpenCode configuration
layers, if present on a machine, are outside this contract.

## Claude

Claude is served in this repository through the base adapter layer, not through a separate runtime config file like OpenCode.
The intended read order is the one declared in `CLAUDE.md`:

1. `CLAUDE.md`
2. `AGENTS.md`
3. `docs/ai/project-context.md`
4. `docs/ai/workflow.md`
5. `docs/ai/agents.md`
6. `docs/ai/failure-handling.md`
7. `docs/ai/agent-ops-checklist.md`
8. `docs/ai/integration-matrix.md`
9. `packages/ai-universal-rules/README.md`

How to read that list:

- `CLAUDE.md` is the Claude adapter entry point.
- Its `Read First` block then names the remaining files in order.
- `AGENTS.md` is first in that list because Claude is expected to inherit the repo-wide baseline before reading deeper canonical docs.

This repository does not currently ship any `.claude/` directory or nested `CLAUDE.md` files.
If those are added later, they should be documented separately instead of being implied here.

## Other Repository Files a Runtime May Load

The three lists above describe the baseline repository files that each runtime is directed to read first.
More files can be pulled in later when the task requires them.

Common examples are:

1. Path-matched instruction files under `.github/instructions/`
2. Task-specific docs under `docs/ai/`
3. Prompt files under `.github/prompts/`
4. Agent files under `.github/agents/` or `.opencode/agents/`
5. Skill files under `.github/skills/` or `.opencode/skills/`
6. Hook config under `.github/hooks/*.json`
7. OpenCode command files under `.opencode/commands/`
8. Project-owned docs under `docs/ai/project/`
9. Source code, tests, and config files named by the current task
10. `docs/ai/source-of-truth.md` when the runtime or human reviewer needs an explicit conflict-resolution rule

Not currently present in this repository:

1. `.github/plugins/`
2. `.vscode/mcp.json`
3. `GEMINI.md`
4. `.claude/`
5. OpenCode `modes/`, `plugins/`, `tools/`, or `themes/` directories

If two files disagree, use the source-of-truth order from `docs/ai/source-of-truth.md`: user request first, then working tree, code, tests, config, canonical `docs/ai/`, adapter files, and finally generated artifacts.
