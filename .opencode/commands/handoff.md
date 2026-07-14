---
description: Transfer control to the next agent via the edgeless handoff contract
---
<!-- GENERATED from handoff/agent-handoff.yaml by gen_handoff.py — do not hand-edit. -->

# /handoff — edgeless agent handoff

Perform a provider-agnostic handoff to the next agent. Steps:

1. Emit a ```handoff``` block with fields: `from`, `goto`, `status`, `contract_id`, `payload_ref`, `human_summary`.
2. Set `goto` to a target allowed for your agent — see `handoff/generated/HANDOFF-PROTOCOL.md` (the allowed_next table).
3. Validate before transferring: `python handoff/dispatch.py --from <FROM> --goto <GOTO>` (replace `<FROM>`/`<GOTO>`).
4. On exit 0, route to `goto`. On exit 1 (REJECT), re-emit with `goto: orchestrator` and `status: blocked` — never force an illegal transfer.

Full rules: [`handoff/generated/HANDOFF-PROTOCOL.md`](handoff/generated/HANDOFF-PROTOCOL.md).
