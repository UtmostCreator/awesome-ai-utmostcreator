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

## Safety

- Binary-looking files are blocked unless `--force`.
- Oversized files are blocked unless `--force`.
- `.git` internals are blocked unless `--force`.
- Long lines are truncated by default.
- Colour is disabled by default.
