# Edit Sequence

Use for controlled code changes.

---

## Required Flow

```bash
git status --short
rg -n "target"
bat -n path/to/file

# apply minimal change

git diff --check
git diff --stat
git diff
```

---

## Verification

Run the smallest relevant check first:

```bash
php artisan test --filter=SpecificTest
pnpm test -- --run
shellcheck scripts/*.sh
actionlint
```

Then run the affected broader check.

---

## Required Before Final Response

```bash
git diff --check
git diff --stat
```

---

## Example

See [`examples/good-bad-edit-sequence.md`](examples/good-bad-edit-sequence.md).
