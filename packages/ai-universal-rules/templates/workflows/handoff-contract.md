---
name: handoff-contract
description: Use to validate a handoff carries provide/produce/avoid plus authority, evidence, budget, stop conditions, security, failure routing, and a human_summary before transferring
argument-hint: 'Describe the handoff you are about to make (from-role and to-role)'
---

## What I Do

I validate that a handoff is complete and safe before control transfers: every required contract field is present and non-empty, a `human_summary` is attached, no secrets leak, and the chosen `goto` is a legal next hop. A prose "recommended next step" is not a handoff — this is the gate that turns one into a real transfer.

## When To Use Me

- before emitting any `handoff` block or transferring to another role
- when assembling a `HandoffPayload` and you need the exact required-field list
- when a stop condition fired and you must escalate with `status: blocked`
- to reject an incoming handoff that is missing fields or carries secrets

## Read Alongside

- `handoff/generated/HANDOFF-PROTOCOL.md`
- `handoff/agent-handoff.yaml`
- `docs/ai/handoff-contract.md`

## Steps

1. Assemble the contract fields and confirm none is empty: `provide`, `produce`, `avoid`, `acceptance`, `evidence`, `stop_conditions`, `failure_route`, `authority`, `security`, `budget` (plus the identity fields `id`, `from`, `to`, `trigger`). A missing or empty core field is a reject.
2. Attach a `human_summary` (<=6 lines) stating what changed or was found, why, what is verified, what is still open, and who is next. No summary, no handoff.
3. Scan every field and the summary for secrets or sensitive file contents. If any appear, reject and report only the path plus the required owner action — never the value.
4. Confirm the transfer is legal: `python handoff/dispatch.py --from <from-agent> --goto <goto>`. Exit 0 routes to `goto`; exit 1 is a reject — re-emit with `goto: orchestrator` and `status: blocked`.

## Output

- a pass or reject verdict that names every missing or empty field
- the validated `HandoffPayload` field list plus the `human_summary`
- the dispatcher decision (ROUTE or REJECT) and its exit code

## Gotchas

- provide/produce/avoid are necessary but NOT sufficient — authority, security, budget, stop_conditions, and failure_route are required too
- `human_summary` is required by the payload schema; a machine-only payload is incomplete
- a `goto` outside `allowed_next` is rejected — escalate to `orchestrator`, never override the dispatcher
- send references and deltas; never resend whole histories or large files
