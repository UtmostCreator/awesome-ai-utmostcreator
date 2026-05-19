# Install Instructions

- Installed at: `2026-05-10T15:25:11+00:00`
- Profile: `full-governance`
- Packs: `capabilities-extended-full, hooks-pack, ci-pack, scripts-pack, policy-pack, evidence-pack, adapter-copilot, adapter-opencode, capabilities-extended-lite, base, setup-docs, capabilities-core, docs-reference-pack, delivery-pack, optional-agents-opencode-pack, optional-agents-copilot-pack, preview-environments-pack, evaluation-pack, service-boundary-pack, mcp-boundaries-pack, advisor-pack, target-tools-pack, shared-templates-pack`

## Step Chain

1. Step 1 -> Preflight: `php tools/ai/ai.php preflight`
   - Next: Step 2 (`package-verify`)
2. Step 2 -> Package Verify: `php tools/ai/ai.php package-verify`
   - Next: Step 3 (`adapter-plan`)
3. Step 3 -> Adapter Plan: `php tools/ai/ai.php adapter-plan --profile full-governance`
   - Next: Step 4 (`install --dry-run`)
4. Step 4 -> Install Dry-Run: `php tools/ai/ai.php install --profile full-governance --reinstall --dry-run`
   - Next: Step 5 (`install --backup-only`)
5. Step 5 -> Backup: `php tools/ai/ai.php install --backup-only --apply --profile full-governance --reinstall`
   - Next: Step 6 (`install --apply --backup <id>`)
6. Step 6 -> Apply: `php tools/ai/ai.php install --apply --profile full-governance --reinstall --backup <backup-id>`
   - Next: Step 7 (post-install verification sequence)

## Before Install

1. Run dry-run first.
2. Confirm profile and optional packs.
3. Check required tools for selected packs.

## During Install

- Dry-run: `php tools/ai/ai.php install --profile full-governance --reinstall --dry-run`
- Backup: `php tools/ai/ai.php install --backup-only --apply --profile full-governance --reinstall`
- Apply: `php tools/ai/ai.php install --apply --profile full-governance --reinstall --backup <backup-id>`

## Selective Updates

- Runtime-only refresh: `php tools/ai/ai.php install --profile full-governance --no-base --reinstall --dry-run`
- Add scripts pack: `php tools/ai/ai.php install --profile full-governance --with scripts-pack --reinstall --dry-run`
- Add advisor pack: `php tools/ai/ai.php install --profile full-governance --with advisor-pack --reinstall --dry-run`
- Create merge-safe upgrade copies instead of skipping collisions: `php tools/ai/install-ai-kit.php --target /path/to/repo --profile full-governance --upgrade-suffix=-upgrade`
- Remove an included pack for comparison: `php tools/ai/ai.php install --profile full-governance --without <pack-id> --reinstall --dry-run`
- Run a helper after apply: `php tools/ai/ai.php install --profile full-governance --reinstall --apply --run-after-install repomix-tree`

## After Install

- Verify: `php tools/ai/ai.php verify --json`
- Resolve placeholders: `php tools/ai/ai.php placeholders --fail`
- Toolchain check: `php tools/ai/ai.php toolchain --with repomix,scc --check`
- Required-tools inventory check: `bash scripts/ai/repo-tool-inventory.sh --check`
- Required-tools inventory regenerate: `bash scripts/ai/repo-tool-inventory.sh`
- Mandatory-tools installer dry-run: `bash scripts/ai/install-mandatory-tools.sh --dry-run`
- Script list: `php tools/ai/ai.php run-script --list`

- Repomix analyze: `bash scripts/ai/repomix-context-tree.sh analyze .`
- Advisor analyze/fixes: `php tools/ai/ai.php advisor --all`
- Full-install verifier: `php tools/ai/verify-full-install.php`

Advisor recommendations are strongest after a full OpenCode install and fresh Repomix analysis, because advisor consumes generated repository signals/context artifacts under `docs/ai/generated/`.

OpenCode agent visibility note: agents in `.opencode/agents/` must not be marked `hidden: true`; use `mode: all` for agents you want in Tab rotation and `mode: subagent` for specialist agents that should appear via `@` mentions.

## Completion Criteria

- Run `php tools/ai/verify-full-install.php` after the sequence above.
- Completion is `full` only when install, validation, repomix analysis, and advisor checks all pass in order.
- If status is not `full`, follow the script output for ordered remediation steps.

For broader operator recipes across Copilot, OpenCode, docs, scripts, hooks, advisor, and Repomix helpers, read `docs/ai/install-order.md`.

## Installed Scripts

- `common` -> `scripts/ai/common.sh`
- `ai-search` -> `scripts/ai/ai-search.sh`
- `rg-code` -> `scripts/ai/rg-code.sh`
- `fd-files` -> `scripts/ai/fd-files.sh`
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
- `setup-powershell-profile` -> `scripts/ai/setup-powershell-profile.ps1`
- `watch-loop` -> `scripts/ai/watch-loop.sh`

## Installed Files

- `.github/agents`
- `.github/copilot-instructions.md`
- `.github/hooks/scripts/tool-guardian.ps1`
- `.github/hooks/tool-guardian.json`
- `.github/hooks/tool-policy.json`
- `.github/instructions`
- `.github/instructions/execution-protocol.instructions.md`
- `.github/instructions/tools.instructions.md`
- `.github/prompts`
- `.github/pull_request_template.md`
- `.github/skills`
- `.github/workflows/export-ai-universal-rules-preview.yml`
- `.github/workflows/test-external-install.yml`
- `.github/workflows/validate-ai-surface.yml`
- `.opencode/agents-optional`
- `.vscode/settings.json`
- `docs/ai/AI-GUARDRAILS.md`
- `docs/ai/MCP-BOUNDARIES.md`
- `docs/ai/POST-INSTALL.md`
- `docs/ai/ai-file-standards.md`
- `docs/ai/capabilities/bug-regression`
- `docs/ai/capabilities/dependency-upgrade`
- `docs/ai/capabilities/evidence-first-execution`
- `docs/ai/capabilities/project-context`
- `docs/ai/capabilities/release-safety`
- `docs/ai/capabilities/review-diff`
- `docs/ai/capabilities/verify-change`
- `docs/ai/delivery/README.md`
- `docs/ai/delivery/slice-card.template.md`
- `docs/ai/execution-protocol.md`
- `docs/ai/generated-artifacts.md`
- `docs/ai/project-context-placeholders.md`
- `docs/ai/project-context.md`
- `docs/ai/project-stack.md`
- `docs/ai/shared/approvals`
- `docs/ai/shared/project-interaction.md`
- `docs/ai/shared/verification`
- `docs/ai/snippets`
- `docs/ai/tools/actions/preview-file.md`
- `docs/ai/workflow.md`
