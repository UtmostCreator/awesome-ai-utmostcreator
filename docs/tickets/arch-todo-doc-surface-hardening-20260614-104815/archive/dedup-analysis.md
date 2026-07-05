# Capability / Skill Projection Dedup Analysis

Generated for Plan C item 80. This report is analysis-only; no files were moved
or deleted as part of this item.

## Evidence Checked

- Capability sources: `docs/ai/capabilities/*/CAPABILITY.md` — 16 capability roots.
- OpenCode skill projections: `.opencode/skills/*/SKILL.md` — 21 skill roots.
- GitHub skill projections: `.github/skills/*/SKILL.md` — 19 skill roots.
- Current adapter contract: `docs/ai/adapter-contract.md` states capabilities are
  canonical procedure and skills are runtime adapters.

## Findings

### Projection overlap is expected

Skills under `.opencode/skills/` and `.github/skills/` intentionally adapt
canonical capability behavior for different runtimes. Overlap between a skill and
its matching capability is therefore expected and should not be removed without a
renderer/template change.

### Runtime-surface differences are real

OpenCode has extra skill surfaces that are not present as GitHub skills or
canonical capability names, including `ai-search`, `ai-scripts`,
`search-evidence`, `review-search-tool`, `script-inventory`, `new-feature`,
`post-install-setup`, `repo-investigation`, and `regression-test`. These are
runtime/task adapters rather than obvious duplicate canonical capabilities.

GitHub and OpenCode skill sets overlap heavily for common workflows such as
`project-context`, `review-diff`, `verify-change`, `release-safety`,
`bug-regression`, `docs-sync`, `dependency-upgrade`, and planning skills. This
is adapter parity, not standalone duplicate logic.

### No safe deletion candidate identified

No skill file was proven to be a pure redundant copy whose removal would preserve
runtime behavior. Any consolidation should happen by changing templates/renderers
so skills stay thin while continuing to point at canonical capabilities.

## Recommendations

1. Keep current skill projection files in place.
2. If future dedup is desired, add a validator that compares each skill against
   its canonical capability reference and warns when a skill duplicates long
   procedure text instead of linking to capability docs.
3. Treat any deletion or move of `.github/skills/**` or `.opencode/skills/**` as
   a separate adapter-contract change with runtime-specific verification.

## Acceptance Note

Plan C item 80 required an analysis report only. This file satisfies that item;
no moves or deletes were performed.
