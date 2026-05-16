---
name: new-feature
description: Use when implementing a bounded feature with existing repository patterns and focused verification
argument-hint: 'Describe the feature, expected behavior, and any constraints'
---

## What I Do

I implement one bounded feature slice that follows existing repository patterns with focused verification.

## When To Use Me

- when implementing a bounded feature slice with existing patterns
- when behavior changes need focused tests
- when the owner and implementation path are already clear

## Do Not Use Me For

- broad architecture changes or large migrations
- bug-first regression work (use bug-regression instead)
- features where ownership is unclear (use researcher first)

## Workflow

1. inspect the current implementation in the active paths
2. identify the existing owner of the behavior
3. extend current patterns before adding new abstractions
4. add focused tests if behavior changes
5. verify with the narrowest relevant check first

## Gotchas

- do not introduce a new subsystem when an existing owner already fits
- do not skip risk and rollout notes for medium or high-risk changes
- do not perform unrelated refactors during feature work
