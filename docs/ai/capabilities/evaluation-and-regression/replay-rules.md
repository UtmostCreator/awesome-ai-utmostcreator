# Replay Rules

Use replay rules to make failed or ambiguous agent runs reproducible.

## Required Replay Inputs

- task request and risk classification
- trace identifiers when available (`trace_id`, `session_id`, `task_id`)
- tool sequence and key arguments (or argument hashes)
- policy decision points (allowed, denied, ask)
- failing check output or observed failure

## Replay Guidance

1. rerun with the same scope and constraints first
2. avoid adding tools or broader context until baseline replay is attempted
3. if replay diverges, record what changed (policy, context, runtime, or tool version)
4. classify the result as reproducible, non-reproducible, or blocked
5. if non-reproducible, escalate to human review for risky flows

## Stop Conditions

- do not loop the same replay without a new hypothesis
- stop after two equivalent failures and record blocker status
- treat high-risk non-reproducible behavior as human-review-required
