<!-- GENERATED — DO NOT EDIT: rendered by ai-kit installer from packages/ai-universal-rules/templates/core/CLAUDE.template.md. Edit the template or .ai/project.yml, not this file. -->

# CLAUDE.md

awesome-ai-utmostcreator — AI workflow starter for awesome-ai-utmostcreator

## Read First

- `AGENTS.md`
- `docs/ai/project-context.md`
- `docs/ai/workflow.md`
- `docs/ai/AI-GUARDRAILS.md`
- `docs/ai/agents.md`
- `docs/ai/failure-handling.md`
- `docs/ai/agent-ops-checklist.md`
- `docs/ai/integration-matrix.md`

## What Matters Here

- Keep Claude-specific guidance thin and consistent with the canonical docs.
- Prefer durable project facts from `docs/ai/` over session assumptions.
- Treat `.github/` as the Copilot adapter layer and `.opencode/` as the OpenCode adapter layer,
  not the primary source of policy.
- When changing docs or config, sync affected setup instructions in the same slice.

## Working Style

- Start narrow and keep changes bounded.
- On an ambiguous or terse request ("implement now", "is it correct?", or several bundled decisions), pause and offer a structured question set with 2-4 selectable options per question before editing (see `docs/ai/capabilities/clarification-and-handoff/CAPABILITY.md`, "Structured Question Set"). Where interactive selection is unavailable, state the assumption, mark it `unknown`, and stop on high-impact ambiguity instead of guessing.
- For non-trivial edits, use `project-context` first and then the smallest fitting capability.
- Before adding non-trivial new logic, search for similar existing patterns; when overlap is
  roughly `>=75%`, flag reuse or replacement instead of duplicating logic.
- For config changes, verify with the closest parser, linter, or tool-specific sanity check
  available.
- If a runtime surface cannot support a workflow step directly, document the fallback instead of
  pretending parity.

## Claude Sub-Agents

`.claude/agents/*.md` are rendered from the same canonical agent source as the Copilot and
OpenCode adapters (see `docs/ai/agents.md` for the live index and routing table). Claude Code
sub-agent frontmatter cannot express the per-command bash allowlist that the canonical source
carries — only tool-level `tools`/`disallowedTools`/`permissionMode` — so each rendered agent
carries a "Bash Command Policy" body section as the advisory boundary, with `.claude/settings.json`
`permissions` as the enforced surface. Claude has no structured handoff field: every rendered
agent's "Recommended next step" stays a prose sentence naming the next agent, per
`docs/ai/handoff-contract.md`.

## Approval Boundaries

- Safe repo-local read-only commands are approval-free by default.
- Ask before changing secrets, machine-specific credentials, or broad compatibility posture.
- Ask before deleting large example areas or removing a supported adapter surface.
- Stop and ask before a read-only step needs privileged access, external side effects, or
  secret-bearing surfaces.

## Failure Handling

- Record command failures, retry choices, corrected usage, and avoid-notes using
  `docs/ai/failure-handling.md`.
- Do not blindly retry blocked, denied, or mis-specified commands.

## Memory Note

If deeper process is needed, prefer `docs/ai/capabilities/` over expanding this file.

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Rules:
- For codebase questions, first run `graphify query "question"` when graphify-out/graph.json exists. Use `graphify path "Node A" "Node B"` for relationships and `graphify explain "concept"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
