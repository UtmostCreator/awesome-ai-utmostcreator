# GitHub Actions

Use for workflow YAML and CI validation.

---

## Preferred Commands

Instead of manual workflow review:

```bash
actionlint
```

Instead of grep in workflows:

```bash
yq '.on' .github/workflows/*.yml
yq '.jobs | keys' .github/workflows/*.yml
```

Instead of manual link checking in docs:

```bash
lychee README.md docs/**/*.md
```

---

## Use When

- editing `.github/workflows/*.yml`
- changing CI scripts
- checking workflow triggers
- checking broken docs links

---

## Avoid

```bash
grep -R "uses:" .github/workflows
```

Example: [`../examples/good-bad-github-actions.md`](../examples/good-bad-github-actions.md)
