# CLI Tool Map

Compact replacement map for AI agents.

Purpose: route agents to deterministic, fast, structured, repository-safe tools.

Use repository wrappers first when available.  
Use raw tools as fallback or when debugging wrappers.

---

## Rules

- Read-only discovery first.
- Repository wrappers before raw tools.
- Structured output before text scraping.
- AST/semantic tools before regex for code structure.
- Project-defined commands before guessed commands.
- Bounded output before full-file or full-log dumps.
- Check/dry-run before write mode.
- Load `approval-required.md` before destructive, install, Git-history, service, infra, network-exec, environment-hook, or DB mutation commands.

---

## Default First Commands

Prefer separate commands over shell chaining.

| Need | Use |
|---|---|
| current repo state | `git status --short` then `git diff --stat` |
| changed files | `git diff --name-only` |
| staged files | `git diff --cached --name-only` |
| tracked files | `git ls-files` |
| task commands | `just --list`, `jq '.scripts' package.json`, `composer run-script --list` |
| repo size / language map | `scc .` |
| exact text search | `scripts/ai/ai-search.sh text "pattern"` |
| read file with lines | `scripts/ai/preview-file.sh path --lines 200` |
| review result | `git diff --check`, then `git diff --stat`, then `git diff` |
| verify result | `scripts/ai/ai-verify.sh .` |

---

## Wrapper Policy

Wrappers do not replace underlying tools.  
They standardize safe defaults, exclusions, output shape, logging, context limits, and approval boundaries.

Use raw tools only when:

- no wrapper exists
- the wrapper lacks the required mode
- debugging the wrapper itself
- the action guide explicitly recommends raw fallback

---

## Repository Wrappers First

| Need | Prefer | Fallback | Details |
|---|---|---|---|
| unified search | `scripts/ai/ai-search.sh` | `rg`, `fd`, `git grep`, `ast-grep` | `actions/text-search.md` |
| file discovery | `scripts/ai/fd-files.sh` | `fd`, `rg --files`, `git ls-files` | `actions/file-discovery.md` |
| text search | `scripts/ai/rg-code.sh` | `rg`, `git grep` | `actions/text-search.md` |
| file preview | `scripts/ai/preview-file.sh` | `bat -n --paging=never`, `sed -n` | `actions/file-viewing.md` |
| changed-file context | `scripts/ai/ai-diff-context.sh` | `repomix`, `files-to-prompt` | `actions/ai-context-packing.md` |
| repo context tree | `scripts/ai/repomix-context-tree.sh` | `repomix` | `actions/ai-context-packing.md` |
| context routing | `scripts/ai/repomix-scc-router.sh` | `scc`, `repomix` | `actions/ai-context-packing.md` |
| PR context | `scripts/ai/gh-pr-context.sh` | `gh pr view`, `gh pr diff`, `gh pr checks` | `actions/github-actions.md` |
| Git forensics | `scripts/ai/git-forensics.sh` | `git blame`, `git log -S`, `git log -G`, `git log -L` | `actions/git-diff-review.md` |
| broad edit | `scripts/ai/ai-edit.sh` | `git apply`, `ast-grep`, `sd`, `comby` | `tools/edit-sequence.md` |
| verification | `scripts/ai/ai-verify.sh` | project test/lint/typecheck commands | `actions/testing.md` |
| rollback | `scripts/ai/ai-rollback.sh` | manual patch/ref recovery | `approval-required.md` |
| checkpoint | `scripts/ai/session-checkpoint.sh` | manual `git diff --binary` snapshot | `tools/edit-sequence.md` |
| tool inventory | `scripts/ai/repo-tool-inventory.sh` | manual script scan | `actions/task-running.md` |
| tool usage logging | `scripts/ai/post-tool-use.sh` | manual log review | `actions/task-running.md` |
| command policy | `scripts/ai/pre-tool-use.sh` | manual approval review | `approval-required.md` |

---

## Missing Wrapper Fallbacks

Use raw tools until wrappers exist.

