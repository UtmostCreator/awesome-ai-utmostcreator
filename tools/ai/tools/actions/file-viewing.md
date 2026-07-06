# File Viewing

Use to inspect files with line numbers and bounded ranges.

---

## Preferred Commands

Instead of `cat file.php` use:

```bash
bat -n file.php
```

Instead of reading a huge or generated file use:

```bash
scripts/ai/preview-file.sh file.php --range 40:120 --max-bytes 200000
```

The wrapper enforces a 64KiB raw-file safety gate by default; raise it explicitly with `--max-bytes` for known-large files. Reserve `--force` for the exceptional case where a file must be inspected despite the gate, and state why — it is not a default recommendation.

For a normal-sized file where the safety gate does not apply, raw `bat --line-range` is fine:

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
bat --line-range 40:120 huge-or-generated-file.php
```

Example: [`../examples/good-bad-file-viewing.md`](../examples/good-bad-file-viewing.md)
