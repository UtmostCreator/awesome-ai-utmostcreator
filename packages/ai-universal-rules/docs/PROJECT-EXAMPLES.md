# Project Patterns

These patterns show how the capability-first model can be used in very different repositories without rewriting the whole instruction system.

Use them as scenario guides when adapting the canonical templates in `templates/core/`, `templates/capabilities/`, and the runtime adapters.

## Project N: Laravel + Vue Product App

Typical capability set:

- `project-context`
- `verify-change`
- `review-diff`
- `bug-regression`

Example request:

```text
Fix the checkout discount bug where coupons apply twice.
```

Expected flow:

1. `project-context` identifies likely ownership in checkout, pricing, and tests.
2. `bug-regression` drives a focused failing test first.
3. `verify-change` chooses the narrowest Laravel test command before broader checks.
4. `review-diff` checks for pricing regressions, contract drift, and missing tests.

High-value repo-specific gotchas:

- do not use raw DB inserts when factories already model the state correctly
- do not verify pricing changes with build-only evidence
- do not refactor unrelated controller or service structure during a bug fix

## Project M: Monorepo With API, Web, And Infra

Typical capability set:

- `project-context`
- `verify-change`
- `review-diff`
- `release-safety`
- `dependency-upgrade`

Example request:

```text
Add a new API field and wire it through the dashboard.
```

Expected flow:

1. `project-context` identifies owning package, downstream consumers, and active paths.
2. architecture or feature planning stays package-aware instead of treating the repo as one app.
3. `verify-change` chooses package-local tests first, then broader build or integration checks only if needed.
4. `review-diff` checks backward compatibility, client drift, and release coordination.
5. `release-safety` becomes relevant only if the new field changes rollout or compatibility risk.

High-value repo-specific gotchas:

- do not assume root commands are the right verification surface
- do not treat one package's passing tests as proof for downstream clients
- do not skip release notes when a shared contract changes

## Project L: CLI Tool Or SDK

Typical capability set:

- `project-context`
- `verify-change`
- `review-diff`
- `dependency-upgrade`

Example request:

```text
Upgrade the HTTP client library and keep the CLI output stable.
```

Expected flow:

1. `project-context` identifies the runtime entrypoints, CLI command paths, and test surface.
2. `dependency-upgrade` scopes the blast radius and checks whether the library is runtime-critical or tooling-only.
3. `verify-change` picks focused integration or snapshot-style checks before broader packaging checks.
4. `review-diff` checks output compatibility, error handling, and hidden contract drift.

High-value repo-specific gotchas:

- do not treat install success as proof that CLI behavior is unchanged
- do not ignore backwards compatibility for output, flags, or exit codes
- do not bundle unrelated dependency bumps into the same upgrade slice

## Why This Matters

The same global instruction model can stay stable across both projects because the reusable detail lives in capability folders, not in one giant repo-wide instruction file.

## See Also

- `COMPOSITION-RECIPES.md` — request-to-capability routing recipes these examples follow
- `workflows/SYSTEM-WORKFLOW.md` — end-to-end lifecycle each example exercises
- `foundations/CAPABILITY-MODEL.md` — capability contract these examples rely on
- `ONBOARDING.md` — how to adapt these patterns to a real repository
