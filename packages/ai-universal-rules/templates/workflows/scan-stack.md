---
name: scan-stack
description: Use to detect this project's language/tool stack and refresh the committed docs/ai/project/stack.md projection
argument-hint: 'Optional: comma-separated stack ids to force-select, e.g. php,markdown'
---

## What I Do

I run the existing stack-detection machinery (`php tools/ai/ai.php stack-detect`) and
refresh the committed `docs/ai/project/stack.md` projection with detected languages,
tools, and confidence — nothing here re-implements scanning.

## When To Use Me

- before generating or reviewing permission overlays for an install/upgrade
- when a project's language/tool signals changed (new manifest, new CI workflow)
- when `docs/ai/project/stack.md` looks stale relative to the repo's current files

## Do Not Use Me For

- hand-writing or inventing stack detection logic — I only wrap `aiStackDetect()` /
  `aiStackSelectionResolve()` via the `stack-detect` CLI verb
- editing `docs/ai/project-stack.md` — that is a different, unrelated legacy
  compatibility file; never touch it from this skill

## Workflow

1. run `php tools/ai/ai.php stack-detect` (add `--stacks <ids>` to force a selection,
   `--no-stack-detect` to skip auto-detection, `--no-write` to preview without writing)
2. review the printed detected/selected summary and each signal's confidence
3. confirm `docs/ai/project/stack.md` was written and reflects the current project
4. report the detected stacks and confidence to the user; do not silently accept a
   low-confidence signal as settled

## Gotchas

- `docs/ai/project/stack.md` (this skill's output, WITH the `/project/` subfolder) is
  distinct from `docs/ai/project-stack.md` (an unrelated legacy shim, no subfolder) —
  never confuse the two or write to the legacy path
- this skill previews/reports only; it does not select agent permissions or write
  permission frontmatter (see the `generate-permissions` skill for that step)
