# Service Boundaries

Use this checklist before changing a contract that another package, runtime,
command, or generated output consumes.

## Ownership Rules

- Name the source-of-truth file before editing derived surfaces.
- Extend the existing owner instead of adding a second implementation.
- Keep orchestration and state ownership out of presentation or adapter files
  when a shared layer exists.
- If two providers differ only in names, paths, or frontmatter, use a renderer
  or mapping.
- If semantics differ, model the difference as a provider capability and
  document the fallback.

## Contract Checks

- Search call sites before changing a public symbol, path, schema key, command,
  or template location.
- Update all consumers in the same slice when a shared contract changes.
- Do not patch generated output unless the generator contract marks it as
  canonical.
- Keep adapter-specific files thin and point back to canonical docs or
  capabilities.

## Escalate When

- ownership is unclear
- more than one valid design has different compatibility trade-offs
- a path move would require broad shims or generated catalog updates
- external users, installers, CI, or runtime permissions could break
