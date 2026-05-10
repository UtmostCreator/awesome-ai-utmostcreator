# Release Safety Examples

## Example: Shared API Contract Change

- Rollout: deploy producer before consumer if compatibility is additive
- Rollback: keep previous field path available until consumers are updated
- Signal: dashboard for response errors plus smoke request against the changed endpoint

## Example: Risky Migration

- Rollout: expand first, backfill separately, contract later
- Rollback: pause after expand if post-release metrics regress
- Signal: migration completion metrics and request latency checks
