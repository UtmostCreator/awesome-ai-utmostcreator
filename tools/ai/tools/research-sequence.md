# Research Sequence

Use before planning or editing unfamiliar code.

---

## Default

```bash
git status --short
git diff --stat
git log --oneline --decorate -10
rg --files | head -200
rg -n "KEYWORD|ClassName|functionName"
bat -n path/to/relevant/file
```

---

## Then inspect project tasks

```bash
just --list 2>/dev/null || true
jq '.scripts' package.json 2>/dev/null || true
composer run-script --list 2>/dev/null || true
```

---

## If searching ownership/history

```bash
git blame -L 100,140 path/to/file
git log --follow -- path/to/file
git log -S "symbolName" -- path/to/file
```

---

## Example

See [`examples/good-bad-research-sequence.md`](examples/good-bad-research-sequence.md).
