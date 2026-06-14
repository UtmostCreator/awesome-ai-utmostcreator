# Command Policy

Command policy groups terminal actions by risk so agents can choose safe defaults
and know when to stop.

## Risk Groups

- Read-only probes inspect files, git state, or repository metadata.
- Verification commands run tests, validators, or static checks.
- Mutation-adjacent commands write generated docs, update indexes, or move files.
- Destructive commands delete, reset, force-push, deploy, or alter secrets.

## Default Handling

Read-only probes are allowed when scoped. Verification is allowed when it is the
smallest relevant proof. Mutation-adjacent commands ask when generated output or
user files may change. Destructive commands are denied unless explicitly
approved.

## Command Reporting

Report exact commands and outcomes. If a command times out or fails, state the
budget, exit status when known, and the next safe diagnostic step.
