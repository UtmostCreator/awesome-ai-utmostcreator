# Security & Runtime Enforcement

This document records the kit's security posture and an **honest** runtime enforcement matrix.
Per core invariant 6 (*no false enforcement claims*), a runtime is listed as **enforced** only when
its hooks are provably invoked by that runtime; everything else is documented as **advisory**.

## Runtime requirements

- **PHP is required only at install/upgrade time.** The installer, upgrader, manifest/lock writer,
  and the policy compiler (`tools/ai/compile-command-policy.php`) run in PHP.
- **Runtime hooks are PHP-free.** The pre/post tool hooks and guardians are POSIX `sh` (+ a
  PowerShell sibling) and use only `grep`/`tr` — no PHP, no `yq`, no `jq` required to enforce policy.

## Enforcement matrix

| Surface | Mechanism | Files | Provably invoked? | Status |
| --- | --- | --- | --- | --- |
| GitHub Copilot CLI | preToolUse / postToolUse hooks | `.github/hooks/tool-policy.json` → `scripts/ai/pre-tool-use.sh`, `scripts/ai/post-tool-use.sh` | Yes — Copilot CLI loads `.github/hooks/*.json` | **Enforced** |
| GitHub Copilot CLI (guardian) | preToolUse hook | `.github/hooks/tool-guardian.json` → `tool-guardian.sh` (POSIX) / `tool-guardian.ps1` (Windows) | Yes — same hook loader | **Enforced** |
| Compiled tier policy | dependency-free `case` table | `.github/hooks/scripts/command-policy.compiled.sh` (compiled from `docs/ai/command-policy.tiers.yaml`) | Only when a runtime invokes it | **Available** |
| OpenCode | `opencode.jsonc` | n/a — the kit does **not** wire `pre-tool-use.sh` into OpenCode | No | **Advisory** |
| Editor / IDE agents | agent instruction files | `AGENTS.md`, `.opencode/`, `.github/` | No (instruction-only) | **Advisory** |

> Advisory means the policy and guardians exist and document intent, but the kit cannot prove the
> runtime calls them. Do not claim a surface is "enforced" until its hook invocation is verified.

## Guardian deny coverage

The guardians (`tool-guardian.sh` / `.ps1`, kept at enforced rule parity) block:

- destructive git: `reset --hard`, force push, `clean -`, `checkout/restore --`
- destructive filesystem: `rm -rf`, Windows `del /s|q`, `rmdir /s|q`
- remote pipe-to-shell: `curl|wget … | sh|bash|python|…`
- data exfiltration: `curl|wget|nc … --data|--upload-file|--data-binary`
- obfuscated execution: `base64 -d … | sh`
- permission mutation: `chmod|chown|chgrp`
- secret/credential access: `.env` reads, `~/.ssh`, `~/.aws/credentials`, `.npmrc`, `.netrc`,
  `*.pem`, `*.key`, and generic `credentials|secret|token|id_rsa`

`GUARD_MODE=warn` downgrades a block to a warning (still printed); `SKIP_TOOL_GUARD=true` bypasses
the guardian entirely (use only for trusted automation).

## Compiled tier policy

`docs/ai/command-policy.tiers.yaml` is the single source of truth for tiered allow/ask/deny. It is
compiled to a dependency-free guard:

```bash
php tools/ai/compile-command-policy.php          # write .github/hooks/scripts/command-policy.compiled.sh
php tools/ai/compile-command-policy.php --check   # CI drift check, writes nothing
```

Decision precedence in the compiled guard is **deny > ask > allow > default-allow**.

### Local overrides (no-downgrade)

A target repo may widen allow via `.ai/project.yml`:

```yaml
policy:
  allow:
    - "pnpm run build"
```

Hard limits enforced at compile time (compile fails otherwise):

- wildcards (`*`) are rejected in local allows,
- a local allow may **not** downgrade any global `deny`,
- a local allow may **not** downgrade a tier ≥ 3 (`ask`) command.

## Reporting

Treat suspected policy bypasses, guardian gaps, or over-claimed enforcement as security issues and
fix them before relying on the affected surface.
