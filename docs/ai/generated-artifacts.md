# Generated Artifacts Guide

`docs/ai/generated/` holds machine-generated files produced by `php tools/ai/ai.php` subcommands.
Files here are **not** tracked in git and are safe to delete and regenerate.

## Commit Policy

Do not commit files from `docs/ai/generated/`.

These artifacts are runtime outputs for local verification and advisor workflows, not source-of-truth files. If a command regenerates tracked files outside `docs/ai/generated/` such as catalog or repository-structure documentation, commit those tracked outputs when their source inputs changed and the regenerated result is intentional.

## When to Read What

| If you want to...                | Read this file                            |
| -------------------------------- | ----------------------------------------- |
| Check install readiness          | `preflight.json`                          |
| Review what was installed        | `install.json`                            |
| See package verification results | `package-verify.json`                     |
| Run the advisor pipeline         | `advisor-context.md`, `advisor-prompt.md` |
| Check adapter drift              | `advisor-drift.md`                        |
| Verify the full install          | `verify.json`                             |
| See repository structure data    | `repo-structure.json`                     |
| Browse all artifacts at a glance | `artifacts.json`                          |

## Essential Files

These are consumed by the verification or advisor pipeline. Do not delete them between related commands.

- `preflight.json` — install prerequisites check
- `package-verify.json` — package integrity verification
- `install.json` — install result and manifest
- `verify.json` — post-install verification
- `adapter-plan.json` — adapter generation plan
- `advisor.json` — advisor analysis results
- `advisor-secret-findings.json` — secret scan results
- `advisor-context.md` — packed context for advisor LLM prompt
- `advisor-context.index.md` — index of packed context files
- `advisor-prompt.md` — assembled advisor prompt
- `advisor-drift.md` — adapter drift report
- `install-manifest.json` — detailed install manifest
- `install-instructions.json` — generated instruction file list
- `repo-structure.json` — repository structure snapshot
- `artifacts.json` — meta-registry of all generated artifacts

## Ephemeral Files

Safe to delete at any time. Re-run the originating command to regenerate.

- `analysis-*.json` — code analysis snapshots
- `workspace-*.json` — workspace diagnostics
- `decisions-*.json` — decision log snapshots
- `git-*.json` — git history summaries
- `next-*.json` — next-action suggestions
- `full-install-validation.*` — install validation run log

## Markdown Duplicates

By default, `.md` duplicate files are **not** generated. They contain the same JSON
wrapped in a markdown code block and are not read by any tool.

To generate them for manual inspection, prefix the command with `AI_ARTIFACTS_VERBOSE=1`:

```bash
AI_ARTIFACTS_VERBOSE=1 php tools/ai/ai.php verify
AI_ARTIFACTS_VERBOSE=1 php tools/ai/ai.php preflight
```

## Cleaning Up

Everything in `docs/ai/generated/` is safe to delete:

```bash
rm -rf docs/ai/generated/*
```

Regenerate only what your next command needs:

```bash
php tools/ai/ai.php preflight   # check prerequisites
php tools/ai/ai.php verify      # verify current install
php tools/ai/ai.php advisor     # run advisor pipeline
```
