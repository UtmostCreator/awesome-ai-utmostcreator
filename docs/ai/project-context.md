# app-configs Project Context

Use this file as durable, canonical project context for instructions, agents, prompts, and capabilities.

## 1) Project Identity

- Project type: `php project`
- Summary: `AI workflow starter for app-configs`
- Primary language: `unknown`
- Primary runtime: `unknown`
- Supported targets: `unknown`
- Primary stack: `unknown`
- Package manager: `unknown`

## 2) Scope and Ownership

- Active paths: `.ai-install-manifest.json,.editorconfig,.gitattributes,.gitignore,.gitleaks.toml,.gitleaksignore,.markdownlint-cli2.yaml,.opencode,.shellcheckrc,AGENTS.md,CLAUDE.md,README.md,composer.json,composer.lock,install-ai-kit.sh,llms.txt,opencode.jsonc,packages,phpunit.xml.dist,policies,readme-install.md,schemas,tests,tools`
- Inactive/legacy paths: `unknown`
- Primary entrypoints: `README.md, docs/ai/project-context.md`
- Architecture notes: `Keep policy and capability docs canonical; keep runtime adapters thin.`
- Risk areas: `stale docs, adapter drift, unsafe command usage`

## 3) Source Of Truth

When files disagree, use:

1. Current git diff and working tree
2. Source code
3. Tests
4. Schemas/contracts/public interfaces
5. Runtime/build config
6. `docs/ai/project-context.md`
7. Other `docs/ai/*.md`
8. Adapter files (`AGENTS.md`, `.github/**`, `.opencode/**`)
9. Generated files

Stale markdown must not override code evidence.

## 4) Task-Context Gate

Load one before planning/editing:

- `docs/ai/generated/task-context/latest.md`
- `php tools/ai/compile-task-context.php`
- `php tools/ai/impact.php`

If missing, perform read-only discovery first and produce a plan before edits.

## 5) Placement, Naming, and Reuse

- File placement rules: `unknown`
- Naming rules: `unknown`
- Golden examples: `unknown`

Before adding non-trivial logic, search for overlap and report nearest reuse percentage.
If overlap is `>=75%`, extend or adapt existing patterns instead of adding duplicates.

## 6) Formatting, Ignored Files, and Script Rules

- Formatter config files: `unknown`
- Linter config files: `unknown`
- EditorConfig path: `unknown`
- Ignore files (`.gitignore`, lint ignore lists, etc.): `unknown`

Script rules:

- Prefer repository wrappers from `docs/ai/script-registry.md` and `docs/ai/script-registry.json`.
- Treat `scripts/ai/pre-tool-use.sh` as canonical pre-execution policy gate.
- Treat `scripts/ai/post-tool-use.sh` as canonical post-execution evidence writer.
- Unknown or external scripts must be `ask` unless explicitly approved.

## 7) Generated and Protected Files

- Generated files/paths: `unknown`
- Protected files/paths: `unknown`

Do not edit generated files directly unless the task explicitly requires regeneration.

## 8) Verification Commands

- Main verification command: `unknown`
- Main build command: `unknown`
- Main test command: `unknown`
- Preferred narrow-first strategy: `start with the narrowest repo-local check and escalate only if needed`

Additional project commands:

- Install: `unknown`
- Lint: `unknown`
- Format: `unknown`

## 9) Approval Boundaries

- `secrets, destructive changes, auth or billing changes`

Never claim verification that was not run.

## 10) Unknowns / Do-Not-Invent

If a convention is missing from code, tests, config, or this file:

- do not invent new conventions
- inspect nearest existing example
- ask before introducing new architecture patterns

Blocked response format:

```text
Blocked by unknown: <UNKNOWN>
Evidence checked: <FILES_OR_COMMANDS_CHECKED>
Safe next step: <NEXT_STEP>
```

## 11) Workflow Notes

- Capability composition hints: `start with project-context, then verify-change, then review-diff`
- Release safety notes: `define rollback posture for medium/high risk changes`
- Known gotcha themes: `stale paths, broad edits without evidence, guessed behavior`
- Review priorities: `correctness, regressions, configuration drift`

## 12) Project-Specific Rule Placeholders

Fill and maintain these for each installed project:

- Formatting exceptions: `unknown`
- Additional ignored files/paths: `unknown`
- Allowed scripts list: `unknown`
- Forbidden script patterns: `unknown`
- Additional security rules: `unknown`
