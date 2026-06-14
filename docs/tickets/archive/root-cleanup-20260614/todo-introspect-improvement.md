> STATUS (verified against live repo): most of this proposal is **ALREADY
> IMPLEMENTED** in `tools/ai/sh-introspect/`. Corrections:
>
> - ALREADY-DONE — P0 help renderer + `--format=json|help|summary|full`:
>   `75-render-help.php` (`shIntrospectRenderHelpSummary`) and `10-cli.php`
>   parse/render exactly the proposed order; tested by
>   `tests/php/ShIntrospect/{ShIntrospectHelpFormatTest,ShIntrospectCliFormatTest}.php`.
> - ALREADY-DONE — all 7 renderer rules (P1 mode/flag rendering, `display_group`
>   before `family`, merge `modes`+`mode_contracts`, dedupe params, hide
>   internals, risk summary, confidence thresholds).
> - ALREADY-DONE — the entire `# pager` section: `tools/ai/sh-introspect/12-pager.php`
>   (auto/always/never, TTY+CI+JSON gates, `less -R -F -X`, `$AI_PAGER`/`$PAGER`,
>   no-page-on-pipe) + CLI `--no-pager`/`--pager`.
> - STALE — the `## Best architecture` block (`tools/ai/introspect/` with
>   `Contract.php`/`Analyzer.php`/`Renderer/*`) does not exist; the real layout
>   is numbered modules under `tools/ai/sh-introspect/`. The `## Field priority
>   algorithm` PHP pseudocode names are illustrative only (behavior exists).
> - NOT ADOPTED — the recommendation to make `--format help` the DEFAULT was
>   intentionally not taken; default remains verbose text.
> - STILL-VALID (only genuinely open item) — P2 "Add quality warnings"
>   (`Help quality: incomplete` + reason/recommendation) is not implemented.

Yes. For `ai-search.sh`, the introspect JSON already contains enough high-quality metadata to generate a much better **human-readable introspect help output**.

Current problem is not lack of data. The problem is that the text renderer prints too many raw sections instead of using the best fields selectively.

## Best fields to use

| JSON field                      | Usefulness | Use in improved help? | Reason                                                            |
| ------------------------------- | ---------: | --------------------- | ----------------------------------------------------------------- |
| `help.summary`                  |        100 | Yes                   | Best one-line purpose.                                            |
| `help.usage`                    |        100 | Yes                   | Canonical invocation.                                             |
| `help.json_output_env`          |         95 | Yes                   | Important for AI usage.                                           |
| `status_values`                 |         90 | Yes                   | Useful for agents parsing output.                                 |
| `modes[]`                       |        100 | Yes                   | Already has descriptions, groups, query requirement, deprecation. |
| `mode_contracts[]`              |         95 | Yes                   | Best source for per-mode positionals, deps, examples.             |
| `params[]`                      |        100 | Yes                   | Already grouped, described, has aliases and value hints.          |
| `examples` / `examples_by_mode` |         95 | Yes                   | Very useful.                                                      |
| `output_schemas[]`              |         85 | Yes, compact          | Good for AI agents.                                               |
| `env_inputs[]`                  |         80 | Yes                   | Useful if concise.                                                |
| `dependencies[]`                |         80 | Yes                   | Group required/optional/base utilities.                           |
| `risk_summary`                  |         90 | Yes                   | Essential safety signal.                                          |
| `side_effects[]`                |         85 | Yes                   | Better than raw command list for normal help.                     |
| `sources[]`                     |         70 | Yes, compact          | Show resolved sourced files only if relevant.                     |
| `warnings[]`                    |         75 | Yes                   | Useful, but put at bottom.                                        |
| `functions[]`                   |         30 | Debug only            | Too noisy for help.                                               |
| `commands[]`                    |         50 | Debug/full only       | Useful for audit, too noisy for normal help.                      |
| `case_labels[]`                 |         20 | Debug only            | Internal implementation detail.                                   |
| `json_key_candidates[]`         |         25 | Debug only            | Low confidence; do not show in normal help.                       |
| `json_paths[]`                  |         60 | AI/full only          | Useful for schema docs, too verbose for quick help.               |

---

## Recommended output profiles

Do not have one giant introspect text output. Add profiles:

```text
bash sh-introspect.sh scripts/ai/ai-search.sh --format summary
bash sh-introspect.sh scripts/ai/ai-search.sh --format help
bash sh-introspect.sh scripts/ai/ai-search.sh --format full
bash sh-introspect.sh scripts/ai/ai-search.sh --format json
```

