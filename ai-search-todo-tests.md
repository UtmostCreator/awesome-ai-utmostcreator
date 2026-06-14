# ai-search.sh test matrix (verified, one-to-one with the rebuild plan)

This matrix is the executable companion to `ai-search-todo.md`. It is TDD-first:
each test encodes the **target** contract, so many tests are RED until the matching
plan phase is implemented. That is intended.

## Verification status of this file

> STATUS (verified against live repo): the rebuild has advanced well past this
> file's snapshot. Corrections:
>
> - Source moved: ai-search implementation is now `scripts/ai/internal/search/*`
>   (root `scripts/ai/ai-search.sh` is a facade; `bin/read/ai-search.sh` is a
>   shim). All `bash scripts/ai/ai-search.sh ...` commands below stay valid.
> - NOW GREEN (was "RED/not implemented"): Phase 4 modes (`diff`, `history`,
>   `tests`, `config`, `deps`, `todo`, `unsafe-patterns`) and Phase 5
>   (`struct --lang`, `symbols`, `class`) are implemented in
>   `scripts/ai/internal/search/25-modes.sh` and asserted `ok` in the suite.
> - STILL RED (genuinely unimplemented): shortcut modes `function`, `method`,
>   `interface`, `enum`, `route`, `config-key`, and the `external-*` /
>   `--allow-outside-root` family.
> - STALE LINE ANCHORS: the suite is now 1158 lines with a Phase 6
>   (self-introspection). Old anchors are wrong — gate is at `:670` (not 586);
>   Phase 3D at `:839` (not 755); Phase 4 at `:889` + multi `:1009` (not
>   805/925); Phase 5 at `:1026` (not 942).

- Test design verified statically against `docs/ai/tools/ai-search.md`,
  `tools/ai/validate-ai-config.php`, and the live script.
- `jq any(.matches[]; cond)` two-argument syntax **verified working**.

### Implementation status (live, as of current branch)

The canonical, runnable suite is `tests/scripts/ai/test-ai-search.sh` (registered
in `run-all-tests.sh`, ~1158 lines, includes Phase 6). THIS markdown matrix is a
secondary design reference; prefer the shell suite for pass/fail truth.

**Gate:** the Phase 3+ block is gated by the `AI_SEARCH_RUN_P1_TESTS=1` env var
(`test-ai-search.sh:670`), NOT by a `set -e` abort.

**Remaining RED is only the shortcut/external mode family** (function, method,
interface, enum, route, config-key, external-*). Phases 0–5 core modes run GREEN.
ast-grep is installed here, so Phase 5 positive cases run (the `unavailable` path
is asserted only when ast-grep is absent).

**GREEN and real-verified** (Phases 0, 1, 2, 3A, 3B, 3C):

- envelope: `query`, `mode`, `schema`, `tool`, `limits.max_results`,
  `meta.returned`, `meta.truncated`, `warnings[]`/`errors[]`;
- arg parser: flags in any position; unknown flag → `error` naming the flag;
- errors: invalid regex → `error`; git modes on non-git root → `error`;
  missing optional tool (`ast-grep`/`fd`) → `unavailable`;
- `doctor` → `diagnostics{available,missing,warnings,root,git_available}`;
- rename family: `changed-files`/`staged-files`/`changed-text`/`staged-text`,
  deprecated `changed`/`staged` aliases (warn; `AI_SEARCH_STRICT=1` → error);
- **structured `results[]`** for `text`/`docs` via `rg --json`:
  `path,line,column,text,mode,source_tool,root,language(,absolute_path)`;
  `matches[]` kept as additive `path:line:text` strings; colon-in-filename safe;
  **accurate 1-based `column`** (the old hardcoded `column=1` defect is fixed);
- context: nested `.context.before[]`/`.context.after[]`; `--max-bytes` truncation;
- scope: `--glob`,`--type`,`--exclude`,`--max-depth`, case/pattern controls,
  default excludes (`vendor,node_modules,dist,build,coverage,.git`);
- **Phase 3D** (done this turn): `--files-with-matches` (`results[]` of `{path}`),
  `--count` (`results[]` of `{path,count}`), `--count-matches`, all with
  `summary{total_files,total_matches}`; `summary` is additive (absent on plain
  searches); closed status set `ok|no_matches|error|unavailable|blocked|dry_run`.

**RED / not implemented yet** — tests exist but the modes/flags do not:

> CORRECTED: Phase 4 (`diff`, `history`, `tests`, `config`, `deps`, `todo`,
> `unsafe-patterns`) and Phase 5 `struct --lang` / `symbols` / `class` are now
> IMPLEMENTED and GREEN (`scripts/ai/internal/search/25-modes.sh`). Only the
> shortcut and external families below remain RED.

- Phase 5 shortcuts: `function`, `method`, `interface`, `enum`, `route`,
  `config-key`.
- External (`external-*`, `--allow-outside-root`, `.ai-search-roots.json`):
  approval-gated, not started.

> Caveat on this matrix's `doctor` test: it requires `diagnostics.available` to
> include `bash`, which the live `doctor` does not report. Align the test or the
> doctor before ticking the `doctor` row here.

