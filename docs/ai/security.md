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

## Native read tools (grep / glob / list) — allow posture

OpenCode's native `grep`, `glob`, and `list` tools are set to `allow` in `opencode.jsonc`
(validated by `tools/ai/validate-ai-config.php`). This is intentional and safe:

- They are **read-only** content/file discovery tools that **bypass the `bash` matcher entirely**,
  so they cannot run pipes, redirects, or chained commands.
- They still honor the `read` secret-denies (`.env`, `.env.*`, `*.pem`, `*.key`, `*.crt`).
- Allowing them removes approval friction for safe searches, so agents do not fall back to raw
  `bash` (`grep file | head`) which breaks on the pipe and wastes turns.

Raw shell equivalents (`grep *`, `rg *`, `cat *`, `sed *`, …) remain `ask` under `permission.bash`,
and all execution/mutation surfaces are unchanged. The validator enforces `allow` for these three
native keys so the posture cannot be silently downgraded.

## Reader secret-path deny backstop (OpenCode only)

Read-only agents grant broad `allow` to content-printing reader wrappers (`preview-file.sh`,
`ai-search.sh`, `rg-code.sh`). On OpenCode — where the native `permission.bash` map is the real
enforcement surface (see the matrix above) — that broad `allow` is backed by a secret-path `deny`
backstop: the `core.safe_read.deny_secret_reads` pack (composed via the `backstop_deny_packs` lane
in `tools/ai/install/permission-layers/`) denies those readers against secret-file globs (`.env`,
`*.pem`, `*.key`, `*.crt`, `id_rsa*`, `secrets.*`, `credentials.*`, `auth.json`). OpenCode resolves
`permission.bash` by last-matching-rule-in-file-order (`.findLast()`), and the generator renders the
deny **after** the reader `allow`, so a secret-path invocation resolves to `deny`.

Honest scope: this backstop is **OpenCode-only**. Copilot and Claude project only allow-effect
entries into their `allowedBash` surface, so these deny entries are inertly skipped there; on those
runtimes the reader secret guard remains the **prompt-level Sensitive File Rule** in each agent
body, not a permission-level block. Coverage is also bounded to the three content-printing reader
wrappers and the enumerated secret globs; raw `git show/log/diff/blame` revspec access and any other
reader stay prompt-enforced. Do not describe the Copilot/Claude reader guard as permission-enforced.

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
