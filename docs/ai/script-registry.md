# Script Registry

This document summarizes the installed AI script surface. The executable source of truth is `tools/ai/install/script-registry.php`; `docs/ai/script-registry.json` mirrors the install paths used by validators and installed projects.

## Notable Read-Only Scripts

- `run-repo-tests` (`scripts/ai/run-repo-tests.sh`) — single parallel-first repository test runner. It runs root PHP tests with ParaTest, shell suites through `tests/scripts/ai/run-all-tests.sh`, optional Bats tests, optional package ParaTest, and validators. Use `PARATEST_PROCS=12`; the script caps ParaTest workers at 20.

## Notable Mutating Scripts

- `prune-shipped-targets` (`scripts/ai/prune-shipped-targets.sh`) — removes the kit-author's local copies of files installed from `packages/ai-universal-rules/templates/**`. `--list` and `--dry-run` are read-only; `--apply` requires a clean worktree (unless `--force`) and snapshots every path to `.ai-backups/prune-shipped-<ts>/` before deletion. Restore with `bash install-ai-kit.sh .`. See `docs/ai/capabilities/evidence-first-execution/examples.md` for the workflow.
