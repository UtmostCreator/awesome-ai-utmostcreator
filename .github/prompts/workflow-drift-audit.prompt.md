---
name: workflow-drift-audit
description: Use to review AI workflow files, instruction drift, repo-context drift, and unsupported workflow claims
argument-hint: 'Describe the workflow, instruction, or adapter surface to audit for drift'
---

## What I Do

I audit AI workflow assets read-only and flag drift: stale paths, placeholder leaks, adapter divergence from the canonical source, unsupported claims about tools or hooks, and contradictions between instruction files. I report evidence-backed findings; I do not edit.

## When To Use Me

- when checking whether instruction, workflow, or adapter files still match reality
- when a doc references a tool, hook, or path that may no longer exist
- when Copilot/OpenCode/Claude adapters may have diverged from the canonical source
- before trusting a workflow claim that no evidence backs

## Read Alongside

- `docs/ai/capabilities/adapter-drift/CAPABILITY.md`
- `docs/ai/capabilities/project-context/CAPABILITY.md`
- `docs/ai/agents.md`

## Steps

1. Confirm instruction files reference correct, existing paths; flag any broken or stale reference with its file location.
2. Check for placeholder leaks and secret values in live workflow files; report only the path and required owner action, never the value.
3. Compare adapter files against the shared canonical source. Legitimate renderer-produced provider differences are not drift; only unexplained divergence from canonical is.
4. Flag unsupported workflow claims: features, tools, or hooks referenced but not present in the repo.
5. Check for contradictions across AGENTS.md, the Copilot/OpenCode/Claude instruction surfaces, and `docs/ai/agents.md`; confirm the roster enumerates every live agent file.
6. Report a verdict (CLEAN / DRIFT FOUND / ERRORS FOUND) with a severity-ranked findings table and a fix direction per row.

## Output

- a verdict and a severity-ranked findings table (file, issue, evidence, fix direction)
- a drift summary distinguishing renderer-legitimate differences from real divergence
- unknowns for any surface that was blocked, denied, or missing evidence

## Gotchas

- this is read-only; flag drift, never edit or invent new policy
- do not treat legitimate rendered provider differences as drift
- name the file and evidence for every finding; use `unknown` rather than guessing a verdict
- never print a leaked secret value; report its path and the owner action only
