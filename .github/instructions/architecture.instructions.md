---
applyTo: '.ai-install-manifest.json,.ai-logs,.editorconfig,.gitattributes,.github,.gitignore,.gitleaks.toml,.gitleaksignore,.markdownlint-cli2.yaml,.opencode,.repomixignore,.shellcheckrc,.vscode,AGENTS.md,CLAUDE.md,PLACEHOLDERS.md,README.md,composer.json,composer.lock,configs,docs,install-ai-kit.sh,justfile,llms.txt,opencode.jsonc,packages,phpunit.xml.dist,policies,readme-install.md,reference,schemas,scripts,tests,tools'
description: 'Architecture, ownership, layering, source-of-truth, and high-risk structural change guidance'
---

# Architecture Rules

Apply these rules before proposing or editing architecture-sensitive code.

## Required Context

- Read the current implementation before proposing structural changes.
- Load task context first when available:
  - `docs/ai/generated/task-context/latest.md`
  - output from `php tools/ai/compile-task-context.php`
  - output from `php tools/ai/impact.php`
- If task context is missing, perform read-only research before planning implementation.

## Source Of Truth

- Prefer canonical repository docs over adapter files.
- Canonical architecture and policy docs:
  - `docs/ai/project-context.md`
  - `docs/ai/workflow.md`
  - `docs/ai/source-of-truth.md`
  - `docs/ai/AI-GUARDRAILS.md`
  - `docs/ai/approval-boundaries.md`
  - `docs/ai/generated-artifacts.md`
  - `docs/ai/adapter-contract.md`
- Adapter files such as `.github/**`, `.opencode/**`, `AGENTS.md`, and `CLAUDE.md` must stay thin and point back to canonical docs.

## Ownership And Layering

- Keep logic with its existing owner unless current code proves the boundary is wrong.
- Prefer extending existing patterns before introducing parallel abstractions.
- Do not create a new abstraction when an existing abstraction has at least 75% overlap.
- Do not move responsibilities across layers without naming:
  - old owner
  - new owner
  - reason for transfer
  - affected callers
  - verification path
- Respect `CODEOWNERS`, ownership docs, and local conventions before editing.

## Architectural Change Rules

- Treat these as high-risk:
  - public API contract changes
  - database schema changes
  - authentication or authorization changes
  - generated artifact changes
  - installer, upgrade, or rollback changes
  - CI or release workflow changes
  - cross-target behavior changes
  - adapter instruction changes
- Ask for approval before making: `secrets, destructive changes, auth or billing changes`
- Approval means a human approver can explain each changed section well enough to own the merge.

## Data And Migration Rules

- For migrations that drop, rename, restructure, or backfill existing data, use expand-contract.
- Do not combine destructive schema changes with application behavior changes unless explicitly approved.
- Every migration must have:
  - rollback or forward-fix posture
  - affected table or entity list
  - compatibility notes
  - test or smoke proof

## Output Requirement

When proposing architecture work, include:

- affected paths
- current owner
- proposed owner
- risk level: low / medium / high
- required approval gates
- verification ladder
- rollback or mitigation path
