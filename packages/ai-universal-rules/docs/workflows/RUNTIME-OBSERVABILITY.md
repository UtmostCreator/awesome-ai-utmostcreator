# Runtime Observability

Do not only ask whether files exist. Ask what actually loaded.

## Inspect At Runtime

- active repository root and working directory
- repo-wide instructions loaded
- path-specific instructions loaded
- prompt file or command entry point used
- active agent and tool permissions
- active skills or capability references
- hooks triggered
- MCP servers available

## Use This When

- the model ignores a rule that should apply
- a prompt file seems unavailable
- an agent behaves too broadly or too narrowly
- hooks or MCP integrations do not appear to run
- long sessions start drifting

## Debug Sequence

1. confirm runtime and surface
2. confirm what files loaded
3. confirm what tools the agent actually had
4. confirm there are no conflicting layers
5. restart in a fresh narrower session if drift is obvious

## Operational Rule

Runtime inspection is a first-class workflow step, not an afterthought.

## See Also

- `SYSTEM-WORKFLOW.md` — lifecycle step that includes runtime inspection
- `../operations/TROUBLESHOOTING.md` — common failure modes and first checks
- `../foundations/COMPATIBILITY.md` — surface limits that affect what loads
- `../foundations/CONTROL-MODEL.md` — advisory vs deterministic controls