Tick rows in THIS matrix only after the matching shell-suite assertions are
green; the shell suite is the source of truth.

## Corrections already applied (do not regress)

1. **Invocation convention fixed (critical).** JSON mode is activated by the
   `AI_OUTPUT=json` **environment variable** and the script is invoked as
   `bash scripts/ai/ai-search.sh`, per the repo contract
   (`docs/ai/tools/ai-search.md:10`, `validate-ai-config.php:555`). The earlier
   draft used a `--json` flag and bare `ai-search.sh`; the current script silently
   ignores `--json`, and under the rebuild plan an unknown flag becomes
   `status=error`, which would have failed **every** test. All commands below use
   `AI_OUTPUT=json bash scripts/ai/ai-search.sh ...`.
2. **`jq any(...)` binding.** Every assertion binds conditions to the *same*
   match object via `any(.matches[]; A and B)`. The split-`select()` pattern
   (which can pass on two different objects) is removed.
3. **Git state isolated.** Separate setup blocks: clean → unstaged-only →
   staged-only. `diff` runs before `git add`; a second unstaged marker is added
   after staging so `diff --staged` and `diff` are independently provable.
4. **Real free-function fixture** added for `function` mode (`tenant_region_label`),
   asserting `kind == "function"` only.
5. **Stronger, full-contract assertions** for `line`/`column`/`mode`/`source_tool`/
   `root`/`language`, exact scope (`all(...)`), `--max-bytes` byte proof, and a
   real `doctor` shape.
6. **Coverage added:** missing-query tests for every content mode; non-git-root
   tests for git modes; missing-tool tests; `--allow-outside-root`; symlink escape;
   `struct` split into present/absent.

## Field-name caveat (RED-until-built)

The current script emits `matches` as an array of **strings**
(`"path:line:text"`). These tests assert the **target** structured-object contract
(plan Phase 0/3). Until structured output lands, object assertions are expected to
fail. File-list modes assert `.files[]`, symbol modes assert `.symbols[]`,
history asserts `.commits[]`, external discovery asserts `.roots[]` — all per the
plan's target envelope.

## Result convention

```md
Result: [ ] PASS / [ ] FAIL
Actual:
Notes:
```

---

## 1. Deterministic fixture set

Run in a disposable branch. Fixtures cover service, interface, enum, route, config,
test, docs, a standalone function, a Composer manifest, dependency folders, a
colon-named file, and an allowlisted sibling root.

```bash
git checkout -b test/ai-search-fixtures

mkdir -p \
  src/Services \
  src/Support \
  src/Contracts \
  src/Enums \
  routes \
  config \
  tests/Feature \
  docs \
  vendor/acme/package \
  node_modules/fake-package \
  ../shared-lib/src

cat > src/Services/TenantResolver.php <<'PHP'
<?php

namespace App\Services;

use App\Contracts\TenantResolverInterface;
use App\Enums\TenantRegion;

final class TenantResolver implements TenantResolverInterface
{
    public function resolveTenant(string $host): string
    {
        // TODO workaround: legacy host mapping
        if ($host === 'uk.example.test') {
            return TenantRegion::UK->value;
        }

        return TenantRegion::US->value;
    }

    public function dangerousDebug(string $payload): mixed
    {
        return unserialize($payload);
    }
}
PHP

cat > src/Support/tenant_helpers.php <<'PHP'
<?php

function tenant_region_label(string $region): string
{
    return strtoupper($region);
}
PHP

cat > src/Contracts/TenantResolverInterface.php <<'PHP'
<?php

namespace App\Contracts;

interface TenantResolverInterface
{
    public function resolveTenant(string $host): string;
}
PHP

cat > src/Enums/TenantRegion.php <<'PHP'
<?php

namespace App\Enums;

enum TenantRegion: string
{
    case UK = 'uk';
    case US = 'us';
}
PHP

cat > routes/web.php <<'PHP'
<?php

use App\Services\TenantResolver;

Route::get('/tenant/{host}', [TenantResolver::class, 'resolveTenant']);
PHP

cat > config/tenant.php <<'PHP'
<?php

return [
    'default_region' => env('TENANT_DEFAULT_REGION', 'uk'),
    'queue' => env('QUEUE_CONNECTION', 'database'),
];
PHP

cat > tests/Feature/TenantResolverTest.php <<'PHP'
<?php

use App\Services\TenantResolver;

it('resolves UK tenant', function () {
    $resolver = new TenantResolver();

    expect($resolver->resolveTenant('uk.example.test'))->toBe('uk');
});
PHP

cat > docs/tenant.md <<'MD'
# Tenant install guide

Use TENANT_DEFAULT_REGION during install.
MD

cat > composer.json <<'JSON'
{
  "require": {
    "php": "^8.2",
    "laravel/framework": "^11.0"
  }
}
JSON

cat > vendor/acme/package/FakeTenant.php <<'PHP'
<?php
final class FakeTenantVendor {}
PHP

cat > node_modules/fake-package/index.php <<'PHP'
<?php
final class FakeTenantNodeModule {}
PHP

cat > ../shared-lib/src/SharedTenant.php <<'PHP'
<?php
final class SharedTenant {}
PHP

cat > .ai-search-roots.json <<'JSON'
{
  "roots": [
    "../shared-lib"
  ]
}
JSON

git add src/Services/TenantResolver.php \
  src/Support/tenant_helpers.php \
  src/Contracts/TenantResolverInterface.php \
  src/Enums/TenantRegion.php \
  routes/web.php \
  config/tenant.php \
  tests/Feature/TenantResolverTest.php \
  docs/tenant.md \
  composer.json \
  .ai-search-roots.json

git commit -m "Add AI search fixture files"
```

