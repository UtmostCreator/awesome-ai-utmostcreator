# Plan D2 — Copilot Renderer Projects agent_assessment When Present

- Status: Todo (implementable after D1)
- Risk: low-medium (renderer change; gated by "only if present"; unit-tested)
- Dependency: D1 (schema + pilot block)

## Goal

OpenCode agents already carry a template `agent_assessment` block verbatim. The
Copilot renderer rebuilds frontmatter from scratch (`tools/ai/install/copilot-agent-renderer.php:62-69`)
and drops everything except name/description/tools/user-invocable. Extend it to carry
an `agent_assessment` block from the template into the rebuilt Copilot frontmatter
WHEN the source template has one.

## Steps

1. In `aiInstallerRenderCopilotAgent()`, after parsing OpenCode frontmatter, detect a
   nested `agent_assessment:` block (multi-line, indented key/values) in the raw
   frontmatter.
2. If present, re-emit it under the rebuilt Copilot frontmatter, preserving keys and
   values exactly (round-trip), before the closing `---`.
3. If absent, behave exactly as today (no empty block emitted).
4. Add renderer unit tests: template WITH block -> Copilot output contains the block;
   template WITHOUT block -> output unchanged from current behavior.

## Out of scope / avoid

- No installer re-render of live agents (D3).
- No new values; only project what the template already has (the D1 pilot block).
- Do not break existing renderer tests or the GENERATED-header insertion.

## Acceptance criteria

- [ ] Pilot template's `agent_assessment` block round-trips into the rendered Copilot
      frontmatter.
- [ ] Agents without the block render byte-identically to current output.
- [ ] Renderer unit tests green; existing copilot-agent-renderer tests still green.

## Verification

- `vendor/bin/phpunit --filter <renderer test>`
- `php tools/ai/validate-adapter-drift.php`
