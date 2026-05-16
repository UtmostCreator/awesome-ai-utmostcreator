---
applyTo: "**/*.php,composer.json,composer.lock,phpunit.xml*,pest.php"
description: "PHP backend, Composer, test-runner, and PHP CLI tooling safety rules"
---

# PHP Instructions

- Treat dependency files, migrations, auth paths, and public contracts as high-impact surfaces.
- Do not edit `composer.lock` unless dependency/platform changes are in scope.
- Prefer typed, explicit, existing project patterns over new abstractions.
- Preserve deterministic CLI behavior under `tools/ai/**`.
