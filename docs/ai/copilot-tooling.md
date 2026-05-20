# Copilot Tooling

Copilot instructions should route shell work through approved scripts and respect `.github/hooks/tool-policy.json` where supported.

## Supported Script Layer

Use the registered wrappers before ad hoc terminal commands:

- `scripts/ai/common.sh` — shared policy, logging, redaction, timeout, and repository helpers.
- `scripts/ai/ai-search.sh` — bounded repository search and file discovery.
- `scripts/ai/rg-code.sh` — focused ripgrep wrapper for code/text evidence.
- `scripts/ai/gh-pr-context.sh` — pull request metadata, checks, reviews, and diff context.
- `scripts/ai/ai-diff-context.sh` — focused diff and change-context bundles.
- `scripts/ai/ai-verify.sh` — project-aware verification gate.
- `scripts/ai/ai-edit.sh` — guarded edit wrapper with snapshots and dry-run support.
- `scripts/ai/ai-rollback.sh` — explicit recovery from guarded edit snapshots.

## Stronger Tool Layer Contracts

- Context routers may emit `bundle-plan.json` so agents can explain which bundles were selected.
- Watch/debounce flows use `WATCH_DEBOUNCE_MS` when supported by the runtime wrapper.
- Evidence hooks should normalize failures with `failureCategory` for reviewable logs.
