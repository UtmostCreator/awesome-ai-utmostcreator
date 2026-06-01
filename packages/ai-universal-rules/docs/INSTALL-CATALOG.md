# Install Catalog

Deterministic catalog generated from installer registries.

## Profiles

- `minimal`: `base, setup-docs, capabilities-core`
- `copilot`: `minimal, adapter-copilot, scripts-pack, policy-pack, hooks-pack`
- `opencode`: `minimal, adapter-opencode, scripts-pack, policy-pack, hooks-pack`
- `dual`: `minimal, adapter-copilot, adapter-opencode, capabilities-extended, scripts-pack, policy-pack, hooks-pack`
- `guarded`: `dual, policy-pack, hooks-pack, evidence-pack`
- `accelerated`: `dual, scripts-pack, policy-pack, evidence-pack`
- `full-governance`: `accelerated, capabilities-governance, hooks-pack, ci-pack, docs-reference-pack, delivery-pack, optional-agents-opencode-pack, optional-agents-copilot-pack, preview-environments-pack, evaluation-pack, service-boundary-pack, mcp-boundaries-pack, advisor-pack, target-tools-pack, shared-templates-pack`
- `docs-reference`: `docs-reference-pack`
- `custom`: ``

## Packs

- `setup-docs` (25 items)
- `capabilities-core` (5 items)
- `base` (14 items)
- `adapter-copilot` (9 items)
- `adapter-opencode` (7 items)
- `capabilities-extended` (2 items)
- `capabilities-governance` (2 items)
- `policy-pack` (3 items)
- `scripts-pack` (39 items)
- `hooks-pack` (6 items)
- `ci-pack` (2 items)
- `evidence-pack` (2 items)
- `docs-reference-pack` (8 items)
- `delivery-pack` (2 items)
- `optional-agents-opencode-pack` (1 items)
- `optional-agents-copilot-pack` (1 items)
- `preview-environments-pack` (1 items)
- `evaluation-pack` (1 items)
- `service-boundary-pack` (1 items)
- `mcp-boundaries-pack` (1 items)
- `advisor-pack` (4 items)
- `target-tools-pack` (42 items)
- `shared-templates-pack` (4 items)
- `package-source-pack` (9 items)
- `kit-authoring-pack` (4 items)

## Script IDs

- `common` -> `scripts/ai/common.sh`
- `ai-search` -> `scripts/ai/ai-search.sh`
- `rg-code` -> `scripts/ai/rg-code.sh`
- `fd-files` -> `scripts/ai/fd-files.sh`
- `run-repo-tests` -> `scripts/ai/run-repo-tests.sh`
- `preview-file` -> `scripts/ai/preview-file.sh`
- `query-usage` -> `scripts/ai/query-usage.sh`
- `git-forensics` -> `scripts/ai/git-forensics.sh`
- `gh-pr-context` -> `scripts/ai/gh-pr-context.sh`
- `ai-doc-check` -> `scripts/ai/ai-doc-check.sh`
- `ai-diff-context` -> `scripts/ai/ai-diff-context.sh`
- `ai-verify` -> `scripts/ai/ai-verify.sh`
- `ai-rollback` -> `scripts/ai/ai-rollback.sh`
- `ai-edit` -> `scripts/ai/ai-edit.sh`
- `pre-tool-use` -> `scripts/ai/pre-tool-use.sh`
- `post-tool-use` -> `scripts/ai/post-tool-use.sh`
- `repomix-context` -> `scripts/ai/run-repomix-context.sh`
- `repomix-tree` -> `scripts/ai/repomix-context-tree.sh`
- `repomix-scc-router` -> `scripts/ai/repomix-scc-router.sh`
- `repomix-freshness` -> `scripts/ai/repomix-freshness.sh`
- `repomix-ensure-fresh` -> `scripts/ai/repomix-ensure-fresh.sh`
- `pack-context` -> `scripts/ai/pack-context.sh`
- `ai-structured` -> `scripts/ai/ai-structured.sh`
- `ai-task` -> `scripts/ai/ai-task.sh`
- `ai-test-select` -> `scripts/ai/ai-test-select.sh`
- `session-checkpoint` -> `scripts/ai/session-checkpoint.sh`
- `ai-file-freshness` -> `scripts/ai/ai-file-freshness.sh`
- `ai-install-coverage` -> `scripts/ai/ai-install-coverage.sh`
- `check-file-refs` -> `scripts/ai/check-file-refs.sh`
- `repo-stats` -> `scripts/ai/repo-stats.sh`
- `repo-tool-inventory` -> `scripts/ai/repo-tool-inventory.sh`
- `install-mandatory-tools` -> `scripts/ai/install-mandatory-tools.sh`
- `watch-loop` -> `scripts/ai/watch-loop.sh`
- `prune-shipped-targets` -> `scripts/ai/prune-shipped-targets.sh`

## Toolchain

- `bash`
- `git`
- `jq`
- `rg`
- `node`
- `npm`
- `repomix` (safe auto-install)
- `scc`
- `php`
- `fd`
- `ast-grep`
- `gh`
- `python3`
- `date`
- `shellcheck`
- `gitleaks`
- `yq`
