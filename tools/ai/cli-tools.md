# AI CLI Tools

Thin mandatory entrypoint for AI agents.

Goal: use deterministic, fast, structured CLI tools instead of slow, noisy, manual, or high-risk commands.

This file defines policy and routing only.
Use `tools/ai/tools/tool-map.md` for tool replacement lookup.

---

## Load Order

1. Read this file first.
2. Read `tools/ai/tools/tool-map.md` when choosing tools.
3. Read the relevant `tools/ai/tools/actions/*.md` before executing that action.
4. Read `tools/ai/tools/examples/good-bad-*.md` only when behaviour is unclear.
5. Read `tools/ai/tools/approval-required.md` before risky mutation.

---

## Mandatory Rules

1. Read-only discovery before edits.
2. Structured tools before text scraping.
3. AST/semantic tools before regex for code structure.
4. Project-defined commands before guessed commands.
5. Bounded output before full-file or full-log dumps.
6. Check/dry-run before write mode.
7. Review diffs before finishing.
8. Run focused verification after changes.
9. Never delete, install, publish, migrate, mutate services, mutate Git history, or execute remote scripts without explicit approval.

---

## Default Research Start

```bash
git status --short
git diff --stat
git log --oneline --decorate -10
git ls-files | head -200
rg -n "KEYWORD|ClassName|functionName"
```

Full guide: [`tools/ai/tools/research-sequence.md`](tools/ai/tools/research-sequence.md)

---

## Default Edit Start

```bash
git status --short
rg -n "target"
bat -n --paging=never path/to/file

# apply minimal change

git diff --check
git diff --stat
git diff
```

Full guide: [`tools/ai/tools/edit-sequence.md`](tools/ai/tools/edit-sequence.md)

---

## Context Rule

Do not make AI read more than necessary.

Prefer generated or targeted context:

```bash
scc .
git diff --name-only
rg -n "target|keyword|entrypoint"
```

When a pre-compiled task context exists, read it instead of scanning broadly:

```bash
bash scripts/ai/preview-file.sh docs/ai/generated/task-context/latest.md
```

Context guide: [`tools/ai/tools/actions/ai-context-packing.md`](tools/ai/tools/actions/ai-context-packing.md)

---

## Tool Routing

Use [`tools/ai/tools/tool-map.md`](tools/ai/tools/tool-map.md) for replacements such as:

```text
find -> fd / rg --files
grep -> rg / git grep
cat -> bat
JSON -> jq
YAML -> yq
code structure -> ast-grep / semgrep
diff review -> git diff --check / delta / difftastic
tasks -> just / package scripts / composer scripts
AI context -> repomix / files-to-prompt / code2prompt / scc
```

---

## Action Guides

Use [`tools/ai/tools/actions/`](tools/ai/tools/actions/) only for the active task.
Use [`tools/ai/tools/examples/`](tools/ai/tools/examples/) only when the action guide is not enough.
Use [`tools/ai/tools/approval-required.md`](tools/ai/tools/approval-required.md) before destructive, install, publish, service, infrastructure, network-exec, Git-history, environment-hook, or database mutation commands.

---

## Final Rule

```text
Read broadly.
Edit narrowly.
Verify locally.
Show the diff.
Do not delete, install, publish, migrate, or mutate services without approval.
```
