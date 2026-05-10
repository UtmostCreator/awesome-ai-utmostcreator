# Shell Scripts

Use for shell syntax, linting, formatting, and tests.

---

## Preferred Commands

Instead of manual review:

```bash
shellcheck scripts/*.sh
```

Instead of manual formatting:

```bash
shfmt -d scripts/*.sh
shfmt -w scripts/*.sh
```

Instead of ad-hoc shell test runs:

```bash
bats tests/*.bats
```

Syntax check:

```bash
bash -n scripts/install.sh
```

---

## Use When

- editing `.sh`, `.bash`, `.zsh`
- reviewing install scripts
- validating CI shell snippets
- testing shell behaviour

---

## Avoid

```bash
bash script.sh
```

before syntax/lint checks when the script mutates files or environment.

Example: [`../examples/good-bad-shell-scripts.md`](../examples/good-bad-shell-scripts.md)
