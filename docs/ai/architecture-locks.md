# Architecture Locks

Architecture locks protect stable project decisions from being rewritten during a
bounded task.

## Current Lock

Do not redesign project architecture during bounded installer or workflow
changes. Prefer the smallest safe change and document unknowns.

## Escalation Required

Ask before changing persistence shape, public interfaces, security posture,
dependency surface, deployment behavior, or runtime adapter contracts.

## Adapter Rule

Keep runtime adapters thin. Canonical policy belongs in `docs/ai/**`, templates,
schemas, and source code. When adapter files disagree with canonical docs, treat
the canonical source as higher authority unless active code proves otherwise.

## Refactor Rule

Do not combine architecture redesign with cleanup, doc sync, or regression fixes.
If redesign is necessary, stop and create a bounded architecture plan first.
