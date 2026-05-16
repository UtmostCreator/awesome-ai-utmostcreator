# Project Context Placeholder Guide

Use this file when installing templates into a new project. Fill placeholders before enabling write-capable AI flows.

## Required rule placeholders

- `<FILE_PLACEMENT_RULES>`
- `<NAMING_RULES>`
- `<GOLDEN_EXAMPLES>`
- `secrets, destructive changes, auth or billing changes`
- `unknown`
- `unknown`

## Formatting and ignore placeholders

- `<FORMATTER_CONFIG_FILES>`
- `<LINTER_CONFIG_FILES>`
- `<EDITORCONFIG_PATH>`
- `<IGNORE_FILES>`
- `<PROJECT_FORMATTING_EXCEPTIONS>`
- `<PROJECT_IGNORED_FILES>`

## Script policy placeholders

- `<PROJECT_ALLOWED_SCRIPTS>`
- `<PROJECT_FORBIDDEN_SCRIPTS>`
- `<PROJECT_SECURITY_RULES>`

## Command placeholders

- `<INSTALL_COMMAND>`
- `unknown`
- `<FORMAT_COMMAND>`
- `unknown`

## Safety notes

- Do not leave unresolved placeholders in canonical docs for active projects.
- If a value is unknown, set it to `NOT_CONFIGURED` and keep edits read-only until clarified.
- Prefer concrete commands and explicit paths over narrative text.
