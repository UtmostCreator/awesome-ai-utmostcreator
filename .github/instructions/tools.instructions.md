---
applyTo: '**'
description: 'Tool selection and script enforcement — use rg/fd/approved scripts; never use bare grep/find'
---

# Tool Selection Rules

## Surface-Aware Tool Routing

Different AI surfaces have different built-in capabilities:

- **Copilot VS Code**: Has built-in `grep_search`, `semantic_search`, `file_search`, `read_file`, `list_dir`. Use these first for search/read/list. Do not use `run_in_terminal` with `cat`/`grep`/`find`/`ls` pipelines for normal exploration. Use repository scripts for verification, validation, workflow, and git forensics.
- **OpenCode**: No built-in search/read tools. Use shell scripts for everything.
- **Claude Code**: Similar to OpenCode. Use shell scripts for everything.

## Required Tools

When searching code or files from a shell-capable surface, always prefer repository-aware tools:

- Use `rg` (ripgrep) instead of `grep` for code search
- Use `fd` instead of `find` for file discovery
- Use `ast-grep` / `sg` for structural code queries

When the repository provides wrapper scripts, use those in preference to direct tool invocation:

- `AI_OUTPUT=json bash scripts/ai/ai-search.sh text "<query>" . --fixed` — safe repository text search
- `bash scripts/ai/rg-code.sh "<pattern>" .` — code-specific rg wrapper
- `bash scripts/ai/fd-files.sh "<pattern>" .` — file discovery wrapper
- `AI_OUTPUT=json bash scripts/ai/preview-file.sh "<path>" --around <line> --context 30` — safe file preview
- `bash scripts/ai/query-usage.sh <path>` — estimate token/byte context cost of a PATH (file or dir); NOT a symbol search (use `ai-search.sh` for symbol usage)
- `bash scripts/ai/git-forensics.sh "<symbol-or-path>"` — git history tracing

Only use scripts that are listed in both `docs/ai/script-registry.md` and `docs/ai/script-registry.json`.

## Planning And Evidence

- Keep implementation plans in the chat response or approved repo files; do not write plans to `~/.copilot/session-state/**` or other external state unless explicitly asked.
- Do not create database-backed todos, run `sql`, or mutate application state for planning unless the task explicitly asks for database changes.
- Prefer `git status --short`, `git diff`, and focused tests over commit-history guessing when determining current scope.
- Avoid piping test output through `tail`/`head` for verification because it can hide failures and can make a failing command appear successful; run the focused command directly and report its real exit code.

## Prohibited

Do not use these for search or discovery:

- `grep` (bare) — use `rg` or `ai-search.sh` instead
- `find` (bare) — use `fd` or `fd-files.sh` instead
- `cat` for broad exploration — use `preview-file.sh` instead
- `grep -rn ... | grep -v` — use built-in search or `ai-search.sh`
- `find ... | xargs grep` — use built-in search, `fd-files.sh`, or `rg-code.sh`
- `cat file | head` — use `read_file` or `preview-file.sh`
- broad `ls ... && cat ...` — use `list_dir` and `read_file`
- inline `php artisan tinker --execute` for discovery unless repo policy explicitly approves it
- `php -r ...` or other inline interpreters for discovery unless explicitly approved
- `php artisan test ... | tail/head/grep` as proof — run the focused test directly so failures and exit codes are preserved
- `sed -i`, mass replacements, or platform-specific edit commands for code changes — use editor tools or approved guarded edit scripts

## Script Boundary

Only run scripts listed in `docs/ai/script-registry.md`, `docs/ai/script-registry.json`, and `docs/ai/scripts-reference.md`.

Prompt files must not grant broader tools than the selected agent.

Forbidden prompt-file examples:

- planner or review prompts with `tools: ['edit']`
- research prompts with broad `tools: ['execute']` instead of fine-grained `execute/runInTerminal`
- any prompt file with `tools: ['*']`

The script namespace is `scripts/ai/`. Do not invent or call `scripts/copilot/*` unless this repository actually contains and documents it. Do not run scripts outside `scripts/ai/` unless explicitly required by the task.

Do not run destructive commands: `rm -rf`, `git push --force`, `git reset --hard`, deploy commands.

For Copilot terminal/sandbox use, simple read-only git commands and exact inventory commands may be auto-approved. Do not rely on auto-approval for compound shell beyond explicitly allowlisted read-only forms. Heredocs, inline interpreters (`python3`, `php -r`), write redirects, destructive git, and network installer pipes must stay blocked or require explicit approval.

## Path Note

Scripts should be run from the repository root. When the script location is unclear, use the repository-root script path:

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh text "query" . --fixed
```

`scripts/ai/` is the script directory at the root of the installed repository.