| Need | Current fallback | Planned wrapper |
|---|---|---|
| structured JSON/YAML/TOML/XML/CSV query | `jq`, `yq`, `mlr`, `csvcut`, `xmllint` | `scripts/ai/ai-structured.sh` |
| project task discovery | `just --list`, `jq '.scripts' package.json`, `composer run-script --list` | `scripts/ai/ai-task.sh` |
| focused test selection | `fd`, `rg`, framework conventions | `scripts/ai/ai-test-select.sh` |
| docs validation | `markdownlint`, `lychee` | `scripts/ai/ai-doc-check.sh` |
| runtime diagnosis | `docker compose ps`, bounded logs, `mysql-client` | `scripts/ai/ai-runtime.sh` |
| frontend verification | `eslint`, `tsc`, `vue-tsc`, `nuxi`, `graphql-codegen` | `scripts/ai/ai-frontend.sh` |

---

## Replacement Map

| Instead of | Prefer | Why | Details |
|---|---|---|---|
| `find . -name` | `scripts/ai/fd-files.sh` or `fd` | Fast, ignore-aware discovery | `actions/file-discovery.md` |
| `find . -type f` | `rg --files` or `git ls-files` | Searchable/tracked project files | `actions/file-discovery.md` |
| `ls` / `tree` | `eza -la --git`, `eza --tree --level=2` | Better structure view | `actions/file-discovery.md` |
| manual changed-file guessing | `git status --short`, `git diff --name-only` | Exact worktree scope | `actions/git-diff-review.md` |
| `grep -R` | `scripts/ai/rg-code.sh` or `rg -n` | Fast recursive search | `actions/text-search.md` |
| grep in Git repo | `git grep -n` | Tracked files only | `actions/text-search.md` |
| grep in PDFs/docs/archives | `rga` | Searches richer file types | `actions/text-search.md` |
| regex for code structure | `scripts/ai/ai-search.sh struct` or `ast-grep` / `sg` | AST-aware search/rewrite | `actions/code-structure-search.md` |
| manual security grep | `semgrep`, `gitleaks`, `trufflehog` | Purpose-built scanning | `actions/formatting-linting.md` |
| `cat` | `scripts/ai/preview-file.sh` or `bat -n --paging=never` | Bounded readable output | `actions/file-viewing.md` |
| reading huge or generated files | `rg` anchor plus `scripts/ai/preview-file.sh --range START:END --max-bytes N` | Bounded, gate-enforced context; reserve `--force` for exceptional, rationale-required bypass | `actions/file-viewing.md` |
| grep in JSON | `jq` | Structured JSON parsing | `actions/structured-data.md` |
| grep in YAML | `yq` | Structured YAML parsing | `actions/structured-data.md` |
| manual CSV parsing | `mlr`, `csvcut`, `csvgrep` | Structured CSV processing | `actions/structured-data.md` |
| raw `diff` | `difftastic` / `difft` | Syntax-aware diff | `actions/git-diff-review.md` |
| raw human `git diff` | `git diff` then optional `delta` | Better review output | `actions/git-diff-review.md` |
| guessing code history | `scripts/ai/git-forensics.sh` | Evidence from Git history | `actions/git-diff-review.md` |
| manual PR/CI lookup | `scripts/ai/gh-pr-context.sh` | Structured GitHub context | `actions/github-actions.md` |
| blind `sed -i` | `git apply --check` then `git apply` | Reviewable patching | `tools/edit-sequence.md` |
| broad regex rewrite | `rg` first, then targeted edit or `sg` rewrite | Prove scope before mutation | `tools/edit-sequence.md` |
| manual shell review | `bash -n`, `shellcheck` | Shell syntax and bug detection | `actions/shell-scripts.md` |
| manual shell formatting | `shfmt -d`, `shfmt -w` | Deterministic shell formatting | `actions/shell-scripts.md` |
| ad-hoc shell testing | `bats` | Shell test runner | `actions/shell-scripts.md` |
| manual workflow review | `actionlint` | GitHub Actions validation | `actions/github-actions.md` |
| manual link checking | `lychee` | Bulk link validation | `actions/formatting-linting.md` |
| manual Markdown lint | `markdownlint` | Markdown quality checks | `actions/formatting-linting.md` |
| manual PHP syntax review | `php -l` | PHP syntax validation | `actions/testing.md` |
| manual Composer review | `composer validate`, `composer audit` | PHP dependency/config checks | `actions/structured-data.md` |
| manual PHP static reasoning | `phpstan`, `psalm` | Static analysis | `actions/formatting-linting.md` |
| manual PHP formatting | `pint`, `php-cs-fixer` | Deterministic PHP formatting | `actions/formatting-linting.md` |
| guessing Laravel routes | `php artisan route:list` | Framework source of truth | `actions/services-runtime.md` |
| guessing Laravel migrations | `php artisan migrate:status` | Framework migration state | `actions/services-runtime.md` |
| manual JS/TS lint review | `eslint`, project lint script | Project-aware linting | `actions/formatting-linting.md` |
| manual JS/TS formatting | `prettier --check`, targeted `prettier --write` | Deterministic formatting | `actions/formatting-linting.md` |
| trusting editor types | `tsc --noEmit` | TypeScript verification | `actions/testing.md` |
| Vue type guessing | `vue-tsc --noEmit` | Vue SFC type checking | `actions/testing.md` |
| Nuxt type guessing | `nuxi typecheck` | Nuxt-aware type checking | `actions/testing.md` |
| manual browser testing | `playwright test`, `cypress run` | Automated browser verification | `actions/testing.md` |
| manual GraphQL type sync | `graphql-codegen` | Schema-to-types generation | `actions/code-structure-search.md` |
| manual GraphQL linting | `graphql-eslint` | Schema/query validation | `actions/formatting-linting.md` |
| command guessing | `just --list`, `jq '.scripts' package.json`, `composer run-script --list` | Discover project tasks | `actions/task-running.md` |
| manual rerun loop | `watchexec` | Rerun commands on file changes | `actions/task-running.md` |
| runtime guessing | `mise current` | Project runtime/tool versions | `actions/task-running.md` |
| blind env activation | `direnv status` before `direnv allow` | Inspect env hooks first | `actions/task-running.md` |
| manual service guessing | `docker compose ps`, bounded logs | Runtime source of truth | `actions/services-runtime.md` |
| huge log reading | `tail -100`, `rg`, `lnav` | Bounded runtime diagnosis | `actions/services-runtime.md` |
| GUI-only DB inspection | `mysql-client`, framework DB tools | Reproducible DB inspection | `actions/services-runtime.md` |
| dependency risk guessing | `composer audit`, `npm audit`, `pnpm audit` | Vulnerability checks | `actions/formatting-linting.md` |
| manual prompt copy | `scripts/ai/ai-diff-context.sh`, `repomix`, `files-to-prompt`, `code2prompt` | AI-ready context | `actions/ai-context-packing.md` |
| packing whole repo | `repomix-scc-router.sh`, `repomix-context-tree.sh`, then narrow include globs | Better signal-to-noise | `actions/ai-context-packing.md` |
| repo size guessing | `scc` | Context size planning | `actions/ai-context-packing.md` |

