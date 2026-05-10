# Risk And Approvals

Use this model across runtimes.

## Risk Levels

- `low`: local, bounded, easy rollback, no shared contract or production posture change
- `medium`: broader verification needed, rollout or contract awareness matters
- `high`: destructive, security-sensitive, migration-heavy, or production-impacting

## Approval Gates

Always require explicit approval before:

- schema or data-shape changes
- auth or permission changes
- dependency or runtime upgrades with non-trivial impact
- destructive deletes or broad search-replace
- secrets, env, billing, or production config changes

Approval prompts are not a substitute for bounded tools, clear task scope, or deterministic enforcement where the runtime supports them.

## Medium Or High Risk Requirements

Before implementation or merge, state:

- rollback or disable path
- observability or smoke signal
- feature-flag posture when practical
- unresolved risks

## Approval Packet

Use `templates/shared/approvals/APPROVAL-PACKET.template.md` to standardize human signoff.

## See Also

- `AGENT-HANDOFFS.md` — which stages handle approvals and review
- `SYSTEM-WORKFLOW.md` — how risk classification fits into the lifecycle
- `../operations/GOVERNANCE.md` — governance goals and required controls
- `../../templates/shared/verification/VERIFICATION-EVIDENCE.template.md` — evidence template