> Colon-file note: `src/Services/foo:bar.php` (used later) is valid on Linux/macOS
> but cannot exist on native Windows checkouts. Mark that test SKIP on Windows.

---

## 2. Per-method / per-mode test matrix

### `doctor`

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh doctor . > /tmp/doctor.json
```

```bash
jq -e '
  .status == "ok"
  and (.diagnostics.available | index("bash"))
  and (.diagnostics.available | index("jq"))
  and (.diagnostics.available | index("rg"))
  and (.diagnostics.available | index("git"))
  and (.diagnostics | has("missing"))
  and (.diagnostics | has("warnings"))
  and (.diagnostics | has("fd") or (.diagnostics.available | index("fd")) or (.diagnostics.available | index("fdfind")))
  and (.diagnostics | has("ast_grep") or (.diagnostics | has("ast-grep")) or (.diagnostics.available | index("ast-grep")) or (.diagnostics.missing | index("ast-grep")))
  and (.diagnostics | has("root"))
  and (.diagnostics | has("git_available"))
' /tmp/doctor.json
```

- [ ] **Result:** PASS / FAIL

---

### `text`

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh text TenantResolver . > /tmp/text.json
```

```bash
jq -e '
  .status == "ok"
  and any(.matches[];
    .path == "src/Services/TenantResolver.php"
    and (.text | contains("final class TenantResolver"))
    and .mode == "text"
    and .source_tool != null
    and (.line | type == "number")
  )
' /tmp/text.json
```

- [ ] **Result:** PASS / FAIL

---

### `text` with `--fixed` (also proves `--fixed` is not treated as root)

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh text 'TenantResolver::class' . --fixed > /tmp/text-fixed.json
```

```bash
jq -e '
  .status == "ok"
  and any(.matches[];
    .path == "routes/web.php"
    and (.text | contains("TenantResolver::class"))
  )
' /tmp/text-fixed.json
```

Parser regression (flag without explicit root must still search `.`):

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh text TenantResolver --fixed > /tmp/text-fixed-noroot.json
jq -e '.status == "ok" and (.matches | length > 0)' /tmp/text-fixed-noroot.json
```

- [ ] **Result:** PASS / FAIL

---

### `text` invalid regex error

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh text '[' . > /tmp/text-invalid-regex.json
```

```bash
jq -e '
  .status == "error"
  and ((.errors | join(" ")) | test("regex|parse|invalid"; "i"))
