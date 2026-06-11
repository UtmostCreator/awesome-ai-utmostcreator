# Agent Script Access

Canonical per-script guidance for every agent. Each agent frontmatter lists all
`scripts/ai/*.sh` scripts explicitly as `allow` / `ask` / `deny`. Agent rules may
only **tighten** the global `opencode.jsonc` policy, never widen it. Out-of-role
scripts are set to `deny` in the agent.

Format: `script — when to use — why — expect after`.

## Read / research (low risk)

- `ai-search.sh` — find code, symbols, or evidence — unified safe search over raw grep/find — expect JSON envelope with `path`, `line`, `context` hits.
- `preview-file.sh` — read a file or range after a search hit — safe bounded reader (blocks binaries/secrets) — expect `path`, `range`, `content`, `truncated`.
- `rg-code.sh` — fast text/code regex search — ripgrep wrapper with repo defaults — expect matching file paths and line numbers.
- `fd-files.sh` — find files by name/pattern — fd wrapper — expect matching file paths.
- `query-usage.sh` — estimate the token/byte context cost of a PATH (file or directory) before a broad read — NOT a symbol search; the argument must be a real path, not an identifier (use `ai-search.sh` for symbol usage) — expect a byte/token estimate.

## Repo-aware read (low/medium risk)

- `git-branch-origin.sh` — resolve branch base/origin and ticket id — branch/scope grounding — expect base ref + branch metadata.
- `git-forensics.sh` — blame, history, and change forensics — investigate regressions/ownership — expect commit/blame summary.
- `gh-pr-context.sh` — pull-request context for review/release — PR-scoped facts — expect PR metadata and changed files. Review/release roles only.

## Stats, inventory, freshness (low/medium risk)

- `repo-stats.sh` — repository size/structure snapshot — quick repo shape — expect stats summary.
- `repo-tool-inventory.sh` — list/verify available repo tools — confirm tool wiring (`--check`) — expect inventory or drift report.
- `ai-file-freshness.sh` — check AI file freshness — detect stale generated/docs files — expect freshness status.
- `ai-install-coverage.sh` — check install/coverage of AI surface — bootstrap/post-install audit — expect coverage status.
- `check-file-refs.sh` — validate file references in docs/instructions — catch broken path refs — expect broken-reference list or OK.

## Context packing (low/medium risk)

- `pack-context.sh` — assemble a context bundle — research/review packing — expect packed bundle path. `ask` (cost).
- `run-repomix-context.sh` — run a repomix context pack — medium context packing — expect repomix output. `ask`.
- `repomix-context-tree.sh` — build a repomix context tree — structured large packing — expect tree artifact. `ask`.
- `repomix-scc-router.sh` — ranked context routing via scc — prioritized packing — expect ranked context. `ask`.
- `repomix-freshness.sh` — check repomix cache freshness — cheap freshness probe — expect fresh/stale status.
- `repomix-ensure-fresh.sh` — refresh repomix cache if stale — regenerates cache — expect refreshed cache. `ask` (writes cache).

## Diff and docs (low/medium risk)

- `ai-diff-context.sh` — bundle current diff context — review/implement grounding — expect diff context bundle.
- `ai-doc-check.sh` — doc lint / drift / budget checks — docs and workflow drift (`--check`) — expect lint/drift results.

## Verify and test (medium/high risk)

- `ai-verify.sh` — project verification of changed behavior — proof after edits (prefer `AI_VERIFY_SCOPE=changed`) — expect verification status; broad run is `ask`.
- `ai-test-select.sh` — select focused tests for a change — narrow-first testing — expect chosen test set.
- `run-repo-tests.sh` — run the repo test suites — affected-layer/full proof — expect pass/fail summary.

## Structured output and tasking (low/high risk)

- `ai-structured.sh` — emit/validate structured output — creator/validator structured specs — expect structured JSON.
- `ai-task.sh` — orchestrate a task/session — supervisor/creator orchestration — expect task state. High risk; supervisor/creator only.

## Guarded mutation (high risk — always `ask`, write roles only)

- `ai-edit.sh` — guarded AST/text edit — only when path-scoped `edit:` is insufficient — expect a tracked, reversible edit. Prefer native `edit:`.
- `ai-rollback.sh` — roll back the last guarded edit — recover from a bad guarded edit — expect restored prior state. Write roles + runtime guardian.
- `session-checkpoint.sh` — checkpoint session state — write/supervisor continuity — expect a session checkpoint.

## Hooks (high risk — guardian / supervisor only)

- `pre-tool-use.sh` — pre-execution policy gate — enforce policy before a tool runs — expect allow/deny decision. Runtime guardian/supervisor only.
- `post-tool-use.sh` — post-execution evidence writer — record evidence after a tool runs — expect an evidence event in `.ai-logs/`. Runtime guardian/supervisor only.

## Host / destructive (block by default)

- `install-mandatory-tools.sh` — installs host CLI tools — changes the host environment — `ask`; supervisor-approved only.
- `prune-shipped-targets.sh` — prune shipped target files — destructive removal — `--list`/`--dry-run` `allow`; `--apply` `ask`; never blind apply.
- `watch-loop.sh` — long-running watch loop — can hang/hide behavior — `deny` for normal agents.

## Library (never executed)

- `common.sh` — shared shell library — sourced by other scripts, not a command — `deny` (never direct-run).
