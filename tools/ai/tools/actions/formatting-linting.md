# Formatting and Linting

Use project-specific formatters and linters.

---

## Preferred Commands

PHP:

```bash
php -l app/File.php
vendor/bin/pint --test
vendor/bin/pint app/File.php
composer validate
```

JS/TS/Vue:

```bash
pnpm lint
pnpm typecheck
prettier --check .
prettier --write path/to/file.ts
vue-tsc --noEmit
```

Shell:

```bash
shellcheck scripts/*.sh
shfmt -d scripts/*.sh
```

Semantic checks:

```bash
semgrep --config auto
```

---

## Use When

- after editing source code
- before final response
- after generated file changes
- after CI/workflow edits

---

## Avoid

Formatting entire repository unless requested.

Example: [`../examples/good-bad-formatting-linting.md`](../examples/good-bad-formatting-linting.md)
