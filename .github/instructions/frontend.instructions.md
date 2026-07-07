---
applyTo: '**/*.tsx,**/*.jsx,**/*.vue,**/*.svelte,**/frontend/**,**/client/**,**/web/**,**/ui/**'
description: 'Frontend, UI, accessibility, state, interaction, and presentation guidance'
---

# Frontend Rules

Apply these rules to UI components, pages, routes, composables or hooks, styles, frontend tests, and client-side API integrations.

## Required Context

- Inspect existing UI primitives before adding new components.
- Inspect nearby components before creating new patterns.
- Load applicable architecture, target, and testing instructions.
- For the active frontend stack, check existing conventions for:
  - components
  - composables or hooks
  - layouts
  - middleware
  - plugins
  - stores
  - API clients
  - test utilities

## Separation Of Concerns

- Keep presentation code focused on rendering and user interaction.
- Do not move heavy business logic into UI components if the repository already separates it.
- Put reusable stateful logic into the project's existing abstraction:
  - composable or hook
  - store
  - service
  - query or mutation wrapper
  - domain module
- Avoid duplicating transformation logic between component and API layer.

## UI Consistency

- Reuse existing UI primitives before adding new ones.
- Preserve existing design tokens, spacing, typography, variants, and theme conventions.
- Do not introduce a second component library without explicit approval.
- Keep component APIs narrow and predictable.

## Accessibility

- Preserve keyboard navigation.
- Preserve focus management.
- Use semantic HTML where possible.
- Keep labels, ARIA attributes, and error states accurate.
- Do not remove loading, empty, disabled, or error states.

## State And Data Loading

- Preserve existing caching and invalidation patterns.
- Do not bypass established API client conventions.
- Do not introduce duplicate network calls when existing query state can be reused.
- Keep optimistic updates, pagination, filtering, and sorting behavior explicit.

## Target Awareness

- Do not assume browser-only APIs are safe in SSR, server routes, workers, CLI, or build-time code.
- Guard client-only behavior behind existing project patterns.
- If a change is intentionally target-specific, state the target and why.

## Frontend Tests

- Prefer the lowest test level that proves behavior:
  - unit for pure logic
  - component test for rendering or interaction
  - integration test for routing or data flow
  - E2E only for critical user flows
- Add regression coverage for bug fixes.
- Do not weaken assertions to pass.
- Preserve accessibility-relevant assertions where present.

## Output Requirement

For frontend changes, report:

- changed UI path
- reused primitive or pattern
- affected user flow
- accessibility impact
- target impact
- test command used
