# CLI Tool Guides

This folder contains action-specific CLI guidance for AI agents.

Load order:

1. `../cli-tools.md`
2. `tool-map.md`
3. relevant file from `actions/`
4. matching example from `examples/`
5. `approval-required.md` when a command mutates files, dependencies, services, Git history, or infrastructure

---

## Folders

| Path | Purpose |
|---|---|
| `actions/` | Short guides by action type |
| `examples/` | Good/bad examples for agent behaviour |
| `tool-map.md` | Standard tool → preferred tool replacement map |
| `research-sequence.md` | Default investigation workflow |
| `edit-sequence.md` | Default safe edit workflow |
| `approval-required.md` | Commands that require explicit approval |

all file:
- TREE.md