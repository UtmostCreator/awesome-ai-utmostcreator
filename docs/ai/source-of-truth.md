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
