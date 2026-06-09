---
applyTo: '**'
description: 'Tool selection and script enforcement — use rg/fd/approved scripts; never use bare grep/find'
---

# Tool Selection Rules

## Surface-Aware Tool Routing

Different AI surfaces have different built-in capabilities:

- **Copilot VS Code**: Has built-in `grep_search`, `read_file`, `file_search`, `semantic_search`, `list_dir`. Use these for search/read. Use shell scripts for verification, validation, workflow, and git forensics.
- **OpenCode**: No built-in search/read tools. Use shell scripts for everything.
- **Claude Code**: Similar to OpenCode. Use shell scripts for everything.

## Required Tools

When searching code or files, always prefer repository-aware tools:

- Use `rg` (ripgrep) instead of `grep` for code search
- Use `fd` instead of `find` for file discovery
- Use `ast-grep` / `sg` for structural code queries

When the repository provides wrapper scripts, use those in preference to direct tool invocation:

- `bash <SCRIPTS_ROOT>/ai-search.sh "<query>"` — safe repository text search
- `bash <SCRIPTS_ROOT>/rg-code.sh "<pattern>"` — code-specific rg wrapper
- `bash <SCRIPTS_ROOT>/fd-files.sh "<pattern>"` — file discovery wrapper
- `bash <SCRIPTS_ROOT>/preview-file.sh "<path>"` — safe file preview
- `bash <SCRIPTS_ROOT>/query-usage.sh "<symbol>"` — symbol usage search
- `bash <SCRIPTS_ROOT>/git-forensics.sh "<symbol-or-path>"` — git history tracing

Only use scripts that are listed in both `docs/ai/script-registry.md` and `docs/ai/script-registry.json`.

## Prohibited

Do not use these for search or discovery:

- `grep` (bare) — use `rg` or `ai-search.sh` instead
- `find` (bare) — use `fd` or `fd-files.sh` instead
- `cat` for broad exploration — use `preview-file.sh` instead

## Script Boundary

Only run scripts listed in `docs/ai/script-registry.md`, `docs/ai/script-registry.json`, and `docs/ai/scripts-reference.md`.

Prompt files must not grant broader tools than the selected agent.

Forbidden prompt-file examples:

- planner or review prompts with `tools: ['edit']`
- research prompts with broad `tools: ['execute']` instead of fine-grained `execute/runInTerminal`
- any prompt file with `tools: ['*']`

Do not run scripts outside `scripts/ai/` unless explicitly required by the task.

Do not run destructive commands: `rm -rf`, `git push --force`, `git reset --hard`, deploy commands.

For Copilot terminal/sandbox use, simple read-only git commands and exact inventory commands may be auto-approved. Do not rely on auto-approval for compound shell beyond explicitly allowlisted read-only forms. Heredocs, inline interpreters (`python3`, `php -r`), write redirects, destructive git, and network installer pipes must stay blocked or require explicit approval.

## Path Note

Scripts should be run from the repository root. When the script location is unclear, use the repository-root script path:

```
bash <SCRIPTS_ROOT>/ai-search.sh "query"
```

`<SCRIPTS_ROOT>` resolves to the `scripts/ai/` directory at the root of the installed repository.
