# Authorization And Tool Governance

Use policy gates and approval boundaries before running commands that mutate state or broaden access.

## Autonomy Levels

Use these levels to describe what an AI agent or script is allowed to do. They do not replace command-risk tiers; they make the access model explicit before execution.

| Level | Name | Allowed behavior | Required controls |
| --- | --- | --- | --- |
| 1 | Observe | Read bounded repository data and return output to the requesting user. No writes, external side effects, or hidden delegation. | Approved read-only tools, bounded output, secret-path protections. |
| 2 | Advise | Analyze, verify, generate plans, or propose actions. May write evidence or generated diagnostics only to documented local artifact paths. | Evidence path, verification status, and no source or external-system mutation. |
| 3 | Act with Approval | Mutate repository files, restore rollback snapshots, install tools, or perform other state-changing work after explicit human approval for that action. | Approval boundary, scoped diff, rollback or disable path when applicable, focused verification. |
| 4 | Act Autonomously | Execute state-changing actions without per-action approval. | Not a default repo mode. Requires named owner, deterministic guardrails, audit logs, monitoring, threshold-based stop/circuit breaker, and rapid rollback path. |

Default to the lowest level that can complete the task. If a requested workflow crosses levels, stop at the boundary and ask before continuing.

## Mapping To Existing Controls

| Existing control | Default autonomy level | Notes |
| --- | --- | --- |
| `docs/ai/command-policy.tiers.yaml` tier 1 read-only commands | Level 1: Observe | Search, preview, git status/diff/log, and bounded validation are read-only by policy. |
| Verification, context packing, and advisory reports | Level 2: Advise | These may write local evidence or generated diagnostics but should not mutate source behavior. |
| `ai-edit`, `ai-rollback apply`, installers, generated-artifact rewrites, destructive git, or dependency installs | Level 3: Act with Approval | Approval is required before each state-changing action. |
| Background or repeated execution such as watch/delegation loops | Level 3 unless proven read-only | Treat as approval-gated when it can trigger mutations or hide repeated actions. |
| Autonomous mutation without per-action approval | Level 4: Act Autonomously | Unsupported unless a project explicitly installs deterministic monitoring, stop rules, audit, and rollback controls. |

## Machine-Readable Registry

Script registries may use an `autonomy_level` field with one of:

- `observe`
- `advise`
- `act_with_approval`
- `act_autonomously`

The field must not understate risk. Any script with `mutates_state: true`, `risk: mutating`, or `requires_approval: true` should be `act_with_approval` unless the project has explicitly approved Level 4 controls.

## Implementation Rules

- Do not infer Level 4 from a tool being useful or low friction.
- Do not treat prompt instructions as enforcement. Use hooks, tool policy, allowlists, approval packets, and logs where deterministic control is required.
- Keep generated artifacts and evidence paths separate from source edits.
- For medium or high-risk changes, pair approval with release-safety notes: rollback path, success signal, and unresolved risk.

## Related Controls

- `docs/ai/approval-boundaries.md`
- `docs/ai/command-policy.tiers.yaml`
- `docs/ai/tool-policy.md`
- `docs/ai/capabilities/agent-observability-and-evidence/CAPABILITY.md`
- `docs/ai/capabilities/release-safety/CAPABILITY.md`
- `scripts/ai/pre-tool-use.sh`
- `scripts/ai/post-tool-use.sh`
