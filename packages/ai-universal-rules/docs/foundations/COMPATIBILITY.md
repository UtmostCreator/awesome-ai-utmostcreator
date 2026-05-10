# Compatibility

This package is intentionally asymmetric.

OpenCode and GitHub Copilot overlap in concepts, but they do not expose the same workflow controls on every surface. The kit preserves a shared operating model and uses runtime adapters where support differs.

## Runtime Tiers

- `OpenCode`
- `GitHub Copilot in VS Code or CLI`
- `GitHub Copilot on GitHub.com`

## Shared Concepts

- one repository-level policy source
- path-scoped rules
- durable project context
- task entry points
- staged roles and handoffs
- reusable capabilities and deep procedure packs
- verification evidence and approval boundaries

## Important Surface Warning

Do not assume a workflow that works in one surface works identically in another.

- prompt files are strongest in VS Code-style Copilot surfaces and may not exist elsewhere
- custom-agent properties, handoffs, and tool limits differ by runtime
- hooks and MCP support are enablement-dependent
- GitHub.com generally exposes a smaller runtime surface than VS Code or CLI

## Practical Rule

- treat OpenCode as the explicit-control reference model
- treat GitHub Copilot as a surface-aware adapter model
- keep canonical workflow logic in capability folders and shared docs
- keep skills thin when advanced runtime behavior is not portable
- document a fallback for any preview-only or surface-dependent adapter

## Safe Default

If portability matters more than maximum customization:

1. start with core policy templates
2. add project context and capability folders
3. add only one runtime adapter first
4. add prompt files, specialist agents, hooks, and MCP only after documenting fallbacks

## See Also

- `../INSTALL-GITHUB-COPILOT.md` — Copilot surface install guide
- `../INSTALL-OPENCODE.md` — OpenCode surface install guide
- `CONTROL-MODEL.md` — advisory vs deterministic controls per runtime
- `../workflows/RUNTIME-OBSERVABILITY.md` — inspecting what actually loaded on a surface
