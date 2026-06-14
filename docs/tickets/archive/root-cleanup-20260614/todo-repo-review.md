Below is the combined **P0–PN verification + cleanup + refactor plan** focused on reducing **shipped files**, not just making SCC look smaller.

Verified repo state (corrected; the prior "latest data" header conflated the
working-tree count with the shipped/tracked surface):

```text
docs/ai/generated/: gitignored AND 0 files tracked (advisor-context.md exists
  only on disk, ~2MB untracked) -> NOT a git-tracking problem; it is a
  source-only SCC/analysis exclusion concern.
Tracked generated/runtime noise: .ai-logs/README.md is being deleted in the
  in-flight reorg, not retained as tracked noise.
Main issue: shipped/generated/manual/provider surfaces are mixed (still true).
```

## Target outcome

| Metric                           |           Current |                       Target |
| -------------------------------- | ----------------: | ---------------------------: |
| Full working-tree SCC            |         950 files |                Not important |
| Source-only SCC                  |     Unknown final |                450–650 files |
| Markdown files counted as source |               533 |                      220–320 |
| Human-maintained Markdown        |         533 mixed |                      120–220 |
| Shipped package files            |           Unknown |             Reduce by 30–50% |
| Generated docs shipped           |       Many likely | 0 unless explicitly required |
| Root planning docs               |     7 large files |                    0 in root |
| Placeholder docs                 | Many 3-line files |        0 public placeholders |
| Duplicate provider/package docs  |         Confirmed |    Removed or generated only |

---

# P0 — Define “shipped files” and create a shipping audit

Right now you cannot safely reduce shipped files until you can answer:

```text
What is source?
What is generated?
What is repo-local dogfooding?
What is package template?
What is actually installed into another repo?
```

## P0.1 Create shipping policy

Create:

```text
docs/ai/architecture/shipping-surface.md
```

Content should define this:

