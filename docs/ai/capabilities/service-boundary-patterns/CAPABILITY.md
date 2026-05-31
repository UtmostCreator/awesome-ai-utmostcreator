# Service Boundary Patterns

Use this capability when a change crosses repository, package, API, adapter, data, or internal tooling boundaries.

## Purpose

Keep one owner for each contract and avoid parallel implementations that drift.

## Trigger When

- a change adds or changes an API, package boundary, adapter surface, generated
  artifact, CLI contract, or installer path
- data ownership, persistence shape, secrets flow, or external integration
  behavior might change
- a proposal duplicates existing logic instead of extending the current owner
- a workflow or AI runtime surface may need provider-specific rendering

## Do Not Use For

- isolated implementation details with no public or cross-package contract
- purely local refactors that preserve all call sites and generated outputs
- broad rewrites without an approved architecture plan

## Required Checks

1. Identify the source-of-truth file or owner for the boundary.
2. Search for adjacent patterns before adding new logic.
3. Prefer extending the existing owner over creating a parallel path.
4. Keep provider-specific differences in mappings or renderers, not copied
   policy bodies.
5. Update tests, validators, docs, and generated catalogs when the boundary
   changes.
6. State rollback posture for medium or high risk boundary changes.

## Read Next

- `service-boundaries.md` for API/package/adapter ownership
- `data-boundaries.md` for state, secrets, fixtures, and generated artifacts
- `internal-tool-surfaces.md` for scripts, validators, installers, and AI
  runtime surfaces

## Output Contract

- boundary owner or `unknown`
- affected contracts and source-of-truth files
- existing pattern to reuse or reason reuse is unsafe
- required tests, validators, docs, and catalog updates
- release or rollback notes when risk is medium or high
