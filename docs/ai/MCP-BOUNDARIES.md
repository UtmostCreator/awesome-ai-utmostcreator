# MCP Boundaries

MCP extends capability, but also risk.

## Rules

- keep an allowlist of approved MCP servers
- document what systems each server can touch
- separate read-only and mutating servers when possible
- require stronger approval for production-facing or billing-sensitive systems
- do not imply MCP availability if the runtime is not configured for it

## Recommended Documentation

For each MCP integration, record:

- server name
- purpose
- environments reachable
- read-only or mutating posture
- approval requirements
- fallback behavior if unavailable

## See Also

- `../foundations/CONTROL-MODEL.md` — advisory vs deterministic controls and MCP boundary model
- `../foundations/COMPATIBILITY.md` — surface-dependent MCP availability
- `GOVERNANCE.md` — approval requirements for external system access
- `HOOKS-AND-ENFORCEMENT.md` — deterministic enforcement alongside MCP boundaries