```md
# Shipping Surface Policy

## Source of truth

The package source of truth lives under:

- `packages/ai-universal-rules/templates/**`
- `packages/ai-universal-rules/docs/**`
- `packages/ai-universal-rules/policies/**`
- `packages/ai-universal-rules/catalog.json`
- `tools/ai/**`
- `scripts/ai/**` only when explicitly required by install/runtime workflows

## Repo-local surfaces

These are installed projections for this repository and must not be treated as canonical package source:

- `.github/agents/**`
- `.github/prompts/**`
- `.github/instructions/**`
- `.github/skills/**`
- `.opencode/agents/**`
- `.opencode/commands/**`
- `.opencode/skills/**`

## Generated/report surfaces

These must not be shipped by default:

- `docs/ai/generated/**`
- `docs/tickets/**`
- `.ai-logs/**`
- `dist/**`
- `vendor/**`
- `node_modules/**`
- `.opencode/node_modules/**`

## Human repository documentation

These are for contributors/readers, not necessarily package shipping:

- `README.md`
- `AGENTS.md`
- `CLAUDE.md`
- `SECURITY.md`
- `SUPPORT.md`
- `docs/ai/**`, excluding generated/package mirrors
```

## P0.1 Acceptance criteria

```text
AC-P0.1.1: shipping-surface.md exists.
AC-P0.1.2: Every major top-level directory is classified as source, generated, repo-local, archive, dependency, or shipped.
AC-P0.1.3: The doc explicitly says `.github/**` and `.opencode/**` are provider projections, not canonical source.
AC-P0.1.4: The doc explicitly says `docs/ai/generated/**` is not shipped.
```

## P0.1 Verification

```bash
grep -E "docs/ai/generated|packages/ai-universal-rules|\.opencode|\.github" docs/ai/architecture/shipping-surface.md
```

---

# P0.2 Create one canonical exclusion list

Create:

```text
scripts/ai/internal/config/exclude-dirs.txt
```

Recommended content:

```text
.git
node_modules
.opencode/node_modules
vendor
dist
.ai-logs
docs/ai/generated/logs
docs/tickets/archive
```

For **source-only analysis**, also exclude:

```text
docs/ai/generated
docs/tickets
```

Create:

```text
scripts/ai/internal/config/source-exclude-dirs.txt
```

Content:

```text
.git
node_modules
.opencode/node_modules
vendor
dist
.ai-logs
docs/ai/generated
docs/tickets
```

## P0.2 Acceptance criteria

```text
AC-P0.2.1: All SCC, tree, repomix, and context-packing scripts use the same exclude list.
AC-P0.2.2: No script hardcodes a different exclusion policy unless documented.
AC-P0.2.3: `.opencode/node_modules` is excluded from all reports.
AC-P0.2.4: `docs/ai/generated/advisor-context.md` is excluded from source-only reports.
```

## P0.2 Verification

```bash
grep -R "node_modules\|docs/ai/generated\|docs/tickets/archive" scripts tools \
  --exclude-dir vendor \
  --exclude-dir dist
```

Then run:

```bash
scc . \
  --exclude-dir .git \
  --exclude-dir node_modules \
  --exclude-dir .opencode/node_modules \
  --exclude-dir vendor \
  --exclude-dir dist \
  --exclude-dir .ai-logs \
  --exclude-dir docs/ai/generated \
  --exclude-dir docs/tickets
```

---

# P0.3 Create a shipped-file audit command

Add one script:

```text
scripts/ai/ship-audit.sh
```

Purpose:

```text
List what would be shipped.
Fail if generated, archive, dependency, or repo-local provider projections are included.
```

Minimum implementation idea:

```bash
#!/usr/bin/env bash
set -euo pipefail

bad_patterns='
^docs/ai/generated/
^docs/tickets/
^dist/
^vendor/
^node_modules/
^\.opencode/node_modules/
^\.ai-logs/
'

git ls-files |
while IFS= read -r file; do
  case "$file" in
    docs/ai/generated/*|docs/tickets/*|dist/*|vendor/*|node_modules/*|.opencode/node_modules/*|.ai-logs/*)
      printf 'not-shippable: %s\n' "$file"
      ;;
  esac
done
```

Later, replace this with an allowlist-based PHP verifier.

## P0.3 Acceptance criteria

```text
AC-P0.3.1: `bash scripts/ai/ship-audit.sh` exists and runs.
AC-P0.3.2: It reports generated/archive/dependency/runtime files.
AC-P0.3.3: It exits non-zero when forbidden shipped paths are present.
AC-P0.3.4: CI or local verification calls it.
```

## P0.3 Verification

```bash
bash scripts/ai/ship-audit.sh
```

---

# P1 — Source-only analysis exclusion for giant generated artifacts

> ALREADY-DONE (git tracking): `docs/ai/generated/` is gitignored and has **0
> tracked files** (verified: `git ls-files docs/ai/generated/` is empty). The
> large `advisor-context.md` exists only on disk and is never committed. The
> original "remove from Git if tracked" step is a no-op and has been dropped.

The remaining (still-valid) concern is **source-only analysis exclusion**: SCC /
repomix / context-packing should not count the untracked multi-MB generated file.
That is handled by the canonical source-exclude list in P0.2, not by git.

## P1.1 Verification (confirm already-satisfied state)

```bash
AI_OUTPUT=json bash scripts/ai/bin/read/ai-search.sh files advisor-context docs/ai/generated
git ls-files docs/ai/generated/   # expect: empty
```

Source-only count:

```bash
find . \
  -path './.git' -prune -o \
  -path './node_modules' -prune -o \
  -path './.opencode/node_modules' -prune -o \
  -path './vendor' -prune -o \
  -path './dist' -prune -o \
  -path './.ai-logs' -prune -o \
  -path './docs/ai/generated' -prune -o \
  -path './docs/tickets' -prune -o \
  -name '*.md' -type f -exec wc -l {} + |
sort -nr |
head -30
```

---

# P1.2 Mark generated docs clearly

These look generated or semi-generated:

```text
docs/ai/catalog.md
docs/ai/installed-files.md
docs/ai/repo-required-tools.md
docs/ai/script-registry.md
docs/ai/scripts-reference.md
scripts/ai/MANIFEST.md
docs/ai/generated/repo-structure.md
docs/ai/generated/install-instructions.md
```

Each generated file should begin with:

```md
<!-- GENERATED FILE: do not edit manually. -->
<!-- Source: <script-or-template-path> -->
<!-- Regenerate: <command> -->
```

## P1.2 Acceptance criteria

```text
AC-P1.2.1: Every generated Markdown file has a GENERATED FILE header.
AC-P1.2.2: Every generated Markdown file names its source.
AC-P1.2.3: Every generated Markdown file names the regeneration command.
AC-P1.2.4: Verification fails if a generated file lacks the header.
```

## P1.2 Verification

```bash
for f in \
  docs/ai/catalog.md \
  docs/ai/installed-files.md \
  docs/ai/repo-required-tools.md \
  docs/ai/script-registry.md \
  docs/ai/scripts-reference.md \
  scripts/ai/MANIFEST.md \
  docs/ai/generated/repo-structure.md \
  docs/ai/generated/install-instructions.md
do
  test -f "$f" || continue
  head -5 "$f" | grep -q "GENERATED FILE" || {
    echo "missing generated header: $f"
    exit 1
  }
done
```

---

# P2 — Archive root planning docs

These should not live in root:

```text
ai-search-todo-tests.md              1,414
readme-install.md                    1,222
todo-agents-script-rework.md           825
todo-introspect-improvement.md         748
todo-agents-rework.md                  669
improvement-plan.md                    378
todo-scripts-refactor.md               375
```

## P2.1 Move root todo/planning docs

> CORRECTED: most root todo docs are **untracked** (`todo-*.md`,
> `todo-repo-review.md`, `todo-scripts-improvement.md`). `git mv` fails on
> untracked files — use plain `mv`. Only `ai-search-todo-tests.md`,
> `improvement-plan.md`, and `readme-install.md` are git-tracked (use `git mv`
> for those).

```bash
mkdir -p docs/tickets/archive/root-cleanup-20260614

# untracked -> plain mv
mv todo-agents-script-rework.md docs/tickets/archive/root-cleanup-20260614/
mv todo-introspect-improvement.md docs/tickets/archive/root-cleanup-20260614/
mv todo-agents-rework.md docs/tickets/archive/root-cleanup-20260614/
mv todo-scripts-refactor.md docs/tickets/archive/root-cleanup-20260614/
mv todo-scripts-improvement.md docs/tickets/archive/root-cleanup-20260614/
mv todo-repo-review.md docs/tickets/archive/root-cleanup-20260614/

# tracked -> git mv
git mv ai-search-todo-tests.md docs/tickets/archive/root-cleanup-20260614/
git mv improvement-plan.md docs/tickets/archive/root-cleanup-20260614/
```

Move install guide, not archive (tracked file):

```bash
mkdir -p docs/ai/getting-started
git mv readme-install.md docs/ai/getting-started/full-install-guide.md
```

## P2.1 Acceptance criteria

```text
AC-P2.1.1: No `todo-*.md` files remain in root.
AC-P2.1.2: No `*-plan.md` files remain in root.
AC-P2.1.3: `readme-install.md` is moved to `docs/ai/getting-started/full-install-guide.md`.
AC-P2.1.4: README links still work.
```

## P2.1 Verification

```bash
find . -maxdepth 1 -type f \( -name '*todo*.md' -o -name '*plan*.md' -o -name 'readme-install.md' \) -print
```

Expected output:

```text
# empty
```

Then:

```bash
bash scripts/ai/check-file-refs.sh
```

---

# P3 — Remove duplicate package docs mirror

Confirmed duplicate:

```text
packages/ai-universal-rules/docs/BROWSE.md
docs/ai/package/BROWSE.md
```

Canonical should be:

```text
packages/ai-universal-rules/docs/**
```

Remove mirror (CORRECTED: `docs/ai/package/**` is **untracked**, so `git rm`
fails — use plain `rm`. It is also already drifting from the canonical copy
(e.g. BROWSE.md 32842 vs 32991 bytes), so diff + back up before deleting since
untracked files are not recoverable via git):

```bash
# 1. confirm no unique content vs canonical
diff -rq docs/ai/package packages/ai-universal-rules/docs || true
# 2. back up (untracked = no git recovery)
tar czf /tmp/docs-ai-package-backup.tgz docs/ai/package
# 3. remove
rm -r docs/ai/package
```

## P3 Acceptance criteria

```text
AC-P3.1: `docs/ai/package/**` no longer exists.
AC-P3.2: Package docs remain in `packages/ai-universal-rules/docs/**`.
AC-P3.3: Any docs linking to `docs/ai/package/**` are updated.
AC-P3.4: `source-of-truth.md` documents this rule.
```

## P3 Verification

```bash
test ! -e docs/ai/package || {
  echo "docs/ai/package still exists"
  exit 1
}

grep -R "docs/ai/package" . \
  --exclude-dir .git \
  --exclude-dir vendor \
  --exclude-dir dist \
  --exclude-dir node_modules \
  --exclude-dir .opencode/node_modules
```

---

# P4 — Decide provider projections: source, generated, or shipped?

You have triplicate agent surfaces:

```text
packages/ai-universal-rules/templates/core/agents/researcher.md
.opencode/agents/researcher.md
.github/agents/researcher.agent.md
```

Recommended policy:

| Path                                                       |       Keep in repo? |               Ship? | Canonical? |
| ---------------------------------------------------------- | ------------------: | ------------------: | ---------: |
| `packages/ai-universal-rules/templates/core/agents/**`     |                 Yes |                 Yes |        Yes |
| `packages/ai-universal-rules/templates/optional/agents/**` |                 Yes |                 Yes |        Yes |
| `.opencode/agents/**`                                      | Optional repo-local | No package shipping |         No |
| `.github/agents/**`                                        | Optional repo-local | No package shipping |         No |
| `.opencode/agents-optional/**`                             |       Prefer remove |                  No |         No |

## P4.1 Add generated provenance headers

> CORRECTED: these provider files **start with YAML frontmatter (`---`)**. A
> comment placed ABOVE the frontmatter can break YAML/agent parsers. Add
> provenance as a key INSIDE the frontmatter, and match the repo's existing
> convention (`AGENTS.md` uses `<!-- GENERATED — DO NOT EDIT: rendered by
> ai-kit installer from <template> -->`, not `GENERATED FROM`). Verify one file
> against `php tools/ai/validate-ai-config.php` + `php tools/ai/validate-adapter-drift.php`
> before bulk-applying.

Add a `generated_from:` key inside the existing frontmatter, e.g.:

```yaml
---
id: researcher
generated_from: packages/ai-universal-rules/templates/core/agents/researcher.md
# ... existing keys ...
---
```

## P4.1 Acceptance criteria

```text
AC-P4.1.1: Every `.opencode/agents/*.md` file has `GENERATED FROM`.
AC-P4.1.2: Every `.github/agents/*.agent.md` file has `GENERATED FROM`.
AC-P4.1.3: Provider files are not hand-edited without changing canonical templates.
AC-P4.1.4: Shipping audit excludes root `.github/**` and `.opencode/**` unless explicitly requested.
```

## P4.1 Verification

```bash
for dir in .opencode/agents .github/agents; do
  find "$dir" -name '*.md' -type f -print |
  while IFS= read -r f; do
    head -5 "$f" | grep -q "GENERATED FROM" || {
      echo "missing provider provenance: $f"
      exit 1
    }
  done
done
```

## P4.2 Remove `.opencode/agents-optional` if duplicated

If optional agents already exist here:

```text
packages/ai-universal-rules/templates/optional/agents/**
```

then remove this generated mirror (verified: `.opencode/agents-optional/`
exists AND is git-tracked, so `git rm` is correct here — but first confirm the
optional agents are genuinely duplicated under
`packages/ai-universal-rules/templates/optional/agents/**`):

```bash
git rm -r .opencode/agents-optional
```

## P4.2 Acceptance criteria

```text
AC-P4.2.1: Optional agents have one canonical source.
AC-P4.2.2: `.opencode/agents-optional/**` is not shipped.
AC-P4.2.3: Installer can still generate/install optional agents when requested.
```

---

# P5 — Clean placeholder docs

You have many public docs with only 3 lines:

```text
docs/ai/architecture-locks.md
docs/ai/command-policy.md
docs/ai/session-reentry.md
docs/ai/hooks.md
docs/ai/failure-handling.md
docs/ai/agent-ops.md
docs/ai/tool-policy.md
docs/ai/handoff-contract.md
docs/ai/ownership.md
docs/ai/approval-boundaries.md
```

> HIGH BLAST RADIUS (corrected): these 3-line files are all git-tracked and
> referenced as **canonical** from `execution-protocol.md`, `agents.md`,
> `source-of-truth.md`, capabilities, and `docs/ai/installed-files.md` (the
> install manifest). Do NOT bulk-move or delete them. **Prefer expand-in-place**
> (>=20 lines). If a move is truly required, first generate a reference map via
> `scripts/ai/bin/read/ai-search.sh` and update every referrer + the install
> manifest in the same change.

These hurt trust. Prefer expanding them in place; merge/delete only with a full
reference map.

## P5.1 Rule

```text
No human-facing Markdown under `docs/ai/**` may be under 20 lines unless:
- it is an index README,
- it is a snippet/template,
- it is generated,
- or it is explicitly marked as intentionally minimal.
```

## P5.2 Suggested merge targets

| Placeholder                      | Better destination                              |
| -------------------------------- | ----------------------------------------------- |
| `docs/ai/command-policy.md`      | `docs/ai/governance/command-policy.md`          |
| `docs/ai/tool-policy.md`         | `docs/ai/governance/tool-policy.md`             |
| `docs/ai/approval-boundaries.md` | `docs/ai/governance/approval-boundaries.md`     |
| `docs/ai/architecture-locks.md`  | `docs/ai/architecture/architecture-locks.md`    |
| `docs/ai/handoff-contract.md`    | `docs/ai/architecture/handoff-contract.md`      |
| `docs/ai/ownership.md`           | `docs/ai/architecture/ownership.md`             |
| `docs/ai/failure-handling.md`    | `docs/ai/operations/failure-handling.md`        |
| `docs/ai/session-reentry.md`     | `docs/ai/operations/session-reentry.md`         |
| `docs/ai/agent-ops.md`           | merge into `docs/ai/agents.md`                  |
| `docs/ai/hooks.md`               | merge into generated/tools/hooks docs or delete |

## P5 Acceptance criteria

```text
AC-P5.1: No public `docs/ai/*.md` placeholder remains at 3 lines.
AC-P5.2: No human-facing docs/ai file under 20 lines unless explicitly allowed.
AC-P5.3: Moved docs have redirects or updated links.
AC-P5.4: `check-file-refs.sh` passes.
```

## P5 Verification

```bash
find docs/ai -name '*.md' -type f -exec wc -l {} + |
sort -n |
awk '$1 < 20 {print}'
```

Then:

```bash
bash scripts/ai/check-file-refs.sh
```

---

# P6 — Split `docs/ai` into stable sections

Current `docs/ai` mixes governance, generated, package mirrors, setup, project docs, and references.

Target:

```text
docs/ai/
├── README.md
├── getting-started/
├── architecture/
├── governance/
├── operations/
├── reference/
├── tools/
├── capabilities/
├── project/
├── shared/
└── generated/
```

## P6.1 Move obvious docs

```bash
mkdir -p \
  docs/ai/getting-started \
  docs/ai/architecture \
  docs/ai/governance \
  docs/ai/operations \
  docs/ai/reference
```

```bash
git mv docs/ai/SETUP.md docs/ai/getting-started/setup.md
git mv docs/ai/POST-INSTALL.md docs/ai/getting-started/post-install.md

git mv docs/ai/source-of-truth.md docs/ai/architecture/source-of-truth.md
git mv docs/ai/schema-ownership.md docs/ai/architecture/schema-ownership.md
git mv docs/ai/adapter-contract.md docs/ai/architecture/adapter-contract.md

git mv docs/ai/security.md docs/ai/governance/security.md
git mv docs/ai/MCP-BOUNDARIES.md docs/ai/governance/mcp-boundaries.md

git mv docs/ai/execution-protocol.md docs/ai/operations/execution-protocol.md
git mv docs/ai/validation.md docs/ai/operations/validation.md
git mv docs/ai/verification-matrix.md docs/ai/operations/verification-matrix.md

git mv docs/ai/catalog.md docs/ai/reference/catalog.md
git mv docs/ai/installed-files.md docs/ai/reference/installed-files.md
git mv docs/ai/repo-required-tools.md docs/ai/reference/repo-required-tools.md
git mv docs/ai/script-registry.md docs/ai/reference/script-registry.md
git mv docs/ai/scripts-reference.md docs/ai/reference/scripts-reference.md
```

## P6 Acceptance criteria

```text
AC-P6.1: `docs/ai` root contains mostly index files, not random policy/reference docs.
AC-P6.2: Architecture docs live under `docs/ai/architecture`.
AC-P6.3: Governance docs live under `docs/ai/governance`.
AC-P6.4: Operations docs live under `docs/ai/operations`.
AC-P6.5: Generated docs remain under `docs/ai/generated`.
```

## P6 Verification

```bash
find docs/ai -maxdepth 1 -type f -name '*.md' -print | sort
```

This should be short.

---

# P7 — Reduce shipped capabilities/skills duplication

You have repeated surfaces like:

```text
templates/workflows/review-diff.md
.opencode/commands/review-diff.md
.github/prompts/review-diff.prompt.md
.github/skills/review-diff/SKILL.md
.opencode/skills/review-diff/SKILL.md
```

This may be intentional, but should be generated from one canonical record.

## Recommended source model

| Concept                          | Canonical source                                                          |
| -------------------------------- | ------------------------------------------------------------------------- |
| Capability definition            | `packages/ai-universal-rules/templates/capabilities/<name>/CAPABILITY.md` |
| Workflow                         | generated from capability metadata                                        |
| OpenCode command                 | generated projection                                                      |
| GitHub prompt                    | generated projection                                                      |
| Skill                            | generated projection                                                      |
| Docs examples/checklists/gotchas | either canonical under capability or generated from fragments             |

## P7 Acceptance criteria

```text
AC-P7.1: Each capability has exactly one canonical source directory.
AC-P7.2: Generated command/prompt/skill surfaces include `GENERATED FROM`.
AC-P7.3: Shipping package does not include both canonical and generated projections unless explicitly required.
AC-P7.4: Drift verifier compares canonical source to provider projections.
```

## P7 Verification

```bash
find packages/ai-universal-rules/templates -path '*capabilities*' -name 'CAPABILITY.md' -print | sort
find .github .opencode -name '*.md' -type f -exec grep -L "GENERATED FROM" {} +
```

---

# P8 — Add strong verification gates

You need a single command that answers:

```text
Is the repo clean?
Are generated files excluded?
Are shipped files allowed?
Are provider projections marked?
Are placeholder docs gone?
Are links still valid?
```

## P8.1 Add `just verify-surface`

In `justfile`:

```just
verify-surface:
    bash scripts/ai/ship-audit.sh
    bash scripts/ai/check-file-refs.sh
    bash scripts/ai/verify-doc-hygiene.sh
    bash scripts/ai/verify-provider-provenance.sh
```

## P8.2 Add doc hygiene script

Create:

```text
scripts/ai/verify-doc-hygiene.sh
```

Checks:

```text
- no root todo/plan markdown
- no docs/ai human docs under 20 lines unless allowed
- no docs/ai/package
- no huge generated advisor context tracked
- generated docs have headers
```

## P8.3 Add provider provenance script

Create:

```text
scripts/ai/verify-provider-provenance.sh
```

Checks:

```text
- `.github/agents/*.agent.md` has GENERATED FROM
- `.opencode/agents/*.md` has GENERATED FROM
- generated skills/prompts/commands have provenance if they are projections
```

## P8 Acceptance criteria

```text
AC-P8.1: `just verify-surface` exists.
AC-P8.2: It fails on forbidden shipped/generated files.
AC-P8.3: It fails on provider files without provenance.
AC-P8.4: It fails on root planning Markdown.
AC-P8.5: It fails on public placeholder docs.
AC-P8.6: It passes after cleanup.
```

## P8 Verification

```bash
just verify-surface
```

---

# P9 — Refactor largest shell files after file cleanup

> STALE / MOSTLY ALREADY-DONE: the in-flight `scripts/ai` reorg already split
> these into thin root wrappers + `scripts/ai/internal/<name>/` modules. The
> 967/733/662/660-line counts below are pre-reorg and no longer accurate
> (e.g. `repomix-scc-router.sh`, `repomix-context-tree.sh`, `ai-diff-context.sh`
> are now ~76-85 lines; `ai-edit.sh` ~117). This P9 is superseded by the
> committed `docs/tickets/arch-todo-restructure-scripts-ai-*` work — do not
> re-run it. The remaining shell candidates not yet split are below.

| Priority | File                                    | Status                                  |
| -------- | --------------------------------------- | --------------------------------------- |
| P9.1     | `scripts/ai/bin/read/preview-file.sh`   | STILL-VALID: 307 lines, not split       |
| P9.2     | `scripts/ai/bin/edit/ai-rollback.sh`    | STILL-VALID: 277 lines, not split       |
| P9.3     | `scripts/ai/bin/admin/install-mandatory-tools.sh` | STILL-VALID: 211 lines        |
| P9.4     | `scripts/ai/bin/read/ai-search-multi.sh` | STILL-VALID: 311 lines, not split      |

## P9 Acceptance criteria

```text
AC-P9.1: Each remaining large shell entrypoint becomes an orchestration wrapper.
AC-P9.2: Shared exclusion logic is imported, not duplicated.
AC-P9.3: Mutation scripts require dry-run by default.
AC-P9.4: Each script has `--help`, `--dry-run` where applicable.
AC-P9.5: Existing tests still pass.
```

## P9 Verification

```bash
bash scripts/ai/bin/read/preview-file.sh --help
bash scripts/ai/bin/edit/ai-rollback.sh --help
bash scripts/ai/bin/read/run-repo-tests.sh
```

---

# P10 — Refactor largest PHP files

Start with installer/core modules because they define what is shipped.

| Priority | File                                        | Reason                       |
| -------- | ------------------------------------------- | ---------------------------- |
| P10.1    | `tools/ai/install/core.php`                 | 2,012 lines / complexity 281 |
| P10.2    | `tools/ai/commands/install_workflow.php`    | 1,249 lines / complexity 176 |
| P10.3    | `tools/ai/validate-ai-config.php`           | 934 lines / complexity 135   |
| P10.5    | `tools/ai/validate-install-surface.php`     | 857 lines / complexity 91    |
| P10.6    | `tools/ai/install/backup.php`               | mutating/safety-critical     |
| P10.7    | `tools/ai/sh-introspect/75-render-help.php` | output layer too large       |

## P10 Acceptance criteria

```text
AC-P10.1: Installer has explicit include/allowlist for shipped files.
AC-P10.2: Installer does not copy generated docs by default.
AC-P10.3: Installer does not copy docs/tickets by default.
AC-P10.4: Installer does not copy repo-local `.github/**` or `.opencode/**` directly unless installing provider projections.
AC-P10.5: Validation fails when forbidden paths enter the install surface.
```

## P10 Verification

> Note: `tools/ai/run-full-install-validation.php` (former P10.4) does not exist;
> that row and its verification line were dropped.

```bash
php tools/ai/validate-install-surface.php
php tools/ai/verify-full-install.php
```

---

# P11 — Add export/package ignore rules

> CORRECTED: `.gitattributes` already exists (eol rules only, no `export-ignore`).
> **Append** these rules; do not recreate the file.

Append to the existing `.gitattributes` for archive/export hygiene:

```gitattributes
docs/tickets/** export-ignore
docs/ai/generated/** export-ignore
.ai-logs/** export-ignore
.opencode/node_modules/** export-ignore
node_modules/** export-ignore
vendor/** export-ignore
dist/** export-ignore

ai-search-todo-tests.md export-ignore
todo-agents-script-rework.md export-ignore
todo-introspect-improvement.md export-ignore
todo-agents-rework.md export-ignore
improvement-plan.md export-ignore
todo-scripts-refactor.md export-ignore
```

After moving root docs, the root file entries can be removed.

## P11 Acceptance criteria

```text
AC-P11.1: Git archive/export excludes generated, archive, logs, dependency folders.
AC-P11.2: Package/install shipping uses an allowlist, not only ignore rules.
AC-P11.3: Exported package does not contain `docs/tickets/**`.
AC-P11.4: Exported package does not contain `docs/ai/generated/**`.
```

## P11 Verification

```bash
git archive --format=tar HEAD | tar -tf - |
grep -E '^(docs/tickets/|docs/ai/generated/|\.ai-logs/|vendor/|dist/|node_modules/|\.opencode/node_modules/)' &&
exit 1 || echo "ok: archive excludes non-shipped surfaces"
```

---

# Recommended commit sequence

Use small commits. Do not mix refactor with cleanup.

```text
00-add-shipping-surface-policy
01-normalize-report-context-excludes
02-add-ship-audit-and-doc-hygiene-verifiers
03-untrack-giant-generated-advisor-context
04-archive-root-planning-docs
05-remove-duplicate-docs-ai-package
06-add-generated-provenance-headers
07-clean-placeholder-docs
08-reorganize-docs-ai-sections
09-tighten-package-export-ignore
10-refactor-shell-reporting-entrypoints
11-refactor-installer-shipping-surface
12-refactor-large-php-validation-modules
```

---

# Master verification command set

Run before cleanup:

```bash
git status --short

git ls-files | grep -E '(^|/)(node_modules|vendor|dist)(/|$)|^\.ai-logs/|^docs/ai/generated/logs/' || true

find . \
  -path './.git' -prune -o \
  -path './node_modules' -prune -o \
  -path './.opencode/node_modules' -prune -o \
  -path './vendor' -prune -o \
  -path './dist' -prune -o \
  -name '*.md' -type f -exec wc -l {} + |
sort -nr |
head -50
```

Run after cleanup:

```bash
bash scripts/ai/ship-audit.sh
bash scripts/ai/check-file-refs.sh
bash scripts/ai/verify-doc-hygiene.sh
bash scripts/ai/verify-provider-provenance.sh

scc . \
  --exclude-dir .git \
  --exclude-dir node_modules \
  --exclude-dir .opencode/node_modules \
  --exclude-dir vendor \
  --exclude-dir dist \
  --exclude-dir .ai-logs \
  --exclude-dir docs/ai/generated \
  --exclude-dir docs/tickets

git archive --format=tar HEAD | tar -tf - |
grep -E '^(docs/tickets/|docs/ai/generated/|\.ai-logs/|vendor/|dist/|node_modules/|\.opencode/node_modules/)' &&
exit 1 || echo "ok: archive clean"
```

---

# Final priority order

Do this first:

```text
P0: Define shipping policy + canonical excludes
P1: Stop tracking/shipping giant generated advisor context
P2: Archive root planning Markdown
P3: Remove docs/ai/package duplicate mirror
P4: Add provider GENERATED FROM provenance
P5: Remove/merge 3-line placeholder docs
P6: Reorganize docs/ai
P7: Reduce capability/skill projection duplication
P8: Add verify-surface gate
P9: Refactor large Shell entrypoints
P10: Refactor installer PHP shipping logic
P11: Add archive/export ignore rules
```

The biggest immediate win is **not code refactor**. It is:

```text
1. remove/generated-exclude docs/ai/generated/advisor-context.md
2. archive root todo docs
3. remove docs/ai/package mirror
4. make shipped surface allowlist-based
```

That will reduce shipped noise much faster than touching PHP or Shell first.
