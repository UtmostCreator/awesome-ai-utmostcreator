# Monorepo Strategy

Large repos need a nested strategy or the workflow becomes noisy.

## Root Layer

Keep these at the root when possible:

- broad policy
- organization-wide constraints
- shared project context sections
- cross-cutting capabilities

## Nested Layer

Add nested instructions or local capability guidance only when a package or service truly diverges.

Good reasons:

- different runtime or stack
- different deployment model
- different test commands
- different risk profile

## Non-Overlap Rule

- root files define broad defaults
- nested files define local exceptions or additions only
- do not restate the full root policy in every package

## Capability Placement

- keep shared workflows in a central `docs/ai/capabilities/`
- add package-local capability notes only when the workflow genuinely differs

## Debugging Nested Behavior

When behavior seems inconsistent, inspect:

- active working directory
- nearest instruction owner
- loaded path-specific rules
- whether the runtime supports nested behavior on the current surface

## See Also

- `../foundations/PRECEDENCE.md` — layering and non-overlap rules
- `../foundations/CAPABILITY-MODEL.md` — where to place shared vs local capability docs
- `TASK-ENTRYPOINTS.md` — which mechanism to use at which scope
- `RUNTIME-OBSERVABILITY.md` — how to debug nested instruction loading
