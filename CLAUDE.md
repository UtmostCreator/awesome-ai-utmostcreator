# CLAUDE.md

This repository is a configuration repo and a live example of cross-tool AI workflow design.

## Read First

- `AGENTS.md`
- `docs/ai/project-context.md`
- `docs/ai/workflow.md`
- `docs/ai/agents.md`
- `docs/ai/failure-handling.md`
- `docs/ai/agent-ops-checklist.md`
- `docs/ai/integration-matrix.md`
- `packages/ai-universal-rules/README.md`

## What Matters Here

- Keep Claude-specific guidance thin and consistent with the canonical docs.
- Prefer durable project facts from `docs/ai/` over session assumptions.
- Treat `.github/` as the Copilot adapter layer, not the primary source of policy.
- When changing docs or config, sync affected setup instructions in the same slice.

## Working Style

- Start narrow and keep changes bounded.
- For non-trivial edits, use `project-context` first and then the smallest fitting capability.
- Before adding non-trivial new logic, search for similar existing patterns; when overlap is roughly `>=75%`, flag reuse or replacement instead of duplicating logic.
- For config changes, verify with the closest parser, linter, or tool-specific sanity check available.
- If a runtime surface cannot support a workflow step directly, document the fallback instead of pretending parity.

## Approval Boundaries

- Safe repo-local read-only commands are approval-free by default.
- Ask before changing secrets, machine-specific credentials, or broad compatibility posture.
- Ask before deleting any file, even one that looks obviously safe to remove, large example areas, or removing a supported adapter surface.
- Stop and ask before a read-only step needs privileged access, external side effects, or secret-bearing surfaces.

## Failure Handling

- Record command failures, retry choices, corrected usage, and avoid-notes using `docs/ai/failure-handling.md`.
- Do not blindly retry blocked, denied, or mis-specified commands.

## Memory Note

If deeper process is needed, prefer `docs/ai/capabilities/` and `packages/ai-universal-rules/` over expanding this file.

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Rules:
- For codebase questions, first run `graphify query "question"` when graphify-out/graph.json exists. Use `graphify path "Node A" "Node B"` for relationships and `graphify explain "concept"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
