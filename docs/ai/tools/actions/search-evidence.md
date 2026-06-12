# Action: Search Evidence

Use this action to collect script-first repository evidence before raw search or broad file reads.

## Mandatory sequence

```bash
git status --short
AI_OUTPUT=json bash scripts/ai/ai-search.sh changed-text QUERY . --fixed
AI_OUTPUT=json bash scripts/ai/ai-search.sh staged-text QUERY . --fixed
AI_OUTPUT=json bash scripts/ai/ai-search.sh tracked QUERY . --fixed
```

Replace `QUERY` with the literal string or pattern to search.

## Escalation order

If `changed`, `staged`, and `tracked` evidence is insufficient:

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh text QUERY . --fixed
AI_OUTPUT=json bash scripts/ai/ai-search.sh files QUERY .
AI_OUTPUT=json bash scripts/ai/ai-search.sh struct QUERY .
```

Use `all` only when narrower modes return no useful hits.

## Companion reads

After a hit, preview the relevant lines:

```bash
AI_OUTPUT=json bash scripts/ai/preview-file.sh PATH --around LINE --context 30
```

## Output expectations

- `schema`, `status`, `tool`, `path`, `range`, `content`, `warnings`, `errors` appear on the JSON envelope.
- A `status` value of `unsafe_blocked` or a `dry_run` preview means the caller must escalate to the human before re-running without those guards.

## Related

- `docs/ai/tools/ai-search.md`
- `docs/ai/tools/tool-map.md`
- `docs/ai/tools/actions/preview-file.md`
- `docs/ai/tools/actions/use-ai-script.md`
