# AI Context Packing

Use to generate small, relevant, deterministic context bundles.

---

## Preferred Commands

Instead of copy/paste:

```bash
repomix --include "docs/ai/**/*.md,tools/**/*.php"
```

Instead of manual file concatenation:

```bash
files-to-prompt docs/ai/project-context.md docs/ai/workflow.md
```

Instead of ad-hoc code prompts:

```bash
code2prompt . --include "app/Services/**/*.php"
```

Before packing:

```bash
scc .
fd "CAPABILITY.md|checklist.md|examples.md|gotchas.md" docs/ai
rg -n "target|keyword|entrypoint"
```

---

## Use When

- preparing task context for AI
- handing off to another agent
- compressing repo reality
- avoiding excessive reading

---

## Avoid

```bash
cat $(find . -type f)
repomix .
```

unless explicitly needed.

Example: [`../examples/good-bad-ai-context-packing.md`](../examples/good-bad-ai-context-packing.md)
