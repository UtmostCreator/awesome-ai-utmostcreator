# Scripts Reference

AI-facing reference for the installed `scripts/ai/*.sh` helper surface. The source of truth is `tools/ai/install/script-registry.php`; `docs/ai/script-registry.json` mirrors installed script paths for validators and adapter allowlists.

## Best Practices For AI Help

- Look up the script in `docs/ai/script-registry.json` before running it, then prefer the narrowest script that matches the task.
- Prefer `--help` or `-h` before first use where the table says help is supported.
- Prefer `AI_OUTPUT=json` where supported by the script or its docs; JSON output is easier to cite as bounded evidence.
- Do not run mutating, installer, rollback, watch, or approval-gated scripts without explicit approval. Use dry-run/list/check modes first when available.
- No-argument scripts with no help are documented below. `common.sh` is a sourced helper library, not a runnable task.

## Registry Scripts

All scripts ship from and install to the listed path in `scripts-pack` unless noted otherwise.

| Script | When to use | Why it exists / usefulness | Produces or changes | Help/support |
| --- | --- | --- | --- | --- |
| `common.sh` | Source from other AI scripts only. | Centralizes shared guards, logging, JSON, git, and rollback helpers. | Library functions; not a runnable task. | No CLI help; inspect callers. |
| `ai-search.sh` | Search changed, staged, tracked, text, files, or structural evidence. | Gives agents a bounded repository search entrypoint before broad reads. | Text or JSON hit envelopes; read-only. | `--help`/`-h`; supports `AI_OUTPUT=json`. |
| `rg-code.sh` | Run code-focused ripgrep with common language filters. | Keeps search patterns consistent and excludes noisy surfaces. | Search results; read-only. | `--help`/`-h`. |
| `fd-files.sh` | Find files by query, extension, or hidden-file mode. | Provides portable file discovery with optional JSON. | File lists or JSON; read-only. | `--help`/`-h`; supports `--json`. |
| `preview-file.sh` | Preview a safe file range after search finds a path/line. | Avoids unbounded dumps, binary files, and unsafe paths. | Bounded text or JSON preview; read-only. | `--help`/`-h`; supports `AI_OUTPUT=json`. |
| `query-usage.sh` | Estimate the token/byte context cost of a PATH (file or directory). | Budgets context packs before broad reads. NOT a symbol search; pass a real path, not an identifier. For symbol usage use `ai-search.sh`. | Token/byte estimate; read-only. | `--help`/`-h`. |
| `git-forensics.sh` | Inspect git history/blame for a path or symbol. | Grounds regressions and ownership questions in history. | Git evidence; read-only. | `--help`/`-h`. |
| `git-branch-origin.sh` | Infer the branch origin, merge base, or commit distance. | Helps reviewers pick a likely base when branch ancestry is unknown. | Branch/base/count text or JSON; read-only. Default registry args: `--json`. | `--help`/`-h`; supports `--json`. |
| `gh-pr-context.sh` | Collect PR metadata, diffs, checks, reviews, or packs. | Gives bounded GitHub PR context when `gh` is available. | PR context text/JSON; read-only remote queries. | `--help`/`-h`; supports `--json`. |
| `ai-doc-check.sh` | Check AI docs, references, and generated-doc consistency. | Catches stale docs and adapter drift before handoff. | Check report; `--dry-run`/`--check` are read-only. | `--help`/`-h`; supports check/dry-run modes. |
| `ai-diff-context.sh` | Extract context around current diffs. | Packages review evidence without reading unrelated files. | Diff context bundle; read-only. | `--help`/`-h`. |
| `ai-verify.sh` | Run the repository verification wrapper when approval allows. | Provides a single higher-level proof path after focused checks. | Verification report; read-only but ask-gated by policy. | `--help`/`-h`. |
| `ai-rollback.sh` | Restore guarded edit snapshots or rollback AI changes. | Provides a reversible escape hatch for approved mutations. | May modify files and write logs/generated rollback evidence. | `--help`/`-h`; approval required. |
| `ai-edit.sh` | Perform scoped text or AST edits through guarded wrappers. | Adds policy and rollback discipline around bulk edits. | Mutates repo-scoped files; may write evidence. | `--help`/`-h`; dry-run/approval required. |
| `pre-tool-use.sh` | Evaluate a shell/tool command before execution. | Canonical policy gate for allowed, ask, and deny command tiers. | Policy decision JSON/text; read-only. | `--help`/`-h`. |
| `post-tool-use.sh` | Record evidence after a command. | Keeps local execution evidence in `.ai-logs/`. | Writes `.ai-logs/` evidence records. | `--help`/`-h`. |
| `run-repomix-context.sh` | Generate a repository Repomix context bundle. | Produces compact context for advisor/review workflows. | Repomix output under context/generated areas; dry-run supported. | `--help`/`-h`. |
| `run-repomix-file.sh` | Pack exactly one file with Repomix. | Gives precise single-file context without broad packing. | Single-file Repomix bundle; defaults to generated output unless `--output` is supplied. | `--help`/`-h`; dry-run supported. |
| `repomix-context-tree.sh` | Analyze or render a Repomix context tree. | Summarizes repository context shape before large packs. | Analysis/tree output; dry-run supported. | `--help`/`-h`. |
| `repomix-scc-router.sh` | Create SCC-ranked Repomix context. | Prioritizes high-value files and avoids oversized context packs. | Ranked context output; dry-run supported. | `--help`/`-h`. |
| `repomix-freshness.sh` | Check whether Repomix context is stale. | Warns or blocks agents from using outdated context. | Freshness status, often JSON; read-only. | `--help`/`-h`; supports JSON-style output. |
| `repomix-ensure-fresh.sh` | Ask-gated check/regeneration helper for Repomix context. | Keeps context current while making regeneration explicit. | May write `.repomix-context/`; approval recommended. | `--help`/`-h`. |
| `pack-context.sh` | Pack a task-specific AI context bundle. | Creates bounded context for planning or review. | Context bundle output; dry-run supported. | `--help`/`-h`. |
| `ai-structured.sh` | Emit structured output from supported inputs such as CSV. | Normalizes machine-readable snippets for AI consumption. | Structured text/JSON-like output; read-only. | `--help`/`-h`. |
| `ai-task.sh` | Generate or inspect task-context evidence. | Establishes task scope before non-trivial edits. | Task-context output/artifacts; read-only registry risk. | `--help`/`-h`. |
| `ai-test-select.sh` | Select focused tests for a diff or touched path. | Helps choose smallest useful verification. | Test recommendation output; read-only. | `--help`/`-h`. |
| `run-repo-tests.sh` | Run repository test suites with parallel-first defaults. | Standardizes broad verification when focused checks are insufficient. | Test output; read-only; uses temp logs. | No dedicated help; run only when broad tests are warranted. |
| `session-checkpoint.sh` | Save checkpoint notes for a long AI session. | Preserves continuity across handoffs or context resets. | Writes checkpoint artifacts/logs; dry-run default in registry. | `--help`/`-h`; approval recommended for writes. |
| `ai-file-freshness.sh` | Show dirty AI/docs/runtime files. | Quick stale-surface check before edits or handoff. | `git status --short docs .github .opencode AGENTS.md`; read-only. | No dedicated help; no arguments. |
| `ai-install-coverage.sh` | Run strict install-surface validation. | Confirms installed script/docs/config surfaces remain registered. | Validation output; read-only. | No dedicated help; no arguments. |
| `check-file-refs.sh` | Validate documentation file references. | Finds broken paths in AI docs before handoff. | Reference check report; read-only. | `--help`/`-h`. |
| `repo-stats.sh` | Count tracked repository files. | Quick repository-size signal for context planning. | `git ls-files` count; read-only. | No dedicated help; no arguments. |
| `repo-tool-inventory.sh` | Generate or check required tool inventory docs. | Keeps tool requirements discoverable after install. | Delegates to `tools/ai/repo-tool-inventory.php`; may check or write depending on args. | No shell help; pass PHP tool args such as `--check`. |
| `install-mandatory-tools.sh` | Install mandatory CLI tools for this AI kit. | Bootstraps required commands across OSes. | Mutates host tools unless `--dry-run`; source-repo-only. | No dedicated help; use `--dry-run` first and require approval. |
| `watch-loop.sh` | Re-run a command under `watchexec` or `entr`. | Supports human-supervised iterative checks. | Long-running watcher; writes evidence under `.ai-logs/`. | No dedicated help; approval recommended. |
| `prune-shipped-targets.sh` | List or prune files duplicated from shipped templates. | Source-repo maintenance cleanup for generated/template drift. | `--list`/`--dry-run` read-only; `--apply` mutates files, writes backup snapshots, and logs to `.ai-logs/`. | `--help`/`-h`; approval required for `--apply`. |
