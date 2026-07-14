<!-- GENERATED from handoff/agent-handoff.yaml by gen_handoff.py — do not hand-edit. -->

# Handoff Protocol (provider-agnostic)

No runtime (Claude Code, Copilot, OpenCode) enforces a typed agent-to-agent
handoff. This protocol makes handoffs portable: an agent **emits a block** and a
**shared dispatcher** enforces the transfer. Same behavior on every surface.

## 1. When you finish, emit a handoff block

````handoff
from: ...
goto: ...
status: ...
contract_id: ...
payload_ref: ...
human_summary: ...
````

- `from` — your agent id.
- `goto` — the next agent id (see allowed_next below), or `orchestrator` to escalate, or `done`.
- `status` — `ok` for a normal transfer, `blocked` when a stop_condition fired.
- `contract_id` — the handoff contract governing this transfer.
- `payload_ref` — path to the serialized `HandoffPayload`, or inline it.
- `human_summary` — <=6 lines a person can read to own the merge.

## 2. Validate the transfer (edgeless guardrail)

```bash
python handoff/dispatch.py --from <FROM> --goto <GOTO>
```

Exit `0` = **ROUTE** (goto is allowed). Exit `1` = **REJECT**: do not proceed —
re-emit with `goto: orchestrator` and `status: blocked`.

## 3. allowed_next — routing lives in the agent

A transfer is legal only if `goto` is in your row (or `orchestrator` / `done`).

| Agent | may `handoff goto` |
| --- | --- |
| `researcher` | `architect`, `implementer` |
| `architect` | `plan-writer`, `implementer` |
| `plan-writer` | `implementer` |
| `implementer` | `reviewer` |
| `configuration-maintainer` | `reviewer` |
| `reviewer` | `implementer`, `release-auditor` |
| `release-auditor` | _(exit only)_ |
| `agent-factory` | `agent-definition-reviewer` |
| `fleet-assessor` | `agent-definition-reviewer` |
| `agent-definition-reviewer` | `fleet-assessor` |
| `bootstrapper` | _(exit only)_ |
| `ui-builder` | `reviewer` |

## Failure & loops

- budget.max_review_fix_loops bounds the reviewer to implementer cycle
- a stop_condition halts an agent instead of transferring on ambiguity
- failure_route names the role that receives a failed handoff
- any agent may transfer back to the supervisor with status blocked
