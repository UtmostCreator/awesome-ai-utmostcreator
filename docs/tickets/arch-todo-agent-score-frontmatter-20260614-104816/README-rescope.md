# Plan D Re-Scope — agent_assessment rubric (D1 / D2 / D3)

This plan (`plan.md`) was BLOCKED as written. Architect re-scope (delivered in the
implementer session 20260614) splits it into three bounded slices. Implement from
`D1-plan.md`, `D2-plan.md`, `D3-plan.md`, NOT from the original `plan.md`.

## Why the original was blocked

1. **No source of per-agent rubric values.** `docs/ai/AGENTS-MANIFEST.md:90-92` says
   enforced `agent_score`/lifecycle frontmatter is a "separate, later phase". The
   manifest carries only qualitative `Risk`/`Mutating`/`Gate` columns — no numeric
   rubric to project or to satisfy original AC-04. Populating ~24 agents × 11 fields
   would mean inventing numbers (forbidden by AGENTS.md "do not invent").
2. **Stale source citation.** The cited `todo-agents-rework.md:38-51` is a
   range/formula spec (now archived under `docs/tickets/archive/root-cleanup-20260614/`),
   not a value table.
3. **Scope overrun.** Original 35a touched ~24 templates + schema + renderer + tests,
   far past the ~6-file bounded-slice limit.
4. **Rendering split (verified in `tools/ai/install/copilot-agent-renderer.php`):**
   OpenCode agents are copied verbatim (rubric in template frontmatter flows through);
   Copilot frontmatter is rebuilt from scratch (lines 62-69) and must be extended to
   carry a rubric.

## Decisions (from architect re-scope)

- **Rubric value source:** ship optional field DEFINITIONS only now; defer all
  per-agent VALUES to D3 behind a future human-supplied source. No invented numbers.
- **AC-04 rewrite:** the only checkable agreement today is `risk_level` (if present)
  vs the manifest `Risk` column; full score agreement deferred to D3.
- **Provenance item 45: DROPPED.** Generated agent files already carry an idempotent
  post-frontmatter `GENERATED` comment on both surfaces; a second provenance key adds
  a redundant source of truth with no demonstrated need.

## Slice order

`Plan A committed` → **D1** (schema + optional validation + pilot template) → **D2**
(Copilot renderer projection) → **(human supplies values)** → **D3** (populate +
approval-gated installer re-render).
