# System Workflow

Use this document as the end-to-end operating model for the kit.

## Lifecycle

1. receive request
2. classify task type and risk level
3. load always-on policy and project context
4. choose the smallest fitting entry point
5. load the relevant capability or skill pack
6. delegate to staged agents if isolation helps
7. verify with evidence
8. inspect runtime state if behavior seems off
9. escalate for approval if destructive or high risk
10. summarize outcome with proof, not confidence language

## Task Classification

Use this quick routing table:

- unfamiliar area -> `project-context`
- existing diff review -> `review-diff`
- bug fix -> `bug-regression` then `verify-change`
- dependency change -> `dependency-upgrade`
- risky rollout or migration -> `release-safety`
- repeated one-off job -> prompt file or command first, then capabilities underneath

## Entry Point Rule

Choose the smallest entry point that fits the task:

- use instructions for broad stable policy
- use prompt files or commands for recurring one-off jobs
- use agents when stage isolation, tool boundaries, or handoffs matter
- use skills when deeper optional procedure should load only when relevant

Start with the simplest working path. Do not add more agents, handoffs, or orchestration layers until the smaller design stops meeting safety, clarity, or verification needs.

## Staged Agent Flow

Default staged flow for non-trivial work:

1. researcher or planner gathers context and defines scope
2. implementer applies the bounded change
3. reviewer audits in a fresh context
4. release auditor joins only for `medium` or `high` risk work

Do not force every task through every stage. Use the shortest safe sequence.

## Verification Rule

Use proof, not claims.

- bugs: reproduce first when practical
- changes: run the smallest meaningful verification first
- risky work: include rollback posture and post-change success signal
- summaries: separate commands actually run from recommended next checks
- evaluate real outcomes, not only convincing wording in the transcript
- inspect traces, tool calls, and handoffs when the failure is at workflow level rather than code level

## Drift Resistance

Watch for these failure modes continuously:

- hidden assumptions filling gaps in the spec
- plausible code with no proof
- long-session context pollution
- environment errors mistaken for reasoning errors
- hallucinated repo facts or APIs
- unsafe high-impact actions without gating
- over-exploration on narrow tasks
- loss of persistent repo memory

## Drift Resistance

Watch for these failure modes continuously:

- hidden assumptions filling gaps in the spec
- plausible code with no proof
- long-session context pollution
- environment errors mistaken for reasoning errors
- hallucinated repo facts or APIs
- unsafe high-impact actions without gating
- over-exploration on narrow tasks
- loss of persistent repo memory

## Runtime Debug Step

If the model behaves strangely:

1. inspect what instructions, prompts, agents, skills, hooks, and tools actually loaded
2. confirm the active working directory and repo root

## See Also

- `TASK-ENTRYPOINTS.md` — when to use each mechanism
- `AGENT-HANDOFFS.md` — staged agent handoff contract
- `RISK-AND-APPROVALS.md` — risk levels and approval gates
- `RUNTIME-OBSERVABILITY.md` — runtime inspection as a workflow step
- `../foundations/CAPABILITY-MODEL.md` — capability-first reusable workflow layer

3. confirm the task entry point matches the request type
4. confirm no conflicting instruction layers are active
5. restart with a narrower fresh context if the session has drifted
