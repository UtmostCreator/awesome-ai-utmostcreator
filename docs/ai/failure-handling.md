# Failure Handling

Failures are part of the execution evidence. Treat them as signals, not as a
reason to broaden scope silently.

## Stop Conditions

Stop and report when the same check fails repeatedly, when a fix would exceed the
bounded task, or when the next step needs approval.

## Evidence To Preserve

Record the exact command, exit status, relevant output, and whether the failure
appears inside or outside the current slice. Do not hide failed checks in the
final handoff.

## Safe Recovery

Prefer the smallest diagnostic step that reduces uncertainty. Avoid destructive
cleanup, broad resets, package installs, or generated rewrites unless the user has
approved the exact recovery action.

## Reporting Language

Use `failed-verification` when a required check failed. Use `partially-verified`
when narrower checks passed but the full requested verification was not green.
Use `unknown` when evidence does not prove the claim.
