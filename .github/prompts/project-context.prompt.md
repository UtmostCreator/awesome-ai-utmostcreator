---
name: project-context
description: Use when planning or reviewing work in an unfamiliar area, choosing verification depth, or checking approval boundaries before editing
argument-hint: 'Describe what you are planning or reviewing in this repository'
---

## What I Do

I provide durable repository context for `awesome-ai-utmostcreator` and point to the support files that other workflows should read next.

## When To Use Me

- before architecture decisions in unfamiliar areas
- before implementation when multiple active paths could own the change
- before review or verification when risk or ownership is unclear
- when another workflow needs repository facts first

## Do Not Use Me For

- purely general coding questions with no repository context
- trivial edits where the owner and verification path are already obvious

## Read Alongside

Read `docs/ai/capabilities/project-context/CAPABILITY.md`, its support files, and relevant context-gate, architecture, or target instructions.

## Task Context Sources

Load the smallest relevant task context first when available. If none exists, stay read-only and produce the missing ownership, path, target, and verification map.

## Project Shape

- Project type: `php project`
- Summary: `AI workflow starter for awesome-ai-utmostcreator`
- Primary language: `unknown`
- Primary runtime: `unknown`
- Active paths: `.ai-install-manifest.json,.ai-logs,.editorconfig,.gitattributes,.github,.gitignore,.gitleaks.toml,.gitleaksignore,.markdownlint-cli2.yaml,.opencode,.repomixignore,.shellcheckrc,.vscode,AGENTS.md,CLAUDE.md,PLACEHOLDERS.md,README.md,composer.json,composer.lock,configs,docs,improvement-plan.md,install-ai-kit.sh,justfile,llms.txt,opencode.jsonc,packages,phpunit.xml.dist,policies,readme-install.md,reference,schemas,scripts,tests,tools`
- Inactive paths: `unknown`
- Targets: `unknown`

## Architecture Notes

- Primary entrypoints: `README.md, docs/ai/project-context.md`
- Notes: `Keep policy and capability docs canonical; keep runtime adapters thin.`
- Risk areas: `stale docs, adapter drift, unsafe command usage`

## Verification Expectations

- Main verification command: `unknown`
- Main build command: `unknown`
- Main test command: `unknown`
- Preferred narrow-first pattern: `start with the narrowest repo-local check and escalate only if needed`

## Review Priorities

- `correctness, regressions, configuration drift`

## Change Hygiene

Search for nearby patterns before changing code, config, docs, or workflow logic; reuse when overlap is roughly `>=75%`; after changes, sweep edited files and nearby references for stale paths, placeholders, and generated-output drift.

## Approval Boundaries

- `secrets, destructive changes, auth or billing changes`

## Common Gotchas

- `stale paths, broad edits without evidence, guessed behavior`

## Output Contract

- current owner or `unknown`
- affected paths and targets
- canonical docs to read next
- approval boundaries relevant to the request
- focused verification starting point
- recommended next stage: research, plan, implement, review
