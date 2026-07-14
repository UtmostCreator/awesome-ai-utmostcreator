---
name: build-configuration
description: Use when changing build, packaging, or verification configuration and you need to keep current behavior verifiable
argument-hint: 'Describe the build or config change and the affected tool or command'
---

## What I Do

I make build, packaging, or verification configuration changes minimal and reversible, then prove them with the closest parser, linter, or tool-specific sanity check. If a runtime cannot support a step, I document the fallback instead of pretending parity.

## When To Use Me

- when editing build, packaging, or CI/verification configuration
- when a command, script target, or tool invocation changes and must be documented exactly
- when a manifest edit forces a lockfile regeneration you must route through install
- when a config change could alter observable build or verification behavior

## Read Alongside

- `docs/ai/capabilities/config-change-safety/CAPABILITY.md`
- `docs/ai/capabilities/verify-change/CAPABILITY.md`
- `docs/ai/verification-matrix.md`

## Steps

1. Scope the config change to the specific file(s) and the exact tool or command it affects; keep it minimal and reversible.
2. Search for the existing pattern before adding new config structure; reuse or adapt when overlap is roughly >=75%.
3. Make the smallest change and document any command change exactly (old vs new invocation).
4. Verify with the closest available check: the tool's own validate/lint (for example a config validator, `composer validate`, a linter), then a scoped test or verification run. Report pass/fail evidence.
5. If a dependency manifest changed, treat the matching lockfile as regenerate-only: stop and route lock regeneration through the install command rather than editing the lock directly.
6. If a runtime surface cannot support a workflow step, document the fallback rather than claiming parity, then hand off with the verification evidence.

## Output

- the scoped config change and exact before/after commands
- the closest verification run and its pass/fail evidence
- any lockfile-regeneration or fallback note
- reuse check for any new config structure

## Gotchas

- do not report a successful build as proof of behavior correctness unless the project says so
- do not edit generated or lock files directly; regenerate them through the owning tool
- keep changes reversible; do not bundle unrelated build edits
- escalate to release review when the change affects security, release, or deployment behavior