| Format    | Purpose                    | Content                                                   |
| --------- | -------------------------- | --------------------------------------------------------- |
| `summary` | quick terminal scan        | purpose, usage, modes, flags, risk                        |
| `help`    | human/AI readable contract | grouped modes, grouped flags, examples, output schema     |
| `full`    | debug/audit                | current verbose output: functions, commands, cases, risks |
| `json`    | machine contract           | raw JSON                                                  |

Best default for `sh-introspect.sh FILE` should be `--format help`, not full raw dump.

---

## Example improved introspect help for `ai-search.sh`

```text
ai-search.sh — unified repository search entrypoint

Usage:
  ai-search.sh MODE [QUERY] [root] [flags]

JSON output:
  AI_OUTPUT=json

Status values:
  ok, no_matches, error, unavailable, dry_run, blocked

Risk:
  max_risk: low
  mutation: no
  dynamic_execution: no
  side_effects: filesystem-read, git-read

Modes:
  File list:
    changed-files     list unstaged-changed files
    staged-files      list staged files

  Content search:
    text              search a root (rg)
    tracked           git-grep over tracked files
    files             filename search
    changed-text      search only unstaged-changed files
    staged-text       search only staged files

  Git-aware:
    diff              search diff hunks; supports --staged, --base REF
    history           search git history; supports --messages, --patch

  Surface search:
    docs              README, CHANGELOG, docs markdown/rst/adoc files
    tests             test files
    config            env/config/docker/nix files
    deps              dependency manifests and lock files

  Structural:
    struct            ast-grep pattern; supports --lang LANG
    symbols           resolve symbol; emits symbols[]
    class             class definitions only

  Curated:
    todo              TODO/FIXME/HACK/XXX/deprecated/workaround/legacy
    unsafe-patterns   curated risky patterns with rule + severity

  Diagnostics:
    doctor            tool/root/git diagnostics
    unsafe-all        approval-gated; always returns blocked

  Deprecated:
    changed           use changed-files or changed-text
    staged            use staged-files or staged-text

Flags:
  Pattern:
    --fixed                 literal fixed-string match
    --regex                 regex match, default
    --pcre2                 PCRE2 regex
    --ignore-case, -i       case-insensitive
    --case-sensitive        force case-sensitive
    --smart-case            case-insensitive unless query has uppercase

  Scope:
    --absolute              include absolute_path in structured results
    --glob PATTERN          include glob; repeatable
    --type NAME             rg type filter; repeatable
    --exclude PATH          exclude path; repeatable
    --max-depth N           bound traversal depth

  Ignore:
    --no-ignore             disable all ignore sources
    --no-ignore-vcs         disable .gitignore sources
    --no-ignore-global      disable global gitignore
    --no-ignore-parent      disable parent-directory ignore files
    --no-ignore-dot         disable .ignore/.rgignore files

  Context:
    --context N, -C         N lines before and after
    --before-context N, -B  N lines before match
    --after-context N, -A   N lines after match

  Output:
    --files-with-matches, -l  return paths only
    --count                  return path/count pairs
    --count-matches          return summary totals only

  Bounds:
    --max-results N          cap matches; default 100
    --max-bytes N            truncate context payload after N bytes

  Mode-specific:
    --staged                 diff mode: staged hunks
    --base REF               diff mode: compare against REF
    --messages               history mode: search commit messages
    --patch                  history mode: include patch text
    --lang LANG              struct/symbols/class language

  Misc:
    --dry-run                report dry_run without searching
    --help, -h               show help
    --introspect             print full machine-readable JSON contract

Examples:
  AI_OUTPUT=json bash scripts/ai/ai-search.sh text TenantResolver . --fixed
  AI_OUTPUT=json bash scripts/ai/ai-search.sh changed-text Tenant . --fixed
  AI_OUTPUT=json bash scripts/ai/ai-search.sh diff Needle . --fixed --staged
  AI_OUTPUT=json bash scripts/ai/ai-search.sh class UserService . --lang php

Output schemas:
  file-list-result: changed-files, staged-files
    keys: schema, tool, status, results, warnings, errors

  content-search-result:
    modes: text, tracked, files, changed-text, staged-text, docs, tests, config, deps, diff, history, struct, symbols, class
    keys: schema, tool, status, results, matches, summary, limits, meta, warnings, errors

  symbols-result:
    symbols[] fields: kind, name, path, start, end, language

Dependencies:
  Required: ast-grep, find, git, rg
  Optional: jq
  Base utilities: awk, cat, grep, sed, tail

Env:
  AI_OUTPUT             default: empty; set to json for JSON output
  AI_SEARCH_STRICT     default: 0
  AI_LANG              default: php
  XDG_CONFIG_HOME      default: $HOME/.config
  PHP_BIN              default: php

Source:
  scripts/ai/common.sh resolved; exists; inside repo

Warnings:
  contract aggregated from sourced modules; target not executed
```

