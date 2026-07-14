---
name: handoff
description: Transfer control to the next agent with a validated, contract-bearing handoff instead of a prose recommendation
argument-hint: 'goto=<next-agent> [status=ok|blocked] — the role to hand off to'
---

## What I Do

I transfer control from the current agent to the next using the shared, provider-agnostic handoff protocol: assemble a `HandoffPayload`, emit a structured `handoff` block, validate the transfer against the routing contract with the dispatcher, and only then route — so no agent hands off on prose alone.

## When To Use Me

- when an agent finishes its bounded slice and a different role must take over
- when a stop condition fired and you must escalate to `orchestrator` with `status: blocked`
- whenever you would otherwise write "recommended next step: <agent>" — run this instead
- to check whether a transfer is legal before performing it

## Read Alongside

- `handoff/generated/HANDOFF-PROTOCOL.md` — the authoritative routing table and rules
- `handoff/agent-handoff.yaml` — the handoff contract and `HandoffPayload` schema
- `docs/ai/handoff-contract.md` — the minimum preserved payload

## Flow

1. Assemble the `HandoffPayload`: `provide`, `produce`, `avoid`, `acceptance`, `evidence`, `stop_conditions`, `failure_route`, `authority`, `security`, `budget`, plus a `human_summary` (<=6 lines: what changed or was found, why, what is verified, what is still open, who is next).
2. Emit the transfer block with `from`, `goto` (a role in your `allowed_next`, or `orchestrator`, or `done`), `status` (`ok` or `blocked`), `contract_id`, `payload_ref`, and `human_summary`.
3. Validate: `python handoff/dispatch.py --from <this-agent-id> --goto <goto>`.
4. Exit 0 routes to `goto`; exit 1 is a rejection — re-emit with `goto: orchestrator`, `status: blocked`, and never force an illegal transfer.

## Output

- the emitted `handoff` block
- the dispatcher decision (ROUTE or REJECT) and exit code
- the next agent, or an escalation to `orchestrator` when blocked

## Gotchas

- a `goto` outside your `allowed_next` is rejected — escalate to `orchestrator`, do not override
- always carry `human_summary`; a reviewer must be able to own the merge from it alone
- never include secrets or sensitive file contents in the payload or summary
- `release-auditor` is terminal — finish with `goto: done`, not another agent
