# Release Bundles

`packages/ai-universal-rules/` now supports curated export bundles for adoption and release previews.

## Why

- keep the canonical package in one repo
- ship smaller starter profiles without forcing adopters to copy every optional asset
- make package contents inspectable before publishing or copying into another repo

## Starter Profiles

- `minimal-starter` - core policy, shared templates, and the foundational capability set
- `dual-runtime-starter` - the base package plus both OpenCode and GitHub Copilot adapters
- `strict-governance-starter` - the dual-runtime bundle plus stronger approval and operations material

## Validate Before Export

```powershell
php tools/ai/validate-ai-catalog.php
php tools/ai/generate-ai-catalog.php --check
php tools/ai/export-ai-universal-rules.php --check
```

## Export A Bundle

```powershell
php tools/ai/export-ai-universal-rules.php --profile=dual-runtime-starter
```

This writes a directory bundle to `dist/ai-universal-rules/<version>/<profile>/` and includes `RELEASE-MANIFEST.json` for machine-readable inspection.

## Release Notes For Maintainers

- regenerate the catalog before exporting
- keep `manifest.json` and `manifest.yml` aligned on name, version, and description
- treat starter profiles as curated adoption shapes, not separate sources of truth

## See Also

- `INSTALL-CATALOG.md` — full profile and pack index from the installer registries
- `../QUICKSTART.md` — step-by-step install flow for adopters
- `operations/MAINTENANCE.md` — versioning rules and update order
- `../../docs/ai/external-repo-install.md` — external repository install examples