This is much better than the current raw output because it uses the same JSON, but renders it through a help-first lens.

---

## Renderer rules to implement

### 1. Use confidence thresholds

```text
>= 90  show normally
75–89  show if useful
55–74  show only in full/debug
< 55   hide from human help
```

For `ai-search.sh`, this means:

- show `modes`, `params`, `status_values`, `risk_summary`
- hide most `case_labels`, `functions`, `json_key_candidates`

---

### 2. Use `display_group` before `family`

Mode grouping priority:

```text
mode.display_group
mode.family
"other"
```

This gives good groups:

```text
file-list
content
surface
git-aware
structural
curated
other
deprecated
```

Without this, many modes collapse into generic `content`.

---

### 3. Merge `modes[]` with `mode_contracts[]`

Use `modes[]` for display:

```json
name
description
display_group
query_required
deprecated
```

Use `mode_contracts[]` for details:

```json
positionals
dependencies
examples
output_notes
replacements
```

Merge by `name`.

---

### 4. Group flags by `params[].group`

Already available:

```text
pattern
scope
ignore
context
output
bounds
git
structural
misc
```

Render each flag as:

```text
--flag VALUE, -x    description
```

Use:

```json
name
aliases
takes_value
value_hint
repeatable
description
applies_to_modes
```

---

### 5. Deduplicate params

Your JSON has duplicate logical flags:

```json
--no-ignore
--no-ignore-global
```

because they appear in both parser and fallback handling.

Deduplication key:

```text
canonical name + sorted aliases + group
```

Preferred item:

```text
higher confidence
parser-case over usage-doc
has description
has value_hint
```

---

### 6. Hide implementation internals from normal help

These should move to `--format full` only:

```text
Functions
Case labels
Raw commands
JSON key candidates
Raw JSON paths
Unknown option handlers
Line numbers
```

Exception: show `unknown_option_handlers` only as a compact line:

```text
Unknown --options fail; non-flag positionals pass through.
```

---

### 7. Render risk summary, not raw command list

For normal help:

```text
Risk:
  max_risk: low
  mutation: no
  side_effects: filesystem-read, git-read
```

For `--format full`, show raw commands and findings.

---

## Suggested implementation plan

### P0 — Add `help` renderer

Add a renderer that accepts the JSON contract and prints sections in this order:

```text
title
usage
json output
status values
risk
modes
flags
examples
output schemas
dependencies
env
sources
warnings
```

Score impact: **+25/100** for current introspect text output.

---

### P0 — Add `--format` to `sh-introspect.sh`

Support:

```text
--format help
--format summary
--format full
--format json
```

Default should probably become:

```text
--format help
```

Keep raw current output as:

```text
--format full
```

---

### P1 — Improve mode rendering

Use:

```text
display_group > family
description
query_required
deprecated replacements
```

This would fix poor output like:

```text
Modes:
  unknown: changed staged ...
```

and replace it with useful grouped mode docs.

---

### P1 — Improve flag rendering

Use `params[].description`, `value_hint`, `aliases`, and `group`.

Current output:

```text
--max-results N value
```

Improved:

```text
--max-results N   cap returned matches; default 100; sets meta.truncated
```

---

### P1 — Compact output schema

Use `output_schemas[]`, but do not print all JSON paths by default.

Good:

```text
symbols-result:
  symbols[] fields: kind, name, path, start, end, language
```

Bad for normal help:

```text
$.symbols[].language
$.symbols[].path
...
```

---

### P2 — Add quality warnings

For scripts with weak introspection confidence, print:

```text
Help quality: incomplete
Reason: no usage function, no params, low confidence
Recommendation: add manual usage() or @help metadata
```

This is important for scripts like:

```text
ai-file-freshness.sh
repo-stats.sh
repo-tool-inventory.sh
ai-install-coverage.sh
```

---

## Best architecture

```text
tools/ai/
  sh-introspect.php
  introspect/
    Contract.php
    Analyzer.php
    Renderer/
      JsonRenderer.php
      HelpRenderer.php
      SummaryRenderer.php
      FullTextRenderer.php
```

Or simpler first step:

```text
tools/ai/sh-introspect.php
  render_help(array $c): string
  render_summary(array $c): string
  render_full(array $c): string
```

Do not over-architect until the output is stable.

---

## Field priority algorithm

