# Action: Use AI Script

Use this action when a task requires running one of the registered `scripts/ai/` wrappers.

## Lookup order

1. Resolve the intent against `docs/ai/tools/tool-map.md`.
2. Look up the script in `docs/ai/script-registry.json` (validated by `docs/ai/script-registry.schema.json`).
3. Cross-check approval class in `docs/ai/approval-boundaries.md`.
4. Confirm the runtime adapter wiring through `docs/ai/tools/ai-search.md`, `docs/ai/tools/actions/preview-file.md`, or the relevant action doc.

## Invocation pattern

```bash
bash scripts/ai/SCRIPT_NAME.sh [arguments]
```

For structured evidence, prefer the JSON output mode where supported:

```bash
AI_OUTPUT=json bash scripts/ai/SCRIPT_NAME.sh [arguments]
```

## Approval rules

Scripts classified as `medium` or `high` risk in the registry require explicit approval before each run. Do not invoke them without confirming:

- the change is in scope
- a rollback or disable path is documented when applicable
- secrets, deployment, and destructive operations remain gated

If the script emits `status: unsafe_blocked` or a `dry_run` preview that would otherwise mutate state, stop and escalate to the human.

## Evidence

Approved scripts write evidence under the local `.ai-logs/` evidence root (created at runtime). Reference the produced trace in the final task report.

## Related

- `docs/ai/script-registry.md`
- `docs/ai/script-registry.json`
- `docs/ai/tools/tool-map.md`
- `docs/ai/tools/ai-search.md`
- `docs/ai/tools/actions/preview-file.md`
- `docs/ai/tools/actions/search-evidence.md`
