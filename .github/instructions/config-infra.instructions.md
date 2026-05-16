---
applyTo: "composer.json,composer.lock,package.json,pnpm-lock.yaml,package-lock.json,yarn.lock,vite.config.*,vitest.config.*,playwright.config.*,cypress.config.*,phpunit.xml*,pest.php"
description: "Dependency, runner, and build-config safety rules"
---

# Config and Build/Test Runner Rules

- Treat dependency manifests and lockfiles as high-impact surfaces.
- Do not narrow test globs to hide failures.
- Do not disable critical checks without explicit approval.
- Keep config edits minimal and verification-backed.
