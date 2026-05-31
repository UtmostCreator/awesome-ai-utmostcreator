# Context Packing

Use installed context scripts only for bounded, approved context collection. Avoid packing secrets, vendor directories, or generated bundles.

## Repomix Context And Freshness

Repomix bundles under `.repomix-context/` are generated, not tracked. Before relying on them, check freshness so agents do not feed stale repository context to an LLM:

```bash
bash scripts/ai/repomix-freshness.sh .
```

Policy (read from `.repomix-context/tree-context/run-manifest.json`):

- `< 2 days`: fresh — OK to use.
- `>= 2 days` and `<= 7 days`: stale — warns and suggests regeneration (still usable).
- `> 7 days`: expired — exits non-zero; regenerate before use.
- missing manifest: exits non-zero; generate context first.

Regenerate (applies `--compress --style xml` for lower token usage):

```bash
SECRETS_SCAN=0 bash scripts/ai/run-repomix-context.sh .
```

Prefer `run-repomix-context.sh` over calling `repomix` directly so compression and bounded styling are applied consistently.
