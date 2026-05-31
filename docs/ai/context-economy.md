# Context Economy

Use the smallest context that proves the work. This file is policy for keeping token and context usage bounded. It complements, and does not replace, `docs/ai/context-packing.md` (Repomix bundles and freshness) and the context-gate instruction surface.

## Core Rule

Before adding context, prove the model needs it. Prefer a bounded read over a broad pack, and a scoped command over an unscoped one.

## Search And Read Discipline

- Probe diff scope first: run `ai-search.sh` in `changed`, then `staged`, then `tracked` mode before broader `text`, `files`, `struct`, or `all` modes.
- Read with bounded previews: prefer `bash scripts/ai/preview-file.sh PATH --around LINE --context 30` or `--range A:B` over reading whole large, generated, or vendored files.
- Do not dump entire files when a range proves the point.

## Command Output Discipline

Shell output enters context verbatim, so cap or scope it before running:

- Prefer `git log --oneline -20` over unbounded `git log`.
- Scope searches to relevant paths and exclude `vendor/`, `node_modules/`, and generated bundles.
- Pipe long test or build output through `tail` to keep only the relevant tail unless full-log analysis is the task.
- Never inject full logs unless analyzing the full log is the task.

## Bounded Context Packing

- Use installed context scripts only for approved, bounded packing; never pack secrets, vendor directories, or generated bundles.
- Follow `docs/ai/context-packing.md` for Repomix freshness gating via `scripts/ai/repomix-freshness.sh` and `scripts/ai/repomix-ensure-fresh.sh`.
- Estimate cost before a broad pack with `bash scripts/ai/query-usage.sh PATH` and `php tools/ai/ai.php budget`.

## Session Discipline

- Keep one task per session where practical; start a fresh session for unrelated work rather than growing one long mixed-topic session.
- Produce a handoff before switching tasks or compacting, capturing exact paths, versions, decisions, and the next step (see `docs/ai/handoff-contract.md` and `docs/ai/session-reentry.md`).
- Load a given skill once per session where possible; repeated loads re-inject the full skill content.

## See Also

- `docs/ai/context-packing.md` - Repomix bundles, freshness, and regeneration.
- `docs/ai/source-of-truth.md` - evidence ordering and context authority.
- `docs/ai/handoff-contract.md` - handoff content before compaction or task switch.
