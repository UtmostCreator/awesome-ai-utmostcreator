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

Regenerate (applies `--compress --style xml --depth 2 --top 0 --min-code 25 --min-files 1` and the default 1M-token budget profile):

```bash
SECRETS_SCAN=0 bash scripts/ai/run-repomix-context.sh .
```

Prefer `run-repomix-context.sh` over calling `repomix` directly so compression and bounded styling are applied consistently.

Use the focused 273K profile when the consumer has a smaller context window:

```bash
SECRETS_SCAN=0 bash scripts/ai/run-repomix-context.sh . \
  --context-window 273000 --reserved-output 12000 --instruction-overhead 18000 --safety-factor 0.8
```

Installed scripts also append common low-value generated/runtime paths to their hard excludes, so `.git/`, `.repomix-context/`, `.ai-logs/`, `.ai-backups/`, dependency directories, build outputs, caches, logs, and framework runtime cache/session/view folders stay out of context plans even when the target `.repomixignore` is missing or stale. The shipped `.repomixignore` is only a default template; installers should not replace a user's existing file just to enforce these script-level excludes.

For an exact single-file bundle, use the dedicated wrapper instead of the tree planner:

```bash
SECRETS_SCAN=0 bash scripts/ai/run-repomix-file.sh . docs/ai/context-packing.md
```

This keeps the file list exact by piping one repository-relative path into `repomix --stdin` and writes the bundle to the generated single-file context output area by default.

## One-Step Freshness Gate (Preferred For Agents)

Instead of checking freshness and regenerating as separate steps, call the single ask-gated wrapper:

```bash
bash scripts/ai/repomix-ensure-fresh.sh .
```

Behaviour (never silent, root-only regeneration):

- fresh: exits `0`, does nothing.
- stale: exits `0`, recommends regeneration (only regenerates if permitted).
- expired/missing: regenerates only with `--regen` or `REPOMIX_AUTO_REGEN=1`; otherwise exits non-zero with the exact regeneration command.
- non-interactive without opt-in: never prompts; prints the recommended command and exits.

Agents should call this wrapper rather than reading `.repomix-context` bundles directly, so stale context is caught before it reaches the model. Regeneration stays approval-gated by tool policy and always runs against the repository root only.
