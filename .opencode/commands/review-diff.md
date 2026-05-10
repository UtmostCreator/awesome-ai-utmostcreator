---
name: review-diff
description: Use when reviewing a change set for correctness, regression risk, policy fit, and missing verification starting from the diff
argument-hint: 'Describe the goal of the change or the diff under review'
---

## What I Do

I review the current change set from the diff first and only expand into unchanged files when needed to verify a concern.
I require duplicate-logic screening evidence before a pass verdict and flag roughly `>=75%` overlap as reuse or replacement candidates.

## When To Use Me

- before merge or handoff
- after implementation
- when a risky slice needs focused review

## Do Not Use Me For

- implementation planning or feature design
- broad repository summarization
- restating the diff without findings

## Read Alongside

- `docs/ai/capabilities/review-diff/CAPABILITY.md`
- `docs/ai/capabilities/review-diff/checklist.md`
- `docs/ai/capabilities/review-diff/gotchas.md`
- `docs/ai/capabilities/review-diff/examples.md`

## Review Priorities

1. correctness
2. regressions
3. security and privacy issues
4. contract drift
5. missing tests
6. duplicate-logic screening (flag `>=75%` overlap as reuse or replacement candidate)

## Output

- verdict
- findings with severity and location
- risk assessment
- recommended next step

## Gotchas

- do not spend the review restating the diff
- do not treat stylistic preferences as the main finding category
- present findings before general summary
