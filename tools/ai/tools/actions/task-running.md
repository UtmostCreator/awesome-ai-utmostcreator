# Task Running

Use to discover and run project-defined commands.

---

## Preferred Commands

Instead of guessing commands:

```bash
just --list
```

For Node scripts:

```bash
jq '.scripts' package.json
```

For Composer scripts:

```bash
composer run-script --list
```

For watch mode:

```bash
watchexec -e php,md -- just verify
```

For runtime versions:

```bash
mise current
```

For env state:

```bash
direnv status
```

---

## Use When

- discovering project tasks
- running verification
- avoiding README drift
- checking runtime versions

---

## Approval Required

```bash
direnv allow
mise install
pnpm install
npm install
composer update
```

Example: [`../examples/good-bad-task-running.md`](../examples/good-bad-task-running.md)
