# OpenCode Model Selection

This repository ships a provider-agnostic `opencode.jsonc` on purpose. It does not pin a `model` or `small_model`, because hardcoded model identifiers fail for any user whose account or provider does not expose those exact models. Choose models in your own global or project config instead.

## Why Models Are Not Shipped

- A pinned model is non-portable: it breaks installs for users without that provider or model.
- Project config overrides global config, so a shipped pin would silently override a user's deliberate choice.
- The shipped config keeps only provider-neutral keys, such as `compaction`, that are safe for every install.

## Discover Available Models

List the models your current OpenCode auth and providers actually expose, then copy an exact identifier:

```bash
opencode models
```

Never guess a model identifier. Use a string returned by `opencode models` verbatim.

## Opt Into Model Selection

Set models in your user-level config or in a local, untracked project override, not in the shipped template. Use the `provider/model` format from `opencode models`:

```jsonc
{
  "$schema": "https://opencode.ai/config.json",
  "model": "PROVIDER/MODEL",
  "small_model": "PROVIDER/SMALL_MODEL"
}
```

- `model` handles primary reasoning and edits.
- `small_model` handles cheap auxiliary work such as titles and summaries; set it to a smaller, faster model to reduce cost.

## Provider Prompt Caching

If your provider supports prompt caching, you can opt in per provider in your own config. For Anthropic the option is `setCacheKey`:

```jsonc
{
  "provider": {
    "anthropic": {
      "options": {
        "setCacheKey": true
      }
    }
  }
}
```

This is provider-specific and off by default. Confirm support for your provider before enabling it.

## See Also

- `docs/ai/context-economy.md` - keeping token and context usage bounded.
- `docs/ai/context-packing.md` - Repomix bundles and freshness.
