---
name: project-context
description: Use when planning or reviewing work in an unfamiliar area, choosing verification depth, or checking approval boundaries before editing
argument-hint: 'Describe what you are planning or reviewing in this repository'
---

## What I Do

I provide durable repository context for `<PROJECT_NAME>` and point to the support files that other workflows should read next.

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

- Project type: `<PROJECT_TYPE>`
- Summary: `<PROJECT_SUMMARY>`
- Primary language: `<PRIMARY_LANGUAGE>`
- Primary runtime: `<PRIMARY_RUNTIME>`
- Active paths: `<ACTIVE_PATHS>`
- Inactive paths: `<INACTIVE_PATHS>`
- Targets: `<TARGET_PLATFORMS>`

## Architecture Notes

- Primary entrypoints: `<PRIMARY_ENTRYPOINTS>`
- Notes: `<ARCHITECTURE_NOTES>`
- Risk areas: `<RISK_AREAS>`

## Verification Expectations

- Main verification command: `<PRIMARY_VERIFY_COMMAND>`
- Main build command: `<PRIMARY_BUILD_COMMAND>`
- Main test command: `<PRIMARY_TEST_COMMAND>`
- Preferred narrow-first pattern: `<NARROW_VERIFY_GUIDANCE>`

## Review Priorities

- `<REVIEW_PRIORITIES>`

## Change Hygiene

Search for nearby patterns before changing code, config, docs, or workflow logic; reuse when overlap is roughly `>=75%`; after changes, sweep edited files and nearby references for stale paths, placeholders, and generated-output drift.

## Approval Boundaries

- `<APPROVAL_REQUIRED_CHANGES>`

## Common Gotchas

- `<KNOWN_GOTCHA_THEMES>`

## Output Contract

- current owner or `unknown`
- affected paths and targets
- canonical docs to read next
- approval boundaries relevant to the request
- focused verification starting point
- recommended next stage: research, plan, implement, review
