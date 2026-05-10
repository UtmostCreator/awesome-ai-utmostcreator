# Text Search

Use to find exact symbols, phrases, routes, config keys, and test names.

---

## Preferred Commands

Instead of `grep -R` use:

```bash
rg -n "pattern"
```

Instead of grep inside a Git repo use:

```bash
git grep -n "pattern"
```

Instead of grep through PDFs/docs/archives use:

```bash
rga "pattern" docs/
```

Instead of grep in ignored folders use explicit globs:

```bash
rg -n "pattern" --glob "!vendor" --glob "!node_modules"
```

---

## Use When

- finding usages
- finding tests
- locating TODO/FIXME
- finding config keys
- finding route names or class names

---

## Avoid

```bash
grep -R "pattern" .
grep -rn "pattern" . | grep -v vendor
```

Example: [`../examples/good-bad-text-search.md`](../examples/good-bad-text-search.md)
