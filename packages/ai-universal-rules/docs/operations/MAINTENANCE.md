# Maintenance

Treat this package like workflow infrastructure, not throwaway prompts.

## Update Triggers

- repeated workflow drift
- new runtime capability or deprecation
- repeated failure mode not captured in `gotchas.md`
- template or package docs falling behind the intended model

## Update Order

1. foundations docs
2. shared templates
3. capability folders
4. runtime adapters
5. manifest and quickstart

## Versioning Rule

- patch: wording or non-breaking support-file improvements
- minor: new workflow layers, capabilities, prompts, or agents
- major: compatibility-breaking structural changes or removed assets

## See Also

- `../RELEASE-BUNDLES.md` — export bundles and release workflow
- `../../PLACEHOLDERS.md` — placeholder reference when updating templates
- `EVAL-SCENARIOS.md` — scenarios to test after maintenance updates
- `TROUBLESHOOTING.md` — common failure modes introduced after updates
