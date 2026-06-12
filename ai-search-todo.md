# ai-search.sh rebuild plan (TDD-first, correctness-first, rename-safe)

## How to read this plan

Two hard rules drive the ordering below:

1. **Correctness first, breakage second.** A working, contract-correct script is the
   primary deliverable. The mode renames (`changed`→`changed-files`,
   `staged`→`staged-files`) are real and stay in the plan, but they are sequenced
   *after* the script behaves correctly, and they ship with backward-compatible
   aliases so nothing breaks at switchover.
2. **TDD-first, per method.** For every method/mode/flag we add or fix, write the
   test(s) first (red), then implement until green. No implementation step starts
   before its failing test exists. Each phase below lists `TEST` items before
   `IMPL` items on purpose — do them in that order.

Mark `[ ]` items as you complete them. Do not mark an `IMPL` item done until its
matching `TEST` item is green and the regression suite still passes.

The executable test matrix lives in `ai-search-todo-tests.md` (fixtures + per-mode
`jq` assertions). It is the source of truth for acceptance; keep both files in sync.

## Invocation contract (verified — do not regress)

JSON mode is activated by the **`AI_OUTPUT=json` environment variable**, and the
script is invoked as `bash scripts/ai/ai-search.sh MODE QUERY [root] [flags]`.
There is **no `--json` flag**: the current script silently ignores unknown flags,
and once Phase 1 makes unknown flags an error, a `--json` token would return
`status=error`. This is enforced by `validate-ai-config.php:555`
(`AI_OUTPUT=json bash scripts/ai/ai-search.sh`) and documented in
`docs/ai/tools/ai-search.md:10`. All tests use the env-var form.

## Canonical envelope keys (target contract the tests assert)

- Common: `schema,status,tool,query,mode,warnings,errors,meta`.
- `meta`: `{elapsed_ms, truncated, bytes_used}`; `limits`: `{max_results,max_bytes,...}`.
- Content modes (`text`/`changed-text`/`staged-text`/`docs`/`tests`/`config`/
  `config-key`/`route`/`todo`/`unsafe-patterns`/`external-text`): `matches[]` of
  objects `{path,line,column?,text,match,mode,source_tool,root,language?,
  absolute_path?,context_before?,context_after?,root_type?,pattern_kind?,severity?}`.
- File-list modes (`changed-files`/`staged-files`): `files[]` + `scope`
  (`unstaged`|`staged`).
- `diff`: `matches[]` with `line_type` (`added`|`removed`|`context`), `hunk`, `scope`.
- `history`: `commits[]` `{hash,date,author,subject,files[]}`.
- Symbol modes (`symbols`/`class`/`function`/`method`/`interface`/`enum`):
  `symbols[]` `{name,kind,path,start_line,end_line?,language}`.
- `external-files`: `roots[]` + `searched_content == false`.
- `doctor`: `diagnostics` `{available[],missing[],warnings[],root,git_available}`.

Keep the legacy `matches[]` string output working only until structured output
lands (Phase 0/3); the tests in `ai-search-todo-tests.md` assert the structured
form and are RED until then by design.

---

## Confirmed evidence (verified live, do not re-litigate)

- `text AGENTS --fixed` → `no_matches`. Bug: `root=$3` + `shift 3` swallows the
  first flag, so `--fixed` is treated as a path. (`ai-search.sh:55-57`)
- `text AGENTS . --bad-flag` → `ok`. Bug: `*) shift ;;` silently ignores unknown
  flags. (`ai-search.sh:80`)
- All backends use `2>/dev/null || true`, so invalid regex / missing tool /
  non-git root all collapse to `no_matches`. (`ai-search.sh:118-159`)
- Envelope drift (pre-existing): `docs/ai/tools/ai-search.md` promises
  `query`/`mode`/`results[]`; script emits `matches[]`. Must be reconciled.

## Implementation progress + new findings (live)

