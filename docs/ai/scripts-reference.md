# Scripts Reference

The approved script registry is defined by `tools/ai/install/script-registry.php` and installed as `docs/ai/script-registry.json`.

- `scripts/ai/run-repo-tests.sh` — single parallel-first repository test runner. Runs root ParaTest, shell suites, optional Bats/package suites, and validators. Read-only; default `PARATEST_PROCS=12`, cap `20`.
- `scripts/ai/prune-shipped-targets.sh` — kit-author cleanup of files duplicated from `packages/ai-universal-rules/templates/**`. Read-only modes: `--list`, `--dry-run`. Mutating: `--apply` (requires clean worktree, snapshots to `.ai-backups/prune-shipped-<ts>/`, logs to `.ai-logs/prune-<ts>.jsonl`). Restore: `bash install-ai-kit.sh .`.