' /tmp/text-invalid-regex.json
```

> Asserts against `.errors[]` (the live envelope key). If the build adds
> `.error.message`, accept either: `((.error.message // (.errors|join(" "))) | test(...))`.

- [ ] **Result:** PASS / FAIL

---

### `text` missing query

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh text > /tmp/text-missing-query.json
```

```bash
jq -e '.status == "error" and ((.errors | join(" ")) | test("query|required|missing"; "i"))' /tmp/text-missing-query.json
```

- [ ] **Result:** PASS / FAIL

---

### Git modes — STATE ISOLATION

> Run these blocks in order. Each resets to a known state first.

#### Clean state baseline

```bash
git stash --include-untracked 2>/dev/null || true
git checkout -- . 2>/dev/null || true
```

#### `changed-files` (unstaged-only setup)

```bash
echo "// changed marker TenantChanged" >> src/Services/TenantResolver.php
AI_OUTPUT=json bash scripts/ai/ai-search.sh changed-files . > /tmp/changed-files.json
```

```bash
jq -e '
  .status == "ok"
  and any(.files[]; .path == "src/Services/TenantResolver.php")
  and ((.scope // "unstaged") == "unstaged")
' /tmp/changed-files.json
```

- [ ] **Result:** PASS / FAIL

#### `changed-text` (still unstaged)

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh changed-text TenantChanged . > /tmp/changed-text.json
```

```bash
jq -e '
  .status == "ok"
  and any(.matches[];
    .path == "src/Services/TenantResolver.php"
    and (.text | contains("TenantChanged"))
  )
  and all(.matches[]; .path == "src/Services/TenantResolver.php")
' /tmp/changed-text.json
```

> `all(...)` proves unchanged files are NOT searched.

- [ ] **Result:** PASS / FAIL

#### `changed-text` missing query (no positional → must NOT treat `.` as query)

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh changed-text > /tmp/changed-text-missing-query.json
```

```bash
jq -e '.status == "error" and ((.errors | join(" ")) | test("query|required|missing"; "i"))' /tmp/changed-text-missing-query.json
```

- [ ] **Result:** PASS / FAIL

#### `diff` (unstaged hunks — BEFORE git add)

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh diff TenantChanged . > /tmp/diff.json
```

```bash
jq -e '
  .status == "ok"
  and ((.scope // "unstaged") == "unstaged")
  and any(.matches[];
    .path == "src/Services/TenantResolver.php"
    and .line_type == "added"
    and (.text | contains("TenantChanged"))
  )
' /tmp/diff.json
```

- [ ] **Result:** PASS / FAIL

#### `staged-files` (staged-only setup)

```bash
git add src/Services/TenantResolver.php
AI_OUTPUT=json bash scripts/ai/ai-search.sh staged-files . > /tmp/staged-files.json
```

```bash
jq -e '
  .status == "ok"
  and any(.files[]; .path == "src/Services/TenantResolver.php")
  and ((.scope // "staged") == "staged")
' /tmp/staged-files.json
```

- [ ] **Result:** PASS / FAIL

#### `staged-text`

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh staged-text TenantChanged . > /tmp/staged-text.json
```

```bash
jq -e '
  .status == "ok"
  and any(.matches[];
    .path == "src/Services/TenantResolver.php"
    and (.text | contains("TenantChanged"))
  )
' /tmp/staged-text.json
```

- [ ] **Result:** PASS / FAIL

#### `diff --staged` (staged hunks)

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh diff TenantChanged . --staged > /tmp/diff-staged.json
```

```bash
jq -e '
  .status == "ok"
  and .scope == "staged"
  and any(.matches[];
    .path == "src/Services/TenantResolver.php"
    and .line_type == "added"
    and (.text | contains("TenantChanged"))
  )
' /tmp/diff-staged.json
```

- [ ] **Result:** PASS / FAIL

#### Git modes against a non-git directory (error, not no_matches)

```bash
tmp_nogit="$(mktemp -d)"
AI_OUTPUT=json bash scripts/ai/ai-search.sh changed-files "$tmp_nogit" > /tmp/changed-nogit.json
AI_OUTPUT=json bash scripts/ai/ai-search.sh tracked foo "$tmp_nogit" > /tmp/tracked-nogit.json
```

```bash
jq -e '.status == "error"' /tmp/changed-nogit.json
jq -e '.status == "error"' /tmp/tracked-nogit.json
```

- [ ] **Result:** PASS / FAIL

---

### `history`

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh history TenantResolver . > /tmp/history.json
```

```bash
jq -e '
  .status == "ok"
  and any(.commits[];
    (.subject | contains("Add AI search fixture files"))
    and (.hash | type == "string")
    and (.date != null)
    and ((.files // []) | index("src/Services/TenantResolver.php"))
  )
' /tmp/history.json
```

- [ ] **Result:** PASS / FAIL

---

### `history --messages`

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh history 'AI search fixture' . --messages > /tmp/history-messages.json
```

```bash
jq -e '.status == "ok" and any(.commits[]; .subject | contains("AI search fixture"))' /tmp/history-messages.json
```

- [ ] **Result:** PASS / FAIL

---

### `docs` (positive + negative scope)

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh docs install . > /tmp/docs.json
```

```bash
jq -e '
  .status == "ok"
  and any(.matches[]; .path == "docs/tenant.md")
  and all(.matches[]; .path | test("(^docs/|README|CHANGELOG|\\.md$|\\.rst$|\\.adoc$)"))
' /tmp/docs.json
```

- [ ] **Result:** PASS / FAIL

---

### `tests` (positive + negative scope)

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh tests resolveTenant . > /tmp/tests.json
```

```bash
jq -e '
  .status == "ok"
  and any(.matches[]; .path == "tests/Feature/TenantResolverTest.php")
  and all(.matches[]; .path | test("(^tests/|__tests__/|Test\\.php$|\\.test\\.|\\.spec\\.)"))
' /tmp/tests.json
```

- [ ] **Result:** PASS / FAIL

---

### `config` (positive + negative scope)

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh config QUEUE_CONNECTION . > /tmp/config.json
```

```bash
jq -e '
  .status == "ok"
  and any(.matches[];
    .path == "config/tenant.php"
    and (.text | contains("QUEUE_CONNECTION"))
  )
  and all(.matches[]; .path | test("(^config/|\\.env|\\.ya?ml$|\\.json$|\\.toml$|\\.ini$|\\.nix$|docker-compose)"))
' /tmp/config.json
```

- [ ] **Result:** PASS / FAIL

---

### `deps` (positive + negative scope)

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh deps laravel . > /tmp/deps.json
```

```bash
jq -e '
  .status == "ok"
  and any(.matches[]; .path == "composer.json")
  and all(.matches[]; .path | test("(composer\\.(json|lock)|package(-lock)?\\.json|pnpm-lock|yarn\\.lock|flake\\.nix|go\\.mod|Cargo\\.toml|pyproject\\.toml)$"))
' /tmp/deps.json
```

> Future: add JS/Nix/Cargo/Python manifest fixtures for full breadth.

- [ ] **Result:** PASS / FAIL

---

### `todo`

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh todo workaround . > /tmp/todo.json
```

```bash
jq -e '
  .status == "ok"
  and any(.matches[];
    .path == "src/Services/TenantResolver.php"
    and (.text | test("TODO|workaround"; "i"))
  )
' /tmp/todo.json
```

> Future: add FIXME/HACK/legacy fixtures to validate preset breadth.

- [ ] **Result:** PASS / FAIL

---

### `unsafe-patterns` (detection + severity + dependency exclusion in one assertion)

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh unsafe-patterns . > /tmp/unsafe.json
```

```bash
jq -e '
  .status == "ok"
  and any(.matches[];
    .path == "src/Services/TenantResolver.php"
    and ((.pattern_kind == "unserialize") or (.text | contains("unserialize")))
    and (.severity != null)
  )
  and all(.matches[];
    (.path | contains("vendor/") | not)
    and (.path | contains("node_modules/") | not)
  )
' /tmp/unsafe.json
```

- [ ] **Result:** PASS / FAIL

---

### `struct` with `ast-grep` present

> Run only when `ast-grep` is installed (`command -v ast-grep`).

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh struct 'class $NAME' . --lang php > /tmp/struct-class.json
```

```bash
jq -e '
  .status == "ok"
  and any(.matches[]; .path == "src/Services/TenantResolver.php")
' /tmp/struct-class.json
```

- [ ] **Result:** PASS / FAIL

### `struct` with `ast-grep` absent → `unavailable`

> Run by hiding ast-grep from PATH, or assert on a host without it.

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh struct 'class $NAME' . --lang php > /tmp/struct-unavail.json
```

```bash
jq -e '.status == "unavailable" and ((.errors | join(" ")) | test("ast-grep|unavailable|missing"; "i"))' /tmp/struct-unavail.json
```

- [ ] **Result:** PASS / FAIL

---

### `symbols`

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh symbols TenantResolver . --lang php > /tmp/symbols.json
```

```bash
jq -e '
  .status == "ok"
  and any(.symbols[];
    .name == "TenantResolver"
    and .kind == "class"
    and .path == "src/Services/TenantResolver.php"
    and (.start_line | type == "number")
    and (has("end_line"))
    and (.language == "php")
  )
' /tmp/symbols.json
```

- [ ] **Result:** PASS / FAIL

---

### `class`

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh class TenantResolver . --lang php > /tmp/class.json
```

```bash
jq -e '
  .status == "ok"
  and any(.symbols[]; .name == "TenantResolver" and .kind == "class")
  and all(.symbols[]; .kind == "class")
' /tmp/class.json
```

- [ ] **Result:** PASS / FAIL

---

### `function` (real free-function fixture; `kind == "function"` only)

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh function tenant_region_label . --lang php > /tmp/function.json
```

```bash
jq -e '
  .status == "ok"
  and any(.symbols[];
    .name == "tenant_region_label"
    and .kind == "function"
    and .path == "src/Support/tenant_helpers.php"
  )
  and all(.symbols[]; .kind == "function")
' /tmp/function.json
```

- [ ] **Result:** PASS / FAIL

---

### `method`

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh method resolveTenant . --lang php > /tmp/method.json
```

```bash
jq -e '
  .status == "ok"
  and any(.symbols[];
    .name == "resolveTenant"
    and .kind == "method"
    and .path == "src/Services/TenantResolver.php"
  )
' /tmp/method.json
```

- [ ] **Result:** PASS / FAIL

---

### `interface`

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh interface TenantResolverInterface . --lang php > /tmp/interface.json
```

```bash
jq -e '
  .status == "ok"
  and any(.symbols[];
    .name == "TenantResolverInterface"
    and .kind == "interface"
    and .path == "src/Contracts/TenantResolverInterface.php"
  )
' /tmp/interface.json
```

- [ ] **Result:** PASS / FAIL

---

### `enum`

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh enum TenantRegion . --lang php > /tmp/enum.json
```

```bash
jq -e '
  .status == "ok"
  and any(.symbols[];
    .name == "TenantRegion"
    and .kind == "enum"
    and .path == "src/Enums/TenantRegion.php"
  )
' /tmp/enum.json
```

- [ ] **Result:** PASS / FAIL

---

### `route` (assert results come from routes/**)

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh route tenant . --lang php > /tmp/route.json
```

```bash
jq -e '
  .status == "ok"
  and any(.matches[];
    .path == "routes/web.php"
    and (.text | contains("Route::get"))
  )
  and all(.matches[]; .path | startswith("routes/"))
' /tmp/route.json
```

- [ ] **Result:** PASS / FAIL

---

### `config-key`

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh config-key default_region . --lang php > /tmp/config-key.json
```

```bash
jq -e '
  .status == "ok"
  and any(.matches[];
    .path == "config/tenant.php"
    and (.text | contains("default_region"))
  )
' /tmp/config-key.json
```

- [ ] **Result:** PASS / FAIL

---

### `external-files` (discovery only, no scan)

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh external-files . > /tmp/external-files.json
```

```bash
jq -e '
  .status == "ok"
  and .searched_content == false
  and any(.roots[]; .path | contains("../shared-lib"))
' /tmp/external-files.json
```

- [ ] **Result:** PASS / FAIL

---

### `external-text` (explicit allowlisted external root)

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh external-text SharedTenant --root ../shared-lib > /tmp/external-text.json
```

```bash
jq -e '
  .status == "ok"
  and any(.matches[];
    .root_type == "external"
    and (.path | contains("SharedTenant.php"))
  )
' /tmp/external-text.json
```

- [ ] **Result:** PASS / FAIL

---

### outside-root blocked by default

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh text SharedTenant ../shared-lib > /tmp/outside-blocked.json
```

```bash
jq -e '.status == "blocked" and ((.errors | join(" ")) | test("outside|root|allow"; "i"))' /tmp/outside-blocked.json
```

- [ ] **Result:** PASS / FAIL

---

### `--allow-outside-root` (explicit opt-in succeeds)

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh text SharedTenant ../shared-lib --allow-outside-root > /tmp/outside-allowed.json
```

```bash
jq -e '
  (.status == "ok" or .status == "no_matches")
  and (.status != "blocked")
' /tmp/outside-allowed.json
```

- [ ] **Result:** PASS / FAIL

---

### dangerous root blocked

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh text root / > /tmp/root-blocked.json
```

```bash
jq -e '.status == "blocked" and ((.errors | join(" ")) | test("dangerous|root|filesystem|blocked"; "i"))' /tmp/root-blocked.json
```

- [ ] **Result:** PASS / FAIL

---

### symlink escape blocked (does not follow out of repo)

```bash
ln -s /etc src/escape-link
AI_OUTPUT=json bash scripts/ai/ai-search.sh text passwd src/escape-link > /tmp/symlink-escape.json
rm -f src/escape-link
```

```bash
jq -e '.status == "blocked" or .status == "no_matches"' /tmp/symlink-escape.json
```

> Must NOT return file contents from `/etc`. `blocked` is preferred;
> `no_matches` is acceptable only if symlinks are not followed at all.

- [ ] **Result:** PASS / FAIL

---

### missing-tool behavior (rg / jq / git / ast-grep)

> Run by hiding each tool from PATH in a subshell. Core tools → `error`;
> optional `ast-grep` → `unavailable`.

```bash
# Example shape (repeat per tool):
PATH="/usr/bin" AI_OUTPUT=json bash scripts/ai/ai-search.sh text Tenant . > /tmp/missing-tool.json
jq -e '.status == "error" or .status == "unavailable"' /tmp/missing-tool.json
```

- [ ] **Result:** PASS / FAIL

---

## 3. Option-contract tests

### structured JSON object output

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh text TenantResolver . > /tmp/json-shape.json
```

```bash
jq -e '
  .status == "ok"
  and (.matches | type == "array")
  and (.matches[0] | has("path"))
  and (.matches[0] | has("line"))
  and (.matches[0] | has("text"))
  and (.matches[0] | has("match"))
  and (.matches[0] | has("mode"))
  and (.matches[0] | has("source_tool"))
  and (.matches[0] | has("root"))
' /tmp/json-shape.json
```

- [ ] **Result:** PASS / FAIL

---

### filenames containing colons (SKIP on Windows)

```bash
cat > 'src/Services/foo:bar.php' <<'PHP'
<?php
final class ColonFileTenant {}
PHP
git add 'src/Services/foo:bar.php'
```

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh text ColonFileTenant . > /tmp/colon-file.json
```

```bash
jq -e '.status == "ok" and any(.matches[]; .path == "src/Services/foo:bar.php")' /tmp/colon-file.json
```

- [ ] **Result:** PASS / FAIL

---

### `--absolute`

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh text TenantResolver . --absolute > /tmp/absolute.json
```

```bash
jq -e '
  .status == "ok"
  and (.matches[0] | has("path"))
  and (.matches[0] | has("absolute_path"))
  and (.matches[0].absolute_path | startswith("/"))
' /tmp/absolute.json
```

- [ ] **Result:** PASS / FAIL

---

### `--context` (bound to the same matched object)

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh text 'uk.example.test' . --context 2 > /tmp/context.json
```

```bash
jq -e '
  .status == "ok"
  and any(.matches[];
    (.text | contains("uk.example.test"))
    and (has("context_before"))
    and (has("context_after"))
    and ((.context_before | length) <= 2)
    and ((.context_after | length) <= 2)
  )
' /tmp/context.json
```

- [ ] **Result:** PASS / FAIL

---

### `--before-context` / `--after-context` (asymmetric, bound)

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh text 'uk.example.test' . --before-context 3 --after-context 1 > /tmp/context-asym.json
```

```bash
jq -e '
  .status == "ok"
  and any(.matches[];
    (.text | contains("uk.example.test"))
    and ((.context_before | length) <= 3)
    and ((.context_after | length) <= 1)
  )
' /tmp/context-asym.json
```

- [ ] **Result:** PASS / FAIL

---

### `--max-results`

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh text Tenant . --max-results 1 > /tmp/max-results.json
```

```bash
jq -e '
  .status == "ok"
  and (.matches | length <= 1)
  and (.limits.max_results == 1)
  and (.meta.truncated == true)
' /tmp/max-results.json
```

> Fixtures contain many `Tenant` hits, so truncation must trigger at 1.

- [ ] **Result:** PASS / FAIL

---

### `--max-bytes` (prove byte limiting, not just presence)

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh text Tenant . --context 20 --max-bytes 1000 > /tmp/max-bytes.json
```

```bash
jq -e '
  (.status == "ok" or .status == "no_matches")
  and (.limits.max_bytes == 1000)
  and (.meta.truncated == true)
  and ((.meta.bytes_used // .limits.bytes_used // 0) <= 1000)
' /tmp/max-bytes.json
```

- [ ] **Result:** PASS / FAIL

---

### `--glob '*.php'`

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh text Tenant . --glob '*.php' > /tmp/glob-php.json
```

```bash
jq -e '.status == "ok" and all(.matches[]; .path | endswith(".php"))' /tmp/glob-php.json
```

- [ ] **Result:** PASS / FAIL

---

### `--type php`

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh text Tenant . --type php > /tmp/type-php.json
```

```bash
jq -e '.status == "ok" and all(.matches[]; .path | endswith(".php"))' /tmp/type-php.json
```

- [ ] **Result:** PASS / FAIL

---

### default excludes

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh text FakeTenant . > /tmp/default-excludes.json
```

```bash
jq -e '
  .status == "no_matches"
  or all(.matches[];
    (.path | contains("vendor/") | not)
    and (.path | contains("node_modules/") | not)
  )
' /tmp/default-excludes.json
```

- [ ] **Result:** PASS / FAIL

---

### explicit `--exclude`

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh text Tenant . --exclude src > /tmp/exclude-src.json
```

```bash
jq -e '
  .status == "ok"
  and (.matches | length > 0)
  and all(.matches[]; .path | startswith("src/") | not)
' /tmp/exclude-src.json
```

- [ ] **Result:** PASS / FAIL

---

### unknown flag

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh text Tenant . --bad-flag > /tmp/bad-flag.json
```

```bash
jq -e '.status == "error" and ((.errors | join(" ")) | test("unknown|flag"; "i"))' /tmp/bad-flag.json
```

- [ ] **Result:** PASS / FAIL

---

## 4. Use-case plans

### Use case A — modify a PHP service safely

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh class TenantResolver . --lang php > /tmp/uc-a-class.json
jq -e '.status == "ok" and any(.symbols[]; .path == "src/Services/TenantResolver.php")' /tmp/uc-a-class.json

AI_OUTPUT=json bash scripts/ai/ai-search.sh method resolveTenant . --lang php > /tmp/uc-a-method.json
jq -e '.status == "ok" and any(.symbols[]; .name == "resolveTenant")' /tmp/uc-a-method.json

# usages must include BOTH route and test
AI_OUTPUT=json bash scripts/ai/ai-search.sh text resolveTenant . --type php --context 2 > /tmp/uc-a-usages.json
jq -e '
  .status == "ok"
  and any(.matches[]; .path == "routes/web.php")
  and any(.matches[]; .path == "tests/Feature/TenantResolverTest.php")
' /tmp/uc-a-usages.json

AI_OUTPUT=json bash scripts/ai/ai-search.sh tests TenantResolver . > /tmp/uc-a-tests.json
jq -e '.status == "ok" and any(.matches[]; .path == "tests/Feature/TenantResolverTest.php")' /tmp/uc-a-tests.json

AI_OUTPUT=json bash scripts/ai/ai-search.sh config TENANT_DEFAULT_REGION . > /tmp/uc-a-config.json
jq -e '.status == "ok" and any(.matches[]; .path == "config/tenant.php")' /tmp/uc-a-config.json
```

- [ ] **Result:** PASS / FAIL

---

### Use case B — review a PR (state reset inside the scenario)

```bash
# Reset to a known dirty state owned by THIS scenario.
git stash --include-untracked 2>/dev/null || true
git checkout -- . 2>/dev/null || true
echo "// changed marker TenantChanged" >> src/Services/TenantResolver.php

AI_OUTPUT=json bash scripts/ai/ai-search.sh changed-files . > /tmp/uc-b-files.json
jq -e '.status == "ok" and (.files | length >= 1)' /tmp/uc-b-files.json

AI_OUTPUT=json bash scripts/ai/ai-search.sh changed-text TenantChanged . > /tmp/uc-b-changed-text.json
jq -e '.status == "ok" and any(.matches[]; .text | contains("TenantChanged"))' /tmp/uc-b-changed-text.json

git add src/Services/TenantResolver.php
AI_OUTPUT=json bash scripts/ai/ai-search.sh diff TenantChanged . --staged > /tmp/uc-b-staged-diff.json
jq -e '.status == "ok" and .scope == "staged"' /tmp/uc-b-staged-diff.json

AI_OUTPUT=json bash scripts/ai/ai-search.sh unsafe-patterns . > /tmp/uc-b-unsafe.json
jq -e '.status == "ok" and any(.matches[]; .text | contains("unserialize"))' /tmp/uc-b-unsafe.json
```

- [ ] **Result:** PASS / FAIL

---

### Use case C — investigate a PHP bug (assert specific paths, not just count)

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh text TENANT_DEFAULT_REGION . --fixed --context 2 > /tmp/uc-c-error.json
jq -e '.status == "ok" and any(.matches[]; .path == "config/tenant.php")' /tmp/uc-c-error.json

AI_OUTPUT=json bash scripts/ai/ai-search.sh text TenantResolver . --type php --context 2 > /tmp/uc-c-refs.json
jq -e '
  .status == "ok"
  and any(.matches[]; .path == "src/Services/TenantResolver.php")
  and any(.matches[]; .path == "routes/web.php")
  and any(.matches[]; .path == "tests/Feature/TenantResolverTest.php")
' /tmp/uc-c-refs.json

AI_OUTPUT=json bash scripts/ai/ai-search.sh history TenantResolver . > /tmp/uc-c-history.json
jq -e '.status == "ok" and (.commits | length >= 1)' /tmp/uc-c-history.json

AI_OUTPUT=json bash scripts/ai/ai-search.sh docs TENANT_DEFAULT_REGION . > /tmp/uc-c-docs.json
jq -e '.status == "ok" and any(.matches[]; .path == "docs/tenant.md")' /tmp/uc-c-docs.json
```

- [ ] **Result:** PASS / FAIL

---

### Use case D — monorepo/package

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh text TenantResolver src --type php > /tmp/uc-d-src.json
jq -e '.status == "ok" and all(.matches[]; .path | startswith("src/"))' /tmp/uc-d-src.json

AI_OUTPUT=json bash scripts/ai/ai-search.sh text FakeTenant . > /tmp/uc-d-excludes.json
jq -e '
  .status == "no_matches"
  or all(.matches[]; ((.path | contains("vendor/")) or (.path | contains("node_modules/"))) | not)
' /tmp/uc-d-excludes.json

AI_OUTPUT=json bash scripts/ai/ai-search.sh text Tenant . --max-results 2 > /tmp/uc-d-limits.json
jq -e '.status == "ok" and (.matches | length <= 2) and (.limits.max_results == 2)' /tmp/uc-d-limits.json
```

- [ ] **Result:** PASS / FAIL

---

### Use case E — sibling project context

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh external-files . > /tmp/uc-e-files.json
jq -e '.status == "ok" and .searched_content == false and any(.roots[]; .path | contains("../shared-lib"))' /tmp/uc-e-files.json

AI_OUTPUT=json bash scripts/ai/ai-search.sh text SharedTenant ../shared-lib > /tmp/uc-e-blocked.json
jq -e '.status == "blocked"' /tmp/uc-e-blocked.json

AI_OUTPUT=json bash scripts/ai/ai-search.sh external-text SharedTenant --root ../shared-lib > /tmp/uc-e-text.json
jq -e '.status == "ok" and any(.matches[]; .root_type == "external")' /tmp/uc-e-text.json
```

- [ ] **Result:** PASS / FAIL

---

### Use case F — security-sensitive patterns

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh unsafe-patterns . > /tmp/uc-f-unsafe.json
jq -e '
  .status == "ok"
  and any(.matches[];
    .path == "src/Services/TenantResolver.php"
    and (.text | contains("unserialize"))
    and (.severity != null)
  )
  and all(.matches[];
    (.path | contains("vendor/") | not)
    and (.path | contains("node_modules/") | not)
  )
' /tmp/uc-f-unsafe.json
```

- [ ] **Result:** PASS / FAIL

---

## 5. Final acceptance checklist

- [ ] JSON mode is activated by `AI_OUTPUT=json` (env), invoked as `bash scripts/ai/ai-search.sh`; a literal `--json` flag is NOT a contract requirement.
- [ ] Every mode returns one of: `ok`, `no_matches`, `error`, `unavailable`, `blocked`, `dry_run`.
- [ ] Every content-search mode requires a query; file-list modes do not.
- [ ] JSON results are structured objects, not raw `file:line:text` strings.
- [ ] PHP paths with `:` are parsed safely (skip on Windows).
- [ ] Context lines are separated from the matched line and bound to the same object.
- [ ] Default limits prevent huge output; truncation is reported in `meta`.
- [ ] `vendor`, `node_modules`, `dist`, `build`, `coverage`, `.git` excluded by default.
- [ ] Git modes distinguish unstaged, staged, committed/history, and base-diff state, and error on non-git roots.
- [ ] Structural modes return name, kind, file, start line, end line (when available), language.
- [ ] Outside-root search is blocked unless `--allow-outside-root`; symlink escape does not leak.
- [ ] Dangerous roots (`/`, `/etc`, `/var`, `/usr`, `$HOME`) are blocked by default.
- [ ] Missing core tools (`rg`/`jq`/`git`) → `error`; missing `ast-grep` → `unavailable`.
- [ ] All assertions use `any(.matches[]; A and B)` binding (no split-`select()`).
```
