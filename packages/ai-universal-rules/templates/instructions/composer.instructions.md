---
applyTo: "composer.json,composer.lock"
description: "Composer manifest and lockfile safety rules"
---

# Composer Rules

- Inspect existing constraints/scripts/autoload before edits.
- Prefer the smallest dependency change.
- Run `composer validate` after composer file changes when available.
- Do not run broad `composer update` unless explicitly approved.
