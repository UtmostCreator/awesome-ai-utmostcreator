---
name: architecture-plan
description: Use when producing a focused implementation plan for a medium or large change before implementation begins
argument-hint: 'Describe the goal, scope, and affected behavior'
---

## What I Do

I produce a grounded implementation plan for medium or large changes, identifying active owners, boundaries, and risks before any code changes.

## When To Use Me

- when planning a medium or large repository change
- when architecture, ownership, or rollout posture is unclear
- when a plan is needed before implementation is approved

## Do Not Use Me For

- trivial changes with obvious owners and approaches
- combined planning and implementation (use implementer agent for that)

## Workflow

1. identify active owners of the behavior
2. call out boundaries and risks
3. propose the smallest sound approach
4. list tests and verification implications
5. state rollout and rollback posture for medium or high-risk changes

## Output

- scoped plan with bounded phases
- risk posture
- affected paths and owners
- verification scope and recommended next step

Write durable plans to the committed `docs/tickets/` location, one folder per current git branch (for example `docs/tickets/{branch-name}/plan-1-{short-desc}.md`, numbering additional plans `plan-2-...`, `plan-3-...`), or `docs/tickets/{TICKET-ID}.md` when a ticket id exists. Never write durable plans under the gitignored `docs/ai/generated/`.

## Gotchas

- do not implement as part of this workflow
- do not produce speculation as if it is confirmed by current code
