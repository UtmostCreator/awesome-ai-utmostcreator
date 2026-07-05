# Recommended Optional Tools

This doc records a completed Tier A/B/C review of external tooling for this kit.
It is hand-maintained (not generated) and is separate from
`docs/ai/repo-required-tools.md`, which is generated only from tool names
referenced inside `scripts/ai/*.sh`.

## Tier A — optional-first, directly integrated

Tools recommended for direct, optional-first integration into this kit itself.

- **Infection** (PHP mutation testing) — not yet integrated in this repository.
  Planned as a fully optional, approval-gated `just mutation-test` recipe (see
  the Phase 2 items of
  `docs/tickets/arch-todo-recommended-tools-tier-ab-integration-20260705-000000/plan.md`,
  gated on separate maintainer approval before implementation).
- **markdownlint** — already wired into `scripts/ai/ai-doc-check.sh:96-99` behind
  a `command -v markdownlint` guard. Note the naming mismatch: the script invokes
  the plain `markdownlint` binary, not `markdownlint-cli2`, even though
  `.markdownlint-cli2.yaml` exists at the repo root and implies the cli2 variant.
  This mismatch is intentional to record here so it is not re-litigated; no code
  change is made by this doc.
- **lychee** — already wired into `scripts/ai/ai-doc-check.sh:107-113` behind a
  `command -v lychee` guard (`--offline` link checking). Separately,
  `justfile:223`'s `lint` recipe calls `scripts/run-link-check.sh`, which does
  **not exist on disk** — this is a pre-existing broken reference, not introduced
  by this doc, and `just lint` currently fails at that step. Fixing it is out of
  scope here; it should be filed as its own follow-up bug.
- **actionlint / shellcheck / shfmt** — already wired into `just lint` / `just
  ci`. Confirmed as needing no action.

## Tier B — recommended for consumer repos only, never bundled

Tools recommended in documentation only, for repositories that install this
kit. These must never be bundled into this kit's own dependency surface.

- **OSV-Scanner** — `osv-scanner .`; zero-config; scans `composer.lock` for
  known vulnerabilities.
- **jscpd** — Node-based copy-paste detection; not applicable to this kit
  (no `package.json`); recommend for consumer repos or as a fully optional dev
  tool.
- **Trivy** — secondary to OSV-Scanner; roughly 40% overlap with the existing
  gitleaks/trufflehog secret-scanning surface; doc mention only.
- **Renovate** — recommended for consumer repos; low ROI for this kit's own
  two dev dependencies.
- **Serena / Aider** — ecosystem-adjacent agent tooling; doc mention only.

## Tier C — evaluated and discarded

Evaluated and fully discarded; name-only mention, no action items. Rationale:
no API, frontend, Kubernetes/infra, QA-team, or comparable service surface
exists in this repository. See
`docs/tickets/arch-todo-recommended-tools-tier-ab-integration-20260705-000000/plan.md`
for the full reasoning; it is not duplicated here.

CodeQL; Deptrac / PHPat / Pest-Arch; Kubernetes / Helm / Terraform / OpenTofu /
Ansible / Vault / SOPS / Argo / Flux; k6 / Playwright / Cypress / Percy /
axe-core / Maestro; oasdiff / Schemathesis; Knip / publint / StrykerJS;
PhpMetrics / PHP-Insights; PyDriller / git-of-theseus.

## AI-eval frameworks (deferred)

Whether Promptfoo, DeepEval, or OpenAI-Evals overlap with the existing
`evaluation-pack` (`docs/ai/capabilities/evaluation-and-regression/`) is
unresolved and explicitly out of scope here. It needs its own `>=75%`-overlap
audit per `docs/ai/capabilities/README.md` before any decision is made.
