---
name: generate-permissions
description: Use to preview the permission overlay a scanned project stack would add, before any agent permission frontmatter is applied
argument-hint: 'Optional: --profile <readonly|verify|impl> --edit-surface <name> --verify-tier <name>'
---

## What I Do

I preview the permission overlay a scanned project's selected stacks would add, by
calling `php tools/ai/ai.php permissions-suggest`. I never write agent permission
frontmatter — I only show what a reviewed language overlay would grant.

## When To Use Me

- after `scan-stack` has produced a fresh `.ai/stack-detection.json`
- before an install/upgrade applies stack-aware permission overlays
- when a maintainer wants to see what a stack selection would grant, without
  touching any shipped agent file

## Do Not Use Me For

- deriving permission patterns directly from package names — overlays resolve
  ONLY through the reviewed language-overlay set (never invent new patterns here)
- writing or editing any agent's `permission:` frontmatter — that stays a
  separate, gated, human-reviewed step (see `tools/ai/generate-agent-permissions.php`
  for this kit's own shipped agents)

## Workflow

1. confirm a fresh scan exists (run the `scan-stack` skill first if not)
2. run `php tools/ai/ai.php permissions-suggest` (refuses with a clear message if
   no fresh `.ai/stack-detection.json` exists)
3. optionally pass `--profile`, `--edit-surface`, `--verify-tier` to preview how
   the overlay composes for a specific agent shape
4. review the printed overlay entries and composed-model summary with the user
5. report the preview; do not apply anything yourself

## Gotchas

- refuses to run without a fresh scan-stack output — that is intentional, not a
  bug; run `scan-stack` first
- the composed preview is illustrative only; it never persists any file
- the immutable hard-deny floor cannot be weakened by this preview — that
  invariant is enforced by `aiPermissionComposeFromSpec()` itself
