# Source Of Truth

Canonical behavior lives in repository code and `docs/ai/`. Generated or runtime adapter files must not contradict canonical docs.

## Evidence Ordering

When sources disagree, resolve in this order and state which level a claim rests on:

1. user request
2. current git diff and working tree
3. source code
4. tests
5. schemas, contracts, migrations, and public interfaces
6. runtime and configuration files
7. canonical docs under `docs/ai/`
8. runtime adapter files under `.github/` and `.opencode/`
9. generated artifacts under `docs/ai/generated/`

Trust active repository evidence over recall. Report conflicts instead of silently picking one side.

## Correctness Rules

- Label any claim not grounded in levels 1-6 above with `[unverified]`. Never present an unverified claim as a fact.
- Prefer retrieval over recall: read the actual file, lockfile, or symbol rather than recalling a value. For versions, read `composer.json`, `composer.lock`, or the relevant manifest; do not state a version from memory.
- Apply a confidence gate before editing. Below high confidence in scope and target, do bounded read-only discovery first; when scope or ownership is unclear, ask one essential question rather than guess.
- Separate verified facts from assumptions and from recommendations in any report. Do not report recommendations or unrun checks as completed work.

## Context Authority

- Generated context, compacted summaries, and adapter files are lower authority than live repository files. When a summary and the current code disagree, the code wins.
- Preserve exact paths, version numbers, error messages, and unresolved decisions across any compaction or handoff; do not let summarization generalize them away.
- See `docs/ai/context-economy.md` for the bounded-context and verification-cost rules that keep this evidence cheap to gather.

## Editable vs Generated Files

Kit-managed files are re-rendered on every upgrade; user-owned files are installed once and preserved. When unsure, check the `GENERATED — DO NOT EDIT` or `Managed by ai-kit` header at the top of the file (see the headers added across rendered files).

| Safe to edit | Do not edit directly |
| --- | --- |
| `.ai/project.yml` | generated `AGENTS.md` body (edit the template or `.ai/project.yml`) |
| `docs/ai/project/**` | kit-managed `.opencode/agents/*`, `.github/agents/*` |
| `docs/ai/project-stack.md`, `docs/ai/project/conventions.md` | `.github/hooks/scripts/command-policy.compiled.sh` |
| pre-existing non-kit files | `docs/ai/catalog.md`, `llms.txt`, `BROWSE.md` (generated) |
| marked user sections in rendered files | `.ai/*.lock`, install manifest |

| Classification (`merge_strategy`) | User can edit? | Upgrade behaviour |
| --- | --- | --- |
| kit-managed (`replace`) | No, except marked user sections | Re-rendered/replaced from templates + `.ai/project.yml` |
| user-owned (`skip-if-exists`) | Yes | Installed once; preserved (never overwritten) |
| adoptable (`opencode.jsonc`, `never_auto_merge`) | Via ai-kit plan/adopt | Not auto-merged |
| foreign (pre-existing at kit path) | Yes | Untouched unless `--adopt` |

Files that say `GENERATED — DO NOT EDIT` or `Managed by ai-kit` are kit-managed.
