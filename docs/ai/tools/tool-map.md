# Tool Map

This document routes common agent and human tasks to the approved repository tools. Prefer the wrappers documented here over raw shell commands.

## Discovery

| Goal | Tool |
| --- | --- |
| Search text in repo | `scripts/ai/ai-search.sh` (see `docs/ai/tools/ai-search.md`) |
| Find files by name | `scripts/ai/ai-search.sh` in `files` mode |
| Structural pattern search | `scripts/ai/ai-search.sh` in `struct` mode |
| Trace symbol usage | `scripts/ai/query-usage.sh` |
| Read a single file | `scripts/ai/preview-file.sh` (see `docs/ai/tools/actions/preview-file.md`) |

## Diff And History

| Goal | Tool |
| --- | --- |
| Inspect current change | `git status --short`, `git diff` |
| Diff context bundle | `scripts/ai/ai-diff-context.sh` |
| Git forensics and blame | `scripts/ai/git-forensics.sh` |
| Pull request context | `scripts/ai/gh-pr-context.sh` |

## Verification

| Goal | Tool |
| --- | --- |
| Project-wide verification | `bash scripts/ai/ai-verify.sh .` |
| Validate AI config | `php tools/ai/validate-ai-config.php` |
| Validate catalog | `php tools/ai/validate-ai-catalog.php` |
| Validate generated artifacts | `php tools/ai/validate-generated-artifacts.php` |
| Doc consistency | `bash scripts/ai/ai-doc-check.sh --check` |

## Guarded Mutation

| Goal | Tool |
| --- | --- |
| AST-based edit | `scripts/ai/ai-edit.sh ast-grep ...` |
| Text edit | `scripts/ai/ai-edit.sh sd ...` |
| Rollback last guarded edit | `scripts/ai/ai-rollback.sh` |

## Context Packing

| Goal | Tool |
| --- | --- |
| Repomix context tree | `scripts/ai/repomix-context-tree.sh` |
| Ranked context router | `scripts/ai/repomix-scc-router.sh` |
| Direct repomix run | `scripts/ai/run-repomix-context.sh` |

## Policy And Hooks

| Goal | Tool |
| --- | --- |
| Pre-execution policy gate | `scripts/ai/pre-tool-use.sh` |
| Post-execution evidence writer | `scripts/ai/post-tool-use.sh` |
| Install mandatory CLI tools | `scripts/ai/install-mandatory-tools.sh` |

## Snippet Routing Notes

- Use `AI_OUTPUT=json` for structured evidence pipelines.
- Use `changed`, `staged`, `tracked` modes in that order before broad search.
- The `schema`, `status`, `warnings`, and `errors` envelope keys are common across tools that emit JSON.
- Treat `unsafe_blocked` status and any `dry_run` preview as gates that require explicit approval before re-running without them.

## See Also

- `docs/ai/script-registry.md`
- `docs/ai/script-registry.json`
- `docs/ai/scripts-reference.md`
- `docs/ai/tools/ai-search.md`
- `docs/ai/tools/actions/preview-file.md`
- `docs/ai/tools/actions/search-evidence.md`
- `docs/ai/tools/actions/use-ai-script.md`
