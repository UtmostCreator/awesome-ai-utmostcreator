# Scripts Reference

The approved script registry is defined by `tools/ai/install/script-registry.php` and installed as `docs/ai/script-registry.json`.

- `scripts/ai/prune-shipped-targets.sh` — kit-author cleanup of files duplicated from `packages/ai-universal-rules/templates/**`. Read-only modes: `--list`, `--dry-run`. Mutating: `--apply` (requires clean worktree, snapshots to `.ai-backups/prune-shipped-<ts>/`, logs to `.ai-logs/prune-<ts>.jsonl`). Restore: `bash install-ai-kit.sh .`.
