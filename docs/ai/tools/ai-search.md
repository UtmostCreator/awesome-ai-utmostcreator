# Tool: ai-search

Use `scripts/ai/ai-search.sh` as the unified search entrypoint for repository discovery. Prefer it over raw `grep`, `find`, or broad file dumps.

> Note: the locally bundled `scripts/ai/ai-search.sh` implements a subset of the documented capabilities. The contract below describes the full stronger tool layer; missing flags fall back to the local script's `text`/`tracked`/`changed`/`staged`/`files`/`struct`/`docs` modes.

## Default Invocation

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh MODE QUERY . --fixed
```

## Modes

- `changed-files` - file list of the working diff (no query)
- `staged-files` - file list of the index (no query)
- `changed-text` - content search restricted to changed files (query required)
- `staged-text` - content search restricted to staged files (query required)
- `changed` / `staged` - deprecated aliases of `changed-files` / `staged-files`;
  emit a `warnings[]` deprecation note. With `AI_SEARCH_STRICT=1` they return
  `status=error` pointing at the new names.
- `tracked` - tracked-file content search (git grep)
- `text` - regex/text search across a root
- `docs` - text search (documentation surface)
- `files` - filename search (fd)
- `struct` - ast-grep structural pattern
- `doctor` - report available/missing tools and root state
- `unsafe-all` - approval-gated; returns `blocked`

Always probe `changed`, then `staged`, then `tracked` before broader modes.

## Scope And Match Control (content modes)

- `--fixed` literal match · `--regex` regex (default) · `--pcre2` PCRE2
- `--smart-case` (default) · `--ignore-case`/`-i` · `--case-sensitive`
- `--glob PATTERN` (repeatable) · `--type NAME` · `--exclude PATH` (repeatable)
- `--max-depth N` · `--context N` / `--before-context N` / `--after-context N`
- `--absolute` adds `absolute_path` · `--max-results N` · `--max-bytes N`

`vendor`, `node_modules`, `dist`, `build`, `coverage`, and `.git` are excluded by
default. `text`/`docs` searches run through `rg --json`, so `results[]` carry an
accurate 1-based `column` and colon-in-filename paths stay valid.

## Output Contract

JSON mode emits an envelope containing:

- `schema` - schema identifier
- `status` - one of `ok`, `no_matches`, `error`, `unavailable`, `dry_run`, `unsafe_blocked`
- `tool` - tool name (`ai-search`)
- `query` - the literal query string
- `mode` - the resolved mode
- `matches` - array of hits (string `path:line:text` entries)
- `warnings` - non-fatal advisories
- `errors` - failure reasons when `status` is not `ok`
- `limits` - applied bounds, e.g. `max_results`
- `meta` - `returned` count and `truncated` flag

`doctor` replaces `matches` with `diagnostics` (`available[]`, `missing[]`,
`warnings[]`, `root`, `git_available`).

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
