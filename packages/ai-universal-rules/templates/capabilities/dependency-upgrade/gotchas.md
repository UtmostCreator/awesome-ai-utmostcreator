# Dependency Upgrade Gotchas

- Do not treat a version bump as low risk without checking actual usage surface.
- Do not rely on changelog assumptions when the repository's integration points are easy to inspect directly.
- Do not stop at install success if runtime or build behavior changed.
- Do not bundle unrelated upgrades into the same slice unless the repository already manages them together.
