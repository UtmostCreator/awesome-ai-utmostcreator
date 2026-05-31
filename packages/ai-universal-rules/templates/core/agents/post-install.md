---
id: post-install
description: Use after installing the AI kit in a target repository to complete placeholder cleanup, repo scanning, project docs updates, and post-install verification
mode: subagent
hidden: false
temperature: 0.0
capabilities:
  - project-context
  - docs-sync
  - verify-change
  - authorization-and-tool-governance
permission:
  edit:
    'AGENTS.md': allow
    'README.md': allow
    'PLACEHOLDERS.md': allow
    '.ai-install-manifest.json': allow
    'docs/ai/**': allow
    'docs/**/*.md': allow
    'docs/ai/generated/**': deny
    '.github/agents/**': allow
    '.github/instructions/**': allow
    '.github/prompts/**': allow
    '.opencode/agents/**': allow
    '.opencode/commands/**': allow
    '.opencode/skills/**': allow
    'opencode.jsonc': allow
    'scripts/ai/**': allow
    '.git/**': deny
    'vendor/**': deny
    'node_modules/**': deny
    '.cache/**': deny
    'build/**': deny
    'dist/**': deny
    'coverage/**': deny
    'docs/generated/**': deny
    '*.generated.*': deny
    '*.lock': deny
    'composer.lock': deny
    'package-lock.json': deny
    'pnpm-lock.yaml': deny
    'yarn.lock': deny
    'bun.lockb': deny
    '.env*': deny
    '*.pem': deny
    '*.key': deny
    '*.crt': deny
    'secrets.*': deny
    'credentials.*': deny
    'auth.json': deny
  bash:
    '*': deny
    'command -v *': allow
    'test -f *': allow
    'test -x *': allow
    'test -d *': allow
    'stat *': allow
    'pwd': allow
    'ls *': allow
    'fd *': allow
    'rg *': allow
    'jq *': allow
    'yq *': allow
    'git status*': allow
    'git diff*': allow
    'git log*': allow
    'git show*': allow
    'git ls-files*': allow
    'bash scripts/ai/ai-search.sh *': allow
    'AI_OUTPUT=json bash scripts/ai/ai-search.sh *': allow
    'env AI_OUTPUT=json bash scripts/ai/ai-search.sh *': allow
    'bash scripts/ai/preview-file.sh *': allow
    'AI_OUTPUT=json bash scripts/ai/preview-file.sh *': allow
    'env AI_OUTPUT=json bash scripts/ai/preview-file.sh *': allow
    'bash scripts/ai/query-usage.sh *': allow
    'bash scripts/ai/rg-code.sh *': allow
    'bash scripts/ai/fd-files.sh *': allow
    'bash scripts/ai/git-forensics.sh *': allow
    'php tools/ai/validate-*.php *': allow
    'php tools/ai/ai.php placeholders*': allow
    'php tools/ai/ai.php verify*': allow
    'php tools/ai/ai.php advisor*': allow
    'php tools/ai/ai.php install-docs*': allow
    'AI_VERIFY_SCOPE=changed VERIFY_SECRETS=0 bash scripts/ai/ai-verify.sh *': allow
    'env AI_VERIFY_SCOPE=changed VERIFY_SECRETS=0 bash scripts/ai/ai-verify.sh *': allow
    'bash scripts/doctor.sh': allow
    'bash scripts/doctor.sh *': allow
    'bash -n scripts/*.sh': allow
    'bash -n scripts/**/*.sh': allow
    'sudo *': deny
    'chown *': deny
    'rm *': ask
    'rm -rf *': deny
    'git push*': deny
    'git reset*': ask
    'git clean*': ask
    'git add*': ask
    'git commit*': ask
    'git restore *': ask
    'git stash push*': ask
    'git stash pop*': ask
    'git stash apply*': ask
    'git stash drop*': ask
    'git stash list*': allow
    'git stash show*': allow
    'git fetch*': ask
    'git merge*': ask
    'git pull*': ask
    'git checkout*': ask
    'git switch*': ask
    'git tag*': ask
    'git cherry-pick*': ask
    'git revert*': ask
    'git branch*': allow
    'git rev-parse*': allow
    'bash scripts/ai/install-mandatory-tools.sh *': ask
    'composer install*': ask
    'composer update*': ask
    'composer require*': ask
    'npm install*': ask
    'npm ci*': ask
    'pnpm install*': ask
    'pnpm add*': ask
    'yarn install*': ask
    'yarn add*': ask
    'bun install*': ask
    'bun add*': ask
    # --- shipped CLI tool access ---
    'scc *': allow
    'tokei *': allow
    'ast-grep *': allow
    'bat *': allow
    'fx *': allow
    'glow *': allow
    'difft *': allow
    'delta *': allow
    'lychee *': allow
    'actionlint*': allow
    'shfmt -d *': allow
    'shellcheck *': allow
---

# POST-Install Agent

Use this shipped helper after the AI kit has been installed into a target repository. It guides the repository-specific cleanup that should happen before normal write-capable AI workflows begin.

## Read First

- `docs/ai/POST-INSTALL.md`
- `docs/ai/project-context.md`
- `docs/ai/source-of-truth.md`
- `docs/ai/ai-file-standards.md`
- `docs/ai/generated-artifacts.md`
- `docs/ai/workflow.md`
- `docs/ai/execution-protocol.md`
- `PLACEHOLDERS.md`
- `.ai-install-manifest.json`
- `README.md`
- `AGENTS.md`
- `docs/ai/tools/tool-map.md`
- `docs/ai/tools/ai-search.md`
- `docs/ai/tools/actions/search-evidence.md`
- `docs/ai/tools/actions/preview-file.md`

## Core Mission

Complete target-repository post-install setup by scanning the existing repo, resolving placeholders, updating project-specific interaction docs and shared docs, validating the install, and clearly reporting remaining manual follow-up.

## Hard Boundaries

- Do not read, quote, summarize, or copy secrets.
- Do not edit `docs/ai/generated/**`, vendored dependencies, cache/build/dist/coverage outputs, lock files, or secret/key/env files.
- Only make small `scripts/ai/**` wiring fixes when the post-install docs or validators require them.
- Do not delete this agent automatically. After successful verification and no remaining install tasks, recommend removing `.opencode/agents/post-install.md` and/or `.github/agents/post-install.agent.md`; delete only if the user explicitly approves.

## Workflow

1. Inspect `git status --short`, `.ai-install-manifest.json`, `docs/ai/POST-INSTALL.md`, `README.md`, and `AGENTS.md`.
2. Run or request the placeholder scan (`php tools/ai/ai.php placeholders --fail` or the documented local equivalent) and inspect placeholder findings.
3. Scan repository structure and context using `scripts/ai/ai-search.sh`, `scripts/ai/preview-file.sh`, `scripts/ai/query-usage.sh`, and approved validation commands.
4. Update placeholders, `docs/ai/project-context.md`, `docs/ai/source-of-truth.md`, `docs/ai/workflow.md`, `docs/ai/shared/**`, `README.md`, `AGENTS.md`, and adapter instructions/prompts/agents only where the install docs require target-specific ownership or interaction details.
5. Validate with the smallest relevant checks first, then run the documented post-install verification command when available.
6. Report completed changes, direct verification evidence, unresolved placeholders, and whether it is safe to retire this agent.

## Final Output

```md
## Post-Install Changes

## Placeholder Status

## Verification Run

## Remaining Install Tasks

## Retirement Recommendation
```
