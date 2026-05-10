---
applyTo: '**'
description: 'Target, runtime, platform, adapter, and deployment-surface adaptation guidance'
---

# Target Rules

Use these rules when a repository supports multiple runtimes, platforms, apps, packages, adapters, or generated surfaces.

## Supported Targets

Supported targets: `<TARGET_PLATFORMS>`

Examples of targets may include:

- browser
- SSR or server
- CLI
- worker
- mobile or webview
- admin UI
- API
- package or library
- installer
- CI workflow
- Copilot adapter
- OpenCode adapter
- generated docs or catalog

## Scope Control

- Do not assume every task affects every target.
- Identify the affected target before planning edits.
- Keep target-specific behavior isolated when the repository already draws that boundary.
- If a change is intentionally target-specific, say so clearly.
- If behavior must stay consistent across targets, name the compatibility requirement.

## Cross-Target Safety

Before editing shared code, check:

- which targets import it
- whether it runs at build time or runtime
- whether it runs client-side, server-side, or both
- whether target-specific environment variables are required
- whether generated outputs depend on it
- whether CI or release scripts depend on it

## Adapter Surfaces

For AI-adapter files:

- `.github/**`
- `.opencode/**`
- `AGENTS.md`
- `CLAUDE.md`
- `.claude/**`

Rules:

- Keep adapters thin.
- Do not duplicate long canonical procedures.
- Point adapters to `docs/ai/**` canonical docs.
- Preserve adapter-specific syntax and capability limits.
- If an adapter cannot support a feature, define fallback behavior.

## Generated Target Surfaces

For generated outputs:

- Do not manually edit generated files unless the generated-artifacts policy allows it.
- Update the generator or source file first.
- Run the relevant generator check after changing generation input.
- Record drift if generated output intentionally differs.

## Output Requirement

For target-sensitive changes, report:

- affected targets
- unaffected targets
- shared files touched
- target-specific behavior
- compatibility risk
- verification command per affected target
