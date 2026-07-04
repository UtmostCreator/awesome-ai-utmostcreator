# <PROJECT_NAME> Project Context

Use this file as durable, canonical project context for instructions, agents, prompts, and capabilities.

## 1) Project Identity

- Project type: `<PROJECT_TYPE>`
- Summary: `<PROJECT_SUMMARY>`
- Primary language: `<PRIMARY_LANGUAGE>`
- Primary runtime: `<PRIMARY_RUNTIME>`
- Supported targets: `<TARGET_PLATFORMS>`
- Primary stack: `<PRIMARY_STACK>`
- Package manager: `<PACKAGE_MANAGER>`

## 2) Scope and Ownership

- Active paths: `<ACTIVE_PATHS>`
- Inactive/legacy paths: `<INACTIVE_PATHS>`
- Primary entrypoints: `<PRIMARY_ENTRYPOINTS>`
- Architecture notes: `<ARCHITECTURE_NOTES>`
- Risk areas: `<RISK_AREAS>`

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

Establish one before planning/editing:

- `docs/ai/generated/task-context/latest.md` (if a task-context generator has produced it)
- otherwise read-only discovery via `scripts/ai/ai-search.sh` plus `git status --short` and `git diff`

If missing, perform read-only discovery first and produce a plan before edits.

Durable plans and task context are written to the committed `docs/tickets/` location.
`docs/ai/generated/task-context/` is ephemeral-only (read if present, never the durable store).

## 5) Placement, Naming, and Reuse

- File placement rules: `<FILE_PLACEMENT_RULES>`
- Naming rules: `<NAMING_RULES>`
- Golden examples: `<GOLDEN_EXAMPLES>`

Before adding non-trivial logic, search for overlap and report nearest reuse percentage.
If overlap is `>=75%`, extend or adapt existing patterns instead of adding duplicates.

## 6) Formatting, Ignored Files, and Script Rules

- Formatter config files: `<FORMATTER_CONFIG_FILES>`
- Linter config files: `<LINTER_CONFIG_FILES>`
- EditorConfig path: `<EDITORCONFIG_PATH>`
- Ignore files (`.gitignore`, lint ignore lists, etc.): `<IGNORE_FILES>`

Script rules:

- Prefer repository wrappers from `docs/ai/script-registry.md` and `docs/ai/script-registry.json`.
- Treat `scripts/ai/pre-tool-use.sh` as canonical pre-execution policy gate.
- Treat `scripts/ai/post-tool-use.sh` as canonical post-execution evidence writer.
- Unknown or external scripts must be `ask` unless explicitly approved.

## 7) Generated and Protected Files

- Generated files/paths: `<GENERATED_FILES>`
- Protected files/paths: `<PROTECTED_FILES>`

Do not edit generated files directly unless the task explicitly requires regeneration.

## 8) Verification Commands

- Main verification command: `<PRIMARY_VERIFY_COMMAND>`
- Main build command: `<PRIMARY_BUILD_COMMAND>`
- Main test command: `<PRIMARY_TEST_COMMAND>`
- Preferred narrow-first strategy: `<NARROW_VERIFY_GUIDANCE>`

Additional project commands:

- Install: `<INSTALL_COMMAND>`
- Lint: `<LINT_COMMAND>`
- Format: `<FORMAT_COMMAND>`

## 9) Approval Boundaries

- `<APPROVAL_REQUIRED_CHANGES>`

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

- Task-to-capability router: `docs/ai/capabilities/README.md` — check it before
  writing new procedure text; extend an existing capability at `>=75%` overlap.
- Capability composition hints: `<CAPABILITY_COMPOSITION_NOTES>`
- Release safety notes: `<RELEASE_SAFETY_NOTES>`
- Known gotcha themes: `<KNOWN_GOTCHA_THEMES>`
- Review priorities: `<REVIEW_PRIORITIES>`

## 12) Project-Specific Rule Placeholders

Fill and maintain these for each installed project:

- Formatting exceptions: `<PROJECT_FORMATTING_EXCEPTIONS>`
- Additional ignored files/paths: `<PROJECT_IGNORED_FILES>`
- Allowed scripts list: `<PROJECT_ALLOWED_SCRIPTS>`
- Forbidden script patterns: `<PROJECT_FORBIDDEN_SCRIPTS>`
- Additional security rules: `<PROJECT_SECURITY_RULES>`

## 13) Additional Project Docs

Extra project docs the AI should reference. Manage these under `context.extraDocs`
in `.ai/project.yml`; the list is re-rendered here on every install/upgrade.

<EXTRA_DOCS>
