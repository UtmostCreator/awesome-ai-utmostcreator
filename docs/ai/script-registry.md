# Script Registry

This document summarizes the installed AI script surface. The executable source of truth is `tools/ai/install/script-registry.php`; `docs/ai/script-registry.json` is a generated projection of that registry, regenerated with `php tools/ai/ai.php registry:export --output docs/ai/script-registry.json` and drift-checked with `php tools/ai/ai.php registry:export --check`. Do not hand-edit the JSON.

## Autonomy Metadata

Registry entries include `autonomy_level` to make the maximum default execution posture machine-readable:

- `observe` — bounded read-only inspection.
- `advise` — analysis, verification, planning, or local evidence/artifact writes.
- `act_with_approval` — state-changing or repeated/delegated action that requires explicit human approval.
- `act_autonomously` — reserved for projects with explicit deterministic monitoring, audit, circuit-breaker, owner, and rollback controls.

Mutating entries must not be lower than `act_with_approval`. See `docs/ai/capabilities/authorization-and-tool-governance/CAPABILITY.md` for the canonical autonomy model.

## Notable Read-Only Scripts

- `run-repo-tests` (`scripts/ai/run-repo-tests.sh`) — single parallel-first repository test runner. It runs root PHP tests with ParaTest, shell suites through `tests/scripts/ai/run-all-tests.sh`, optional Bats tests, optional package ParaTest, and validators. Use `PARATEST_PROCS=12`; the script caps ParaTest workers at 20.
- `run-test-focused` (`scripts/ai/run-test-focused.sh`) — focused PHPUnit runner for a single `--filter <pattern>` or one test file, bounded by `TEST_TIMEOUT` (default 120s). Distinct from `run-repo-tests` (whole suite) and `ai-test-select` (selects only); use it to prove one class/method quickly.
- `run-repomix-file` (`scripts/ai/run-repomix-file.sh`) — exact single-file Repomix wrapper. Runs from a repository root, passes one relative file through `repomix --stdin`, defaults to `--compress --style xml`, and writes to the generated single-file context output area unless `--output` is provided.

## Manual-Only Per-Language Verification Wrappers

`ai-verify-html`, `ai-verify-js`, `ai-verify-php`, `ai-verify-ts`, and `ai-verify-vue` are
thin convenience wrappers around `ai-verify.sh --language <lang>`. They are **manual-only,
non-autowired scripts**: nothing in any `.opencode/agents/*`, `.github/agents/*`,
`tools/ai/install/permission-layers/*`, capability, command, skill, or hook references
them — a human must type the command directly. Each is `risk: read-only`,
`requires_approval: true`, and writes only bounded reports under a `verify` subdirectory of
`.ai-logs/` (created at runtime).

- `ai-verify-html` (`scripts/ai/ai-verify-html.sh`) — HTML-surface checks only.
- `ai-verify-js` (`scripts/ai/ai-verify-js.sh`) — JS-surface checks only.
- `ai-verify-php` (`scripts/ai/ai-verify-php.sh`) — PHP-surface checks only.
- `ai-verify-ts` (`scripts/ai/ai-verify-ts.sh`) — TS-surface checks only.
- `ai-verify-vue` (`scripts/ai/ai-verify-vue.sh`) — Vue-surface checks only.

## Notable Mutating Scripts

- `prune-shipped-targets` (`scripts/ai/prune-shipped-targets.sh`) — removes the kit-author's local copies of files installed from `packages/ai-universal-rules/templates/**`. `--list` and `--dry-run` are read-only; `--apply` requires a clean worktree (unless `--force`) and snapshots every path to `.ai-backups/prune-shipped-<ts>/` before deletion. Restore with `bash install-ai-kit.sh .`. See `docs/ai/capabilities/evidence-first-execution/examples.md` for the workflow.
