# Git Diff and Review

Use before and after every edit.

---

## Preferred Commands

```bash
git status --short
git diff --stat
git diff --check
git diff
```

For human-readable diff:

```bash
git diff | delta
```

For syntax-aware file comparison:

```bash
difft old-file.ts new-file.ts
```

For history:

```bash
git log --oneline --decorate -20
git show --stat HEAD
git blame -L 100,140 path/to/file
```

---

## Use When

- checking current worktree
- reviewing generated edits
- finding whitespace/conflict markers
- understanding recent branch changes

---

## Avoid

Finishing work without:

```bash
git diff --check
git diff --stat
```

Example: [`../examples/good-bad-git-diff-review.md`](../examples/good-bad-git-diff-review.md)
