# Hooks And Enforcement

Instructions are advisory. Hooks are enforcement.

## Use Hooks For

- secret scanning
- required formatting or validation
- risky command blocking
- release checkpoint reminders
- audit logging where supported

## Design Rule

If a repo says something must always happen, decide whether a runtime can actually enforce it.

- if yes: encode it in hooks or deterministic scripts
- if no: document it as advisory and require explicit evidence in summaries

## Minimum Enforcement Posture

- block or gate destructive actions
- require explicit verification commands for changed behavior
- scan for obvious secret leaks where the runtime supports it

## See Also

- `GOVERNANCE.md` — governance goals and required controls
- `../foundations/CONTROL-MODEL.md` — advisory vs deterministic controls
- `../workflows/RISK-AND-APPROVALS.md` — when approval gates are required
