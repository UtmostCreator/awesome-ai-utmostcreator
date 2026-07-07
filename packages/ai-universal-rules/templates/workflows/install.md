---
name: install
description: Use to install (or reinstall/refresh) the AI workflow kit into a target project, asking for missing parameters (target path, profile, runtime, project name) before writing anything
argument-hint: "Target path and any known parameters (profile, runtime, project name); leave blank to be asked"
---

## What I Do

I run this kit's canonical install sequence — preflight, package-verify, dry-run, confirm,
apply, verify — against a target project, resolving whichever of target path, profile,
runtime, or project name were not already given.

## When To Use Me

- installing this kit into a new project for the first time
- refreshing/reinstalling an already-installed target after this source repo's templates changed
- exporting a standalone installer bundle instead of installing directly (see Related Tool below)

## Parameters I Resolve

1. **Target path** — confirm it exists before proceeding; ask if it does not.
2. **Profile** — one of `minimal`, `copilot`, `opencode`, `claude`, `dual`, `guarded`,
   `accelerated`, `full-governance`, `docs-reference`, or the editions `basic`, `standard`,
   `creator`, `full`, `agents-only`. I ask with a short set of options rather than guessing.
3. **Runtime** — `github-copilot`, `opencode`, `claude-code`, or `both` (default `both`).
4. **Project name** — defaults to the target directory's basename.

I ask a short structured question (2-4 options plus a free-text choice) for any of these not
already supplied, instead of silently choosing a default that was not asked for.

## Workflow

1. run `php tools/ai/ai.php preflight`
2. run `php tools/ai/ai.php package-verify` — stop and report if this finds checksum
   mismatches instead of silently continuing against a drifted source tree
3. run `php tools/ai/install-ai-kit.php --dry-run --target <target> --profile <profile> --runtime <runtime> --project-name "<name>"`
4. show the dry-run plan and get explicit confirmation before writing anything; if the target
   already has a managed install, say a backup will be created automatically and do not pass
   `--force`/`--allow-core-overwrite` unless the user explicitly asks for a reinstall that
   overwrites existing files
5. after confirmation, run `php tools/ai/install-ai-kit.php --target <target> --profile <profile> --runtime <runtime> --project-name "<name>"`
6. verify the installed target: `(cd <target> && php tools/ai/validate-install-surface.php --strict)`

## Related Tool

To vendor a standalone, offline-runnable copy of this installer into a path (without the rest
of this development repo) instead of installing directly, use
`php tools/ai/export-install-bundle.php --target <path> --apply`, then run
`bash <path>/install-ai-kit.sh --target <other-project> --profile <name>` from there.

## Output

- resolved target, profile, runtime, project name
- each step run and its pass/fail status
- the dry-run plan shown before any write
- confirmation obtained before `--apply`
- final verification result

## Gotchas

- never claim an install succeeded without the final verification step passing
- do not guess a target path; confirm it exists or ask whether to create it
- do not pass `--force` or `--allow-core-overwrite` unless the user explicitly asks for it
- missing prerequisites (php, git, jq) should be reported exactly, pointing to
  `docs/ai/mandatory-tools-install.md`, not worked around silently