---

## AI Should Avoid Depending On

| Avoid as core automation | Prefer |
|---|---|
| `fzf` | direct `fd` / `rg` query |
| `yazi` | `fd`, `rg --files`, `bat` |
| `zoxide` | explicit repo-relative paths |
| `atuin` | project command discovery |
| `tmux` | direct command execution |
| `lazygit` | `git status`, `git diff`, `git log` |
| `btop` | bounded process/log commands |
| `neovim` | patch/script-based edits |
| `starship` | no agent dependency |
| `zsh-autosuggestions` | no agent dependency |
| `zsh-syntax-highlighting` | no agent dependency |

---

## Approval Gate

Load `approval-required.md` before:

| Category | Examples |
|---|---|
| destructive files | `rm -rf`, `find . -delete`, `git clean -fdx` |
| Git history/publishing | `git reset`, `git checkout`, `git switch`, `git merge`, `git rebase`, `git push` |
| dependency mutation | `npm install`, `pnpm install`, `pnpm add`, `composer update`, `composer require` |
| runtime/tool installs | `mise install`, `brew install`, `cargo install`, `uv tool install` |
| services/infrastructure | `docker compose up`, `docker compose down`, `docker system prune`, `colima start`, `sudo`, `ssh`, `scp` |
| network execution | `curl URL | sh`, `wget -O- URL | sh` |
| database mutation | `DELETE`, `UPDATE`, `TRUNCATE`, `DROP`, `ALTER`, migrations |
| environment hooks | `direnv allow` |
| rollback mutation | `scripts/ai/ai-rollback.sh apply`, `scripts/ai/ai-rollback.sh prune` |
| generated context deletion | `repomix-scc-router.sh clean/purge`, `repomix-context-tree.sh clean/purge` |

---

## Final Rule

```text
Read broadly.
Edit narrowly.
Verify locally.
Show the diff.
Do not delete, install, publish, migrate, or mutate services without approval.
```