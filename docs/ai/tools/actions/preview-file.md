# Action: Preview File

Use this after search returns a file path or line number.

## Default

```bash
bash scripts/ai/preview-file.sh <path>
```

## AI evidence mode

```bash
AI_OUTPUT=json bash scripts/ai/preview-file.sh <path> --around <line> --context 30
```

## Exact range

```bash
AI_OUTPUT=json bash scripts/ai/preview-file.sh <path> --range 40:90
```

## Output contract

JSON mode returns `schema`, `status`, `tool`, `path`, `range`, `total_lines`, `truncated`, `content`, `limits`, `warnings`, `errors`, and `meta.size_bytes`.

## Size And Width Controls

- `--max-columns N` truncates each line to at most `N` columns; the default is suitable for terminal review.
- `--max-bytes N` caps total payload bytes when emitting JSON or text; oversized files are blocked unless `--force` is set.

Example bounded read:

```bash
AI_OUTPUT=json bash scripts/ai/preview-file.sh PATH --range 1:200 --max-columns 200 --max-bytes 65536
```

## Safety

- Binary-looking files are blocked unless `--force`.
- Oversized files are blocked unless `--force`.
- `.git` internals are blocked unless `--force`.
- Long lines are truncated by default; tune with `--max-columns`.
- Total payload is bounded; tune with `--max-bytes`.
- Colour is disabled by default.
