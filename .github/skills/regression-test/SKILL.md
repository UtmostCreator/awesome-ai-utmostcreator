---
name: regression-test
description: Use when the main task is to create a failing or proving regression test for a reported bug or edge case
argument-hint: 'Describe the behavior, failure mode, and expected result'
---

## What I Do

I add the narrowest practical regression test or deterministic reproduction for a reported bug or edge case.

## When To Use Me

- when the most important outcome is a focused regression test
- when a reproduction step is needed before a fix
- when proving an edge case with a deterministic assertion

## Do Not Use Me For

- broad refactors or feature implementation
- cases where the fix should be combined with the test in one pass (use bug-regression instead)

## Workflow

1. identify the smallest useful proving surface
2. add the narrowest practical test or reproduction
3. confirm failure or proving behavior when practical
4. stop unless the request also includes the fix

## Gotchas

- do not widen into a broader refactor or feature task
- do not weaken assertions to make a test pass