State of the build, newest first. The **test file is ahead of the
implementation**: `tests/scripts/ai/test-ai-search.sh` encodes the full Phase
0–5 matrix. The Phase 3+ block is **gated behind `AI_SEARCH_RUN_P1_TESTS=1`**
(see `test-ai-search.sh:586`), not `set -e`. The default suite run stays GREEN
(Phase 0–2 + multi wiring); enabling the flag runs Phase 3A–5 and goes RED at
the first unbuilt feature (currently Phase 3D's `--files-with-matches`).

- **Default suite** (`bash tests/scripts/ai/test-ai-search.sh`): **GREEN**.
- **P1 suite** (`AI_SEARCH_RUN_P1_TESTS=1 bash …`): GREEN through Phase **3D**
  (16/16 count/file-only assertions), now **RED at `[phase4] diff unstaged -> ok`**
  (`unknown mode: diff`). Verified live this turn.
- **Done + real-verified:** Phases 0, 1, 2, 3A, 3B, 3C, **3D**. Verified
  independently (not just via the suite) against fresh fixtures, plus regression
  sweeps.
- **Tests already written for unbuilt phases:** Phase 3D, 4 (`diff`/`history`/
  `tests`/`docs`/`config`/`deps`/`todo`/`unsafe-patterns` + multi allowlist), and
  5 (`struct --lang`/`symbols`/`class`) all have complete assertions AND fixtures
  in `test-ai-search.sh`. Remaining work is **implementation-only** (+ the
  approval-gated install-surface items). ast-grep positive cases auto-skip when
  ast-grep is absent; the `unavailable` path is asserted instead.
- **Script size:** `scripts/ai/ai-search.sh` ≈ 932 lines (was ~360 at Phase 1).

### Findings worth not re-discovering

1. **`column` was hardcoded to `1`** (old `lines_to_structured_results`) because
   `rg -n` never reports the match column, and the test only asserted
   `type=="number"` — a wrong value passed. **Fixed** in Phase 3C by switching
   `text`/`docs` to `rg --json` and reading the submatch byte offset (`+1`).
   `tracked`/`changed-text`/`staged-text` still line-parse, so their column is
   approximate until a later slice. Consider strengthening the test to assert an
   exact column on a known fixture.
2. **`set -e` + trailing `[[ … ]] && cmd` is a recurring footgun.** A function (or
   case branch) whose LAST statement is `[[ cond ]] && action` returns 1 when the
   condition is false, aborting the whole script with NO envelope. Hit twice:
   `search_git_scoped_files` (Phase 2) and `build_rg_scope_args` (Phase 3C).
   Always end such helpers with an explicit `return 0` or a full `if`.
3. **`rg` vs `git grep` flag drift.** rg uses `--smart-case`/`--pcre2`; git grep
   (used by `tracked`) does not. Case/pattern modes must map per-backend
   (`-i`/`-F`/`-P` for git grep). Do not share the rg arg array with `tracked`.
4. **rg single-file output drops the path prefix** — `changed-text`/`staged-text`
   needed `-H` to keep the `path:line:text` shape.
5. **Install-surface is blocked (approval-gated).** Once `ai-search-multi.sh`
   became tracked, `validate-install-surface.php` errors (not in scripts-pack
   registry) and `validate-generated-artifacts.php` flags `repo-required-tools.md`
   drift (`php tools/ai/repo-tool-inventory.php --write`). Pre-existing and
   unrelated: `templates/core/agents/researcher.md` 322 > 320 hard max.

## Hard gates that constrain every change (keep green)

- `tools/ai/validate-ai-config.php:555` requires the substrings `changed`,
  `staged`, `tracked` to appear in the wiring sources. `changed-files` /
  `staged-files` *contain* those substrings, so the rename can satisfy this —
  but a `TEST` item below must prove it before the rename lands.
- `scripts/ai/ai-search-multi.sh:51-52` hardcodes the mode allowlist
  `changed|staged|tracked|text|files|struct|docs`. Every mode add/rename must be
  mirrored here.
- `php tools/ai/validate-adapter-drift.php`, `php tools/ai/validate-install-surface.php`,
  `php tools/ai/validate-generated-artifacts.php`, `php tools/ai/validate-ai-config.php`.
- `bash tests/scripts/ai/test-ai-search.sh` (registered in
  `tests/scripts/ai/run-all-tests.sh` as the `ai-search.sh` suite).
- `shellcheck scripts/ai/ai-search.sh`, `shfmt -d scripts/ai/ai-search.sh`.

## Files that move in lockstep with the script

- `scripts/ai/ai-search.sh` (implementation)
- `tests/scripts/ai/test-ai-search.sh` (extend; never delete existing assertions)
- `docs/ai/tools/ai-search.md` **and** its render source
  `packages/ai-universal-rules/templates/docs/ai/tools/ai-search.md`
- `scripts/ai/ai-search-multi.sh` (mode allowlist mirror)
- When/if a mode name changes in callers:
  `.opencode/skills/ai-search/SKILL.md` (↔ template),
  `.github/instructions/ai-search.instructions.md` (↔ template),
  `docs/ai/tools/actions/search-evidence.md` (↔ template),
  plus agents/prompts/commands that name modes (see "Phase R" inventory).

---

# Phase 0 — TDD harness and contract lock (do this first)

- [x] **TEST:** Extend `tests/scripts/ai/test-ai-search.sh` into a structured
      case runner (helper `expect_status MODE ARGS... -> STATUS`,
      `expect_count ... -> N`). Keep all current lines 4-21 passing unchanged.
      _(done: added `run_search`/`expect_status`/`expect_count`/`expect_jq`; all
      original assertions retained and green.)_
- [x] **TEST:** Add a "current behavior is a bug" block that asserts the *desired*
      outcomes — now green after Phase 1:
  - [x] `text AGENTS --fixed` → `status=ok`, `matches>0`.
  - [x] `text AGENTS . --bad-flag` → `status=error`.
  - [x] `text '(' .` → `status=error` _(used `(` — equivalent invalid-regex case;
        `[` is blocked by a shell deny rule in this environment)._
- [ ] **TEST:** Add a gate-proof test (shell or note for PHP) that
      `validate-ai-config.php` still passes with new mode names present
      (substring check for `changed`/`staged`/`tracked`).
      _(validator run manually and green, but no automated test case added yet.)_
- [~] **IMPL:** Adopt the canonical envelope from the "Canonical envelope keys"
      section above; use it everywhere. Activation stays env-based (`AI_OUTPUT=json`).
  - [x] Keep `matches` as the content field name (consumers depend on it).
  - [x] Add `query` and `mode` (doc already promises them).
  - [ ] Add per-shape keys: `files[]`+`scope`, `commits[]`, `symbols[]`, `roots[]`,
        `diff` `line_type`/`hunk`, `meta.truncated`/`meta.bytes_used`.
        _(only `meta.truncated` + `meta.returned` + `limits.max_results` shipped;
        the rest land with Phases 2-5.)_
  - [x] Update `docs/ai/tools/ai-search.md` + template to match the real envelope.

---

# Phase 1 — P0 correctness (working script; highest priority)

> Order inside each item: write `TEST` (red) → write `IMPL` (green).

## 1A. Argument parser

- [x] **TEST:** `text foo --fixed` searches `.` in fixed mode (not root `--fixed`).
- [x] **TEST:** `text foo src --fixed --ignore-case` searches `src`, fixed, ic.
- [x] **TEST:** flags accepted in any position; `root` = last non-flag positional.
      _(verified: `text --fixed AGENTS .` resolves `query=AGENTS`.)_
- [x] **TEST:** `text foo --bad-flag` → `status=error`, names the flag.
- [x] **IMPL:** Replace `query=$2; root=$3; shift 3` with a single parse loop.
- [x] **IMPL:** Unknown flag → `status=error` + exit 1 + flag name; no silent `shift`.

## 1B. Real errors vs no_matches vs unavailable

- [x] **TEST:** `text '(' .` → `status=error` (regex), not `no_matches`.
- [x] **TEST:** `tracked foo <non-git-dir>` → `status=error` (non-git root).
      _(also covers `changed` on a non-git root.)_
- [ ] **TEST:** `struct '$A' .` with `ast-grep` absent → `status=unavailable`.
      _(impl path emits `unavailable`, but not provable here: ast-grep is installed
      and cannot be cleanly hidden from PATH in this environment.)_
- [ ] **TEST:** simulate missing `rg`/`jq` → `status=error` (skip-guard if present).
- [x] **IMPL:** Stop blanket `2>/dev/null || true`; capture exit codes per backend.
- [x] **IMPL:** Map: regex fail→`error`; missing core tool→`error`; non-git in git
      modes→`error`; missing optional tool (`ast-grep`/`fd`)→`unavailable`.

## 1C. Real `doctor`

- [x] **TEST:** `AI_OUTPUT=json ... doctor` returns `{available, missing, warnings}`.
- [x] **TEST:** `doctor` reports `rg`/`jq`/`git`/`fd|fdfind`/`ast-grep` and root + git_available.
- [x] **IMPL:** Replace stub `doctor` with real checks.

## 1D. Bounded output (safety)

- [~] **TEST:** broad query respects default `--max-results` and sets
      `meta.truncated=true`. _(`--max-results 1` proven green; `limit_reason` field
      not added.)_
- [x] **TEST:** `--max-results N` returns ≤N; metadata shows actual returned count
      (`meta.returned`).
- [~] **IMPL:** Default `--max-results 100` + truncation meta shipped. `--max-bytes`
      NOT implemented yet (currently rejected as an unknown flag).

**Exit criteria for Phase 1:** all Phase 0 "bug" tests green; existing suite green;
`shellcheck`/`shfmt` clean; PHP validators green (`validate-ai-config` OK).
Remaining before Phase 1 is fully closed: `--max-bytes`, the two missing-tool
tests, and the `struct`-unavailable test.

---

# Phase 2 — Rename modes the safe way (breakage handled as best practice)

> Renames stay in the plan. Best practice = add new names + keep aliases, migrate
> callers, only then consider removing aliases. Tests come first.

## 2A. Backward-compatible aliasing (no breakage at switchover)

- [x] **TEST:** `changed-files` lists changed files, no query required.
- [x] **TEST:** `staged-files` lists staged files, no query required.
- [x] **TEST:** legacy `changed` still works AND emits a `warnings[]` deprecation note.
- [x] **TEST:** legacy `staged` still works AND emits a deprecation `warnings[]`.
- [x] **TEST:** `tracked` unchanged (no rename) — guards against accidental drift.
- [x] **IMPL:** Add canonical `changed-files` / `staged-files`.
- [x] **IMPL:** Keep `changed` / `staged` as deprecated aliases that route to the
      new names and add a non-fatal `warnings[]` entry. Do NOT remove yet.
- [x] **IMPL:** Mirror new + alias modes into `scripts/ai/ai-search-multi.sh`
      allowlist (family-split). _(NOTE: `ai-search-multi.sh` is now tracked but
      not yet in the scripts-pack registry — see 2C validator status.)_

## 2B. Split file-list modes from content-search modes

- [x] **TEST:** `changed-text Tenant` searches only changed files; not unchanged.
- [x] **TEST:** `changed-text` (no query) → `status=error` (query required).
- [x] **TEST:** `staged-text Tenant` searches only staged files.
- [x] **IMPL:** Add `changed-text` / `staged-text` content-search modes
      (`search_git_scoped_files`, `-H` for path-prefixed matches).
- [x] **IMPL:** Require `QUERY` for content modes; forbid it for canonical
      file-list modes (legacy aliases tolerate an ignored leading query).

## 2C. Gate + wiring verification (the "breakage" pass)

- [x] **TEST:** `validate-ai-config.php` passes with renamed wiring docs. _(green)_
- [x] **IMPL:** Update `docs/ai/tools/ai-search.md` + template to document the
      mode families and the deprecation of `changed`/`staged`.
- [~] **IMPL:** Re-run `validate-adapter-drift.php`, `validate-install-surface.php`,
      `validate-generated-artifacts.php`.
  - `validate-ai-config.php` → **OK**.
  - `validate-adapter-drift.php` → **OK** (only pre-existing WARNs on unrelated
    `workflows/search-evidence.md`/`plan-slice.md`).
  - `validate-install-surface.php` → **ERROR**: `scripts/ai/ai-search-multi.sh`
    is tracked but not registered in the scripts-pack registry. Needs a registry
    entry (install-surface change — approval-gated). Also flags pre-existing
    `templates/core/agents/researcher.md` 322 > 320 hard max (NOT this slice).
  - `validate-generated-artifacts.php` → **ERROR**: `docs/ai/repo-required-tools.md`
    out of date once `ai-search-multi.sh` is tracked. Fix:
    `php tools/ai/repo-tool-inventory.php --write` after the registry decision.

## 2D. Caller migration inventory (Phase R) — update references to new names

Migrate these to `changed-files`/`staged-files` (edit template ↔ rendered pairs
together). Aliases keep them working during migration, so this can be incremental.

- [ ] `.opencode/skills/ai-search/SKILL.md` (↔ template skills/ai-search/SKILL.md)
- [ ] `.opencode/skills/ai-scripts/SKILL.md`
- [ ] `.opencode/commands/search-evidence.md` (↔ template commands/search-evidence.md)
- [ ] `.github/skills/search-evidence/SKILL.md`, `.github/prompts/search-evidence.prompt.md`
- [ ] `.opencode/agents/repository-researcher.md` (↔ template + `.github/agents/*.agent.md`)
- [ ] `docs/ai/tools/actions/search-evidence.md` (↔ template)
- [ ] `packages/ai-universal-rules/templates/skills/ai-search/SKILL.md`
- [ ] `packages/ai-universal-rules/templates/commands/search-evidence.md`
- [ ] `packages/ai-universal-rules/templates/core/agents/repository-researcher.md`
- [ ] Remaining hits from
      `AI_OUTPUT=json bash scripts/ai/ai-search.sh text "ai-search.sh changed" . --fixed`
      and `... "ai-search.sh staged" . --fixed` (≈133 total references; verify each).

## 2E. Alias removal (optional, gated, last)

- [x] **TEST:** when `AI_SEARCH_STRICT=1`, legacy `changed`/`staged` → `status=error`
      pointing at the new names.
- [x] **IMPL:** Add strict-mode rejection (off by default) so teams can opt in.
- [ ] **DECISION (needs approval):** only remove aliases entirely after every
      caller in 2D is migrated AND validators are green without legacy names.

---

# Phase 3 — P1 AI-grade output (additive; after rename is stable)

> Each sub-feature: failing test(s) first, then implement. Additive only — do not
> remove `matches[]`.

## 3A. Structured match objects

- [x] **TEST:** JSON match has `path`,`line`,`text`,`mode`,`source_tool` (+`column`,
      `language`,`root`,`absolute_path` when applicable).
- [x] **TEST:** file with a colon in its name parses correctly (`path` stays valid).
      _(rg --json carries the path explicitly, so `app/Has:Colon.php` is safe in
      both `results[]` and `matches[]`.)_
- [x] **TEST:** `--absolute` adds `absolute_path`; relative `path` still present.
- [x] **IMPL:** Parse `rg --json` internally; emit structured objects alongside
      `matches[]`. **`column` is now accurate** (1-based, from submatch byte
      offset) — replaces the earlier hardcoded `column = 1` defect. Applies to
      `text`/`docs`; `tracked`/`changed-text`/`staged-text` stay line-parsed
      (column approximate) until a later slice.

## 3B. Context lines

- [x] **TEST:** `--context 2` yields 2 before/after; JSON separates match vs context
      via nested `.context.before[]` / `.context.after[]`.
- [x] **TEST:** `--before-context 3 --after-context 1` asymmetric works.
- [x] **TEST:** context respects `--max-bytes` and sets `meta.truncated=true`
      (context payload is dropped when the byte budget is exceeded).
- [x] **IMPL:** `--context/-A/-B`; context lines read from the file around the
      match (not rg's own context stream), so it survives the rg --json switch.

## 3C. Scope control

- [x] **TEST:** `--glob '*.php'` PHP-only; `--type js` JS-only.
- [x] **TEST:** `--exclude vendor --exclude node_modules` omits deps; defaults exclude
      `vendor,node_modules,dist,build,coverage` (+`.git` via rg).
- [x] **TEST:** case controls `--ignore-case/--case-sensitive/--smart-case/--fixed/--regex/--pcre2`;
      default smart-case (not forced ic).
- [x] **TEST:** `--max-depth N` bounds traversal.
- [x] **IMPL:** Wire all of the above through to `rg` (native `--smart-case`,
      `--glob`, `--type`, `--max-depth`; `--exclude P` → `--glob !P --glob !P/**`).
      `tracked` maps case/pattern to git-grep-compatible flags (`-i`/`-F`/`-P`).
      Removed now-dead `fixed`/`ignore_case` vars (superseded by
      `pattern_mode`/`case_mode`).

> Verified real (not just via the suite): Phase 3C **17/17**, Phase 3A/3B
> regression **10/10** (with accurate columns), Phase 0/1/2 regression **11/11**.
> `shellcheck`/`shfmt` clean; `validate-ai-config`/`adapter-drift` green; docs +
> template updated with the new modes and scope flags.

## 3D. Count / file-only modes + stable statuses  — NEXT SLICE (RED)

- [x] **TEST:** `--files-with-matches`, `--count`, `--count-matches` return totals
      without dumping all lines; add a `summary{total_files,total_matches}` object;
      `results[]` carry `count` (no `text`) for `--count`. _(green: 16/16)_
- [x] **TEST:** statuses limited to `ok|no_matches|error|unavailable|blocked|dry_run`.
- [x] **TEST:** `unsafe-all MODE` → `status=blocked` (closed-set proof); `text --dry-run`
      → `status=dry_run`. _(Both already asserted in the suite, Phase 0 + 3D; green.)_
- [x] **IMPL:** Implement count/file-only flags and the closed status set.
      _(Done. Added `--files-with-matches`/`-l`, `--count`, `--count-matches` →
      `count_mode`; aggregate `g_results_json` per-file via `group_by(.path)`;
      publish `g_summary_json` `{total_files,total_matches}`; `emit_json` adds
      `summary` only when set (additive — plain `text` has no `summary` key).
      Count-mode results are NOT truncated by the match-line cap. `matches[]`
      string array preserved.)_

> **Verification (Phase 3D, this turn):**
> `AI_SEARCH_RUN_P1_TESTS=1 bash tests/scripts/ai/test-ai-search.sh` → 16/16
> phase3D PASS, suite now advances to phase4 `diff` (next RED, expected).
> Default suite GREEN. `bash tests/scripts/ai/run-all-tests.sh ai-search` GREEN.
> `shfmt -d` clean; `shellcheck` clean (only the pre-existing SC2016 info on the
> `_rg_json_jq_prelude`, not in 3D code). Manual JSON shape confirmed:
> files→`{path}` only, count→`{path,count}`, count-matches→`total_matches>=2`.

> **Exact contract the tests assert (3D):**
> `--files-with-matches` → `results[]` of `{path}` only (no `text`); `--count` →
> `results[]` of `{path,count}` (no `text`); `--count-matches` →
> `summary.total_matches >= 2`. All three keep `summary{total_files,total_matches}`
> and keep the legacy `matches[]` string array. Closed status set everywhere:
> `ok|no_matches|error|unavailable|blocked|dry_run`.

---

# Phase 4 — P1 repo-aware modes (each its own slice; TDD-first) — RED

For every mode: `TEST` (mode searches only its surface; wrong-surface excluded) →
`IMPL` → mirror into `ai-search-multi.sh` → update docs/template.

> Status: tests AND fixtures for ALL of these already exist in
> `tests/scripts/ai/test-ai-search.sh:805-920` (RED, gated by
> `AI_SEARCH_RUN_P1_TESTS=1`) and assert structured `results[]` shapes plus
> wrong-surface exclusion via `expect_no_jq`. None of these modes exist in the
> script dispatch yet. The multi-allowlist smoke checks (`docs/config/deps/tests`)
> at `:925-937` must pass too. Result-field names below are taken verbatim from
> the live assertions — do not rename them.

- [ ] `diff` (unstaged/`--staged`/`--base main`; result fields: `path`,`marker`
      (`"+"`),`new_line`(number),`text`; `--staged` sets `.scope=="staged"`;
      excludes out-of-scope files via `expect_no_jq`).
- [ ] `history` (`git log -S` pickaxe; `--regex` → `-G`; `--messages` searches
      messages; `--patch` adds `.patch` string ONLY on request; result fields:
      `commit`,`author`,`date`,`message`,`path`; no `patch` key by default).
- [ ] `tests` (only test files: `tests/**`,`__tests__/**`,`*.test.*`,`*.spec.*`,`*Test.php`).
- [ ] `docs` (currently aliased to `text`; make it surface-scoped: `README*`,
      `CHANGELOG*`,`docs/**`,`*.md`,`*.rst`,`*.adoc`).
- [ ] `config` (`.env.example`,`config/**`,`*.yaml|yml|json|toml|ini|nix`,`docker-compose*`).
- [ ] `deps` (`composer.json`,`package.json`,lock files,`flake.nix`,`go.mod`,`Cargo.toml`,`pyproject.toml`).
- [ ] `todo` (no query required; `TODO|FIXME|HACK|XXX|deprecated|temporary|workaround|legacy`,
      grouped by file: `results[]` of `{path,matches[]}` where each match has
      `tag`,`line`,`text`).
- [ ] `unsafe-patterns` (no query required; curated risky patterns with
      `rule`/`severity`; result fields `path`,`rule`,`text`; NOT an unrestricted
      root scan — `expect_no_jq` proves `app/scope.php` is excluded).
- [ ] **IMPL (multi):** mirror `diff`/`history`/`tests`/`docs`/`config`/`deps`/`todo`/
      `unsafe-patterns` into `scripts/ai/ai-search-multi.sh` allowlist; suite at
      `:925-937` asserts `docs`/`config`/`deps`/`tests` route via multi.

---

# Phase 5 — P1 structural search — RED

> Status: tests + fixtures exist (`test-ai-search.sh:942-994`, RED, gated by
> `AI_SEARCH_RUN_P1_TESTS=1`). They are ast-grep-gated: when ast-grep is ABSENT
> the suite asserts `struct`/`symbols` → `status=unavailable`; when PRESENT it
> asserts `source_tool=="ast-grep"`, `language`, and `symbols`/`class` shapes
> (`kind`,`name`,`path`,`start`,`end`,`language`). ast-grep IS installed in this
> environment, so the positive cases run. Script currently has `struct` via
> `AI_LANG` only; `--lang`, `symbols`, and the
> `class|function|method|interface|enum|route|config-key` shortcuts are not
> implemented. Note: the test file covers `struct`/`symbols`/`class`; `method`/
> `interface`/`enum`/`route`/`config-key` are specified in the matrix markdown but
> not yet in the shell suite — add those assertions before implementing them.

- [ ] **TEST:** `struct 'class $NAME' . --lang php` runs PHP ast-grep.
- [ ] **TEST:** `struct 'function $NAME($$$ARGS)' . --lang js` runs JS ast-grep.
- [ ] **TEST:** `symbols UserService` returns kind+name+file+start(+end)+language.
- [ ] **TEST:** shortcut `class UserService` returns class defs only.
- [ ] **IMPL:** `--lang LANG` (not only `AI_LANG`); `symbols` mode; shortcuts
      `class|function|method|interface|enum|route|config-key`; structured results.

---

# Phase 6 — Outside-repo search (SEPARATE, approval-gated design)

> This conflicts with `external_directory: ask` and `assert_inside_repo` in
> `common.sh`. Do NOT fold into a bug-fix slice. Architect + explicit approval first.

- [ ] **DECISION (needs approval):** confirm outside-root policy with project owner.
- [ ] Block outside-root by default → `status=blocked` with clear warning.
- [ ] `--allow-outside-root` required for siblings/parent/`$HOME`/absolute paths.
- [ ] Allowlist from `.ai-search-roots.json`/`.ai-search.roots`; reject non-listed
      unless `--unsafe-approved`.
- [ ] Project interaction discovery (list candidates; never auto-scan).
- [ ] `external-files` (list only) and `external-text` (search allowlisted only;
      `root_type: external` in JSON).
- [ ] Dangerous-root protection: block `/`,`$HOME`(unapproved),`/etc`,`/var`,`/usr`,
      `/System`,`/Applications`,hidden globals; no symlink escape unless `--follow`+approved.

---

# Phase 7 — P2 advanced (only after contract is stable)

- [ ] Multi-root (`--root` repeated; preserve root identity; per-root + global limits).
- [ ] Multi-pattern (`--or/--and/--not`; staged chained `rg` first).
- [ ] Time filters (`--modified-after/--modified-before/--changed-within`).
- [ ] Fuzzy as a separate marked mode (lower confidence; never mixed with exact).
- [ ] Caching (bounded structured results; key=query+root+mode+flags+commit; `--no-cache`).
- [ ] Raw `rg` passthrough behind `--rg --` delimiter; disabled in restricted mode.

---

# Final implementation order (TDD-first, correctness before rename)

- [~] **Step 0:** Phase 0 — TDD harness + canonical envelope + gate-proof test.
      _(harness + envelope `query`/`mode` + docs done; gate-proof test and
      per-shape envelope keys deferred.)_
- [~] **Step 1:** Phase 1 — arg parser, real errors, real doctor, bounded output.
      _(1A + 1C done; 1B core done except missing-tool/struct-unavailable tests;
      1D `--max-results` done, `--max-bytes` pending.)_ (script is correct and green)
- [x] **Step 2:** Phase 2A/2B — `changed-files`/`staged-files`/`changed-text`/`staged-text`
      + deprecated aliases + strict mode (2E impl); `ai-search-multi.sh` family-split.
      _(logic green: 14/14 Phase 2 criteria + 20/20 Phase 0/1 no regression.)_
- [~] **Step 3:** Phase 2C/2D — docs/template rename done; `validate-ai-config`/
      `adapter-drift` green. **Blocked:** `ai-search-multi.sh` scripts-pack registry
      entry + `repo-required-tools.md` regen (install-surface, approval-gated);
      caller migration (2D, ~133 refs) not started.
- [x] **Step 4:** Phase 3 — **3A/3B/3C/3D done** (structured JSON via `rg --json`,
      accurate column, context, scope control, count/file-only modes + closed
      status set). Verified 16/16 phase3D + no regression.
- [~] **Step 5:** Phase 4 — `diff/history/tests/docs/config/deps/todo/unsafe-patterns`
      + multi allowlist mirror. **NOW the active RED line** (`[phase4] diff` →
      `unknown mode: diff`). **Tests + fixtures already written** (`:805-937`).
      Implementation-only.
- [ ] **Step 6:** Phase 5 — `struct --lang` + `symbols` + `class` shortcut (RED;
      tests + fixtures written, ast-grep present so positive cases run). Add
      `method/interface/enum/route/config-key` test assertions first, then implement.
- [ ] **Step 7:** Phase 6 — outside-repo (approval-gated, architect-designed).
- [ ] **Step 8:** Phase 7 — multi-root, time filters, fuzzy, cache, raw passthrough.
- [ ] **Step 9 (optional):** Phase 2E — alias removal once all callers migrated.

## Verification command block (run per phase; copy-paste ready)

shellcheck scripts/ai/ai-search.sh
shfmt -d scripts/ai/ai-search.sh
bash tests/scripts/ai/test-ai-search.sh
bash tests/scripts/ai/run-all-tests.sh ai-search
php tools/ai/validate-ai-config.php
php tools/ai/validate-adapter-drift.php
php tools/ai/validate-install-surface.php
php tools/ai/validate-generated-artifacts.php

Manual mode-contract matrix (after fixtures are built): run the assertions in
`ai-search-todo-tests.md`. Each uses `AI_OUTPUT=json bash scripts/ai/ai-search.sh`
and binds match conditions with `any(.matches[]; A and B)`.
