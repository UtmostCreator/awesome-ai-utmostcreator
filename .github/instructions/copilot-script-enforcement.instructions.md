---
applyTo: '**'
description: 'Copilot-specific script enforcement — when built-in tools are insufficient, use repository shell scripts'
---

# Copilot Script Enforcement

Copilot VS Code has built-in tools for search and file reading that are faster than shell scripts.
Use built-in tools for search and read. Use repository shell scripts for everything else.

## When To Use Built-In Tools

Use `grep_search`, `semantic_search`, `file_search`, `read_file`, `list_dir` for:

- searching code
- reading files
- finding files by name or pattern
- listing directories

## When To Use Repository Shell Scripts

You MUST use `run_in_terminal` with the repository shell scripts for these operations.
Built-in tools CANNOT perform these — do not skip them or substitute alternatives.

### Verification and Validation (MANDATORY before committing)

```bash
bash scripts/ai/ai-verify.sh .                          # run full verification suite
bash scripts/ai/ai-doc-check.sh --check                 # check AI doc consistency
php tools/ai/validate-ai-config.php                      # validate AI config
php tools/ai/validate-ai-catalog.php                     # validate AI catalog
php tools/ai/generate-ai-catalog.php --check             # check catalog freshness
php tools/ai/generate-repo-structure.php --check --with-scc  # check repo structure freshness
```

### Workflow Commands (MANDATORY for AI workflow operations)

```bash
php tools/ai/ai.php verify --changed                     # verification digest
php tools/ai/ai.php preflight                            # installer prerequisites
php tools/ai/ai.php env-check                            # environment readiness
php tools/ai/ai.php install --dry-run                    # preview install
php tools/ai/ai.php list                                 # list available commands
php tools/ai/ai.php freshness                            # check artifact freshness
```

### Git History and Forensics

```bash
bash scripts/ai/git-forensics.sh "<symbol-or-path>"     # git history tracing
bash scripts/ai/query-usage.sh "<symbol>"                # symbol usage across repo
bash scripts/ai/gh-pr-context.sh                         # PR context
```

### Context and Packaging

```bash
bash scripts/ai/pack-context.sh                          # pack AI context
bash scripts/ai/repomix-scc-router.sh                    # SCC-ranked context
bash scripts/ai/ai-diff-context.sh                       # diff-aware context
```

### File Reference and Freshness Checks

```bash
bash scripts/ai/check-file-refs.sh                       # check file references
bash scripts/ai/ai-file-freshness.sh                     # check AI file freshness
bash scripts/ai/ai-install-coverage.sh                   # check install completeness
```

## Script Execution Rules

1. Run scripts from the repository root.
2. Only run scripts listed in `docs/ai/script-registry.md`.
3. Do not invent shell commands when a registered script exists for the operation.
4. After any code change, run at least one verification command before committing.
5. Report which scripts you ran and their exit codes in your response.

## Do Not

- Do not skip verification scripts because built-in tools found no errors.
- Do not substitute `grep_search` for `query-usage.sh` when checking symbol usage across the full repo.
- Do not substitute `read_file` for `preview-file.sh` when the script provides structured JSON output needed by other tools.
- Do not claim verification was done if you only used built-in search tools.
