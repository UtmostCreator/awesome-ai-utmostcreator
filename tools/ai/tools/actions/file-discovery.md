# File Discovery

Use to find files, directories, changed files, and tracked files.

---

## Preferred Commands

Instead of `find . -name "*.php"` use:

```bash
fd "\.php$" app tests
```

Instead of `find . -type f` use:

```bash
rg --files | sort
```

Instead of `ls` / `tree` use:

```bash
eza -la --git
eza --tree --level=2
```

Instead of manual Git file listing use:

```bash
git ls-files "*.php"
git status --short
git diff --name-only
```

---

## Use When

- locating files by extension or name
- identifying changed files
- discovering repo structure
- reducing search noise before reading code

---

## Avoid

```bash
find . -type f
ls -R
```

unless preferred tools are unavailable.

Example: [`../examples/good-bad-file-discovery.md`](../examples/good-bad-file-discovery.md)
