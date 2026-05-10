# File Viewing

Use to inspect files with line numbers and bounded ranges.

---

## Preferred Commands

Instead of `cat file.php` use:

```bash
bat -n file.php
```

Instead of reading a huge file use:

```bash
bat --line-range 40:120 file.php
```

For agent-friendly output:

```bash
bat -n --paging=never file.php
```

For logs:

```bash
tail -100 storage/logs/laravel.log
lnav storage/logs/*.log
```

---

## Use When

- reading source files
- quoting line ranges for review
- inspecting logs
- avoiding huge terminal output

---

## Avoid

```bash
cat large-file.php
tail -f many/*.log
```

Example: [`../examples/good-bad-file-viewing.md`](../examples/good-bad-file-viewing.md)