```php
$title = basename($contract['file']);
$summary = $contract['help']['summary'] ?? null;
$usage = $contract['help']['usage'] ?? inferred_usage($contract);

$modes = merge_modes_and_contracts(
    $contract['modes'] ?? [],
    $contract['mode_contracts'] ?? []
);

$params = dedupe_params($contract['params'] ?? []);
$paramsByGroup = group_by($params, 'group');

$examples = $contract['examples'] ?? [];
$schemas = filter_confidence($contract['output_schemas'] ?? [], 70);

$risk = $contract['risk_summary'] ?? null;
$deps = group_dependencies($contract['dependencies'] ?? []);
$env = filter_confidence($contract['env_inputs'] ?? [], 80);
```

---

## Final answer

Yes, you can use the introspect JSON to generate much better help.

For `ai-search.sh`, the JSON quality is already **90/100**. The right move is:

```text
raw JSON contract -> curated help renderer -> concise grouped text
```

Do **not** add more raw introspection data to the default output. Instead, use the metadata you already have to hide noise and promote the useful fields:

```text
help, modes, mode_contracts, params, examples, output_schemas, env_inputs, dependencies, risk_summary
```

Expected result:

| Output type                        | Current quality | After renderer |
| ---------------------------------- | --------------: | -------------: |
| Raw JSON                           |          90/100 |         92/100 |
| Current text introspect            |          60/100 |         88/100 |
| Human `--help` replacement quality |          55/100 |         80/100 |
| AI contract readability            |          70/100 |         92/100 |

Best target: **manual script `--help` remains canonical**, while `sh-introspect --format help` becomes an excellent generated audit/help view.

# pager

Yes, it makes sense, but only with a **smart pager**, not always.

Best design:

```text
--introspect              human-readable, paged only in interactive terminal
--introspect --json       raw JSON, never paged
--introspect --no-pager   human-readable, never paged
--introspect --pager      force pager
```

## Recommended rule

```text
Use less only when:
  stdout is a terminal
  output is human-readable
  output is long enough
  CI is not set
  --no-pager was not passed
```

Do **not** page when output is piped:

```bash
bash script.sh --introspect | wl-copy
bash script.sh --introspect --json | jq .
bash script.sh --introspect > contract.txt
```

Otherwise `less` will break automation and clipboard workflows.

## Best `less` flags

Use:

```bash
less -R -F -X
```

Meaning:

| Flag | Why                                             |
| ---- | ----------------------------------------------- |
| `-R` | preserve colours safely                         |
| `-F` | quit automatically if output fits on one screen |
| `-X` | do not clear the screen on exit                 |

Optional:

```bash
less -R -F -X -S
```

`-S` chops long lines instead of wrapping. Good for tables, worse for prose.

## Simple Bash implementation

```bash
page_if_interactive() {
  local file="$1"

  if [[ "${NO_PAGER:-0}" == "1" || "${CI:-}" != "" ]]; then
    cat "$file"
    return
  fi

  if [[ -t 1 ]]; then
    "${PAGER:-less}" -R -F -X "$file"
  else
    cat "$file"
  fi
}
```

Usage:

```bash
tmp="$(mktemp)"
render_introspect_help > "$tmp"
page_if_interactive "$tmp"
rm -f "$tmp"
```

## Better implementation with explicit flags

```bash
PAGER_MODE=auto
OUTPUT_FORMAT=help

while [[ $# -gt 0 ]]; do
  case "$1" in
    --json)
      OUTPUT_FORMAT=json
      PAGER_MODE=never
      ;;
    --no-pager)
      PAGER_MODE=never
      ;;
    --pager)
      PAGER_MODE=always
      ;;
  esac
  shift
done
```

Then:

```bash
emit_with_optional_pager() {
  local file="$1"

  case "$PAGER_MODE" in
    never)
      cat "$file"
      ;;
    always)
      "${PAGER:-less}" -R -F -X "$file"
      ;;
    auto)
      if [[ -t 1 && -z "${CI:-}" && "$OUTPUT_FORMAT" != "json" ]]; then
        "${PAGER:-less}" -R -F -X "$file"
      else
        cat "$file"
      fi
      ;;
  esac
}
```

## Recommended final behaviour

```bash
bash ai-search.sh --introspect
# human help, opens less only if terminal + long output

bash ai-search.sh --introspect --json
# raw JSON, no pager

bash ai-search.sh --introspect --no-pager
# human help, plain stdout

bash ai-search.sh --introspect --pager
# force less

bash ai-search.sh --introspect | wl-copy
# no pager, copies full output
```

## Best design score

| Design                                        |      Score |
| --------------------------------------------- | ---------: |
| Always print full output                      |     65/100 |
| Always pipe to `less`                         |     55/100 |
| Smart pager for human format only             | **94/100** |
| Smart pager + `--json`/`--no-pager`/`--pager` | **98/100** |

Use smart paging. It improves terminal usability without damaging AI, clipboard, JSON, or CI workflows.
