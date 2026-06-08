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

## How To Wire An MCP Server

No MCP server is configured by default. The runtime config is `opencode.jsonc`
(OpenCode) and the equivalent Copilot/Claude runtime config; MCP servers are declared
there under a top-level `mcp` block, then governed by the existing `permission` rules
in the same file.

Wiring checklist:

1. Add the server under the `mcp` key in `opencode.jsonc` (or your runtime's MCP config).
2. Declare its posture (read-only vs mutating) and the systems it can reach.
3. Add it to the approved allowlist and record it in the "Recommended Documentation" table above.
4. Gate mutating or production-facing servers behind explicit approval.
5. Keep secrets out of the config; reference environment variables, never inline credentials.

### Example: read-only SQL database replica

Use a read replica, never the primary, and keep the server read-only so the agent
cannot mutate production data.

```jsonc
// opencode.jsonc
{
  "mcp": {
    "sql-replica": {
      "type": "local",
      "command": ["mcp-server-postgres"],
      "environment": {
        // point at a READ REPLICA, credentials from env only
        "DATABASE_URL": "{env:SQL_REPLICA_READONLY_URL}"
      },
      "enabled": true
    }
  },
  "permission": {
    // keep the replica read-only; require approval for anything mutating
    "mcp": "ask"
  }
}
```

Posture to record for this example: read-only; reaches the analytics read replica
only; requires approval before any query that could be expensive or expose PII;
falls back to "MCP unavailable" (the agent must not fabricate query results) if the
server is down.

## See Also

- `../foundations/CONTROL-MODEL.md` — advisory vs deterministic controls and MCP boundary model
- `../foundations/COMPATIBILITY.md` — surface-dependent MCP availability
- `GOVERNANCE.md` — approval requirements for external system access
- `HOOKS-AND-ENFORCEMENT.md` — deterministic enforcement alongside MCP boundaries
