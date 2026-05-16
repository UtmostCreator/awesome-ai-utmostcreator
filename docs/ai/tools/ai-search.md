# Tool: ai-search

Use `scripts/ai/ai-search.sh` as the unified search entrypoint for repository discovery. Prefer it over raw `grep`, `find`, or broad file dumps.

> Note: the locally bundled `scripts/ai/ai-search.sh` implements a subset of the documented capabilities. The contract below describes the full stronger tool layer; missing flags fall back to the local script's text/files/struct/tracked/all modes.

## Default Invocation

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh MODE QUERY . --fixed
```

## Modes

- `changed` - files in the current working diff
- `staged` - files in the index
- `tracked` - all tracked files
- `text` - regex/text search across the repo
- `files` - filename search
- `struct` - ast-grep structural pattern
- `all` - broadest mode, last resort

Always probe `changed`, then `staged`, then `tracked` before broader modes.

## Output Contract

JSON mode emits an envelope containing:

- `schema` - schema identifier and version
- `status` - `ok`, `unsafe_blocked`, or `error`
- `tool` - tool name and version
- `query` - the literal query string
- `mode` - the resolved mode
- `results` - array of hit objects with `path`, `line`, `context`
- `warnings` - non-fatal advisories
- `errors` - failure reasons when `status` is not `ok`

## Approval And Safety

Approval is required for:

- `unsafe-all` mode
- runs that touch `secrets` paths or sensitive globs
- `AI_ALLOW_UNLIMITED=1` overrides
- any `dry_run` toggle that would otherwise mutate state

When a query targets a denied surface, the script returns `status: unsafe_blocked` and the caller must escalate to the human.

## Companion Commands

After `ai-search` finds a relevant file or line, preview it with the approved reader:

```bash
AI_OUTPUT=json bash scripts/ai/preview-file.sh PATH --around LINE --context 30
```

Estimate context usage before broad packs:

```bash
bash scripts/ai/query-usage.sh PATH
```

Verify behavior changes after edits:

```bash
bash scripts/ai/ai-verify.sh .
```

## Examples

Find usages of a symbol restricted to the current diff:

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh changed "MySymbol" . --fixed
```

Search tracked files for a config key:

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh tracked "feature_flag.enabled" . --fixed
```
